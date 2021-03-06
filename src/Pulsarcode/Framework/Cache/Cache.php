<?php

namespace Pulsarcode\Framework\Cache;

use Doctrine\Common\Cache as DoctrineCache;
use Pulsarcode\Framework\Config\Config;
use Pulsarcode\Framework\Core\Core;
use Pulsarcode\Framework\Router\Router;

/**
 * Class Cache Para gestionar la caché
 *
 * La gestión de la caché ofrece una mejora del rendimiento poniendo una capa por encima de memoria caché sobre las
 * peticiones reales a la base de datos, al disco o fuentes de datos externas.
 *
 * Su uso es muy sencillo:
 *
 * <pre>
 * $url         = 'http://www.example.com/data.json'; // Para cachear este JSON en memoria
 * $provider    = 'redis'; // Los proveedores pueden variar según la configuración
 * $cache       = new Cache($provider); // Si no se le pasa proveedor usará el por defecto
 * $cacheKey    = md5($url);
 * $cacheData   = $cache->getCache($cacheKey);
 * $cacheExpire = 300; // Tiempo de duración de la caché en segundos (5 minutos)
 *
 * // Si no existe caché se hace la petición y se guarda en caché
 * if ($cacheData === false)
 * {
 *     $cacheData = file_get_contents($url);
 *     $cache->setCache($cacheKey, $cacheData, $cacheExpire);
 * }
 *
 * return $cacheData; // Devolvemos la caché si existe o la petición real si no existe caché
 * </pre>
 *
 * @see     app/config/config.yml Para comprobar la lista de proveedores disponibles
 *
 * @package Pulsarcode\Framework\Cache
 */
class Cache extends Core
{
    /**
     * Namespace para evitar colisiones de caché
     *
     * @var string DEFAULT_CACHE_NAMESPACE
     */
    const DEFAULT_CACHE_NAMESPACE = 'PulsarcodeFrameworkCache';

    /**
     * Cadena de caché para poder cachear nulos y valores false
     *
     * @var string EMPTY_CACHE_STRING
     */
    const EMPTY_CACHE_STRING = 'Empty Cache';

    /**
     * @var bool Control para los capturadores de cacheos
     */
    private static $dispatched;

    /**
     * Instancias de proveedores en uso
     *
     * @var array $providerInstances
     */
    private static $providerInstances = array();

    /**
     * Objetos cacheados de proveedores
     *
     * @var array $providerObjects
     */
    private static $providerObjects = array();

    /**
     * Listado de proveedores activos
     *
     * @var array $providerStatus
     */
    private static $providerStatus = array();

    /**
     * Configuración de proveedores
     *
     * @var null $providers
     */
    private static $providers = null;

    /**
     * Proveedor de caché en uso elegido
     *
     * @var string $currentProvider
     */
    private $currentProvider;

    /**
     * Constructor
     *
     * Inicializa el proveedor de caché seleccionado, si no se pasa ninguno se inicializará el por defecto
     *
     * @param string $provider Proveedor de caché a usar
     */
    public function __construct($provider = '')
    {
        parent::__construct();

        if (false === isset(self::$providers))
        {
            self::$providers = Config::getConfig()->cache['providers'];
        }

        $this->initProvider($provider);
    }

    /**
     * Función de apagado para mostrar todos los objetos cacheados
     */
    public static function setupCacheObjects()
    {
        if (false === isset(self::$dispatched))
        {
            register_shutdown_function(
                function ()
                {
                    Cache::showObjects();
                }
            );
            self::$dispatched = true;
        }
    }

    /**
     * Pinta los objetos cacheados en una tabla para depurar
     */
    public static function showObjects()
    {
        if (false !== Router::getRequest()->isXmlHttpRequest())
        {
            return;
        }
        elseif (false !== strpos(Router::getRequest()->getPathInfo(), '.json'))
        {
            return;
        }

        if (false !== Config::getConfig()->cache['active'] && false !== Config::getConfig()->cache['show'])
        {
            if (false !== in_array(Config::getConfig()->environment, Config::$debugEnvironments))
            {
                foreach (self::$providerObjects as $providerName => $providerObjects)
                {
                    if (!empty($providerObjects))
                    {
                        include Config::getConfig()->paths['views']['web'] . '/cache-table.html.php';
                    }
                }
            }
        }
    }

    /**
     * Borra la clave proporcionada de la caché
     *
     * @param string $cacheKey Clave de caché a borrar
     */
    public function delete($cacheKey = '')
    {
        if (false !== $this->providerIsActive() && false === empty($cacheKey))
        {
            switch ($this->currentProvider)
            {
                case 'apc':
                case 'memcache':
                case 'memcached':
                case 'redis':
                case 'xcache':
                    /** @var DoctrineCache\CacheProvider $instance */
                    $instance = &self::$providerInstances[$this->currentProvider];
                    $instance->delete($cacheKey);
                    break;

                default:
                    trigger_error('Proveedor de caché desconocido: ' . $this->currentProvider, E_USER_ERROR);
                    break;
            }
        }
    }

