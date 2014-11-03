<?php

namespace Pulsarcode\Framework\Core;

use Pulsarcode\Framework\Cache\Cache;
use Pulsarcode\Framework\Error\Error;

/**
 * Class Core Para gestionar el Framework
 *
 * @package Pulsarcode\Framework\Core
 */
class Core
{
    /**
     * Constructor
     */
    public function __construct()
    {
        /**
         * Capturador de petates
         */
        Error::setupErrorHandler();

        /**
         * Capturador de cacheos
         */
        Cache::setupCacheObjects();
    }

    /**
     * Run command and parse output from STOUT + STERR
     *
     * @param string $command Command to be run
     * @param array  $output  Command output
     * @param bool   $silent  Command run silently?
     *
     * @return bool true if exit code of program was 0, false otherwise
     */
    protected static function run($command, array &$output = null, $silent = true)
    {
        $replace   = array('$' => '\$');
        $command   = str_replace(array_keys($replace), array_values($replace), $command);
        $errorFile = tempnam(sys_get_temp_dir(), uniqid('CommandErrors', true));
        exec($command . ' 2> ' . $errorFile, $output, $exitCode);
        $errors = file($errorFile, FILE_IGNORE_NEW_LINES);
        unlink($errorFile);

        if ($silent === false)
        {
            if (empty($output) === false)
            {
                print_r($output);
            }

            if (empty($errors) === false)
            {
                print_r($errors);
            }
        }

        return ($exitCode === 0);
    }
}
