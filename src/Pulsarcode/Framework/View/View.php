<?php

namespace Pulsarcode\Framework\View;

use Pulsarcode\Framework\Config\Config;
use Pulsarcode\Framework\Core\Core;
use Pulsarcode\Framework\Error\Error;
use Twig_Environment;
use Twig_Loader_Filesystem;

/**
 * Class View Para gestionar las vistas
 *
 * @package Pulsarcode\Framework\View
 */
class View extends Core
{
    /**
     * @var null Controlador de la petición
     */
    private $controller = null;

    /**
     * @var null Acción de la petición
     */
    private $action = null;

    /**
     * @var array Argumentos de la petición
     */
    private $args = array();

    /**
     * @var string Formato de la petición
     */
    private $format = 'html';

    /**
     * @var Twig_Environment Motor Twig
     */
    private $twig;

    /**
     * @var null Vista para pintar
     */
    private $template = null;

    /**
     * @var array Variables de la vista
     */
    private $variables = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $debug      = (in_array(Config::getConfig()->environment, array('loc', 'des')));
        $loader     = new Twig_Loader_Filesystem(Config::getConfig()->paths['views']['web']);
        $options    = array(
            'debug'            => $debug, // Debug sólo en local y desarrollo
            'strict_variables' => $debug, // Petes de variables ocultos en producción
            'cache'            => Config::getConfig()->paths['cache'] . DIRECTORY_SEPARATOR . 'Twig',
        );
        $this->twig = new Twig_Environment($loader, $options);
    }

    /**
     * Establece una variable para la template
     *
     * @param string $variable Nombre de la variable
     * @param mixed  $value    Valor de la variable
     *
     * @return $this
     */
    public function __set($variable, $value)
    {
        $this->variables[$variable] = $value;
    }

    /**
     * Obtiene una variable de la template o un método de la vista
     *
     * @param string $variable Nombre de la variable
     *
     * @return mixed
     */
    public function __get($variable)
    {
        if (isset($this->variables[$variable]) === false)
        {
            trigger_error('Imposible acceder al campo "' . $variable . '"', E_USER_WARNING);
        }

        $field = $this->variables[$variable];

        return ($field instanceof \Closure) ? $field($this) : $field;
    }

    /**
     * Comprueba si la variable está seteada o es distinta a null
     *
     * @param string $variable Nombre de la variable
     *
     * @return bool
     */
    public function __isset($variable)
    {
        return isset($this->variables[$variable]);
    }

    /**
     * Unset variable value
     *
     * @param string $variable Nombre de la variable
     */
    public function __unset($variable)
    {
        if (isset($this->variables[$variable]) === false)
        {
            trigger_error('Imposible borrar el campo "' . $variable . '"', E_USER_WARNING);
        }

        unset($this->variables[$variable]);
    }

    /**
     * Establece la template para la vista
     *
     * @param string $template Archivo template para la vista
     */
    public function setTemplate($template)
    {
        $pathViews = Config::getConfig()->paths['views']['web'] . DIRECTORY_SEPARATOR;

        if (isset($this->controller))
        {
            $template = substr($this->controller, 0, -10) . DIRECTORY_SEPARATOR . $template;
        }

        if (!is_file($pathViews . $template) || !is_readable($pathViews . $template))
        {
            trigger_error('La template ' . $pathViews . $template . ' no existe o no tengo acceso', E_USER_ERROR);
        }

        $this->template = $template;
    }

    /**
     * Obtiene la template para la vista
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param null $controller
     */
    public function setController($controller)
    {
        $this->controller = $controller;
    }

    /**
     * @return null
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * @param null $action
     */
    public function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * @return null
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param array $args
     */
    public function setArgs($args)
    {
        $this->args = $args;
    }

    /**
     * @return array
     */
    public function getArgs()
    {
        return $this->args;
    }

    /**
     * @param string $format
     */
    public function setFormat($format)
    {
        $this->format = $format;
    }

    /**
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
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
            $this->$name = $value;
        }
    }

    /**
     * Obtiene una template
     *
     * @return string
     */
    public function fetch()
    {
        $pathViews = Config::getConfig()->paths['views']['web'] . DIRECTORY_SEPARATOR;

        if (isset($this->template) === false)
        {
            trigger_error('La template para la vista no se ha establecido', E_USER_ERROR);
        }
        elseif (!is_file($pathViews . $this->template) || !is_readable($pathViews . $this->template))
        {
            trigger_error('La template ' . $pathViews . $this->template . ' no existe o no tengo acceso', E_USER_ERROR);
        }

        list($template, $type, $engine) = explode('.', basename($this->template));

        switch ($engine)
        {
            case 'php':
                if (!empty($this->variables))
                {
                    extract($this->variables);
                }

                ob_start();
                require $pathViews . $this->template;

                return ob_get_clean();

            case 'twig':
                return $this->twig->render($this->template, $this->variables);

            default:
                trigger_error('Motor no soportado (' . $engine . ' -> ' . $this->template . ')', E_USER_ERROR);
        }
    }

    /**
     * Renderiza una template
     */
    public function display()
    {
        $this->checkHeaders('VIEW_INVALID_DISPLAY_' . strtoupper($this->format), __FILE__, __LINE__);

        switch ($this->format)
        {
            case 'html':
            case 'xml':
                header('Content-type: text/' . $this->format, '; charset=utf-8');
                break;

            case 'json':
                header('Content-type: application/json; charset=utf-8');
                $this->displayJSON();

                return;

            case 'rss':
                header('Content-type: application/rss+xml; charset=utf-8');
                break;

            default:
                trigger_error('No reconozco el formato ' . $this->format . ', no sé como procesarlo', E_USER_ERROR);
                break;
        }

        $content = $this->fetch();

        /**
         * TODO: Refactorizar esta guarrería, se hace deprisa y corriendo por la subida
         */
        $controller = substr(strtolower($this->controller), 0, -10);

        if (file_exists($js = sprintf('%s/js/%s.js', Config::getConfig()->paths['public'], $controller)))
        {
            $script  = sprintf('<script type="text/javascript">%s</script>', file_get_contents($js));
            $content = str_replace('</body>', "$script</body>", $content);
        }

        echo $content;
    }

    /**
     * Renderiza la respuesta en JSON
     */
    private function displayJSON()
    {
        /**
         * Cabeceras para hacer felíz a Internet Exploter
         */
        header('X-Content-Type-Options: nosniff');

        $defaultFields = array(
            'success' => true,
            'message' => '',
        );
        $json          = array();

        foreach ($defaultFields as $defaultName => $defaultValue)
        {
            if (isset($this->$defaultName))
            {
                $json[$defaultName] = $this->$defaultName;
                unset($this->$defaultName);
            }
            else
            {
                $json[$defaultName] = $defaultValue;
            }
        }

        if (!empty($this->variables))
        {
            $json['data'] = $this->variables;
        }

        echo json_encode($json);
    }

    /**
     * Función para comprobar que no se han enviado las cabeceras
     *
     * @param string $errorLevel Nivel del error a lanzar si falla
     * @param string $errorFile  Archivo que llamo la comprobación
     * @param int    $errorLine  Línea que llamo la comprobación
     */
    private function checkHeaders($errorLevel = 'UNKNOWN_ERROR', $errorFile = __FILE__, $errorLine = __LINE__)
    {
        if (headers_sent($file, $line) !== false)
        {
            $errorData = array(
                'errorLevel'   => $errorLevel,
                'errorMessage' => 'Imposible continuar, se han enviado cabeceras desde ' . $file . ':' . $line,
                'errorFile'    => $errorFile,
                'errorLine'    => $errorLine,
            );
            Error::setError('500', $errorData);
        }
    }
}
