<?php

namespace PetateFramework\Config;

use PetateFramework\Router\Router;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Config Para gestionar la configuración
 *
 * Toda la configuración de la aplicación se almacena en el objeto Config, la estructura de la configuración es muy
 * similar a Symfony, existe un archivo config.yml que no debe tocarse salvo para agregar o quitar opciones de
 * configuración, y existe otro archivo parameters.yml en donde se almacenan los valores de los parámetros que serán
 * interpretados por config.yml, el parseo se realiza de forma automática
 *
 * Para acceder a la configuración se llama de forma estática:
 *
 * <pre>
 * $environment = Config::getConfig()->environment;
 * $sendErrors  = Config::getConfig()->error_reporting['send'];
 * $viewErrors  = Config::getConfig()->error_reporting['view'];
 * </pre>
 *
 * Lo que se obtiene es un array de datos representados en config.yml, tambien existe la posibilidad de cargar archivos
 * YAML independientes bajo su clave de array tomando como nombre el del propio archivo:
 *
 * En el caso del archivo de configuración app/config/metas.yml:
 *
 * <pre>
 * # app/config/metas.yml
 * home:
 *     title: 'Home page'
 *     message: 'Welcome!'
 * about:
 *     title: 'About us'
 *     message: 'View our team!'
 * </pre>
 *
 * <pre>
 * $metas = Config::getConfig()->metas;
 * </pre>
 *
 * Se obtiene el contenido entero del propio archivo app/config/metas.yml parseado como un array
 *
 * <pre>
 * $metasHome = Config::getConfig()->metas['home'];
 * </pre>
 *
 * Se obtiene el contenido sólo de la sección home del archivo app/config/metas.yml parseado como un array
 *
 * @see     app/config/config.yml
 * @see     app/config/parameters.yml
 *
 * @package PetateFramework\Config
 */
class Config
{
    /**
     * Nombre del archivo de configuración
     *
     * @var string CONFIG_FILE
     */
    const CONFIG_FILE = 'config.yml';

    /**
     * Nombre del archivo de parámetros
     *
     * @var string PARAMETERS_FILE
     */
    const PARAMETERS_FILE = 'parameters.yml';

    /**
     * Toda la configuración de la aplicación y archivos adicionales
     *
     * @var null $config
     */
    private static $config = null;

    /**
     * Instancia estática de sí misma
     *
     * @var Config $instance
     */
    private static $instance;

    /**
     * Constructor
     */
    public function __construct()
    {
        /**
         * Cargamos la configuración sólo la primera vez en estático
         * Así evitamos penalizar el rendimiento por lectura a disco
         *
         * TODO: Generar un archivo procesado para no reemplazar variables
         * TODO: Meter configuración en cache
         */
        if (isset(self::$config) === false)
        {
            /**
             * Generamos la configuración global de paths para su referencia
             */
            $rootPath    = Router::getRequest()->server->get('DOCUMENT_ROOT');
            $appPath     = $rootPath . DIRECTORY_SEPARATOR . 'app';
            $cachePath   = $appPath . DIRECTORY_SEPARATOR . 'cache';
            $configPath  = $appPath . DIRECTORY_SEPARATOR . 'config';
            $logsPath    = $appPath . DIRECTORY_SEPARATOR . 'logs';
            $publicPath  = implode(DIRECTORY_SEPARATOR, array($appPath, 'Resources', 'PetateFramework', 'public'));
            $mailsPath   = implode(DIRECTORY_SEPARATOR, array($appPath, 'Resources', 'PetateFramework', 'Mails'));
            $viewsPath   = implode(DIRECTORY_SEPARATOR, array($appPath, 'Resources', 'PetateFramework', 'Views'));
            $tmpPath     = $rootPath . DIRECTORY_SEPARATOR . 'tmp';
            $this->paths = array(
                'root'   => $rootPath,
                'app'    => $appPath,
                'cache'  => $cachePath,
                'config' => $configPath,
                'logs'   => $logsPath,
                'public' => $publicPath,
                'mails'  => $mailsPath,
                'views'  => $viewsPath,
                'tmp'    => $tmpPath,
            );

            $parametersFile = $configPath . DIRECTORY_SEPARATOR . self::PARAMETERS_FILE;
            $configFile     = $configPath . DIRECTORY_SEPARATOR . self::CONFIG_FILE;

            if (file_exists($parametersFile) === false)
            {
                trigger_error('Falta archivo de parametros ' . $parametersFile, E_USER_ERROR);
            }
            elseif (file_exists($configFile) === false)
            {
                trigger_error('Falta archivo de configuracion ' . $configFile, E_USER_ERROR);
            }
            else
            {
                $yaml             = new Yaml();
                $parametersParsed = $yaml->parse($parametersFile);
                $configContent    = file_get_contents($configFile);

                /**
                 * Este metodo es un poco ortodoxo pero YAML no soporta variables estilo Symfony
                 */
                foreach ($parametersParsed as $parameterName => $parameterValue)
                {
                    /**
                     * Corrector de cambios de booleanos a 0 o 1
                     */
                    $parameterValue = ($parameterValue === true) ? 'true' : $parameterValue;
                    $parameterValue = ($parameterValue === false) ? 'false' : $parameterValue;
                    $configContent  = str_replace("%$parameterName%", $parameterValue, $configContent);
                }

                $configParsed = $yaml->parse($configContent);

                foreach ($configParsed as $configName => $configValue)
                {
                    $this->$configName = $configValue;
                }

                /**
                 * Cargamos todos los YAMLs disponibles bajo su nombre como índice
                 */
                foreach (glob($this->paths['config'] . '/*.yml') as $yamlFile)
                {
                    $pathinfo = pathinfo($yamlFile);
                    $basename = $pathinfo['basename'];
                    $yamlName = $pathinfo['filename'];

                    if (!in_array($basename, array(self::CONFIG_FILE, self::PARAMETERS_FILE, Router::ROUTES_FILE)))
                    {
                        $this->$yamlName = $yaml->parse($yamlFile);
                    }
                }
            }
        }
    }

    /**
     * Devuelve la instancia de sí misma en estático
     *
     * @return Config
     */
    public static function getConfig()
    {
        if (isset(self::$instance) === false)
        {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Establece la configuración dada
     *
     * @param string $configName  Nombre de la configuración
     * @param mixed  $configValue Valor de la configuración
     */
    public function __set($configName, $configValue)
    {
        self::$config[$configName] = $configValue;
    }

    /**
     * Devuelve la configuración si existe o null
     *
     * @param string $configName Nombre de la configuración
     *
     * @return null
     */
    public function __get($configName)
    {
        return (isset(self::$config[$configName])) ? self::$config[$configName] : null;
    }
}
