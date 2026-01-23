<?php

namespace App\Core;

class Router
{
    private $routes = [];
    private $middlewares = [];

    public function get($path, $handler, $middlewares = [])
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post($path, $handler, $middlewares = [])
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put($path, $handler, $middlewares = [])
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function delete($path, $handler, $middlewares = [])
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    private function addRoute($method, $path, $handler, $middlewares)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    public function dispatch()
    {
        try {
            // Carregar rotas
            $this->loadRoutes();
            
            $method = $_SERVER['REQUEST_METHOD'];
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            
            // Detectar ambiente: produção ou desenvolvimento local
            $appEnv = $_ENV['APP_ENV'] ?? 'local';
            
            // Remover prefixo do projeto (apenas se existir - desenvolvimento local)
            if (strpos($uri, '/cfc-v.1/public_html') !== false) {
                $uri = str_replace('/cfc-v.1/public_html', '', $uri);
            }
            
            // Se a URI é /index.php ou termina com /index.php, remover
            $uri = str_replace('/index.php', '', $uri);
            
            // Normalizar para /
            $uri = $uri ?: '/';

            foreach ($this->routes as $route) {
                $pattern = $this->convertToRegex($route['path']);
                
                if ($route['method'] === $method && preg_match($pattern, $uri, $matches)) {
                    array_shift($matches); // Remove full match
                    
                    // Executar middlewares
                    foreach ($route['middlewares'] as $middleware) {
                        $middlewareInstance = new $middleware();
                        if (!$middlewareInstance->handle()) {
                            return;
                        }
                    }

                    // Executar handler
                    if (is_array($route['handler'])) {
                        $controller = new $route['handler'][0]();
                        $handlerMethod = $route['handler'][1];
                        call_user_func_array([$controller, $handlerMethod], $matches);
                    } else {
                        call_user_func_array($route['handler'], $matches);
                    }
                    return;
                }
            }

            // 404
            http_response_code(404);
            if (file_exists(APP_PATH . '/Views/errors/404.php')) {
                include APP_PATH . '/Views/errors/404.php';
            } else {
                echo "404 - Página não encontrada";
            }
        } catch (\PDOException $e) {
            // Tratar erros de conexão ao banco de dados
            error_log('[Router] Erro de conexão ao banco: ' . $e->getMessage());
            
            http_response_code(500);
            
            // Se for erro de limite de conexões, mostrar mensagem específica
            if (strpos($e->getMessage(), 'max_connections_per_hour') !== false || 
                strpos($e->getMessage(), 'Limite de conexões') !== false) {
                $message = 'Limite de conexões ao banco de dados excedido. Por favor, aguarde alguns minutos e tente novamente.';
            } else {
                $message = 'Erro ao conectar ao banco de dados. Por favor, tente novamente mais tarde.';
            }
            
            if (file_exists(APP_PATH . '/Views/errors/500.php')) {
                $data = [
                    'pageTitle' => 'Erro de Conexão',
                    'error' => $message
                ];
                include APP_PATH . '/Views/errors/500.php';
            } else {
                echo '<html><head><title>Erro</title></head><body>';
                echo '<h1>Erro de Conexão</h1>';
                echo '<p>' . htmlspecialchars($message) . '</p>';
                echo '</body></html>';
            }
        } catch (\Exception $e) {
            // Tratar outras exceções
            error_log('[Router] Erro não tratado: ' . $e->getMessage());
            error_log('[Router] Stack trace: ' . $e->getTraceAsString());
            
            http_response_code(500);
            
            $appEnv = $_ENV['APP_ENV'] ?? 'local';
            $message = ($appEnv === 'production') 
                ? 'Ocorreu um erro inesperado. Por favor, tente novamente mais tarde.'
                : $e->getMessage();
            
            if (file_exists(APP_PATH . '/Views/errors/500.php')) {
                $data = [
                    'pageTitle' => 'Erro',
                    'error' => $message
                ];
                include APP_PATH . '/Views/errors/500.php';
            } else {
                echo '<html><head><title>Erro</title></head><body>';
                echo '<h1>Erro</h1>';
                echo '<p>' . htmlspecialchars($message) . '</p>';
                if ($appEnv !== 'production') {
                    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                }
                echo '</body></html>';
            }
        }
    }

    private function convertToRegex($path)
    {
        $pattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function loadRoutes()
    {
        global $router;
        $router = $this;
        require_once APP_PATH . '/routes/web.php';
    }
}
