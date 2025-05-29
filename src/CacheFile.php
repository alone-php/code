<?php

namespace AlonePhp\Code;

class CacheFile {
    public static string $filePath = __DIR__ . '/../cache/file';
    public static string $lockPath = __DIR__ . '/../cache/lock';

    /**
     * 设置
     * @param string|int $key
     * @param mixed      $val
     * @param int        $time
     * @return mixed
     */
    public static function set(string|int $key, mixed $val, int $time = 0): mixed {
        Frame::mkDir(static::$filePath);
        $val = (is_callable($val) ? $val() : $val);
        $file = rtrim(static::$filePath, DIRECTORY_SEPARATOR) . '/' . md5($key) . '.cache';
        $fp = fopen($file, 'w');
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, serialize(['data' => $val, 'time' => ($time > 0 ? (time() + $time) : 0)]));
            flock($fp, LOCK_UN);
            chmod($file, 0777);
        }
        return $val;
    }

    /**
     * 获取
     * @param string|int $key
     * @param mixed      $def
     * @return mixed
     */
    public static function get(string|int $key, mixed $def = ''): mixed {
        $file = rtrim(static::$filePath, DIRECTORY_SEPARATOR) . '/' . md5($key) . '.cache';
        if (is_file($file)) {
            $fp = fopen($file, 'r');
            if (flock($fp, LOCK_SH)) {
                $data = fread($fp, filesize($file));
            }
            fclose($fp);
            if (isset($data)) {
                $data = unserialize($data);
                $time = $data['time'] ?? -1;
                if ($time == 0 || time() <= $time) {
                    return $data['data'] ?? $def;
                } else {
                    static::del($key);
                }
            }
        }
        return $def;
    }

    /**
     * 删除
     * @param string|int $key
     * @return bool
     */
    public static function del(string|int $key): bool {
        $file = rtrim(static::$filePath, DIRECTORY_SEPARATOR) . '/' . md5($key) . '.cache';
        if (is_file($file)) {
            unlink($file);
            return true;
        }
        return false;
    }

    /**
     * 文件 排他锁 执行
     * @param string|int    $key      唯一标识
     * @param callable      $callable 执行包
     * @param callable|bool $closure  超时的时候处理,false=不处理,true=运行执行包,callable($callable)=自定执行包
     * @param int           $timeout  有效时间,执行的最长等待时间 秒
     * @param int           $wait     间隔等待时间 微秒
     * @return mixed
     */
    public static function lock(string|int $key, callable $callable, callable|bool $closure = false, int $timeout = 5, int $wait = 100): mixed {
        Frame::mkDir(static::$lockPath);
        $fp = fopen(rtrim(static::$lockPath, DIRECTORY_SEPARATOR) . '/' . $key . '.cache', 'a+');
        if ($fp) {
            $startTime = time();
            while (true) {
                if (flock($fp, LOCK_EX | LOCK_NB)) {
                    $start = date("Y-m-d H:i:s");
                    $res = $callable();
                    fwrite($fp, $start . " ~ " . date("Y-m-d H:i:s") . "\r\n");
                    flock($fp, LOCK_UN);
                    break;
                }
                if ((time() - $startTime) >= $timeout) {
                    return (!empty($closure) ? (is_callable($closure) ? $closure($callable) : $callable()) : $closure);
                }
                usleep($wait * 10000);
            }
            fclose($fp);
            return $res;
        }
        return null;
    }

    /**
     * 文件 独占锁 执行,一直等待
     * @param string|int $make     唯一标识
     * @param callable   $callable 执行包
     * @return mixed
     */
    public static function lockFile(string|int $make, callable $callable): mixed {
        Frame::mkDir(static::$lockPath);
        $fp = fopen(rtrim(static::$lockPath, DIRECTORY_SEPARATOR) . '/' . $make . '.cache', 'a+');
        if (flock($fp, LOCK_EX)) {
            $start = date("Y-m-d H:i:s");
            $res = $callable();
            fwrite($fp, $start . " ~ " . date("Y-m-d H:i:s") . "\r\n");
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return ($res ?? null);
    }
}