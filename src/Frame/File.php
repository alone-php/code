<?php

namespace AlonePhp\Code\Frame;

use Phar;
use Generator;
use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

trait File {
    /**
     * 复制目录,已存在的文件不会复制
     * @param string $source    源目录
     * @param string $dest      目标目录
     * @param bool   $overwrite true 替换存在文件
     * @return void
     */
    public static function copyDir(string $source, string $dest, bool $overwrite = false): void {
        if (is_dir($source)) {
            if (!is_dir($dest)) {
                static::mkDir($dest);
            }
            $files = scandir($source);
            foreach ($files as $file) {
                if ($file !== "." && $file !== "..") {
                    static::copyDir("$source/$file", "$dest/$file", $overwrite);
                }
            }
        } elseif (file_exists($source) && ($overwrite || !file_exists($dest))) {
            copy($source, $dest);
        }
    }

    /**
     * 删除目录
     * @param string $path
     * @param array  $exclude
     * @return bool
     */
    public static function deleteDir(string $path, array $exclude = []): bool {
        if (is_link($path) || is_file($path)) {
            return unlink($path);
        } elseif (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            $del = true;
            foreach ($files as $file) {
                if (empty($exclude) || !in_array(trim($file, '/'), $exclude)) {
                    (is_dir("$path/$file") && !is_link($path)) ? static::deleteDir("$path/$file") : unlink("$path/$file");
                } else {
                    $del = false;
                }
            }
            return $del && rmdir($path);
        }
        return false;
    }

    /**
     * 复制指定目录和文件,先删除目标再复制
     * @param string $source 源目录
     * @param string $dest   目标目录
     * @param array  $list   复制那些目录和文件
     * @return array
     */
    public static function copyDirFile(string $source, string $dest, array $list = []): array {
        $array = [];
        if (!empty($list)) {
            foreach ($list as $dir => $val) {
                if (is_array($val)) {
                    foreach ($val as $v) {
                        $destFile = static::dirPath($dest, "$dir/$v");
                        if (is_dir($destFile) || is_file($destFile)) {
                            static::deleteDir($destFile);
                        }
                        $sourceFile = static::dirPath($source, "$dir/$v");
                        if (is_dir($sourceFile) || is_file($sourceFile)) {
                            static::copyDir(static::dirPath($source, "$dir/$v"), $destFile);
                            $array[] = $destFile;
                        }
                    }
                } else {
                    $destFile = static::dirPath($dest, "$val");
                    if (is_dir($destFile) || is_file($destFile)) {
                        static::deleteDir($destFile);
                    }
                    $sourceFile = static::dirPath($source, "$val");
                    if (is_dir($sourceFile) || is_file($sourceFile)) {
                        static::copyDir($sourceFile, $destFile, true);
                        $array[] = $destFile;
                    }
                }
            }
        } else {
            static::deleteDir($dest);
            static::copyDir($source, $dest, true);
        }
        return $array;
    }

    /**
     * 还原phar文件
     * @param string $file
     * @param string $dir
     * @return bool
     */
    public static function pharFile(string $file, string $dir = ''): bool {
        $dir = (!empty($dir) ? $dir : static::delFileFormat($file));
        static::mkDir($dir);
        return (new Phar($file))->extractTo($dir, null, true);
    }

    /**
     * @param $file
     * @return false|string
     */
    public static function isFile($file): bool|string {
        return realpath($file);
    }

    /**
     * 文件夹不存在创建文件夹(无限级)
     * @param $dir
     * @return bool
     */
    public static function mkDir($dir): bool {
        return (!empty(is_dir($dir)) || @mkdir($dir, 0777, true));
    }

    /**
     * 获取php文件array
     * @param string          $file
     * @param null|string|int $key
     * @param mixed|string    $default
     * @return mixed
     */
    public static function getFileArr(string $file, null|string|int $key = null, mixed $default = []): mixed {
        if (is_file($file)) {
            $config = include $file;
        }
        return static::getArr(($config ?? $default), $key, $default);
    }

