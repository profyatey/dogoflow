<?php
declare(strict_types=1);

// Public entry-point bootstrap. It resolves the private core identically on XAMPP
// (/DogoFlow/public -> ../core) and AvesHost (public_html -> ../core).
$coreBootstrap = dirname(__DIR__) . '/core/bootstrap.php';
if (!is_file($coreBootstrap)) {
    http_response_code(500);
    exit('DogoFlow core bootstrap is not available.');
}
require_once $coreBootstrap;
