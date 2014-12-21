<?php

namespace Pulsarcode\Framework\Config;

use Pulsarcode\Framework\Core\Core;
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
class Config extends Core
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
     * Entornos permitidos para mostrar información de debug
     *
     * @var array $debugEnvironments
     */
    public static $debugEnvironments = array('loc', 'dev');

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
        parent::__construct();

        /**
         * Cargamos la configuración sólo la primera vez en estático
         * Así evitamos penalizar el rendimiento por lectura a disco
         *
         * TODO: Meter configuración en cache
         */
        if (isset(self::$config) === false)
        {
            /**
             * Cargamos el Yaml parser para cargar los archivos
             */
            $yaml = new Yaml();

            /**
             * TODO: Usar un método mejor para saber dónde está el directorio raíz
             * TODO: Eliminar compatibilidad con enlaces simbólicos en capistrano
             */
            $rootPath   = dirname(dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))));
            $rootPath   = str_replace('/shared', '/current', $rootPath);
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
            $parametersDistFile   = __DIR__ . DIRECTORY_SEPARATOR . self::PARAMETERS_FILE . '.dist';
            $parametersCacheFile  = $cachePath . DIRECTORY_SEPARATOR . self::CONFIG_FILE;
            $parametersConfigFile = __DIR__ . DIRECTORY_SEPARATOR . self::CONFIG_FILE;

            if (file_exists($parametersYamlFile) === false)
            {
                /**
                 * TODO: Mostrar en una template para verlo mejor
                 */
                if (php_sapi_name() == 'cli')
                {
                    $pattern = PHP_EOL . 'Missing parameters file %s' . PHP_EOL;
                }
                else
                {
                    $pattern = '<h1>Missing parameters file %s</h1>';
                }

                die(sprintf($pattern, $parametersYamlFile));
            }
            elseif (file_exists($parametersDistFile) === false)
            {
                /**
                 * TODO: Mostrar en una template para verlo mejor
                 */
                if (php_sapi_name() == 'cli')
                {
                    $pattern = PHP_EOL . 'Missing parameters dist file %s' . PHP_EOL;
                }
                else
                {
                    $pattern = '<h1>Missing parameters dist file %s</h1>';
                }

                die(sprintf($pattern, $parametersDistFile));
            }
            /**
             * TODO: Meter el config.yml en el Framework y hacerlo extendible
             */
            elseif (file_exists($parametersConfigFile) === false)
            {
                /**
                 * TODO: Mostrar en una template para verlo mejor
                 */
                if (php_sapi_name() == 'cli')
                {
                    $pattern = PHP_EOL . 'Missing config file %s' . PHP_EOL;
                }
                else
                {
                    $pattern = '<h1>Missing config file %s</h1>';
                }

                die(sprintf($pattern, $parametersConfigFile));
            }
            elseif ($this->checkParameters($parametersYamlFile, $parametersDistFile, $parametersErrors) === false)
            {
                /**
                 * TODO: Mostrar en una template para verlo mejor
                 */
                $output = '';

                if (php_sapi_name() == 'cli')
                {
                    $pattern = PHP_EOL . '%s' . PHP_EOL . PHP_EOL . '%s' . PHP_EOL;
                }
                else
                {
                    $pattern = '<h2>%s</h2><pre>%s</pre>';
                }

                foreach ($parametersErrors as $errorTitle => $errorContent)
                {
                    $output .= sprintf($pattern, $errorTitle, implode(PHP_EOL, $errorContent));
                }

                if (php_sapi_name() == 'cli')
                {
                    die(sprintf(PHP_EOL . 'Parameters error:' . PHP_EOL . '%s', $output));
                }
                else
                {
                    die(sprintf('<h1>Parameters error:</h1>%s', $output));
                }
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
                    $configContent = str_replace("%$parameterName%", $this->phpToYaml($parameterValue), $configContent);
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
        $testContent = array(
            'Missing parameters:' => array_diff_key($distContent, $yamlContent),
            'Unknown parameters:' => array_diff_key($yamlContent, $distContent),
        );

        foreach ($testContent as $errorTitle => $diffContent)
        {
            if (empty($diffContent) === false)
            {
                $parametersErrors[$errorTitle] = array();

                foreach ($diffContent as $fieldKey => $fieldValue)
                {
                    $parametersErrors[$errorTitle][] = sprintf(
                        '%s (Value: %s)',
                        $fieldKey,
                        $this->phpToYaml($fieldValue)
                    );
                }
            }
        }

        foreach ($distContent as $distKey => $distValue)
        {
            if (isset($yamlContent[$distKey]) !== false && $distValue !== null)
            {
                $distType = gettype($distValue);
                $yamlType = gettype($yamlContent[$distKey]);

                if ($distType !== $yamlType)
                {
                    $errorMessage = sprintf('%s: must be "%s" but is "%s"', $distKey, $distType, $yamlType);

                    $parametersErrors['Missmatch parameters type:'][] = $errorMessage;
                }
            }
        }

        return (empty($parametersErrors));
    }

    /**
     * Procesa un valor PHP y lo castea a tipo YAML
     *
     * @param mixed $parameterValue Valor casteado de tipo PHP
     *
     * @return string  Valor casteado de tipo YAML
     */
    private function phpToYaml($parameterValue)
    {
        switch (true)
        {
            // bool
            case (true === $parameterValue):
                $parameterValue = 'true';
                break;

            // bool
            case (false === $parameterValue):
                $parameterValue = 'false';
                break;

            // null
            case (false === isset($parameterValue)):
                $parameterValue = '~';
                break;

            // string
            case (false === is_numeric($parameterValue)):
                $parameterValue = "'$parameterValue'";
                break;
        }

        return $parameterValue;
    }
}
