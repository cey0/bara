<?php

declare(strict_types=1);

use Framework\Application;
use Framework\Http\Response;
use Framework\Security\Csrf;
use Framework\View\View;

/**
 * Resolve a class from the service container, or return the application itself.
 */
function app(?string $abstract = null)
{
    $app = Application::getInstance();

    return $abstract === null ? $app : $app->container()->make($abstract);
}

/**
 * Read a config value using dot notation, e.g. config('database.driver').
 */
function config(string $key, $default = null)
{
    return Application::getInstance()->config()->get($key, $default);
}

function base_path(string $path = ''): string
{
    return Application::getInstance()->basePath($path);
}

/**
 * Escape a value for HTML output. Every dynamic value in a view goes through this.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Render a view inside a layout and return it as an HTTP response.
 */
function view(string $view, array $data = [], ?string $layout = 'layouts/app'): Response
{
    return Response::html(app(View::class)->render($view, $data, $layout));
}

function redirect(string $to, int $status = 302): Response
{
    return Response::redirect($to, $status);
}

function csrf_token(): string
{
    return Csrf::token();
}

/**
 * Hidden input with the CSRF token. Echo it raw: <?= csrf_field() ?>
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

/**
 * Hidden input that spoofs PUT/PATCH/DELETE from a regular HTML form.
 */
function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}
