<?php

namespace AlonePhp\Code\Frame;

trait Tool {
    /**
     * 报错html
     * @param int $status
     * @return string
     */
    public static function errHtml(int $status = 404): string {
        return "<html><head><title>$status Not Found</title></head><body><center><h1>$status Not Found</h1></center></body></html>";
    }

    /**
     * 报错html
     * @param string|int $title
     * @param string|int $content
     * @return string
     */
    public static function errorHtml(string|int $title = '', string|int $content = ''): string {
        return static::Tag((@file_get_contents(__DIR__ . '/../../file/error.html')), ['title' => ($title ?: '400'), 'content' => ($content ?: 'error')]);
    }

    /**
     * 计算crc32
     * hash('crc32b', $str)
     * @param $str
     * @return string
     */
    public static function strCrc($str): string {
        return dechex(crc32($str));
    }

    /**
     * 获取header头部状态码
     * @param $header
     * @return string
     */
    public static function headStatus($header): string {
        return substr(trim(trim($header, "\r\n")), 9, 3);
    }


    /**
     * 获取html跳转域名
     * @param $html
     * @return string
     */
    public static function getHtmlUrl($html): string {
        preg_match('/content=[\'"][0-9]*;?url=([^\'"]+)[\'"]/i', $html, $matches);
        return $matches[1] ?? '';
    }

    /**
     * 头部信息转array
     * @param string $header
     * @param array  $array
     * @return array
     */
    public static function headToArr(string $header, array $array = []): array {
        if (!empty($header)) {
            $arr = explode("\r\n", trim($header, "\r\n"));
            if (!empty($arr)) {
                foreach ($arr as $v) {
                    $position = strpos($v, ":");
                    if (!empty($position)) {
                        $key = trim(substr($v, 0, $position));
                        $val = trim(substr($v, $position + 1));
                        $array[(strtolower(str_replace("_", "-", $key)))] = ['key' => $key, 'val' => $val, 'head' => $v];
                    }
                }
            }
        }
        return $array;
    }

    /**
     * 获取二个符号之间的内容
     * @param        $str
     * @param string $one
     * @param string $two
     * @return bool|false|string
     */
    public static function signData($str, string $one = '(', string $two = ')'): bool|string {
        $onePos = stripos($str, $one);
        $twoPos = stripos($str, $two);
        if (($onePos === false || $twoPos === false) || $onePos >= $twoPos) {
            return false;
        }
        return substr($str, ($onePos + 1), ($twoPos - $onePos - 1));
    }

    /**
     * 是否cli
     * @return bool
     */
    public static function isCli(): bool {
        return (bool) preg_match("/cli/i", php_sapi_name());
    }


    /**
     * 据传入的经纬度，和距离范围，返回所在距离范围内的经纬度的取值范围
     * @param       $lng
     * @param       $lat
     * @param float $distance 单位：km
     * @return array
     */
    public static function locationRange($lng, $lat, float $distance = 2): array {
        $earthRadius = 6378.137;//单位km
        $d_lng = rad2deg(2 * asin(sin($distance / (2 * $earthRadius)) / cos(deg2rad($lat))));
        $d_lat = rad2deg($distance / $earthRadius);
        return [
            'lat_start' => round($lat - $d_lat, 7),//纬度开始
            'lat_end'   => round($lat + $d_lat, 7),//纬度结束
            'lng_start' => round($lng - $d_lng, 7),//纬度开始
            'lng_end'   => round($lng + $d_lng, 7)//纬度结束
        ];
    }

    /**
     * 根据经纬度返回距离
     * @param $lng1 //经度
     * @param $lat1 //纬度
     * @param $lng2 //经度
     * @param $lat2 //纬度
     * @return float 距离：m
     */
    public static function getDistance($lng1, $lat1, $lng2, $lat2): float {
        $radLat1 = deg2rad($lat1);//deg2rad()函数将角度转换为弧度
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6370996;
        return round($s);
    }

    /**
     *  根据经纬度返回距离
     * @param $lng1 //经度
     * @param $lat1 //纬度
     * @param $lng2 //经度
     * @param $lat2 //纬度
     * @return string 距离：km,m
     */
    public static function distance($lng1, $lat1, $lng2, $lat2): string {
        $m = self::getDistance($lng1, $lat1, $lng2, $lat2);
        if ($m > 1000) {
            return round($m / 1000, 1) . 'km';
        } else {
            return $m . 'm';
        }
    }

    /**
     * 获取字符串 as 别名
     * @param string $string
     * @param bool   $type
     * @param array  $arr
     * @return array
     */
    public static function getAlias(string $string, bool $type = false, array $arr = []): array {
        if (!empty(preg_match_all('/(\w+[^,]*)\s+as\s+(\w+[^,]*)/i', $string, $array, PREG_SET_ORDER))) {
            foreach ($array as $v) {
                if (!empty($key = trim(($v[1] ?? ''))) && !empty($val = trim(($v[2] ?? '')))) {
                    $arr[] = ['old' => $key, 'new' => $val, 'str' => $v[0]];
                }
            }
        }
        return (!empty($arr) && $type === false) ? $arr[key($arr)] : $arr;
    }

    /**
     * 根据总列数生成EXCEL列名的算法
     * @param        $i
     * @param string $str
     * @param int    $iv
     * @return string
     */
    public static function aZ($i, string $str = '', int $iv = 26): string {
        while ($i > 0) {
            $int = $i % $iv;
            $int = ($int == 0) ? $iv : $int;
            $str = strtoupper(chr($int + 64)) . $str;
            $i = ($i - $int) / $iv;
        }
        return $str;
    }

    /**
     * 根据总列数生成EXCEL列名的算法
     * 最多输出二个组合
     * @param        $i
     * @param string $data
     * @return string
     */
    public static function aAZZ($i, string $data = ''): string {
        $str = 'A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z';
        $arr = explode(',', $str);
        $iv = count($arr);
        $key = ($i - 1);
        if ($i > $iv) {
            $number = ($i / $iv);
            $int = intval($number);
            $key = ($i - ($int * $iv));
            $key = (($key > 0 ? $key : $iv) - 1);
            $int = (($int > 0 ? is_int($number) ? $int - 2 : $int - 1 : ($iv - 1)));
            $data = join('', array_slice($arr, $int, 1));
        }
        return $data . $arr[$key] ?? $arr[0];
    }

    /**
     * 全部结合
     * row('ABC',2)
     * @param $letters
     * @param $num
     * @return array
     */
    public static function row($letters, $num): array {
        $last = str_repeat($letters[0], $num);
        $result = [];
        while ($last != str_repeat($letters[strlen($letters) - 1], $num)) {
            $result[] = $last;
            $last = self::charAdd($letters, $last, $num - 1);
        }
        $result[] = $last;
        return $result;
    }

    /**
     * @param $digits
     * @param $string
     * @param $char
     * @return mixed
     */
    private static function charAdd($digits, $string, $char): mixed {
        $chang = function($string, $char, $start = 0, $end = 0) {
            if ($end == 0)
                $end = strlen($string) - 1;
            for ($i = $start; $i <= $end; $i++) {
                $string[$i] = $char;
            }
            return $string;
        };
        if ($string[$char] != $digits[strlen($digits) - 1]) {
            $string[$char] = $digits[strpos($digits, $string[$char]) + 1];
            return $string;
        } else {
            $string = $chang($string, $digits[0], $char);
            return self::charAdd($digits, $string, $char - 1);
        }
    }
}