<?php

namespace PetateFramework\Database;

/*
 * Actualizado para MS SQL
 */

/**
* Conector a la base de datos
*/
class MSSQLWrapper {
    /** @var string query sql*/
    var $_sql            = '';
    /** @var int codigo de error de la BD */
    var $_errorNum        = 0;
    /** @var string Internal mensaje de error */
    var $_errorMsg        = '';
    /** @var Internal variable conector a base de datos */
    var $_resource        = '';
    /** @var Internal variable ultimo cursor utilizado */
    var $_cursor        = null;
    /** @var boolean Debug */
    var $_debug            = false;
    /** @var fichero de log */
    var $_filelog    = null;
    /** @var int desplazamiento hasta el limite */
    var $_offset        = 0;
    /** @var int contador de querys de la instancia */
    var $_ticker        = 0;
    /** @var array log de querys */
    var $_log            = null;
    /** @var string fecha/hora nulo */
    var $_nullDate        = '0000-00-00 00:00:00';
    /** @var string acotamiento de nombre de columnas */
    var $_nameQuote        = '';
    /** @var Internal variable ultimo cursor utilizado */
    var $_cur        = null;
    /** @var Internal variable comienzo a contar el tiempo */
    var $_timestart        = null;
    /** @var Internal variable tiempo acumulado desde la conexion */
    var $_timeaccum        = 0.0;

    var $_camposTipoFecha = array();

