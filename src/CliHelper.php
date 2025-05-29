<?php

namespace AlonePhp\Code;

use ReflectionClass;
use ReflectionUnionType;
use ReflectionNamedType;

/**
 * screen -S name -dm && screen -S name -X stuff "php-v \n"
 * mysqldump -u帐号 -p密码 --databases 名称 >/awww/oubo.sql --verbose
 */
class CliHelper {
    protected static array $cache = [];

    /**
     * @param string     $class  类名,有namespace要带上
     * @param int        $row    cli 左则位数
     * @param int        $len    cli 总长度
     * @param array|bool $config new参数
     * @param bool       $mode   config是否使用...传参,[11,22,33],true=new(11,22,33),false=new([11,22,33])
     * @param bool       $def    是否提示默认值
     * @return void
     */
    public static function cli(string $class, int $row = 21, int $len = 90, array|bool $config = [], bool $mode = false, bool $def = false): void {
        $time = microtime(true);
        echo str_repeat("=", $len) . PHP_EOL;
        $array = static::exec($class, $config, $mode, $def);
        echo static::show($array, $row, $len);
        $date = date("Y-m-d H:i:s");
        $line = $len - static::strlen($date);
        $int = (int) ceil($line / 2);
        //$str = (isset($array['title']) ? '' : (str_repeat("-", $len) . PHP_EOL));
        $str = str_repeat(" ", $int) . $date . str_repeat(" ", $int) . PHP_EOL;
        if (empty(isset($array['title']))) {
            $time = (microtime(true) - $time);
            $line = $len - static::strlen($time);
            $int = (int) ceil($line / 2);
            $str .= str_repeat("-", $len) . PHP_EOL;
            $str .= str_repeat(" ", $int) . $time . str_repeat(" ", $int) . PHP_EOL;
        }
        $str .= str_repeat("=", $len) . PHP_EOL;
        echo $str;
    }

    /**
     * 生成类使用文档和使用
     * @param string     $class  类名,有namespace要带上
     * @param array|bool $config new参数,false返回可执行信息
     * @param bool       $mode   config是否使用...传参,[11,22,33],true=new(11,22,33),false=new([11,22,33])
     * @param bool       $def    是否提示默认值
     * @param array      $array
     * @return array
     */
    public static function exec(string $class, array|bool $config = [], bool $mode = false, bool $def = false, array $array = []): array {
        global $argv;
        $php = 'php ' . ($argv[0] ?? 'file') . ' ';
        $conf = static::get($class);
        $arr = Frame::getArr($conf, 'help', []);
        $title = ($arr['name'] ?: 'help');
        foreach (($arr['list'] ?? []) as $key => $val) {
            $doc = $php . $key;
            $j = 0;
            $type = '';
            foreach ($val['list'] ?? [] as $v) {
                ++$j;
                $doc .= " [" . $v['doc'] . "]";
                $type .= " [" . $v['val'] . "]";
            }
            $array[($val['name'] ?? $key)] = $doc . (!empty($def) ? (" |" . $type) : '');
        }
        if (!empty($name = ($argv[1] ?? ''))) {
            if (isset($conf['method'][$name])) {
                $data = array_slice($argv, 2);
                $list = $conf['method'][$name];
                if (!empty($list)) {
                    $val = array_values(($list['val'] ?? []));
                    foreach ($val as $k => $v) {
                        if (empty(isset($data[$k]))) {
                            $data[$k] = $v;
                        }
                    }
                    $data = array_slice($data, 0, count($val));//参数列表
                }
                $new = empty($conf['method'][$name]['static']);//是否要new
                $class = '\\' . trim($class, '\\');
                $construct = [];
                if (!empty($new)) {
                    if (!empty($construct = ($conf['magic']['__construct'] ?? [])) && !empty($values = ($construct['val'] ?? ''))) {
                        $construct = array_values($values);
                    }
                    if (empty(is_bool($config))) {
                        if (!empty($config)) {
                            if (!empty($mode)) {
                                $class = (new $class(...$config));
                            } else {
                                $class = (new $class($config));
                            }
                        } elseif (!empty($construct)) {
                            $class = (new $class(...$construct));
                        } else {
                            $class = (new $class());
                        }
                    }
                }
                $doc = Frame::getArr($conf, 'help.list.' . $name . '.name');
                if (empty(is_bool($config))) {
                    echo $doc . ': ' . $name . '(' . (!empty($data) ? (trim(join(',', $data), ',')) : '') . ')' . PHP_EOL;
                    call_user_func_array([$class, $name], $data);
                }
                return ['name' => $name, 'data' => $data, 'static' => $new, 'conf' => $construct, 'doc' => Frame::getArr($conf, 'help.list.' . $name . '.name')];

            }
        }
        return ['title' => $title, 'list' => $array];
    }

