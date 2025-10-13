<?php

namespace AlonePhp\Code;

use Closure;
use Exception;
use Throwable;
use stdClass;
use AlonePhp\Code\Frame\Arr;
use AlonePhp\Code\Frame\Xml;
use AlonePhp\Code\Frame\Zip;
use AlonePhp\Code\Frame\File;
use AlonePhp\Code\Frame\Tool;
use AlonePhp\Code\Frame\Date;
use AlonePhp\Code\Frame\Mime;
use AlonePhp\Code\Frame\Bank;
use AlonePhp\Code\Frame\Amount;
use AlonePhp\Code\Frame\Domain;

class Frame {
    use Amount, Arr, Bank, Date, Domain, File, Mime, Tool, Xml, Zip;

    /**
     * 判断是否方法
     * @param mixed $value
     * @return bool
     */
    public static function isCallable(mixed $value): bool {
        return $value instanceof Closure;
    }

    /**
     * 是否维护
     * maintain(12,14,1) 周一12:00-14:00
     * maintain("13:30","18:20",3) 周三13:30-18:20
     * maintain("13:30","18:20",0) 每天13:30-18:20
     * maintain("13:30","18:20",false) 每天13:30-18:20
     * maintain("2025-04-01 13:40:22","2025-04-02 13:40:22")
     * @param int|string $top  开始点数,支持设置 时:分  开始日期
     * @param int|string $end  结束点数,支持设置 时:分  结束日期
     * @param int|bool   $week int=设置周几或者false,0=每天,true=日期
     * @param int        $time 时间戳,默认当前时间戳
     * @return false|string false=未维护,string=返回当前维护时间
     */
    public static function maintain(int|string $top, int|string $end, int|bool $week = true, int $time = 0): false|string {
        $time = $time > 0 ? $time : time();
        if ($week === true) {
            if (strtotime($top) <= $time && strtotime($end) >= $time) {
                return "$top ~ $end";
            }
        } elseif ($week === false || $week === 0 || date('N', $time) == $week) {
            $topArr = explode(':', $top);
            $topHour = (int) $topArr[0];
            $topTime = (int) (($topArr[1] ?? 0) ?: 0);
            $hour = (int) date('G', $time);
            $times = (int) date('i', $time);
            if ($hour > $topHour || ($hour == $topHour && ($topTime == 0 || $times >= $topTime))) {
                $endArr = explode(':', $end);
                $endHour = (int) $endArr[0];
                $endTime = (int) (($endArr[1] ?? 0) ?: 0);
                $res = function($h, $i, $hs, $is) use ($top, $end, $week, $time) {
                    $h = strlen($h) == 1 ? "0$h" : $h;
                    $i = strlen($i) == 1 ? "0$i" : $i;
                    $hs = strlen($hs) == 1 ? "0$hs" : $hs;
                    $is = strlen($is) == 1 ? "0$is" : $is;
                    if ($week === false || $week === 0) {
                        return date("Y-m-d $h:$i:00", $time) . " ~ " . date("Y-m-d $hs:$is:00", $time);
                    }
                    $times = $time - (60 * 60 * 24 * ((int) date('N', $time) - 1)) + (60 * 60 * 24 * ($week - 1));
                    return date("Y-m-d $h:$i:00", $times) . " ~ " . date("Y-m-d $hs:$is:00", $times);
                };
                if ($hour < $endHour) {
                    return $res($topHour, $topTime, $endHour, $endTime);
                } elseif ($endTime > 0) {
                    if ($hour == $endHour && $times <= $endTime) {
                        return $res($topHour, $topTime, $endHour, $endTime);
                    }
                }
            }

        }
        return false;
    }

    /**
     * 获取格式
     * @param $str
     * @return string|array
     */
    public static function getFormat($str): string|array {
        return pathinfo($str, PATHINFO_EXTENSION);
    }

    /**
     * 删除格式
     * @param string $str
     * @param null   $format
     * @return string
     */
    public static function delFormat(string $str, $format = null): string {
        $format = !isset($format) ? self::getFormat($str) : $format;
        return trim(($format ? substr($str, 0, (strlen($str) - strlen($format) - 1)) : $str), '.');
    }

    /**
     * 替换内容
     * @param string|null $string 要替换的string
     * @param array       $array  ['key'=>'要替换的内容']
     * @param string      $symbol key前台符号
     * @return string
     */
    public static function tag(string|null $string, array $array = [], string $symbol = '%'): string {
        if (!empty($string)) {
            $array = array_combine(array_map(fn($key) => ($symbol . $key . $symbol), array_keys($array)), array_values($array));
            $result = strtr($string, $array);
            $result = preg_replace("/" . $symbol . "[^" . $symbol . "]+" . $symbol . "/", '', $result);
            $string = trim($result);
        }
        return $string ?? '';
    }

