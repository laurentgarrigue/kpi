<?php
require_once __DIR__ . '/../commun/ArchivedEndpoint.php'; archivedEndpointGuard(__FILE__);
$user = '';
if (isset($_GET['user'])) $user = $_GET['user'];

$pwd = '';
if (isset($_GET['pwd'])) $pwd = $_GET['pwd'];

echo md5($user.md5($pwd));
