<?php
// Render.php
// Este archivo se encarga de crear la vista en pantalla

class Render {

    protected $metasController;
    protected $controller;

    protected bool $handlingError = false;

    public function __construct() {
        $this->metasController = Meta::getInstance();
    }


    public function renderView(array $routeParams) {
        //$traza = debug_backtrace();
        //print_r($routeParams);
        $routeParams = $this->normalizeRouteParams($routeParams);
        $this->metasController->reset();
    

        if (
            ($routeParams['controller'] ?? '') === 'seo/Sitemap'
            || !empty($routeParams['context']['sitemap']['build'])
            || (($routeParams['currentURL'] ?? '') && preg_match('#/sitemap\.xml$#', $routeParams['currentURL']))
        ) {
            if (!defined('SITEMAP_BUILD')) define('SITEMAP_BUILD', true);
        }

        $data = [];
        if (!$this->isErrorRoute($routeParams)) {
            // Instanciar el controlador correspondiente a la vista solicitada
            $this->controller = $this->getControllerInstance($routeParams);

            $data = $this->executeAction($routeParams);
            //echo "<pre>";
            //print_r($routeParams);
            //echo "</pre>";

            $data = $this->interpretDataResponse($data);
        }

        //Cargar la vista con un contenido especifico
        $content = $this->loadView($routeParams, $data);
        $this->applyHttpCodeFromRoute($routeParams);

        $this->loadTemplate($content, $routeParams, $data);

    }

    private function executeAction($routeParams) {

        //var_dump($routeParams);
        if (!empty($routeParams['skipAction']) || empty($routeParams['actionName'])) {
            return []; // o null
        }
        $action = $routeParams['actionName'];

        if (method_exists($this->controller, $action)) {
            return $this->controller->$action($routeParams);
        }

        DebugMode
            ? $this->errorControl('404', '', 'Accion "' . $action . '" no encontrada')
            : $this->errorControl('404');
    }


    private function loadView(array $routeParams, $data = []) {
        //echo '<pre>';print_r($routeParams);echo '</pre>';
        $viewName = $routeParams['view'];
        $relativePath = $routeParams['relativePath'];
        $templateName = $routeParams['templateName'];

        $parts = explode('/', $relativePath);
        $groupName = end($parts);
        //$groupName=$groupName==$relativePath?"":$groupName;

        $metaGroupPath = realpath(ABSPATH . "app/views/" . $relativePath . "/" . $groupName . ".group.meta.php");



        //print_r($routeParams);

        $metaFilePath = realpath(ABSPATH . "app/views/" . $relativePath . "/" . $viewName . ".meta.php");


        $metaGroupData = [];
        if (file_exists($metaGroupPath)) {
            $metaGroupData = require $metaGroupPath;
        }

        $viewPath = realpath(ABSPATH . "app/views/" . $relativePath . "/" . $viewName . ".php");

        $metaData = [];
        if (file_exists($metaFilePath)) {

            $metaData = require $metaFilePath;
        }

        $finalMetaData = $this->mergeMetaArrays($metaGroupData, $metaData);

        $this->setMetas($finalMetaData, $routeParams);

        if (file_exists($viewPath)) {
            ob_start(); // Iniciar el bufer de salida
            // Inyectar la variable $data directamente en la vista
            if ($data !== null) {
                $viewData = $data;
            }
            require_once $viewPath;
            return ob_get_clean(); // Obtener el contenido del bufer y limpiarlo
        } else {
            DebugMode ? $this->errorControl('404', '', 'Vista no encontrada') : $this->errorControl('404');
        }

    }

    private function getControllerInstance(array $routeParams) {
        $controller=$routeParams['controller'];
        //var_dump($controller);
        $controllerPath = $this->resolveControllerPath($controller);
        // echo'app/controllers/'.$controller.'.php';

        if ($controllerPath !== null && file_exists($controllerPath)) {
            //comprobamos la existencia del controlador
            require_once $controllerPath;
            $parts = explode('/', str_replace('\\', '/', $controller));
            $className = end($parts);
            $controllerInstance = $this->instantiateController($className, $routeParams);
            return $controllerInstance;

        } else {
            DebugMode ? $this->errorControl('404', '', 'Controlador no encontrado') : $this->errorControl('404');
        }

    }

