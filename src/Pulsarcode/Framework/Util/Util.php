<?php

namespace Pulsarcode\Framework\Util;

use Pulsarcode\Framework\Config\Config;
use Pulsarcode\Framework\Core\Core;

/**
 * Class Util para ofrecer utilidades
 *
 * @package Pulsarcode\Framework\Util
 */
class Util extends Core
{
    /**
     * Transforma una cadena UTF8 en su correspondencia ASCII
     *
     * @param string $text    Cadena a procesar
     * @param string $unknown Caracter por defecto si no se puede reemplazar
     *
     * @return string Cadena procesada
     */
    public static function UTF8ToASCII($text, $unknown = '?')
    {
        static $UTF8_TO_ASCII;

        if (strlen($text) == 0)
        {
            return '';
        }

        preg_match_all('/.{1}|[^\x00]{1,1}$/us', $text, $ar);
        $chars = $ar[0];

        foreach ($chars as $i => $c)
        {
            // ASCII - next please
            if (ord($c{0}) >= 0 && ord($c{0}) <= 127)
            {
                continue;
            }

            if (ord($c{0}) >= 192 && ord($c{0}) <= 223)
            {
                $ord = (ord($c{0}) - 192) * 64 + (ord($c{1}) - 128);
            }

            if (ord($c{0}) >= 224 && ord($c{0}) <= 239)
            {
                $ord = (ord($c{0}) - 224) * 4096 + (ord($c{1}) - 128) * 64 + (ord($c{2}) - 128);
            }

            if (ord($c{0}) >= 240 && ord($c{0}) <= 247)
            {
                $ord = (ord($c{0}) - 240) * 262144 + (ord($c{1}) - 128) * 4096 + (ord($c{2}) - 128) * 64 + (ord(
                            $c{3}
                        ) - 128);
            }

            if (ord($c{0}) >= 248 && ord($c{0}) <= 251)
            {
                $ord = (ord($c{0}) - 248) * 16777216 + (ord($c{1}) - 128) * 262144 + (ord($c{2}) - 128) * 4096 + (ord(
                            $c{3}
                        ) - 128) * 64 + (ord($c{4}) - 128);
            }

            if (ord($c{0}) >= 252 && ord($c{0}) <= 253)
            {
                $ord = (ord($c{0}) - 252) * 1073741824 + (ord($c{1}) - 128) * 16777216 + (ord(
                            $c{2}
                        ) - 128) * 262144 + (ord($c{3}) - 128) * 4096 + (ord($c{4}) - 128) * 64 + (ord($c{5}) - 128);
            }

            // error
            if (ord($c{0}) >= 254 && ord($c{0}) <= 255)
            {
                $chars{$i} = $unknown;
                continue;
            }

            $bank = $ord >> 8;

            if (!array_key_exists($bank, (array) $UTF8_TO_ASCII))
            {
                $bankfile = implode(DIRECTORY_SEPARATOR, array(__DIR__, 'data', sprintf("x%02x", $bank) . '.php'));

                if (file_exists($bankfile))
                {
                    include $bankfile;
                }
                else
                {
                    $UTF8_TO_ASCII[$bank] = array();
                }
            }

            $newchar = $ord & 255;

            if (array_key_exists($newchar, $UTF8_TO_ASCII[$bank]))
            {
                $chars{$i} = $UTF8_TO_ASCII[$bank][$newchar];
            }
            else
            {
                $chars{$i} = $unknown;
            }
        }

        return implode('', $chars);
    }

    /**
     * Función para pintar de forma visual un var_dump()
     *
     * @param mixed $variable Variable con el contenido a pintar
     * @param bool  $info     Texto adicional como información del var_dump()
     */
    public static function dump(&$variable, $info = false)
    {
        $backup       = $variable;
        $variable     = $seed = md5(uniqid() . rand());
        $variableName = 'unknown';

        foreach ($GLOBALS as $key => $value)
        {
            if ($value === $seed)
            {
                $variableName = $key;
            }
        }

        $variable = $backup;

        echo '<pre style="
            font-family : monospace, sans-serif;
            text-align  : left;
            margin      : 25px;
            display     : block;
            background  : #ffffff;
            color       : #000000;
            border      : 1px solid #cdcdcd;
            padding     : 5px;
            font-size   : 11px;
            line-height : 14px;
          ">';

        $info = ($info) ? $info : '$' . $variableName;
        echo '<strong style="color:red;">' . $info . ':</strong><br />';
        self::dumpVal($variable, '$' . $variableName);
        echo '<strong style="color:red;">End ' . $info . '</strong></pre>';
    }

