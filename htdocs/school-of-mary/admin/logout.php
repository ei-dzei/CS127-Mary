<?php
require_once __DIR__ . '/../config/utils.php';
require_once __DIR__ . '/auth.php';
logout_admin();
redirect('/login.php');
