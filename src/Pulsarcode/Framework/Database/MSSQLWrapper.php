<?php

namespace Pulsarcode\Framework\Database;

use Pulsarcode\Framework\Config\Config;
use Pulsarcode\Framework\Core\Core;
use Pulsarcode\Framework\Error\Error;
use Pulsarcode\Framework\Mail\Mail;
use Pulsarcode\Framework\Router\Router;

/**
 * Class MSSQLWrapper Para gestionar la base de datos SQL Server
 *
 * @package Pulsarcode\Framework\Database
 */
class MSSQLWrapper extends Core
{
    /**
     * Límite de tamaño para el log de queries (256MB)
     */
    const SQL_LOG_SIZE = 268435456;

    /**
     * @var array Queries ejecutadas para su pintado
     */
    private static $queries = array();

    /**
     * @var array Campos tipo fecha
     *
     * TODO Eliminar esta guarrería
     */
    public $_camposTipoFecha = array(
        'fecha%',
        'campo',
        'fin_autocasion',
        'sello',
        'test_date',
        'ultima_actualizacion',
        'ultima_aparicion',
        'ultimo_aviso',
    );

    /**
     * @var string acotamiento de nombre de columnas
     */
    public $_nameQuote = '';

    /**
     * @var null Internal variable ultimo cursor utilizado
     */
    public $cursor;

    /**
     * @var null Internal variable ultimo cursor utilizado
     *
     * TODO: Eliminar esta copia por que da lugar a bug en consultas consecutivas
     */
    public $cursorCopia = null;

    /**
     * @var boolean Debug mode
     */
    public $debug = false;

    /**
     * @var int Codigo del ultimo error de la base de datos
     */
    public $lastErrorCode = 0;

    /**
     * @var string Mensaje del ultimo error de la base de datos
     */
    public $lastErrorMessage = '';

    /**
     * @var resource Instancia de la conexión con la base de datos
     */
    public $link;

    /**
     * @var int desplazamiento hasta el limite
     */
    public $offset = 0;

    /**
     * @var string Query SQL para ejecutar en la base de datos
     */
    private $sql = '';

    /**
     * @var float Tiempo en el que se finalizó la query
     */
    private $queryTimeFinish = 0.0;

    /**
     * @var float Tiempo en el que se inició la query
     */
    private $queryTimeStart = 0.0;

    /**
     * @var float Tiempo que se ha invertido en todas las queries
     */
    private static $queryTimeTotal = 0.0;

    /**
     * Constructor
     *
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string $dbname
     * @param bool   $persistent
     */
    public function __construct($host, $user, $pass, $dbname, $persistent = true)
    {
        parent::startConnection();
        parent::__construct();

        /**
         * TODO: Implementar en Util la validacion multiple de nulos y tipos (TR) para evitar este código repetido
         */
        if (isset($host) === false)
        {
            trigger_error('No se ha especificado el host para la conexion con la base de datos', E_USER_ERROR);
        }
        elseif (isset($user) === false)
        {
            trigger_error('No se ha especificado el usuario para la conexion con la base de datos', E_USER_ERROR);
        }
        elseif (isset($pass) === false)
        {
            trigger_error('No se ha especificado el password para la conexion con la base de datos', E_USER_ERROR);
        }
        elseif (isset($dbname) === false)
        {
            trigger_error('No se ha especificado la base de datos para la conexion con la base de datos', E_USER_ERROR);
        }

        if ($persistent === false)
        {
            if (function_exists('mssql_connect') === false)
            {
                trigger_error(
                    'No existe la función mssql_connect, no hay módulo Sybase/PDOLib/ODBC disponible',
                    E_USER_ERROR
                );
            }
            else
            {
                $this->link = mssql_connect($host, $user, $pass);

                if ($this->link === false)
                {
                    trigger_error('Imposible conectar con la base de datos en modo no persistente', E_USER_ERROR);
                }
            }
        }
        else
        {
            if (function_exists('mssql_pconnect') === false)
            {
                trigger_error(
                    'No existe la función pmssql_connect, no hay módulo Sybase/PDOLib/ODBC disponible',
                    E_USER_ERROR
                );
            }
            else
            {
                $this->link = mssql_pconnect($host, $user, $pass);

                if ($this->link === false)
                {
                    trigger_error('Imposible conectar con la base de datos en modo persistente', E_USER_ERROR);
                }
            }
        }

        if ($this->selectDatabase($dbname) === false)
        {
            trigger_error('Imposible seleccionar la base de datos "' . $dbname . '"', E_USER_ERROR);
        }

        parent::finishConnection();

        $this->debug = (in_array(Config::getConfig()->environment, Config::$debugEnvironments));

        if ($this->debug)
        {
            $connectionType = ($persistent) ? 'Persistent' : 'Dynamic';
            $this->log(
                sprintf(
                    '%s connection to database host=%s;dbname=%s;user=%s;password=******** (filtered)',
                    $connectionType,
                    $host,
                    $dbname,
                    $user
                )
            );
        }
    }

