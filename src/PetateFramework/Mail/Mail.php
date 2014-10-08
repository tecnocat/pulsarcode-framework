<?php

namespace PetateFramework\Mail;

use PetateFramework\Config\Config;

/**
 * Class Mail Para gestionar los mails
 *
 * @package PetateFramework\Mail
 */
class Mail
{
    /**
     * User Agent para fakear los mails
     */
    const XMAILER = 'Microsoft Outlook Express %d.%d.%d.%d';

    /**
     * @var \PHPMailer Clase externa para gestionar los mails
     */
    private $mailer;

    /**
     * @var null Template para pintar
     */
    private $template = null;

    /**
     * @var array Variables de la template
     */
    private $variables = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->mailer = new \PHPMailer();
    }

    /**
     * Setea los valores internos de PHPMailer
     *
     * @param string $variable Nombre de la configuración
     * @param string $value    Valor de la configuración
     */
    public function __set($variable, $value)
    {
        $this->mailer->set($variable, $value);
    }

    /**
     * Comprueba los valores internos de PHPMailer
     *
     * @param string $variable Nombre de la configuración
     *
     * @return bool
     */
    public function __isset($variable)
    {
        return (isset($this->mailer->$variable));
    }

    /**
     * Obtiene los valores internos de PHPMailer
     *
     * @param string $variable Nombre de la configuración
     *
     * @return null
     */
    public function __get($variable)
    {
        return (isset($this->mailer->$variable)) ? $this->mailer->$variable : null;
    }

    /**
     * Inicializa l configuración para enviar el email
     *
     * @param string $config Nombre de la configuración a obtener
     */
    public function initConfig($config = 'default')
    {
        /**
         * Seteamos los valores por defecto necesarios
         */
        $this->From        = $this->getConfig($config, 'mail');
        $this->FromName    = $this->getConfig($config, 'name');
        $this->Sender      = $this->getConfig($config, 'mail');
        $this->Host        = $this->getConfig($config, 'host');
        $this->Port        = $this->getConfig($config, 'port');
        $this->Username    = $this->getConfig($config, 'user');
        $this->Password    = $this->getConfig($config, 'pass');
        $this->SMTPAuth    = $this->getConfig($config, 'auth');
        $this->Mailer      = 'smtp';
        $this->ContentType = 'text/html';
        $this->CharSet     = 'UTF-8';
        $this->XMailer     = sprintf(self::XMAILER, rand(4, 6), rand(10, 99), rand(1000, 9999), rand(1000, 9999));
        $this->mailer->clearAllRecipients();
    }

    /**
     * Setea el origen del email
     *
     * @param string $address Dirección del remitente
     * @param string $name    Nombre del remitente
     * @param bool   $auto    Setear el sender como este remitente
     */
    public function setFrom($address, $name = '', $auto = true)
    {
        $this->mailer->setFrom($address, $name, $auto);

        if ($auto)
        {
            $this->Sender = $address;
        }
    }

    /**
     * Setea el listado de destinatarios
     *
     * @param array $to Lista de destinatarios
     */
    public function setTo(array $to = array())
    {
        foreach ($to as $recipient)
        {
            list($address, $name) = $recipient;
            $this->mailer->addAddress($address, $name);
        }
    }

    /**
     * Agrega un destinatario mas a los existentes
     *
     * @param string $address
     * @param string $name
     */
    public function addAddress($address, $name = '')
    {
        $this->mailer->addAddress($address, $name);
    }

    /**
     * Setea el asunto del email
     *
     * @param string $subject Asunto del email
     */
    public function setSubject($subject = '')
    {
        $this->Subject = $subject;
    }

    /**
     * Setea el cuerpo del email
     *
     * @param string $body Cuerpo del email
     */
    public function setBody($body = '')
    {
        $this->Body = $body;
    }

    /**
     * Establece la template para el email
     *
     * @param string $template   Archivo template para la vista
     * @param null   $controller Controlador para obtener la template
     */
    public function setTemplate($template, $controller = null)
    {
        $pathMails = Config::getConfig()->paths['mails'] . DIRECTORY_SEPARATOR;

        if (isset($controller))
        {
            $template = substr($controller, 0, -10) . DIRECTORY_SEPARATOR . $template;
        }

        if (!is_file($pathMails . $template) || !is_readable($pathMails . $template))
        {
            trigger_error('La template ' . $pathMails . $template . ' no existe o no tengo acceso', E_USER_ERROR);
        }

        $this->template = $template;
    }

    /**
     * Establece las variables para la template
     *
     * @param array $variables
     */
    public function setVariables(array $variables = array())
    {
        foreach ($variables as $name => $value)
        {
            $this->variables[$name] = $value;
        }
    }

    /**
     * Envía el email
     */
    public function send()
    {
        $envinronment = Config::getConfig()->environment;
        $pathMails    = Config::getConfig()->paths['mails'] . DIRECTORY_SEPARATOR;

        /**
         * En desarrollo mostramos información de la TPL que usa el email
         */
        if (in_array($envinronment, array('loc', 'des')))
        {
            $prefix = 'Desarrollo (' . $envinronment . ')';

            if (isset($this->template))
            {
                $prefix .= ' TPL: ' . $this->template;
            }

            $this->setSubject($prefix . ' ' . $this->Subject);
        }

        /**
         * La template reemplaza el contenido de Body
         */
        if (isset($this->template))
        {
            if (!empty($this->variables))
            {
                extract($this->variables);
            }

            ob_start();
            include $pathMails . $this->template;

            $this->setBody(ob_get_clean());
        }

        if (isset($this->Host) === false || isset($this->Port) === false || isset($this->Username) === false)
        {
            trigger_error('Imposible enviar el email sin antes establecer la configuración necesaria', E_USER_ERROR);
        }
        elseif ($this->mailer->Send() === false)
        {
            $info = array(
                'FROM'     => $this->From,
                'TO'       => $this->mailer->getToAddresses(),
                'BCC'      => $this->mailer->getBccAddresses(),
                'CC'       => $this->mailer->getCcAddresses(),
                'REPLY-TO' => $this->mailer->getReplyToAddresses(),
                'SUBJECT'  => $this->Subject,
                'BODY'     => $this->Body,
                'SMTP'     => $this->mailer->getSMTPInstance(),
            );

            if (isset($this->ErrorInfo) !== false)
            {
                $info['ERROR-INFO'] = $this->ErrorInfo;
            }

            $message = sprintf('Ha sido imposible enviar el email con PHPMailer%s%s', PHP_EOL, print_r($info, true));
            trigger_error($message, E_USER_WARNING);
        }
    }

    /**
     * Obtiene la configuración de emails con fallback a default
     *
     * @param string $config    Nombre de la configuración a obtener
     * @param string $parameter Parámetro de la configuración a obtener
     *
     * @return null
     */
    private function getConfig($config = 'default', $parameter = '')
    {
        $result = null;

        if (isset(Config::getConfig()->mail[$config][$parameter]))
        {
            $result = Config::getConfig()->mail[$config][$parameter];
        }
        elseif (isset(Config::getConfig()->mail['default'][$parameter]))
        {
            $result = Config::getConfig()->mail['default'][$parameter];
        }
        else
        {
            trigger_error(sprintf('No existe configuración emails para %s => %s', $config, $parameter), E_USER_ERROR);
        }

        return $result;
    }
}
