<?php

namespace AlonePhp\Code\Frame;

trait Domain {
    /**
     * 获取url详细/修改url中的get参数
     * @param string       $url
     * @param array|string $set    要设置的get
     * @param bool         $encode get是否url编码
     * @return array
     */
    public static function urlShow(string $url, array|string $set = [], bool $encode = false): array {
        $parse = parse_url($url);
        $array['scheme'] = $parse['scheme'] ?? 'http';
        $array['host'] = $parse['host'] ?? '';
        $array['port'] = $parse['port'] ?? '';
        $array['path'] = $parse['path'] ?? '';
        $query = $parse['query'] ?? '';
        $get = [];
        parse_str($query, $get);
        $array['get'] = $get;
        if (is_string($set)) {
            $get = [];
            parse_str(($set ?: ''), $get);
            $array['get'] = array_merge($array['get'], $get);
        } else {
            $array['get'] = array_merge($array['get'], $set);
        }
        $array['fragment'] = $parse['fragment'] ?? '';
        $array['query'] = '';
        if (!empty($array['get'])) {
            foreach ($array['get'] as $k => $v) {
                $val = trim(($encode === true ? urlencode($v) : $v));
                $array['query'] .= $k . '=' . $val . '&';
            }
        }
        $array['query'] = trim($array['query'], '&');
        $array['url'] = $array['scheme'] . '://' . $array['host']
                        . (!empty($array['port']) ? ':' . $array['port'] : '')
                        . ($array['path'] ?? "")
                        . (!empty($array['query']) ? ('?' . $array['query']) : '')
                        . (!empty($array['fragment']) ? '#' . $array['fragment'] : '');
        return $array;
    }

    /**
     * 判断白名单ip列表
     * @param string       $ip   要判断的ip
     * @param array|string $list 允许ip列表
     * @param bool         $type 是否允许IP段，使用[0-255],全部使用*
     * @return bool true=允许访问，false=禁止访问
     */
    public static function ifIp(string $ip, array|string $list, bool $type = true): bool {
        if (!empty($list) && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            $rest = is_array($list) ? $list : explode(',', $list);
            foreach ($rest as $v) {
                if (!empty($v)) {
                    if ($ip == $v) {
                        return true;
                    } elseif ($type === true) {
                        if (!empty(in_array($v, ['*', '*.*.*.*']))) {
                            return true;
                        }
                    }
                }
            }
            if ($type === true) {
                $arrIp = explode('.', $ip);
                foreach ($rest as $v) {
                    if (str_contains($v, ".")) {
                        $ifIp = $arrIp;
                        $arr = explode('.', $v);
                        if (str_contains($v, "*") || (str_contains($v, "[") && str_contains($v, "]"))) {
                            foreach ($arr as $key => $val) {
                                if (isset($ifIp[$key])) {
                                    if ($val == '*') {
                                        $ifIp[$key] = $val;
                                    } elseif (str_starts_with($val, '[') && str_ends_with($val, ']')) {
                                        $ipVal = $ifIp[$key];
                                        $arrA = explode('[', $val);
                                        $arrB = explode(']', ($arrA[1] ?? ''));
                                        $arrC = explode('-', ($arrB[0] ?? ''));
                                        $min = $arrC[0] ?? 0;
                                        $max = $arrC[1] ?? 255;
                                        if ($ipVal >= $min && $ipVal <= $max) {
                                            $ifIp[$key] = $val;
                                        }
                                    }
                                }
                            }
                        }
                        if (join('.', $ifIp) == $v) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * 判断域名
     * @param string       $domain 当前访问的域名
     * @param array|string $list   *=全部开放 支持*.域名
     * @param bool         $type   是否允许使用*
     * @param int          $i
     * @return bool true=允许访问，false=禁止访问
     */
    public static function ifDomain(string $domain, array|string $list, bool $type = true, int $i = 0): bool {
        $rest = is_array($list) ? $list : explode(',', $list);
        $arr = explode("://", strtolower($domain));
        $array = explode("/", end($arr));
        $host = $array[key($array)];
        $hostArr = explode('.', $host);
        foreach ($rest as $v) {
            $val = strtolower($v);
            if ($host == $val) {
                $i++;
                break;
            }
            if ($type === true) {
                if ($v == '*') {
                    $i++;
                    break;
                } elseif (str_starts_with($val, '*.')) {
                    if (join('.', array_slice($hostArr, -count(explode(".", substr($v, 2))))) == substr($v, 2)) {
                        $i++;
                        break;
                    }
                }
            }
        }
        return !empty($i > 0);
    }

}