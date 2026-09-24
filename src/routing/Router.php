<?php
// core/routing/Router.php
// Este archivo es gestionador de rutas

class Router {
   
  private $renderController;
  private $intendedType;

 
  public function __construct(?Render $renderController = null) {
    $this->renderController = $renderController;
  }

  // Determinamos que vista y acción se pide por url
  public function route() {

    // Detectamos el "origen de la ruta"
    $this->intendedType = $this->detectIntendedType();

    // Si es api o webhook, no "embellecer" la URL para evitar 301
    if (!in_array($this->intendedType, ['ajax', 'api', 'webhook', 'sse'])) {
        $this->fixUrl();
    }

    // 2. Detecta idioma y limpia segmentos
    $uri = $this->getLanguageAndUri();

    // Render de error forzado desde frontend (toErrorView)
    if ($this->shouldRenderPostedError()) {
      $this->renderPostedError();
      exit();
    }

    // 3. Aplica rutas
    $routeParams = $this->getRouteParamsFromDeclarative($uri);
    $routeParams['currentURL'] = $this->getCurrentURL(); 

/*echo "<pre>";
    var_dump($routeParams);
echo "</pre>";*/

    // 4. Aplica Middwares
    if (!empty($routeParams['middleware'])) {
      $middlewareHandler = new Middleware();
      $res=$middlewareHandler->handle($routeParams);

      if (!empty($res['context']) && is_array($res['context'])) {
        // Si $routeParams['context'] no existe, inicialízalo
        if (empty($routeParams['context']) || !is_array($routeParams['context'])) {
            $routeParams['context'] = [];
        }
        // Mezclar sin sobreescribir las claves ya existentes
        $routeParams['context'] = array_merge($routeParams['context'], $res['context']);
    }

      // Preflight permitido (o cualquier OPTIONS con señal CORS)
  if (
      ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' && isset($res['cors_headers'])) ||
      (($res['status'] ?? '') === 'preflight')
  ) {
      CorsHelper::sendHeaders(!empty($res['cors_headers']));
  }
      if ($res['status']!=='success') {
        $routeParams = $this->processMiddlewareError($res, $routeParams);   
      }
    }

    // 5. Detección si la ruta no es web para dar una respuesta fuera del render
    if ($this->intendedType!='web') { 
      $this->handleDirectExecution($routeParams); 
    } 
    else {
      $this->toRender($routeParams); // Renderiza
    }
  }

  private function getRouteParamsFromDeclarative($uri){
    $method = $_SERVER['REQUEST_METHOD'];
    $uriPath = implode('/', $uri);  
          /*  echo "<pre>";
    var_dump(RouteBuilder::all());
echo "</pre>";*/

    foreach (RouteBuilder::all() as $routeMethod => $routesByUri) {
      if ($routeMethod !== $method) continue;
      foreach ($routesByUri as $pattern => $route) {

        $regexPattern = preg_replace('#\{[a-zA-Z_]+\}#', '([a-zA-Z0-9_\-]+)', $pattern);
        $regexPattern = '#^' . $regexPattern . '$#';
        //echo "<pre>Comparando: pattern={$pattern} vs uriPath={$uriPath}</pre>";

        if (preg_match($regexPattern, $uriPath, $matches)) {

          array_shift($matches);
          preg_match_all('#\{([a-zA-Z_]+)\}#', $pattern, $paramNames);
          $params = array_combine($paramNames[1], $matches);
          /*echo "<pre>params: ";
          var_dump($params);
          echo "</pre>";*/

          $relativePath = RouteBuilder::relativePathFromController($route['controller']);

          // Infieren si no se declaran
          $templateName = $route['templateName'] ?? RouteBuilder::inferTemplateFromController($route['controller']);


          $actionName = $route['action'] ?? null;
          $skipAction = !empty($route['skipAction']);
          $view = $route['view'] ?? RouteBuilder::inferViewName($route['controller'], $actionName ?? '');


          $middlewares = $route['middleware'] ?? [];
          $excludedMiddleware = $route['excludedMiddleware'] ?? [];

          // Inyectar middleware automáticos según el canal de la ruta
          $middlewares = $this->injectAutoMiddlewares($middlewares, $excludedMiddleware);

          $isProtected = ($this->intendedType === 'web') && in_array('auth', $middlewares, true);

          // extraer modulo para usar en can del middware
          
          $parts = explode('/', str_replace('\\', '/', (string)$route['controller']));
          $module = $parts[count($parts) - 2] ?? ($parts[0] ?? null);

          

          return [
            'type'       => $this->intendedType,
            'method'       => $routeMethod,
            'controller'   => $route['controller'],
            'relativePath' => $relativePath,
            'templateName' => $templateName,
            'view'         => $view,
            'actionName'  => $actionName,
            'skipAction'  => $skipAction,
            'refreshSession' => (bool)($route['refreshSession'] ?? true),
            'module'       => $module,
            'lang'         => APP_LANG,
            'params'       => $params,
            'middleware'   => $middlewares,
            'excludedMiddleware' => $excludedMiddleware,
            'permission'   => $route['permission'] ?? null,
            'context'      => $route['context'] ?? [],
            'isProtected'  => $isProtected,
          ];
        }
      }
    }

    if ($this->intendedType!='web') {
      $this->sendErrorResponse('not_found', 'Ruta no encontrada');
    }
    
    return $this->buildErrorRouteParams('not_found', '', [
      'method'     => $_SERVER['REQUEST_METHOD'],
      'type'       => $this->intendedType,
      'lang'       => APP_LANG,
      'currentURL' => $this->getCurrentURL(),
    ]);
  }

 
  //Llamamos al render con los parámetros obtenidos
  public function toRender(array $routeParams) {
    /*echo "to render ";
    print_r($routeParams);
    echo "<br><br>";*/
    $this->renderController->renderView($routeParams);       
  }


