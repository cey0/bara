<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Container;
use Framework\Exceptions\HttpException;
use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Route table with `{param}` placeholders. Actions can be closures,
 * "Controller@method" strings, or [Controller::class, 'method'] arrays.
 */
final class Router
{
    /** @var list<array{method: string, uri: string, pattern: string, action: mixed}> */
    private array $routes = [];

    public function __construct(private Container $container)
    {
    }

    public function get(string $uri, $action): void
    {
        $this->add('GET', $uri, $action);
    }

    public function post(string $uri, $action): void
    {
        $this->add('POST', $uri, $action);
    }

    public function put(string $uri, $action): void
    {
        $this->add('PUT', $uri, $action);
    }

    public function patch(string $uri, $action): void
    {
        $this->add('PATCH', $uri, $action);
    }

    public function delete(string $uri, $action): void
    {
        $this->add('DELETE', $uri, $action);
    }

    public function add(string $method, string $uri, $action): void
    {
        $uri = '/' . trim($uri, '/');

        // `{post}` becomes a named capture group, so route parameters can be
        // injected into controller methods by argument name. Literal parts are
        // quoted, so `/feed.xml` does not behave like a regex wildcard.
        $pattern = '';
        $offset = 0;

        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $uri, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $index => $match) {
            $pattern .= preg_quote(substr($uri, $offset, $match[1] - $offset), '#') . '(?P<' . $matches[1][$index][0] . '>[^/]+)';
            $offset = $match[1] + strlen($match[0]);
        }

        $pattern .= preg_quote(substr($uri, $offset), '#');

        $this->routes[] = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'pattern' => '#^' . $pattern . '/?$#',
            'action' => $action,
        ];
    }

    public function dispatch(Request $request): Response
    {
        // Make the current request resolvable by type-hint in controllers.
        $this->container->instance(Request::class, $request);

        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $path = '/' . trim($request->path(), '/');
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            $parameters = array_map(
                'rawurldecode',
                array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY)
            );

            return $this->run($route['action'], $parameters);
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'Metode HTTP tidak diizinkan untuk ' . $path);
        }

        throw new HttpException(404, 'Halaman tidak ditemukan: ' . $path);
    }

    /**
     * @return list<array{method: string, uri: string, pattern: string, action: mixed}>
     */
    public function routes(): array
    {
        return $this->routes;
    }

    private function run($action, array $parameters): Response
    {
        if (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
            $action = [$controller, $method];
        }

        $result = $this->container->call($action, $parameters);

        if ($result instanceof Response) {
            return $result;
        }

        return Response::html(is_string($result) ? $result : '');
    }
}
