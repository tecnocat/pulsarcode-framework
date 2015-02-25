<?php

namespace Pulsarcode\Framework\Core;

use Pulsarcode\Framework\Cache\Cache;
use Pulsarcode\Framework\Config\Config;
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
     * Devuelve una barra de información HTML sólo a desarrolladores
     *
     * @return string
     */
    public static function toolbar()
    {
        $toolbar  = Router::getRequest()->cookies->get('DeveloperToolbar');
        $remoteIp = Router::getRequest()->server->get('REMOTE_ADDR');
        $serverIp = Router::getRequest()->server->get('SERVER_ADDR');
        $develIps = explode('|', Config::getConfig()->debug['ips']);
        $result   = '';

        if (false === Config::getConfig()->cache['active'])
        {
            trigger_error('La caché está deshabilitada para poder mostrar el toolbar', E_USER_NOTICE);
        }
        /**
         * TODO: Hacer este check en un método para poder ser llamado por otros y guardar datos si es Developer
         */
        elseif (false !== in_array($remoteIp, $develIps) && false !== isset($toolbar) && 'enabled' === $toolbar)
        {
            $cache          = new Cache();
            $cacheMemcache  = new Cache('memcache');
            $cacheMemcached = new Cache('memcached');
            $cacheRedis     = new Cache('redis');
            $cacheXcache    = new Cache('xcache');
            $repositoryTag  = $cache->getCache('CURRENT_REPOSITORY_TAG');
            $submoduleTag   = $cache->getCache('CURRENT_SUBMODULE_TAG');
            $memcacheStats  = $cacheMemcache->getStats();
            $memcachedStats = $cacheMemcached->getStats();
            $redisStats     = $cacheRedis->getStats();
            $xcacheStats    = $cacheXcache->getStats();

            function getDriverUptime($uptime)
            {
                $uptime  = ($uptime) ?: 1;
                $seconds = $uptime % 60;
                $minutes = floor(($uptime % 3600) / 60);
                $hours   = floor(($uptime % 86400) / 3600);
                $days    = floor(($uptime % 2592000) / 86400);

                if (1 == $days)
                {
                    $result = sprintf('%d day %02d:%02d:%02d', $days, $hours, $minutes, $seconds);
                }
                elseif (1 < $days)
                {
                    $result = sprintf('%d days %02d:%02d:%02d', $days, $hours, $minutes, $seconds);
                }
                else
                {
                    $result = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
                }

                return $result;
            }

            function getDriverAccuracy($hits, $misses)
            {
                if (0 == $hits && 0 == $misses)
                {
                    return 'UNKNOWN (hits 0/miss 0)';
                }
                else
                {
                    return sprintf('%.5f%% (hits %d/miss %d)', 100 - (($misses / $hits) * 100), $hits, $misses);
                }
            }

            function getDriverUsage($used, $total)
            {
                $total = ($total) ?: 0;

                if (0 == $total)
                {
                    $used = ($used / 1048576);

                    return sprintf('UNKNOWN (used %.3fmb/total UKNOWN)', $used);
                }
                else
                {
                    $used  = ($used / 1048576);
                    $total = ($total / 1048576);

                    return sprintf('%.5f%% (used %.3fmb/total %.3fmb)', (($used / $total) * 100), $used, $total);
                }
            }

            $memcacheBanner  = sprintf(
                'Uptime %s, Accuracy: %s, Usage: %s',
                getDriverUptime($memcacheStats['uptime']),
                getDriverAccuracy($memcacheStats['hits'], $memcacheStats['misses']),
                getDriverUsage($memcacheStats['memory_usage'], $memcacheStats['memory_available'])
            );
            $memcachedBanner = sprintf(
                'Uptime %s, Accuracy: %s, Usage: %s',
                getDriverUptime($memcachedStats['uptime']),
                getDriverAccuracy($memcachedStats['hits'], $memcachedStats['misses']),
                getDriverUsage($memcachedStats['memory_usage'], $memcachedStats['memory_available'])
            );
            $redisBanner     = sprintf(
                'Uptime %s, Accuracy: %s, Usage: %s',
                getDriverUptime($redisStats['uptime']),
                getDriverAccuracy($redisStats['hits'], $redisStats['misses']),
                getDriverUsage($redisStats['memory_usage'], $redisStats['memory_available'])
            );
            $xcacheBanner    = sprintf(
                'Uptime %s, Accuracy: %s, Usage: %s',
                getDriverUptime($xcacheStats['uptime']),
                getDriverAccuracy($xcacheStats['hits'], $xcacheStats['misses']),
                getDriverUsage($xcacheStats['memory_usage'], $xcacheStats['memory_available'])
            );

            if (false === $repositoryTag)
            {
                $repositoryTag = trim(
                    file_get_contents(
                        Config::getConfig()->paths['root'] . DIRECTORY_SEPARATOR . 'CURRENT_REPOSITORY_TAG'
                    )
                );
                $cache->setCache('CURRENT_REPOSITORY_TAG', $repositoryTag, 300);
            }

            if (false === $submoduleTag)
            {
                $submoduleTag = trim(
                    file_get_contents(
                        Config::getConfig()->paths['root'] . DIRECTORY_SEPARATOR . 'CURRENT_SUBMODULE_TAG'
                    )
                );
                $cache->setCache('CURRENT_SUBMODULE_TAG', $submoduleTag, 300);
            }

            $template = '
                <div class="DeveloperToolbar" style="margin:0;padding:10px;background-color:#101010;color:#00f000;">
                    <h1>[Developer Toolbar]</h1>
                    <br />
                    <h2>Your IP: :remoteIp, Server IP: :serverIp, Repository Tag: :repositoryTag, Submodule Tag: :submoduleTag</h2>
                    <br />
                    <h2>Cache System Status</h2>
                    <pre style="font-family:DejaVu Sans Mono,Verdana,Tahoma;color:#f0f000;border:solid 1px red;margin:10px;padding:10px;word-wrap:break-word;">
Memcache:  :memcache_banner
Memcached: :memcached_banner
Redis:     :redis_banner
Xcache:    :xcache_banner</pre>
                </div>
            ';
            $tokens   = array(
                ':remoteIp'         => $remoteIp,
                ':serverIp'         => $serverIp,
                ':repositoryTag'    => $repositoryTag,
                ':submoduleTag'     => $submoduleTag,
                ':memcache_banner'  => $memcacheBanner,
                ':memcached_banner' => $memcachedBanner,
                ':redis_banner'     => $redisBanner,
                ':xcache_banner'    => $xcacheBanner,
            );

            $result = strtr($template, $tokens);
        }

        return $result;
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
