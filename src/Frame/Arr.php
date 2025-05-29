<?php

namespace AlonePhp\Code\Frame;

trait Arr {
    /**
     * 取指定二维array的key
     * @param array $array
     * @param array $keys
     * @return array
     */
    public static function arrayColumn(array $array, array $keys): array {
        return array_map(function($item) use ($keys) {
            return array_intersect_key($item, array_flip($keys));
        }, $array);
    }

    /**
     * 数组转Json
     * @param array|object $array
     * @param bool         $int 是否数字检查
     * @return false|string
     */
    public static function json(array|object $array, bool $int = true): bool|string {
        return $int ? json_encode($array, JSON_NUMERIC_CHECK + JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES) : json_encode($array, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES);
    }

    /**
     * 数组转Json 格式化
     * @param array|object $array
     * @param bool         $int 是否数字检查
     * @return bool|string
     */
    public static function jsons(array|object $array, bool $int = true): bool|string {
        return $int ? json_encode($array, JSON_NUMERIC_CHECK + JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT) : json_encode($array, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT);
    }

    /**
     * 数组转Json格式化
     * @param      $data
     * @param bool $type 是否强制int
     * @return string
     */
    public static function jsonFormat($data, bool $type = true): string {
        array_walk_recursive($data, function(&$val) {
            if (!empty($val) && $val !== true && !is_numeric($val)) {
                $val = urlencode($val);
            }
        });
        $data = (empty($type)) ? json_encode($data, JSON_PRETTY_PRINT) : json_encode($data, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
        return urldecode($data);
    }

    /**
     * 判断字符串是否json,返回array
     * @param mixed     $json
     * @param bool|null $associative
     * @param int       $depth
     * @param int       $flags
     * @return mixed
     */
    public static function isJson(mixed $json, bool $associative = true, int $depth = 512, int $flags = 0): mixed {
        $json = json_decode((is_string($json) ? ($json ?: '') : ''), $associative, $depth, $flags);
        return (($json && is_object($json)) || (is_array($json) && $json)) ? $json : [];
    }

    /**
     * 按 $field 设置从 $array中取数据
     * @param array|null $array 数据包
     * @param array      $field @转换,#排除
     *                          [
     *                          'user'                      字段 user    array 中 user 存在 才会返回
     *                          '#pass'                     字段 pass    array 中 排除 pass 建议单独使用
     *                          '%pid'                      字段 pid     array 中 pid  存在并有值 才会返回
     *                          '*pass' => 2,               字段 pass    array 已存在可以强制设置pass=2,支持callable
     *                          'info' => 1,                字段 info    array 不存在或者没有值时 默认:1,支持callable
     *                          '@uid as user_id'           字段 uid     array 中 uid 存在 才会返回 user_id
     *                          '@aid as bid' => 2,         字段 aid     array 中 uid 转为 bid,不存在或者没有值时 默认:2,支持callable
     *                          ]
     * @param bool       $type  是否结合array,如使用了#号时此项会默认true
     * @param array      $arr
     * @return array
     */
    public static function getArray(array|null $array, array $field = [], bool $type = false, array $arr = []): array {
        foreach ($field as $k => $v) {
            if (is_numeric($k)) {
                $prefix = substr($v, 0, 1);
                if ($prefix == '%') {
                    $key = substr($v, 1);
                    if (isset($array[$key])) {
                        $arr[$key] = $array[$key];
                        unset($array[$key]);
                    }
                    continue;
                }
                if ($prefix == '@') {
                    $alias = static::getAsName(substr($v, 1));
                    if (isset($array[$alias[0]])) {
                        $arr[$alias[1]] = $array[$alias[0]];
                        unset($array[$alias[0]]);
                    }
                    continue;
                }
                if ($prefix == '#') {
                    $key = substr($v, 1);
                    if (isset($array[$key])) {
                        unset($array[$key]);
                    }
                    $delete = true;
                    continue;
                }
                if (isset($array[$v])) {
                    $arr[$v] = $array[$v];
                    unset($array[$v]);
                }
                continue;
            }
            $prefix = substr($k, 0, 1);
            if ($prefix == '*') {
                $key = substr($k, 1);
                if (is_callable($v)) {
                    $val = $v($array[$key] ?? '');
                    if (isset($val)) {
                        $arr[$key] = $val;
                    }
                } else {
                    $arr[$key] = $v;
                }
                if (isset($array[$key])) {
                    unset($array[$key]);
                }
                continue;
            }
            if ($prefix == '@') {
                $alias = static::getAsName(substr($k, 1));
                $valve = $array[$alias[0]] ?? '';
                if (is_callable($v)) {
                    if (!empty($val = $v($valve))) {
                        $arr[$alias[1]] = $val;
                    }
                } else {
                    $arr[$alias[1]] = $valve ?: $v;
                }
                if (isset($array[$alias[0]])) {
                    unset($array[$alias[0]]);
                }
                continue;
            }
            $valve = $array[$k] ?? '';
            if (is_callable($v)) {
                $val = $v($valve);
                if (isset($val)) {
                    $arr[$k] = $val;
                }
            } else {
                $arr[$k] = $valve ?: $v;
            }
            if (isset($array[$k])) {
                unset($array[$k]);
            }
        }
        return ((!empty($delete) || $type || empty($field)) ? array_merge($array, $arr) : $arr);
    }


    /**
     * 通过a.b.c.d获取数组内容
     * @param array|null      $array   要取值的数组
     * @param string|null|int $key     支持aa.bb.cc.dd这样获取数组内容
     * @param mixed           $default 默认值
     * @param string          $symbol  自定符号
     * @return mixed
     */
    public static function getArr(array|null $array, string|null|int $key = null, mixed $default = null, string $symbol = '.'): mixed {
        if (isset($key)) {
            if (isset($array[$key])) {
                $array = $array[$key];
            } else {
                $symbol = $symbol ?: '.';
                $arr = explode($symbol, trim($key, $symbol));
                foreach ($arr as $v) {
                    if (isset($v) && isset($array[$v])) {
                        $array = $array[$v];
                    } else {
                        $array = $default;
                        break;
                    }
                }
            }
        }
        return $array ?? $default;
    }

    /**
     * 通过a.b.c.d生成多维数组
     * @param string $key    名字
     * @param mixed  $val    内容
     * @param string $symbol 自定符号
     * @return mixed
     */
    public static function setArr(string $key, mixed $val, string $symbol = '.'): mixed {
        $arr = explode($symbol, trim($key, $symbol));
        $arr[] = $val;
        while (count($arr) > 1) {
            $v = array_pop($arr);
            $k = array_pop($arr);
            $arr[] = [$k => $v];
        }
        return $arr[key($arr)];
    }


    /**
     * 合并两个多维数组
     * @param array $arr
     * @param array $array
     * @return array
     */
    public static function arrMerge(array $arr, array $array): array {
        $merge = $arr;
        foreach ($array as $key => $value) {
            if (is_array($value) && isset($merge[$key]) && is_array($merge[$key])) {
                $merge[$key] = static::arrMerge($merge[$key], $value);
            } else {
                $merge[$key] = $value;
            }
        }
        return $merge;
    }

    /**
     * array生成字符串array
     * @param array       $array //要转换的array
     * @param bool|string $name  string=变量名
     * @param bool        $type  //是否使用var_export, array()
     * @return string
     */
    public static function ArrToPhp(array $array, bool|string $name = false, bool $type = false): string {
        return "\r\n<?php\r\n " . (is_string($name) ? "\$" . $name . "=" : "return ") . self::arrayToString($array, $type) . ";\r\n";
    }

    /**
     * 根据数组的值从小到大排序
     * @param $array
     * @param $key
     * @return array
     */
    public static function arrAsc($array, $key): array {
        array_multisort(array_column($array, $key), SORT_ASC, $array);
        return $array;
    }

    /**
     * 根据数组的值从大到小排序
     * @param $array
     * @param $key
     * @return array
     */
    public static function arrDesc($array, $key): array {
        array_multisort(array_column($array, $key), SORT_DESC, $array);
        return $array;
    }

    /**
     * 判断几维数组
     * @param     $arr
     * @param int $j
     * @return int
     */
    public static function arrLevel($arr, int $j = 0): int {
        if (empty(is_array($arr))) {
            return $j;
        }
        foreach ($arr as $K) {
            $v = self::arrLevel($K);
            if ($v > $j) {
                $j = $v;
            }
        }
        return $j + 1;
    }

    /**
     * 多维数组转1维,清空键名
     * @param       $arr
     * @param array $data
     * @return array
     */
    public static function oneArr($arr, array $data = []): array {
        foreach ($arr as $v) {
            if (is_array($v)) {
                $data = self::oneArr($v, $data);
            } else {
                $data [] = $v;
            }
        }
        return $data;
    }

    /**
     * 数组根据值的长度排序
     * @param array $data //默认由高到低
     * @param bool  $type //true=由低到高,false=由高到低
     * @return array
     */
    public static function arrLenSort(array $data, bool $type): array {
        usort($data, function($a, $b) use ($type) {
            return ($type ? strlen($a) - strlen($b) : strlen($b) - strlen($a));
        });
        return $data;
    }

    /**
     * 不是二维数组返回二维数组
     * @param array $data //判断的数组
     * @param null  $key  //要判断的Key
     * @return array
     */
    public static function isTwoArr(array $data, $key = null): array {
        if (!empty($data)) {
            if (empty(is_array($data[($key ?? key($data))]))) {
                $data = [$data];
            }
        }
        return $data;
    }

    /**
     * 查找指定二维数组字段的值
     * @param      $Data  //要查找的数组
     * @param      $Field //要查找的字段
     * @param      $Val   //要查找的值
     * @param bool $Type  //是否模湖查找
     * @return array
     */
    public static function arraySearchFidel($Data, $Field, $Val, bool $Type = false): array {
        return array_filter($Data, function($Row) use ($Field, $Val, $Type) {
            if (isset($Row[$Field])) {
                if (!empty($Type)) {
                    if (str_contains($Row[$Field], $Val)) {
                        return $Row[$Field];
                    }
                }
                return $Row[$Field] == $Val;
            }
            return false;
        });
    }

    /**
     * 查找全部上级   false查找全部下级
     * @param        $Data //查找的数组
     * @param        $Val  //查找的值
     * @param        $Type //true 查找全部上级   false查找全部下级
     * @param string $Id   //id字段名称
     * @param string $Pid  //上下级字段名称
     * @param string $Key  //返回字段名称的内容
     * @return array
     */
    public static function childParent($Data, $Val, $Type, string $Id = 'id', string $Pid = 'pid', string $Key = 'id'): array {
        if (!empty($Type)) {
            return array_slice(self::getParent($Data, $Val, $Id, $Pid, $Key), 1);
        } else {
            return self::getChild($Data, $Val, $Id, $Pid, $Key);
        }
    }

    /**
     * 获取下拉列表
     * @param        $Data //分类数据
     * @param        $Name //分类字段名称
     * @param string $Id   //数据唯一标识
     * @param string $Pid  //数据库上级id
     * @param array  $Arr
     * @return array
     */
    public static function optionArr($Data, $Name, string $Id = 'id', string $Pid = 'pid', array $Arr = []): array {
        if (!empty($Data)) {
            foreach ($Data as $v) {
                $Arr[$v[$Pid]][] = $v;
            }
            $Data = self::handleOption($Arr, $Name, $Id);
        }
        return $Data;
    }

    /**
     * @param       $Data //分类数据
     * @param       $Name //分类字段名称
     * @param       $Id   //数据唯一标识
     * @param int   $Pid
     * @param array $Arr
     * @param int   $Spec
     * @return array
     */
    private static function handleOption($Data, $Name, $Id, int $Pid = 0, array $Arr = [], int $Spec = 0): array {
        $Spec = $Spec + 2;
        if (isset($Data[$Pid])) {
            if (!empty($Rs = $Data[$Pid])) {
                foreach ($Rs as $v) {
                    $v[$Name] = str_repeat('&nbsp;&nbsp', $Spec) . '|--' . $v[$Name];
                    $Arr = array_merge($Arr, self::handleOption($Data, $Name, $Id, $v[$Id], [$v], $Spec));
                }
            }
        }
        return $Arr;
    }

    /**
     * 获取全部下级id
     * @param        $Data //查找的数组
     * @param        $Val  //查找的值
     * @param string $Id   //id字段名称
     * @param string $Pid  //上下级字段名称
     * @param string $Key  //返回字段名称的内容
     * @param array  $Arr
     * @return array
     */
    private static function getChild($Data, $Val, string $Id = 'id', string $Pid = 'pid', string $Key = 'id', array $Arr = []): array {
        foreach ($Data as $V) {
            if ($V[$Pid] == $Val) {
                $Arr[] = $V[$Key];
                $Arr = array_merge($Arr, self::getChild($Data, $V[$Id], $Id, $Pid, $Key));
            }
        }
        return $Arr;
    }

    /**
     * 获取全部上级id
     * @param        $Data //查找的数组
     * @param        $Val  //查找的值
     * @param string $Id   //id字段名称
     * @param string $Pid  //上下级字段名称
     * @param string $Key  //返回字段名称的内容
     * @param array  $Arr
     * @return array
     */
    private static function getParent($Data, $Val, string $Id = 'id', string $Pid = 'pid', string $Key = 'id', array $Arr = []): array {
        foreach ($Data as $V) {
            if ($V[$Id] == $Val) {
                $Arr[] = $V[$Key];
                $Arr = array_merge($Arr, self::getParent($Data, $V[$Pid], $Id, $Pid, $Key));
            }
        }
        return $Arr;
    }

    /**
     * array生成字符串array
     * @param array $array //要转换的array
     * @param bool  $type  //是否使用var_export, array()
     * @param int   $i
     * @return string
     */
    public static function arrayToString(array $array, bool $type = false, int $i = 0): string {
        if (!empty($type)) {
            return var_export($array, true);
        }
        ++$i;
        $branch = "\r\n";
        $symbol = str_repeat('  ', $i);
        $string = "[" . $branch;
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $string .= "$symbol'" . $key . "' => " . self::arrayToString($value, $type, $i) . "," . $branch;
            } else {
                if (empty(is_numeric($value))) {
                    $value = is_string($value)
                        ? ("'" . addslashes($value) . "'")
                        : (
                        $value === null ? 'null' : ($value === true ? 'true' : (($value === false ? 'false' : $value))));
                }
                $string .= "$symbol'" . $key . "' => " . $value . "," . $branch;
            }
        }
        return rtrim($string, "," . $branch) . $branch . $symbol . ']';
    }
}