<?php

namespace Pulsarcode\Framework\Logger;

/**
 * Class Logger Para gestión de logs
 *
 * @package Pulsarcode\Framework\Logger
 */
class Logger
{
    /**
     * @var string Registro para procesar
     */
    private $log = '';

    /**
     * Guarda los mensajes en el registro para ser procesados
     *
     * @param string $message      El mensaje a registrar
     * @param array  $placeholders Tokens para remplazar en $message
     * @param bool   $timestamp    Agregar timestamp si es true
     */
    public function log($message = '', array $placeholders = array(), $timestamp = false)
    {
        if (!empty($placeholders))
        {
            $message = strtr($message, $placeholders);
        }

        if (isset($message) === false)
        {
            $message = str_repeat('-', 21);
        }
        elseif ($timestamp)
        {
            $message = sprintf('[%s] %s', date('Y-m-d H:i:s'), $message);
        }

        $message .= PHP_EOL;

        if (php_sapi_name() == 'cli')
        {
            echo $message;
        }

        $this->log .= $message;
    }

    /**
     * Devuelve el registro para ser procesaro
     *
     * @return string
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Registra todas las líneas y las borra
     *
     * @param array $output Salida de una ejecución exec()
     */
    public function parseOutput(array $output = array())
    {
        foreach ($output as $message)
        {
            $this->log($message);
        }
    }
}
