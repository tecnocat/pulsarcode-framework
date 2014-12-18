<?php

namespace Pulsarcode\Framework\Router;

use Pulsarcode\Framework\Cache\Cache;
use Pulsarcode\Framework\Config\Config;
use Pulsarcode\Framework\Controller\Controller;
use Pulsarcode\Framework\Core\Core;
use Pulsarcode\Framework\Error\Error;
use Pulsarcode\Framework\Util\Util;
use Pulsarcode\Framework\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherDumper;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Router Para gestionar las rutas
 *
 * @package Pulsarcode\Framework\Router
 */
class Router extends Core
{
    /**
     * Archivo de rutas
     */
    const ROUTES_FILE = 'routes.yml';

    /**
     * Patrón para los namespaces de los controladores
     *
     * TODO: Quitar la dependencia del nombre del bundle, hacerlo dinámico buscando en src/
     */
    const CONTROLLER_NAME_PATTERN = 'Autocasion\\MainBundle\\Controller\\%sController';

    /**
     * Patrón para importar un recurso CSS
     */
    const STATIC_CSS_IMPORT_PATTERN = '<link rel="stylesheet" type="text/css"%s href="%s" />';

    /**
     * Patrón para insertar un contenido CSS
     */
    const STATIC_CSS_INSERT_PATTERN = '<style type="text/css"%s>%s</style>';

    /**
     * Patrón para importar un recurso JS
     */
    const STATIC_JS_IMPORT_PATTERN = '<script type="text/javascript"%s src="%s"></script>';

    /**
     * Patrón para insertar un contenido JS
     */
    const STATIC_JS_INSERT_PATTERN = '<script type="text/javascript"%s>%s</script>';

    /**
     * Patrón para importar un recurso IMG
     */
    const STATIC_IMG_IMPORT_PATTERN = '<img%s src="%s" />';

    /**
     * Patrón para insertar un contenido IMG
     */
    const STATIC_IMG_INSERT_PATTERN = '<img%s src="%s" />';

    /**
     * @var array Valores de parámetros internos del Request a excluir
     */
    public static $internalValues = array(
        '_route'     => null,
        'controller' => null,
        'redirect'   => null,
        'template'   => null,
    );

    /**
     * @var array Valores de extensiones estáticas para ignorar por el Router
     */
    private static $ignoreExtensions = array(
        'avi',
        'bmp',
        'css',
        'flv',
        'gif',
        'jpg',
        'js',
        'mov',
        'mp3',
        'mp4',
        'png',
        'rar',
        'swf',
        'wmv',
        'zip',
    );

    /**
     * @var Request Petición actual
     */
    private static $request;

    /**
     * @var array Parámetros de la ruta matcheada
     */
    private static $routerParams = array();

    /**
     * @var UrlMatcher Matcheador de rutas según definición y contexto
     */
    private static $urlMatcher;

    /**
     * @var UrlGenerator Generador de rutas según definición y parámetros
     */
    private static $urlGenerator;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        if (isset(self::$request) === false)
        {
            self::$request = Request::createFromGlobals();
            self::$request->setSession(new Session());
        }

        $routeCollection  = new RouteCollection();
        $routesCacheFile  = Config::getConfig()->paths['cache'] . DIRECTORY_SEPARATOR . self::ROUTES_FILE . '.php';
        $routesConfigFile = Config::getConfig()->paths['config'] . DIRECTORY_SEPARATOR . self::ROUTES_FILE;

        /**
         * Generamos las rutas sólo la primera vez y las guardamos en caché (sólo en pro)
         */
        if (file_exists($routesCacheFile) === false || Config::getConfig()->environment != 'pro')
        {
            if (file_exists($routesConfigFile) === false)
            {
                trigger_error('Falta archivo de rutas ' . $routesConfigFile, E_ERROR);
            }
            else
            {
                $cache     = new Cache();
                $cacheKey  = md5($routesConfigFile);
                $cacheData = $cache->getCache($cacheKey);

                if ($cacheData === false)
                {
                    $yaml      = new Yaml();
                    $cacheData = $yaml->parse($routesConfigFile);
                    $cache->setCache($cacheKey, $cacheData, 0);
                }

                self::loadRoutes($cacheData, $routeCollection);
                self::cacheRoutes($routeCollection);
            }
        }

