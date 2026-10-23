<?php
declare(strict_types=1); require_once __DIR__.'/../bootstrap.php'; Auth::requireRole('affiliate'); header('Location: '.url('/affiliate/dashboard.php')); exit;
