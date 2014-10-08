<?php

namespace PetateFramework\Model;

use PetateFramework\Cache\Cache;
use PetateFramework\Database\MSSQLWrapper;

/**
 * Class Model Para gestionar los modelos
 *
 * @package PetateFramework\Model
 */
class Model
{
    /**
     * @var Cache Para gestionar la caché
     */
    protected $cache;

    /**
     * @var string Nombre del modelo en uso
     */
    protected $model;

    /**
     * @var MSSQLWrapper Para gestionar la base de datos
     */
    protected $database;

    /**
     * Constructor
     *
     * TODO: Refactorizar el wrapper de Database para no instanciar la porquería MSSQLWrapper
     *
     * @param MSSQLWrapper $database Base de datos para realizar las operaciones
     */
    public function __construct(MSSQLWrapper $database)
    {
        $this->cache    = new Cache();
        $this->model    = get_class($this);
        $this->database = $database;
    }

    /**
     * Establece el valor de un campo (mapeado si existe mapeo)
     *
     * @param string $field Nombre del campo
     * @param mixed  $value Valor del campo
     *
     * @return $this
     */
    public function __set($field, $value)
    {
        $this->_map($field);
        $this->$field = $value;
    }

    /**
     * Obtiene el valor de un campo (mapeado si existe mapeo)
     *
     * @param string $field Nombre del campo
     *
     * @return mixed
     */
    public function __get($field)
    {
        $this->_map($field);

        return $this->$field;
    }

    /**
     * Comprueba si el campo está seteado o es distinta a null
     *
     * @param string $field Nombre del campo
     *
     * @return bool
     */
    public function __isset($field)
    {
        return isset($this->$field);
    }

    /**
     * Borra el valor de un campo
     *
     * @param string $field Nombre del campo
     */
    public function __unset($field)
    {
        if (isset($this->$field) === false)
        {
            $errorMessage = sprintf('Imposible borrar el campo "%s" del modelo "%s"', $field, $this->model);
            trigger_error($errorMessage, E_USER_ERROR);
        }

        unset($this->$field);
    }

    /**
     * Función para obtener los datos con cache por encima
     *
     * @param string $node   Nombre del nodo a obtener
     * @param array  $fields Campos del noto a obtener
     */
    protected function getCachedData($node = 'id', array $fields = array())
    {
    }

    /**
     * Función para obtener los datos sin cache por encima
     *
     * @param string $node   Nombre del nodo a obtener
     * @param array  $fields Campos del noto a obtener
     */
    protected function getFreshData($node = 'id', array $fields = array())
    {
    }

    /**
     * Aplica el mapeo correspondiente existente en el modelo
     *
     * @param string $field Nombre del campo
     */
    private function _map(&$field)
    {
        $classVars = get_class_vars(__CLASS__);
        $modelVars = get_class_vars($this->model);

        if (array_key_exists($field, $classVars) === true)
        {
            $errorMessage = sprintf('"%s" es un campo reservado, llamada desde el modelo "%s"', $field, $this->model);
            trigger_error($errorMessage, E_USER_ERROR);
        }
        elseif (isset($this->_map) !== false)
        {
            if (isset($this->_map[$field]) === false)
            {
                $errorMessage = sprintf('Falta el mapeo de campo para "%s" en el modelo "%s"', $field, $this->model);
                trigger_error($errorMessage, E_USER_ERROR);
            }

            $map          = $this->_map[$field];
            $errorMessage = sprintf('Mapeo de campo "%s -> %s" en el modelo "%s"', $field, $map, $this->model);
            trigger_error($errorMessage, E_USER_NOTICE);
            $fields = array_diff_assoc($modelVars, $classVars);
            $field  = $map;

            if (array_key_exists($field, $fields) === false)
            {
                $errorMessage = sprintf('El campo mapeado "%s" no existe en el modelo "%s"', $field, $this->model);
                trigger_error($errorMessage, E_USER_ERROR);
            }

            foreach ($fields as $fieldName => $fieldValue)
            {
                if (array_search($fieldName, $this->_map) === false && $fieldName !== '_map')
                {
                    $errorMessage = sprintf('El campo "%s" no tiene mapeo en el modelo "%s"', $fieldName, $this->model);
                    trigger_error($errorMessage, E_USER_ERROR);
                }
            }
        }
    }
}