    /**
     * 添加内容到文件
     * @param mixed                $data 要添加的内容
     * @param string|null          $file 文件
     * @param string|int|bool|null $sing 标识,有标识可追加
     * @param bool                 $type 是否使用锁定
     * @return bool|int
     */
    public static function saveFileData(mixed $data, string|null $file, string|int|bool|null $sing = '', bool $type = false): bool|int {
        $file = !empty($file) ? $file : (__DIR__ . '/../../file/fileData.cache');
        $content = is_callable($data) ? $data() : $data;
        if (!empty($sing)) {
            if (!empty($fileData = @file_get_contents($file))) {
                $arr = !empty($array = unserialize($fileData) ?? []) ? (is_array($array) ? $array : []) : [];
            }
            $arr[$sing] = $content;
        }
        return (
        !empty($type)
            ? (static::saveLockData($file, ($arr ?? $content), true))
            : (@file_put_contents(static::mkDir($file), serialize(($arr ?? $content))))
        );
    }

    /**
     * 读取文件内容
     * @param string|int|bool|null $sing    标识
     * @param string|null          $file    文件
     * @param bool                 $type    是否判断锁定
     * @param mixed                $default 默认
     * @return mixed
     */
    public static function getFileData(string|int|bool|null $sing = '', string|null $file = '', bool $type = false, mixed $default = ''): mixed {
        $file = !empty($file) ? $file : (__DIR__ . '/../../file/fileData.cache');
        $content = empty($type) ? unserialize((@file_get_contents($file) ?? '')) : static::getLockData($file, true);
        return (
        !empty($sing)
            ? ((!empty($data = ($content[$sing] ?? $default)) ? $data : $default))
            : $content
        );
    }

    /**
     * 写入文件时,用户无法读取
     * 把内容添加到指定文件
     * @param string $file 写入文件名
     * @param mixed  $data 文件内容
     * @param bool   $type 是否使用serialize
     * @param string $mode 模式
     *                     "r" （只读方式打开，将文件指针指向文件头）
     *                     "r+" （读写方式打开，将文件指针指向文件头）
     *                     "w" （写入方式打开，清除文件内容，如果文件不存在则尝试创建之）
     *                     "w+" （读写方式打开，清除文件内容，如果文件不存在则尝试创建之）
     *                     "a" （写入方式打开，将文件指针指向文件末尾进行写入，如果文件不存在则尝试创建之）
     *                     "a+" （读写方式打开，通过将文件指针指向文件末尾进行写入来保存文件内容）
     *                     "x" （创建一个新的文件并以写入方式打开，如果文件已存在则返回 FALSE 和一个错误）
     *                     "x+" （创建一个新的文件并以读写方式打开，如果文件已存在则返回 FALSE 和一个错误）
     * @return bool
     */
    public static function saveLockData(string $file, mixed $data, bool $type = false, string $mode = 'w'): bool {
        static::mkDir($file);
        $fp = fopen($file, $mode);
        if (flock($fp, LOCK_EX)) {
            $content = (is_callable($data) ? ($data()) : $data);
            fwrite($fp, (!empty($type) ? serialize($content) : (is_array($content) ? static::json($content) : $content)));
            flock($fp, LOCK_UN);
        }
        return fclose($fp);
    }

    /**
     * 文件写入完成才可以读取
     * @param string $file 要读取的文件
     * @param bool   $type 是否使用
     * @param string $mode 模式
     * @param mixed  $data 默认返回内容
     *                     "r" （只读方式打开，将文件指针指向文件头）
     *                     "r+" （读写方式打开，将文件指针指向文件头）
     *                     "w" （写入方式打开，清除文件内容，如果文件不存在则尝试创建之）
     *                     "w+" （读写方式打开，清除文件内容，如果文件不存在则尝试创建之）
     *                     "a" （写入方式打开，将文件指针指向文件末尾进行写入，如果文件不存在则尝试创建之）
     *                     "a+" （读写方式打开，通过将文件指针指向文件末尾进行写入来保存文件内容）
     *                     "x" （创建一个新的文件并以写入方式打开，如果文件已存在则返回 FALSE 和一个错误）
     *                     "x+" （创建一个新的文件并以读写方式打开，如果文件已存在则返回 FALSE 和一个错误）
     * @return mixed
     */
    public static function getLockData(string $file, bool $type = false, string $mode = 'r', mixed $data = ''): mixed {
        if (!empty(is_file($file))) {
            $fp = fopen($file, $mode);
            if (flock($fp, LOCK_SH)) {
                $data = fread($fp, filesize($file));
                $data = (!empty($type) ? unserialize($data) : (!empty($arr = static::isJson($data)) ? $arr : $data));
            }
            fclose($fp);
        }
        return $data;
    }