    /**
     * Functión para hacer override a la clase DatabaseCache
     *
     * @param string $cacheKey Clave de caché a recuperar
     *
     * @return bool
     */
    public function getCache($cacheKey = '')
    {
        return false;
    }

    /**
     * Functión para hacer override a la clase DatabaseCache
     *
     * @param string $cacheKey    Clave de caché a establecer
     * @param null   $cacheValue  Valor de caché a establecer
     * @param null   $cacheExpire Tiempo de caché a establecer
     */
    public function setCache($cacheKey = '', $cacheValue = null, $cacheExpire = null)
    {
        // No cache, sorry ;-)
    }

    /**
     * Establece la query SQL para una proxima ejecucion
     *
     * @param string $sql    La query SQL
     * @param string $offset Comienzo de la fila que devolvera
     */
    public function setQuery($sql, $offset = 0)
    {
        $this->sql    = $sql;
        $this->offset = intval($offset);
    }

    /**
     * Devuelve la query actual para visualizar en consola o HTML
     *
     * @return string Query SQL
     */
    public function getQuery()
    {
        if (php_sapi_name() !== 'cli')
        {
            $result = '<pre>' . $this->sql . '</pre>';
        }
        else
        {
            $result = PHP_EOL . $this->sql . PHP_EOL;
        }

        return $result;
    }

    /**
     * Obtiene el tiempo total invertido en las queries
     *
     * @return float
     */
    public static function getQueryTimeTotal()
    {
        return self::$queryTimeTotal;
    }

    /**
     * Setea y ejecuta una query
     *
     * @param string $query
     *
     * @return mixed
     */
    public function runQuery($query)
    {
        $this->setQuery($query);

        return $this->query();
    }

    /**
     * Ejecuta la query y devuelve el recurso en caso de éxito o false si no
     *
     * @return bool|mixed|null
     */
    public function query()
    {
        $this->setQueryTimeStart();
        $this->cursor = mssql_query($this->sql, $this->link);
        $this->setQueryTimeFinish();

        if ($this->debug)
        {
            $this->log($this->sql, $isQuery = true, $error = ($this->cursor === false));
        }

        if ($this->cursor === false)
        {
            $lastErrorMessage = mssql_get_last_message() ?: 'MSSQL no ha dado información sobre este error';

            switch(connection_status())
            {
                case CONNECTION_ABORTED:
                    $lastErrorMessage = sprintf('La conexión ha sido abortada por un error (%s)', $lastErrorMessage);
                    break;

                case CONNECTION_TIMEOUT:
                    $lastErrorMessage = sprintf('La conexión ha sido cerrada por tiempo (%s)', $lastErrorMessage);
                    break;
            }

            $this->lastErrorMessage = $lastErrorMessage;
            $errorMessage           = 'Imposible ejecutar la query debido a un error: ' . $this->lastErrorMessage;
            Error::mail($errorMessage, htmlentities($this->sql));

            /**
             * TODO: Hasta reducir el numero ingente de errores tenemos que hacer esta Garretada
             */
            if (false !== in_array(Config::getConfig()->environment, Config::$debugEnvironments))
            {
                trigger_error($errorMessage, E_USER_ERROR);
            }
            else
            {
                trigger_error($errorMessage, E_USER_WARNING);
            }
        }

        return ($this->cursor !== false) ? $this->cursor : false;
    }

    /**
     * @return int Numero de filas de la ejecucion anterior
     */
    public function getAffectedRows()
    {
        return mssql_rows_affected($this->link);
    }

