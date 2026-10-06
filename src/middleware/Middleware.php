<?php
// Core HTTP middleware pipeline.

class Middleware {

private \GFrame\Auth\RolePermissionService $rolePermissionService;
private \GFrame\Http\Contracts\ApiCredentialProvider $apiCredentialProvider;


public function __construct(
    ?\GFrame\Auth\RolePermissionService $rolePermissionService = null,
    ?\GFrame\Http\Contracts\ApiCredentialProvider $apiCredentialProvider = null
) {
    $this->rolePermissionService = $rolePermissionService
      ?? new \GFrame\Auth\RolePermissionService(new \GFrame\Auth\RoleModel());
    $this->apiCredentialProvider = $apiCredentialProvider ?? new \GFrame\Http\RouteApiCredentialProvider();
}

    
  public  function handle(array $routeParams) {

    $middlewares = (array)($routeParams['middleware'] ?? []);
    $refreshSession = (bool)($routeParams['refreshSession'] ?? true);
    $sessionAwareRoute = $this->usesSessionState($middlewares);
    if (!$sessionAwareRoute) {
        $refreshSession = true;
    }

    if (!$this->hasSessionIdentity()) {
        if ($refreshSession === false) {
            return ['status' => 'unauthorized', 'code' => 'expired', 'message' => 'Sesión expirada por inactividad.'];
        }
    }
    if ($sessionAwareRoute && $this->hasSessionIdentity()) {
        $res = $this->sessionTimeout($refreshSession);
        if ($res['status'] !== 'success') return $res;
    }

    $agg = ['status' => 'success'];

    foreach ($middlewares as $middleware) { 
      $middleware=mb_strtolower($middleware, 'UTF-8');  

      if ($middleware === 'block_external_ajax') {//automatico para las ajax
        $res = $this->block_external_ajax();
      }
      elseif ($middleware === 'allow_cors_with_token') {//automatico para las apis
        $res = $this->allow_cors_with_token($routeParams);
      }
      elseif ($middleware === 'webhook_guard') {//automatico para las webhook
        $res = $this->webhook_guard($routeParams);
      } 
      elseif ($middleware === 'sse_guard') {//automatico para las sse
        $res = $this->sse_guard($routeParams);
      }
      elseif ($middleware === 'honeypot') {
        $res = $this->val_honeypot();
      }
      elseif ($middleware === 'csrf') {
        $res = $this->val_CSRF(); 
      }
      elseif ($middleware === 'auth') { 
        $res = $this->auth();
      }
      elseif ($middleware === 'guest') { 
        $res = $this->guest();
      }
      elseif ($middleware === 'admin') { 
        $res = $this->isadmin();
      }
      elseif (str_starts_with($middleware, 'role:')) {
        $role = explode(':', $middleware, 2)[1] ?? '';
        $res = $this->checkRole($role);
      }
      
      elseif (str_starts_with($middleware, 'can:')) {
        // Si es un permiso can:xxx
        $permission = explode(':', $middleware)[1] ?? '';
        $res = $this->checkPermission($permission,$routeParams);  
      }
      else {
        return [
          'status' => 'error',
          'code' => 'unknown_middleware',
          'message' => 'Middleware no registrado: ' . $middleware,
        ];
      }

      //die($middleware.' - '.$res['status']);
      if (($res['status'] ?? '') !== 'success') {

       // LogHelper::write($res,"middleware_error");

        return $res;  // ⛔ Detener cadena si falla Detener cadena si falla
      }

      foreach (['headers', 'http_code', 'cors_headers', 'code', 'message'] as $key) {
        if (array_key_exists($key, $res)) {
          $agg[$key] = $res[$key];
        }
      }

      if (!empty($res['context']) && is_array($res['context'])) {
        $agg['context'] = array_merge((array)($agg['context'] ?? []), $res['context']);
      }
    }

    return $agg;
  }

private function usesSessionState(array $middlewares): bool {
    foreach ($middlewares as $middleware) {
        $normalized = mb_strtolower((string)$middleware, 'UTF-8');

        if (
            $normalized === 'auth'
            || $normalized === 'guest'
            || $normalized === 'admin'
            || str_starts_with($normalized, 'role:')
            || str_starts_with($normalized, 'can:')
        ) {
            return true;
        }
    }

    return false;
}

/*************************************/


private function block_external_ajax(): array {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
 
  if ($method === 'OPTIONS') {
    return [
        'status'=>'unauthorized',
        'code'=>'forbidden',
        'message'=>'AJAX no permite CORS.',
        'http_code'=> 403
    ];
  }
    
$hasHeaders = !empty($_SERVER['HTTP_ORIGIN']) || !empty($_SERVER['HTTP_REFERER']);
    if ($hasHeaders && !$this->is_same_origin()) {
        return ['status'=>'unauthorized','code'=>'forbidden','message'=>'Llamada AJAX externa no permitida.'];
    }
    

 return ['status'=>'success'];

}



private function allow_cors_with_token(array $routeParams): array {
    $origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] 
         ?? $headers['authorization'] 
         ?? $headers['AUTHORIZATION'] 
         ?? $_SERVER['HTTP_AUTHORIZATION']
         ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
         ?? '';
    $bearer = preg_match('/^Bearer\s+(.+)$/i', trim((string)$auth), $match) === 1 ? trim($match[1]) : '';
    $originFull = $origin ?: $referer ?: '';
    $originHost = parse_url($originFull, PHP_URL_HOST) ?? '';
    $originHostCanon = $this->canonicalApiHost((string)$originHost);
    $context = (array)($routeParams['context'] ?? []);
    if ($this->apiCredentialProvider instanceof \GFrame\Http\RouteApiCredentialProvider && !$this->apiCredentialProvider->hasCredentials($context)) {
        return ['status' => 'error', 'code' => 'api_token_not_configured', 'message' => 'La ruta API no tiene consumidores configurados.', 'http_code' => 500];
    }

