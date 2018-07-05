<?php
/**
 * New implementation - Moiseenko Evgeniy. 2018
 * The original idea - Kruglov Sergei
 *
 * KCAPTCHA PROJECT VERSION 2.0
 * Automatic test to tell computers and humans apart
 * Copyright by Kruglov Sergei, 2006, 2007, 2008, 2011
 * www.captcha.ru, www.kruglov.ru
 * System requirements: PHP 4.0.6+ w/ GD
 * KCAPTCHA is a free software. You can freely use it for developing own site or software.
 * If you use this software as a part of own software, you must leave copyright notices intact or add KCAPTCHA copyright notices to own.
 * As a default configuration, KCAPTCHA has a small credits text at bottom of CAPTCHA image.
 */

namespace k_captcha;

/**
 * Class KCaptcha
 * @package k_captcha
 *
 * @property string $keystring
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
	 * Random 5 or 6 or 7
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
	 * $whiteNoiseDensity = 0; // no white noise
	 * $blackNoiseDensity = 0; // no black noise
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
	 * @var bool
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
	 * @throws \Exception
	 */
	public function __construct()
	{
		$this->length = random_int(5, 7);
		$this->width = 160;
		$this->height = 80;
		$this->fluctuationAmplitude = 8;
		$this->whiteNoiseDensity = 1 / 6;
		$this->blackNoiseDensity = 1 / 30;
		$this->noSpaces = true;
		$this->showCredits = false;
		$this->credits = '';
		$this->foregroundColor = [random_int(0, 80), random_int(0, 80), random_int(0, 80)];
		$this->backgroundColor = [random_int(220, 255), random_int(220, 255), random_int(220, 255)];
		$this->jpegQuality = 90;

		$this->_init();
	}

	/**
	 * Generates key string and image
	 * @throws \Exception
	 */
	private function _init(): void
	{
		$fonts = [];
		$fontsDirAbsolute = __DIR__.DIRECTORY_SEPARATOR.$this->_fontsDir;
		if ($handle = opendir($fontsDirAbsolute)) {
			while (false !== ($file = readdir($handle))) {
				if (preg_match('/\.png$/i', $file)) {
					$fonts[] = $fontsDirAbsolute.'/'.$file;
				}
			}
			closedir($handle);
		}
	
		$alphabetLength = \strlen($this->_alphabet);

		do {
			$this->_generateKeyString();

			$font = imagecreatefrompng($fonts[random_int(0, \count($fonts) - 1)]);
			imagealphablending($font, true);

			$fontFileWidth = imagesx($font);
			$fontFileHeight = imagesy($font) - 1;
			$fontMetrics = [];

			$symbol = 0;
			$readingSymbol = false;

			//Loading font
			for ($i = 0; $i < $fontFileWidth && $symbol < $alphabetLength; $i++) {
				$transparent = (imagecolorat($font, $i, 0) >> 24) === 127;

				if (!$readingSymbol && !$transparent) {
					$fontMetrics[$this->_alphabet{$symbol}] = ['start' => $i];
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
			imagefilledrectangle($img, 0, 0, $this->width - 1, $this->height - 1, $white);

			//Draw text
			$x = 1;
			$odd = random_int(0, 1);
			if ($odd === 0) {
				--$odd;
			}
			for ($i = 0; $i < $this->length; $i++) {
				$m = $fontMetrics[$this->keystring{$i}];
				$y = (($i % 2) * $this->fluctuationAmplitude - $this->fluctuationAmplitude / 2) * $odd
					+ random_int(-round($this->fluctuationAmplitude / 3), round($this->fluctuationAmplitude / 3))
					+ ($this->height - $fontFileHeight) / 2;

				$shift = 1;
				if ($this->noSpaces) {
					$shift = 0;
					if ($i > 0) {
						$shift = 10000;
						for ($sy = 3; $sy < $fontFileHeight - 10; ++$sy) {
							for ($sx = $m['start'] - 1; $sx < $m['end']; ++$sx) {
								$opacity = imagecolorat($font, $sx, $sy)>>24;
								if ($opacity < 127) {
									$left = $sx - $m['start'] + $x;
									$py = $sy + $y;
									if ($py > $this->height) {
										break;
									}
									for ($px = min($left, $this->width-1); $px > $left - 200 && $px >= 0; --$px) {
										if (imagecolorat($img, $px, $py) & 0xff + $opacity < 170) { //170 - threshold
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
						if ($shift === 10000) {
							$shift = random_int(4,6);
						}
					}
				}
				imagecopy($img, $font, $x - $shift, $y, $m['start'], 1, $m['end'] - $m['start'], $fontFileHeight);
				$x += $m['end'] - $m['start'] - $shift;
			}
		} while ($x >= $this->width - 10); // while not fit in canvas

		$this->_noise($font, $img, $x);

		//Credits. To remove, see configuration file
		$img2 = imagecreatetruecolor($this->width, $this->height + ($this->showCredits ? 12 : 0));
		$foreground = imagecolorallocate($img2, $this->foregroundColor[0], $this->foregroundColor[1], $this->foregroundColor[2]);
		$background = imagecolorallocate($img2, $this->backgroundColor[0], $this->backgroundColor[1], $this->backgroundColor[2]);
		imagefilledrectangle($img2, 0, 0, $this->width - 1, $this->height - 1, $background);
		imagefilledrectangle($img2, 0, $this->height, $this->width - 1, $this->height + 12, $foreground);
		$credits = empty($this->credits) ? $_SERVER['HTTP_HOST'] : $this->credits;
		imagestring($img2, 2, $this->width / 2 - imagefontwidth(2) * \strlen($credits) / 2, $this->height - 2, $credits, $background);

		//Periods
		$rand[] = random_int(750000, 1200000) / 10000000;
		$rand[] = random_int(750000, 1200000) / 10000000;
		$rand[] = random_int(750000, 1200000) / 10000000;
		$rand[] = random_int(750000, 1200000) / 10000000;
		//Phases
		$rand[] = random_int(0, 31415926) / 10000000;
		$rand[] = random_int(0, 31415926) / 10000000;
		$rand[] = random_int(0, 31415926) / 10000000;
		$rand[] = random_int(0, 31415926) / 10000000;
		//Amplitudes
		$rand[] = random_int(330, 420) / 110;
		$rand[] = random_int(330, 450) / 100;

		$this->_waveDistortion($rand, $x / 2, $img, $img2);
		$this->_setHeader($img2);
	}

	/**
	 * Generating random key string
	 * @throws \Exception
	 */
	private function _generateKeyString(): void
	{
		while (true) {
			$this->keystring = '';
			for ($i = 0; $i < $this->length; $i++) {
				$this->keystring .= $this->_allowedSymbols{random_int(0, \strlen($this->_allowedSymbols) - 1)};
			}
			if (!preg_match('/cp|cb|ck|c6|c9|rn|rm|mm|co|do|cl|db|qp|qb|dp|ww/', $this->keystring)) {
				break;
			}
		}
	}

	/**
	 * Noise
	 * @param resource $font
	 * @param resource $img
	 * @param int $x
	 * @throws \Exception
	 */
	private function _noise($font, $img, $x): void
	{
		$white = imagecolorallocate($font, 255, 255, 255);
		$black = imagecolorallocate($font, 0, 0, 0);
		for ($i = 0; $i < (($this->height - 30) * $x) * $this->whiteNoiseDensity; $i++) {
			imagesetpixel($img, random_int(0, $x - 1), random_int(10, $this->height - 15), $white);
		}
		for ($i = 0; $i < (($this->height - 30) * $x) * $this->blackNoiseDensity; $i++) {
			imagesetpixel($img, random_int(0, $x - 1), random_int(10, $this->height - 15), $black);
		}
	}

	/**
	 * Wave distortion
	 * @param array $rand
	 * @param int $center
	 * @param $img
	 * @param $img2
	 */
	private function _waveDistortion($rand, $center, $img, $img2): void
	{
		for ($x = 0; $x < $this->width; $x++) {
			for ($y = 0; $y < $this->height; $y++) {
				$sx = $x + (sin($x * $rand[0] + $rand[5]) + sin($y * $rand[2] + $rand[5])) * $rand[8] - $this->width / 2 + $center + 1;
				$sy = $y + (sin($x * $rand[1] + $rand[6]) + sin($y * $rand[3] + $rand[7])) * $rand[9];

				if ($sx < 0 || $sy < 0 || $sx >= $this->width - 1 || $sy >= $this->height - 1) {
					continue;
				}
				$color = imagecolorat($img, $sx, $sy) & 0xFF;
				$colorX = imagecolorat($img, $sx + 1, $sy) & 0xFF;
				$colorY = imagecolorat($img, $sx, $sy + 1) & 0xFF;
				$colorXY = imagecolorat($img, $sx + 1, $sy + 1) & 0xFF;

				if ($color === 255 && $colorX === 255 && $colorY === 255 && $colorXY === 255) {
					continue;
				}
				if ($color === 0 && $colorX === 0 && $colorY === 0 && $colorXY === 0) {
					[$newRed, $newGreen, $newBlue] = $this->foregroundColor;
				} else {
					$frSX = $sx - floor($sx);
					$frSY = $sy - floor($sy);
					$frSX1 = 1 - $frSX;
					$frSY1 = 1 - $frSY;

					$newColor = (
						$color * $frSX1 * $frSY1 +
						$colorX * $frSX * $frSY1 +
						$colorY * $frSX1 * $frSY +
						$colorXY * $frSX * $frSY);

					if ($newColor > 255) {
						$newColor = 255;
					}
					$newColor /=  255;
					$newColor0 = 1 - $newColor;

					$newRed = $newColor0 * $this->foregroundColor[0] + $newColor * $this->backgroundColor[0];
					$newGreen = $newColor0 * $this->foregroundColor[1] + $newColor * $this->backgroundColor[1];
					$newBlue = $newColor0 * $this->foregroundColor[2] + $newColor * $this->backgroundColor[2];
				}

				imagesetpixel($img2, $x, $y, imagecolorallocate($img2, $newRed, $newGreen, $newBlue));
			}
		}
	}

	/**
	 * @param $img
	 */
	private function _setHeader($img): void
	{
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', false);
		header('Pragma: no-cache');
		if (\function_exists('imagejpeg')) {
			header('Content-Type: image/jpeg');
			imagejpeg($img, null, $this->jpegQuality);
		} else if(\function_exists('imagegif')) {
			header('Content-Type: image/gif');
			imagegif($img);
		} else if(\function_exists('imagepng')) {
			header('Content-Type: image/x-png');
			imagepng($img);
		}
	}

	/**
	 * Returns key string
	 * @return string
	 */
	public function getKeyString(): string
	{
		return $this->keystring;
	}

	/**
	 * Preparing PNG fonts to use with KCAPTCHA.
	 * Reads files from folder "../fonts0", scans for symbols ans spaces and writes new font file with cached symbols positions to folder "../fonts"
	 */
	public function fontPrepare(): void
	{
		if ($handle = opendir('../fonts0')) {
			while (false !== ($file = readdir($handle))) {
				if ($file === '.' || $file === '..') {
					continue;
				}

				$img = imagecreatefrompng('../fonts0/'.$file);
				imagealphablending($img, false);
				imagesavealpha($img, true);
				$black = imagecolorallocate($img, 0, 0, 0);
				$gray = imagecolorallocate($img, 100, 100, 100);

				$imgWidth = imagesx($img);
				for ($x = 0; $x < $imgWidth; $x++) {
					$space = true;
					$column_opacity = 0;
					$imgHeight = imagesy($img);
					for ($y = 1; $y < $imgHeight; $y++) {
						$rgb = imagecolorat($img, $x, $y);
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
