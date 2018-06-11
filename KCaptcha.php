<?php
/**
 * KCAPTCHA PROJECT VERSION 2.0
 * Automatic test to tell computers and humans apart
 * Copyright by Kruglov Sergei, 2006, 2007, 2008, 2011
 * www.captcha.ru, www.kruglov.ru
 * System requirements: PHP 4.0.6+ w/ GD
 * KCAPTCHA is a free software. You can freely use it for developing own site or software.
 * If you use this software as a part of own sofware, you must leave copyright notices intact or add KCAPTCHA copyright notices to own.
 * As a default configuration, KCAPTCHA has a small credits text at bottom of CAPTCHA image.
 * You can remove it, but I would be pleased if you left it. ;)
 * See kcaptcha_config.php for customization
 */

namespace k_captcha;

/**
 * Class KCaptcha
 * @package k_captcha
 *
 * @property string $keystring @todo needed test
 */
class KCaptcha
{
	/**
	 * Do not change without changing font files!
	 * @var string
	 */
	private $_alphabet = '0123456789abcdefghijklmnopqrstuvwxyz';

	/**
	 * Symbols used to draw CAPTCHA.
	 * Alphabet without similar symbols (o=0, 1=l, i=j, t=f)
	 * @var string
	 */
	private $_allowedSymbols = '23456789abcdegikpqsvxyz';

	/**
	 * Folder with fonts
	 * @var string
	 */
	private $_fontsDir = 'fonts';

	/**
	 * CAPTCHA string length
	 *  random 5 or 6 or 7
	 * @var int
	 */
	public $length;

	/**
	 * CAPTCHA image size (you do not need to change it, this parameters is optimal)
	 * @var int
	 */
	public $width;
	public $height;

	/**
	 * Symbol's vertical fluctuation amplitude
	 * @var int
	 */
	public $fluctuationAmplitude;

	/**
	 * Noise
	 * $white_noise_density=0; // no white noise
	 * $black_noise_density=0; // no black noise
	 * @var
	 */
	public $whiteNoiseDensity;
	public $blackNoiseDensity;

	/**
	 * Increase safety by prevention of spaces between symbols
	 * @var bool
	 */
	public $noSpaces;

	/**
	 * Show credits
	 * set to false to remove credits line. Credits adds 12 pixels to image height
	 * @var bool1
	 */
	public $showCredits;

	/**
	 * If empty, HTTP_HOST will be shown
	 * @var string
	 */
	public $credits;

	/**
	 * CAPTCHA image colors (RGB, 0-255)
	 * @var array
	 */
	public $foregroundColor;
	public $backgroundColor;

	/**
	 * JPEG quality of CAPTCHA image (bigger is better quality, but larger file size)
	 * @var int
	 */
	public $jpegQuality;

	/**
	 * KCaptcha constructor.
	 */
	public function __construct() {
		$this->length = mt_rand(5, 7);
		$this->width = 160;
		$this->height = 80;
		$this->fluctuationAmplitude = 8;
		$this->whiteNoiseDensity = 1 / 6;
		$this->blackNoiseDensity = 1 / 30;
		$this->noSpaces = true;
		$this->showCredits = false;
		$this->credits = '';
		$this->foregroundColor = array(mt_rand(0, 80), mt_rand(0, 80), mt_rand(0, 80));
		$this->backgroundColor = array(mt_rand(220, 255), mt_rand(220, 255), mt_rand(220, 255));
		$this->jpegQuality = 90;
	}

