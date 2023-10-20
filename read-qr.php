<?php
/* 
 * Read QR for SwissQR-Bill
 */
declare(strict_types=1);

namespace BizCuit\SwissQR;
require(__DIR__ . '/qrstd.php');

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
define('QRSIZE', (int) round(52 * RATIO));
// must be 67mm from the left of the page
define('XPOS', (int) round(66 * RATIO));
// YPOS is 37mm from bottom of the page (bottom of QR).
define('YPOS', (int) round(38 * RATIO));


function read_qr_data(string $file): false|array {
	try {
		$filename = realpath($file);
		if (!$filename) { return false; }
		if (!is_file($filename) && !is_readable($filename)) { return false; }

		$rotate = 0;
		$found = false;
		/* Convert input file to png, crop to where the QR code must be according to
		* swissqr documentation, try to get the qr code, rotate the image 90 degrees
		* and try again until a qr code is found.
		*/
		$IMagick = new Imagick();
		if (!$IMagick->setResolution(DENSITY, DENSITY)) { return false; }
		if (!$IMagick->readImage($filename)) { return false; }

		$whiteColor = new ImagickPixel('white');
		if (!$IMagick->setImageBackgroundColor($whiteColor)) { return false; }
		do {		
			for ($i = 0; $i < $IMagick->getNumberImages(); $i++) {
				$IMagick->setIteratorIndex($i);
				$im = $IMagick->getImage();
				
				$h = $im->getImageHeight();
				if ($h < QRSIZE) { continue; }
				$im->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
				$im->cropImage(QRSIZE, QRSIZE, XPOS, $h - (YPOS + QRSIZE));
				if ($rotate > 0) { $im->rotateImage($whiteColor, $rotate); }
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

		/* trim and verify */
		$output = trim_qrdata($output);
		if ($found && verify_qrdata($output)) { return $output; }
	} catch (Exception $e) {
		throw new Exception('Error decoding SwissQR', 0, $e);
	}

	return false;
}