    private function handleDirectExecution(array $routeParams) {
    $errorController = new ErrorResponder();
    $controllerName = $routeParams['controller'];
    $actionName     = $routeParams['actionName'];

    $controllerPath = $this->resolveControllerPath((string)$controllerName);
    if ($controllerPath === null) {
        return $this->sendErrorResponse('not_found', 'El controlador no existe.');
    }

    require_once $controllerPath;
    $parts     = explode('/', str_replace('\\', '/', $controllerName));
    $className = end($parts);

    if (!class_exists($className)) {
        return $this->sendErrorResponse('not_found', "La clase '$className' no esta definida.");
    }

    $controllerInstance = new $className();

    if (!empty($routeParams['skipAction'])) {
        $this->respondSkipAction();
    }

    if (!method_exists($controllerInstance, $actionName) || !is_callable([$controllerInstance, $actionName])) {
        return $this->sendErrorResponse('not_found', "El metodo '$actionName' no esta disponible o no es publico.");
    }

    switch ($this->intendedType) {
        case 'ajax':
            $response = $controllerInstance->$actionName($routeParams);
            if ($this->isErrorResponse($response)) {
                $this->processErrorResponse((array)$response, $routeParams);
            }
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
            echo $this->safeJsonEncode(is_array($response) || is_object($response) ? $response : ['data' => $response]);
            break;

        case 'api':
            $response = $controllerInstance->$actionName($routeParams);
            $httpCode = 200;
            if (is_array($response) || is_object($response)) {
                $responseArray = (array)$response;
                if (isset($responseArray['http_code']) && is_numeric($responseArray['http_code'])) {
                    $httpCode = (int)$responseArray['http_code'];
                } elseif (isset($responseArray['status']) && in_array((string)$responseArray['status'], ['error', 'unauthorized'], true)) {
                    $httpCode = $errorController->resolveHttpCode($responseArray['code'] ?? '', 400);
                    $responseArray['http_code'] = $responseArray['http_code'] ?? $httpCode;
                }
                $response = $responseArray;
            }
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($httpCode);
            echo $this->safeJsonEncode($response);
            break;

        case 'webhook':
            $response = $controllerInstance->$actionName($routeParams);
            if (is_array($response) || is_object($response)) {
                header('Content-Type: application/json; charset=utf-8');
                echo $this->safeJsonEncode($response);
            } else {
                header('Content-Type: text/plain; charset=utf-8');
                echo (string)$response;
            }
            break;

        case 'sse':
            if (!headers_sent()) {
                header('Content-Type: text/event-stream; charset=utf-8');
                header('Cache-Control: no-cache, no-transform');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no');
            }
            if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            while (ob_get_level() > 0) { @ob_end_clean(); }

            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            @ignore_user_abort(true);

            $controllerInstance->$actionName($routeParams);
            exit;
    }
    exit();
}

private function respondSkipAction(): void {
    switch ($this->intendedType) {
        case 'ajax':
        case 'api':
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
            echo $this->safeJsonEncode(['status' => 'success']);
            break;

        case 'webhook':
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(200);
            echo 'ok';
            break;

        case 'sse':
            if (!headers_sent()) {
                header('Content-Type: text/event-stream; charset=utf-8');
                header('Cache-Control: no-cache, no-transform');
                header('X-Accel-Buffering: no');
            }
            echo ": skip_action\n\n";
            break;
    }

    exit();
}
private function sendErrorResponse($code, $message) {
    $errorController = new ErrorResponder();
    $normalizedCode = $errorController->normalizeErrorCode($code);
    $httpCode = $errorController->resolveHttpCode($normalizedCode, 400);

    switch ($this->intendedType) {
        case 'ajax':
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
            echo $this->safeJsonEncode([
                'status'  => 'error',
                'code'    => $normalizedCode,
                'message' => DebugMode ? $message : 'Error en la peticion AJAX.'
            ]);
            break;

        case 'api':
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($httpCode);
            echo $this->safeJsonEncode([
                'status'  => 'error',
                'code'    => $normalizedCode,
                'message' => DebugMode ? $message : 'Error en la API.'
            ]);
            break;

        case 'webhook':
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code($httpCode);
            echo DebugMode ? $message : 'Error en Webhook.';
            break;

        case 'sse':
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache, no-transform');
            header('X-Accel-Buffering: no');
            echo "event: error\n";
            echo "data: " . $this->safeJsonEncode(DebugMode ? $message : 'SSE error') . "\n\n";
            break;
    }
    exit();
}
private function getCurrentURL() {
    $base = dirname($_SERVER['SCRIPT_NAME']);
    $uri = site_url . ltrim(substr($_SERVER['REQUEST_URI'], strlen($base)), '/');
    return $uri;
  }



