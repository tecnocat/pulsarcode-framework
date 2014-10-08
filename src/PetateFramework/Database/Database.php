<?php

namespace PetateFramework\Database;

use PetateFramework\Config\Config;
use Doctrine\DBAL\DriverManager;

/**
 * Class Database Para gestionar la base de datos
 *
 * @package PetateFramework\Controller
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
     * Devuelve una instancia de la conexión a la base de datos
     *
     * @param string $config Nombre de la configuración de conexión
     *
     * @return MSSQLWrapper
     */
    public function getInstance($config = 'autocasion')
    {
        if (isset(Config::getConfig()->database[$config]) === false)
        {
            trigger_error('No existe la configuración de conexión ' . $config, E_USER_ERROR);
        }

        $host = Config::getConfig()->database[$config]['host'];
        $user = Config::getConfig()->database[$config]['user'];
        $pass = Config::getConfig()->database[$config]['pass'];
        $base = Config::getConfig()->database[$config]['base'];

        /*
        $params    = array(
            'dbname'   => $base,
            'user'     => $user,
            'password' => $pass,
            'host'     => $host,
            'driver'   => 'sqlsrv',
        );
        $conection = DriverManager::getConnection($params);
        */

        return $this->connect($host, $user, $pass, $base);
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
     * Retorna los datos en un array asociativo
     *
     * @return array
     */
    public function loadAssoc()
    {
        $this->getInstance()->setQuery($this->getQuery());

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

        return $this->getInstance()->loadResult();
    }

    /**
     * Establece la instancia de la conexión a la base de datos
     *
     * @param null $server   Servidor de la base de datos
     * @param null $user     Usuario de la base de datos
     * @param null $password Contraseña de la base de datos
     * @param null $database Nombre de la base de datos
     *
     * @return MSSQLWrapper
     */
    private function connect($server = null, $user = null, $password = null, $database = null)
    {
        $connectionKey = md5($server . $user . $password . $database);

        if (isset(self::$connections[$connectionKey]) === false)
        {
            /**
             * TODO: Refactorizar esta porquería a un DBAL wrapper en condiciones
             */
            self::$connections[$connectionKey] = new MSSQLWrapper($server, $user, $password, $database);
        }

        return self::$connections[$connectionKey];
    }
}