    /**
     * Función para registrar eventos de la base de datos
     *
     * @param string $message Texto a registrar
     * @param bool   $isQuery Indica si proviene de $this->query
     * @param bool   $error   Indica si la query ha lanzado un error
     */
    private function log($message, $isQuery = false, $error = false)
    {
        $message = sprintf(
            '[%s] %s %s',
            date('Y-m-d H:i:s'),
            $this->getQueryTimestamp($isQuery, $error),
            preg_replace('/\s+/', ' ', $message)
        );

        if (Router::getRequest()->server->has('REMOTE_ADDR'))
        {
            $host = Router::getRequest()->server->get('REMOTE_ADDR');
        }
        else
        {
            $host = 'localhost';
        }

        $sqlLog = sprintf('%s/database-%s.log', Config::getConfig()->paths['logs'], $host);

        if ('cli' === php_sapi_name())
        {
            echo $message . PHP_EOL;
        }

        if (false !== Config::getConfig()->queries['write'])
        {
            /**
             * Guardamos el log en background para no penalizar rendimiento
             */
            register_shutdown_function(
                function () use ($sqlLog, $message)
                {
                    if (file_exists($sqlLog) && filesize($sqlLog) >= MSSQLWrapper::SQL_LOG_SIZE)
                    {
                        /**
                         * Guardamos al menos la última pila de errores pos si tuvieramos que consultarlos
                         */
                        rename($sqlLog, $sqlLog . '.old');
                    }

                    file_put_contents($sqlLog, $message . PHP_EOL, FILE_APPEND);
                }
            );
        }
    }

    /**
     * Establece la marca de tiempo en el que finalizó la query
     */
    private function setQueryTimeFinish()
    {
        $this->queryTimeFinish = microtime(true);
        self::$queryTimeTotal += ($this->queryTimeFinish - $this->queryTimeStart);
    }

    /**
     * Establece la marca de tiempo en el que empezó la query
     */
    private function setQueryTimeStart()
    {
        $this->queryTimeStart = microtime(true);
    }

    /**
     * Devuelve la marca de tiempo actual respecto al tiempo de inicio
     *
     * @param bool $isQuery Indica si proviene de $this->query
     * @param bool $error   Indica si la query ha lanzado un error
     *
     * @return string Marca de tiempo en milisegundos
     */
    private function getQueryTimestamp($isQuery = false, $error = false)
    {
        $queryTime = ($this->queryTimeFinish - $this->queryTimeStart);

        if (false !== $isQuery)
        {
            self::$queries[] = array(
                'error' => $error,
                'time'  => $queryTime,
                'sql'   => $this->sql,
            );
        }

        return sprintf('(Query: %.3fms Total: %.3fms)', $queryTime, self::$queryTimeTotal);
    }

    /**
     * Selecciona la base de datos para el recurso existente si existe
     *
     * @param string $dbname Nombre de la base de datos
     *
     * @return bool Devuelve true en caso de éxito, false si hay error
     */
    public function selectDatabase($dbname)
    {
        if (isset($this->link) !== false)
        {
            return mssql_select_db($dbname, $this->link);
        }
        else
        {
            trigger_error('Imposible seleccionar la base de datos: ' . $dbname, E_USER_ERROR);
        }
    }

    /**
     * Devuelve el último código de error de la base de datos
     *
     * @return int Código de error
     */
    public function getLastErrorCode()
    {
        return $this->lastErrorCode;
    }

    /**
     * @return string La cadena explicativa del error
     */
    public function getLastErrorMessage()
    {
        return preg_replace('/\s+/', ' ', $this->lastErrorMessage);
    }

    /**
     * Libera el último cursor utilizado y el recurso de la query
     */
    public function release()
    {
        if (isset($this->cursorCopia) !== false)
        {
            mssql_free_result($this->cursorCopia);
        }

        $this->cursorCopia = null;
    }

