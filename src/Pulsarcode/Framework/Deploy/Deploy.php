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
     * Patrones de configuración
     */
    const COMMIT_URL_PATTERN = '<a href="http://git.autocasion.com/autocasion/%s/commit/%2$s">%2$s</a> %3$s';

    /**
     * Patrón para comandos de Git
     */
    const GIT_DESCRIBE_PATTERN = 'git describe --abbrev=0 --tags %s';
    const GIT_DIFF_PATTERN     = 'git diff --stat %s %s';
    const GIT_FETCH_PATTERN    = 'git fetch --progress --prune origin';
    const GIT_LOG_PATTERN      = 'git log --pretty=format:"%%H (%%an) %%s" %s...%s';
    const GIT_STATUS_PATTERN   = 'git status';

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
        $repositoryPath = '/var/www/html';

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
        elseif (Core::run(self::GIT_FETCH_PATTERN) === false)
        {
            echo 'Unable to fetch Git data from repository';
            exit(1);
        }
        elseif (Core::run(sprintf(self::GIT_DESCRIBE_PATTERN, 'origin/master'), $lastRepoTag) === false)
        {
            echo 'Unable to get last tag of origin/master';
            exit(1);
        }
        elseif (Core::run(sprintf(self::GIT_DESCRIBE_PATTERN, ''), $prevRepoTag) === false)
        {
            echo 'Unable to get prev tag for last tag of origin/master';
            exit(1);
        }
        elseif (Core::run(sprintf(self::GIT_LOG_PATTERN, $prevRepoTag[0], $lastRepoTag[0]), $repoCommits) === false)
        {
            printf('Unable to get log details from tag %s to tag %s', current($prevRepoTag), current($lastRepoTag));
            exit(1);
        }
        elseif (Core::run(sprintf(self::GIT_DIFF_PATTERN, $prevRepoTag[0], $lastRepoTag[0]), $repoStats) === false)
        {
            printf('Unable to get diff details from tag %s to tag %s', current($prevRepoTag), current($lastRepoTag));
            exit(1);
        }
        elseif (Core::run(self::GIT_STATUS_PATTERN, $repoStatus) === false)
        {
            echo 'Unable to get Git status of repository';
            exit(1);
        }
        elseif (Core::run('cd includes') === false)
        {
            echo 'Unable to SYS chdir to repository submodule: includes';
            exit(1);
        }
        elseif (chdir('includes') === false)
        {
            echo 'Unable to PHP chdir to repository submodule: includes';
            exit(1);
        }
        elseif (Core::run('cat ../CURRENT_SUBMODULE_TAG', $lastSubmoTag) === false)
        {
            echo 'Unable to get current submodule tag';
            exit(1);
        }
        elseif (Core::run(sprintf(self::GIT_DESCRIBE_PATTERN, $lastSubmoTag[0] . '^'), $prevSubmoTag) === false)
        {
            echo 'Unable to get prev tag for current submodule tag';
            exit(1);
        }
        elseif (Core::run(sprintf(self::GIT_LOG_PATTERN, $prevSubmoTag[0], $lastSubmoTag[0]), $submoCommits) === false)
        {
            printf('Unable to get log details from tag %s to tag %s', current($prevSubmoTag), current($lastSubmoTag));
            exit(1);
        }
        elseif (Core::run(sprintf(self::GIT_DIFF_PATTERN, $prevSubmoTag[0], $lastSubmoTag[0]), $submoStats) === false)
        {
            printf('Unable to get diff details from tag %s to tag %s', current($prevSubmoTag), current($lastSubmoTag));
            exit(1);
        }
        elseif (Core::run(self::GIT_STATUS_PATTERN, $submoStatus) === false)
        {
            echo 'Unable to get Git status of submodule';
            exit(1);
        }

        $message = '
            <h1>Repositorio autocasion</h1>
            <h3>Listado de commits aplicados en el repositorio del tag desplegado %s:</h3>
            <pre>%s</pre>
            <h3>Archivos afectados por estos commits:</h3>
            <pre>%s</pre>
            <h3>Estado actual del repositorio:</h3>
            <pre>%s</pre>

            <hr />

            <h1>Submódulo includes</h1>
            <h3>Listado de commits aplicados en el submódulo del tag desplegado %s:</h3>
            <pre>%s</pre>
            <h3>Archivos afectados por estos commits:</h3>
            <pre>%s</pre>
            <h3>Estado actual del submódulo:</h3>
            <pre>%s</pre>
        ';

        $totalCommits = array($repoCommits, $submoCommits);

        foreach ($totalCommits as &$commits)
        {
            $repository = (false === isset($repository)) ? 'autocasion_legacy' : 'includes';

            foreach ($commits as &$commit)
            {
                list($commitHash, $commitMessage) = explode(' ', $commit, 2);
                $commit = sprintf(self::COMMIT_URL_PATTERN, $repository, $commitHash, $commitMessage);
            }
        }

        list($repoCommits, $submoCommits) = $totalCommits;

        $message = sprintf(
            $message,
            current($lastRepoTag),
            implode(PHP_EOL, $repoCommits),
            implode(PHP_EOL, $repoStats),
            implode(PHP_EOL, $repoStatus),
            current($lastSubmoTag),
            implode(PHP_EOL, $submoCommits),
            implode(PHP_EOL, $submoStats),
            implode(PHP_EOL, $submoStatus)
        );
        $mailer  = new Mail();
        $mailer->initConfig('autobot');
        $mailer->AddAddress(Config::getConfig()->debug['mail']);
        $mailer->setSubject(
            sprintf(
                '[DEPLOYACO] (%s) [%s] %s (%s) %s',
                $environment,
                $ip,
                current($lastRepoTag),
                current($lastSubmoTag),
                $host
            )
        );
        $mailer->setBody($message);
        $mailer->send();

        /**
         * TODO: Hacer una función para este tipo de notificaciones mas amigable y menos fea
         */
        $notification = sprintf(
            '[DEPLOYACO] (%s) [%s] %s (%s) %s',
            $environment,
            $ip,
            current($lastRepoTag),
            current($lastSubmoTag),
            $host
        );
        exec(
            sprintf(
                'curl -X POST --data-urlencode \'payload={"text": "%s"}\' https://hooks.slack.com/services/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',
                $notification
            )
        );
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