    if ($method === 'OPTIONS') {
        $originAllowed = $originHostCanon !== '' && $this->apiCredentialProvider->allowsPreflightOrigin($originHostCanon, $context);
        return [
            'status'       => $originAllowed ? 'preflight' : 'unauthorized',
            'code'         => $originAllowed ? 'cors_ok' : 'forbidden',
            'http_code'    => $originAllowed ? 204 : 403,
            'cors_headers' => $originAllowed,
        ];
    }

    $authentication = $this->apiCredentialProvider->authenticate($bearer, $context);
    if (($authentication['status'] ?? '') !== 'success') return $authentication;
    $consumer = (array)($authentication['data'] ?? []);

    if ($originHostCanon !== '' && !$this->originAllowedByConsumer($originHostCanon, (array)($consumer['origins'] ?? []))) {
        return [
            'status'    => 'unauthorized',
            'code'      => 'cors_denied',
            'message'   => 'Origen no autorizado',
            'http_code' => 403,
        ];
    }

    return [
        'status'       => 'success',
        'cors_headers' => !empty($originHostCanon),
        'context' => [
            'api_consumer' => (string)($consumer['name'] ?? ''),
            'api_tenant_id' => $consumer['tenant_id'] ?? null,
            'api_scopes' => (array)($consumer['scopes'] ?? []),
        ],
    ];
}

private function originAllowedByConsumer(string $origin, array $allowed): bool {
    return in_array('*', $allowed, true) || in_array($origin, $allowed, true);
}

private function canonicalApiHost(string $value): string {
    $value = trim(strtolower($value));
    if ($value === '*') return '*';
    $host = parse_url(str_contains($value, '://') ? $value : 'https://' . $value, PHP_URL_HOST) ?: $value;
    return (string)preg_replace('/^www\./', '', strtolower($host));
}




