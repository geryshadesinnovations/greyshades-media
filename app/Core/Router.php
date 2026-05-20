<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal pattern router.
 *  - supports GET/POST/PUT/PATCH/DELETE
 *  - parameter syntax: /media/{id}
 *  - calls handler as [Controller::class, 'method'] or Closure
 */
final class Router
{
    /** @var array<string,array<int,array{0:string,1:array<int,string>,2:mixed,3:array<int,callable|string>}>> */
    private array $routes = [];

    public function get(string $path, mixed $handler, array $middleware = []): void    { $this->add('GET',    $path, $handler, $middleware); }
    public function post(string $path, mixed $handler, array $middleware = []): void   { $this->add('POST',   $path, $handler, $middleware); }
    public function put(string $path, mixed $handler, array $middleware = []): void    { $this->add('PUT',    $path, $handler, $middleware); }
    public function delete(string $path, mixed $handler, array $middleware = []): void { $this->add('DELETE', $path, $handler, $middleware); }
    public function any(string $path, mixed $handler, array $middleware = []): void {
        foreach (['GET','POST','PUT','DELETE'] as $m) $this->add($m, $path, $handler, $middleware);
    }

    private function add(string $method, string $path, mixed $handler, array $middleware): void
    {
        $params = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $path);
        $regex = '#^' . $regex . '$#';
        $this->routes[$method][] = [$regex, $params, $handler, $middleware];
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri    = '/' . trim($uri, '/');

        // method override via _method (forms)
        if ($method === 'POST' && !empty($_POST['_method'])) {
            $method = strtoupper((string)$_POST['_method']);
        }

        foreach ($this->routes[$method] ?? [] as [$regex, $params, $handler, $middleware]) {
            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                $args = array_combine($params, $matches) ?: [];

                // Run middleware chain
                foreach ($middleware as $mw) {
                    if (is_string($mw) && class_exists($mw)) {
                        (new $mw())->handle();
                    } elseif (is_callable($mw)) {
                        $mw();
                    }
                }

                $this->call($handler, $args);
                return;
            }
        }

        http_response_code(404);
        echo View::render('errors/404', []);
    }

    private function call(mixed $handler, array $args): void
    {
        // Cast numeric route params (e.g. {id}) to int for strict-typed controllers
        $castArgs = array_map(
            fn ($v) => is_string($v) && ctype_digit($v) ? (int) $v : $v,
            array_values($args)
        );

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = new $class();
            $instance->{$method}(...$castArgs);
            return;
        }
        if (is_callable($handler)) {
            $handler(...$castArgs);
            return;
        }
        throw new \RuntimeException('Invalid route handler');
    }
}