    /**
     * Borra todas las claves de la caché
     *
     * @return bool
     */
    public function deleteAll()
    {
        /** @var DoctrineCache\CacheProvider $instance */
        $instance = &self::$providerInstances[$this->currentProvider];

        return $instance->deleteAll();
    }

    /**
     * Obtiene el valor de la caché si no ha caducado su tiempo de expiración
     *
     * @param string $cacheKey Clave de caché a recuperar
     *
     * @return array|bool|mixed|string
     */
    public function getCache($cacheKey = '')
    {
        $result = false;

        if (false === Config::getConfig()->cache['active'])
        {
            trigger_error('La caché está desactivada, proveedor sin uso: ' . $this->currentProvider, E_USER_WARNING);
        }
        elseif (false !== $this->providerIsActive() && false === empty($cacheKey))
        {
            $prefix = '';

            if (false !== isset(self::$providers[$this->currentProvider]['prefix']))
            {
                $prefix = self::$providers[$this->currentProvider]['prefix'];
            }

            /** @var DoctrineCache\CacheProvider $instance */
            $instance = &self::$providerInstances[$this->currentProvider];

            if (false !== $instance->contains($prefix . $cacheKey))
            {
                $result = $instance->fetch($prefix . $cacheKey);
            }
            else
            {
                $result = self::EMPTY_CACHE_STRING;
            }

            /**
             * Transformamos la cadena interna de caché en su valor false original
             * Los nulos también son transformados a false por razones de rendimiento
             */
            if ($result === self::EMPTY_CACHE_STRING)
            {
                $result = false;
            }
        }

        /**
         * Almacenamos los objetos cacheados para pintarlos, esto debería usarse sólo en desarrollo
         * pero al hacerlo en producción nos ayuda a saber cuando estamos cacheando en exceso, si
         * vamos a pintar 10 o 20 resultados no tiene ningun sentido cachear 500000 para una página
         */
        $this->storeObject($cacheKey, $result);

        return $result;
    }

    /**
     * Obtiene las estadísticas del driver instanciado
     *
     * @return array|null
     */
    public function getStats()
    {
        $result = array();

        if (false === Config::getConfig()->cache['active'])
        {
            trigger_error('La caché está desactivada, proveedor sin uso: ' . $this->currentProvider, E_USER_WARNING);
        }
        elseif (false !== $this->providerIsActive())
        {
            /** @var DoctrineCache\CacheProvider $instance */
            $instance = &self::$providerInstances[$this->currentProvider];
            $result   = $instance->getStats();
        }

        return $result;
    }

    /**
     * Establece la caché con el valor y su tiempo de expiración
     *
     * @param string $cacheKey    Clave de caché a establecer
     * @param null   $cacheValue  Valor de caché a establecer
     * @param null   $cacheExpire Tiempo de caché a establecer
     */
    public function setCache($cacheKey = '', $cacheValue = null, $cacheExpire = null)
    {
        if (false === Config::getConfig()->cache['active'])
        {
            trigger_error('La caché está desactivada, proveedor sin uso: ' . $this->currentProvider, E_USER_WARNING);
        }
        elseif (false !== $this->providerIsActive() && false === empty($cacheKey))
        {
            /**
             * Fix para poder cachear valores vacíos y evitar consultarlos siempre
             */
            if (false === $cacheValue || null === $cacheValue)
            {
                $cacheValue = self::EMPTY_CACHE_STRING;
            }

            if (false !== isset($cacheExpire))
            {
                $cacheExpire = intval($cacheExpire);
            }
            else
            {
                $cacheExpire = Config::getConfig()->cache['default_expire'];
            }

            /** @var DoctrineCache\CacheProvider $instance */
            $instance = &self::$providerInstances[$this->currentProvider];
            $instance->save($cacheKey, $cacheValue, $cacheExpire);
        }
    }

    /**
     * Comprueba que los campos necesarios están presentes en la configuración
     *
     * @param array $fields Campos para validar si existen
     */
    private function checkRequiredProviderFields(array $fields)
    {
        foreach ($fields as $field)
        {
            if (false === isset(self::$providers[$this->currentProvider][$field]))
            {
                trigger_error(
                    sprintf('Falta configuración de "%s" en "%s"', $field, $this->currentProvider),
                    E_USER_ERROR
                );
            }
        }
    }

