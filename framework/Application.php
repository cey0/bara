<?php

declare(strict_types=1);

namespace Framework;

use Framework\Database\Connection;
use Framework\Exceptions\HttpException;
use Framework\Exceptions\ModelNotFoundException;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Router;
use Framework\Security\Csrf;
use Framework\View\View;
use Throwable;

/**
 * Application kernel: bootstraps the container, loads config and routes,
 * verifies CSRF on state-changing requests, dispatches, and renders errors.
 */
final class Application
{
    private static ?Application $instance = null;

    private Container $container;

    private Config $config;

    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
        self::$instance = $this;

        $this->container = new Container();
        $this->config = new Config();
        $this->config->load($this->basePath . '/config');

        $this->container->instance(self::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Config::class, $this->config);
        $this->container->instance(View::class, new View($this->basePath . '/views'));

        // One PDO connection per request, reused by every model.
        $this->container->singleton(Connection::class, function (Container $container): Connection {
            return new Connection($container->make(Config::class)->get('database'));
        });

        $this->container->singleton(Router::class, static function (Container $container): Router {
            return new Router($container);
        });
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('The application has not been booted.');
        }

        return self::$instance;
    }

    public function bootHttp(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
            // Session cookie hardening before the session starts.
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_start();
        }
    }

    public function loadRoutes(string $file): void
    {
        $router = $this->container->make(Router::class);

        require $file;
    }

    public function handle(Request $request): Response
    {
        try {
            if ($this->shouldVerifyCsrf($request)
                && ! Csrf::check($request->input('_token') ?? $request->header('X-CSRF-TOKEN'))) {
                throw new HttpException(419, 'CSRF token tidak cocok. Muat ulang form lalu coba lagi.');
            }

            return $this->container->make(Router::class)->dispatch($request);
        } catch (ModelNotFoundException $e) {
            return $this->renderError(404, $e->getMessage(), $e);
        } catch (HttpException $e) {
            return $this->renderError($e->getStatusCode(), $e->getMessage(), $e);
        } catch (Throwable $e) {
            return $this->renderError(500, 'Terjadi kesalahan pada server.', $e);
        }
    }

    public function run(Request $request): void
    {
        $this->handle($request)->send();
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path === '' ? '' : '/' . ltrim($path, '/\\'));
    }

    private function shouldVerifyCsrf(Request $request): bool
    {
        return PHP_SAPI !== 'cli'
            && (bool) $this->config->get('app.csrf', true)
            && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function renderError(int $status, string $message, Throwable $exception): Response
    {
        $debug = (bool) $this->config->get('app.debug', false);
        $data = [
            'status' => $status,
            'message' => $message,
            'exception' => $debug ? $exception : null,
        ];

        try {
            $html = $this->container->make(View::class)->render('errors/error', $data, 'layouts/app');
        } catch (Throwable $viewError) {
            $html = '<h1>' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return Response::html($html, $status);
    }
}