    /**
     * Pinta las consultas SQL realizadas ordenadas por su tiempo
     */
    public static function showQueries()
    {
        if (false !== Router::getRequest()->isXmlHttpRequest() || 'json' === Router::getRequest()->getRequestFormat())
        {
            return;
        }

        if (false !== Config::getConfig()->queries['show'])
        {
            if (in_array(Config::getConfig()->environment, Config::$debugEnvironments))
            {
                if (false === empty(self::$queries))
                {
                    /**
                     * Si es por línea de comandos pintamos un resumen en texto plano
                     */
                    if (php_sapi_name() == 'cli')
                    {
                        /**
                         * TODO: Pintar estadísticas teniendo en cuenta que en loc y dev se pintaron ya
                         */
                    }
                    else
                    {
                        $queries = self::$queries;
                        usort(
                            $queries,
                            function ($a, $b)
                            {
                                return $a['time'] < $b['time'];
                            }
                        );

                        include Config::getConfig()->paths['views']['web'] . '/query-table.html.php';
                    }
                }
            }
        }

        if (false !== Config::getConfig()->queries['send'])
        {
            $slowQueries = array();

            foreach (self::$queries as $query)
            {
                if ($query['time'] > Config::getConfig()->queries['slow'])
                {
                    $slowQueries[] = sprintf(
                        '%.3fms > %s %s',
                        $query['time'],
                        (false !== $query['error'] ? '[ERROR]' : '[OK]'),
                        preg_replace('/\s+/', ' ', $query['sql'])
                    );
                }
            }

            /**
             * TODO: Dupicamos código de Error:mail por que no podemos meter en background otro callback en background
             */
            if (false === empty($slowQueries))
            {
                $environment = Config::getConfig()->environment;

                if (php_sapi_name() !== 'cli')
                {
                    $ip   = Router::getRequest()->server->get('SERVER_ADDR');
                    $host = Router::getRequest()->getHttpHost();
                    $uri  = Router::getRequest()->getRequestUri();
                }
                elseif (Router::getRequest()->server->has('SSH_CONNECTION'))
                {
                    $ip   = Router::getRequest()->server->get('SSH_CONNECTION');
                    $host = Router::getRequest()->server->get('HOSTNAME');
                    $uri  = Router::getRequest()->server->get('SCRIPT_NAME');
                }
                else
                {
                    $ip   = Router::getRequest()->server->get('HOME');
                    $host = Router::getRequest()->server->get('USER');
                    $uri  = Router::getRequest()->server->get('SCRIPT_NAME');
                }

                $info = print_r(
                    array(
                        '$_GET'     => Router::getRequest()->query->all(),
                        '$_POST'    => Router::getRequest()->request->all(),
                        '$_FILES'   => Router::getRequest()->files->all(),
                        '$_COOKIE'  => Router::getRequest()->cookies->all(),
                        '$_SERVER'  => Router::getRequest()->server->all(),
                        '$_SESSION' => Router::getRequest()->getSession()->all(),
                    ),
                    true
                );

                $subject = sprintf(
                    'Queries demasiado lentas (por encima de %s segundos)',
                    Config::getConfig()->queries['slow']
                );
                $body    = sprintf('<pre>%s</pre>', implode(PHP_EOL, $slowQueries));
                $mailer  = new Mail();
                $mailer->initConfig('autobot');
                $mailer->AddAddress(Config::getConfig()->debug['mail']);
                $mailer->setSubject(sprintf('[PERFORMANCE] (%s) [%s] %s - %s', $environment, $ip, $host, $uri));
                $mailer->setBody(sprintf('<h4>%s</h4><hr />%s<hr /><pre>%s</pre>', $subject, $body, $info));
                $mailer->Send();
            }
        }
    }

    /*******************************************************************************************************************
     * A partir de aqui las funciones existentes son las viejas que había sin refacorizar de la clase anterior Joomla! *
     ******************************************************************************************************************/
    /*******************************************************************************************************************
     * A partir de aqui las funciones existentes son las viejas que había sin refacorizar de la clase anterior Joomla! *
     ******************************************************************************************************************/
    /*******************************************************************************************************************
     * A partir de aqui las funciones existentes son las viejas que había sin refacorizar de la clase anterior Joomla! *
     ******************************************************************************************************************/
    /*******************************************************************************************************************
     * A partir de aqui las funciones existentes son las viejas que había sin refacorizar de la clase anterior Joomla! *
     ******************************************************************************************************************/
    /*******************************************************************************************************************
     * A partir de aqui las funciones existentes son las viejas que había sin refacorizar de la clase anterior Joomla! *
     ******************************************************************************************************************/
    /*******************************************************************************************************************
     * A partir de aqui las funciones existentes son las viejas que había sin refacorizar de la clase anterior Joomla! *
     ******************************************************************************************************************/
    /*******************************************************************************************************************
     * A partir de aqui las funciones existentes son las viejas que había sin refacorizar de la clase anterior Joomla! *
     ******************************************************************************************************************/
    /*******************************************************************************************************************
     * A partir de aqui las funciones existentes son las viejas que había sin refacorizar de la clase anterior Joomla! *
     ******************************************************************************************************************/
    /*******************************************************************************************************************
     * A partir de aqui las funciones existentes son las viejas que había sin refacorizar de la clase anterior Joomla! *
     ******************************************************************************************************************/
    /*******************************************************************************************************************
     * A partir de aqui las funciones existentes son las viejas que había sin refacorizar de la clase anterior Joomla! *
     ******************************************************************************************************************/

