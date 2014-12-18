<?php

namespace Pulsarcode\Framework\Controller;

use Pulsarcode\Framework\Cache\Cache;
use Pulsarcode\Framework\Config\Config;
use Pulsarcode\Framework\Core\Core;
use Pulsarcode\Framework\Database\Database;
use Pulsarcode\Framework\Log\Log;
use Pulsarcode\Framework\Mail\Mail;
use Pulsarcode\Framework\Router\Router;
use Pulsarcode\Framework\View\View;
use Symfony\Component\HttpFoundation\ParameterBag as Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Class Controller Para gestionar los controladores
 *
 * @package Pulsarcode\Framework\Controller
 */
class Controller extends Core
{
    /**
     * @var Config Configuración de la aplicación
     */
    protected $config;

    /**
     * @var Request Petición actual
     */
    protected $request;

    /**
     * @var Session Sesión actual
     */
    protected $session;

    /**
     * @var Cookie Cookies actuales
     */
    protected $cookie;

    /**
     * @var Cache Para gestionar la caché
     */
    protected $cache;

    /**
     * @var Database Para gestionar la base de datos
     */
    protected $database;

    /**
     * @var Log Para gestionar los logs
     */
    protected $log;

    /**
     * @var Mail Para gestionar los emails
     */
    protected $mail;

    /**
     * @var View Para gestionar las vistas
     */
    protected $view;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->config   = Config::getConfig();
        $this->request  = Router::getRequest();
        $this->session  = Router::getRequest()->getSession();
        $this->cookie   = Router::getRequest()->cookies;
        $this->cache    = new Cache();
        $this->database = new Database();
        $this->log      = new Log();
        $this->mail     = new Mail();
        $this->view     = new View();
    }

    /**
     * Procesa la petición y muestra su salida en el formato adecuado
     *
     * @param array $match Datos procesados por el Router
     */
    public function dispatch(array $match = array())
    {
        list($controller, $action) = explode('::', $match['controller']);

        $this->view->setController($controller . 'Controller');
        $this->view->setAction($action . 'Action');
        $this->view->setArgs(array_diff_key($match, Router::$internalValues));

        if (isset($match['template']) !== false)
        {
            $this->view->setTemplate($match['template']);
        }
        elseif ($this->request->getRequestFormat() !== 'json')
        {
            $this->view->setTemplate(sprintf('%s-layout.%s.twig', $action, $this->request->getRequestFormat()));
        }

        $this->view->setFormat($this->request->getRequestFormat());
        $this->view->setupForms();
        $this->callMethod($this->view->getAction(), $this->view->getArgs());
        $this->view->display();
    }

    /**
     * Obtiene un parámetro SERVER si existe
     *
     * @param string $string  Nombre del parámetro
     * @param null   $default Valor por defecto si no existe
     *
     * @return mixed
     */
    protected function getServer($string, $default = null)
    {
        return $this->request->server->get($string, $default);
    }

    /**
     * Obtiene un parámetro GET si existe
     *
     * @param string $string  Nombre del parámetro
     * @param null   $default Valor por defecto si no existe
     *
     * @return mixed
     */
    protected function getQuery($string, $default = null)
    {
        return $this->request->query->get($string, $default);
    }

    /**
     * Obtiene un parámetro POST si existe
     *
     * @param string $string  Nombre del parámetro
     * @param null   $default Valor por defecto si no existe
     *
     * @return mixed
     */
    protected function getRequest($string, $default = null)
    {
        return $this->request->request->get($string, $default);
    }

    /**
     * Obtiene un parámetro FILES si existe
     *
     * @param string $string  Nombre del parámetro
     * @param null   $default Valor por defecto si no existe
     *
     * @return mixed
     */
    protected function getFiles($string, $default = null)
    {
        return $this->request->files->get($string, $default);
    }

    /**
     * Obtiene un parámetro COOKIE si existe
     *
     * @param string $string  Nombre del parámetro
     * @param null   $default Valor por defecto si no existe
     *
     * @return mixed
     */
    protected function getCookie($string, $default = null)
    {
        return $this->request->cookies->get($string, $default);
    }

    /**
     * Obtiene un parámetro SESSION si existe
     *
     * @param string $string  Nombre del parámetro
     * @param null   $default Valor por defecto si no existe
     *
     * @return mixed
     */
    protected function getSession($string, $default = null)
    {
        return $this->request->getSession()->get($string, $default);
    }

    /**
     * Función para llamar al método del controlador instanciado
     *
     * @param string $method Método a invocar
     * @param array  $args   Argumentos a pasar al método
     */
    private function callMethod($method, $args)
    {
        if (is_callable(array($this, $method)) === false)
        {
            if (method_exists($this, $method))
            {
                $errorMessage = sprintf('El método "%s" del controlador "%s" es privado', $method, get_class($this));
            }
            else
            {
                $errorMessage = sprintf('El método "%s" del controlador "%s" no existe', $method, get_class($this));
            }

            trigger_error($errorMessage, E_USER_ERROR);
        }
        else
        {
            call_user_func_array(array($this, $method), $args);
        }
    }
}
