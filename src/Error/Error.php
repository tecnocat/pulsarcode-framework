<?php

namespace Pulsarcode\Framework\Error;

use Pulsarcode\Framework\Config\Config;
use Pulsarcode\Framework\Mail\Mail;
use Pulsarcode\Framework\Router\Router;

/**
 * Class Error Para gestionar los errores
 *
 * @package Pulsarcode\Framework\Error
 */
class Error
{
    /**
     * Archivo de log para errores
     */
    const ERROR_LOG_FILE = 'php_error.log';

    /**
     * Límite de tamaño para el log de errores (5MB)
     */
    const ERROR_LOG_SIZE = 5242880;

    /**
     * @var array Niveles de error transformados a su nombre
     */
    public static $errorLevel = array(
        E_ERROR             => 'E_ERROR',
        E_WARNING           => 'E_WARNING',
        E_PARSE             => 'E_PARSE',
        E_NOTICE            => 'E_NOTICE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_CORE_WARNING      => 'E_CORE_WARNING',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_STRICT            => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
    );

    /**
     * @var array Errores por los que la aplicación lanzará un 500
     */
    public static $errorHalts = array(
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
        E_RECOVERABLE_ERROR,
    );

    /**
     * @var array Colores para según que tipo de error
     */
    public static $errorColors = array(
        'success' => '#5cb85c',
        'info'    => '#5bc0de',
        'warning' => '#f0ad4e',
        'error'   => '#d9534f',
    );

    /**
     * @var Error Instancia estática de sí misma
     */
    private static $instance;

    /**
     * @var array Errores para registrar o mostrar
     */
    private static $errors = array();

    /**
     * @var array Entornos permitidos para mostrar / guardar los errores
     */
    private static $allowedEnvironments = array('loc', 'des');

    /**
     * @var bool Control para los capturadores de errores
     */
    private static $dispatched;

    /**
     * Configuración para capturar todos los tipos de errores
     */
    public static function setupErrorHandler()
    {
        if (isset(self::$dispatched) === false)
        {
            /**
             * Configuración obligatoria necesaria
             */
            ini_set('error_reporting', false);
            ini_set('display_errors', false);
            ini_set('display_startup_errors', false);

            self::setShutdownHandler();
            self::setErrorHandler();
            self::setExceptionHandler();
            self::$dispatched = true;
        }
    }

    /**
     * Necesitamos poner a peluni el error handler para captar todos los petates
     */
    private static function setErrorHandler()
    {
        set_error_handler(
            function ($errorLevel, $errorMessage, $errorFile, $errorLine)
            {
                $errorType = (in_array($errorLevel, Error::$errorHalts)) ? '500' : 'PHP';
                $errorData = array(
                    'errorLevel'   => $errorLevel,
                    'errorMessage' => $errorMessage,
                    'errorFile'    => $errorFile,
                    'errorLine'    => $errorLine,
                );
                Error::setError($errorType, $errorData);
            }
        );
    }

    /**
     * Ponemos el exception handler también a peluni para captar todas las excepciones
     */
    private static function setExceptionHandler()
    {
        set_exception_handler(
            function (\Exception $exception)
            {
                $errorData = array(
                    'errorLevel'   => ($exception->getCode()) ? $exception->getCode() : 'E_EXCEPTION',
                    'errorMessage' => $exception->getMessage(),
                    'errorFile'    => $exception->getFile(),
                    'errorLine'    => $exception->getLine(),
                );
                Error::setError('500', $errorData);
            }
        );
    }

    /**
     * Seteamos la función de shutdown para capturar todos los errores
     */
    private static function setShutdownHandler()
    {
        register_shutdown_function(
            function ()
            {
                $lastError = error_get_last();

                if (isset($lastError) && in_array($lastError['type'], Error::$errorHalts))
                {
                    $errorData = array(
                        'errorLevel'   => $lastError['type'],
                        'errorMessage' => $lastError['message'],
                        'errorFile'    => $lastError['file'],
                        'errorLine'    => $lastError['line'],
                    );
                    Error::setError('500', $errorData);
                }
                else
                {
                    Error::parseErrors();
                }
            }
        );
    }

