<?php
if(!isset($_SESSION)) {
	session_start(); 
}
$_SESSION['lang'] = 'fr';

header('Location: /?lang=fr');
exit;