    /**
    * Get a database escaped string
    * @return string
    */
    public function getEscaped( $text ) {
        /*
        * Hace un escape de la cadena para lso caracteres especiales
         */
        $string = str_replace("'", "''", $text);
        return $string;
    }
    /**
    * retorna el escape de la cadena dada entre comillas
    * @return string
    */
    public function Quote( $text ) {
        return '\'' . $this->getEscaped( $text ) . '\'';
    }
    /**
     * Acota un identificador (nombre de tabla, de campo..etc)
     * @param string El nombre
     * @return string El nombre acotado
     */
    public function NameQuote($s)
    {
        $q = $this->_nameQuote;

        if (strlen($q) == 1)
        {
            return $q . $s . $q;
        }
        else
        {
            return (isset($q{0}) ? $q{0} : '') . $s . (isset($q{1}) ? $q{1} : '');
        }
    }

    /**
    * Retorna el valor del primer campo de la primera fila de la query
    *
    * @return bool|null retorna null si la query falla
    */
    public function loadResult() {
        if (!($cur = $this->query())) {
            return null;
        }
        $ret = null;

        $cacheKey  = md5($this->sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $ret = $cacheData;
        }
        else
        {
            if ($row = mssql_fetch_row($cur))
            {
            $ret = $row[0];
        }
            mssql_free_result($cur);
            $this->setCache($cacheKey, $ret);
        }

        $this->offset = 0;
        return $ret;
    }
    /**
    * Retorna una lista de resultados en un array
    * @param int indice donde colocar el resultado
    * @return array Arry de resultados
    */
    public function loadResultArray($numinarray = 0) {
        if (!($cur = $this->query())) {
            return null;
        }

        $cacheKey  = md5($this->sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $array = $cacheData;
        }
        else
        {
        if ($this->offset>1) mssql_field_seek($cur, $this->offset-1);
        $array = array();
        while ($row = mssql_fetch_row( $cur )) {
            $array[] = $row[$numinarray];
        }
        mssql_free_result( $cur );
            $this->setCache($cacheKey, $array);
        }

        $this->offset = 0;
        return $array;
    }
    /**
    * Retorna un array asociativo
    * @param string Clave primaria
    * @return array Si <var>key</var> no se suministra es una lista secuencial de resultados
    */
    public function loadAssocList( $key='' ) {
        if (!($cur = $this->query())) {
            return null;
        }

        $cacheKey  = md5($this->sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $array = $cacheData;
        }
        else
        {
        if ($this->offset>1) mssql_field_seek($cur, $this->offset-1);
        $array = array();
        while ($row = mssql_fetch_assoc( $cur )) {
            if ($key) {
                $array[$row[$key]] = $row;
            } else {
                $array[] = $row;
            }
        }
        mssql_free_result( $cur );
            $this->setCache($cacheKey, $array);
        }

        $this->offset = 0;
        return $array;
    }
    /**
    * Retorna un array asociativo
    * @param string Clave primaria
    * @return array Si <var>key</var> no se suministra es una lista secuencial de resultados
    */
    public function loadAssoc() {
        if (!$this->cursorCopia) {
          if (!($this->cursorCopia = $this->query())) {
            return null;
          }
        }

        $cacheKey  = md5($this->sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $row = $cacheData;
        }
        else
        {
        if ($this->offset>1) mssql_field_seek($this->cursorCopia, $this->offset-1);
        $array = array();
        $row = mssql_fetch_assoc( $this->cursorCopia );
        //mssql_free_result( $cur );
        $this->offset = 0;
        if ($row == null) $this->release();
            $this->setCache($cacheKey, $row);
        }

        return $row;
    }

