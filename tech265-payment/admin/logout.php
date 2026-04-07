<?php
require_once __DIR__ . '/../src/AdminAuth.php';
AdminAuth::startSession();
AdminAuth::logout();
header('Location: ' . APP_URL . '/admin/login.php');
exit;
