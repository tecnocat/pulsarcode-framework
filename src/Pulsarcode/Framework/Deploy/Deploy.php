<?php

namespace Pulsarcode\Framework\Deploy;

use Pulsarcode\Framework\Config\Config;
use Pulsarcode\Framework\Core\Core;

/**
 * Class Deploy Para gestionar los deploys
 *
 * @package Pulsarcode\Framework\Deploy
 */
class Deploy extends Core
{
    /**
     * Patrón para subir archivo temporal
     */
    const SCP_PATTERN = 'scp -v %s %s:%s.tmp';

    /**
     * Patrón para mover archivo temporal
     */
    const MV_PATTERN = 'ssh -t %s "mv -v %s.tmp %s"';

    /**
     * Patrón para eliminar archivo temporal
     */
    const RM_PATTERN = 'ssh -t %s "rm -v %s.tmp"';

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
