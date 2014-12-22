<?php

namespace Pulsarcode\Framework\Core;

use Pulsarcode\Framework\Cache\Cache;
use Pulsarcode\Framework\Database\Database;
use Pulsarcode\Framework\Error\Error;

/**
 * Class Core Para gestionar el Framework
 *
 * @package Pulsarcode\Framework\Core
 */
class Core
{
    /**
     * @var float Instancia en el tiempo en elq ue se conectó con la base de datos
     */
    private static $startConnection;

    /**
     * @var float Instancia en el tiempo en el que se inició la petición
     */
    private static $startRequest;

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
    }

    /**
     * Guarda en el log el tiempo transcurrido durante la petición
     */
    protected static function finishRequest()
    {
        $microtime      = microtime(true);
        $requestTime    = $microtime - self::$startRequest;
        $connectionTime = (self::$startConnection) ? $microtime - self::$startConnection : 0;

        trigger_error(
            sprintf(
                'Duración de la petición: %.3fms (Web: %.3fms | Database: %.3fms)',
                $requestTime,
                $requestTime - $connectionTime,
                $connectionTime
            ),
            E_USER_NOTICE
        );
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
    protected static function run($command, array &$output = null, $silent = true)
    {
        $replace   = array('$' => '\$');
        $command   = str_replace(array_keys($replace), array_values($replace), $command);
        $errorFile = tempnam(sys_get_temp_dir(), uniqid('CommandErrors', true));
        exec($command . ' 2> ' . $errorFile, $output, $exitCode);
        $errors = file($errorFile, FILE_IGNORE_NEW_LINES);
        unlink($errorFile);

        if ($silent === false)
        {
            if (empty($output) === false)
            {
                print_r($output);
            }

            if (empty($errors) === false)
            {
                print_r($errors);
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
     * Setea el momento del tiempo en el que se inicia la petición
     */
    protected static function startRequest()
    {
        self::$startRequest = (self::$startRequest) ?: microtime(true);
    }
}
