<?php
/* 
 * Read QR for SwissQR-Bill
 */
require('qrstd.php');

use function BizCuit\SwissQR\verify_qrdata;
use function BizCuit\SwissQR\bexio_from_qrdata;
use Zxing\QrReader;

/* convert: https://imagemagick.org/
 * 
 * /!\ zbarimg is not used as it doesn't work with the QR code on the swissqr,
 * the documentation require to have a swiss flag in the middle of the QR code
 * which trips zbarimg.
 */
require(__DIR__ . '/vendor/autoload.php');

define('IMGCONV', '/usr/bin/convert');
define('DATADIR', './data/');
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

$filename = escapeshellarg(realpath($argv[1]));
$dir = DATADIR . '/' . md5($filename);
mkdir($dir, 0777, true);

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
	 */
	exec(IMGCONV . ' -density ' . DENSITY . ' -colorspace gray -background white -alpha remove -alpha off  -gravity SouthWest -crop  ' . sprintf('%dx%d+%d+%d', QRSIZE, QRSIZE, XPOS, YPOS) . ' -rotate ' . $rotate . ' ' . $filename . ' ' . $dir . '/o.png');
	$dh = opendir($dir);
	if (!$dh) { break; }
	while(($file = readdir($dh)) !== false) {
		if ($file === '.' || $file === '..') { continue; }
		$output = [];
		$rescode = 0;
		$qrreader = new QRReader($dir.'/'.$file);
		$text = $qrreader->text($output);
		if ($text === false) { continue; }
		/* documentation says only CR+LF or LF allowed, but we allow for almost
		 * anything.
		 */
		$output = preg_split("/\r\n|\n\r|\n|\r/", $text);
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
rewinddir($dh);
while(($file = readdir($dh)) !== false) {
	if ($file === '.' || $file === '..') { continue; }
	unlink($dir . '/' . $file);
}
rmdir($dir);


if(!$found) { return -1; }
if (!verify_qrdata($output)) { return -1; }
echo json_encode(bexio_from_qrdata($output), JSON_PRETTY_PRINT);