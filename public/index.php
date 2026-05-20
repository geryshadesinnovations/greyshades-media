<?php
/**
 * Greyshades Innovations - Front Controller
 * All HTTP traffic enters here.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH',  BASE_PATH . '/app');
define('PUBLIC_PATH', __DIR__);

require BASE_PATH . '/app/bootstrap.php';

$router = new \App\Core\Router();
require BASE_PATH . '/app/routes.php';
$router->dispatch();