    /**
    * Retorna un array asociativo
    * @return se suministra es una lista secuencial de resultados, el indice es el primer elemento del select
    */
    public function loadHash() {
        if (!($cur = $this->query())) {
            return null;
        }

        $cacheKey  = md5($this->sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $array = $cacheData;
        }
        else
        {
        if ($this->offset>1) mssql_field_seek($cur, $this->offset-1);
        $array = array();
        while ($row = mssql_fetch_row( $cur )) {
            $array[$row[0]] = $row[1];
        }
        mssql_free_result( $cur );
            $this->setCache($cacheKey, $array);
        }

        $this->offset = 0;
        return $array;

    }

    /**
    * Carga la primera fila en un objeto dado
    *
    * If an object is passed to this function, the returned row is bound to the existing elements of <var>object</var>.
    * If <var>object</var> has a value of null, then all of the returned query fields returned in the object.
    * @param string The SQL query
    * @param object The address of variable
    */
    public function loadObject( &$object ) {
        $this->offset = 0;

        $cacheKey  = md5($this->sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $object = $cacheData;
        }
        else
        {
        if ($object != null) {
            if (!($cur = $this->query())) {
                return false;
            }
            if ($array = mssql_fetch_assoc( $cur )) {
                    $this->setCache($cacheKey, $object);
                mssql_free_result( $cur );
                /*
                 */
                BindArrayToObject( $array, $object, null, null );
                return true;
            } else {
                return false;
            }
        } else {
            if ($cur = $this->query()) {
                if ($object = mssql_fetch_object( $cur )) {
                        $this->setCache($cacheKey, $object);
                    mssql_free_result( $cur );
                    return true;
                } else {
                    $object = null;
                    return false;
                }
            } else {
                return false;
            }
        }
    }
    }
    /**
    * Load a list of database objects
    * @param string The field name of a primary key
    * @return array If <var>key</var> is empty as sequential list of returned records.
    * If <var>key</var> is not empty then the returned array is indexed by the value
    * the database key.  Returns <var>null</var> if the query fails.
    */
    public function loadObjectList( $key='' ) {
        if (!($cur = $this->query())) {
                $this->offset = 0;
            return null;
        }

        $cacheKey  = md5($this->sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $array = $cacheData;
        }
        else
        {
        if ($this->offset>1) mssql_field_seek($cur, $this->offset-1);
        $array = array();
        while ($row = mssql_fetch_object( $cur )) {
            if ($key) {
                $array[$row->$key] = $row;
            } else {
                $array[] = $row;
            }
        }
        mssql_free_result( $cur );
            $this->setCache($cacheKey, $array);
        }

        $this->offset = 0;
        return $array;
    }

    /**
    * @return The first row of the query.
    */
    public function loadRow() {
        $this->offset = 0;
        if (!$this->cursorCopia) {
          if (!($this->cursorCopia = $this->query())) {
            return null;
          }
        }
        $ret = null;

        $cacheKey  = md5($this->sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $ret = $cacheData;
        }
        else
        {
        if ($row = mssql_fetch_row( $this->cursorCopia )) {
            $ret = $row;
        }
        //mssql_free_result( $cur );
        if ($ret == null) $this->release();
            $this->setCache($cacheKey, $ret);
        }

        return $ret;
    }
    /**
    * Load a list of database rows (numeric column indexing)
    * @param int Value of the primary key
    * @return array If <var>key</var> is empty as sequential list of returned records.
    * If <var>key</var> is not empty then the returned array is indexed by the value
    * the database key.  Returns <var>null</var> if the query fails.
    */
    public function loadRowList( $key=null ) {
        if (!($cur = $this->query())) {
                $this->offset = 0;
            return null;
        }

        $cacheKey  = md5($this->sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $array = $cacheData;
        }
        else
        {
        if ($this->offset>1) mssql_field_seek($cur, $this->offset-1);
        $array = array();
        while ($row = mssql_fetch_row( $cur )) {
            if ( !is_null( $key ) ) {
                $array[$row[$key]] = $row;
            } else {
                $array[] = $row;
            }
        }
        mssql_free_result( $cur );
            $this->setCache($cacheKey, $array);
        }

        $this->offset = 0;
        return $array;
    }
    /**
    * Document::db_insertObject()
    *
    * { Description }
    *
    * @param string $table This is expected to be a valid (and safe!) table name
    * @param [type] $keyName
    * @param [type] $verbose
    */
    public function insertObject( $table, &$object, $keyName = NULL, $verbose=false ) {
        $fmtsql = "INSERT INTO $table ( %s ) VALUES ( %s ) ";
        $fields = array();
        foreach (get_object_vars( $object ) as $k => $v) {
            if (is_array($v) or is_object($v) or $v === NULL) {
                continue;
            }
            if ($k{0} == '_') { // internal field
                continue;
            }

            $v = $this->validarFormateoFecha($k, $v);

            $fields[] = $this->NameQuote( $k );
            $values[] = $this->Quote( $v );
        }
        $this->setQuery( sprintf( $fmtsql, implode( ",", $fields ) ,  implode( ",", $values ) ) );

        if (!$this->query()) {
                $this->offset = 0;
            return false;
        }
        $this->setQuery ("SELECT @@IDENTITY");
        $id = $this->loadResult();

        if ($keyName && $id) {
            $object->$keyName = $id;
        }
        $this->offset = 0;
        return true;
    }