    /**
     * Crea un nuevo error del tipo dado
     *
     * @param string $errorType Tipo de error
     * @param array  $errorData Datos del error
     */
    public static function setError($errorType = '', array $errorData = array())
    {
        if (Config::getConfig()->error_reporting['show'] || Config::getConfig()->error_reporting['write'])
        {
            $errorLevel = $errorData['errorLevel'];
            $isErrorPHP = (isset(Error::$errorLevel[$errorLevel]) !== false);

            if ($isErrorPHP)
            {
                $errorLevel = Error::$errorLevel[$errorLevel];
            }

            /**
             * Por omisión si no tenemos configurado el nivel de error no reportamos dicho nivel
             */
            if ($isErrorPHP && isset(Config::getConfig()->error_reporting[$errorLevel]) === false)
            {
                return;
            }
            /**
             * Si por el contrario lo tenemos puesto a false tampoco reportamos
             */
            elseif ($isErrorPHP && Config::getConfig()->error_reporting[$errorLevel] === false)
            {
                return;
            }
            /**
             * En caso contrario reportamos dicho nivel
             */
            else
            {
                /**
                 * Evitamos mostrar los mismos errores
                 */
                $uniqid = md5($errorData['errorFile'] . $errorData['errorLine'] . $errorData['errorMessage']);

                self::$errors[$errorLevel][$uniqid] = $errorData;
            }
        }

        if ($errorType == '403')
        {
            self::throw403();
        }
        elseif ($errorType == '404')
        {
            self::throw404();
        }
        elseif ($errorType == '405')
        {
            self::throw405();
        }
        elseif ($errorType == '500')
        {
            self::throw500($errorData);
        }
    }

