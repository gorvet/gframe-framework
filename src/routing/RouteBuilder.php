<?php

class RouteBuilder {
    protected static array $routes = [];
    
    protected string $type = 'web'; // por defecto
    protected string $method;
    protected string $uri;
    protected string $controller; // 'admin/b/BController'
    protected string $action;     // 'show'
    protected array $middleware = [];
    protected array $excludedMiddleware = [];
    protected ?string $templateName  = null;
    protected ?string $view = null;
    protected ?string $permission = null;
    protected array $context = [];
    protected bool $useAutoSlug = false;
    protected bool $skipAction = false;
    protected bool $refreshSession = true;



    public static function get(string $uri, string $controllerAction): static {
        return static::register('GET', $uri, $controllerAction);
    }

    public static function post(string $uri, string $controllerAction): static {
        return static::register('POST', $uri, $controllerAction);
    }

    public static function put(string $uri, string $controllerAction): static {
        return static::register('PUT', $uri, $controllerAction);
    }

    public static function delete(string $uri, string $controllerAction): static {
        return static::register('DELETE', $uri, $controllerAction);
    }

    protected static function register(string $method, string $uri, string $controllerAction): static {
        if (!str_contains($controllerAction, '@')) {
            throw new InvalidArgumentException("El controllerAction debe tener el formato 'Controlador@acción'");
        }

        [$controller, $action] = explode('@', $controllerAction);

        $route = new static();
        $route->method = strtoupper($method);
        //$route->uri = strtolower(trim($uri, '/'));// homogéneo
        $route->uri = trim($uri, '/');  
        $route->controller = $controller;
        $route->action = $action;

        // Detecta si la ruta fue declarada en un archivo de tipo AJAX
        $callerFile = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['file'] ?? '';
        if (str_contains($callerFile, 'routes_webhook')) {
            $route->type = 'webhook';
        } 
        elseif (str_contains($callerFile, 'routes_api')) {
            $route->type = 'api';
        } 
        elseif (str_contains($callerFile, 'routes_ajax')) {
            $route->type = 'ajax';
        }
        elseif (str_contains($callerFile, 'routes_system')) {
            $route->type = 'system';
        }
        elseif (str_contains($callerFile, 'routes_sse')) {
            $route->type = 'sse';
        }
        else {
            $route->type = 'web';
        }

        return $route;
    }

    public function middleware(array $middleware): static {
        $this->middleware = $middleware;
        return $this;
    }

    public function excludeMiddleware(array $middleware): static {
        $this->excludedMiddleware = $middleware;
        return $this;
    }

    public function template(string $name): static {
    $this->templateName = $name;
    return $this;
}

    public function view(string $view): static {
        $this->view = $view;
        return $this;
    }

    public function permission(string $permission): static {
        $this->permission = $permission;
        return $this;
    }

    public function context(array $context): static {
        $this->context = $context;
        return $this;
    }

    public function autoSlug(): static {
        $this->useAutoSlug = true;
        return $this;
    }

    public function noAction(): static {
        $this->skipAction = true;
        return $this;
    }

    public function noRefreshSession(): static {
        $this->refreshSession = false;
        return $this;
    }


    public function registerFinal(): void {
        // Prepara context local para no mutar el original accidentalmente
    $context = $this->context;

    if ($this->useAutoSlug
        && !array_key_exists('slug', $context)
        && strpos($this->uri, '{') === false // evita rutas con parámetros
    ) {
        // El slug es exactamente la URI declarada (puede ser multi-segmento)
        $context['slug'] = $this->uri;
    }
        self::$routes[$this->method][$this->uri] = [
            'controller' => $this->controller,
            'action'       => $this->skipAction ? null : $this->action,
            'skipAction'   => $this->skipAction,
            'refreshSession' => $this->refreshSession,
            'middleware' => $this->middleware,
            'excludedMiddleware' => $this->excludedMiddleware,
            'templateName' => $this->templateName, // <-- nuevo
            'view' => $this->view,
            'permission' => $this->permission,
            'context' => $context,
            'type' => $this->type,
        ];
    }

    public static function all(): array {
        return self::$routes;
    }

    public static function inferViewName(string $controllerPath, string $action): string {
    // Obtener el módulo (última carpeta antes del Controller)
    $parts = explode('/', str_replace('\\', '/', $controllerPath));
    $module = count($parts) >= 2 ? $parts[count($parts) - 2] : '';
    return $module . ucfirst($action); // Ej: "bShow"
}


    public static function inferTemplateFromController(string $controllerPath): string {
        $parts = explode('/', str_replace('\\', '/', $controllerPath));
        if (count($parts) >= 2) {
            return $parts[count($parts) - 2]; // penúltimo elemento
        }
        return '';
    }
    public static function relativePathFromController(string $controllerPath): string {
    $parts = explode('/', str_replace('\\', '/', $controllerPath));
    array_pop($parts); // quitamos el nombre del controller
    return implode('/', $parts); // devuelve la carpeta real
}


}
