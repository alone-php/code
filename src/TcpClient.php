<?php

namespace AlonePhp\Code;

use Closure;
use Throwable;

/**
 * Tcp客户端
 */
class TcpClient {
    // 状态码 200=成功, 300=没有连接, 400=连接失败, 500=系统错误
    public int|string $code = 300;
    // 提示信息
    public string|int $msg = "No connection";

    // 连接地址 (例如 tcp://127.0.0.1:11223)
    public string $url = "";
    // 连接方式 (1=立即连接,2=异步连接,3=持久连接)
    public int $flags = 1;
    // 连接和读取超时时间
    public int|float $timeout = 3.0;
    // stream上下文资源，可用于设置 SSL 选项、超时等
    public array $context = [];
    // 连接对像
    public mixed $client = "";

    // 是否分块发送
    public bool $sendChunk = true;
    // 分块发送大小
    public int $sendSize = 8192;
    // 结尾发送符号
    public string $sendSymbol = "\n";
    // 转换后发送内容
    public mixed $sendBody = "";

    // 是否分块读取(false只读取只定字节数)
    public bool $readChunk = true;
    // 分块读取大小
    public int $readSize = 8192;
    // 结尾读取符号
    public string $readSymbol = "\n";
    // 原始返回内容
    public string $readBody = "";

    /**
     * @param string|array $url
     * @param mixed        $data
     * @param array        $config
     * @return static
     */
    public static function get(string|array $url, mixed $data, array $config = []): static {
        return static::link($url, $config)->send($data)->read();
    }

    /**
     * @param string|array $url
     * @param array        $config
     * @return static
     */
    public static function link(string|array $url, array $config = []): static {
        return (new self($url, $config));
    }

    /**
     * @param string|array $url    连接地址 例如 tcp://127.0.0.1:11223
     * @param array        $config 配置
     */
    public function __construct(string|array $url, array $config = []) {
        $config = array_merge([
            // 连接地址 (例如 tcp://127.0.0.1:11223)
            "url"        => $url,
            // 连接方式 (1=立即连接,2=异步连接,3=持久连接)
            "flags"      => 1,
            // 连接和读取超时时间
            "timeout"    => 3.0,
            // 是否分块发送
            "sendChunk"  => true,
            // 分块发送大小
            "sendSize"   => 8192,
            // 结尾发送符号
            "sendSymbol" => "\n",
            // 是否分块读取(false只读取只定字节数)
            "readChunk"  => true,
            // 分块读取大小
            "readSize"   => 8192,
            // 结尾读取符号
            "readSymbol" => "\n",
            // stream上下文资源，可用于设置 SSL 选项、超时等
            "context"    => []
        ], $config);
        $this->url = (string) $config['url'];
        $this->flags = (int) $config['flags'];
        $this->timeout = (float) $config['timeout'];
        $this->context = (array) $config['context'];
        $this->sendChunk = (bool) $config['sendChunk'];
        $this->sendSize = (int) $config['sendSize'];
        $this->sendSymbol = (string) $config['sendSymbol'];
        $this->readChunk = (bool) $config['readChunk'];
        $this->readSize = (int) $config['readSize'];
        $this->readSymbol = (string) $config['readSymbol'];
    }

    /**
     * 发送数据
     * @param mixed       $data   发送的内容
     * @param bool|null   $chunk  是否分块发送
     * @param int|null    $size   分块发送大小
     * @param string|null $symbol 结尾发送符号
     * @return $this
     */
    public function send(mixed $data, bool|null $chunk = null, int|null $size = null, string|null $symbol = null): static {
        $this->sendChunk = $chunk ?? $this->sendChunk;
        $this->sendSize = $size ?? $this->sendSize;
        $this->sendSymbol = $symbol ?? $this->sendSymbol;
        (!$this->client && $this->code != 400) && $this->connect();
        $body = ($data instanceof Closure) ? $data() : $data;
        $this->sendBody = (string) ((is_array($body) || is_object($body)) ? json_encode($body, JSON_UNESCAPED_UNICODE) : $body);
        if ($this->code == 200 && $this->client) {
            if ($this->sendChunk) {
                $written = 0;
                $total = strlen($this->sendBody);
                while ($written < $total) {
                    $chunk = substr($this->sendBody, $written, $this->sendSize);
                    $n = fwrite($this->client, $chunk);
                    $written += $n;
                }
                fwrite($this->client, $this->sendSymbol);
            } else {
                fwrite($this->client, $this->sendBody . $this->sendSymbol);
            }
        }
        return $this;
    }

