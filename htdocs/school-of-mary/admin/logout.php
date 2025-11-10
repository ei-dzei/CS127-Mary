<?php
require_once __DIR__ . '/../partials/site_header.php';
unset($_SESSION['admin_user']);
session_regenerate_id(true);
header('Location: /public/');
exit;
