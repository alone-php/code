<?php

namespace AlonePhp\Code\Frame;

trait Mime{
    private static array  $MimeData = [];
    private static string $MimeType = __DIR__ . '/../../file/mime.types';
    private static string $MimeJson = __DIR__ . '/../../file/mime.json';

    /**
     * 获取Mime
     * @param $key
     * @return string
     */
    public static function getMime($key): string {
        if (empty(self::$MimeData)) {
            $MimeData = static::isJson(@file_get_contents(self::handleMime()));
            if (!empty($MimeData)) {
                foreach ($MimeData as $k => $v) {
                    $VArr = explode(',', $v);
                    foreach ($VArr as $var) {
                        self::$MimeData[strtoupper($var)] = $k;
                    }
                }
            }
        }
        return static::getArr(self::$MimeData, strtoupper($key));
    }

    /**
     * 通过文件获取文件Mime
     * @param $file
     * @return string
     */
    public static function getFileMime($file): string {
        $fp = finfo_open(FILEINFO_MIME);
        $mime = finfo_file($fp, $file);
        finfo_close($fp);
        $arr = explode(';', $mime);
        return $arr[key($arr)];
    }

    /**
     * 生成mime.json
     * @return string
     */
    private static function handleMime(): string {
        if (empty(is_file(self::$MimeJson))) {
            $items = file(self::$MimeType, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $MimeData = [];
            if (!empty($items)) {
                foreach ($items as $content) {
                    if (preg_match("/\s*(\S+)\s+(\S.+)/", $content, $match)) {
                        $MimeData[trim($match[1], ';')] = trim(join(',', explode(' ', $match[2])), ';');
                    }
                }
            }
            @file_put_contents(self::$MimeJson, json_encode($MimeData, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES));
        }
        return self::$MimeJson;
    }
}