<?php

namespace AlonePhp\Code\Frame;

use Closure;
use Throwable;

trait Rcp {
    /**
     * @param string $address 连接地址，例如 tcp://127.0.0.1:11223
     * @param mixed  $data    要发送的数据（数组、对象、字符串或闭包）
     * @param int    $chunk   每次读取的字节数（默认 8192）
     * @param bool   $all     是否持续读取到连接关闭 (服务端发送完成要主动关闭)
     * @param float  $timeout 连接和接收超时时间（秒）
     * @return array
     */
    public static function rpcSend(string $address, mixed $data, int $chunk = 8192, bool $all = true, float $timeout = 3.0): array {
        return $all === true ? static::rpcLinkAll($address, $data, $chunk, $timeout) : static::rpcLink($address, $data, $chunk, $timeout);
    }

    /**
     * @param string $address 连接地址，例如 tcp://127.0.0.1:11223
     * @param mixed  $data    要发送的数据（数组、对象、字符串或闭包）
     * @param int    $chunk   每次读取的字节数（默认 8192）
     * @param float  $timeout 连接和接收超时时间（秒）
     * @param string $result  接收的数据-不用传参
     * @return array
     */
    public static function rpcLinkAll(string $address, mixed $data, int $chunk = 8192, float $timeout = 3.0, string $result = ""): array {
        try {
            $client = @stream_socket_client($address, $error_code, $error_message, $timeout);
            if (!$client) {
                return ['code' => 400, 'msg' => "$error_message", 'data' => ['code' => $error_code]];
            }
            fwrite($client, static::getIsJson($data) . "\n");
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
            }
            return ['code' => 200, 'msg' => 'success', 'data' => static::getIsArray($result)];
        } catch (Throwable $e) {
            return ['code' => 500, 'msg' => $e->getMessage(), 'data' => ['file' => $e->getFile(), 'line' => $e->getLine()]];
        } finally {
            if (!empty($client) && is_resource($client)) {
                fclose($client);
            }
        }
    }

    /**
     * RPC通信函数
     * @param string $address 连接地址，例如 tcp://127.0.0.1:11223
     * @param mixed  $data    要发送的数据（数组、对象、字符串或闭包）
     * @param int    $chunk   每次读取的字节数（默认 8192）
     * @param int    $timeout 连接和接收超时时间（秒）
     * @return array
     */
    public static function rpcLink(string $address, mixed $data, int $chunk = 8192, int $timeout = 3): array {
        try {
            $client = @stream_socket_client($address, $error_code, $error_message, $timeout);
            if (!$client) {
                return ['code' => 400, 'msg' => $error_message, 'data' => ['code' => $error_code]];
            }
            fwrite($client, static::getIsJson($data) . "\n");
            $result = stream_get_line($client, $chunk);
            return ['code' => 200, 'msg' => 'success', 'data' => static::getIsArray($result)];
        } catch (Throwable $e) {
            return ['code' => 500, 'msg' => $e->getMessage(), 'data' => ['file' => $e->getFile(), 'line' => $e->getLine()]];
        } finally {
            if (!empty($client) && is_resource($client)) {
                fclose($client);
            }
        }
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function getIsArray(mixed $value): mixed {
        return !empty($array = json_decode($value, true)) && is_array($array) ? $array : $value;
    }

    /**
     * 传入参数判断是否 json,方法,对像,数组,转换成json
     * @param mixed $value
     * @return string
     */
    public static function getIsJson(mixed $value): string {
        $body = ($value instanceof Closure) ? $value() : $value;
        return (string) ((is_array($body) || is_object($body)) ? json_encode($body, JSON_UNESCAPED_UNICODE) : $body);
    }
}