private function webhook_guard(array $routeParams = []): array {
    $ctx    = $routeParams['context'] ?? [];
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Bloquea navegador y preflight
    if ($method === 'OPTIONS' || !empty($_SERVER['HTTP_ORIGIN']) || !empty($_SERVER['HTTP_REFERER'])) {
        return ['status'=>'unauthorized','code'=>'cors_blocked','message'=>'Solicitud bloqueada','http_code'=>403];
    }

    // Método (default POST)
    $allowed = $ctx['methods'] ?? ['POST'];
    if (!in_array($method, $allowed, true)) {
        return ['status'=>'unauthorized','code'=>'method_not_allowed','message'=>'Método no permitido','http_code'=>403];
    }

    // Límite de payload
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len > 2*1024*1024) {
        return ['status'=>'unauthorized','code'=>'webhook_blocked','message'=>'Payload demasiado grande','http_code'=>403];
    }

    // Conservar los defaults si el contexto no configura una credencial verificable.
    $hasHeaderSecret = !empty($ctx['require_header']) && is_string($ctx['expected_value'] ?? null) && $ctx['expected_value'] !== '';
    $requiredQuery = (array)($ctx['require_query'] ?? []);
    $hasQuerySecret = $requiredQuery !== [] && !empty($ctx['expected_value']);
    foreach ($requiredQuery as $parameter) {
        $expected = is_array($ctx['expected_value'] ?? null) ? ($ctx['expected_value'][$parameter] ?? null) : ($ctx['expected_value'] ?? null);
        $hasQuerySecret = $hasQuerySecret && is_string($expected) && $expected !== '';
    }
    $hasHmac = !empty($ctx['validate_hmac']) && !empty($ctx['require_header']) && is_string($ctx['hmac_secret'] ?? null) && $ctx['hmac_secret'] !== '';
    if (!empty($ctx['validate_hmac']) && !$hasHmac) {
        return ['status' => 'unauthorized', 'code' => 'invalid_webhook_configuration', 'http_code' => 403];
    }
    if (!$hasHeaderSecret && !$hasQuerySecret && !$hasHmac) {
        // Exigir JSON
        if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
            return ['status'=>'unauthorized','code'=>'unsupported_media','message'=>'Content-Type inválido','http_code'=>403];
        }
        // Exigir secreto por cabecera (defínelo en env/config)
        $hdr = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
        $expected = defined('WEBHOOK_DEFAULT_SECRET') ? WEBHOOK_DEFAULT_SECRET : '';
        if ($expected === '' || !hash_equals((string)$expected, (string)$hdr)) {
            return ['status'=>'unauthorized','code'=>'invalid_secret','message'=>'Cabecera inválida','http_code'=>403];
        }
        // (Opcional) Valida un timestamp anti-replay
        // $ts = $_SERVER['HTTP_X_WEBHOOK_TS'] ?? '';
        // if (abs(time() - (int)$ts) > 300) { ... unauth 403 ... }
    }

    // Reglas opcionales por contexto (si existen)
    if (!empty($ctx['require_header'])) {
        $headerValue = $_SERVER['HTTP_' . str_replace('-', '_', strtoupper($ctx['require_header']))] ?? '';
        if (is_string($ctx['expected_value'] ?? null) && $ctx['expected_value'] !== '') {
            if (!hash_equals($ctx['expected_value'], (string)$headerValue)) {
                 return ['status'=>'unauthorized','code'=>'invalid_secret','message'=>'Cabecera inválida','http_code'=>403];
            }
        } elseif ($headerValue === '') {
            return ['status'=>'unauthorized','code'=>'missing_header','message'=>'Falta cabecera requerida','http_code'=>403];
        }
    }

    // ✅ Reglas opcionales por contexto: require_query (soporta string o array)
if (!empty($ctx['require_query'])) {
    $requiredParams = is_array($ctx['require_query']) ? $ctx['require_query'] : [$ctx['require_query']];
    foreach ($requiredParams as $param) {
        $queryValue = $_GET[$param] ?? '';
        if ($queryValue === '') {
            return ['status'=>'unauthorized','code'=>'missing_param','message'=>"Falta parámetro $param",'http_code'=>403];
        }
        // Si se definió expected_value y es un array, puedes mapearlo por parámetro
        elseif (!empty($ctx['expected_value']) && is_array($ctx['expected_value']) && isset($ctx['expected_value'][$param])) {
            if (!hash_equals((string)$ctx['expected_value'][$param], (string)$queryValue)) {
                return ['status'=>'unauthorized','code'=>'invalid_param','message'=>"Parámetro $param inválido",'http_code'=>403];
            }
        }
        // expected_value como string → solo aplica al primer param
        elseif (!empty($ctx['expected_value']) && !is_array($ctx['expected_value'])) {
            if (!hash_equals((string)$ctx['expected_value'], (string)$queryValue)) {
                return ['status'=>'unauthorized','code'=>'invalid_param','message'=>"Parámetro $param inválido",'http_code'=>403];
            }
        }
        
    }
}


    if (!empty($ctx['validate_hmac'])) {
        // ⚠️ Si el controlador también leerá php://input, cachea:
        if (!isset($GLOBALS['RAW_INPUT'])) {
            $GLOBALS['RAW_INPUT'] = file_get_contents('php://input') ?: '';
        }
        $raw = $GLOBALS['RAW_INPUT'];
        $sigHeader = $_SERVER['HTTP_' . str_replace('-', '_', strtoupper($ctx['require_header']))] ?? '';
        $calc = 'sha256=' . hash_hmac('sha256', $raw, $ctx['hmac_secret']);
        if (!hash_equals($calc, $sigHeader)) {
            return ['status'=>'unauthorized','code'=>'invalid_signature','message'=>'Firma inválida','http_code'=>403];
        }
    }

    return ['status'=>'success'];
}





