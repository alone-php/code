<?php

namespace AlonePhp\Code;

use Closure;
use Throwable;

/**
 * RPC客户端
 */
class RcpClient {
    // 连接地址，例如 tcp://127.0.0.1:11223
    public string $address = "";

    // 连接和接收超时时间（秒 默认 0.3）
    public int|float $timeout = 3.0;

    // 连接方式，1=立即连接,2=异步连接,3=持久连接
    public int $flags = 1;

    // stream上下文资源，可用于设置 SSL 选项、超时等
    public array $context = [];

    // 读取的字节数（默认 8192）
    public int $length = 8192;

    // 消息结尾符号
    public string $ending = "";

    // 原始发送内容
    public mixed $rawBody = "";

    // 发送包体
    public mixed $sendBody = "";

    // 原样返回内容
    public string $resBody = "";

    // 连接对像
    public mixed $client = "";

    // 状态码 200=成功, 300=没有连接, 400=连接失败, 500=系统错误
    public int|string $code = 300;

    // 提示信息
    public string|int $msg = "No connection";

    // 接收到的内容
    public mixed $data = "";

    /**
     * @param string $address 连接地址，例如 tcp://127.0.0.1:11223
     * @param mixed  $data    要发送的数据（数组、对象、字符串或闭包）
     * @param int    $length  每次读取的字节数（默认 8192）
     * @param bool   $read    是否持续读取到连接关闭 (服务端发送完成要主动关闭)
     * @param float  $timeout 连接和接收超时时间（秒）
     * @return array
     */
    public static function link(string $address, mixed $data, int $length = 8192, bool $read = true, float $timeout = 3.0): array {
        $client = static::url($address);
        try {
            $client->send($data);
            $client->receive($length, $read);
            return $client->array();
        } catch (Throwable $e) {
            return ['code' => 500, 'msg' => $e->getMessage(), 'data' => ['file' => $e->getFile(), 'line' => $e->getLine()]];
        } finally {
            $client->close();
        }
    }

    /**
     * @param string|array $address 连接地址 例如 tcp://127.0.0.1:11223
     * @param array        $context stream上下文资源，可用于设置 SSL 选项、超时等
     * @return $this
     */
    public static function url(string|array $address, array $context = []): static {
        return new static($address, $context);
    }

    /**
     * @param string|array $address 连接地址 例如 tcp://127.0.0.1:11223
     * @param array        $context stream上下文资源，可用于设置 SSL 选项、超时等
     */
    public function __construct(string|array $address, array $context = []) {
        if (is_array($address)) {
            foreach ($address as $key => $val) {
                if (isset($this->$key)) {
                    $this->$key = $val;
                }
            }
        } else {
            $this->address = $address;
            $this->context = $context;
        }
    }

    /**
     * 设置连接和接收超时时间
     * @param int|float $timeout
     * @return $this
     */
    public function timeout(int|float $timeout): static {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * 设置连接方式
     * @param int $flags 1=立即连接,2=异步连接,3=持久连接
     * @return $this
     */
    public function flags(int $flags): static {
        $this->flags = $flags;
        return $this;
    }

    /**
     * stream上下文资源，可用于设置 SSL 选项、超时等
     * @param array $context
     * @return $this
     */
    public function context(array $context): static {
        $this->context = $context;
        return $this;
    }

    /**
     * 设置读取的字节数和消息结尾符号
     * @param int         $length 读取的字节数
     * @param string|null $ending 消息结尾符号
     * @return $this
     */
    public function length(int $length, string|null $ending = null): static {
        $this->length = $length;
        (isset($ending)) && $this->ending($ending);
        return $this;
    }

    /**
     * 设置消息结尾符号
     * @param string $ending
     * @return $this
     */
    public function ending(string $ending = ""): static {
        $this->ending = $ending;
        return $this;
    }

    /**
     * 连接
     * @param array $context stream上下文资源，可用于设置 SSL 选项、超时等
     * @return $this
     */
    public function connect(array $context = []): static {
        $flags = [1 => STREAM_CLIENT_CONNECT, 2 => STREAM_CLIENT_ASYNC_CONNECT, 3 => STREAM_CLIENT_PERSISTENT];
        $flag = $flags[$this->flags] ?? $this->flags;
        $this->context = !empty($context) ? $context : $this->context;
        $context = !empty($this->context) ? stream_context_create($this->context) : null;
        $this->client = @stream_socket_client($this->address, $code, $msg, $this->timeout, $flag, $context);
        if ($this->client) {
            $this->code = 200;
            $this->msg = "success";
            stream_set_blocking($this->client, true);
            stream_set_timeout($this->client, $this->timeout);
            return $this;
        }
        $this->code = 400;
        $this->msg = "$msg ($code)";
        return $this;
    }

    /**
     * 发送数据
     * @param mixed $data 发送的内容
     * @return $this
     */
    public function send(mixed $data): static {
        (!$this->client && $this->code != 400) && $this->connect();
        $body = ($data instanceof Closure) ? $data() : $data;
        $this->rawBody = $body;
        $this->sendBody = (string) ((is_array($body) || is_object($body)) ? json_encode($body, JSON_UNESCAPED_UNICODE) : $body);
        ($this->code == 200 && $this->client) && fwrite($this->client, $this->sendBody . "\n");
        return $this;
    }

    /**
     * 接收数据 (1和2参数可以对调使用)
     * @param bool|int $length  长度 或者 是否接收全部
     * @param bool     $read    长度 或者 是否接收全部
     * @param string   $resBody 不用理会
     * @return mixed
     */
    public function receive(int|bool $length = false, bool|int $read = false, string $resBody = ""): mixed {
        if ($this->code == 200 && $this->client) {
            $reads = null;
            $lengths = null;
            // 如果第一个参数是布尔，交换到 reads，长度取第二个参数
            if (is_bool($length)) {
                $reads = $length;
                $lengths = is_int($read) ? $read : $this->length;
            } // 如果第一个参数是整数，长度取第一个参数，reads 取第二个参数（如果是布尔）
            elseif (is_int($length)) {
                $lengths = $length;
                $reads = is_bool($read) ? $read : false;
            }
            if ($reads === true) {
                while (!feof($this->client)) {
                    $chunk = fread($this->client, $lengths);
                    if ($chunk === false)
                        break;
                    if ($chunk === '') {
                        continue;
                    }
                    $resBody .= $chunk;
                    if ($this->ending && str_ends_with($resBody, $this->ending)) {
                        $resBody = rtrim($resBody, $this->ending);
                        break;
                    }
                }
            } else {
                $resBody = $this->ending ? stream_get_line($this->client, $lengths, $this->ending) : fgets($this->client, $lengths);
            }
            $this->resBody = $resBody;
            $this->data = !empty($array = json_decode($resBody, true)) && is_array($array) ? $array : $resBody;
            return $this->data;
        }
        return $this->msg;
    }

    /**
     * 获取array
     * @return array
     */
    public function array(): array {
        return ['code' => $this->code, 'msg' => $this->msg, 'data' => $this->data];
    }

    /**
     * 关闭连接
     * @return $this
     */
    public function close(): static {
        if (!empty($this->client) && is_resource($this->client)) {
            fclose($this->client);
        }
        return $this;
    }
}