    /**
     * 获取类的信息
     * @param string      $class 类名,有namespace要带上
     * @param string|null $key   attr=属性值列表,method=公开方法列表,attribute=属性列表信息,magic=魔术方法列表,help=类说明
     * @param mixed       $def
     * @return mixed
     */
    public static function get(string $class, string|null $key = null, mixed $def = ''): mixed {
        $keys = md5(trim(trim($class, '\\'), '/'));
        if (empty($arr = (static::$cache[$keys] ?? []))) {
            $reflection = new ReflectionClass($class);
            $methods = $reflection->getMethods();
            $attribute = [];
            $method = [];
            $magic = [];
            $attr = [];
            $help = [];
            foreach ($methods as $item) {
                $name = $item->getName();
                if ($item->isPublic()) {
                    $param = [];
                    $parameters = $item->getParameters();//参数列表
                    foreach ($parameters as $params) {
                        $Ptype = $params->getType();//参数类型对像
                        $typeName = '';
                        if ($Ptype instanceof ReflectionNamedType) {
                            $typeName = $Ptype->getName();
                        } elseif ($Ptype instanceof ReflectionUnionType) {
                            $typeArr = [];
                            $types = $Ptype->getTypes();
                            foreach ($types as $unionType) {
                                $typeArr[] = $unionType->getName();
                            }
                            $typeName = join('|', $typeArr);
                        }
                        if (!empty($params->isDefaultValueAvailable())) {
                            $pval = $params->getDefaultValue();//默认值
                        } else {
                            //没有默认值设置默认值
                            $TypeArr = explode('|', strtolower($typeName));
                            if (in_array('string', $TypeArr)) {
                                $pval = '';
                            } elseif (in_array('array', $TypeArr) || in_array('iterable', $TypeArr)) {
                                $pval = [];
                            } elseif (in_array('bool', $TypeArr) || in_array('false', $TypeArr)) {
                                $pval = false;
                            } elseif (in_array('null', $TypeArr)) {
                                $pval = null;
                            } elseif (in_array('callable', $TypeArr) || in_array('closure', $TypeArr)) {
                                $pval = function() {};
                            } else {
                                $pval = 0;
                            }
                        }
                        $pName = $params->getName();                  //参数名字
                        $param['val'][$pName] = $pval;                //默认值
                        $param['type'][$pName] = $typeName;           //参数类型
                        $param['def'][$pName] = $params->isOptional();//是否有默认值
                    }
                    $data = [
                        'static' => $item->isStatic(),//是否静态方法
                        'val'    => $param['val'] ?? [],//默认值
                        'type'   => $param['type'] ?? [],//参数类型
                        'def'    => $param['def'] ?? [],//是否有默认值
                        'help'   => $param['help'] ?? [],//是否有默认值
                    ];
                    if (str_starts_with($name, '__')) {
                        $magic[$name] = $data;
                    } else {
                        $method[$name] = $data;
                        //方法说明
                        $doc = $item->getDocComment();
                        if (!empty($doc)) {
                            $lines = explode("\n", $doc);
                            if (!empty($description = ($lines[1] ?? ''))) {
                                $docName = trim(preg_replace('/^\s*\*\s?/', '', $description));
                                if (!empty($docName) && !str_starts_with($docName, '@')) {
                                    $help[$name]['name'] = $docName; // 第二行是描述
                                }
                            }
                        }
                        //方法参数说明
                        $doc = $reflection->getMethod($name)->getDocComment();
                        preg_match_all('/@param\s+([^\s]+)\s+\$([^\s]+)\s+(.*)/', $doc, $matches, PREG_SET_ORDER);
                        foreach ($matches as $match) {
                            $explain = [];
                            $explain['type'] = $match[1] ?? ($data['type'][$match[2]] ?? '');//参数类型
                            $explain['val'] = ($data['val'][$match[2]] ?? '');               //默认值
                            $explain['doc'] = $match[3] ?? '';                               //参数说明
                            $help[$name]['list'][$match[2]] = $explain;
                        }
                    }
                }
            }
            $properties = $reflection->getProperties();
            foreach ($properties as $item) {
                if ($item->isPublic()) {
                    $static = $item->isStatic();//是否静态属性
                    $Ptype = $item->getType();  //参数类型对像
                    $typeName = '';
                    if ($Ptype instanceof ReflectionNamedType) {
                        $typeName = $Ptype->getName();
                    } elseif ($Ptype instanceof ReflectionUnionType) {
                        $typeArr = [];
                        $types = $Ptype->getTypes();
                        foreach ($types as $unionType) {
                            $typeArr[] = $unionType->getName();
                        }
                        $typeName = join('|', $typeArr);
                    }
                    if (!empty($static)) {
                        $pval = $item->getValue();
                    } else {
                        if (!empty($data = ($magic['__construct'] ?? '')) && !empty($val = ($data['val'] ?? []))) {
                            $pval = ($item->getValue($reflection->newInstanceArgs(array_values($val))));
                        } else {
                            $pval = $item->getValue($reflection->newInstanceArgs());
                        }
                    }
                    $pName = $item->getName();//参数名字
                    $attribute[$pName] = [
                        'static' => $static,//是否静态方法
                        'val'    => $pval,//默认值
                        'type'   => $typeName,//参数类型
                    ];
                    $attr[$pName] = $pval;
                }
            }
            $arr['help'] = [
                //类说明
                'name' => trim(preg_replace('/^\s*\*\s?/m', '', trim(($reflection->getDocComment() ?: ''), "/* \n\r\t"))),
                //方法有参数说明,只对公开方法列表,一定要有注视才会显示
                'list' => $help
            ];
            $arr['magic'] = $magic;        //魔术方法列表
            $arr['method'] = $method;      //公开方法列表
            $arr['attribute'] = $attribute;//属性列表信息
            $arr['attr'] = $attr;          //属性值列表
            static::$cache[$keys] = $arr;
        }
        return Frame::getArr($arr, $key, $def);
    }