    /**
     * 删除 PHP 注释以及空白字符
     * @param string      $code 代码
     * @param string|null $save 保存文件
     * @return string|int|false
     */
    public static function phpCodeWhite(string $code, string|null $save = null): string|int|false {
        $tempFile = __DIR__ . '/../../file/code/' . date("YmdHis") . '_' . rand(10000, 99999) . rand(10000, 99999) . '.php';
        @file_put_contents(static::mkDir($tempFile), $code);
        $strippedCode = static::phpWhite($tempFile);
        @unlink($tempFile);
        if (is_string($save) && !empty($save)) {
            return @file_put_contents(static::mkDir($save), $strippedCode);
        }
        return $strippedCode;
    }

    /**
     * 删除 PHP 注释以及空白字符
     * @param string      $file 文件
     * @param string|null $save 保存文件
     * @return string|int|false
     */
    public static function phpWhite(string $file, string|null $save = null): string|int|false {
        if (is_file($file)) {
            $strippedCode = php_strip_whitespace($file);
            if (is_string($save) && !empty($save)) {
                return @file_put_contents(static::mkDir($save), $strippedCode);
            }
            return $strippedCode;
        }
        return 'File does not exist';
    }

    /**
     * PHP代码高亮输出
     * @param string $code
     * @return bool|string
     */
    public static function phpCodeHigh(string $code): bool|string {
        return highlight_string($code, true);
    }

    /**
     * PHP文件代码高亮输出
     * @param string $file
     * @return bool|string
     */
    public static function phpHigh(string $file): bool|string {
        if (is_file($file)) {
            return highlight_string(@file_get_contents($file), true);
        }
        return 'File does not exist';
    }

    /**
     * yield 获取目录下的文件列表
     * @param      $path  //文件夹路径
     * @param bool $isDir //是否获取文件夹
     * @return array
     */
    public static function getDirList($path, bool $isDir = false): array {
        $array = [];
        if (!empty(is_dir($path))) {
            $obj = static::DirList($path, $isDir);
            while ($obj->valid()) {
                $file = $obj->current();
                $array[static::strRep($file, $path)] = $file;
                $obj->next();
            }
        }
        return $array;
    }


    /**
     * yield 获取文件内容
     * @param $file
     * @return string
     */
    public static function getFile($file): string {
        $str = '';
        if (!empty(is_file($file))) {
            $glob = static::FileData($file);
            while ($glob->valid()) {
                $str .= $glob->current();
                $glob->next();
            }
        }
        return $str;
    }

    /**
     * yield 读取文件
     * @param $file
     * @return Generator
     */
    private static function FileData($file): Generator {
        if ($handle = fopen($file, 'r')) {
            while (!feof($handle)) {
                yield trim(fgets($handle));
            }
            fclose($handle);
        }
    }

    /**
     * 复制文件
     * @param $filePath
     * @param $newFilePath
     * @return false|int
     */
    public static function copyFile($filePath, $newFilePath): bool|int {
        $type = false;
        if (is_readable($filePath)) {
            static::mkDir($newFilePath);
            if (($handle1 = fopen($filePath, 'r')) && ($handle2 = fopen($newFilePath, 'w'))) {
                $type = stream_copy_to_stream($handle1, $handle2);
                fclose($handle1);
                fclose($handle2);
            }
        }
        clearstatcache();
        return $type;
    }

    /**
     * 删除其文件夹下所有指定格式文件(文件夹，格式)
     * @param        $dir
     * @param string $format (为空删除全部)
     */
    public static function delDirFile($dir, string $format = ''): void {
        if (file_exists($dir)) {
            $fp = opendir($dir);
            while ($file = readdir($fp)) {
                if ($file != "." && $file != "..") {
                    $files = $dir . "/" . $file;
                    if (!is_dir($files)) {
                        if (empty($format) || (substr(strrchr($files, '.'), 1) == $format)) {
                            @unlink($files);
                        }
                    } else {
                        if (is_dir($files)) {
                            static::delDirFile($files, $format);
                        }
                    }
                }
            }
            closedir($fp);
        }
    }

