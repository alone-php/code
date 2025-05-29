<?php

namespace AlonePhp\Code\Frame;

use ZipArchive;

trait Zip{

    /**
     * 把文件夹打包成zip
     * @param array|string $path //要打包的目录
     * @param string       $file //zip存放路径
     * @return int|bool
     */
    public static function zips(array|string $path, string $file): int|bool {
        static::mkDir(dirname($file));
        $zip = new ZipArchive();
        $res = $zip->open($file, ZipArchive::CREATE);
        if (is_array($path)) {
            foreach ($path as $v) {
                self::addFileToZips(rtrim($v, '/'), $zip, rtrim($v, '/'));
            }
        } else {
            self::addFileToZips(rtrim($path, '/'), $zip, rtrim($path, '/'));
        }
        return $res;
    }

    /**
     * @param $path
     * @param $zip
     * @param $dir
     */
    public static function addFileToZips($path, $zip, $dir): void {
        $handler = opendir($path);
        while (($filename = readdir($handler)) !== false) {
            if ($filename != "." && $filename != "..") {
                if (is_dir($path . "/" . $filename)) {
                    self::addFileToZips($path . "/" . $filename, $zip, $dir);
                } else {
                    $zip->addFile($path . "/" . $filename, static::strRep($path . "/" . $filename, rtrim(dirname($dir), '/')));
                }
            }
        }
    }

    /**
     * 把字符串添加至GZ文件
     * @param string $str    //字符串
     * @param string $gzFile //GZ文件路径
     * @return string
     */
    public static function strToGz(string $str, string $gzFile): string {
        static::mkDir(dirname($gzFile));
        $fp = gzopen($gzFile, 'w9');
        gzwrite($fp, $str);
        gzclose($fp);
        return static::isFile($gzFile);
    }

    /**
     * 把文件添加至GZ文件
     * @param string $file   //要添加的文件
     * @param string $gzFile //GZ文件路径 (为空当前目录)
     * @return bool
     */
    public static function FileToGz(string $file, string $gzFile = ''): bool {
        if (is_file($file)) {
            $gzFile = !empty($gzFile) ? $gzFile : $file . '.gz';
            static::mkDir(dirname($gzFile));
            $fp = gzopen($gzFile, 'w9');
            gzwrite($fp, file_get_contents($file));
            gzclose($fp);
            return true;
        }
        return false;
    }

    /**
     * 读取GZ文件
     * @param string $gzFile //GZ文件路径
     * @return string
     */
    public static function getGzFile(string $gzFile): string {
        $str = '';
        if (is_file($gzFile)) {
            $buffer_size = 4096;
            $file = gzopen($gzFile, 'rb');
            while (!gzeof($file)) {
                $str .= gzread($file, $buffer_size);
            }
            gzclose($file);
        }
        return $str;
    }

    /**
     * 解压GZ文件
     * @param string $gzFile   //GZ文件路径
     * @param string $filePath //解压路径 (为空当前目录)
     */
    public static function unGz(string $gzFile, string $filePath = ''): bool {
        if (is_file($gzFile)) {
            $size = 4096;
            $path = !empty($filePath) ? $filePath : static::delFileFormat($gzFile);
            static::mkDir(dirname($filePath));
            $file = gzopen($gzFile, 'rb');
            $outFile = fopen($path, 'wb');
            while (!gzeof($file)) {
                fwrite($outFile, gzread($file, $size));
            }
            fclose($outFile);
            gzclose($file);
            return true;
        }
        return false;
    }

    /**
     * @param string       $zipFile  //压缩的zip文件名
     * @param string|array $fileList //要压缩的文件列表
     * @return mixed
     */
    public static function fileToZip(string $zipFile, string|array $fileList): mixed {
        $zip = new ZipArchive();
        static::mkDir(dirname($zipFile));
        $res = $zip->open($zipFile, ZipArchive::CREATE);//OVERWRITE|CREATE
        if ($res === true) {
            if (is_string($fileList)) {
                $fileList = [$fileList];
            }
            foreach ($fileList as $file) {
                if (is_file($file)) {
                    $zip->addFile($file, basename($file));
                }
            }
            $zip->close();
        }
        return $res;
    }

    /**
     * 解压zip文件
     * @param string $zipFile //zip文件路径
     * @param string $path    //解压到那里,为空默认当前目录
     * @return bool
     */
    public static function unzip(string $zipFile, string $path = ''): bool {
        if (is_file($zipFile)) {
            $zip = new ZipArchive;
            $res = $zip->open($zipFile);
            if ($res === true) {
                $path = !empty($path) ? $path : static::delFileFormat($zipFile);
                static::mkDir($path);
                $zip->extractTo($path);
                $zip->close();
                return true;
            }
        }
        return false;
    }

}