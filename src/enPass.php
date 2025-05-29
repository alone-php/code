<?php

namespace AlonePhp\Code;

class enPass {

    public static function encodeSha1($e): string {
        error_reporting(0);
        return self::g(self::o(self::h($e), strlen($e) * 8));
    }

    public static function encode($e): string {
        return self::encodeSha1(self::encodeSha1($e));
    }

    public static function encodePsw($e): array {
        $data['time'] = self::getTime();
        $data['token'] = self::encodeSha1(self::encodeSha1($e) . $data['time']);
        return $data;
    }

    protected static function a($e): string {
        $r = 0;
        $t = $r ? "0123456789ABCDEF" : "0123456789abcdef";
        $o = "";
        for ($i = 0; $i < 4 * count($e); $i++) {
            $byteIndex = $i >> 2;
            $byteValue = $e[$byteIndex];
            $bitShift = 8 * (3 - ($i % 4)) + 4;
            $hexValue1 = ($byteValue >> $bitShift) & 15;
            $hexValue2 = ($byteValue >> 8 * (3 - $i % 4)) & 15;
            $o .= $t[$hexValue1] . $t[$hexValue2];
        }
        return $o;
    }

    protected static function h($t): array {
        $a = 8;
        $e = [];
        $n = (1 << $a) - 1;
        for ($r = 0; $r < strlen($t) * $a; $r += $a) {
            $e[$r >> 5] |= (ord($t[$r / $a]) & $n) << 32 - $a - $r % 32;
        }
        return $e;
    }

    protected static function g($t): string {
        $i = "=";
        $e = "";
        for ($n = 0; $n < 4 * count($t); $n += 3) {
            $r = ($t[$n >> 2] >> 8 * (3 - $n % 4) & 255) << 16 | ($t[$n + 1 >> 2] >> 8 * (3 - ($n + 1) % 4) & 255) << 8 | $t[$n + 2 >> 2] >> 8 * (3 - ($n + 2) % 4) & 255;
            for ($a = 0; $a < 4; $a++) {
                if (8 * $n + 6 * $a > 32 * count($t)) {
                    $e .= $i;
                } else {
                    $e .= substr("ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/", ($r >> 6 * (3 - $a) & 63), 1);
                }
            }
        }
        return $e;
    }

    protected static function u($t, $e): int|string {
        $mask = 0xffffffff;
        $shifted = ($t << $e) & $mask;
        if ($shifted & 0x80000000) {
            $left = -((~$shifted & $mask) + 1);
        } else {
            $left = $shifted;
        }
        return $left | ($t & 0xffffffff) >> (32 - $e);
    }

    protected static function c($t): int|string {
        return $t < 20 ? 1518500249 : ($t < 40 ? 1859775393 : ($t < 60 ? -1894007588 : -899497514));
    }

    protected static function d($t, $e, $n, $r): int|string {
        return $t < 20 ? ($e & $n) | (~$e & $r) : ($t < 40 ? $e ^ $n ^ $r : ($t < 60 ? ($e & $n) | ($e & $r) | ($n & $r) : $e ^ $n ^ $r));
    }

    protected static function s($t, $e): int|string {
        $add = function($a, $b) {
            while ($b != 0) {
                $carry = $a & $b;
                $a = $a ^ $b;
                $b = $carry << 1;
            }
            return $a;
        };
        $mod = function($a, $b) {
            return $a - floor($a / $b) * $b;
        };
        $way = function($a, $b) {
            $data = ($a >> $b) & 0xFFFF;
            if ($data & 0x8000) {
                $data -= 0xFFFF + 1;
            }
            return $data;
        };

        $combine = function($t, $e) {
            $mask = 0xffffffff;
            $shifted = ($t << $e) & $mask;
            if ($shifted & 0x80000000) {
                $left = -((~$shifted & $mask) + 1);
            } else {
                $left = $shifted;
            }
            return $left;
        };
        $n = $add($mod($t, 65536), $mod($e, 65536));
        return $combine($way($t, 16) + $way($e, 16) + $way($n, 16), 16) | $mod($n, 65536);
    }

    protected static function o($t, $e): array {
        $t[$e >> 5] |= 128 << (24 - $e % 32);
        $t[15 + (($e + 64) >> 9 << 4)] = $e;
        $n = array_fill(0, 80, null);
        $r = 1732584193;
        $i = -271733879;
        $a = -1732584194;
        $o = 271733878;
        $h = -1009589776;
        end($t);
        for ($g = 0; $g < (key($t) + 1); $g += 16) {
            for ($p = 0; $p < 80; $p++) {
                $n[$p] = $p < 16 ? $t[$g + $p] : (self::u($n[$p - 3] ^ $n[$p - 8] ^ $n[$p - 14] ^ $n[$p - 16], 1));
                $v = self::s(self::s(self::u($r, 5), self::d($p, $i, $a, $o)), self::s(self::s($h, $n[$p]), self::c($p)));
                $h = $o;
                $o = $a;
                $a = self::u($i, 30);
                $i = $r;
                $r = $v;
            }
            $r = self::s($v, 1732584193);
            $i = self::s($i, -271733879);
            $a = self::s($a, -1732584194);
            $o = self::s($o, 271733878);
            $h = self::s($h, -1009589776);
        }
        return [$r, $i, $a, $o, $h];
    }

    public static function getTime(null|string|int|float $time = null): float {
        [$t1, $t2] = explode(" ", (!empty($time) ? $time : microtime()));
        return (float) sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }

    public static function randTime(): string {
        return substr(self::getTime(), 2) . rand(100, 999);
    }

    public static function encodeSha2($e): string {
        error_reporting(0);
        return self::a(self::o(self::h($e), strlen($e) * 8));
    }

    public static function encode2($e): string {
        return self::encodeSha2(self::encodeSha2($e));
    }

    public static function encodePsw2($e): array {
        $data['time'] = self::getTime();
        $data['token'] = self::encodeSha2(self::encodeSha2($e) . $data['time']);
        return $data;
    }
}