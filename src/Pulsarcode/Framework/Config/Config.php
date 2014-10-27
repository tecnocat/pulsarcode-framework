<?php

namespace Pulsarcode\Framework\Config;

use Pulsarcode\Framework\Error\Error;
use Pulsarcode\Framework\Router\Router;
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
 * @package Pulsarcode\Framework\Config
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
         * TODO: Meter configuración en cache
         */
        if (isset(self::$config) === false)
        {
            /**
             * En la primera petición de configuración activamos los capturadores de errores
             */
            Error::setupErrorHandler();

            /**
             * Cargamos el Yaml parser para cargar los archivos
             */
            $yaml              = new Yaml();
            $parametersContent = array();

            /**
             * TODO: Usar un método mejor para saber dónde está el directorio raíz
             */
            $rootPath   = dirname(dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))));
            $appPath    = $rootPath . DIRECTORY_SEPARATOR . 'app';
            $cachePath  = $appPath . DIRECTORY_SEPARATOR . 'cache';
            $configPath = $appPath . DIRECTORY_SEPARATOR . 'config';
            $logsPath   = $appPath . DIRECTORY_SEPARATOR . 'logs';

            /**
             * TODO: Eliminar esta porquería y usar algo dinámico ASAP
             */
            $bundlePath  = implode(DIRECTORY_SEPARATOR, array($rootPath, 'src', 'Autocasion', 'MainBundle'));
            $publicPath  = implode(DIRECTORY_SEPARATOR, array($bundlePath, 'Resources', 'public'));
            $mailPath    = implode(DIRECTORY_SEPARATOR, array($bundlePath, 'Resources', 'views', 'mail'));
            $webPath     = implode(DIRECTORY_SEPARATOR, array($bundlePath, 'Resources', 'views', 'web'));
            $this->paths = array(
                'root'   => $rootPath,
                'app'    => $appPath,
                'cache'  => $cachePath,
                'config' => $configPath,
                'logs'   => $logsPath,
                'bundle' => $bundlePath,
                'public' => $publicPath,
                'views'  => array(
                    'mail' => $mailPath,
                    'web'  => $webPath,
                ),
            );

            $parametersYamlFile   = $configPath . DIRECTORY_SEPARATOR . self::PARAMETERS_FILE;
            $parametersDistFile   = $configPath . DIRECTORY_SEPARATOR . self::PARAMETERS_FILE . '.dist';
            $parametersCacheFile  = $cachePath . DIRECTORY_SEPARATOR . self::CONFIG_FILE;
            $parametersConfigFile = $configPath . DIRECTORY_SEPARATOR . self::CONFIG_FILE;

            if (file_exists($parametersYamlFile) === false)
            {
                /**
                 * TODO: Mostrar en una template para verlo mejor
                 */
                die(sprintf('<h1>Falta archivo de parámetros %s</h1>', $parametersYamlFile));
            }
            elseif (file_exists($parametersConfigFile) === false)
            {
                /**
                 * TODO: Mostrar en una template para verlo mejor
                 */
                die(sprintf('<h1>Falta archivo de configuracion %s</h1>', $parametersConfigFile));
            }
            elseif ($this->checkParameters($parametersYamlFile, $parametersDistFile, $parametersErrors) === false)
            {
                /**
                 * TODO: Mostrar en una template para verlo mejor
                 */
                $output = '';

                foreach ($parametersErrors as $errorTitle => $errorContent)
                {
                    $output .= sprintf('<h2>%s</h2><pre>%s</pre>', $errorTitle, implode(PHP_EOL, $errorContent));
                }

                die(sprintf('<h1>Error en configuración de parameters.yml:</h1>%s', $output));
            }
            elseif (file_exists($parametersCacheFile) === false)
            {
                $parametersContent = $yaml->parse($parametersYamlFile);
                $configContent     = file_get_contents($parametersConfigFile);

                /**
                 * Este metodo es un poco ortodoxo pero YAML no soporta variables estilo Symfony
                 */
                foreach ($parametersContent as $parameterName => $parameterValue)
                {
                    /**
                     * Corrector de cambios de booleanos a 0 o 1
                     */
                    switch (true)
                    {
                        // bool
                        case ($parameterValue === true):
                            $parameterValue = 'true';
                            break;

                        // bool
                        case ($parameterValue === false):
                            $parameterValue = 'false';
                            break;

                        // null
                        case (isset($parameterValue) === false):
                            $parameterValue = '~';
                            break;

                        // string
                        case (is_numeric($parameterValue) === false):
                            $parameterValue = "'$parameterValue'";
                            break;
                    }

                    $configContent = str_replace("%$parameterName%", $parameterValue, $configContent);
                }

                $parametersContent = $yaml->parse($configContent);
                file_put_contents($parametersCacheFile, $configContent);
            }
            else
            {
                $parametersContent = $yaml->parse($parametersCacheFile);
            }

            foreach ($parametersContent as $parametersName => $parametersValue)
            {
                $this->$parametersName = $parametersValue;
            }

            /**
             * Cargamos todos los YAMLs disponibles bajo su nombre como índice
             *
             * TODO: Meterlo en archivos de cache como la configuración
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

    /**
     * Comprueba y marca errores si los parámetros de parameters.yml.dist no están en parameters.yml
     *
     * @param string $parametersYamlFile Archivo de parámetros del usuario
     * @param string $parametersDistFile Archivo de parámetros del framework
     * @param array  $parametersErrors   Errores detectados en el archivo de parámetros
     *
     * @return bool Devuelve false si se detectan errores
     */
    private function checkParameters($parametersYamlFile, $parametersDistFile, &$parametersErrors = array())
    {
        $yaml        = new Yaml();
        $yamlContent = $yaml->parse($parametersYamlFile);
        $distContent = $yaml->parse($parametersDistFile);
        $diffContent = array_diff_key($distContent, $yamlContent);

        if (empty($diffContent) === false)
        {
            $parametersErrors['Parámetros que faltan:'] = array_keys($diffContent);
        }

        foreach ($distContent as $distKey => $distValue)
        {
            if (isset($yamlContent[$distKey]) !== false && $distValue !== null)
            {
                $distType = gettype($distValue);
                $yamlType = gettype($yamlContent[$distKey]);

                if ($distType !== $yamlType)
                {
                    $errorMessage = sprintf('%s: Debería ser "%s" pero es "%s"', $distKey, $distType, $yamlType);

                    $parametersErrors['Parámetros que no son del mismo tipo:'][] = $errorMessage;
                }
            }
        }

        return (empty($parametersErrors));
    }
}