    /**
     * Constructor
     *
     * @param string $host
     * @param        $user
     * @param        $pass
     * @param string $db
     * @param bool   $persistente
     */
    public function __construct($host, $user, $pass, $db = '', $persistente = true)
    {
        /*
         * verificamos que las funciones para MS SQL est�n disponibles
         */
        global $_SERVER;
        global $DEBUG;

        $this->_camposTipoFecha = array('fecha%','campo','fin_autocasion','sello','test_date','ultima_actualizacion','ultima_aparicion','ultimo_aviso');

        $ip = 'localhost';
        if(isset($_SERVER['REMOTE_ADDR']))
        {
        $ip = $_SERVER['REMOTE_ADDR'];
        }

        $ruta = __DIR__;
        $this->time_start();

                if (!$persistente) {

          if (!function_exists( 'mssql_connect' )) {
            $SystemError = 1;
          } else {
            if (!($this->_resource = mssql_connect( $host, $user, $pass ))) {
                $SystemError = 2;
            }
                if ($db != '' && !mssql_select_db( $db, $this->_resource )) {
              $SystemError = 3;
                }
          }
                } else {
                  if (!function_exists( 'mssql_pconnect' )) {
                        $SystemError = 1;
                  } else {
                        $per = 1;
                        if (!($this->_resource = mssql_pconnect( $host, $user, $pass ))) {
                                $SystemError = 2;
                        }
                        if ($db != '' && !mssql_select_db( $db, $this->_resource )) {
                          $SystemError = 3;
                        }
                  }
                }
        $this->_debug=$DEBUG;
        $this->_ticker = 0;
        $this->_log = array();

        if(!isset($msg)) {
            $msg = '';
        }

        $this->_filelog = "$ruta/log/$ip.log";
        if(isset($SystemError)) $msg = " ($SystemError $user/$pass/$db)";
        if ($this->_debug) $this->logear($this->time_end()." [$per] CONEXI�N $db $msg");
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

    public function logear($texto) {
        $charlist = " \t\n\r\0\x0B";

                $txt = str_replace(str_split($charlist), ' ', $texto);
                $txt = str_replace(str_split($charlist), ' ', $txt);
        file_put_contents($this->_filelog, date("Y-m-d H:i:s")." >>$txt\r\n", FILE_APPEND);
    }

    public function time_start() {
             $mtime = microtime();
             $mtime = explode(" ",$mtime);
             $mtime = $mtime[1] + $mtime[0];
             $this->_timestart = $mtime;
        }

        function time_end() {
             $mtime = microtime();
             $mtime = explode(" ",$mtime);
         $mtime = $mtime[1] + $mtime[0];
         $this->_timeaccum += ($mtime - $this->_timestart);
             return "(".number_format($mtime - $this->_timestart, 6).")(".number_format($this->_timeaccum, 6).")";
        }

    public function selectDatabase($db) {
        if ($db != '' && !mssql_select_db( $db, $this->_resource )) {
            $mosSystemError = 3;
        }
        $this->_ticker = 0;
        $this->_log = array();
    }
    /**
     * @param int
     */
    public function debug( $level ) {
        $this->_debug = intval( $level );
    }
    /**
     * @return string codigo del ultimo error de la bd
     */
    public function getErrorNum() {
        return $this->_errorNum;
    }
    /**
    * @return string La cadena explicativa del error
    */
    public function getErrorMsg() {
        return str_replace( array( "\n", "'" ), array( '\n', "\'" ), $this->_errorMsg );
    }
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
     * @return string El fecha/hora nulo
     */
    public function getNullDate() {
        return $this->_nullDate;
    }
    /**
    * Establece el query para una proxima ejecucion
    *
    * @param string La query
    * @param string Comienzo de la fila que devolvera
    */
    public function setQuery( $sql, $offset = 0) {
        $this->_sql = $sql;
        $this->_offset = intval( $offset );
    }

    /**
    * @return string Devuelve la query actual, formateada para sacar en web
    */
    public function getQuery() {
        return "<pre>" . htmlspecialchars( $this->_sql ) . "</pre>";
    }
    /**
    * Ejecuta la query
    * @return mixed El cursor o FALSE si falla
    */
    public function query() {
        /*if ($this->_debug) {
            $this->_ticker++;
              $this->_log[] = $this->_sql;
        }
         */
        $this->_errorNum = 0;
        $this->_errorMsg = '';
        $this->time_start();
        $this->_cursor = mssql_query( $this->_sql, $this->_resource );
        if (!$this->_cursor) {
            $SystemError = 5;
            //echo "**".$this->_sql."<br>";
            return false;
        }
        if ($this->_debug) $this->logear($this->time_end()." ".$this->_sql);
        return $this->_cursor;
    }

    /**
     * @return int Numero de filas de la ejecucion anterior
     */
    public function getAffectedRows() {
        return mssql_rows_affected( $this->_resource );
    }

    /**
    * @return int Numero de filas devueltas del anterior query
    */
    public function getNumRows( $cur=null ) {
        return mssql_num_rows( $cur ? $cur : $this->_cursor );
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

        $cacheKey  = md5($this->_sql);
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

        $this->_offset = 0;
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

        $cacheKey  = md5($this->_sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $array = $cacheData;
        }
        else
        {
        if ($this->_offset>1) mssql_field_seek($cur, $this->_offset-1);
        $array = array();
        while ($row = mssql_fetch_row( $cur )) {
            $array[] = $row[$numinarray];
        }
        mssql_free_result( $cur );
            $this->setCache($cacheKey, $array);
        }

        $this->_offset = 0;
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

        $cacheKey  = md5($this->_sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $array = $cacheData;
        }
        else
        {
        if ($this->_offset>1) mssql_field_seek($cur, $this->_offset-1);
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

        $this->_offset = 0;
        return $array;
    }
    /**
    * Retorna un array asociativo
    * @param string Clave primaria
    * @return array Si <var>key</var> no se suministra es una lista secuencial de resultados
    */
    public function loadAssoc() {
        if (!$this->_cur) {
          if (!($this->_cur = $this->query())) {
            return null;
          }
        }

        $cacheKey  = md5($this->_sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $row = $cacheData;
        }
        else
        {
        if ($this->_offset>1) mssql_field_seek($this->_cur, $this->_offset-1);
        $array = array();
        $row = mssql_fetch_assoc( $this->_cur );
        //mssql_free_result( $cur );
        $this->_offset = 0;
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

        $cacheKey  = md5($this->_sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $array = $cacheData;
        }
        else
        {
        if ($this->_offset>1) mssql_field_seek($cur, $this->_offset-1);
        $array = array();
        while ($row = mssql_fetch_row( $cur )) {
            $array[$row[0]] = $row[1];
        }
        mssql_free_result( $cur );
            $this->setCache($cacheKey, $array);
        }

        $this->_offset = 0;
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
        $this->_offset = 0;

        $cacheKey  = md5($this->_sql);
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
                $this->_offset = 0;
            return null;
        }

        $cacheKey  = md5($this->_sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $array = $cacheData;
        }
        else
        {
        if ($this->_offset>1) mssql_field_seek($cur, $this->_offset-1);
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

        $this->_offset = 0;
        return $array;
    }

    /**
    * @return The first row of the query.
    */
    public function loadRow() {
        $this->_offset = 0;
        if (!$this->_cur) {
          if (!($this->_cur = $this->query())) {
            return null;
          }
        }
        $ret = null;

        $cacheKey  = md5($this->_sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $ret = $cacheData;
        }
        else
        {
        if ($row = mssql_fetch_row( $this->_cur )) {
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
                $this->_offset = 0;
            return null;
        }

        $cacheKey  = md5($this->_sql);
        $cacheData = $this->getCache($cacheKey);

        if ($cacheData)
        {
            $array = $cacheData;
        }
        else
        {
        if ($this->_offset>1) mssql_field_seek($cur, $this->_offset-1);
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

        $this->_offset = 0;
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
        ($verbose) && print "$sql<br />\n";
        if (!$this->query()) {
                $this->_offset = 0;
            return false;
        }
        $this->setQuery ("SELECT @@IDENTITY");
        $id = $this->loadResult();
        ($verbose) && print "id=[$id]<br />\n";
        if ($keyName && $id) {
            $object->$keyName = $id;
        }
        $this->_offset = 0;
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
        return "DB function failed with error number $this->_errorNum"
        ."<br /><font color=\"red\">$this->_errorMsg</font>"
        .($showSQL ? "<br />SQL = <pre>$this->_sql</pre>" : '');
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

    /**
    * @return The first row of the query.
    */
    public function release() {
        if($this->_cur) mssql_free_result( $this->_cur );
        $this->_cur = null;
    }

}
