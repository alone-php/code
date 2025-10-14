<?php

namespace AlonePhp\Code;

use Closure;
use Throwable;

/**
 * Tcp客户端
 */
class TcpClient {
    // 状态码,200=成功,300=未连接,400=连接失败,500=系统错误,600=发送错误,700=读取错误
    public int $code = 300;
    // 提示信息
    public string $msg = "No connection";
    // 连接对像
    public mixed $client = null;
    // 转换后发送内容
    public string $data = "";
    // 原始返回内容
    public string $body = "";
    // 默认配置
    public array $config = [
        // 连接方式 (1=立即连接,2=异步连接,3=持久连接)
        "flags"      => 1,
        // 连接和读取超时时间
        "timeout"    => 3,
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

    /**
     * @param string $url    连接地址 例如 tcp://127.0.0.1:11223
     * @param mixed  $data   发送数据
     * @param array  $config 配置
     * @return mixed
     */
    public static function link(string $url, mixed $data, array $config = []): mixed {
        $client = static::url($url, $config);
        try {
            return $client->send($data)->read();
        } catch (Throwable $e) {
            return [
                'code' => 500,
                'msg'  => $e->getMessage(),
                'data' => [
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        } finally {
            $client->close();
        }
    }

    /**
     * @param string $url    连接地址 例如 tcp://127.0.0.1:11223
     * @param array  $config 配置
     * @return static
     */
    public static function url(string $url, array $config = []): static {
        return (new self($url, $config));
    }

    /**
     * @param string $url    连接地址 例如 tcp://127.0.0.1:11223
     * @param array  $config 配置
     */
    public function __construct(string $url, array $config = []) {
        $this->config = array_merge($this->config, $config, ['url' => $url]);
    }

    /**
     * 开始连接
     * @param int|null   $flags   1=立即连接,2=异步连接,3=持久连接
     * @param int|null   $timeout 连接和读取超时时间
     * @param array|null $context stream上下文资源，可用于设置 SSL 选项、超时等
     * @return $this
     */
    public function connect(int|null $flags = null, int|null $timeout = null, array|null $context = null): static {
        $this->config['flags'] = (int) ($flags ?? $this->config['flags']);
        $this->config['timeout'] = (int) ($timeout ?? $this->config['timeout']);
        $this->config['context'] = $context ?? $this->config['context'];
        $context = null;
        if (!empty($this->config['context'])) {
            if (is_array($this->config['context'])) {
                $context = stream_context_create($this->config['context']);
            } elseif (is_resource($this->config['context'])) {
                $context = $this->config['context'];
            }
        }
        $flag = [1 => STREAM_CLIENT_CONNECT, 2 => STREAM_CLIENT_ASYNC_CONNECT, 3 => STREAM_CLIENT_PERSISTENT];
        $this->client = @stream_socket_client(
            $this->config['url'],
            $code,
            $msg,
            $this->config['timeout'],
            $flag[$this->config['flags']] ?? $this->config['flags'],
            $context
        );
        if (!$this->client) {
            $this->code = 400;
            $this->msg = $msg ?: "Connection failed to {$this->config['url']} ($code)";
            return $this;
        }
        $this->code = 200;
        $this->msg = "success";
        stream_set_blocking($this->client, true);
        stream_set_timeout($this->client, $this->config['timeout']);
        return $this;
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
        $this->config['sendChunk'] = (bool) ($chunk ?? $this->config['sendChunk']);
        $this->config['sendSize'] = (int) ($size ?? $this->config['sendSize']);
        $this->config['sendSymbol'] = (string) ($symbol ?? $this->config['sendSymbol']);
        ($this->code != 400 && !$this->isConnected()) && $this->connect();
        $body = ($data instanceof Closure) ? $data() : $data;
        if (is_string($body) || is_numeric($body)) {
            $this->data = (string) $body;
        } else {
            if ($this->config['sendJson']) {
                $json = json_encode($body, JSON_UNESCAPED_UNICODE);
                if ($json === false) {
                    $this->code = 600;
                    $this->msg = "json error";
                } else {
                    $this->data = $json;
                }
            } else {
                $this->data = (string) $body;
            }
        }
        if ($this->code == 200 && $this->isConnected()) {
            if ($this->config['sendChunk']) {
                $written = 0;
                $total = strlen($this->data);
                while ($written < $total) {
                    $chunk = substr($this->data, $written, $this->config['sendSize']);
                    $n = @fwrite($this->client, $chunk);
                    if ($n === false) {
                        $this->code = 600;
                        $this->msg = "send failed";
                        break;
                    }
                    if ($n === 0) {
                        usleep(1000);
                        continue;
                    }
                    $written += $n;
                }
                if ($this->config['sendSymbol']) {
                    @fwrite($this->client, $this->config['sendSymbol']);
                }
            } else {
                @fwrite($this->client, $this->data . ($this->config['sendSymbol'] ?: ""));
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
        $this->config['readChunk'] = (bool) ($chunk ?? $this->config['readChunk']);
        $this->config['readSize'] = (int) ($size ?? $this->config['readSize']);
        $this->config['readSymbol'] = (string) ($symbol ?? $this->config['readSymbol']);
        return $this->receive();
    }

    /**
     * 关闭连接
     * @return $this
     */
    public function close(): static {
        ($this->isConnected()) && fclose($this->client);
        $this->code = 300;
        $this->client = null;
        $this->msg = "No connection";
        return $this;
    }

    /**
     * 是否超时
     * @return bool
     */
    public function isOut(): bool {
        $meta = stream_get_meta_data($this->client);
        return !empty($meta['timed_out'] ?? null);
    }

    /**
     * 是否连接有效
     * @return bool
     */
    public function isConnected(): bool {
        return $this->client && (is_resource($this->client));
    }

    /**
     * 读取处理
     * @param string $body
     * @return mixed
     */
    protected function receive(string $body = ""): mixed {
        if ($this->code == 200 && $this->isConnected()) {
            if ($this->config['readChunk']) {
                $start = microtime(true);
                if ($this->config['readSymbol']) {
                    while (!feof($this->client)) {
                        $chunk = fread($this->client, $this->config['readSize']);
                        if ($chunk === false) {
                            break;
                        }
                        if ($chunk === "") {
                            usleep(1000);
                            continue;
                        }
                        $body .= $chunk;
                        if (str_ends_with($body, $this->config['readSymbol'])) {
                            $body = rtrim($body, $this->config['readSymbol']);
                            break;
                        }
                        if (microtime(true) - $start > $this->config['timeout']) {
                            $this->code = 700;
                            $this->msg = "read timeout - 1";
                            break;
                        }
                    }
                } else {
                    while (!feof($this->client)) {
                        $chunk = fread($this->client, $this->config['readSize']);
                        if ($chunk === "") {
                            usleep(1000);
                            continue;
                        }
                        if ($chunk === false) {
                            break;
                        }
                        $body .= $chunk;
                        if (microtime(true) - $start > $this->config['timeout']) {
                            $this->code = 700;
                            $this->msg = "read timeout - 2 ";
                            break;
                        }
                    }
                }
            } else {
                if ($this->config['readSymbol']) {
                    $body = stream_get_line($this->client, $this->config['readSize'], $this->config['readSymbol']);
                } else {
                    $body = fgets($this->client, $this->config['readSize']);
                }
            }
            $this->body = $body;
            if ($this->config['readJson']) {
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }
            return $this->body;
        }
        return ['code' => $this->code, 'msg' => $this->msg, 'data' => null];
    }
}