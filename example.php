<?php
include 'KCaptcha.php';

session_start();

$captcha = new \k_captcha\KCaptcha();

if ($_REQUEST[session_name()]) {
	$_SESSION['captcha_keystring'] = $captcha->getKeyString();
}
