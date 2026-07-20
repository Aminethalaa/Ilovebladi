<?php
require_once __DIR__ . '/includes/auth.php';
logout_user();
header('Location: ' . WEB_BASE . '/index.php');
exit;
