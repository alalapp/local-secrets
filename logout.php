<?php
require_once __DIR__ . '/bootstrap.php';
Auth::logout();
header('Location: /local_secrets/login.php');
exit;