	/**
	 * Generates keystring and image
	 * KCaptcha constructor.
	 */
	public function KCAPTCHA() {
		$fonts = array();
		$fontsdirAbsolute = __DIR__.DIRECTORY_SEPARATOR.$this->_fontsDir;
		if ($handle = opendir($fontsdirAbsolute)) {
			while (false !== ($file = readdir($handle))) {
				if (preg_match('/\.png$/i', $file)) {
					$fonts[] = $fontsdirAbsolute.'/'.$file;
				}
			}
			closedir($handle);
		}
	
		$alphabetLength = strlen($this->_alphabet);
		
		do {
			// generating random keystring
			while (true) {
				$this->keystring = '';
				for ($i = 0; $i < $this->length; $i++) {
					$this->keystring .= $this->_allowedSymbols{mt_rand(0, strlen($this->_allowedSymbols) - 1)};
				}
				if (!preg_match('/cp|cb|ck|c6|c9|rn|rm|mm|co|do|cl|db|qp|qb|dp|ww/', $this->keystring)) {
					break;
				}
			}
		
			$fontFile = $fonts[mt_rand(0, count($fonts) - 1)];
			$font = imagecreatefrompng($fontFile);
			imagealphablending($font, true);

			$fontfileWidth = imagesx($font);
			$fontfileHeight = imagesy($font)-1;
			
			$fontMetrics = array();
			$symbol = 0;
			$readingSymbol = false;

			// loading font
			for ($i = 0; $i < $fontfileWidth && $symbol < $alphabetLength; $i++) {
				$transparent = (imagecolorat($font, $i, 0) >> 24) == 127;

				if (!$readingSymbol && !$transparent) {
					$fontMetrics[$this->_alphabet{$symbol}] = array('start' => $i);
					$readingSymbol = true;
					continue;
				}

				if ($readingSymbol && $transparent) {
					$fontMetrics[$this->_alphabet{$symbol}]['end'] = $i;
					$readingSymbol = false;
					$symbol++;
					continue;
				}
			}

			$img = imagecreatetruecolor($this->width, $this->height);
			imagealphablending($img, true);
			$white = imagecolorallocate($img, 255, 255, 255);
			$black = imagecolorallocate($img, 0, 0, 0);

			imagefilledrectangle($img, 0, 0, $this->width - 1, $this->height - 1, $white);

			// draw text
			$x = 1;
			$odd = mt_rand(0,1);
			if ($odd == 0) {
				$odd =- 1;
			}
			for ($i = 0; $i < $this->length; $i++) {
				$m = $fontMetrics[$this->keystring{$i}];

				$y = (($i % 2) * $this->fluctuationAmplitude - $this->fluctuationAmplitude / 2) * $odd
					+ mt_rand(-round($this->fluctuationAmplitude / 3), round($this->fluctuationAmplitude / 3))
					+ ($this->height - $fontfileHeight) / 2;

				if ($this->noSpaces) {
					$shift = 0;
					if ($i > 0) {
						$shift = 10000;
						for ($sy = 3; $sy < $fontfileHeight-10; $sy += 1) {
							for ($sx = $m['start'] - 1; $sx < $m['end']; $sx += 1) {
								$rgb = imagecolorat($font, $sx, $sy);
								$opacity = $rgb>>24;
								if ($opacity < 127) {
									$left = $sx - $m['start'] + $x;
									$py = $sy + $y;
									if ($py > $this->height) {
										break;
									}
									for ($px = min($left, $this->width-1); $px > $left - 200 && $px >= 0; $px -= 1) {
										$color = imagecolorat($img, $px, $py) & 0xff;
										if ($color + $opacity < 170) { // 170 - threshold
											if ($shift > $left - $px) {
												$shift = $left - $px;
											}
											break;
										}
									}
									break;
								}
							}
						}
						if ($shift == 10000) {
							$shift = mt_rand(4,6);
						}
					}
				} else {
					$shift = 1;
				}
				imagecopy($img, $font, $x - $shift, $y, $m['start'], 1, $m['end'] - $m['start'], $fontfileHeight);
				$x += $m['end'] - $m['start'] - $shift;
			}
		} while ($x >= $this->width - 10); // while not fit in canvas

		//noise
		$white = imagecolorallocate($font, 255, 255, 255);
		$black = imagecolorallocate($font, 0, 0, 0);
		for ($i = 0; $i < (($this->height - 30) * $x) * $this->whiteNoiseDensity; $i++) {
			imagesetpixel($img, mt_rand(0, $x - 1), mt_rand(10, $this->height - 15), $white);
		}
		for ($i = 0; $i < (($this->height - 30) * $x) * $this->blackNoiseDensity; $i++) {
			imagesetpixel($img, mt_rand(0, $x - 1), mt_rand(10, $this->height - 15), $black);
		}

		$center = $x / 2;

		// credits. To remove, see configuration file
		$img2 = imagecreatetruecolor($this->width, $this->height + ($this->showCredits ? 12 : 0));
		$foreground = imagecolorallocate($img2, $this->foregroundColor[0], $this->foregroundColor[1], $this->foregroundColor[2]);
		$background = imagecolorallocate($img2, $this->backgroundColor[0], $this->backgroundColor[1], $this->backgroundColor[2]);
		imagefilledrectangle($img2, 0, 0, $this->width - 1, $this->height - 1, $background);
		imagefilledrectangle($img2, 0, $this->height, $this->width - 1, $this->height + 12, $foreground);
		$credits = empty($this->credits) ? $_SERVER['HTTP_HOST'] : $this->credits;
		imagestring($img2, 2, $this->width / 2 - imagefontwidth(2) * strlen($credits) / 2, $this->height - 2, $credits, $background);

		// periods
		$rand1 = mt_rand(750000, 1200000) / 10000000;
		$rand2 = mt_rand(750000, 1200000) / 10000000;
		$rand3 = mt_rand(750000, 1200000) / 10000000;
		$rand4 = mt_rand(750000, 1200000) / 10000000;
		// phases
		$rand5 = mt_rand(0, 31415926) / 10000000;
		$rand6 = mt_rand(0, 31415926) / 10000000;
		$rand7 = mt_rand(0, 31415926) / 10000000;
		$rand8 = mt_rand(0, 31415926) / 10000000;
		// amplitudes
		$rand9 = mt_rand(330, 420) / 110;
		$rand10 = mt_rand(330, 450) / 100;

		//wave distortion
		for ($x = 0; $x < $this->width; $x++) {
			for ($y = 0; $y < $this->height; $y++) {
				$sx = $x + (sin($x * $rand1 + $rand5) + sin($y * $rand3 + $rand6)) * $rand9 - $this->width / 2 + $center + 1;
				$sy = $y + (sin($x * $rand2 + $rand7) + sin($y * $rand4 + $rand8)) * $rand10;

				if ($sx < 0 || $sy < 0 || $sx >= $this->width - 1 || $sy >= $this->height - 1) {
					continue;
				} else {
					$color = imagecolorat($img, $sx, $sy) & 0xFF;
					$color_x = imagecolorat($img, $sx + 1, $sy) & 0xFF;
					$color_y = imagecolorat($img, $sx, $sy + 1) & 0xFF;
					$color_xy = imagecolorat($img, $sx + 1, $sy + 1) & 0xFF;
				}

				if ($color == 255 && $color_x == 255 && $color_y == 255 && $color_xy == 255) {
					continue;
				} else if ($color == 0 && $color_x == 0 && $color_y == 0 && $color_xy == 0) {
					$newred = $this->foregroundColor[0];
					$newgreen = $this->foregroundColor[1];
					$newblue = $this->foregroundColor[2];
				} else {
					$frsx = $sx - floor($sx);
					$frsy = $sy - floor($sy);
					$frsx1 = 1 - $frsx;
					$frsy1 = 1 - $frsy;

					$newcolor = (
						$color * $frsx1 * $frsy1 +
						$color_x * $frsx * $frsy1 +
						$color_y * $frsx1 * $frsy +
						$color_xy * $frsx * $frsy);

					if ($newcolor > 255) {
						$newcolor = 255;
					}
					$newcolor = $newcolor / 255;
					$newcolor0 = 1 - $newcolor;

					$newred = $newcolor0 * $this->foregroundColor[0] + $newcolor * $this->backgroundColor[0];
					$newgreen = $newcolor0 * $this->foregroundColor[1] + $newcolor * $this->backgroundColor[1];
					$newblue = $newcolor0 * $this->foregroundColor[2] + $newcolor * $this->backgroundColor[2];
				}

				imagesetpixel($img2, $x, $y, imagecolorallocate($img2, $newred, $newgreen, $newblue));
			}
		}
		
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); 
		header('Cache-Control: no-store, no-cache, must-revalidate'); 
		header('Cache-Control: post-check=0, pre-check=0', FALSE); 
		header('Pragma: no-cache');
		if (function_exists('imagejpeg')) {
			header('Content-Type: image/jpeg');
			imagejpeg($img2, null, $this->jpegQuality);
		} else if(function_exists('imagegif')) {
			header('Content-Type: image/gif');
			imagegif($img2);
		} else if(function_exists('imagepng')) {
			header('Content-Type: image/x-png');
			imagepng($img2);
		}
	}

	/**
	 * Returns keystring
	 * @return string
	 */
	public function getKeyString()
	{
		return $this->keystring;
	}

	/**
	 * Preparing PNG fonts to use with KCAPTCHA.
	 * Reads files from folder "../fonts0", scans for symbols ans spaces and writes new font file with cached symbols positions to folder "../fonts"
	 */
	public function fontPrepare() {
		if ($handle = opendir('../fonts0')) {
			while (false !== ($file = readdir($handle))) {
				if ($file === '.' || $file === '..') {
					continue;
				}

				$img = imagecreatefrompng('../fonts0/'.$file);
				imageAlphaBlending($img, false);
				imageSaveAlpha($img, true);
				$transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
				$white = imagecolorallocate($img, 255, 255, 255);
				$black = imagecolorallocate($img, 0, 0, 0);
				$gray = imagecolorallocate($img, 100, 100, 100);

				for ($x = 0; $x < imagesx($img); $x++) {
					$space = true;
					$column_opacity = 0;
					for ($y = 1; $y < imagesy($img); $y++) {
						$rgb = ImageColorAt($img, $x, $y);
						$opacity = $rgb>>24;
						if ($opacity !== 127) {
							$space = false;
						}
						$column_opacity += 127 - $opacity;
					}
					if (!$space) {
						imageline($img, $x, 0, $x, 0, $column_opacity < 200 ? $gray : $black);
					}
				}
				imagepng($img, '../fonts/'.$file);
			}
			closedir($handle);
		}
	}
}