    /**
    * Document::db_updateObject()
    *
    * { Description }
    *
    * @param string $table This is expected to be a valid (and safe!) table name
    * @param [type] $updateNulls
    */
    public function updateObject( $table, &$object, $keyName, $updateNulls=true ) {
        $fmtsql = "UPDATE $table with(rowlock) SET %s WHERE %s";
        $tmp = array();
        foreach (get_object_vars( $object ) as $k => $v) {
            if( is_array($v) or is_object($v) or $k[0] == '_' ) { // internal or NA field
                continue;
            }
            if( $k == $keyName ) { // PK not to be updated
                $where = $keyName . '=' . $this->Quote( $v );
                continue;
            }
            if ($v === NULL && !$updateNulls) {
                continue;
            }

            $v = $this->validarFormateoFecha($k, $v);

            if( $v == '' ) {
                $val = "''";
            } else {
                $val = $this->Quote( $v );
            }
            $tmp[] = $this->NameQuote( $k ) . '=' . $val;
        }
        $this->setQuery( sprintf( $fmtsql, implode( ",", $tmp ) , $where ) );
        return $this->query();
    }

    /**
     * Funci�n que mira si el nombre del campo est� en el array $_camposTipoFecha e intenta formatearlo
     * para intentar evitar posibles fallos al insertar o modificar datos.
     *
     * @param string $column_name
     * @param string $value
     */
    public function validarFormateoFecha($column_name, $value){

        // check if the column to modify is a date
        $campoTipoFecha = false;
        foreach($this->_camposTipoFecha as $campo){
            $nombre_variable = stripos($campo, "%");
            if ($nombre_variable === false) { // El campo se tendr�a que llamar exactamente igual al del valor $campo
                if($column_name == $campo){
                    $campoTipoFecha = true;
                    break;
                }
            } else {
                $nombre_columna = str_replace('%','',$campo);
                $nombre_variable = stripos($column_name, $nombre_columna);
                if ($nombre_variable !== false) {
                    $campoTipoFecha = true;
                    break;
                }
            }
        }

        if($campoTipoFecha){
            if (strtotime($value) !== false) {
                $value = date(_FORMATO_FECHA, strtotime($value));
            }
        }

        return $value;

    }

    /**
    * @param boolean If TRUE, displays the last SQL statement sent to the database
    * @return string A standised error message
    */
    function stderr( $showSQL = false ) {
        return "DB function failed with error number $this->lastErrorCode"
        ."<br /><font color=\"red\">$this->lastErrorMessage</font>"
        .($showSQL ? "<br />SQL = <pre>$this->sql</pre>" : '');
    }

    public function insertid() {
        $this->setQuery ("SELECT @@IDENTITY");
        $id = $this->loadResult();
        return $id;
    }

    public function getVersion() {
        return "?";
    }

    /**
     * @return array A list of all the tables in the database
     */
    public function getTableList() {
        $this->setQuery( 'SELECT name FROM sysobjects WHERE xtype = "U"' );
        return $this->loadResultArray();
    }

    /**
    * Fudge method for ADOdb compatibility
    */
    public function GenID( $foo1=null, $foo2=null ) {
        return '0';
    }
}
