<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
Auth::logout();
redirect('/auth/login.php');
