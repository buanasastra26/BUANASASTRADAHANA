<?php
namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, array $handler): void { $this->add('POST', $path, $handler); }

    private function add(string $method, string $path, array $handler): void
    {
        $this->routes[] = [$method, '/' . trim($path, '/'), $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');
        if ($path === '//') $path = '/';
        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            if ($routeMethod !== $method) continue;
            $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $routePath);
            $pattern = '#^' . ($pattern === '' ? '/' : $pattern) . '$#';
            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                [$class, $action] = $handler;
                $controller = new $class();
                $controller->$action(...array_values($params));
                return;
            }
        }
        http_response_code(404);
        View::render('public/404', ['title' => 'Halaman Tidak Ditemukan']);
    }
}