    /**
     * 删除其文件夹下所有的空文件夹
     * @param $path
     */
    public static function delNullDir($path): void {
        if (is_dir($path) && ($handle = opendir($path)) !== false) {
            while (($file = readdir($handle)) !== false) {
                if ($file != '.' && $file != '..') {
                    $dir = $path . '/' . $file;
                    if (is_dir($dir)) {
                        static::delNullDir($dir);
                        if (count(scandir($dir)) == 2) {
                            rmdir($dir);
                        }
                    }
                }
            }
            closedir($handle);
        }
    }

    /**
     * 获取目录下全部文件列表
     * @param string       $path
     * @param array|string $format
     * @param string       $route
     * @param array        $result
     * @return mixed
     */
    public static function getDirFile(string $path, array|string $format = [], string $route = '', array $result = []): mixed {
        if (!empty(is_dir($path))) {
            $files = scandir($path);
            $route = $route ?: $path;
            $format = is_string($format) ? explode(',', $format) : $format;
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    if (is_dir($path . '/' . $file)) {
                        $result = static::getDirFile($path . '/' . $file, $format, $route, $result);
                    } else {
                        if (empty($format) || in_array($file, $format)) {
                            $key = trim($file, '/');
                            if (!empty($route)) {
                                $route_ = trim(realpath($route), '/');
                                $path_ = trim(realpath($path), '/');
                                if (!empty($route_) && !empty($path_) && str_starts_with($path_, $route_)) {
                                    $key = trim(substr($path_, strlen($route_)), '/') . '/' . $key;
                                }
                            }
                            $result[trim($key, '/')] = $path . '/' . $file;
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * yield 读取文件夹
     * @param      $path
     * @param bool $isDir
     * @return Generator
     */
    public static function DirList($path, bool $isDir = false): Generator {
        $path = rtrim($path, '/*');
        if (is_readable($path)) {
            $dh = opendir($path);
            while (($file = readdir($dh)) !== false) {
                if (str_starts_with($file, '.')) {
                    continue;
                }
                $dirFile = "$path/$file";
                if (is_dir($dirFile)) {
                    $obj = static::DirList($dirFile, $isDir);
                    while ($obj->valid()) {
                        yield $obj->current();
                        $obj->next();
                    }
                    if ($isDir) {
                        yield $dirFile;
                    }
                } else {
                    yield $dirFile;
                }
            }
            closedir($dh);
        }
    }

    /**
     * 将一个文件单位转为字节
     * @param string $unit 将b、kb、m、mb、g、gb的单位转为 byte
     */
    public static function fileToByte(string $unit): int {
        preg_match('/([0-9.]+)(\w+)/', $unit, $matches);
        if (!$matches) {
            return 0;
        }
        $typeDict = ['b' => 0, 'k' => 1, 'kb' => 1, 'm' => 2, 'mb' => 2, 'gb' => 3, 'g' => 3];
        return (int) ($matches[1] * pow(1024, $typeDict[strtolower($matches[2])] ?? 0));
    }


    /**
     * 格式化文件大小
     * @param $file_size
     * @return string
     */
    public static function formatBytes($file_size): string {
        $size = sprintf("%u", $file_size);
        if ($size == 0) {
            return ("0 Bytes");
        }
        $size_name = [" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB"];
        return round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . $size_name[$i];
    }

    /**
     * 检查目录/文件是否可写
     * @param $path
     * @return bool
     */
    public static function isPathWritable($path): bool {
        if (DIRECTORY_SEPARATOR == '/' && !@ini_get('safe_mode')) {
            return is_writable($path);
        }
        if (is_dir($path)) {
            $path = rtrim($path, '/') . '/' . md5(mt_rand(1, 100) . mt_rand(1, 100));
            if (($fp = @fopen($path, 'ab')) === false) {
                return false;
            }
            fclose($fp);
            @chmod($path, 0777);
            @unlink($path);
            return true;
        } elseif (!is_file($path) || ($fp = @fopen($path, 'ab')) === false) {
            return false;
        }
        fclose($fp);
        return true;
    }

    /**
     * 删除文件夹
     * @param string $dirname 目录
     * @param bool   $densely 是否删除自身
     * @return boolean
     */
    public static function delDir(string $dirname, bool $densely = true): bool {
        if (!is_dir($dirname)) {
            return false;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirname, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $filing) {
            if ($filing->isDir()) {
                self::delDir($filing->getRealPath());
            } else {
                @unlink($filing->getRealPath());
            }
        }
        if ($densely) {
            @rmdir($dirname);
        }
        return true;
    }
}