    /**
     * sign生成和验证,升序排序
     * @param array       $data    加密array
     * @param string      $key     加密key
     * @param string|bool $verify  是否验证,true使用data的sign,string直接验证
     * @param string      $signKey 验证时的key
     * @return bool|string
     */
    public static function sign(array $data, string $key, string|bool $verify = false, string $signKey = 'sign'): bool|string {
        $splicing = '';
        ksort($data);
        if (isset($data[$signKey])) {
            $verify = ($verify === true ? $data[$signKey] : $verify);
            unset($data[$signKey]);
        }
        foreach ($data as $k => $v) {
            $splicing .= $k . '=' . (is_array($v) ? json_encode($v) : $v) . '&';
        }
        $sign = strtolower(md5(trim($splicing, '&') . $key));
        return $verify ? ($sign == $verify) : $sign;
    }

    /**
     * 生成订单号
     * @param string $token 自定标识,为空随机生成token
     * @param int    $unix  顺序,为空按当前时间13位
     * @param string $type  md516 md532 sha256 默认crc32
     * @return string
     */
    public static function getOrderId(string $token = '', int $unix = 0, string $type = ''): string {
        $token = (!empty($token) ? $token : static::getToken());
        $prefix = match ($type) {
            'md5-16'  => substr(md5($token), 8, 16),
            'md5-32'  => md5($token),
            'sha-256' => hash('sha256', $token),
            default   => dechex(crc32($token)),
        };
        $unix = (!empty($unix) ? $unix : static::getUnix());
        return $prefix . bin2hex(pack('N', $unix));
    }

    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @param string $name    字符串
     * @param bool   $type    转换类型 true不使用_,false=使用_
     * @param bool   $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    public static function stringConvert(string $name, bool $type = true, bool $ucfirst = true): string {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        }
        return strtolower(trim(preg_replace('/[A-Z]/', '_\\0', $name), '_'));
    }

    /**
     * 替换内容
     * @param string $str
     * @param string $old
     * @param string $new
     * @return string
     */
    public static function strRep(string $str, string $old, string $new = ''): string {
        return str_replace($old, $new, $str);
    }

    /**
     * 获取格式
     * @param $str
     * @return string|array
     */
    public static function getFileFormat($str): string|array {
        return pathinfo($str, PATHINFO_EXTENSION);
    }

    /**
     * 删除格式
     * @param string $str
     * @param null   $format
     * @return string
     */
    public static function delFileFormat(string $str, $format = null): string {
        $format = !isset($format) ? self::getFileFormat($str) : $format;
        return trim(($format ? substr($str, 0, (strlen($str) - strlen($format) - 1)) : $str), '.');
    }

    /**
     * 获取文件名
     * @param string $str    字符串
     * @param string $format 是否删除格式(结尾要删除的内容)
     * @return string
     */
    public static function getFileName(string $str, string $format = ''): string {
        return basename($str, $format);
    }

    /**
     * 获取缓冲区内容
     * @param $data //闭包
     * @return string|bool
     */
    public static function obCache($data): string|bool {
        ob_start();
        if (is_callable($data)) {
            $data();
        } else {
            echo $data;
        }
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }


    /**
     * 是否包含
     * @param       $str
     * @param       $in
     * @param false $type //是否使用逗号
     * @return bool
     */
    public static function strIn($str, $in, bool $type = false): bool {
        $str = !empty($type) ? "," . $str . "," : $str;
        $in = !empty($type) ? "," . $in . "," : $in;
        if (str_contains($str, $in)) {
            return true;
        }
        return false;
    }

    /**
     * @param string $string
     * @param array  $tag
     * @param array  $pattern
     * @param array  $replacement
     * @return array|string|null
     */
    public static function strPreg(string $string, array $tag, array $pattern = [], array $replacement = []): array|string|null {
        foreach ($tag as $k => $v) {
            $pattern[] = '/{' . $k . '}/';
            $replacement[] = $v;
        }
        if (!empty($pattern)) {
            $string = preg_replace($pattern, $replacement, $string);
        }
        return $string;
    }

    /**
     * 将字符串中的连续多个空格转换为一个空格
     * @param $str
     * @return string
     */
    public static function mergeSpaces($str): string {
        return preg_replace("/\s(?=\s)/", "\\1", $str);
    }

    /**
     * @param $str
     * @return array|string
     */
    public static function trim($str): array|string {
        return str_replace([" ", "　", "\t", "\n", "\r"], ["", "", "", "", ""], $str);
    }

    /**
     * 获取解析IP
     * @param $data
     * @return string
     */
    public static function getHostIp($data): string {
        return gethostbyname($data);
    }

    /**
     * 生成Token
     * @return string
     */
    public static function getToken(): string {
        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }


    /**
     * 随机生成ip
     * @return bool|string
     */
    public static function randIp(): bool|string {
        $ip_long = [
            ['607649792', '608174079'], // 36.56.0.0-36.63.255.255
            ['1038614528', '1039007743'], // 61.232.0.0-61.237.255.255
            ['1783627776', '1784676351'], // 106.80.0.0-106.95.255.255
            ['2035023872', '2035154943'], // 121.76.0.0-121.77.255.255
            ['2078801920', '2079064063'], // 123.232.0.0-123.235.255.255
            ['-1950089216', '-1948778497'], // 139.196.0.0-139.215.255.255
            ['-1425539072', '-1425014785'], // 171.8.0.0-171.15.255.255
            ['-1236271104', '-1235419137'], // 182.80.0.0-182.92.255.255
            ['-770113536', '-768606209'], // 210.25.0.0-210.47.255.255
            ['-569376768', '-564133889'], // 222.16.0.0-222.95.255.255
        ];
        $rand_key = mt_rand(0, 9);
        return long2ip(mt_rand($ip_long[$rand_key][0], $ip_long[$rand_key][1]));
    }

    /**
     * 生成随机字符串
     * @param $length
     * @return string
     */
    public static function randStr($length): string {
        return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890'), 0, $length);
    }

    /**
     * 获取别名
     * @param string $name
     * @param bool   $type true=只返回别名string,false返回array(名,别名)
     * @return array|string
     */
    public static function getAsName(string $name, bool $type = false): array|string {
        preg_match('/(\w+[^,]*)\s+as\s+(\w+[^,]*)/i', trim($name), $arr);
        return (!empty($type) ? trim($arr[2] ?? $name) : [trim($arr[1] ?? $name), trim($arr[2] ?? $name)]);
    }

    /**
     * @param array $array 要转换的array
     * @param bool  $type  是否支持多级
     * @return stdClass
     */
    public static function arrToObj(array $array, bool $type = true): stdClass {
        $obj = new stdClass();
        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($type)) {
                $obj->$key = static::arrToObj($value);
            } else {
                $obj->$key = $value;
            }
        }
        return $obj;
    }

    /**
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     * @return mixed
     * @throws Exception
     */
    public static function throwNew(string $message = "", int $code = 0, Throwable|null $previous = null) {
        throw new Exception($message, $code, $previous);
    }

    /**
     * 执行次数
     * @param callable $callable
     * @param int      $hits
     * @param mixed    $res
     * @return mixed
     */
    public static function execHits(callable $callable, int $hits = 2, mixed $res = ['code', 200]): mixed {
        $number = 1;
        while (true) {
            $data = $callable($number);
            $number++;
            if ($number > $hits) {
                return $data;
            } elseif (is_array($res) && is_array($data)) {
                if (($data[$res[0]] ?? 1) == ($res[1] ?? 2)) {
                    return $data;
                }
            } elseif ($data == $res) {
                return $data;
            }
        }
    }

    /**
     * 路径拼接,后面 不带 /
     * @param string $dir  绝对路径
     * @param string $path 相对路径
     * @return string
     */
    public static function dirPath(string $dir, string $path = ''): string {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        $path = $path ? (($path == '/') ? $path : (DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) : DIRECTORY_SEPARATOR;
        return rtrim(rtrim($dir . $path, DIRECTORY_SEPARATOR), '/');
    }


    /**
     * 加密密码
     * @param string $pass
     * @return string
     */
    public static function enPass(string $pass): string {
        return password_hash($pass, PASSWORD_DEFAULT);
    }

    /**
     * 密码验证
     * @param string $pass
     * @param string $hash
     * @return bool
     */
    public static function verifyPass(string $pass, string $hash): bool {
        return password_verify($pass, $hash);
    }

    /**
     * preg_replace("/Host: ?(.*?)\r\n/", "Host: $host\r\n", $buffer)
     * @param array|string $header 头部信息
     * @param array        $edit   要修改的头部信息,bool.false为删除
     * @return array|string
     */
    public static function header(array|string $header, array $edit = []): array|string {
        $i = 0;
        $head = [];
        $headers = [];
        $header = is_array($header) ? $header : explode("\r\n", trim($header));
        foreach ($header as $val) {
            if (str_contains($val, ':')) {
                [$k, $v] = explode(': ', $val, 2);
                $ks = str_replace('-', '_', strtolower(trim($k)));
                $headers[$ks] = Frame::isJson($v) ?: $v;
                if (!empty($edit)) {
                    if (isset($edit[$ks])) {
                        if ($edit[$ks] !== false) {
                            $head[$k] = $edit[$ks];
                        }
                    } elseif (isset($edit[$k])) {
                        if ($edit[$k] !== false) {
                            $head[$k] = $edit[$k];
                        }
                    } else {
                        $head[$k] = $v;
                    }
                }
            } elseif (!empty($edit)) {
                ++$i;
                $head['@tool' . $i] = $val;
            }
        }
        if (!empty($head)) {
            $heads = '';
            foreach ($head as $k => $v) {
                if (str_starts_with($k, '@tool')) {
                    $heads .= "$v\r\n";
                } else {
                    $heads .= "$k: $v\r\n";
                }
            }
            return "$heads\r\n";
        }
        return $headers;
    }
}