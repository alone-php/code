<?php

namespace AlonePhp\Code;

use Closure;
use Throwable;

/**
 * RPC客户端
 */
class RcpClient {
    // 状态码 200=成功, 300=没有连接, 400=连接失败, 500=系统错误
    public int|string $code = 300;
    // 提示信息
    public string|int $msg = "No connection";
    // 接收到解析后的内容
    public mixed $data = "";
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
    // 转换后发送内容
    public mixed $sendBody = "";
    // 是否分块发送
    public bool $chunk = false;
    // 分块发送大小
    public int $size = 8192;
    // 原始返回内容
    public string $resBody = "";
    // 连接对像
    public mixed $client = "";

    /**
     * @param string $address 连接地址，例如 tcp://127.0.0.1:11223
     * @param mixed  $data    要发送的数据（数组、对象、字符串或闭包）
     * @param int    $length  每次读取的字节数（默认 8192）
     * @param bool   $read    是否持续读取到连接关闭 (服务端发送完成要主动关闭)
     * @param string $ending  消息结尾符号
     * @param float  $timeout 连接和接收超时时间（秒）
     * @return mixed
     */
    public static function link(string $address, mixed $data, int $length = 65536, bool $read = false, string $ending = "", float $timeout = 3.0): mixed {
        $client = static::url($address);
        try {
            $client->length($length, $ending);
            $client->timeout($timeout);
            $client->send($data);
            return $client->receive($read);
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
        ($ending !== null) && $this->ending($ending);
        return $this;
    }

    /**
     * 设置消息结尾符号
     * @param string $ending
     * @return $this
     */
    public function ending(string $ending): static {
        $this->ending = $ending;
        return $this;
    }

    /**
     * 设置分块发送
     * @param bool     $chunk 是否分块发送
     * @param int|null $size  发送大小
     * @return $this
     */
    public function chunk(bool $chunk, int|null $size = null): static {
        $this->chunk = $chunk;
        $this->size = $size ?? $this->size;
        return $this;
    }

    /**
     * 发送数据
     * @param mixed  $data   发送的内容
     * @param string $ending 发送结尾符号
     * @return $this
     */
    public function send(mixed $data, string $ending = "\n"): static {
        (!$this->client && $this->code != 400) && $this->connect();
        $body = ($data instanceof Closure) ? $data() : $data;
        $this->rawBody = $body;
        $this->sendBody = (string) ((is_array($body) || is_object($body)) ? json_encode($body, JSON_UNESCAPED_UNICODE) : $body);
        if ($this->code == 200 && $this->client) {
            if ($this->chunk) {
                $written = 0;
                $total = strlen($this->sendBody);
                while ($written < $total) {
                    $chunk = substr($this->sendBody, $written, $this->size);
                    $n = fwrite($this->client, $chunk);
                    $written += $n;
                }
                fwrite($this->client, $ending);
            } else {
                fwrite($this->client, $this->sendBody . $ending);
            }
        }
        return $this;
    }

    /**
     * 接收数据 (1和2参数可以对调使用)
     * @param bool|int $length 长度 或者 是否接收全部
     * @param bool     $read   长度 或者 是否接收全部
     * @return mixed
     */
    public function receive(int|bool $length = false, bool|int $read = false): mixed {
        return $this->receiveProcess($length, $read);
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
     * 关闭连接
     * @return $this
     */
    public function close(): static {
        if ($this->client && is_resource($this->client)) {
            fclose($this->client);
        }
        return $this;
    }

    /**
     * 接收数据 (1和2参数可以对调使用)
     * @param bool|int $length  长度 或者 是否接收全部
     * @param bool     $read    长度 或者 是否接收全部
     * @param string   $resBody 不用理会
     * @return mixed
     */
    private function receiveProcess(int|bool $length = false, bool|int $read = false, string $resBody = ""): mixed {
        if ($this->code == 200 && $this->client) {
            $reads = null;
            $lengths = null;
            if (is_bool($length)) {
                $reads = $length;
                $lengths = is_int($read) ? $read : $this->length;
            } elseif (is_int($length)) {
                $lengths = $length;
                $reads = is_bool($read) ? $read : false;
            }
            if ($this->ending) {
                if ($reads === true) {
                    while (!feof($this->client)) {
                        $chunk = fread($this->client, $lengths);
                        if ($chunk === false) {
                            break;
                        }
                        if ($chunk === "") {
                            continue;
                        }
                        $resBody .= $chunk;
                        if (str_ends_with($resBody, $this->ending)) {
                            $resBody = rtrim($resBody, $this->ending);
                            break;
                        }
                    }
                } else {
                    $resBody = stream_get_line($this->client, $lengths, $this->ending);
                }
            } else {
                if ($reads === true) {
                    while (!feof($this->client)) {
                        $chunk = fread($this->client, $lengths);
                        if ($chunk === false) {
                            break;
                        }
                        if ($chunk === "") {
                            continue;
                        }
                        $resBody .= $chunk;
                    }
                } else {
                    $resBody = fgets($this->client, $lengths);
                }
            }
            $this->resBody = $resBody;
            $this->data = !empty($array = json_decode($resBody, true)) ? $array : $resBody;
            return $this->data;
        }
        return ['code' => $this->code, 'msg' => $this->msg, 'data' => []];
    }
}