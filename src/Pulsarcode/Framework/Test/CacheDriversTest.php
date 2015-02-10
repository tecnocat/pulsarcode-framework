<?php

namespace Pulsarcode\Framework\Test;

use Pulsarcode\Framework\Cache\Cache;
use Pulsarcode\Framework\Core\Core;
use Pulsarcode\Framework\Error\Error;

class CacheDriversTest extends Core
{
    public function runTest()
    {
        $cacheMemcache  = new Cache('memcache');
        $cacheMemcached = new Cache('memcached');
        $cacheRedis     = new Cache('redis');
        $cacheXcache    = new Cache('xcache');
        $testLoops      = 5000;
        $testDrivers    = array(
            'Memcache'  => $cacheMemcache,
            'Memcached' => $cacheMemcached,
            'Redis'     => $cacheRedis,
            'Xcache'    => $cacheXcache,
        );
        $testStart      = microtime(true);
        $debugTrace     = 'Testing ' . $testLoops . ' iterations over cache GET/SET' . PHP_EOL . PHP_EOL;

        foreach ($testDrivers as $driverName => &$driverInstance)
        {
            $driverStart = microtime(true);
            $debugTrace .= sprintf('%.3f %s driver start%s', $driverStart, $driverName, PHP_EOL);

            for ($i = 1; $i <= $testLoops; $i++)
            {
                $driverInstance->setCache($driverName, microtime(true));
                $driverInstance->getCache($driverName);
            }

            $driverEnd = microtime(true);
            $debugTrace .= sprintf('%.3f %s driver end%s', $driverEnd, $driverName, PHP_EOL);
            $debugTrace .= sprintf(
                '%sTook %.3fms with driver %s%s%s',
                PHP_EOL,
                $driverEnd - $driverStart,
                $driverName,
                PHP_EOL,
                PHP_EOL
            );
        }

        $testEnd = microtime(true);
        $debugTrace .= sprintf('%sTest took %.3fms with all drivers', PHP_EOL, $testEnd - $testStart);
        Error::mail('Resultado de los test de drivers de caché', sprintf('<pre>%s</pre>', $debugTrace));

        return $debugTrace;
    }
}
