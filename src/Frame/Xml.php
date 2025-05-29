<?php

namespace AlonePhp\Code\Frame;

use DOMDocument;

trait Xml{
    /**
     * XML转array|object
     * @param string|array $xml
     * @param bool         $type //true=array false=object
     * @return mixed
     */
    public static function toArr(string|array $xml, bool $type = true): mixed {
        try {
            $xmlData = (is_array($xml) ? (@file_get_contents($xml[key($xml)])) : $xml);
            $obj = simplexml_load_string($xmlData, "SimpleXMLElement", LIBXML_NOCDATA);
            return static::isJson(static::json($obj, false), $type);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * array|object转xml
     * @param array|object $arr
     * @param string|null  $name
     * @param array|null   $config
     * @param string       $tag
     * @param int          $i
     * @param string       $attr
     * @param string       $xml
     * @return string
     */
    public static function toXml(array|object $arr, string|null $name = "name", array|null $config = ["version" => "1.0", "encoding" => "UTF-8"], int $i = 0, string $tag = "@attributes", string $attr = "", string $xml = ""): string {
        $arr = (is_array($arr) ? json_decode(json_encode($arr)) : $arr);
        if (!empty($tabArr = ($arr->{$tag} ?? []))) {
            foreach ($tabArr as $a => $b) {
                $attr .= " $a=\"$b\"";
            }
            $attr = (" " . trim($attr, " "));
            unset($arr->{$tag});
        }
        if (!empty($config) && $i == 0) {
            $xml .= "<?xml";
            foreach ($config as $k => $v) {
                $xml .= " {$k}=\"{$v}\"";
            }
            $xml .= "?>";
        }
        $xml .= (!empty($name) ? "<{$name}{$attr}>" : "");
        if (!empty($arr)) {
            foreach ($arr as $k => $v) {
                if (!empty($v) && (is_array($v) || is_object($v))) {
                    if (is_array($v)) {
                        foreach ($v as $b) {
                            $xml .= static::arrToXml($b, $k, $config, ($i + 1), $tag);
                        }
                    } else {
                        $xml .= static::arrToXml($v, $k, $config, ($i + 1), $tag);
                    }
                } else {
                    $xml .= "<{$k}>" . ((is_array($v) || is_object($v)) ? "" : $v) . "</{$k}>";
                }
            }
        }
        $xml .= (!empty($name) ? "</{$name}>" : "");
        return $xml;
    }

    /**
     * 支持一级属性标签
     * XML转Array
     * @param array|string $data //str为xml内容,array为文件
     * @return array
     */
    public static function xmlToArr(array|string $data): array {
        try {
            $content = (is_array($data) ? (@file_get_contents($data[key($data)])) : $data);
            $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
            $attr = [];
            $attributes = $xml->attributes();
            if (count($attributes) > 0) {
                foreach ($attributes as $k => $v) {
                    $attr[$k] = (string) $v;
                }
            }
            $arr['tag'] = self::getXmlTag($content);
            $arr['name'] = $xml->getName();
            $arr['attr'] = $attr;
            $arr['data'] = self::toArrHandle($xml->children());
            return $arr;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 支持一级属性标签
     * XML转Array
     * @param array|string $data //str为xml内容,array为文件
     * @return array
     */
    public static function xmlToArray(array|string $data): array {
        try {
            $content = (is_array($data) ? (@file_get_contents($data[key($data)])) : $data);
            $xml = new DOMDocument();
            $xml->loadXML($content);
            $root = $xml->documentElement;                      //根目录
            $index = $xml->getElementsByTagName($root->tagName);//根对像
            $obj = $index->item(0)->attributes;
            if ($obj->length > 0) {
                for ($i = 0; $i < $obj->length; $i++) {
                    $tag = $obj->item($i);
                    if (!empty($tag->nodeName)) {
                        $attr[$tag->nodeName] = $tag->nodeValue;
                    }
                }
            }
            $arr['tag'] = self::getXmlTag($content);
            $arr['name'] = $root->tagName;
            $arr['attr'] = (!empty($attr) ? $attr : []);
            $arr['data'] = self::toArrayHandle($index->item(0));
            return $arr;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 支持一级属性标签
     * Array转Xml
     * @param array       $arr      //array
     * @param string|null $name     //根名称
     * @param string      $version  //板本
     * @param string      $encoding //编码
     * @return string
     */
    public static function arrToXml(array $arr, string|null $name = "XmlName", string $version = "1.0", string $encoding = "UTF-8"): string {
        $tagFun = function($arr, $str = '') {
            if (count($arr) > 0) {
                foreach ($arr as $k => $v) {
                    $str .= $k . "=\"{$v}\" ";
                }
                $str = " " . trim($str);
            }
            return $str;
        };
        $arr['tag']['version'] = ($arr['tag']['version'] ?? $version);
        $arr['tag']['encoding'] = ($arr['tag']['encoding'] ?? $encoding);
        $xml = (($name === null) ? "" : "<?xml" . ($tagFun($arr['tag'])) . "?>");
        $xml .= (($name === null) ? "" : "<" . ($arr['name'] ?? $name) . $tagFun(($arr['attr'] ?? [])) . ">");
        $xml .= self::toXmlHandle(($arr['data'] ?? ''));
        $xml .= (($name === null) ? "" : "</" . ($arr['name'] ?? $name) . ">");
        return $xml;
    }

    /**
     * @param $content
     * @return array
     */
    protected static function getXmlTag($content): array {
        preg_match_all("/ (.*?)\=(\'|\")(.*?)(\'|\")/i", explode("?>", $content)[0], $array);
        if (isset($array[1])) {
            foreach ($array[1] as $k => $v) {
                $arr[$v] = $array[3][$k] ?? '';
            }
        }
        return (!empty($arr) ? $arr : []);
    }

    /**
     * Array转Xml处理
     * @param $arr
     * @return string
     */
    protected static function toXmlHandle($arr): string {
        $xml = '';
        $attr = function($v, $attrStr = '') {
            $attr = ($v['attr'] ?? []);
            if (count($attr) > 0) {
                foreach ($attr as $a => $s) {
                    $attrStr .= $a . "=\"{$s}\" ";
                }
                $attrStr = " " . trim($attrStr);
            }
            return $attrStr;
        };
        if (is_array($arr) && !empty($arr)) {
            foreach ($arr as $k => $v) {
                $attrStr = $attr($v);
                if (empty(isset($v['data']))) {
                    foreach ($v as $s) {
                        $attrStr = $attr($s);
                        $xml .= "<" . ($k . $attrStr) . ">";
                        $xml .= (is_array($s['data']) ? self::toXmlHandle($s['data']) : $s['data']);
                        $xml .= "</" . $k . ">";
                    }
                } else {
                    $xml .= "<" . ($k . $attrStr) . ">";
                    $xml .= ($v['data'] ?? '');
                    $xml .= "</" . $k . ">";
                }
            }
        }
        return $xml;
    }

    /**
     * XMl转Array处理
     * @param $xml
     * @return array
     */
    protected static function toArrHandle($xml): array {
        foreach ($xml as $child) {
            $attr = [];
            $attributes = $child->attributes();
            if (count($attributes) > 0) {
                foreach ($attributes as $k => $v) {
                    $attr[$k] = (string) $v;
                }
            }
            $tagName = $child->getName();
            $array = ['attr' => $attr];
            if ($child->count() > 0) {
                $array['data'] = self::toArrHandle($child);
                $arr[$tagName][] = $array;
            } else {
                $array['data'] = (string) $child;
                if (isset($arr[$tagName])) {
                    if (isset($arr[$tagName]['data'])) {
                        $arr[$tagName] = array_merge([$arr[$tagName]], [$array]);
                    } else {
                        $arr[$tagName][] = $array;
                    }
                } else {
                    $arr[$tagName] = $array;
                }
            }
        }
        return !empty($arr) ? $arr : [];
    }

    /**
     * XMl转Array处理
     * @param        $obj
     * @param string $type
     * @return array
     */
    protected static function toArrayHandle($obj, string $type = ''): array {
        $child = $obj->childNodes;
        $arr = [];
        if ($child->length > 0) {
            foreach ($child as $v) {
                $attr = [];
                if (!empty($tagName = ($v->tagName ?? ''))) {
                    if ($v->attributes->length > 0) {
                        foreach ($v->attributes as $tag) {
                            if (!empty($nodeName = ($tag->nodeName ?? ''))) {
                                $attr[$nodeName] = ($type == 'val' ? $tag->nodeValue : $tag->textContent);
                            }
                        }
                    }
                    $array = ['attr' => $attr];
                    if ($v->childElementCount > 0) {
                        $array['data'] = self::toArrayHandle($v, $type);
                        $arr[$tagName][] = $array;
                    } else {
                        $array['data'] = ($type == 'val' ? $v->nodeValue : $v->textContent);
                        if (isset($arr[$tagName])) {
                            if (isset($arr[$tagName]['data'])) {
                                $arr[$tagName] = array_merge([$arr[$tagName]], [$array]);
                            } else {
                                $arr[$tagName][] = $array;
                            }
                        } else {
                            $arr[$tagName] = $array;
                        }
                    }
                }
            }
        }
        return $arr;
    }
}