    /**
     * Comprueba el estado del proveedor y lo inicializa
     *
     * @param string $provider Proveedor de caché a inicializar
     */
    private function initProvider($provider = '')
    {
        if (Config::getConfig()->cache['active'])
        {
            if (false !== empty($provider))
            {
                $provider = Config::getConfig()->cache['default_provider'];
            }

            /**
             * Solo funcionamos con proveedores soportados, si no petate
             */
            if (false !== in_array($provider, array_keys(self::$providers)))
            {
                $host                  = null;
                $port                  = null;
                $this->currentProvider = $provider;

                /**
                 * Sólo se marca el estado de activo/inactivo la primera vez
                 */
                if (false === isset(self::$providerStatus[$this->currentProvider]))
                {
                    $this->checkRequiredProviderFields(array('active'));
                    $this->setProviderStatus(self::$providers[$this->currentProvider]['active']);
                }

                /**
                 * Sólo inicializamos la instancia la primera vez, el resto usamos la instancia estática
                 */
                if ($this->providerIsActive() && false === isset(self::$providerInstances[$this->currentProvider]))
                {
                    /**
                     * Cargamos toda la configuración disponible en el proveedor
                     */
                    foreach (self::$providers[$this->currentProvider] as $fieldName => $fieldValue)
                    {
                        $$fieldName = $fieldValue;
                    }

                    /**
                     * TODO: Transformar este switch en adaptadores independientes
                     */
                    switch ($this->currentProvider)
                    {
                        case 'apc':
                            $instance = new DoctrineCache\ApcCache();
                            $instance->setNamespace(self::DEFAULT_CACHE_NAMESPACE);
                            self::$providerInstances[$this->currentProvider] = &$instance;
                            break;

                        case 'memcache':
                            $this->checkRequiredProviderFields(array('host', 'port'));
                            $memcache = new \Memcache();

                            if (false !== $memcache->connect($host, $port))
                            {
                                $instance = new DoctrineCache\MemcacheCache();
                                $instance->setMemcache($memcache);
                                $instance->setNamespace(self::DEFAULT_CACHE_NAMESPACE);
                                self::$providerInstances[$this->currentProvider] = &$instance;
                            }
                            else
                            {
                                $this->setProviderStatus(false);
                                trigger_error('Error al conectar con ' . $this->currentProvider, E_USER_ERROR);
                            }
                            break;

                        case 'memcached':
                            $this->checkRequiredProviderFields(array('host', 'port'));
                            $memcached = new \Memcached();

                            if (false !== $memcached->addServer($host, $port))
                            {
                                $instance = new DoctrineCache\MemcachedCache();
                                $instance->setMemcached($memcached);
                                $instance->setNamespace(self::DEFAULT_CACHE_NAMESPACE);
                                self::$providerInstances[$this->currentProvider] = &$instance;
                            }
                            else
                            {
                                $this->setProviderStatus(false);
                                trigger_error('Error al conectar con ' . $this->currentProvider, E_USER_ERROR);
                            }
                            break;

                        case 'redis':
                            $this->checkRequiredProviderFields(array('host', 'port'));
                            $redis = new \Redis();

                            if (false !== $redis->connect($host, $port))
                            {
                                $instance = new DoctrineCache\RedisCache();
                                $instance->setRedis($redis);
                                $instance->setNamespace(self::DEFAULT_CACHE_NAMESPACE);
                                self::$providerInstances[$this->currentProvider] = &$instance;
                            }
                            else
                            {
                                $this->setProviderStatus(false);
                                trigger_error('Error al conectar con ' . $this->currentProvider, E_USER_ERROR);
                            }
                            break;

                        case 'xcache':
                            $instance = new DoctrineCache\XcacheCache();
                            $instance->setNamespace(self::DEFAULT_CACHE_NAMESPACE);
                            self::$providerInstances[$this->currentProvider] = &$instance;
                            break;

                        default:
                            trigger_error('Proveedor de caché desconocido: ' . $this->currentProvider, E_USER_ERROR);
                            break;
                    }
                }
            }
            else
            {
                trigger_error('Proveedor de caché no soportado: ' . $provider, E_USER_ERROR);
            }
        }
    }

    /**
     * Comprueba si el estado del proveedor de caché es activo
     *
     * @return bool true si está activo, false si no
     */
    private function providerIsActive()
    {
        return (bool) self::$providerStatus[$this->currentProvider];
    }

    /**
     * Establece el estado del proveedor como activo o inactivo
     *
     * @param bool $status Estado del proveedor, por defecto false
     */
    private function setProviderStatus($status = false)
    {
        self::$providerStatus[$this->currentProvider] = (bool) $status;
    }

    /**
     * Almacena los objetos cacheados para pintarlos después y depurar
     *
     * @param string $cacheKey Clave de caché para almacenar
     * @param null   $object   Valor de caché para almacenar
     */
    private function storeObject($cacheKey = '', $object = null)
    {
        self::$providerObjects[$this->currentProvider][$cacheKey] = array(
            'from' => ($object === false) ? 'REQUEST' : 'CACHE',
            'data' => ($object === false) ? self::EMPTY_CACHE_STRING : $object,
        );
    }
}