    /**
     * 读取数据
     * @param bool|null   $chunk  是否分块读取(false只读取只定字节数)
     * @param int|null    $size   分块读取大小
     * @param string|null $symbol 结尾读取符号
     * @return mixed
     */
    public function read(bool|null $chunk = null, int|null $size = null, string|null $symbol = null): mixed {
        $this->readChunk = $chunk ?? $this->readChunk;
        $this->readSize = $size ?? $this->readSize;
        $this->readSymbol = $symbol ?? $this->readSymbol;
        return $this->receive();
    }

    /**
     * 开始连接
     * @param int|null   $flags   1=立即连接,2=异步连接,3=持久连接
     * @param int|null   $timeout 连接和读取超时时间
     * @param array|null $context stream上下文资源，可用于设置 SSL 选项、超时等
     * @return $this
     */
    public function connect(int|null $flags = null, int|null $timeout = null, array|null $context = null): static {
        $this->flags = $flags ?? $this->flags;
        $this->timeout = $timeout ?? $this->timeout;
        $this->context = $context ?? $this->context;
        $context = !empty($this->context) ? stream_context_create($this->context) : null;
        $flag = [1 => STREAM_CLIENT_CONNECT, 2 => STREAM_CLIENT_ASYNC_CONNECT, 3 => STREAM_CLIENT_PERSISTENT];
        $this->client = @stream_socket_client($this->url, $code, $msg, $this->timeout, $flag[$this->flags] ?? $this->flags, $context);
        if (!$this->client) {
            $this->code = 400;
            $this->msg = "$msg ($code)";
            return $this;
        }
        $this->code = 200;
        $this->msg = "success";
        stream_set_blocking($this->client, true);
        stream_set_timeout($this->client, $this->timeout);
        return $this;
    }

    /**
     * 关闭连接
     * @return $this
     */
    public function close(): static {
        ($this->client && is_resource($this->client)) && fclose($this->client);
        return $this;
    }

    /**
     * 读取处理
     * @param string $body
     * @return mixed
     */
    private function receive(string $body = ""): mixed {
        if ($this->code == 200 && $this->client) {
            if ($this->readChunk) {
                if ($this->readSymbol) {
                    while (!feof($this->client)) {
                        $chunk = fread($this->client, $this->readSize);
                        if ($chunk === false) {
                            break;
                        }
                        if ($chunk === "") {
                            continue;
                        }
                        $body .= $chunk;
                        if (str_ends_with($body, $this->readSymbol)) {
                            $body = rtrim($body, $this->readSymbol);
                            break;
                        }
                    }
                } else {
                    while (!feof($this->client)) {
                        $chunk = fread($this->client, $this->readSize);
                        if ($chunk === false) {
                            break;
                        }
                        if ($chunk === "") {
                            continue;
                        }
                        $body .= $chunk;
                    }
                }
            } else {
                if ($this->readSymbol) {
                    $body = stream_get_line($this->client, $this->readSize, $this->readSymbol);
                } else {
                    $body = fgets($this->client, $this->readSize);
                }
            }
            $this->readBody = $body;
            return !empty($array = json_decode($body, true)) ? $array : $body;
        }
        return ['code' => $this->code, 'msg' => $this->msg, 'data' => []];
    }
}