<?php

namespace Pulsarcode\Framework\Core;

use Pulsarcode\Framework\Cache\Cache;
use Pulsarcode\Framework\Database\Database;
use Pulsarcode\Framework\Database\MSSQLWrapper;
use Pulsarcode\Framework\Error\Error;
use Pulsarcode\Framework\Router\Router;

/**
 * Class Core Para gestionar el Framework
 *
 * @package Pulsarcode\Framework\Core
 */
class Core
{
    /**
     * @var bool Control para los capturadores de peticiones
     */
    private static $dispatched;

    /**
     * @var float Instancia en el tiempo en el que se terminó de conectar con la base de datos
     */
    private static $finishConnection = 0.0;

    /**
     * @var float Instancia en el tiempo en el que se conectó con la base de datos
     */
    private static $startConnection = 0.0;

    /**
     * @var float Instancia en el tiempo en el que se inició la petición Web
     */
    private static $startRequest = 0.0;

    /**
     * @var float Instancia en el tiempo en el que se inició la ejecución en consola
     */
    private static $startScript = 0.0;

    /**
     * Constructor
     */
    public function __construct()
    {
        /**
         * Capturador de petates
         */
        Error::setupErrorHandler();

        /**
         * Capturador de cacheos
         */
        Cache::setupCacheObjects();

        /**
         * Capturador de queries
         */
        Database::setupQueryLogger();

        /**
         * Si la ejecución es en consola iniciamos la misma
         */
        if ('cli' === php_sapi_name() && 0.0 === self::$startScript)
        {
            Router::execute();
        }
    }

    /**
     * Muestra información sobre el tiempo transcurrido durante la petición Web
     */
    public static function finishRequest()
    {
        $microtime      = microtime(true);
        $requestTime    = $microtime - self::$startRequest;
        $connectionTime = (self::$startConnection) ? self::$finishConnection - self::$startConnection : 0.0;
        $queryTime      = (self::$startConnection) ? MSSQLWrapper::getQueryTimeTotal() : 0.0;
        $databaseTime   = (self::$startConnection) ? $connectionTime + $queryTime : 0.0;
        $spaghettiTime  = $requestTime - $databaseTime;

        $errorMessage = sprintf(
            'Duración de la petición: %.3fms (Web: %.3fms | Database: %.3fms [Conexión: %.3fms | Queries: %.3fms])',
            $requestTime,
            $spaghettiTime,
            $databaseTime,
            $connectionTime,
            $queryTime
        );
        $errorData    = array(
            'errorLevel'   => 'PERFORMANCE_INFO',
            'errorMessage' => $errorMessage,
            'errorFile'    => __FILE__,
            'errorLine'    => __LINE__,
        );
        Error::setError('PHP', $errorData);

        /**
         * TODO: Hasta hacer una tabla o similar para mostrar la información de arriba usar el parseo de errores
         */
        Error::parseErrors();
    }

    /**
     * Muestra información sobre el tiempo transcurrido durante la ejecución en consola
     */
    public static function finishScript()
    {
        $microtime      = microtime(true);
        $scriptTime     = $microtime - self::$startScript;
        $connectionTime = (self::$startConnection) ? self::$finishConnection - self::$startConnection : 0.0;
        $queryTime      = (self::$startConnection) ? MSSQLWrapper::getQueryTimeTotal() : 0.0;
        $databaseTime   = (self::$startConnection) ? $connectionTime + $queryTime : 0.0;
        $spaghettiTime  = $scriptTime - $databaseTime;

        $errorMessage = sprintf(
            'Duración de la ejecución: %.3fms (Cli: %.3fms | Database: %.3fms [Conexión: %.3fms | Queries: %.3fms])',
            $scriptTime,
            $spaghettiTime,
            $databaseTime,
            $connectionTime,
            $queryTime
        );
        $errorData    = array(
            'errorLevel'   => 'PERFORMANCE_INFO',
            'errorMessage' => $errorMessage,
            'errorFile'    => __FILE__,
            'errorLine'    => __LINE__,
        );
        Error::setError('PHP', $errorData);

        /**
         * TODO: Hasta hacer una tabla o similar para mostrar la información de arriba usar el parseo de errores
         */
        Error::parseErrors();
    }

    /**
     * Setea el momento del tiempo en el que se terminó de conectar con la base de datos
     */
    protected static function finishConnection()
    {
        self::$finishConnection = (self::$finishConnection) ?: microtime(true);
    }

    /**
     * Run command and parse output from STOUT + STERR
     *
     * @param string $command Command to be run
     * @param array  $output  Command output
     * @param bool   $silent  Command run silently?
     *
     * @return bool true if exit code of program was 0, false otherwise
     */
    protected static function run($command, array &$output = null, $silent = false)
    {
        $replace   = array('$' => '\$');
        $command   = str_replace(array_keys($replace), array_values($replace), $command);
        $errorFile = tempnam(sys_get_temp_dir(), uniqid('CommandErrors', true));
        echo 'STRUN: ' . $command . PHP_EOL;
        exec($command . ' 2> ' . $errorFile, $output, $exitCode);
        $errors = file($errorFile, FILE_IGNORE_NEW_LINES);
        unlink($errorFile);

        if ($silent === false)
        {
            if (empty($output) === false)
            {
                echo 'STOUT: ' . implode(PHP_EOL . 'STOUT: ', $output) . PHP_EOL;
            }

            if (empty($errors) === false)
            {
                echo 'STERR: ' . implode(PHP_EOL . 'STERR: ', $errors) . PHP_EOL;
            }
        }

        return ($exitCode === 0);
    }

    /**
     * Setea el momento del tiempo en el que se conectó con la base de datos
     */
    protected static function startConnection()
    {
        self::$startConnection = (self::$startConnection) ?: microtime(true);
    }

    /**
     * Setea el momento del tiempo en el que se inicia la petición Web
     */
    protected static function startRequest()
    {
        self::$startRequest = (self::$startRequest) ?: microtime(true);

        if (false === isset(self::$dispatched))
        {
            register_shutdown_function(
                function ()
                {
                    Core::finishRequest();
                }
            );
            self::$dispatched = true;
        }
    }

    /**
     * Setea el momento del tiempo en el que se inicia la ejecución en consola
     */
    protected static function startScript()
    {
        self::$startScript = (self::$startScript) ?: microtime(true);

        if (false === isset(self::$dispatched))
        {
            register_shutdown_function(
                function ()
                {
                    Core::finishScript();
                }
            );
            self::$dispatched = true;
        }
    }
}
