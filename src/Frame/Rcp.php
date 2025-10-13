<?php

namespace AlonePhp\Code\Frame;

use Closure;
use Throwable;

trait Rcp {

    /**
     * 使用swoole连接
     * @param string $address 连接地址，例如 tcp://127.0.0.1:11223
     * @param mixed  $data    要发送的数据（数组、对象、字符串或闭包）
     * @param float  $timeout 连接和接收超时时间（秒）
     * @param string $result  接收的数据-不用传参
     * @return array
     */
    public static function rpcSwoole(string $address, mixed $data, float $timeout = 3.0, string $result = ""): array {
        try {
            if (!class_exists('Swoole\Client')) {
                return ['code' => 400, 'msg' => 'PHP Not installed Swoole', 'data' => ['code' => "new \Swoole\Client Null"]];
            }
            $client = new \Swoole\Client(SWOOLE_SOCK_TCP);
            if (!$client->connect(parse_url($address, PHP_URL_HOST), parse_url($address, PHP_URL_PORT), $timeout)) {
                return ['code' => 500, 'msg' => "Swoole TCP Connection failed", 'data' => ['address' => $address]];
            }
            $client->send(static::getJsonValue($data) . "\n");
            while (true) {
                $chunkData = $client->recv($timeout);
                if ($chunkData === false)
                    break;
                if ($chunkData === '') {
                    usleep(5000);
                    continue;
                }
                $result .= $chunkData;
            }
            $decoded = json_decode($result, true);
            return ['code' => 200, 'msg' => 'success', 'data' => is_array($decoded) ? $decoded : $result];
        } catch (Throwable $e) {
            return ['code' => 400, 'msg' => $e->getMessage(), 'data' => ['file' => $e->getFile(), 'line' => $e->getLine()]];
        } finally {
            if (!empty($client) && $client instanceof \Swoole\Client) {
                $client->close();
            }
        }
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
                return ['code' => 500, 'msg' => $error_message, 'data' => ['code' => $error_code, 'address' => $address]];
            }
            fwrite($client, static::getJsonValue($data) . "\n");
            stream_set_blocking($client, true);
            stream_set_timeout($client, $timeout);
            while (!feof($client)) {
                $chunkData = fread($client, $chunk);
                if ($chunkData === false)
                    break;
                if ($chunkData === '') {
                    usleep(5000);
                    continue;
                }
                $result .= $chunkData;
            }
            $decoded = json_decode($result, true);
            return ['code' => 200, 'msg' => 'success', 'data' => is_array($decoded) ? $decoded : $result];
        } catch (Throwable $e) {
            return ['code' => 400, 'msg' => $e->getMessage(), 'data' => ['file' => $e->getFile(), 'line' => $e->getLine()]];
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
                return ['code' => 500, 'msg' => $error_message, 'data' => ['code' => $error_code, 'address' => $address]];
            }
            fwrite($client, static::getJsonValue($data) . "\n");
            $result = stream_get_line($client, $chunk, "\n");
            $decoded = json_decode($result, true);
            return ['code' => 200, 'msg' => 'success', 'data' => is_array($decoded) ? $decoded : $result];
        } catch (Throwable $e) {
            return ['code' => 400, 'msg' => $e->getMessage(), 'data' => ['file' => $e->getFile(), 'line' => $e->getLine()]];
        } finally {
            if (!empty($client) && is_resource($client)) {
                fclose($client);
            }
        }
    }

    /**
     * 传入参数判断是否 json,方法,对像,数组,转换成json
     * @param mixed $value
     * @return string
     */
    public static function getJsonValue(mixed $value): string {
        $body = ($value instanceof Closure) ? $value() : $value;
        return (string) ((is_array($body) || is_object($body)) ? json_encode($body, JSON_UNESCAPED_UNICODE) : $body);
    }
}