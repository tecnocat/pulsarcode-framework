<?php

namespace Pulsarcode\Framework\Database;

use Doctrine\DBAL\DriverManager;
use Pulsarcode\Framework\Config\Config;

/**
 * Class Database Para gestionar la base de datos
 *
 * @package Pulsarcode\Framework\Controller
 */
class Database
{
    /**
     * @var array Conexiones de bases de datos
     */
    private static $connections = array();

    /**
     * @var string Query para ser ejecutada
     */
    private $query = '';

    /**
     * @var array Argumentos de la query a ser ejecutada
     */
    private $queryArguments = array();

    /**
     * Devuelve una instancia de la conexión a la base de datos usando MSSQLWrapper
     *
     * @param string $connectionName Nombre de la configuración de conexión
     *
     * @return MSSQLWrapper
     */
    public function getInstance($connectionName = 'mssql')
    {
        return $this->getOldWrappedConnection(self::getConnectionParams($connectionName));
    }

    /**
     * Devuelve una instancia de la conexión a la base de datos usando DBAL
     *
     * @param string $connectionName
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function getManager($connectionName = 'mysql')
    {
        return $this->getDbalConnection(self::getConnectionParams($connectionName));
    }

    /**
     * Establece la query para ser ejecutada
     *
     * @param $query
     *
     * @return $this
     */
    public function setQuery($query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Devuelve la query para ser ejecutada
     *
     * @return string
     */
    public function getQuery()
    {
        return strtr($this->query, $this->queryArguments);
    }

    /**
     * Libera los datos de la query anterior
     */
    public function release()
    {
        $this->getInstance()->release();
    }

    /**
     * Ejecuta la Query procesada en la instancia de la base de datos
     *
     * @return mixed
     */
    public function runQuery()
    {
        return $this->getInstance()->runQuery($this->getQuery());
    }

    /**
     * Establece un argumento para reemplazar en la query
     *
     * @param string $argumentName  Nombre del argumento a reemplazar en la query
     * @param string $argumentValue Valor del argumento a reemplazar en la query
     *
     * @return $this
     */
    public function setQueryArgument($argumentName, $argumentValue)
    {
        $this->queryArguments[$argumentName] = $argumentValue;

        return $this;
    }

    /**
     * Establece varios argumentos para reemplazar en la query
     *
     * @param array $arguments Argumentos a reemplazar en la query
     *
     * @return $this
     */
    public function setQueryArguments(array $arguments)
    {
        foreach ($arguments as $argumentName => $argumentValue)
        {
            $this->setQueryArgument($argumentName, $argumentValue);
        }

        return $this;
    }

    /**
     * Retorna si ha sido posible cargar los datos en el objeto
     *
     * @param null $object
     *
     * @return bool
     */
    public function loadObject(&$object = null)
    {
        $this->getInstance()->setQuery($this->getQuery());
        $this->getInstance()->release();

        return $this->getInstance()->loadObject($object);
    }

    /**
     * Retorna los datos en un array asociativo
     *
     * @return array
     */
    public function loadAssoc()
    {
        $this->getInstance()->setQuery($this->getQuery());
        $this->getInstance()->release();

        return $this->getInstance()->loadAssoc();
    }

    /**
     * Retorna los datos en un array asociativo
     *
     * @param string $key
     *
     * @return array
     */
    public function loadAssocList($key = '')
    {
        $this->getInstance()->setQuery($this->getQuery());
        $this->getInstance()->release();

        return $this->getInstance()->loadAssocList($key);
    }

    /**
     * Retorna el resultado de la query
     *
     * @return bool|null
     */
    public function loadResult()
    {
        $this->getInstance()->setQuery($this->getQuery());
        $this->getInstance()->release();

        return $this->getInstance()->loadResult();
    }

    /**
     * Devuelve los parámetros de conexión para la configuración dada
     *
     * @param string $conectionName Nombre de la configuración a usar
     *
     * @return array Parámetros de la conexión
     */
    public static function getConnectionParams($conectionName)
    {
        $result = array();

        if (isset(Config::getConfig()->database[$conectionName]) !== false)
        {
            $server      = Config::getConfig()->database[$conectionName]['server'];
            $port        = Config::getConfig()->database[$conectionName]['port'];
            $username    = Config::getConfig()->database[$conectionName]['username'];
            $password    = Config::getConfig()->database[$conectionName]['password'];
            $database    = Config::getConfig()->database[$conectionName]['database'];
            $charset     = Config::getConfig()->database[$conectionName]['charset'];
            $driver      = Config::getConfig()->database[$conectionName]['driver'];
            $driverClass = Config::getConfig()->database[$conectionName]['driver_class'];

            $result = array(
                'host'     => $server,
                'port'     => $port,
                'user'     => $username,
                'password' => $password,
                'dbname'   => $database,
                'charset'  => $charset,
            );

            if (isset($driver) !== false)
            {
                $result['driver'] = $driver;
            }
            elseif (isset($driverClass) !== false)
            {
                $result['driverClass'] = $driverClass;
            }
            else
            {
                trigger_error('No se ha definido Driver para la conexión ' . $conectionName, E_USER_ERROR);
            }
        }
        else
        {
            trigger_error('No existe la configuración de conexión ' . $conectionName, E_USER_ERROR);
        }

        return $result;
    }

    /**
     * Devuelve una instancia de la conexión con la base de datos usando DBAL
     *
     * @param array $params Parámetros de la conexión para DBAL
     *
     * @return \Doctrine\DBAL\Connection
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getDbalConnection($params)
    {
        $connectionKey = md5(implode($params));

        if (isset(self::$connections[$connectionKey]) === false)
        {
            $dbalConnection = DriverManager::getConnection($params);

            if ($dbalConnection->isConnected() === false)
            {
                if ($dbalConnection->connect() === true)
                {
                    self::$connections[$connectionKey] = $dbalConnection;
                }
                else
                {
                    trigger_error('Imposible conectar usando DBAL', E_USER_ERROR);
                }
            }
        }

        return self::$connections[$connectionKey];
    }

    /**
     * Devuelve una instancia de la conexión con la base de datos usando MSSQLWrapper
     *
     * @param array $params Parámetros de la conexión para DBAL
     *
     * @return MSSQLWrapper
     */
    private function getOldWrappedConnection($params)
    {
        $connectionKey = md5(implode($params));

        if (isset(self::$connections[$connectionKey]) === false)
        {
            /**
             * TODO: Refactorizar esta porquería a un DBAL wrapper en condiciones
             */
            self::$connections[$connectionKey] = new MSSQLWrapper(
                $params['host'],
                $params['user'],
                $params['password'],
                $params['dbname']
            );
        }

        return self::$connections[$connectionKey];
    }
}
