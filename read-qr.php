<?php
/* 
 * Read QR for SwissQR-Bill
 */
namespace BizCuit\SwissQR;
require(__DIR__ . '/vendor/autoload.php');
require(__DIR__ . '/qrstd.php');

use Zxing\QrReader;

// convert: https://imagemagick.org/
define('IMGCONV', '/usr/bin/convert');

define('DATADIR', '/tmp/bizcuit-swissqr');
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
define('QRSIZE', round(66 * RATIO));
// must be 62mm from the left of the page
define('XPOS', round(62 * RATIO));
// YPOS is 32mm from bottom of the page.
define('YPOS', round(32 * RATIO));

function read_qr_data(string $file, $basedir = null): false|array {
	$filename = escapeshellarg(realpath($file));
	$dir = ($basedir ?? DATADIR) . '/' . md5($filename);
	if (!@mkdir($dir, 0777, true)) { return false; }

	$rotate = 0;
	$found = false;
	/* Convert input file to png, crop to where the QR code must be according to
	* swissqr documentation, try to get the qr code, rotate the image 90 degrees
	* and try again until a qr code is found.
	*/
	do {
		/* -gravity SouthWest allows to position from the bottom left of the page
		* -crop 66x66+62+32 crops the image to 66x66 pixels starting at 62mm from
		* the left of the page and 32mm from the bottom of the page.
		* -rotate rotates the image by 90 degrees.
		* TODO replace with Imagick-PHP library
		*/
		$command = sprintf('%s -density %d -colorspace gray -background white -alpha remove -alpha off  -gravity SouthWest -crop  %dx%d+%d+%d -rotate %d %s %s/%s', IMGCONV, DENSITY, QRSIZE, QRSIZE, XPOS, YPOS, $rotate, $filename, $dir, '%05d_file.png');
		exec($command);
		$dh = opendir($dir);
		if (!$dh) { break; }
		while(($file = readdir($dh)) !== false) {
			if ($file === '.' || $file === '..') { continue; }
			$output = [];
			$qrreader = new QRReader($dir.'/'.$file);
			$text = $qrreader->text($output);
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

	/* cleanup */
	if ($dh) {
		rewinddir($dh);
		while(($file = readdir($dh)) !== false) {
			if ($file === '.' || $file === '..') { continue; }
			@unlink($dir . '/' . $file);
		}
		closedir($dh);
	}
	@rmdir($dir);

	/* trim and verify */
	$output = trim_qrdata($output);
	if ($found && verify_qrdata($output)) { return $output; }

	return false;
}