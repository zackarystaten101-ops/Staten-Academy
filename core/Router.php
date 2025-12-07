<?php
/**
 * Router Class
 * Handles routing and dispatches requests to controllers
 */

class Router {
    private $routes = [];
    private $middleware = [];
    
    /**
     * Add a route
     */
    public function add($method, $path, $handler, $middleware = []) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }
    
    /**
     * Add GET route
     */
    public function get($path, $handler, $middleware = []) {
        $this->add('GET', $path, $handler, $middleware);
    }
    
    /**
     * Add POST route
     */
    public function post($path, $handler, $middleware = []) {
        $this->add('POST', $path, $handler, $middleware);
    }
    
    /**
     * Add PUT route
     */
    public function put($path, $handler, $middleware = []) {
        $this->add('PUT', $path, $handler, $middleware);
    }
    
    /**
     * Add DELETE route
     */
    public function delete($path, $handler, $middleware = []) {
        $this->add('DELETE', $path, $handler, $middleware);
    }
    
    /**
     * Dispatch request
     */
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = rtrim($path, '/') ?: '/';
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchRoute($route['path'], $path, $params)) {
                // Run middleware
                foreach ($route['middleware'] as $middleware) {
                    if (is_string($middleware)) {
                        // Check if middleware has parameters (e.g., "RoleMiddleware:teacher")
                        if (strpos($middleware, ':') !== false) {
                            list($middlewareClass, $param) = explode(':', $middleware, 2);
                            $middleware = new $middlewareClass($param);
                        } else {
                            $middleware = new $middleware();
                        }
                    }
                    if (!$middleware->handle()) {
                        return; // Middleware blocked the request
                    }
                }
                
                // Execute handler
                $handler = $route['handler'];
                if (is_string($handler) && strpos($handler, '@') !== false) {
                    list($controllerClass, $method) = explode('@', $handler);
                    $controller = new $controllerClass($GLOBALS['conn']);
                    call_user_func_array([$controller, $method], $params);
                } elseif (is_callable($handler)) {
                    call_user_func_array($handler, $params);
                }
                return;
            }
        }
        
        // 404 Not Found
        http_response_code(404);
        echo "404 - Page Not Found";
    }
    
    /**
     * Match route pattern with path
     */
    private function matchRoute($pattern, $path, &$params) {
        $params = [];
        $pattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';
        
        if (preg_match($pattern, $path, $matches)) {
            array_shift($matches);
            $params = $matches;
            return true;
        }
        return false;
    }
}

