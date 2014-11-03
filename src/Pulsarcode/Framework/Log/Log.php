<?php

namespace Pulsarcode\Framework\Log;

use Pulsarcode\Framework\Core\Core;

/**
 * Class Log Para gestionar los registros
 *
 * @package Pulsarcode\Framework\Log
 */
class Log extends Core
{
    /**
     * Mensaje de registro para error
     */
    const LOG_MESSAGE_ERROR = '<del style="text-decoration: none; color: #640000;">%s</del>';

    /**
     * Mensaje de registro para información
     */
    const LOG_MESSAGE_INFO = '%s';

    /**
     * Mensaje de registro para éxito
     */
    const LOG_MESSAGE_SUCCESS = '<del style="text-decoration: none; color: #006400;">%s</del>';

    /**
     * @var string Registro de mensajes para mostrar
     */
    private $messages = '';

    /**
     * @param string $message      El mensaje a registrar
     * @param array  $placeholders Tokens para remplazar en $message
     * @param bool   $timestamp    Agregar timestamp si es true
     *
     * TODO: Diferenciar entre cli y HTML para pintar colores usando <error></error>
     */
    public function error($message = '', array $placeholders = array(), $timestamp = false)
    {
        $this->setMessage(sprintf(static::LOG_MESSAGE_ERROR, $message), $placeholders, $timestamp);
    }

    /**
     * @param string $message      El mensaje a registrar
     * @param array  $placeholders Tokens para remplazar en $message
     * @param bool   $timestamp    Agregar timestamp si es true
     *
     * TODO: Diferenciar entre cli y HTML para pintar colores usando <info></info>
     */
    public function info($message = '', array $placeholders = array(), $timestamp = false)
    {
        $this->setMessage(sprintf(static::LOG_MESSAGE_INFO, $message), $placeholders, $timestamp);
    }

    /**
     * @param string $message      El mensaje a registrar
     * @param array  $placeholders Tokens para remplazar en $message
     * @param bool   $timestamp    Agregar timestamp si es true
     *
     * TODO: Diferenciar entre cli y HTML para pintar colores usando <success></success>
     */
    public function success($message = '', array $placeholders = array(), $timestamp = false)
    {
        $this->setMessage(sprintf(static::LOG_MESSAGE_SUCCESS, $message), $placeholders, $timestamp);
    }

    /**
     * Guarda los mensajes en el registro para ser procesados
     *
     * @param string $message      El mensaje a registrar
     * @param array  $placeholders Tokens para remplazar en $message
     * @param bool   $timestamp    Agregar timestamp si es true
     */
    public function setMessage($message = '', array $placeholders = array(), $timestamp = false)
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

        $this->messages .= $message;
    }

    /**
     * Devuelve el registro para ser procesaro
     *
     * @return string
     */
    public function getMessages()
    {
        return $this->messages;
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
            $this->setMessage($message);
        }
    }
}