  private function getLanguageAndUri(): array {
    $supportedLangs = SUPPORTED_LANGS;
    $defaultLang = $supportedLangs[0];

    $base = dirname($_SERVER['SCRIPT_NAME']);
    $uri  = substr($_SERVER['REQUEST_URI'], strlen($base));
    $segments = explode('/', trim(parse_url($uri, PHP_URL_PATH), '/'));

    $lang = $defaultLang;
    if (in_array($segments[0] ?? '', $supportedLangs)) {
      $lang = $segments[0];
      array_shift($segments); // quita idioma si está
    }

    define('APP_LANG', $lang);
    return $segments;
  }

  private function fixUrl() {
    $supportedLangs = SUPPORTED_LANGS;
    $defaultLang = $supportedLangs[0];

    $base = dirname($_SERVER['SCRIPT_NAME']);
    $uri = substr($_SERVER['REQUEST_URI'], strlen($base));
    $uri = preg_replace('#/+#', '/', $uri); // Normaliza slashes
    $uriSegments = explode('/', trim($uri, '/'));

    $firstSegment = $uriSegments[0] ?? '';

    // Eliminar idioma redundante si es el default
    if ($firstSegment === $defaultLang) {
      array_shift($uriSegments);
      $this->redirectToCleanUrl($uriSegments);
    }

    // Redirige si hay slashes dobles
    if (preg_match('#/{2,}#', $_SERVER['REQUEST_URI'])) {
        $this->redirectToCleanUrl($uriSegments);
    }
  }

  private function redirectToCleanUrl(array $uriSegments): void {
    $fixedUrl = site_url . implode('/', $uriSegments);
    header("Location: " . $fixedUrl, true, 301);
    exit();
  }

  private function processMiddlewareError($res, $routeParams) {
    return $this->processErrorResponse((array)$res, $routeParams);
  }