    public function loadTemplate($content, array $routeParams, array $data = []) {
        //print_r($routeParams);
        $templateFile = ABSPATH . 'app/views/templates/' . $routeParams['templateName'] . 'Template.php';
        $bodyClass = $this->buildBodyClasses($routeParams);


        if (!file_exists(realpath($templateFile))) {
            DebugMode ? $this->errorControl('404', '', 'Template no encontrado') : $this->errorControl('404');
        }



        include realpath(ABSPATH . "app/views/templates/header.php");
        include realpath(ABSPATH . "app/views/templates/" . $routeParams['templateName'] . 'Template.php');
        include realpath(ABSPATH . "app/views/templates/footer.php");

    }


    private function setMetas($metaData, $routeParams) {

        //var_dump($metaData['metaTags']);
        $this->metasController->setRouteParams($routeParams);

        if (!empty($routeParams['lang'])) {
            $this->metasController->setMetaTags([
                'oglocale' => $routeParams['lang'],
                // === 'es' ? 'es-ES' : 'en-US' // ajusta si usas mas idiomas
            ]);
        }
        if (!empty($routeParams['currentURL'])) {
            $this->metasController->setMetaTags([
                'ogurl' => $routeParams['currentURL'],
                'canonical' => $routeParams['currentURL']
            ]);
        }


        if (is_array($metaData)) {

            if (!empty($metaData['metaTags'])) {
                if (isset($routeParams['isProtected']) && $routeParams['isProtected'] == true) {
                    $metaData['metaTags']['darkmode'] = true;
                }
                $this->metasController->setMetaTags($metaData['metaTags']);
            }

            if (!empty($metaData['css'])) {
                $this->metasController->setCssLinks($metaData['css']);
            }

            if (!empty($metaData['js'])) {
                $this->metasController->setJsScripts($metaData['js']);
            }

            if (!empty($metaData['hjs'])) {
                $this->metasController->setHeaderJsScripts($metaData['hjs']);
            }

            if (!empty($metaData['credits'])) {
                $this->metasController->setFooterCredits($metaData['credits']);
            }

            if (!empty($metaData['schema'])) {
                $this->metasController->setSchema($metaData['schema']);
            }
        }


    }