    /**
     * cli显示
     * @param mixed $array
     * @param int   $row
     * @param int   $len
     * @return string
     */
    public static function show(mixed $array, int $row = 21, int $len = 90): string {
        if (isset($array['title']) && isset($array['list'])) {
            $line = $len - static::strlen($array['title']);
            $int = (int) ceil($line / 2);
            $str = str_repeat(" ", $int) . $array['title'] . str_repeat(" ", $int) . PHP_EOL;
            $j = 0;
            foreach ($array['list'] as $k => $v) {
                ++$j;
                $str .= "||" . str_repeat("-", $len - 2) . PHP_EOL;
                $iv = $row - static::strlen($k . $j);
                $left = "||" . $j . '.' . $k . str_repeat(" ", $iv) . '|';
                $line = $len - static::strlen($left) - static::strlen($v);
                $right = str_repeat(" ", 2) . $v . str_repeat(" ", $line);
                $str .= $left . $right . PHP_EOL;
            }
            $str .= "||" . str_repeat("-", $len - 2) . PHP_EOL;
        }
        return ($str ?? '');
    }

    /**
     * 计算长度
     * @param $string
     * @return int
     */
    public static function strlen($string): int {
        $length = 0;
        for ($i = 0; $i < mb_strlen($string, 'UTF-8'); $i++) {
            $char = mb_substr($string, $i, 1, 'UTF-8');
            if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $char)) {
                $length += 2;
            } else {
                $length += 1;
            }
        }
        return $length;
    }
}