  private function processErrorResponse(array $res, array $routeParams) {
    $errorController = new ErrorResponder();
    $resCode = $errorController->normalizeErrorCode($res['code'] ?? '');
    if ($resCode !== '') {
      $res['code'] = $resCode;
    }

    if ($this->intendedType=='ajax') {
      if ($this->shouldRenderAjaxErrorView($res, $errorController)) {
        $this->renderAjaxErrorView($res, $routeParams);
      }

      http_response_code(200);
      header('Content-Type: application/json; charset=utf-8');
      echo $this->safeJsonEncode($res);
      exit();
    }

    if (($res['code'] ?? '') == 'login_required') {
      header("Location: " . site_url.'login', true, 301);
      exit();
    }
    elseif (($res['code'] ?? '') == 'already_logged' && ($this->intendedType!='ajax'&& $this->intendedType!='api')) {

     $to = $this->pick();
    header('Location: ' . rtrim(site_url, '/') . $to, true, 302);
    exit;
    }
    elseif (($res['code'] ?? '') == 'to_reload') {
      header("Location: " . $routeParams['currentURL'], true, 301);
      exit();
    }
    elseif (($res['code'] ?? '') === 'expired') {
      if ($this->intendedType === 'web') {
        $routeParams['expired'] = true;
        return $routeParams;
      }
    }

    if ($this->intendedType=='api') {
      $httpCode = isset($res['http_code']) && is_numeric($res['http_code'])
        ? (int)$res['http_code']
        : $errorController->resolveHttpCode($res['code'] ?? '', 400);
      http_response_code($httpCode);
      header('Content-Type: application/json; charset=utf-8');
      echo $this->safeJsonEncode($res);
      exit();
    }
    if ($this->intendedType==='webhook') {
    $httpCode = isset($res['http_code']) && is_numeric($res['http_code'])
      ? (int)$res['http_code']
      : $errorController->resolveHttpCode($res['code'] ?? '', 400);
    http_response_code($httpCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo DebugMode ? ($res['message'] ?? '') : '';
    exit();
  }

    $routeCode = $errorController->resolveWebRouteCode($res['code'] ?? 'internal_error');
    return $this->buildErrorRouteParams(
      $routeCode,
      (isset($res['message']) && DebugMode) ? $res['message'] : '',
      $routeParams
    );
  }

  private function isErrorResponse($response): bool {
    if (!is_array($response) && !is_object($response)) {
      return false;
    }

    $payload = (array)$response;
    $status = (string)($payload['status'] ?? '');

    return in_array($status, ['error', 'unauthorized'], true);
  }

  private function shouldRenderAjaxErrorView(array $res, ErrorResponder $errorController): bool {
    $code = (string)($res['code'] ?? '');
    if ($code === '' || $code === 'to_reload') {
      return false;
    }

    $httpCode = $errorController->resolveHttpCode($code, 0);
    if (in_array($httpCode, [401, 403, 404, 500, 503], true)) {
      return true;
    }

    return in_array($code, [
      'permission',
      'forbidden',
      'not_found',
      'internal_error',
      'service_unavailable',
      'external_api_error',
    ], true);
  }

  private function renderAjaxErrorView(array $res, array $routeParams): void {
    $errorController = new ErrorResponder();
    $routeCode = $errorController->resolveWebRouteCode($res['code'] ?? 'internal_error');
    $returnUrl = $this->resolveAjaxErrorReturnUrl($routeParams);

    $errorRouteParams = $this->buildErrorRouteParams(
      $routeCode,
      (isset($res['message']) && DebugMode) ? (string)$res['message'] : '',
      array_merge($routeParams, ['tolink' => $returnUrl])
    );
    $errorRouteParams['httpCode'] = 200;
    $errorRouteParams['params']['tolink'] = $returnUrl;

    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    $this->toRender($errorRouteParams);
    exit();
  }

  private function resolveAjaxErrorReturnUrl(array $routeParams): string {
    $pageUrl = (string)($_SERVER['HTTP_X_GFRAME_PAGE_URL'] ?? '');
    if ($this->isInternalNonDirectChannelUrl($pageUrl)) {
      return $pageUrl;
    }

    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($this->isInternalNonDirectChannelUrl($referer)) {
      return $referer;
    }

    $currentURL = (string)($routeParams['currentURL'] ?? '');
    if ($this->isInternalNonDirectChannelUrl($currentURL)) {
      return $currentURL;
    }

    return site_url;
  }

  private function isInternalNonDirectChannelUrl(string $url): bool {
    $url = trim($url);
    if ($url === '') {
      return false;
    }

    if (str_starts_with($url, '/')) {
      $url = rtrim(site_url, '/') . $url;
    }

    $siteHost = parse_url(site_url, PHP_URL_HOST);
    $urlHost = parse_url($url, PHP_URL_HOST);
    if (!empty($siteHost) && !empty($urlHost) && strcasecmp($siteHost, $urlHost) !== 0) {
      return false;
    }

    $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
    $basePath = parse_url(site_url, PHP_URL_PATH);
    $basePath = is_string($basePath) ? trim($basePath, '/') : '';

    $relativePath = trim($path, '/');
    if ($basePath !== '' && ($relativePath === $basePath || str_starts_with($relativePath, $basePath . '/'))) {
      $relativePath = trim(substr($relativePath, strlen($basePath)), '/');
    }

    $segments = $relativePath === '' ? [] : explode('/', $relativePath);
    if (($segments[0] ?? '') === 'index.php') {
      array_shift($segments);
    }

    $first = strtolower((string)($segments[0] ?? ''));
    if (defined('SUPPORTED_LANGS') && in_array($first, SUPPORTED_LANGS, true)) {
      array_shift($segments);
      $first = strtolower((string)($segments[0] ?? ''));
    }
    if ($first === 'index.php') {
      array_shift($segments);
      $first = strtolower((string)($segments[0] ?? ''));
    }

    return !in_array($first, ['ajax', 'api', 'webhook', 'sse', 'system'], true);
  }
private function buildErrorRouteParams(string $errorCode, string $infoMsg = '', array $routeParams = []): array {
    $errorController = new ErrorResponder();

    return $errorController->buildRouteParams($errorCode, [
      'type'       => $routeParams['type'] ?? $this->intendedType ?? 'web',
      'method'     => $routeParams['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
      'lang'       => $routeParams['lang'] ?? (defined('APP_LANG') ? APP_LANG : 'es'),
      'currentURL' => $routeParams['currentURL'] ?? $this->getCurrentURL(),
      'tolink'     => $routeParams['tolink'] ?? '',
      'infoMsg'    => $infoMsg,
      'helpMsg'    => $routeParams['helpMsg'] ?? '',
      'helpUrl'    => $routeParams['helpUrl'] ?? '',
      'helpLabel'  => $routeParams['helpLabel'] ?? '',
      'helpEnabled' => array_key_exists('helpEnabled', $routeParams) && $routeParams['helpEnabled'] !== null
        ? (bool)$routeParams['helpEnabled']
        : null,
      'context'    => $routeParams['context'] ?? [],
    ]);
  }



  private function shouldRenderPostedError(): bool {
    return $this->intendedType === 'web'
      && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
      && isset($_POST['errorType']);
  }

  private function renderPostedError(): void {
    $errorType = trim((string)($_POST['errorType'] ?? 'service_unavailable'));
    $tolink = $this->normalizePostedToLink((string)($_POST['tolink'] ?? ''));
    $infoMsg = trim((string)($_POST['infoMsg'] ?? ''));

    $errorController = new ErrorResponder();
    $routeParams = $errorController->buildRouteParams($errorType, [
      'type'       => 'web',
      'method'     => $_SERVER['REQUEST_METHOD'] ?? 'POST',
      'lang'       => defined('APP_LANG') ? APP_LANG : 'es',
      'currentURL' => $this->getCurrentURL(),
      'tolink'     => $tolink,
      'infoMsg'    => $infoMsg,
      'context'    => [],
    ]);

    $this->toRender($routeParams);
  }

  private function normalizePostedToLink(string $tolink): string {
    $tolink = trim($tolink);
    if ($tolink === '') {
      return '';
    }

    // URL relativa interna
    if (str_starts_with($tolink, '/')) {
      return rtrim(site_url, '/') . $tolink;
    }

    // URL absoluta del mismo host
    if (preg_match('#^https?://#i', $tolink)) {
      $siteHost = parse_url(site_url, PHP_URL_HOST);
      $urlHost = parse_url($tolink, PHP_URL_HOST);
      if (!empty($siteHost) && !empty($urlHost) && strcasecmp($siteHost, $urlHost) === 0) {
        return $tolink;
      }
      return '';
    }

    // Path relativo sin slash inicial
    return rtrim(site_url, '/') . '/' . ltrim($tolink, '/');
  }

private function detectIntendedType(): string {
    // 1) Saca el path relativo (sin dominio ni query, y sin el base del script)
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $base = dirname($_SERVER['SCRIPT_NAME'] ?? '') ?: '';
    $rel  = ltrim(substr($path, strlen($base)), '/'); // p.ej. "es/ajax/login" o "ajax/login"

    // 2) Segmenta y quita el idioma si está al inicio
    $segments = $rel === '' ? [] : explode('/', $rel);
    $first = strtolower($segments[0] ?? '');
    if (defined('SUPPORTED_LANGS') && in_array($first, SUPPORTED_LANGS, true)) {
        array_shift($segments);
        $first = strtolower($segments[0] ?? '');
    }

    // 3) Decide el canal por prefijo real
    return in_array($first, ['webhook', 'api', 'ajax','sse'], true) ? $first : 'web';
}


private function injectAutoMiddlewares(array $middlewares, array $excludedMiddlewares = []): array {
    $prepend = [];

    if ($this->intendedType === 'api' && !$this->middlewareDisabled('allow_cors_with_token', $middlewares, $excludedMiddlewares)) {
        $prepend[] = 'allow_cors_with_token';
    }

    if ($this->intendedType === 'ajax') {
        if (!$this->middlewareDisabled('block_external_ajax', $middlewares, $excludedMiddlewares)) {
            $prepend[] = 'block_external_ajax';
        }
        if (!$this->middlewareDisabled('CSRF', $middlewares, $excludedMiddlewares)) {
            $prepend[] = 'CSRF';
        }
    }

    if ($this->intendedType === 'webhook' && !$this->middlewareDisabled('webhook_guard', $middlewares, $excludedMiddlewares)) {
        $prepend[] = 'webhook_guard';
    }

    if ($this->intendedType === 'sse' && !$this->middlewareDisabled('sse_guard', $middlewares, $excludedMiddlewares)) {
        $prepend[] = 'sse_guard';
    }

    if (empty($prepend)) {
        return $middlewares;
    }

    return array_merge($prepend, $middlewares);
}

private function middlewareDisabled(string $middleware, array $middlewares, array $excludedMiddlewares): bool {
    return $this->hasMiddleware($middlewares, $middleware) || $this->hasMiddleware($excludedMiddlewares, $middleware);
}

private function hasMiddleware(array $middlewares, string $needle): bool {
    foreach ($middlewares as $middleware) {
        if (mb_strtolower((string)$middleware, 'UTF-8') === mb_strtolower($needle, 'UTF-8')) {
            return true;
        }
    }

    return false;
}

private function safeJsonEncode($value): string {
    $json = json_encode(
        $value,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PARTIAL_OUTPUT_ON_ERROR
    );

    if (is_string($json)) {
        return $json;
    }

    return '{}';
}

private function resolveControllerPath(string $controller): ?string {
    $controller = trim(str_replace('\\', '/', $controller), '/');
    if ($controller === '') {
        return null;
    }

    $candidates = [
        ABSPATH . "app/controllers/{$controller}.php",
        (defined('GFRAME_PATH') ? GFRAME_PATH : ABSPATH . 'core/') . "{$controller}.php",
    ];

    foreach ($candidates as $candidate) {
        $real = realpath($candidate);
        if ($real !== false && file_exists($real)) {
            return $real;
        }
    }

    return null;
}

// helpers/AuthRedirect.php

    private static function pick(string $fallback = '/admin'): string
    {
        $rd = $_GET['rd'] ?? ($_SESSION['intended'] ?? null);
        if (!$rd) return $fallback;

        $rd = urldecode($rd);

        // 1) Si vino con dominio propio, quítalo (solo permitimos rutas internas)
        $siteHost = parse_url(site_url, PHP_URL_HOST);
        $pattern  = '#^https?://'.preg_quote($siteHost, '#').'(:\d+)?#i';
        $rd       = preg_replace($pattern, '', $rd);

        // 2) Normaliza a ruta absoluta interna
        $rd = '/' . ltrim($rd, '/');

        // 3) Bloquea intentos externos o raros: //, http(s), CRLF
        if (preg_match('#^\s*//|^\s*https?://#i', $rd) || preg_match('/[\r\n]/', $rd)) {
            return $fallback;
        }

        // 4) Evita bucles hacia login/logout
        if (preg_match('#^/(login|logout)(/|$)#i', $rd)) {
            return $fallback;
        }

        return $rd;
    }


// fin de la clase 
}
 




