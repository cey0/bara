<?php

declare(strict_types=1);

use Framework\Application;

$app = new Application(dirname(__DIR__));

$app->bootHttp();
$app->loadRoutes(__DIR__ . '/../app/routes.php');

return $app;