    private function mergeMetaArrays(array $base, array $override): array {
        foreach ($override as $key => $value) {
            if (!isset($base[$key])) {
                $base[$key] = $value;
            } elseif (is_array($base[$key]) && is_array($value)) {
                // Tratar como diccionario: metaTags y schema
                if ($key === 'metaTags') {
                    $base[$key] = array_merge($base[$key], $value);
                } elseif ($key === 'schema') {
                    $base[$key] = array_replace_recursive($base[$key], $value);
                } else {
                    // Listas (css, js): fusionar evitando duplicados
                    $base[$key] = array_values(array_unique(array_merge($base[$key], $value)));
                }
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }



    public function errorControl($action, $tolink = "", $infoMsg = "", array $options = []) {
        if ($this->handlingError) {
            die("Error critico: no se pudo cargar el error handler. Accion: $action");
        }

        $this->handlingError = true;

        if ($tolink == "") {
            $tolink = site_url;
        }

        $errorController = new ErrorResponder();
        $routeParams = $errorController->buildRouteParams((string)$action, [
            'type' => 'web',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'lang' => defined('APP_LANG') ? APP_LANG : 'es',
            'currentURL' => $_SERVER['REQUEST_URI'] ?? '',
            'tolink' => $tolink,
            'infoMsg' => $infoMsg,
            'helpMsg' => $options['helpMsg'] ?? '',
            'helpUrl' => $options['helpUrl'] ?? '',
            'helpLabel' => $options['helpLabel'] ?? '',
            'helpEnabled' => array_key_exists('helpEnabled', $options) && $options['helpEnabled'] !== null
                ? (bool)$options['helpEnabled']
                : null,
        ]);


        $content = $this->loadView($routeParams);
        $this->applyHttpCodeFromRoute($routeParams);
        $this->errorTemplate($content, $routeParams);
        die();
    }



    public function errorTemplate($content, $routeParams = []) {

        $bodyClass = $this->buildBodyClasses($routeParams);

        include realpath(ABSPATH . "app/views/templates/header.php");
        include realpath(ABSPATH . "app/views/templates/errorTemplate.php");
        include realpath(ABSPATH . "app/views/templates/footer.php");
    }

    /*public function ajaxToRender(array $routeParams) {
        $html = $this->renderView($routeParams);
        echo $html;
    }*/

    // Extrae datos desde la vista para modulos legacy ya montados sobre el nuevo render.
    public function getDatas($method = '', array $params = []) {
        if (is_object($this->controller) && method_exists($this->controller, (string) $method)) {
            return $this->controller->$method($params);
        }

        return ['status' => 'error', 'code' => 'method_not_found', 'message' => 'El metodo no existe en el controlador'];
    }

    private function buildBodyClasses($routeParams) {
        $classes = [];

        $base = dirname($_SERVER['SCRIPT_NAME']);
        $uri = substr($_SERVER['REQUEST_URI'], strlen($base));
        $segments = explode('/', trim(parse_url($uri, PHP_URL_PATH), '/'));

        $templateName = $routeParams['templateName'] ?? '';
        $viewName = $routeParams['actionName'] ?? '';

        $prev = null;
        $lastNonNumeric = null;
        if ($templateName != "error") {

            foreach ($segments as $i => $seg) {
                if (is_numeric($seg) && $prev) {
                    $classes[] = $prev . '-' . $seg;
                } else {
                    $classes[] = $seg;
                    $lastNonNumeric = $seg; // lo mas reciente no numerico
                }
                $prev = $seg;
            }

        }


        // Combinacion con el ultimo no numerico
        if ($viewName && $lastNonNumeric) {
            //$classes[] = $lastNonNumeric . '-a' . $viewName;
        }
        // Clases base
        if ($templateName) $classes[] = 'tpl-' . $templateName;
        if ($viewName) $classes[] = 'act-' . $viewName;



        return implode(' ', array_unique($classes));
    }

    private function interpretDataResponse($data) {
        if (!is_array($data)) return $data;

        $status = (string)($data['status'] ?? '');
        if (!in_array($status, ['error', 'unauthorized'], true)) return $data;

        $errorController = new ErrorResponder();
        $routeCode = $errorController->resolveWebRouteCode($data['code'] ?? '');
        $debugMessage = $data['message'] ?? 'Ocurrio un error inesperado';

        $errorOptions = [
            'helpMsg' => $data['helpMsg'] ?? '',
            'helpUrl' => $data['helpUrl'] ?? '',
            'helpLabel' => $data['helpLabel'] ?? '',
            'helpEnabled' => array_key_exists('helpEnabled', $data) && $data['helpEnabled'] !== null
                ? (bool)$data['helpEnabled']
                : null,
        ];

        DebugMode
            ? $this->errorControl($routeCode, '', $debugMessage, $errorOptions)
            : $this->errorControl($routeCode, '', '', $errorOptions);

    }

    private function normalizeRouteParams(array $routeParams): array {
        if ($this->isErrorRoute($routeParams)) {
            $routeParams['skipAction'] = true;
        }
        return $routeParams;
    }

    private function isErrorRoute(array $routeParams): bool {
        $controller = (string)($routeParams['controller'] ?? '');
        $template = strtolower((string)($routeParams['templateName'] ?? ''));
        $view = strtolower((string)($routeParams['view'] ?? ''));

        return $controller === 'error/ErrorResponder'
            || $template === 'error'
            || str_starts_with($view, 'error');
    }

    private function applyHttpCodeFromRoute(array $routeParams): void {
        if (headers_sent()) {
            return;
        }

        if (isset($routeParams['httpCode']) && is_numeric($routeParams['httpCode'])) {
            http_response_code((int)$routeParams['httpCode']);
            return;
        }

        $inferred = $this->inferHttpCodeFromRoute($routeParams);
        if ($inferred !== null) {
            http_response_code($inferred);
        }
    }

    private function inferHttpCodeFromRoute(array $routeParams): ?int {
        $actionName = (string)($routeParams['actionName'] ?? '');
        if (preg_match('/([0-9]{3})$/', $actionName, $matches)) {
            return (int)$matches[1];
        }

        $view = strtolower((string)($routeParams['view'] ?? ''));
        if ($view === 'errorpermissions') {
            return 403;
        }
        if (preg_match('/error([0-9]{3})$/', $view, $matches)) {
            return (int)$matches[1];
        }

        return null;
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

    private function instantiateController(string $className, array $routeParams) {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
            return $reflection->newInstance();
        }

        return $reflection->newInstance($routeParams);
    }


// Fin de la clase
}
