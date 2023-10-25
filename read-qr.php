<?php
/* 
 * Read QR for SwissQR-Bill
 */
declare(strict_types=1);

namespace BizCuit\SwissQR;
require(__DIR__ . '/qrstd.php');

use stdClass;
use DateTime;
use Exception;
use Imagick;
use ImagickPixel;
use Zxing\QrReader;

/* Below 300dpi, the quality is not good enough so that it doesn't always detect
 * the QR code.
 */
define('DENSITY', 300);
/* DENSITY is in INCHES per PIXELS, so 300 DPI is 300/25.4 = 11.811023622 pixels 
 * per mm 64mm is the size of the QR code according to swissqr documentation, 
 * 64mm is the left position on the page and 206mm is the top position on the
 * page.
 */	
define('RATIO', DENSITY / 25.4);
/* https://www.six-group.com/dam/download/banking-services/standardization/qr-bill/style-guide-qr-bill-fr.pdf */

// qrsize is 46mm + 5mm padding on each side
define('QRSIZE', (int) round(65 * RATIO));
// must be 67mm from the left of the page
define('XPOS', (int) round(58 * RATIO));
// YPOS is 37mm from bottom of the page (bottom of QR).
define('YPOS', (int) round(32 * RATIO));


function read_qr_data(string $blob, string &$error = ''): false|array {
	try {
		$rotate = 0;
		$found = false;
		/* Convert input file to png, crop to where the QR code must be according to
		* swissqr documentation, try to get the qr code, rotate the image 90 degrees
		* and try again until a qr code is found.
		*/
        $error = 'Error reading image';
		$IMagick = new Imagick();
		if (!$IMagick->setResolution(DENSITY, DENSITY)) { return false; }
		if (!$IMagick->readImageBlob($blob)) { return false; }

		$whiteColor = new ImagickPixel('white');
		if (!$IMagick->setImageBackgroundColor($whiteColor)) { return false; }
        $error = 'Detecting QRCode';
		do {		
			for ($i = 0; $i < $IMagick->getNumberImages(); $i++) {
				$IMagick->setIteratorIndex($i);
				$im = $IMagick->getImage();
				
				$h = $im->getImageHeight();
				if ($h < QRSIZE) { continue; }
				$im->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
				if ($rotate > 0) { $im->rotateImage($whiteColor, $rotate); }
                $im->cropImage(QRSIZE, QRSIZE, XPOS, $h - (YPOS + QRSIZE));
				$im->setImageFormat('png');
				$output = [];
				$qrreader = new QRReader($im->getImageBlob(), QRReader::SOURCE_TYPE_BLOB);
				$im->destroy();
				$text = $qrreader->text(['TRY_HARDER' => true]);
				if ($text === false) { continue; }
				/* documentation says only CR+LF or LF allowed (and some qrbill may
				* use CR only in communicaton). So strictly follow the
				* documentation
				*/
				$output = preg_split("/\r\n|\n/", $text);
				if ($output === false) { continue; }
				/* remove trailing spaces */
				$output = array_map('trim', $output);
				$lastidx = count($output) - 1;
				/* EPD must happend somewhere in the last 4 for lines */
				if ($output[0] === 'SPC' 
					&&
					($output[$lastidx] === 'EPD'
					|| $output[$lastidx - 1] === 'EPD'
					|| $output[$lastidx - 2] === 'EPD'
					|| $output[$lastidx - 3] === 'EPD')
				)  {
					$found = true;
					break;
				}

			}
			if($found) { break; }
			$rotate += 90;
		} while ($rotate != 360);
        
        $error = '';

        /* trim and verify */
		$output = trim_qrdata($output);
		if ($found && verify_qrdata($output, $error)) { return $output; }
        $error = 'QRCode verification failed: ' . $error;
	} catch (Exception $e) {
		throw new Exception('Error decoding SwissQR', 0, $e);
	}

	return false;
}

/* according to https://www.swico.ch/media/filer_public/1c/cd/1ccd7062-fc69-40f8-be3f-2a3ba9048c5f/v2_qr-bill-s1-syntax-fr.pdf */
function decode_swicov1 (array $qrcontent):false|stdClass {
    $bill = new stdClass;
	$bill->tva = new stdClass;

	/* after trailer, might not be set */
	if (!isset($qrcontent[QRCH\AddInf\StrdBkgInf])) { return false; }
	
	$content = $qrcontent[QRCH\AddInf\StrdBkgInf];
    if (empty($content)) { return false; }
    if (!str_starts_with($content,  '//')) { return false; }
    
	$parts = explode('/', substr($content, 2));
    if (array_shift($parts) !== 'S1') { return false; }
    while(!empty($parts)) {
        switch(array_shift($parts)) {
            case '10':
                /* free text */
                $bill->reference = array_shift($parts);
                break;
            case '11':
                $date = array_shift($parts);
                $bill->date = new DateTime();
                $bill->date->setDate(2000 + intval(substr($date, 0, 2)), intval(substr($date, 2, 2)), intval(substr($date, 4, 2)));
                break;
            case '20':
                $bill->reference = array_shift($parts);
                break;
            case '30':
                $bill->ide = array_shift($parts);
                break;
            case '31':
                $date = array_shift($parts);
                if (strlen($date) >= 6) {
                    $bill->tva->begin = new DateTime();
                    $bill->tva->begin->setDate(2000 + intval(substr($date, 0, 2)), intval(substr($date, 2, 2)), intval(substr($date, 4, 2)));
                }
                if (strlen($date) > 6) {
                    $bill->tva->end = new DateTime();
                    $bill->tva->end->setDate(2000 + intval(substr($date, 6, 2)), intval(substr($date, 8, 2)), intval(substr($date, 10, 2)));
                } else {
                    $bill->tva->end = $bill->tva->begin;
                }
                break;
            case '32':
                $bill->tva->details = [];
                $tva = array_shift($parts);
                $tva = explode(';', $tva);
                if (count($tva) === 1) {
                    $bill->tva->details[] = ['rate' => $tva[0], 'amount' => 0];
                    break;
                }
                foreach($tva as $t) {
                    $t = explode(':', $t);
                    if (count($t) === 2) {
                        $bill->tva->details[] = ['rate' => $tva[0], 'amount' => floatval($t[1])];
                    }
                }
                break;
            case '33':
                $bill->tva->import = [];
                $tva = array_shift($parts);
                $tva = explode(';', $tva);
                if (count($tva) === 1) {
                    $t = explode(':', $tva[0]);
                    if (count($t) === 2) {
                        $bill->tva->import[] = ['rate' => $t[0], 'amount' => floatval($t[1])];
                    }
                    break;
                }
                foreach($tva as $t) {
                    $t = explode(':', $t);
                    if (count($t) === 2) {
                        $bill->tva->import[] = ['rate' => $t[0], 'amount' => floatval($t[1])];
                    }
                }
                break;
            case '40':
                $bill->conditions = [];
                $conditions = array_shift($parts);
                $conditions = explode(';', $conditions);
                foreach($conditions as $condition) {
                    $condition = explode(':', $condition);
                    if (count($condition) === 2) {
                        $bill->conditions[] = ['reduction' => floatval($condition[0]), 'day' => intval($condition[1])];
                    }
                }
                break;
            default:
                /* unknown field we just remove value */
                array_shift($parts);
                continue 2;
        }
    }

    return $bill;
}