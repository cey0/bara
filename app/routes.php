<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\PostController;
use Framework\Routing\Router;

/** @var Router $router */
$router->get('/', [HomeController::class, 'index']);

$router->get('/posts', [PostController::class, 'index']);
$router->get('/posts/create', [PostController::class, 'create']);
$router->post('/posts', [PostController::class, 'store']);
$router->get('/posts/{post}', [PostController::class, 'show']);
$router->delete('/posts/{post}', [PostController::class, 'destroy']);
