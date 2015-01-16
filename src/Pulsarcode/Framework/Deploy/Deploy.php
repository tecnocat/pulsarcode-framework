<?php

namespace Pulsarcode\Framework\Deploy;

use Pulsarcode\Framework\Config\Config;
use Pulsarcode\Framework\Core\Core;
use Pulsarcode\Framework\Mail\Mail;

/**
 * Class Deploy Para gestionar los deploys
 *
 * @package Pulsarcode\Framework\Deploy
 */
class Deploy extends Core
{
    /**
     * Patrón para mover archivo temporal
     */
    const MV_PATTERN = 'ssh -t %s "mv -v %s.tmp %s"';

    /**
     * Patrón para eliminar archivo temporal
     */
    const RM_PATTERN = 'ssh -t %s "rm -v %s.tmp"';

    /**
     * Patrón para subir archivo temporal
     */
    const SCP_PATTERN = 'scp -v %s %s:%s.tmp';

    /**
     * Envía un email con la información del deploy que se acaba de realizar
     *
     * @param string $ip   IP de la máquina en la que se realizó el deploy
     * @param string $host Nombre del host de la máquina en la que se realizó el deploy
     */
    public static function sendMail($ip, $host)
    {
        $environment    = Config::getConfig()->environment;
        $repositoryPath = __DIR__ . '/../../../../';

        if (Core::run(sprintf('cd %s', $repositoryPath)) === false)
        {
            echo 'Unable to SYS chdir to repository path: ' . $repositoryPath;
            exit(1);
        }
        elseif (chdir($repositoryPath) === false)
        {
            echo 'Unable to PHP chdir to repository path: ' . $repositoryPath;
            exit(1);
        }

        exec(sprintf('tail -1 /var/www/vhosts/%s.autocasion.com/autocasion/revisions.log', $environment), $title);
        exec('git describe --abbrev=0 --tags origin/master', $lastRepositoryTag);
        exec('git describe --abbrev=0 --tags origin/master^', $prevRepositoryTag);
        $lastSubmoduleTag = file_get_contents(sprintf(__DIR__ . '/../../../../CURRENT_SUBMODULE_TAG', $environment));
        exec(sprintf('cd includes && git describe --abbrev=0 --tags %s^', $lastSubmoduleTag), $prevSubmoduleTag);
        exec(
            sprintf('git log --pretty=oneline %s...%s', current($prevRepositoryTag), current($lastRepositoryTag)),
            $repositoryDetails
        );
        exec(
            sprintf('git log --pretty=oneline %s...%s', current($prevSubmoduleTag), $lastSubmoduleTag),
            $submoduleDetails
        );
        $message = '
            <h4>Se ha lanzado un deployaco a %s</h4>
            <hr />

            <p>Listado de cambios en el repositorio del tag desplegado %s:</p>
            <ol><li>%s</li></ol>

            <p>Listado de cambios en el submódulo del tag desplegado %s:</p>
            <ol><li>%s</li></ol>
        ';
        $message = sprintf(
            $message,
            strtoupper($environment),
            current($lastRepositoryTag),
            implode('</li><li>', $repositoryDetails),
            $lastSubmoduleTag,
            implode('</li><li>', $submoduleDetails)
        );
        $mailer  = new Mail();
        $mailer->initConfig('autobot');
        $mailer->AddAddress('alpha@pulsarcode.com');// Config::getConfig()->debug['mail']);
        $mailer->setSubject(
            sprintf(
                '[DEPLOYACO] (%s) [%s] %s (%s) %s',
                $environment,
                $ip,
                current($lastRepositoryTag),
                $lastSubmoduleTag,
                $host
            )
        );
        $mailer->setBody(sprintf('<h4>%s</h4><hr /><pre>%s</pre>', current($title), $message));
        $mailer->send();
    }

    /**
     * Sube uno o varios archivos a los roles especificados
     *
     * @param array $files Archivos para subir a los servidores
     * @param array $roles Servidores a los que subir los archivos
     */
    public static function uploadToServerRoles(array $files = array(), array $roles = array())
    {
        foreach ($roles as $role)
        {
            if (isset(Config::getConfig()->deploy['roles'][$role]) !== false)
            {
                $servers = explode('|', Config::getConfig()->deploy['roles'][$role]['host']);

                foreach ($servers as $server)
                {
                    foreach ($files as $file)
                    {
                        if (file_exists($file) !== false)
                        {
                            $realPath = realpath($file);
                            $rolePath = static::getPathOnRole($file, $role);

                            if (Core::run(sprintf(static::SCP_PATTERN, $realPath, $server, $rolePath)) === false)
                            {
                                /**
                                 * TODO: Reemplazar por la clase Log para pintar correctamente
                                 */
                                echo 'Error al subir el archivo ' . $realPath . ' a ' . $server . ':' . $rolePath;
                            }
                            elseif (Core::run(sprintf(static::MV_PATTERN, $server, $rolePath, $rolePath)) === false)
                            {
                                /**
                                 * TODO: Reemplazar por la clase Log para pintar correctamente
                                 */
                                echo 'Error al actualizar el archivo ' . $realPath . ' a ' . $server . ':' . $rolePath;

                                if (Core::run(sprintf(static::RM_PATTERN, $server, $rolePath)) === false)
                                {
                                    /**
                                     * TODO: Reemplazar por la clase Log para pintar correctamente
                                     */
                                    echo 'Error al borrar archivo temporal ' . $server . ':' . $rolePath . '.tmp';
                                }
                                else
                                {
                                    /**
                                     * TODO: Reemplazar por la clase Log para pintar correctamente
                                     */
                                    echo 'Archivo temporal ' . $server . ':' . $rolePath . '.tmp borrado';
                                }
                            }
                            else
                            {
                                /**
                                 * TODO: Reemplazar por la clase Log para pintar correctamente
                                 */
                                echo 'Archivo ' . $realPath . ' subido a ' . $server . ':' . $rolePath;
                            }
                        }
                        else
                        {
                            trigger_error('El archivo "' . $file . '" no existe.', E_USER_ERROR);
                        }
                    }
                }
            }
            else
            {
                trigger_error('El rol de servidores "' . $role . '" no existe en la configuración.', E_USER_ERROR);
            }
        }
    }

    /**
     * Devuelve el path correcto para el servidor según su configuración
     *
     * @param string $path Ruta relativa a ransformar
     * @param string $role Rol de servidor
     *
     * @return string Ruta absoluta en el servidor basada en el rol
     */
    private static function getPathOnRole($path, $role)
    {
        $result = '';

        if (isset(Config::getConfig()->deploy['roles'][$role]['root']) === false)
        {
            trigger_error('El rol de servidores "' . $role . '" no tiene root path en la configuración.', E_USER_ERROR);
        }
        elseif (realpath($path) === false)
        {
            trigger_error('El path especificado no es válido: ' . $path, E_USER_ERROR);
        }
        else
        {
            $relativePath = str_replace(Config::getConfig()->paths['root'] . DIRECTORY_SEPARATOR, '', realpath($path));

            /**
             * TODO: Reemplazo temporal hasta la integración de una feature en develop
             */
            if ($role == 'web')
            {
                $relativePath = str_replace('submodule-includes', 'includes', $relativePath);
            }

            $result = Config::getConfig()->deploy['roles'][$role]['root'] . DIRECTORY_SEPARATOR . $relativePath;
        }

        return $result;
    }
}