    /**
     * Comprueba si una cadena contiene caracteres UTF8
     *
     * @param string $text Cadena a procesar
     *
     * @return boolean $bool
     */
    public static function isUTF8($text)
    {
        for ($i = 0; $i < strlen($text); $i++)
        {
            if (ord($text[$i]) < 0x80)
            {
                continue; // 0bbbbbbb
            }
            elseif ((ord($text[$i]) & 0xE0) == 0xC0)
            {
                $n = 1; // 110bbbbb
            }
            elseif ((ord($text[$i]) & 0xF0) == 0xE0)
            {
                $n = 2; // 1110bbbb
            }
            elseif ((ord($text[$i]) & 0xF8) == 0xF0)
            {
                $n = 3; // 11110bbb
            }
            elseif ((ord($text[$i]) & 0xFC) == 0xF8)
            {
                $n = 4; // 111110bb
            }
            elseif ((ord($text[$i]) & 0xFE) == 0xFC)
            {
                $n = 5; // 1111110b
            }
            else
            {
                return false; // Does not match any model
            }

            // n bytes matching 10bbbbbb follow ?
            for ($j = 0; $j < $n; $j++)
            {
                if ((++$i == strlen($text)) || ((ord($text[$i]) & 0xC0) != 0x80))
                {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Valida si una cadena es UTF8 usando el Unicode estándar
     *
     * @param string $text Cadena a procesar
     *
     * @return boolean
     */
    public static function isValidUTF8($text)
    {
        /**
         * Número esperado en caché de octetos después del octeto actual
         * hasta el comienzo de la siguiente secuencia de caracteres UTF8
         */
        $mState = 0;

        /**
         * Carácter Unicode en caché
         */
        $mUcs4 = 0;

        /**
         * Número esperado en caché de octetos en la secuencia actual
         */
        $mBytes = 1;

        $len = strlen($text);

        for ($i = 0; $i < $len; $i++)
        {
            $in = ord($text{$i});

            if ($mState == 0)
            {
                /**
                 * Cuando $mState es cero esperamos que sea un caracter US-ASCII o una secuencia multi-octeto
                 */
                if (0 == (0x80 & ($in)))
                {
                    /**
                     * US-ASCII, pasa directamente
                     */
                    $mBytes = 1;
                }
                elseif (0xC0 == (0xE0 & ($in)))
                {
                    /**
                     * En primer octeto de una secuencias de 2 octetos
                     */
                    $mUcs4  = ($in);
                    $mUcs4  = ($mUcs4 & 0x1F) << 6;
                    $mState = 1;
                    $mBytes = 2;
                }
                elseif (0xE0 == (0xF0 & ($in)))
                {
                    /**
                     * En primer octeto de una secuencias de 3 octetos
                     */
                    $mUcs4  = ($in);
                    $mUcs4  = ($mUcs4 & 0x0F) << 12;
                    $mState = 2;
                    $mBytes = 3;
                }
                elseif (0xF0 == (0xF8 & ($in)))
                {
                    /**
                     * En primer octeto de una secuencias de 4 octetos
                     */
                    $mUcs4  = ($in);
                    $mUcs4  = ($mUcs4 & 0x07) << 18;
                    $mState = 3;
                    $mBytes = 4;
                }
                elseif (0xF8 == (0xFC & ($in)))
                {
                    /**
                     * En primer octeto de una secuencias de 5 octetos
                     *
                     * Esto es ilegal, porque el punto de código codificado debe:
                     *
                     * (A) no ser la forma más corta o más
                     *
                     * (B) estar fuera del rango Unicode 0-0x10FFFF
                     *
                     * En lugar de tratar de volver a sincronizar, vamos a continuar hasta el final de la secuencia
                     * y dejar que el código de gestión de errores lo atrape después en una excepción
                     */
                    $mUcs4  = ($in);
                    $mUcs4  = ($mUcs4 & 0x03) << 24;
                    $mState = 4;
                    $mBytes = 5;
                }
                elseif (0xFC == (0xFE & ($in)))
                {
                    /**
                     * En primer octeto de una secuencias de 6 octetos, leer comentario para los 5 octetos
                     */
                    $mUcs4  = ($in);
                    $mUcs4  = ($mUcs4 & 1) << 30;
                    $mState = 5;
                    $mBytes = 6;
                }
                else
                {
                    /**
                     * El octeto actual no está ni en el rango de US-ASCII
                     * ni es un primer octeto legal de una secuencia multi-octeto
                     */
                    return false;
                }
            }
            else
            {
                /**
                 * Cuando $mState es distinto de cero, se espera una continuación de la secuencia multi-octeto
                 */
                if (0x80 == (0xC0 & ($in)))
                {
                    /**
                     * Continuación legal
                     */
                    $shift = ($mState - 1) * 6;
                    $tmp   = $in;
                    $tmp   = ($tmp & 0x0000003F) << $shift;
                    $mUcs4 |= $tmp;

                    /**
                     * Fin de la secuencia multi-octeto, $mUcs4 ahora contiene el punto final de salida Unicode
                     */
                    if (0 == --$mState)
                    {
                        /**
                         * Compruebe si hay secuencias y puntos de código ilegales
                         * Desde Unicode 3.1, la forma no menor es ilegal
                         */
                        if (((2 == $mBytes) && ($mUcs4 < 0x0080)) ||
                            ((3 == $mBytes) && ($mUcs4 < 0x0800)) ||
                            ((4 == $mBytes) && ($mUcs4 < 0x10000)) ||
                            (4 < $mBytes) ||
                            /**
                             * Desde Unicode 3.2, los caracteres suplentes son ilegales
                             */
                            (($mUcs4 & 0xFFFFF800) == 0xD800) ||
                            /**
                             * Los puntos de código fuera del rango Unicode son ilegales
                             */
                            ($mUcs4 > 0x10FFFF)
                        )
                        {
                            return false;
                        }

                        /**
                         * Inicializar caché UTF8
                         */
                        $mState = 0;
                        $mUcs4  = 0;
                        $mBytes = 1;
                    }
                }
                else
                {
                    /**
                     * Secuencia multi-octeto incompleta
                     */
                    // ((0xC0 & (*in) != 0x80) && (mState != 0))

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Genera una cadena libre de símbolos extraños
     *
     * @param string $text Cadena a procesar
     * @param string $glue Pegamento para unir palabras
     *
     * @return string
     */
    public static function sanitizeString($text = '', $glue = '-')
    {
        $text = self::unaccent($text);

        return self::postProcessText($text, $glue);
    }

    /**
     * Descodifica los datos de un token
     *
     * @param string $token Token con datos a decodificar
     *
     * @return string
     */
    public static function tokenDecode($token)
    {
        $configToken = Config::getConfig()->application['token'];
        list($email, $data) = explode(':', base64_decode(str_replace($configToken, '', base64_decode($token))));

        return array(
            'token' => $token,
            'email' => $email,
            'data'  => $data,
        );
    }

    /**
     * Codifica los datos con un token
     *
     * @param string $email Email para incluir en la codificación
     * @param string $data  Datos a codificar
     *
     * @return array
     */
    public static function tokenEncode($email, $data)
    {
        $token = base64_encode(Config::getConfig()->application['token'] . base64_encode($email . ':' . $data));

        return array(
            'token' => $token,
            'email' => $email,
            'data'  => $data,
        );
    }

    /**
     * Genera un hash basado en el token y el data codificado
     *
     * @param string $token Token a usar para la codificación del hash
     * @param string $data  Datos a usar para la codificación del hash
     *
     * @return string
     */
    public static function tokenHash($token, $data)
    {
        return md5($token . Config::getConfig()->environment . $data . Config::getConfig()->application['token']);
    }

    /**
     * Transforma caracteres UTF8 usando tablas de transliteración
     *
     * @param string $text Cadena a procesar
     * @param string $glue Pegamento para unir palabras
     *
     * @return string $text
     */
    public static function transliterate($text, $glue = '-')
    {
        if (preg_match('/[\x80-\xff]/', $text) && self::isValidUTF8($text))
        {
            $text = self::UTF8ToASCII($text);
        }

        return self::postProcessText($text, $glue);
    }

    /**
     * Elimina caracteres extraños y acentos
     *
     * @param string $text Cadena a procesar
     *
     * @return string Cadena procesada
     */
    public static function unaccent($text)
    {
        if (!preg_match('/[\x80-\xff]/', $text))
        {
            return $text;
        }

        if (self::isUTF8($text))
        {
            $chars = array(
                // Decompositions for Latin-1 Supplement
                chr(195) . chr(128)            => 'A',
                chr(195) . chr(129)            => 'A',
                chr(195) . chr(130)            => 'A',
                chr(195) . chr(131)            => 'A',
                chr(195) . chr(132)            => 'A',
                chr(195) . chr(133)            => 'A',
                chr(195) . chr(135)            => 'C',
                chr(195) . chr(136)            => 'E',
                chr(195) . chr(137)            => 'E',
                chr(195) . chr(138)            => 'E',
                chr(195) . chr(139)            => 'E',
                chr(195) . chr(140)            => 'I',
                chr(195) . chr(141)            => 'I',
                chr(195) . chr(142)            => 'I',
                chr(195) . chr(143)            => 'I',
                chr(195) . chr(145)            => 'N',
                chr(195) . chr(146)            => 'O',
                chr(195) . chr(147)            => 'O',
                chr(195) . chr(148)            => 'O',
                chr(195) . chr(149)            => 'O',
                chr(195) . chr(150)            => 'O',
                chr(195) . chr(153)            => 'U',
                chr(195) . chr(154)            => 'U',
                chr(195) . chr(155)            => 'U',
                chr(195) . chr(156)            => 'U',
                chr(195) . chr(157)            => 'Y',
                chr(195) . chr(159)            => 's',
                chr(195) . chr(160)            => 'a',
                chr(195) . chr(161)            => 'a',
                chr(195) . chr(162)            => 'a',
                chr(195) . chr(163)            => 'a',
                chr(195) . chr(164)            => 'a',
                chr(195) . chr(165)            => 'a',
                chr(195) . chr(167)            => 'c',
                chr(195) . chr(168)            => 'e',
                chr(195) . chr(169)            => 'e',
                chr(195) . chr(170)            => 'e',
                chr(195) . chr(171)            => 'e',
                chr(195) . chr(172)            => 'i',
                chr(195) . chr(173)            => 'i',
                chr(195) . chr(174)            => 'i',
                chr(195) . chr(175)            => 'i',
                chr(195) . chr(177)            => 'n',
                chr(195) . chr(178)            => 'o',
                chr(195) . chr(179)            => 'o',
                chr(195) . chr(180)            => 'o',
                chr(195) . chr(181)            => 'o',
                chr(195) . chr(182)            => 'o',
                chr(195) . chr(182)            => 'o',
                chr(195) . chr(185)            => 'u',
                chr(195) . chr(186)            => 'u',
                chr(195) . chr(187)            => 'u',
                chr(195) . chr(188)            => 'u',
                chr(195) . chr(189)            => 'y',
                chr(195) . chr(191)            => 'y',
                // Decompositions for Latin Extended-A
                chr(196) . chr(128)            => 'A',
                chr(196) . chr(129)            => 'a',
                chr(196) . chr(130)            => 'A',
                chr(196) . chr(131)            => 'a',
                chr(196) . chr(132)            => 'A',
                chr(196) . chr(133)            => 'a',
                chr(196) . chr(134)            => 'C',
                chr(196) . chr(135)            => 'c',
                chr(196) . chr(136)            => 'C',
                chr(196) . chr(137)            => 'c',
                chr(196) . chr(138)            => 'C',
                chr(196) . chr(139)            => 'c',
                chr(196) . chr(140)            => 'C',
                chr(196) . chr(141)            => 'c',
                chr(196) . chr(142)            => 'D',
                chr(196) . chr(143)            => 'd',
                chr(196) . chr(144)            => 'D',
                chr(196) . chr(145)            => 'd',
                chr(196) . chr(146)            => 'E',
                chr(196) . chr(147)            => 'e',
                chr(196) . chr(148)            => 'E',
                chr(196) . chr(149)            => 'e',
                chr(196) . chr(150)            => 'E',
                chr(196) . chr(151)            => 'e',
                chr(196) . chr(152)            => 'E',
                chr(196) . chr(153)            => 'e',
                chr(196) . chr(154)            => 'E',
                chr(196) . chr(155)            => 'e',
                chr(196) . chr(156)            => 'G',
                chr(196) . chr(157)            => 'g',
                chr(196) . chr(158)            => 'G',
                chr(196) . chr(159)            => 'g',
                chr(196) . chr(160)            => 'G',
                chr(196) . chr(161)            => 'g',
                chr(196) . chr(162)            => 'G',
                chr(196) . chr(163)            => 'g',
                chr(196) . chr(164)            => 'H',
                chr(196) . chr(165)            => 'h',
                chr(196) . chr(166)            => 'H',
                chr(196) . chr(167)            => 'h',
                chr(196) . chr(168)            => 'I',
                chr(196) . chr(169)            => 'i',
                chr(196) . chr(170)            => 'I',
                chr(196) . chr(171)            => 'i',
                chr(196) . chr(172)            => 'I',
                chr(196) . chr(173)            => 'i',
                chr(196) . chr(174)            => 'I',
                chr(196) . chr(175)            => 'i',
                chr(196) . chr(176)            => 'I',
                chr(196) . chr(177)            => 'i',
                chr(196) . chr(178)            => 'IJ',
                chr(196) . chr(179)            => 'ij',
                chr(196) . chr(180)            => 'J',
                chr(196) . chr(181)            => 'j',
                chr(196) . chr(182)            => 'K',
                chr(196) . chr(183)            => 'k',
                chr(196) . chr(184)            => 'k',
                chr(196) . chr(185)            => 'L',
                chr(196) . chr(186)            => 'l',
                chr(196) . chr(187)            => 'L',
                chr(196) . chr(188)            => 'l',
                chr(196) . chr(189)            => 'L',
                chr(196) . chr(190)            => 'l',
                chr(196) . chr(191)            => 'L',
                chr(197) . chr(128)            => 'l',
                chr(197) . chr(129)            => 'L',
                chr(197) . chr(130)            => 'l',
                chr(197) . chr(131)            => 'N',
                chr(197) . chr(132)            => 'n',
                chr(197) . chr(133)            => 'N',
                chr(197) . chr(134)            => 'n',
                chr(197) . chr(135)            => 'N',
                chr(197) . chr(136)            => 'n',
                chr(197) . chr(137)            => 'N',
                chr(197) . chr(138)            => 'n',
                chr(197) . chr(139)            => 'N',
                chr(197) . chr(140)            => 'O',
                chr(197) . chr(141)            => 'o',
                chr(197) . chr(142)            => 'O',
                chr(197) . chr(143)            => 'o',
                chr(197) . chr(144)            => 'O',
                chr(197) . chr(145)            => 'o',
                chr(197) . chr(146)            => 'OE',
                chr(197) . chr(147)            => 'oe',
                chr(197) . chr(148)            => 'R',
                chr(197) . chr(149)            => 'r',
                chr(197) . chr(150)            => 'R',
                chr(197) . chr(151)            => 'r',
                chr(197) . chr(152)            => 'R',
                chr(197) . chr(153)            => 'r',
                chr(197) . chr(154)            => 'S',
                chr(197) . chr(155)            => 's',
                chr(197) . chr(156)            => 'S',
                chr(197) . chr(157)            => 's',
                chr(197) . chr(158)            => 'S',
                chr(197) . chr(159)            => 's',
                chr(197) . chr(160)            => 'S',
                chr(197) . chr(161)            => 's',
                chr(197) . chr(162)            => 'T',
                chr(197) . chr(163)            => 't',
                chr(197) . chr(164)            => 'T',
                chr(197) . chr(165)            => 't',
                chr(197) . chr(166)            => 'T',
                chr(197) . chr(167)            => 't',
                chr(197) . chr(168)            => 'U',
                chr(197) . chr(169)            => 'u',
                chr(197) . chr(170)            => 'U',
                chr(197) . chr(171)            => 'u',
                chr(197) . chr(172)            => 'U',
                chr(197) . chr(173)            => 'u',
                chr(197) . chr(174)            => 'U',
                chr(197) . chr(175)            => 'u',
                chr(197) . chr(176)            => 'U',
                chr(197) . chr(177)            => 'u',
                chr(197) . chr(178)            => 'U',
                chr(197) . chr(179)            => 'u',
                chr(197) . chr(180)            => 'W',
                chr(197) . chr(181)            => 'w',
                chr(197) . chr(182)            => 'Y',
                chr(197) . chr(183)            => 'y',
                chr(197) . chr(184)            => 'Y',
                chr(197) . chr(185)            => 'Z',
                chr(197) . chr(186)            => 'z',
                chr(197) . chr(187)            => 'Z',
                chr(197) . chr(188)            => 'z',
                chr(197) . chr(189)            => 'Z',
                chr(197) . chr(190)            => 'z',
                chr(197) . chr(191)            => 's',
                // Euro Sign
                chr(226) . chr(130) . chr(172) => 'E',
                // GBP (Pound) Sign
                chr(194) . chr(163)            => '',
                'Ä'                            => 'Ae',
                'ä'                            => 'ae',
                'Ü'                            => 'Ue',
                'ü'                            => 'ue',
                'Ö'                            => 'Oe',
                'ö'                            => 'oe',
                'ß'                            => 'ss',
                // Norwegian characters
                'Å'                            => 'Aa',
                'Æ'                            => 'Ae',
                'Ø'                            => 'O',
                'æ'                            => 'a',
                'ø'                            => 'o',
                'å'                            => 'aa'
            );

            $text = strtr($text, $chars);
        }
        else
        {
            // Assume ISO-8859-1 if not UTF-8
            $chars['in'] = chr(128) . chr(131) . chr(138) . chr(142) . chr(154) . chr(158)
                . chr(159) . chr(162) . chr(165) . chr(181) . chr(192) . chr(193) . chr(194)
                . chr(195) . chr(196) . chr(197) . chr(199) . chr(200) . chr(201) . chr(202)
                . chr(203) . chr(204) . chr(205) . chr(206) . chr(207) . chr(209) . chr(210)
                . chr(211) . chr(212) . chr(213) . chr(214) . chr(216) . chr(217) . chr(218)
                . chr(219) . chr(220) . chr(221) . chr(224) . chr(225) . chr(226) . chr(227)
                . chr(228) . chr(229) . chr(231) . chr(232) . chr(233) . chr(234) . chr(235)
                . chr(236) . chr(237) . chr(238) . chr(239) . chr(241) . chr(242) . chr(243)
                . chr(244) . chr(245) . chr(246) . chr(248) . chr(249) . chr(250) . chr(251)
                . chr(252) . chr(253) . chr(255);

            $chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";

            $text = strtr($text, $chars['in'], $chars['out']);

            $doubleChars['in']  = array(
                chr(140),
                chr(156),
                chr(198),
                chr(208),
                chr(222),
                chr(223),
                chr(230),
                chr(240),
                chr(254)
            );
            $doubleChars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');

            $text = str_replace($doubleChars['in'], $doubleChars['out'], $text);
        }

        return $text;
    }

    private static function dumpVal(&$dump, $info = '', $tab = '', $reference = '')
    {
        $indent       = 4;
        $downLine     = '<span style="color:#ccc;">|</span>' . str_repeat(' ', $indent);
        $reference    = $reference . $info;
        $variableKey  = 'the_do_dump_recursion_protection_scheme';
        $variableName = 'referenced_object_name';

        if (is_array($dump) && isset($dump[$variableKey]))
        {
            $referenceVariableKey  = &$dump[$variableKey];
            $referenceVariableName = &$dump[$variableName];
            $variableType          = ucfirst(gettype($referenceVariableKey));
            echo $tab . $info . '<span style="color:#a2a2a2">' . $variableType . '</span> = <span style="color:#e87800;">&amp;' . $referenceVariableName . '</span><br>';
        }
        else
        {
            $dump     = array($variableKey => $dump, $variableName => $reference);
            $variable = &$dump[$variableKey];

            $variableType = gettype($variable);
            $span         = '&nbsp;<span style="color:#a2a2a2;">';
            $color        = array(
                'array'   => '#fff',
                'object'  => '#fff',
                'string'  => 'green',
                'integer' => 'red',
                'double'  => '#0099c5',
                'boolean' => '#92008d',
                'NULL'    => 'blue',
            );
            $colorType    = '<span style="color:' . $color[$variableType] . ';">';
            $closeSpan    = '&nbsp;</span>';
            $newLine      = '</span><br />';
            switch (gettype($variable))
            {
                case 'object':
                    echo $tab . $info . $span . $variableType . '(' . count(
                            $variable
                        ) . ')</span><br>' . $tab . '(<br>';

                    foreach ($variable as $name => $value)
                    {
                        self::dumpVal($value, '->' . $name, $tab . $downLine, $reference);
                    }
                    echo $tab . ')<br>';
                    break;

                case 'array':

                    echo $tab . ($info ? $info . ' =' : '') . $span . $variableType . '(' . count(
                            $variable
                        ) . ')</span><br>' . $tab . '(<br>';

                    $keys = array_keys($variable);

                    foreach ($keys as $name)
                    {
                        $value = &$variable[$name];
                        if (is_integer($name) === true)
                        {
                            self::dumpVal($value, '[' . $name . ']', $tab . $downLine, $reference);
                        }
                        else
                        {
                            self::dumpVal($value, "['" . $name . "']", $tab . $downLine, $reference);
                        }
                    }
                    echo $tab . ')<br>';
                    break;
                case 'string':
                    echo $tab . $info . ' =' . $span . $variableType . '(' . strlen(
                            $variable
                        ) . ')' . $closeSpan . $colorType . '"' . htmlentities($variable) . '"' . $newLine;
                    break;
                case 'integer':
                    echo $tab . $info . ' =' . $span . $variableType . '(' . strlen(
                            $variable
                        ) . ')' . $closeSpan . $colorType . $variable . $newLine;
                    break;
                case 'double':
                    $variableType = 'float';
                    echo $tab . $info . ' =' . $span . $variableType . '(' . strlen(
                            $variable
                        ) . ')' . $closeSpan . $colorType . $variable . $newLine;
                    break;
                case 'boolean':
                    echo $tab . $info . ' =' . $span . $variableType . '(' . strlen(
                            $variable
                        ) . ')' . $closeSpan . $colorType . ($variable ? 'true' : 'false') . $newLine;
                    break;
                case 'NULL':
                    echo $tab . $info . ' =' . $span . $variableType . '(' . strlen(
                            $variable
                        ) . ')' . $closeSpan . $colorType . 'null' . $newLine;
                    break;
                default:
                    echo $tab . $info . ' =' . $span . $variableType . '(' . strlen(
                            $variable
                        ) . ')' . $closeSpan . $variable . '<br>';
                    break;
            }

            $dump = $dump[$variableKey];
        }
    }

    /**
     * Limpia la cadena pasada y aplica el pegamento para unirla
     *
     * @param string $text Cadena a procesar
     * @param string $glue Pegamento para unir palabras
     *
     * @return string
     */
    private static function postProcessText($text, $glue)
    {
        if (function_exists('mb_strtolower'))
        {
            $text = mb_strtolower($text);
        }
        else
        {
            $text = strtolower($text);
        }

        /**
         * Deben suprimirse los apóstrofes antes de reemplazar lo que no son letras, números o guiones bajos
         */
        $text = preg_replace("/'/", '', $text);

        /**
         * Reemplazo de todos los caracteres excepto letras, números o guiones bajos
         */
        $text = preg_replace('/\W/', ' ', $text);

        /**
         * Reemplazo de espacios con $glue
         */
        $text = strtolower(
            preg_replace(
                '/[^A-Za-z0-9\/]+/',
                $glue,
                preg_replace(
                    '/([a-z\d])([A-Z])/',
                    '\1_\2',
                    preg_replace(
                        '/([A-Z]+)([A-Z][a-z])/',
                        '\1_\2',
                        preg_replace('/::/', '/', $text)
                    )
                )
            )
        );

        return trim($text, $glue);
    }
}
