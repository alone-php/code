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
    // 默认配置
    public static array $config = [
        // 连接方式 (1=立即连接,2=异步连接,3=持久连接)
        "flags"      => 1,
        // 连接和读取超时时间
        "timeout"    => 3.0,
        // 是否json发送
        "sendJson"   => true,
        // 是否分块发送
        "sendChunk"  => true,
        // 分块发送大小
        "sendSize"   => 8192,
        // 结尾发送符号
        "sendSymbol" => "\n",
        // 接收是否json
        "readJson"   => true,
        // 是否分块读取(false只读取只定字节数)
        "readChunk"  => true,
        // 分块读取大小
        "readSize"   => 8192,
        // 结尾读取符号
        "readSymbol" => "\n",
        // stream上下文资源，可用于设置 SSL 选项、超时等
        "context"    => []
    ];
    //-------------------连接配置-------------------
    // 连接地址 (例如 tcp://127.0.0.1:11223)
    public string $url = "";
    // 连接方式 (1=立即连接,2=异步连接,3=持久连接)
    public int $flags = 0;
    // 连接和读取超时时间
    public int|float $timeout = 0;
    // stream上下文资源，可用于设置 SSL 选项、超时等
    public array $context = [];
    // 连接对像
    public mixed $client = "";
    //-------------------发送配置-------------------
    // 是否json发送
    public bool $sendJson = false;
    // 是否分块发送
    public bool $sendChunk = false;
    // 分块发送大小
    public int $sendSize = 0;
    // 结尾发送符号
    public string $sendSymbol = "";
    // 转换后发送内容
    public mixed $sendBody = "";
    //-------------------读取配置-------------------
    // 是否json接收
    public bool $readJson = false;
    // 是否分块读取(false只读取只定字节数)
    public bool $readChunk = false;
    // 分块读取大小
    public int $readSize = 0;
    // 结尾读取符号
    public string $readSymbol = "";
    // 原始返回内容
    public string $readBody = "";

    /**
     * @param string|array $url    连接地址 例如 tcp://127.0.0.1:11223
     * @param mixed        $data   发送数据
     * @param array        $config 配置
     * @return mixed
     */
    public static function link(string|array $url, mixed $data, array $config = []): mixed {
        $client = static::url($url, $config);
        try {
            return $client->send($data)->read();
        } catch (Throwable $e) {
            return ['code' => 500, 'msg' => $e->getMessage(), 'data' => ['code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine()]];
        } finally {
            $client->close();
        }
    }

    /**
     * @param string|array $url    连接地址 例如 tcp://127.0.0.1:11223
     * @param array        $config 配置
     * @return static
     */
    public static function url(string|array $url, array $config = []): static {
        return (new self($url, $config));
    }

    /**
     * @param string|array $url    连接地址 例如 tcp://127.0.0.1:11223
     * @param array        $config 配置
     */
    public function __construct(string|array $url, array $config = []) {
        $config = array_merge(static::$config, ["url" => $url], $config);
        $this->url = (string) $config['url'];
        $this->flags = (int) $config['flags'];
        $this->timeout = (float) $config['timeout'];
        $this->context = (array) $config['context'];
        $this->sendJson = (bool) $config['sendJson'];
        $this->sendChunk = (bool) $config['sendChunk'];
        $this->sendSize = (int) $config['sendSize'];
        $this->sendSymbol = (string) $config['sendSymbol'];
        $this->readJson = (bool) $config['readJson'];
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
        $this->sendBody = is_string($body) ? $body : ($this->sendJson ? json_encode($body, JSON_UNESCAPED_UNICODE) : $body);
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
            return $this->readJson ? json_decode($body, true) : $body;
        }
        return ['code' => $this->code, 'msg' => $this->msg, 'data' => null];
    }
}