private function sse_guard(array $routeParams = []): array {
    // 1) Método correcto
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') {
        return ['status' => 'unauthorized', 'code' => 'sse_no_options', 'message' => 'OPTIONS no permitido'];
    }
    if ($method !== 'GET') {
        return ['status' => 'unauthorized', 'code' => 'sse_only_get', 'message' => 'SSE requiere GET'];
    }
 
 
    // 3) Si no hay mismo origen y tampoco hay auth/token requerido → bloquear

    $ctx = $routeParams['context'] ?? [];
    $requiresAuth = in_array('auth', $routeParams['middleware'] ?? [], true) || !empty($ctx['require_token']);

    if (!$this->is_same_origin() && !$requiresAuth) {
    return [
        'status' => 'unauthorized', 
        'code' => 'forbidden', 
        'message' => 'Llamada externa no permitida.',
        'http_code'=> 403
    ];
}

    // 4) Liberar lock de sesión
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }

    // 5) Token opcional via context
    if (!empty($ctx['require_token'])) {
        if ((!is_string($ctx['expected'] ?? null) || $ctx['expected'] === '') && !is_callable($ctx['verify'] ?? null)) {
            return ['status' => 'unauthorized', 'code' => 'sse_token_configuration', 'http_code' => 403];
        }
        $paramName  = $ctx['token_param']  ?? 'token';
        $headerName = $ctx['token_header'] ?? 'Authorization';

        // token por query
        $token = $_GET[$paramName] ?? '';

        // o por header
        if (!is_string($token) || $token === '') {
            $token = '';
            $hdrKey = 'HTTP_' . str_replace('-', '_', strtoupper($headerName));
            $rawHdr = $_SERVER[$hdrKey] ?? '';
            if ($rawHdr) {
                $token = stripos($rawHdr, 'Bearer ') === 0 ? trim(substr($rawHdr, 7)) : trim($rawHdr);
            }
        }

        if ($token === '') {
            return ['status' => 'unauthorized', 'code' => 'sse_token_missing', 'message' => 'Falta token'];
        }

        // Validación simple por valor esperado
        if (is_string($ctx['expected'] ?? null) && $ctx['expected'] !== '' && !hash_equals($ctx['expected'], $token)) {
            return ['status' => 'unauthorized', 'code' => 'sse_token_invalid', 'message' => 'Token inválido'];
        }

        // Validación custom via callable
        if (!empty($ctx['verify']) && is_callable($ctx['verify'])) {
            if (!call_user_func($ctx['verify'], $token, $routeParams)) {
                return ['status' => 'unauthorized', 'code' => 'sse_token_invalid', 'message' => 'Token inválido'];
            }
        }
    }

    return ['status' => 'success'];
}


private  function val_honeypot(): array{
    $middle_name = $_POST['middle_name'] ?? false;

    if ($middle_name) {
      return ['status' => 'unauthorized','code' => 'is_bot'];  
    }
    return ['status' => 'success'];  
  }

  private  function val_CSRF(): array{ 
     $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    return ['status'=>'success'];// fallback para rutas q no deben aplciar el csrf
}

    // Origen mismo sitio
    $hasHeaders = !empty($_SERVER['HTTP_ORIGIN']) || !empty($_SERVER['HTTP_REFERER']);
