<?php
include 'KCaptcha.php';

session_start();

$captcha = new \k_captcha\KCaptcha();

if ($_REQUEST[session_name()]) {
	$_SESSION['captcha'] = $captcha->keyString;
}
?>
<img src="example.php?<?= session_name(); ?>=<?= session_id(); ?>">