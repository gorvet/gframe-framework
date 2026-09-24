<?php

class ErrorResponder {  
    public function buildRouteParams($code, array $options = []): array
    {
        $resolved = $this->resolveDefinition((string)$code);

        $tolink = $this->resolveErrorToLink($options);

      

        return [
            'type'         => $options['type'] ?? 'web',
            'method'       => $options['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            'controller'   => 'error/ErrorResponder',
            'relativePath' => 'error',
            'templateName' => 'error',
            'view'         => $resolved['view'],
            'actionName'   => $resolved['actionName'],
            'skipAction'   => true,
            'module'       => 'error',
            'httpCode'     => $resolved['httpCode'],
            'lang'         => $options['lang'] ?? (defined('APP_LANG') ? APP_LANG : 'es'),
            'params'       => [
                'tolink'  => $tolink,
                'infoMsg' => $options['infoMsg'] ?? '',
                'helpMsg' => $options['helpMsg'] ?? '',
                'helpUrl' => $options['helpUrl'] ?? '',
                'helpLabel' => $options['helpLabel'] ?? '',
                'helpEnabled' => array_key_exists('helpEnabled', $options) && $options['helpEnabled'] !== null
                    ? (bool)$options['helpEnabled']
                    : null,
            ],
            'middleware'   => [],
            'permission'   => null,
            'context'      => $options['context'] ?? [],
            'currentURL'   => $options['currentURL'] ?? null,
        ];
    }

    public function __call($name, $arguments)
    {
        return [];
    }

    public function normalizeErrorCode($code): string
    {
        $normalized = trim((string)$code);

        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^[0-9]+$/', $normalized)) {
            $numericAliases = [
                '0' => 'external_api_error',
                '403' => 'forbidden',
                '404' => 'not_found',
                '500' => 'internal_error',
                '503' => 'service_unavailable',
                '1045' => 'service_unavailable',
                '1049' => 'service_unavailable',
                '2002' => 'service_unavailable',
            ];

            return $numericAliases[$normalized] ?? $normalized;
        }

        $aliases = [
            'forbidden' => 'forbidden',
            'permission' => 'permission',
            'badController' => 'not_found',
            'badcontroller' => 'not_found',
            'bad_controller' => 'not_found',
            'missingClass' => 'not_found',
            'missingclass' => 'not_found',
            'missing_class' => 'not_found',
            'badMethod' => 'not_found',
            'badmethod' => 'not_found',
            'bad_method' => 'not_found',
            'route_not_found' => 'not_found',
            'not_found' => 'not_found',
            '42s02' => 'service_unavailable',
            'db_error' => 'service_unavailable',
            'database_error' => 'service_unavailable',
            'reload' => 'to_reload',
            'to_reload' => 'to_reload',
            'internal_error' => 'internal_error',
            'service_unavailable' => 'service_unavailable',
            'external_api_error' => 'external_api_error',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    public function resolveHttpCode($code, int $fallback = 500): int
    {
        $normalized = $this->normalizeErrorCode($code);

        if ($normalized !== '' && preg_match('/^[0-9]{3}$/', $normalized)) {
            $http = (int)$normalized;
            if ($http >= 100 && $http <= 599) {
                return $http;
            }
        }

        $map = [
            'permission' => 403,
            'forbidden' => 403,
            'login_required' => 401,
            'expired' => 401,
            'already_logged' => 403,
            'to_reload' => 409,
            'is_bot' => 403,
            'fail_csrf' => 403,
            'invalid_token' => 401,
            'cors_denied' => 403,
            'cors_blocked' => 403,
            'method_not_allowed' => 405,
            'unsupported_media' => 415,
            'invalid_secret' => 403,
            'missing_header' => 400,
            'missing_param' => 400,
            'invalid_param' => 400,
            'invalid_signature' => 403,
            'sse_no_options' => 405,
            'sse_only_get' => 405,
            'sse_token_missing' => 401,
            'sse_token_invalid' => 403,
            'not_found' => 404,
            'internal_error' => 500,
            'service_unavailable' => 503,
            'external_api_error' => 503,
        ];

        return $map[$normalized] ?? $fallback;
    }

    public function resolveWebRouteCode($code): string
    {
        $normalized = $this->normalizeErrorCode($code);

        if ($normalized === 'permission') {
            return 'permission';
        }

        if ($normalized === 'service_unavailable') {
            return 'service_unavailable';
        }

        if ($normalized === 'not_found') {
            return 'not_found';
        }

        if ($normalized === 'forbidden') {
            return 'forbidden';
        }

        $httpCode = $this->resolveHttpCode($normalized, 500);
        if ($httpCode === 404) {
            return 'not_found';
        }
        if ($httpCode === 503) {
            return 'service_unavailable';
        }
        if ($httpCode === 401 || $httpCode === 403) {
            return 'forbidden';
        }
        if ($httpCode >= 500) {
            return 'internal_error';
        }

        return 'internal_error';
    }

    private function resolveDefinition(string $code): array
    {
        $normalized = $this->resolveWebRouteCode($code);

        $map = [
            'permission' => ['view' => 'errorPermissions', 'actionName' => '403', 'httpCode' => 403],
            'forbidden'  => ['view' => 'error403',        'actionName' => '403', 'httpCode' => 403],
            'not_found'  => ['view' => 'error404',        'actionName' => '404', 'httpCode' => 404],
            'internal_error' => ['view' => 'error500',    'actionName' => '500', 'httpCode' => 500],
            'service_unavailable' => ['view' => 'error503', 'actionName' => '503', 'httpCode' => 503],
        ];

        return $map[$normalized] ?? $map['service_unavailable'];
    }


  private function resolveErrorToLink(array $routeParams=[]): string {
    if (!empty($routeParams['params']['tolink'])) {
      return (string)$routeParams['params']['tolink'];
    }

    if (!empty($routeParams['tolink'])) {
      return (string)$routeParams['tolink'];
    }

    if (!empty($routeParams['currentURL'])) {
      return $this->buildParentUrlFromCurrent((string)$routeParams['currentURL']);
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer !== '') {
      $siteHost = parse_url(site_url, PHP_URL_HOST);
      $refererHost = parse_url($referer, PHP_URL_HOST);
      if (!empty($siteHost) && !empty($refererHost) && strcasecmp($siteHost, $refererHost) === 0) {
        return $referer;
      }
    }
 
    return site_url;
  }

  private function buildParentUrlFromCurrent(string $currentURL): string {
    
    $path = parse_url($currentURL, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
      return site_url;
    }

    $basePath = parse_url(site_url, PHP_URL_PATH);
    $basePath = is_string($basePath) ? rtrim($basePath, '/') : '';

    if ($basePath !== '' && str_starts_with($path, $basePath)) {
      $relative = substr($path, strlen($basePath));
    } else {
      $relative = $path;
    }

    $segments = array_values(array_filter(explode('/', trim((string)$relative, '/')), 'strlen'));
    if (count($segments) === 0) {
      return site_url;
    }
    if (count($segments) === 1) {
      return site_url;
    }

    $last = end($segments);
    if (ctype_digit((string)$last)) {
      array_pop($segments);
    } elseif (count($segments) > 1) {
      array_pop($segments);
    }

    if (count($segments) === 0) {
      return site_url;
    }

    return rtrim(site_url, '/') . '/' . implode('/', $segments);
  }
}