if ($hasHeaders && !$this->is_same_origin()) {
    return ['status'=>'unauthorized','code'=>'fail_csrf','message'=>'Origen inválido'];
}

    $inCsrfToken = $_POST['csrfToken'] ?? null; 
    $inCsrfTimestamp = $_POST['csrfTimestamp'] ?? null;

    if ($inCsrfToken !== null && $inCsrfTimestamp !== null) {
      $csrf_token = $inCsrfToken;
      $csrf_timestamp = $inCsrfTimestamp;

       // Verifica si el token CSRF y la marca de tiempo coinciden con los valores almacenados en la sesión
      if (!isset($_SESSION['csrfToken']) || !isset($_SESSION['csrfTimestamp'])) {
        return ['status' => 'unauthorized', 'code' => 'login_required'];
      }

      if ($csrf_token === $_SESSION['csrfToken'] && (int)$csrf_timestamp === (int)$_SESSION['csrfTimestamp']) {
        return ['status' => 'success']; 
      }
      else {// El token CSRF y/o la marca de tiempo no son válidos
      // Verifica si la marca de tiempo es anterior a la marca de tiempo almacenada en la sesión
        if ($csrf_timestamp < $_SESSION['csrfTimestamp']) {
           // La marca de tiempo es anterior, esto significa que la sesión se cerró y abrió en otra pestaña
          return  ['status' => 'unauthorized','code' => 'to_reload']; 
        }
        else {
          // La marca de tiempo es posterior, esto significa que el token CSRF no coincide debido a un ataque CSRF
          return  ['status' => 'unauthorized','code' => 'fail_csrf','message' => 'Falta el token CSRF'];
        }
      }
    }

    return ['status' => 'unauthorized','code' => 'fail_csrf','message' => 'Falta el token CSRF'];
  }


  private  function auth(): array { 
    if (!$this->hasSessionIdentity()) {
      return ['status' => 'unauthorized', 'code' => 'login_required'];
    }

    return ['status' => 'success'];
  }

  private  function guest(): array {
    if ($this->hasSessionIdentity()) {
      return ['status' => 'unauthorized', 'code' => 'already_logged'];
    }
      return ['status' => 'success'];
  }

  private  function isadmin(): array {
    if (!$this->hasSessionIdentity()) {
      return ['status' => 'unauthorized', 'code' => 'login_required'];
    }

    $authorization = $this->rolePermissionService->authorize($this->sessionUserID(), 'admin.access');
    if (($authorization['status'] ?? '') !== 'success') {
      return $authorization;
    }

    return ['status' => 'success', 'context' => [
      'role' => (string)($authorization['data']['role'] ?? $this->sessionRole()),
    ]];
  }

  private function checkRole(string $requiredRole): array {
    if (!$this->hasSessionIdentity()) {
      return ['status' => 'unauthorized', 'code' => 'login_required'];
    }

    $requiredRole = mb_strtolower(trim($requiredRole), 'UTF-8');
    $resolved = $this->rolePermissionService->role($this->sessionUserID());
    if (
      $requiredRole === ''
      || ($resolved['status'] ?? '') !== 'success'
      || (string)($resolved['data']['role'] ?? '') !== $requiredRole
    ) {
      return ['status' => 'unauthorized', 'code' => 'forbidden'];
    }

    return ['status' => 'success', 'context' => ['role' => $requiredRole]];
  }

  

private function checkPermission($permission, $routeParams): array {

        $userId = $this->sessionUserID();
        if ($userId <= 0) return ['status'=>'unauthorized','code'=>'forbidden'];

        [$module, $action] = $this->resolvePermissionTarget((string)$permission, $routeParams);
        if ($module === '' || $action === '') {
            return ['status' => 'unauthorized', 'code' => 'permission'];
        }

        $resolvedRole = $this->rolePermissionService->role($userId);
        if (
            ($resolvedRole['status'] ?? '') === 'success'
            && !empty($resolvedRole['data']['bypass'])
        ) {
            return ['status' => 'success', 'context' => [
                'role' => \GFrame\Auth\SystemRole::SUPERADMINISTRATOR,
                'permission_module' => $module,
                'permission_action' => $action,
            ]];
        }

        if (!$this->usesTenantPermissions()) {
            $authorization = $this->rolePermissionService->authorize($userId, $module . '.' . $action);
            if (($authorization['status'] ?? '') !== 'success') {
                return $authorization;
            }

            return ['status' => 'success', 'context' => [
                'role' => (string)($authorization['data']['role'] ?? $this->sessionRole()),
                'permission_module' => $module,
                'permission_action' => $action,
            ]];
        }

        $tenantID = $this->resolveTenantID($routeParams);
        if ($tenantID <= 0) return ['status' => 'unauthorized', 'code' => 'forbidden'];
        $authorization = $this->rolePermissionService->authorize($userId, $module . '.' . $action, $tenantID);
        if (($authorization['status'] ?? '') !== 'success') return $authorization;

        return ['status' => 'success', 'context' => [
            'role' => (string)($authorization['data']['role'] ?? $this->sessionRole()),
            'permission_module' => $module,
            'permission_action' => $action,
        ]];
}