        /**
         * Cargamos las rutas generadas por \Symfony\Routing\Matcher\Dumper\PhpMatcherDumper
         */
        require_once $routesCacheFile;

        $requestContext = new RequestContext();
        $requestContext->fromRequest(self::$request);
        self::$urlMatcher = new \RouterUrlMatcher($requestContext);

        if (isset(self::$urlGenerator) === false)
        {
            $cache     = new Cache();
            $cacheKey  = md5($routesConfigFile);
            $cacheData = $cache->getCache($cacheKey);

            if ($cacheData === false)
            {
                $yaml      = new Yaml();
                $cacheData = $yaml->parse($routesConfigFile);
                $cache->setCache($cacheKey, $cacheData, 0);
            }

            self::loadRoutes($cacheData, $routeCollection);
            self::$urlGenerator = new UrlGenerator($routeCollection, $requestContext);
        }
    }

    /**
     * Procesa y ejecuta la acción adecuada
     */
    public static function dispatch()
    {
        self::$request = Request::createFromGlobals();
        self::$request->setSession(new Session());

        if (preg_match('/\.(' . implode('|', self::$ignoreExtensions) . ')$/', self::$request->getPathInfo(), $match))
        {
            $errorData = array(
                'errorLevel'   => 'UNSUPPORTED_MEDIA_TYPE',
                'errorMessage' => 'El Router no puede procesar la petición a estáticos con extensión ' . $match[0],
                'errorFile'    => __FILE__,
                'errorLine'    => __LINE__,
            );
            Error::setError('404', $errorData);
        }

        try
        {
            new self();

            self::$routerParams = self::match(self::$request->getPathInfo());

            if (isset(self::$routerParams['_format']) !== false)
            {
                self::$request->setRequestFormat(self::$routerParams['_format']);
            }
            elseif (substr(self::$request->getPathInfo(), -4) === 'json')
            {
                self::$request->setRequestFormat('json');
            }

            /**
             * TODO: Usar el componente de seguridad en vez de esta basura
             */
            if (isset(self::$routerParams['ip']))
            {
                if (is_array(self::$routerParams['ip']) === false)
                {
                    self::$routerParams['ip'] = (array) self::$routerParams['ip'];
                }

                if (in_array($ip = self::$request->getClientIp(), self::$routerParams['ip']) === false)
                {
                    $errorData = array(
                        'errorLevel'   => 'HTTP_FORBIDDEN',
                        'errorMessage' => $ip . ' ' . self::$request->getMethod() . ' ' . self::$request->getPathInfo(),
                        'errorFile'    => __FILE__,
                        'errorLine'    => __LINE__,
                    );
                    Error::setError('403', $errorData);
                }
            }

            if (isset(self::$routerParams['controller']))
            {
                self::runController(self::$routerParams);
            }
            /**
             * TODO: Eliminar esto, sólo son válidas las rutas con controlador
             */
            elseif (isset(self::$routerParams['template']))
            {
                self::runInclude(self::$routerParams);
            }
            /**
             * TODO: Eliminar esto, sólo son válidas las rutas con controlador
             */
            elseif (isset(self::$routerParams['redirect']))
            {
                self::runRedirect(self::$routerParams);
            }
            else
            {
                trigger_error('La ruta no tiene los argumentos requeridos, no puedo ejecutar nada', E_USER_ERROR);
            }
        }
        catch (ResourceNotFoundException $exception)
        {
            /**
             * Petate 404, si Apache no sabe que ruta es y el Router tampoco es un 404
             */
            $errorData = array(
                'errorLevel'   => 'HTTP_NOT_FOUND',
                'errorMessage' => self::$request->getMethod() . ' ' . self::$request->getPathInfo(),
                'errorFile'    => $exception->getFile(),
                'errorLine'    => $exception->getLine(),
            );
            Error::setError('404', $errorData);
        }
        catch (MethodNotAllowedException $exception)
        {
            /**
             * Petate 405, si el método no es válido pero la ruta ha matcheado es un 405
             */
            $errorData = array(
                'errorLevel'   => 'HTTP_METHOD_NOT_ALLOWED',
                'errorMessage' => self::$request->getMethod() . ' ' . self::$request->getPathInfo(),
                'errorFile'    => $exception->getFile(),
                'errorLine'    => $exception->getLine(),
            );
            Error::setError('405', $errorData);
        }
    }

    /**
     * Obtiene un parámetro de la configuración de la ruta
     *
     * @param string $param   Nombre del parámetro
     * @param null   $default Valor por defecto si no existe
     *
     * @return mixed
     */
    public static function getParam($param, $default = null)
    {
        if (isset(self::$routerParams[$param]) !== false)
        {
            $result = self::$routerParams[$param];
        }
        else
        {
            $result = $default;
        }

        return $result;
    }

    /**
     * Redirecciona a un path dado
     *
     * @param string $path El path a redirigir
     * @param int    $code El tipo de redirect
     */
    public static function redirect($path, $code = 301)
    {
        $status = array(
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => 'Switch Proxy',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
        );

        if (isset($status[$code]) !== false)
        {
            switch ($code)
            {
                case 300:
                case 301:
                case 302:
                case 304:
                    $version = '1.0';
                    break;

                case 303:
                case 305 :
                case 306 :
                case 307 :
                case 308 :
                    $version = '1.1';
                    break;
            }

            header('HTTP/' . $version . ' ' . $code . ' ' . $status[$code]);
            header('Location: ' . $path, true, $code);
            die;
        }
        else
        {
            trigger_error('Código de redirección no implementado: ' . $code, E_USER_ERROR);
        }
    }

    /**
     * Devuelve una instancia de Request en estático
     *
     * @return Request
     */
    public static function getRequest()
    {
        if (isset(self::$request) === false)
        {
            new self();
        }

        return self::$request;
    }

    /**
     * Genera una URL basandose en la configuración de rutas
     *
     * @param string $name       Nombre de la ruta
     * @param array  $parameters Parámetros de la ruta
     * @param bool   $absolute   Para generar ruta absoluta
     *
     * @return string
     */
    public static function generateUrl($name, array $parameters = array(), $absolute = true)
    {
        if (isset(self::$urlGenerator) === false)
        {
            new self();
        }

        return self::$urlGenerator->generate($name, $parameters, $absolute);
    }

    /**
     * Genera un error HTTP/1.1 403 Forbidden
     */
    public static function throw403()
    {
        $backtrace = current(debug_backtrace());
        $errorData = array(
            'errorLevel'   => 'HTTP_FORBIDDEN',
            'errorMessage' => self::getRequest()->getMethod() . ' ' . self::getRequest()->getPathInfo(),
            'errorFile'    => $backtrace['file'],
            'errorLine'    => $backtrace['line'],
        );
        Error::setError('403', $errorData);
    }

    /**
     * Genera un error HTTP/1.1 404 Not Found
     */
    public static function throw404()
    {
        $backtrace = current(debug_backtrace());
        $errorData = array(
            'errorLevel'   => 'HTTP_NOT_FOUND',
            'errorMessage' => self::getRequest()->getMethod() . ' ' . self::getRequest()->getPathInfo(),
            'errorFile'    => $backtrace['file'],
            'errorLine'    => $backtrace['line'],
        );
        Error::setError('404', $errorData);
    }

    /**
     * Genera un error HTTP/1.1 405 Method Not Allowed
     */
    public static function throw405()
    {
        $backtrace = current(debug_backtrace());
        $errorData = array(
            'errorLevel'   => 'HTTP_METHOD_NOT_ALLOWED',
            'errorMessage' => self::getRequest()->getMethod() . ' ' . self::getRequest()->getPathInfo(),
            'errorFile'    => $backtrace['file'],
            'errorLine'    => $backtrace['line'],
        );
        Error::setError('405', $errorData);
    }

    /**
     * Obtiene un CSS para importarlo por URL o insertarlo en el HTML
     *
     * @param string $path       Ruta relativa al host de estáticos
     * @param string $mode       Modo a usar para incluir el estático en el HTML
     * @param array  $attributes Atributos para aplicar en la etiqueta resultante
     *
     * @return string Código HTML para importarlo o insertarlo
     */
    public static function getCss($path, $mode = 'import', array $attributes = array())
    {
        return self::getStatic('css', $path, $mode, $attributes);
    }

    /**
     * Obtiene un JS para importarlo por URL o insertarlo en el HTML
     *
     * @param string $path       Ruta relativa al host de estáticos
     * @param string $mode       Modo a usar para incluir el estático en el HTML
     * @param array  $attributes Atributos para aplicar en la etiqueta resultante
     *
     * @return string Código HTML para importarlo o insertarlo
     */
    public static function getJs($path, $mode = 'import', array $attributes = array())
    {
        return self::getStatic('js', $path, $mode, $attributes);
    }

    /**
     * Obtiene un IMG para importarlo por URL o insertarlo en el HTML
     *
     * @param string $path       Ruta relativa al host de estáticos
     * @param string $mode       Modo a usar para incluir el estático en el HTML
     * @param array  $attributes Atributos para aplicar en la etiqueta resultante
     *
     * @return string Código HTML para importarlo o insertarlo
     */
    public static function getImg($path, $mode = 'import', array $attributes = array())
    {
        return self::getStatic('img', $path, $mode, $attributes);
    }

    /**
     * Obtiene una URL absoluta con el protocolo, host, path y argumentos especificados
     *
     * @param string $host   Host para construir la URL (css|js|img|www)
     * @param string $path   Ruta relativa al host especificado
     * @param array  $query  Array de parámetros para concatenar a la URL
     * @param string $scheme Protocolo usado para construir la URL
     *
     * @return string URL bien formada
     */
    public static function getUrl($host, $path, array $query = array(), $scheme = 'http')
    {
        $host  = Config::getConfig()->host[$host];
        $query = (empty($query) === false) ? '?' . http_build_query($query) : '';

        return sprintf('%s://%s%s%s', $scheme, $host, $path, $query);
    }

    /**
     * Obtiene un código HTML para importar o insertar un archivo estático
     *
     * @param string $type       Tipo de estático a importar o insertar (css|js|img)
     * @param string $path       Ruta relativa al host de estáticos
     * @param string $mode       Modo a usar para incluir el estático en el HTML
     * @param array  $attributes Atributos para aplicar en la etiqueta resultante
     *
     * @return string Código HTML para importarlo o insertarlo
     */
    private static function getStatic($type, $path, $mode, array $attributes)
    {
        if (in_array($type, array('css', 'js', 'img')) !== false)
        {
            $url = parse_url($path);

            if (isset($url['scheme']) !== false)
            {
                $result = self::importStatic($type, $path, $attributes);
            }
            else
            {
                $file = Config::getConfig()->paths['public'] . $path;

                /**
                 * TODO: Backward compatibility
                 */
                $root     = Config::getConfig()->paths['root'];
                $rootPath = $root . $path;
                if (file_exists($file) === false && file_exists($rootPath) !== false)
                {
                    $source = str_replace($root, '', $rootPath);
                    $target = str_replace($root, '', $file);
                    trigger_error('Estático sin migrar de ' . $source . ' a ' . $target, E_USER_WARNING);
                    $file = $rootPath;
                }

                if (file_exists($file) !== false)
                {
                    switch ($mode)
                    {
                        case 'import':
                            $version = Config::getConfig()->deploy['version'];
                            $src     = self::getUrl($type, $path, array('v' => $version));
                            $result  = self::importStatic($type, $src, $attributes);
                            break;

                        case 'insert':
                            $content = file_get_contents($file);

                            /**
                             * TODO: Algunos navegadores tienen límite de tamaño embebido, hacer sólo por debajo de 32KB
                             */
                            if ($type == 'img')
                            {
                                $mimeType = pathinfo($file, PATHINFO_EXTENSION);
                                $content  = sprintf('data:image/%s;base64,%s', $mimeType, base64_encode($content));
                            }

                            $result = self::insertStatic($type, $content, $attributes);
                            break;

                        default:
                            trigger_error(
                                'Método de estático no soportado (' . $type . ', ' . $path . ', ' . $mode . ')',
                                E_USER_ERROR
                            );
                            break;
                    }
                }
                else
                {
                    trigger_error('Estático no encontrado: ' . $path . ' -> ' . $file, E_USER_ERROR);
                }
            }
        }
        else
        {
            trigger_error('Tipo de estático no soportado (' . $type . ', ' . $path . ', ' . $mode . ')', E_USER_ERROR);
        }

        return $result . PHP_EOL;
    }

    /**
     * Devuelve el código HTML para importar un recurso estático
     *
     * @param string $type       Tipo de recurso estático (css|js|img)
     * @param string $url        URL del recurso estático
     * @param array  $attributes Atributos para aplicar en la etiqueta resultante
     *
     * @return string Código HTML para importar el recurso estático
     */
    private static function importStatic($type, $url, array $attributes)
    {
        switch ($type)
        {
            case 'css':
                $result = sprintf(self::STATIC_CSS_IMPORT_PATTERN, self::parseAttributes($attributes), $url);
                break;

            case 'js':
                $result = sprintf(self::STATIC_JS_IMPORT_PATTERN, self::parseAttributes($attributes), $url);
                break;

            case 'img':
                $result = sprintf(self::STATIC_IMG_IMPORT_PATTERN, self::parseAttributes($attributes), $url);
                break;

            default:
                trigger_error('Importación de recurso estático desconocida: ' . $type, E_USER_ERROR);
                break;
        }

        return $result;
    }

    /**
     * Devuelve el código HTML para insertar un recurso estático
     *
     * @param string $type       Tipo de recurso estático (css|js|img)
     * @param string $content    Contenido del recurso estático
     * @param array  $attributes Atributos para aplicar en la etiqueta resultante
     *
     * @return string Código HTML para insertar el recurso estático
     */
    private static function insertStatic($type, $content, array $attributes)
    {
        switch ($type)
        {
            case 'css':
                $result = sprintf(self::STATIC_CSS_INSERT_PATTERN, self::parseAttributes($attributes), $content);
                break;

            case 'js':
                $result = sprintf(self::STATIC_JS_INSERT_PATTERN, self::parseAttributes($attributes), $content);
                break;

            case 'img':
                $result = sprintf(self::STATIC_IMG_INSERT_PATTERN, self::parseAttributes($attributes), $content);
                break;

            default:
                trigger_error('Inserción de recurso estático desconocida: ' . $type, E_USER_ERROR);
                break;
        }

        return $result;
    }

    /**
     * Devuelve una cadena HTML para representar valor="attributo"
     *
     * @param array $attributes Atributos para aplicar en la etiqueta resultante
     *
     * @return string Código HTML para insertar los atributos
     */
    private static function parseAttributes(array $attributes)
    {
        foreach ($attributes as $attribute => &$data)
        {
            $data = implode(' ', (array) $data);
            $data = $attribute . '="' . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . '"';
        }

        return ($attributes) ? ' ' . implode(' ', $attributes) : '';
    }

    /**
     * Devuelve los argumentos de la ruta coincidente
     *
     * @param string $path Ruta para buscar su coincidencia
     *
     * @return array
     */
    private static function match($path = '')
    {
        return self::$urlMatcher->match($path);
    }

    /**
     * Carga y ejecuta el controlador apropiado
     *
     * @param array $match Datos procesados por el Router
     */
    private static function runController(array $match = array())
    {
        if (isset($match['controller']) === false)
        {
            trigger_error('No reconozco el controlador de esta petición', E_USER_ERROR);
        }
        elseif (strpos($match['controller'], '::') === false)
        {
            trigger_error('No reconozco la acción de la petición (' . $match['controller'] . ')', E_USER_ERROR);
        }

        list($controller, $action) = explode('::', $match['controller']);

        $controllerName = sprintf(self::CONTROLLER_NAME_PATTERN, $controller);
        /** @var Controller $controllerClass */
        $controllerClass = new $controllerName();
        $controllerClass->dispatch($match);
    }

    /**
     * @param array $match Datos procesados por el Router
     *
     * TODO: Eliminar esto, sólo son válidas las rutas con controlador
     *
     * @deprecated since 13-07-2014
     */
    private static function runInclude(array $match = array())
    {
        if (isset($match['template']) === false)
        {
            trigger_error('La template para la vista no se ha establecido', E_USER_ERROR);
        }
        else
        {
            $view = new View();
            $view->setTemplate($match['template']);
            $view->display();
        }
    }

    /**
     * @param array $match Datos procesados por el Router
     *
     * TODO: Eliminar esto, sólo son válidas las rutas con controlador
     *
     * @deprecated since 13-07-2014
     */
    private static function runRedirect(array $match = array())
    {
        self::redirect($match['redirect']['path'], $match['redirect']['type']);
    }

    /**
     * Carga todas las rutas de la aplicación
     *
     * @param array           $routesConfig
     * @param RouteCollection $routeCollection
     */
    private static function loadRoutes(array $routesConfig = array(), RouteCollection $routeCollection)
    {
        foreach ($routesConfig as $routeName => $routeConfig)
        {
            /**
             * Ponemos los valores por defecto
             */
            $path         = '';
            $defaults     = array();
            $requirements = array();
            $options      = array();
            $host         = '';
            $schemes      = array();
            $methods      = array();

            /**
             * Sobreescribimos los valores con la configuración de la ruta
             */
            extract($routeConfig);

            /**
             * Validación de la formación del Controller::method()
             */
            if (isset($defaults['controller']) && strpos($defaults['controller'], '::') === false)
            {
                trigger_error(sprintf('La ruta %s tiene controlador pero no acción', $path), E_USER_ERROR);
            }
            /**
             * Validación de los métodos de la ruta
             */
            elseif (empty($methods))
            {
                trigger_error(sprintf('La ruta %s no tiene ningun método configurado', $path), E_USER_ERROR);
            }

            $route = new Route($path, $defaults, $requirements, $options, $host, $schemes, $methods);

            /**
             * TODO: Ya no es autonumérico el archivo de rutas
             */
            if (is_int($routeName) === false)
            {
                $routeCollection->add($routeName, $route);
            }
            else
            {
                $routeCollection->add(self::getRouteName($path, $defaults, $methods), $route);
            }
        }
    }

    /**
     * Guarda las rutas en una clase PHP para mejorar rendimiento
     *
     * @param RouteCollection $routeCollection
     */
    private static function cacheRoutes(RouteCollection $routeCollection)
    {
        $routesCacheFile   = Config::getConfig()->paths['cache'] . DIRECTORY_SEPARATOR . self::ROUTES_FILE . '.php';
        $routesCacheConfig = array('class' => 'RouterUrlMatcher');
        $phpMatcherDumper  = new PhpMatcherDumper($routeCollection);

        if (file_put_contents($routesCacheFile, $phpMatcherDumper->dump($routesCacheConfig)) === false)
        {
            trigger_error('Unable to write routes cache file', E_USER_ERROR);
        }
    }

    /**
     * Transforma una ruta en un nombre interno
     *
     * @param string $path     Ruta
     * @param array  $defaults Parámetros de la ruta
     * @param array  $methods  Métodos de la ruta
     *
     * @return string
     */
    private static function getRouteName($path = '', array $defaults = array(), array $methods = array())
    {
        if (isset($defaults['controller']))
        {
            list($controller, $action) = explode('::', $defaults['controller']);
            $name = sprintf('controller_%s_%s_%s', $controller, $action, implode('_', $methods));
        }
        elseif (isset($defaults['redirect']))
        {
            $type = $defaults['redirect']['type'];
            $path = $defaults['redirect']['path'];
            $name = sprintf('redirect_%s_%s', $type, $path);
        }
        else
        {
            $name = sprintf('path_%s', $path);
        }

        return Util::sanitizeString($name, '_');
    }
}