    /**
     * Envía un correo con información del petate
     *
     * @param string $subject Asunto del email
     * @param string $body    Cuerpo del email
     * @param null   $toCopy  Dirección en copia
     */
    public static function mail($subject, $body = '', $toCopy = null)
    {
        $environment = Config::getConfig()->environment;
        $ip          = Router::getRequest()->server->get('SERVER_ADDR');
        $host        = Router::getRequest()->getHttpHost();
        $uri         = Router::getRequest()->getRequestUri();
        $info        = print_r(
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

        /**
         * Los errores 500 se envian siempre por mail en pre y pro, en loc y des sólo si lo tenemos activado
         */
        if (Config::getConfig()->error_reporting['send'] || !in_array($environment, Error::$allowedEnvironments))
        {
            /**
             * Enviamos los emilios en background like a boss
             */
            register_shutdown_function(
                function () use ($subject, $body, $toCopy, $environment, $ip, $host, $uri, $info)
                {
                    $mailer = new Mail();
                    $mailer->initConfig('autobot');
                    $mailer->AddAddress(Config::getConfig()->error_reporting['email']);

                    if (isset($toCopy) !== false)
                    {
                        $mailer->AddAddress($toCopy, $toCopy);
                    }

                    $mailer->setSubject(sprintf('[ERRORACO] (%s) [%s] %s - %s', $environment, $ip, $host, $uri));
                    $mailer->setBody(sprintf('<h4>%s</h4><hr />%s<hr /><pre>%s</pre>', $subject, $body, $info));
                    $mailer->Send();
                }
            );
        }
    }

    /**
     * Lanza un 403 para el usuario
     *
     * TODO: refactorizar para no repetir código y usar Request y View
     */
    private static function throw403()
    {
        if (headers_sent() === false && php_sapi_name() !== 'cli')
        {
            header('HTTP/1.1 403 Forbidden');
        }

        /**
         * TODO: Refactorizar esto para usar View::getFormat();
         */
        if (strpos(Router::getRequest()->getPathInfo(), '.json') !== false)
        {
            echo json_encode(array('success' => false, 'message' => 'Forbidden'));
        }
        else
        {
            include_once Config::getConfig()->paths['views'] . '/404.html.php';
        }

        exit;
    }

    /**
     * Lanza un 404 para el usuario
     *
     * TODO: refactorizar para no repetir código y usar Request y View
     */
    private static function throw404()
    {
        if (headers_sent() === false && php_sapi_name() !== 'cli')
        {
            header('HTTP/1.1 404 Not Found');
        }

        /**
         * TODO: Refactorizar esto para usar View::getFormat();
         */
        if (strpos(Router::getRequest()->getPathInfo(), '.json') !== false)
        {
            echo json_encode(array('success' => false, 'message' => 'Not Found'));
        }
        else
        {
            include_once Config::getConfig()->paths['views'] . '/404.html.php';
        }

        exit;
    }

    /**
     * Lanza un 405 para el usuario
     *
     * TODO: refactorizar para no repetir código y usar Request y View
     */
    private static function throw405()
    {
        if (headers_sent() === false && php_sapi_name() !== 'cli')
        {
            header('HTTP/1.1 405 Method Not Allowed');
        }

        /**
         * TODO: Refactorizar esto para usar View::getFormat();
         */
        if (strpos(Router::getRequest()->getPathInfo(), '.json') !== false)
        {
            echo json_encode(array('success' => false, 'message' => 'Method Not Allowed'));
        }
        else
        {
            include_once Config::getConfig()->paths['views'] . '/404.html.php';
        }

        exit;
    }

    /**
     * Lanza un quini con información para el usuario o petate para desarrollo
     *
     * TODO: refactorizar para usar una función con las cabeceras
     *
     * @param array $errorData
     */
    private static function throw500(array $errorData = array())
    {
        if (headers_sent() === false && php_sapi_name() !== 'cli')
        {
            header('HTTP/1.1 500 Internal Server Error');
        }

        if (isset(Error::$errorLevel[$errorData['errorLevel']]) !== false)
        {
            $errorData['errorLevel'] = Error::$errorLevel[$errorData['errorLevel']];
        }

        $environment = Config::getConfig()->environment;
        $ip          = Router::getRequest()->server->get('SERVER_ADDR');
        $host        = Router::getRequest()->getHttpHost();
        $uri         = Router::getRequest()->getRequestUri();
        $info        = print_r(
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
        $message     = sprintf(
            '[%s] %s:%s %s',
            $errorData['errorLevel'],
            $errorData['errorFile'],
            $errorData['errorLine'],
            $errorData['errorMessage']
        );

        $mailer = new Mail();
        $mailer->initConfig('autobot');
        $mailer->AddAddress(Config::getConfig()->error_reporting['email']);
        $mailer->setSubject(sprintf('[ERRORACO] (%s) [%s] %s - %s', $environment, $ip, $host, $uri));
        $mailer->setBody(sprintf('<h4>%s</h4><hr /><pre>%s</pre>', $message, $info));
        $mailer->Send();

        if (php_sapi_name() === 'cli')
        {
            echo PHP_EOL . $message . PHP_EOL;
        }
        /**
         * TODO: Refactorizar esto para usar View::getFormat();
         */
        elseif (strpos(Router::getRequest()->getPathInfo(), '.json') !== false)
        {
            $response = array('success' => false, 'message' => 'Internal Server Error');

            if (in_array($environment, Error::$allowedEnvironments))
            {
                $response['error'] = $errorData;
            }

            echo json_encode($response);
        }
        elseif (in_array($environment, Error::$allowedEnvironments))
        {
            ob_start();

            if ($isXdebug = function_exists('xdebug_print_function_stack'))
            {
                xdebug_print_function_stack();
            }
            else
            {
                debug_print_backtrace();
            }

            $trace = ob_get_clean();

            include_once Config::getConfig()->paths['views'] . '/petate.html.php';
        }
        else
        {
            include_once Config::getConfig()->paths['views'] . '/404.html.php';
        }

        Error::parseErrors();
        exit;
    }

    /**
     * Procesa los errores y después los elimina
     */
    public static function parseErrors()
    {
        Error::showErrors();
        Error::writeErrors();
        self::$errors = array();
    }

    /**
     * Función para pintar los errores
     */
    private static function showErrors()
    {
        /**
         * No respondemos ante llamadas AJAX
         */
        if (Router::getRequest()->isXmlHttpRequest() === false && Config::getConfig()->error_reporting['show'])
        {
            if (!empty(self::$errors) && in_array(Config::getConfig()->environment, self::$allowedEnvironments))
            {
                /**
                 * TODO: Refactorizar esto para usar View::getFormat();
                 */
                if (strpos(Router::getRequest()->getPathInfo(), '.json') === false)
                {
                    foreach (self::$errors as $errorLevel => $errorList)
                    {
                        /**
                         * Si es por línea de comandos pintamos un resumen en texto plano
                         */
                        if (php_sapi_name() == 'cli')
                        {
                            foreach ($errorList as $errorData)
                            {
                                printf(
                                    '[%s] [%s] %s:%s %s %s',
                                    date('Y-m-d H:i:s'),
                                    $errorLevel,
                                    $errorData['errorFile'],
                                    $errorData['errorLine'],
                                    $errorData['errorMessage'],
                                    PHP_EOL
                                );
                            }
                        }
                        /**
                         * Si es por petición Web usamos una template
                         */
                        else
                        {
                            include Config::getConfig()->paths['views'] . '/error-table.html.php';
                        }
                    }
                }
            }
        }
    }

    /**
     * Función para grabar los errores en el log
     */
    private static function writeErrors()
    {
        if (Config::getConfig()->error_reporting['write'])
        {
            if (!empty(self::$errors) && in_array(Config::getConfig()->environment, self::$allowedEnvironments))
            {
                $errorLog = Config::getConfig()->paths['logs'] . DIRECTORY_SEPARATOR . self::ERROR_LOG_FILE;

                if (file_exists($errorLog) && filesize($errorLog) >= self::ERROR_LOG_SIZE)
                {
                    /**
                     * Guardamos al menos la última pila de errores pos si tuvieramos que consultarlos
                     */
                    rename($errorLog, $errorLog . '.old');
                }

                foreach (self::$errors as $errorLevel => $errorList)
                {
                    foreach ($errorList as $errorData)
                    {
                        error_log(
                            sprintf(
                                '[%s] [%s] %s:%s %s %s',
                                date('Y-m-d H:i:s'),
                                $errorLevel,
                                $errorData['errorFile'],
                                $errorData['errorLine'],
                                $errorData['errorMessage'],
                                PHP_EOL
                            ),
                            3,
                            $errorLog
                        );
                    }
                }
            }
        }
    }

    /**
     * Devuelve la instancia de sí misma en estático
     *
     * @return Error
     */
    public static function getInstance()
    {
        if (isset(self::$instance) === false)
        {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Devuelve un color para pintar la tabla de errores segun su importancia
     *
     * @param $errorLevel
     *
     * @return mixed
     */
    public static function getErrorColor($errorLevel)
    {
        switch ($errorLevel)
        {
            case 'E_ERROR':
            case 'E_PARSE':
            case 'E_CORE_ERROR':
            case 'E_COMPILE_ERROR':
            case 'E_USER_ERROR':
            case 'E_RECOVERABLE_ERROR':
                $color = self::$errorColors['error'];
                break;

            case 'E_WARNING':
            case 'E_CORE_WARNING':
            case 'E_COMPILE_WARNING':
            case 'E_USER_WARNING':
                $color = self::$errorColors['warning'];
                break;

            case 'E_NOTICE':
            case 'E_USER_NOTICE':
                $color = self::$errorColors['info'];
                break;

            case 'E_STRICT':
            case 'E_DEPRECATED':
            case 'E_USER_DEPRECATED':
                $color = self::$errorColors['success'];
                break;

            default:
                trigger_error('Error desconocido: ' . $errorLevel, E_USER_NOTICE);
                $color = self::$errorColors['warning'];
                break;
        }

        return $color;
    }
}