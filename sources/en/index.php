<?php
if(!isset($_SESSION)) {
	session_start(); 
}
$_SESSION['lang'] = 'en';

header('Location: /?lang=en');
exit;
