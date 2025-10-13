<?php

namespace AlonePhp\Code;

use Closure;
use Throwable;

/**
 * RPC客户端
 */
trait RcpClient {
    /**
     * @param string $address 连接地址，例如 tcp://127.0.0.1:11223
     * @param mixed  $data    要发送的数据（数组、对象、字符串或闭包）
     * @param int    $chunk   每次读取的字节数（默认 8192）
     * @param bool   $all     是否持续读取到连接关闭 (服务端发送完成要主动关闭)
     * @param float  $timeout 连接和接收超时时间（秒）
     * @param string $ending  消息结尾
     * @return array
     */
    public static function send(string $address, mixed $data, int $chunk = 8192, bool $all = true, float $timeout = 3.0, string $ending = ''): array {
        return $all === true ? static::all($address, $data, $chunk, $timeout, $ending) : static::first($address, $data, $chunk, $timeout, $ending);
    }

    /**
     * 连接RPC发送数据获取全部返回数据
     * 如不设置结尾则获取到服务关闭或者超时
     * @param string $address 连接地址，例如 tcp://127.0.0.1:11223
     * @param mixed  $data    要发送的数据（数组、对象、字符串或闭包）
     * @param int    $chunk   每次读取的字节数（默认 8192）
     * @param float  $timeout 连接和接收超时时间（秒）
     * @param string $ending  消息结尾
     * @param string $result  接收的数据-不用传参
     * @return array
     */
    public static function all(string $address, mixed $data, int $chunk = 8192, float $timeout = 3.0, string $ending = '', string $result = ""): array {
        try {
            $client = @stream_socket_client($address, $error_code, $error_message, $timeout);
            if (!$client) {
                return ['code' => 400, 'msg' => "$error_message", 'data' => ['code' => $error_code]];
            }
            fwrite($client, static::convertJson($data) . "\n");
            stream_set_blocking($client, true);
            stream_set_timeout($client, $timeout);
            while (!feof($client)) {
                $chunkData = fread($client, $chunk);
                if ($chunkData === false)
                    break;
                if ($chunkData === '') {
                    continue;
                }
                $result .= $chunkData;
                if ($ending && str_ends_with($result, "\n")) {
                    $result = rtrim($result, "\n");
                    break;
                }
            }
            return ['code' => 200, 'msg' => 'success', 'data' => static::convertArray($result)];
        } catch (Throwable $e) {
            return ['code' => 500, 'msg' => $e->getMessage(), 'data' => ['file' => $e->getFile(), 'line' => $e->getLine()]];
        } finally {
            if (!empty($client) && is_resource($client)) {
                fclose($client);
            }
        }
    }

    /**
     * 连接RPC发送数据获取指定字节数
     * @param string $address 连接地址，例如 tcp://127.0.0.1:11223
     * @param mixed  $data    要发送的数据（数组、对象、字符串或闭包）
     * @param int    $chunk   每次读取的字节数（默认 8192）
     * @param int    $timeout 连接和接收超时时间（秒）
     * @param string $ending  消息结尾
     * @return array
     */
    public static function first(string $address, mixed $data, int $chunk = 8192, int $timeout = 3, string $ending = ''): array {
        try {
            $client = @stream_socket_client($address, $error_code, $error_message, $timeout);
            if (!$client) {
                return ['code' => 400, 'msg' => $error_message, 'data' => ['code' => $error_code]];
            }
            fwrite($client, static::convertJson($data) . "\n");
            $result = stream_get_line($client, $chunk, $ending);
            return ['code' => 200, 'msg' => 'success', 'data' => static::convertArray($result)];
        } catch (Throwable $e) {
            return ['code' => 500, 'msg' => $e->getMessage(), 'data' => ['file' => $e->getFile(), 'line' => $e->getLine()]];
        } finally {
            if (!empty($client) && is_resource($client)) {
                fclose($client);
            }
        }
    }

    /**
     * 传入参数转换成array,不是array的原样返回
     * @param mixed $value
     * @return mixed
     */
    public static function convertArray(mixed $value): mixed {
        return !empty($array = json_decode($value, true)) && is_array($array) ? $array : $value;
    }

    /**
     * 传入参数判断是否 json,方法,对像,数组,转换成json
     * @param mixed $value
     * @return string
     */
    public static function convertJson(mixed $value): string {
        $body = ($value instanceof Closure) ? $value() : $value;
        return (string) ((is_array($body) || is_object($body)) ? json_encode($body, JSON_UNESCAPED_UNICODE) : $body);
    }
}