private function sessionRole(): string {
    $normalized = is_array($_SESSION['auth'] ?? null)
        ? ($_SESSION['auth']['role'] ?? null)
        : null;
    return mb_strtolower(trim((string)($normalized ?? '')), 'UTF-8');
}

private function usesTenantPermissions(): bool {
    $hasTenantKey = defined('TENANT') && trim((string)TENANT) !== '';
    $hasTenantTable = defined('TENANT_TABLE') && trim((string)TENANT_TABLE) !== '';
    if ($hasTenantKey !== $hasTenantTable) {
        throw new RuntimeException('La configuración de permisos requiere definir TENANT y TENANT_TABLE juntos.');
    }
    return $hasTenantKey && $hasTenantTable;
}

private function hasSessionIdentity(): bool {
    return $this->sessionUserID() > 0;
}

private function sessionUserID(): int {
    $normalized = is_array($_SESSION['auth'] ?? null)
        ? (int)($_SESSION['auth']['id'] ?? 0)
        : 0;

    return $normalized;
}

private function resolvePermissionTarget(string $permission, array $routeParams): array {
    $permission = strtolower(trim($permission));
    if (str_contains($permission, '.')) {
        [$module, $action] = array_pad(explode('.', $permission, 2), 2, '');
        return [trim($module), trim($action)];
    }

    $module = strtolower(trim((string)($routeParams['module'] ?? '')));
    return [$module, $permission];
}

private function resolveTenantID(array $routeParams): int {
    return (new \GFrame\Auth\TenantContextResolver())->resolve((array)($routeParams['params'] ?? []));
}
public  function sessionTimeout($refresh = true): array{
    $maxIdle = max(60, (int)\GFrame\Config\ConfigRepository::get('session.idle_timeout', 1800));
    $last = $_SESSION['lastActivity'] ?? time();
    if (time() - $last > $maxIdle) {
        session_unset();
        session_destroy();
        return ['status' => 'unauthorized', 'code' => 'expired', 'message' => 'Sesión expirada por inactividad.'];
    }
    else {
        if ($refresh) {
            $_SESSION['lastActivity'] = time();
        }
            
        return ['status' => 'success'];
    }
}



/**
 * Valida si el origen de la petición coincide con el del sitio.
 * Devuelve true si es el mismo origen, false si no.
 */

private function is_same_origin(): bool {
    $origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $originUrl   = $origin ?: $referer;

    $originParts = $originUrl ? (parse_url($originUrl) ?: []) : [];

    // Respetar proxy/CDN
    $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($_SERVER['REQUEST_SCHEME'] ?? null);
    $host   = $_SERVER['HTTP_X_FORWARDED_HOST']  ?? ($_SERVER['HTTP_HOST'] ?? null);


    $siteParts = parse_url(site_url) ?: [];
    $siteParts['scheme'] = $siteParts['scheme'] ?? $scheme;
    $siteParts['host']   = $siteParts['host']   ?? $host;

    $originParts['port'] = $originParts['port'] ?? ((strtolower($originParts['scheme'] ?? '') === 'https') ? 443 : 80);
    $siteParts['port']   = $siteParts['port']   ?? ((strtolower($siteParts['scheme'] ?? '') === 'https') ? 443 : 80);


    // Comparación estricta
    return (
        !empty($originParts['host']) &&
        strtolower($originParts['scheme'] ?? '') === strtolower($siteParts['scheme'] ?? '') &&
        strtolower($originParts['host']) === strtolower($siteParts['host'] ?? '') &&
        (int)$originParts['port'] === (int)$siteParts['port']
    );
}


 

// fin de la clase 
}



