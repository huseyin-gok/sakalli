<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Basit HTTP router — GET/POST ve {param} desteği
 */
final class Router
{
    /** @var array<string, array<int, array{pattern:string, handler:callable|array}>> */
    private array $routes = [];

    public function get(string $path, callable|array $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable|array $handler): self
    {
        $this->routes[$method][] = [
            'pattern' => $path,
            'handler' => $handler,
        ];
        return $this;
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $uri = $uri === '' ? '/' : $uri;
        if (!str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }

        foreach ($this->routes[$method] ?? [] as $route) {
            $params = $this->match($route['pattern'], $uri);
            if ($params !== null) {
                $handler = $route['handler'];
                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    $controller = new $class();
                    $controller->$action(...array_values($params));
                } else {
                    $handler(...array_values($params));
                }
                return;
            }
        }

        http_response_code(404);
        echo '404 — Sayfa bulunamadı';
    }

    /**
     * @return array<string, string>|null
     */
    private function match(string $pattern, string $uri): ?array
    {
        $paramNames = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function ($m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '([^/]+)';
        }, $pattern);

        if ($regex === null) {
            return null;
        }
        $regex = '#^' . $regex . '$#';
        if (!preg_match($regex, $uri, $matches)) {
            return null;
        }
        array_shift($matches);
        $out = [];
        foreach ($paramNames as $i => $name) {
            $out[$name] = $matches[$i] ?? '';
        }
        return $out;
    }
}
