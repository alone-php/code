<?php

namespace AlonePhp\Code;

use Closure;
use Throwable;

class TcpClient {
    // 状态码
    public int $code = 201;
    // 提示信息
    public string $msg = "";
    // 解析内容
    public mixed $data = null;
    // 发送内容
    public string $sendBody = "";
    // 返回内容
    public string $readBody = "";
    // 连接对像
    public mixed $client = null;
    // 连接列表
    protected static array $flags = [
        1 => STREAM_CLIENT_CONNECT,
        2 => STREAM_CLIENT_ASYNC_CONNECT,
        3 => STREAM_CLIENT_PERSISTENT
    ];
    // 提示对应列表
    protected static array $error = [
        // 执行成功
        "200" => "Success",
        // 没有连接
        "201" => "Not connected",
        // 连接失败
        "202" => "Connection refused",
        // 连接成功
        "203" => "Connection success",
        // 连接关闭
        "204" => "Connection closed",
        // 连接超时
        "205" => "Connection timeout",
        // 发送内容为空
        "206" => "Send data null",
        // 数据发送中
        "207" => "Send data loading",
        // 数据发送超时
        "208" => "Send data timeout",
        // 发送数据失败
        "209" => "Send data failed",
        // 数据发送完成
        "210" => "Send data success",
        // 包长度接收超时
        "211" => "Read length timeout",
        // 包长度接收错误
        "212" => "Read length error 1",
        // 包长度错误
        "213" => "Read length error 2",
        // 数据接收超时
        "214" => "Read body timeout",
        // 数据接收错误
        "215" => "Read body error",
        // 系统错误
        "500" => "System error"
    ];
    // 默认配置
    protected array $config = [
        /**
         * ================= 连接设置 =================
         */
        // 类型 text(数据包+结尾符号) 或 frame(包总长度+包体)
        "scheme"         => "frame",
        // 连接方式 (1=立即连接,2=异步连接,3=持久连接)
        "flags"          => 1,
        // 连接和读取超时时间
        "timeout"        => 3,
        // stream上下文资源，可用于设置 SSL 选项、超时等
        "context"        => [],
        /**
         * ================= 发送设置 =================
         */
        // 类型为空默认scheme
        "sendScheme"     => null,
        // 分块发送大小
        "sendSize"       => 8192,
        // 发送包长字节数 (4=4字节,8=8字节)
        "sendPackLength" => 4,
        // 发送包长字节序 (N=大端序,V=小端序)
        "sendPackFormat" => "N",
        // 结尾发送符号
        "sendSymbol"     => "\n",
        // 空转时等待时间
        "sendSleep"      => 5000,
        /**
         * ================= 读取设置 =================
         */
        // 类型为空默认scheme
        "readScheme"     => null,
        // 分块读取大小
        "readSize"       => 8192,
        // 接收包长字节数 (4=4字节,8=8字节)
        "readPackLength" => 4,
        // 接收包长字节序 (N=大端序,V=小端序)
        "readPackFormat" => "N",
        // 结尾读取符号
        "readSymbol"     => "\n",
        // 空转时等待时间
        "readSleep"      => 5000,
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
            $client->send($data)->read();
        } catch (Throwable $e) {
            $client->code(500, [
                'code' => $e->getCode(),
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        } finally {
            $client->close();
        }
        return $client->code == 200 ? $client->data : $client->array();
    }

    /**
     * @param string $url
     * @param mixed  $data
     * @param array  $config
     * @return $this
     */
    public static function async(string $url, mixed $data, array $config = []): static {
        $client = static::url($url, $config);
        try {
            $client->send($data);
        } catch (Throwable $e) {
            $client->code(500, [
                'code' => $e->getCode(),
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
        return $client;
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
        if (str_starts_with($url, "text:")) {
            $this->setConfig('scheme', 'text');
            $this->setConfig('url', "tcp:" . substr($url, strlen("text:")));
        } elseif (str_starts_with($url, "frame:")) {
            $this->setConfig('scheme', 'frame');
            $this->setConfig('url', "tcp:" . substr($url, strlen("frame:")));
        } else {
            $this->setConfig('url', $url);
        }
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 开始连接
     * @return $this
     */
    public function connect(): static {
        $flags = $this->getConfig('flags');
        $flag = static::$flags[$flags] ?? $flags;
        $context = $this->getConfig('context');
        $context = is_array($context) ? stream_context_create($context) : $context;
        $this->client = @stream_socket_client($this->getConfig('url'), $code, $msg, $this->getConfig('timeout'), $flag, $context);
        if (!$this->isConnected()) {
            $this->code(202, ['code' => $code, 'msg' => $msg]);
            return $this;
        }
        $this->code(203);
        stream_set_blocking($this->client, true);
        stream_set_timeout($this->client, $this->getConfig('timeout', 3));
        return $this;
    }

    /**
     * 发送数据
     * @param mixed $data
     * @return $this
     */
    public function send(mixed $data): static {
        // 不是连接失败和没有连接时开始连接
        ($this->code != 202 && !$this->isConnected()) && $this->connect();
        // 是否执行包
        $body = ($data instanceof Closure) ? $data() : $data;
        // 数据和对像时使用json
        $this->sendBody = (string) ((is_array($body) || is_object($body)) ? json_encode($body, JSON_UNESCAPED_UNICODE) : $body);
        if ($this->sendBody === "") {
            $this->code(206);
            return $this;
        }
        // 连接成时时发送
        if ($this->code == 203) {
            $this->code(207);
            $sendScheme = strtolower($this->getConfig('sendScheme') ?: $this->getConfig('scheme', 'frame'));
            $sendScheme = in_array($sendScheme, ["text", "frame"]) ? $sendScheme : "frame";
            if ($sendScheme == 'text') {
                $buffer = $this->sendBody . $this->getConfig('sendSymbol', "");
            } else {
                $buffer = static::frameEncode($this->sendBody, $this->getConfig('sendPackLength', 4), $this->getConfig('sendPackFormat', "N"));
            }
            $sendSize = $this->getConfig('sendSize', 8192);
            $sendSleep = $this->getConfig('sendSleep', 2000);
            $sendStatus = $this->sendData($this->client, $buffer, $sendSize, $sendSleep);
            if ($sendStatus === 1) {
                $this->code(210);
                return $this;
            }
            if ($sendStatus === null) {
                $this->code(208);
                return $this;
            }
            if ($sendStatus === true) {
                $this->code(204);
                return $this;
            }
            $this->code(209);
        }
        return $this;
    }

    /**
     * 接收数据
     * @return array
     */
    public function read(): array {
        if ($this->code == 210) {
            $readScheme = strtolower($this->getConfig('readScheme') ?: $this->getConfig('scheme', 'frame'));
            $readScheme = in_array($readScheme, ["text", "frame"]) ? $readScheme : "frame";
            $buffer = $readScheme == 'text' ? $this->textRead() : $this->frameRead();
            $this->code(200, $buffer);
            if (!is_array($buffer)) {
                $this->readBody = $buffer;
                $array = json_decode($buffer, true);
                if (json_last_error() === JSON_ERROR_NONE && $array !== null) {
                    $this->code(200, $array);
                }
            }
        }
        return $this->array();
    }

    /**
     * 关闭连接
     * @return $this
     */
    public function close(): static {
        ($this->isConnected()) && fclose($this->client);
        $this->client = null;
        return $this;
    }

    /**
     * 是否连接有效
     * @return bool
     */
    public function isConnected(): bool {
        return $this->client && (is_resource($this->client));
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function setConfig(string $key, mixed $value): static {
        $this->config[$key] = $value;
        return $this;
    }

    /**
     * @param string|null $key
     * @param mixed|null  $def
     * @return mixed
     */
    public function getConfig(string|null $key = null, mixed $def = null): mixed {
        return isset($key) ? ($this->config[$key] ?? $def) : $this->config;
    }

    /**
     * @return array
     */
    public function array(): array {
        return ['code' => $this->code, 'msg' => $this->msg, 'data' => $this->data];
    }

    /**
     * @param int   $code
     * @param mixed $data
     * @return $this
     */
    protected function code(int $code, mixed $data = null): static {
        $this->code = $code;
        $this->msg = static::$error[$code] ?? $code;
        $this->data = $data;
        return $this;
    }

    /**
     * frame读取
     * @return array|string|null
     */
    protected function frameRead(): array|string|null {
        $size = (int) $this->getConfig('readSize', 8192);
        $size = $size > 0 ? $size : 8192;
        $packLength = $this->getConfig('readPackLength', 4);
        $packLength = in_array($packLength, [4, 8]) ? $packLength : 4;
        $readSleep = $this->getConfig('readSleep', 2000);
        $lengthBuffer = static::readFrameData($this->client, $packLength, $size, $readSleep);
        if ($lengthBuffer === true) {
            return $this->code(204)->array();
        }
        if ($lengthBuffer === null) {
            return $this->code(211)->array();
        }
        if ($lengthBuffer === false) {
            return $this->code(212)->array();
        }
        $length = static::frameLength($lengthBuffer, $packLength, $this->getConfig('readPackFormat', "N"));
        if ($length === 0) {
            return $this->code(213)->array();
        }
        $readLength = $length > $packLength ? $length - $packLength : $length;
        $bodyBuffer = self::readFrameData($this->client, $readLength, $size, $readSleep);
        if ($bodyBuffer === true) {
            return $this->code(204)->array();
        }
        if ($bodyBuffer === null) {
            return $this->code(214)->array();
        }
        if ($bodyBuffer === false) {
            return $this->code(215)->array();
        }
        return $bodyBuffer;
    }

    /**
     * text读取
     * @return array|string|null
     */
    protected function textRead(): array|string|null {
        $size = (int) $this->getConfig('readSize', 8192);
        $size = $size > 0 ? $size : 8192;
        $readSleep = $this->getConfig('readSleep', 2000);
        $bodyBuffer = self::readTextData($this->client, $this->getConfig('readSymbol', "\n"), $size, $readSleep);
        if ($bodyBuffer === true) {
            return $this->code(204)->array();
        }
        if ($bodyBuffer === null) {
            return $this->code(214)->array();
        }
        if ($bodyBuffer === false) {
            return $this->code(215)->array();
        }
        return $bodyBuffer;
    }

    /**
     * text发送
     * 支持分块读取、超时和 EOF 检测
     * @param resource $socket    TCP 或其他流资源
     * @param string   $symbol    消息结尾符号，默认 "\n"
     * @param int      $chunkSize 每次最大读取字节数，默认8192
     * @param int      $sleep     空转等待微秒，默认5000
     * @return string|bool|null 成功返回完整消息（去掉结尾符），失败返回 false，超时返回 null,true=连接已关闭
     */
    public static function readTextData($socket, string $symbol = "\n", int $chunkSize = 8192, int $sleep = 5000): string|bool|null {
        $body = '';
        $chunkSize = $chunkSize > 0 ? $chunkSize : 8192;
        $sleep = $sleep > 0 ? $sleep : 2000;
        while (true) {
            $meta = stream_get_meta_data($socket);
            if ($meta['timed_out'] ?? '') {
                return null;
            }
            if (feof($socket)) {
                return true;
            }
            $chunk = fread($socket, $chunkSize);
            if ($chunk === false) {
                return false;
            }
            if ($chunk === '') {
                usleep($sleep);
                continue;
            }
            $body .= $chunk;
            if ($symbol !== '' && str_ends_with($body, $symbol)) {
                return rtrim($body, $symbol);
            }
        }
    }

    /**
     * 从流中读取指定长度的数据（支持分块读取、超时和EOF检测）
     * @param resource $socket    TCP 或其他流资源
     * @param int      $length    需要读取的字节数
     * @param int      $chunkSize 每次最大读取字节数，默认8192
     * @param int      $sleep     空转等待微秒，默认5000
     * @return string|bool|null 成功返回完整数据，失败返回false，超时返回null,true=连接已关闭
     */
    public static function readFrameData($socket, int $length, int $chunkSize = 8192, int $sleep = 2000): string|bool|null {
        $body = '';
        $chunkSize = $chunkSize > 0 ? $chunkSize : 8192;
        $remaining = $length;
        $sleep = $sleep > 0 ? $sleep : 2000;
        while ($remaining > 0) {
            $meta = stream_get_meta_data($socket);
            if ($meta['timed_out'] ?? '') {
                return null;
            }
            if (feof($socket)) {
                return true;
            }
            $readSize = min($chunkSize, $remaining);
            $chunk = fread($socket, $readSize);
            if ($chunk === false) {
                return false;
            }
            if ($chunk === '') {
                usleep($sleep);
                continue;
            }
            $body .= $chunk;
            $remaining -= strlen($chunk);
        }
        return $body;
    }

    /**
     * 分块发送数据到流（支持超时、空转等待）
     * @param resource $socket    TCP 或其他流资源
     * @param string   $buffer    待发送数据
     * @param int      $chunkSize 每次最大写入字节数，默认8192
     * @param int      $sleep     空转等待微秒，默认5000
     * @return bool|null|int 成功返回1，失败返回false，超时返回null,true=连接已关闭
     */
    public static function sendData($socket, string $buffer, int $chunkSize = 8192, int $sleep = 2000): bool|null|int {
        $written = 0;
        $total = strlen($buffer);
        $chunkSize = $chunkSize > 0 ? $chunkSize : 8192;
        $sleep = $sleep > 0 ? $sleep : 2000;
        while ($written < $total) {
            $meta = stream_get_meta_data($socket);
            if ($meta['timed_out'] ?? '') {
                return null;
            }
            if (feof($socket)) {
                return true;
            }
            $toWrite = min($chunkSize, $total - $written);
            $chunk = substr($buffer, $written, $toWrite);
            $n = @fwrite($socket, $chunk);
            if ($n === false) {
                return false;
            }
            if ($n === 0) {
                usleep($sleep);
                continue;
            }
            $written += $n;
        }
        return 1;
    }

    /**
     * 读取时使用
     * @param string $buffer
     * @param int    $packLength 包长字节数 (4 或 8)
     * @param string $packFormat 字节序 (N=大端 或 V=小端)
     * @return int 返回包总长度，不足时返回0
     */
    public static function frameLength(string $buffer, int $packLength = 4, string $packFormat = 'N'): int {
        $length = in_array($packLength, [4, 8]) ? $packLength : 4;
        if (strlen($buffer) < $length) {
            return 0;
        }
        $packFormat = strtoupper($packFormat);
        $format = in_array($packFormat, ["N", "V"]) ? $packFormat : "N";
        if ($length === 4) {
            $data = unpack($format === 'N' ? 'Ntotal_length' : 'Vtotal_length', $buffer);
            return (int) ($data['total_length'] ?? 0);
        }
        if ($length === 8) {
            if ($format === 'N') {
                $data = unpack('Nhi/Nlo', $buffer);
            } else { // 小端
                $data = unpack('Vlo/Vhi', $buffer);
            }
            $hi = $data['hi'];
            $lo = $data['lo'];
            if (PHP_INT_SIZE >= 8) {
                return ($hi << 32) | $lo;
            } else {
                $val = $hi * 4294967296 + $lo;
                return $val > PHP_INT_MAX ? PHP_INT_MAX : (int) $val;
            }
        }
        return 0;
    }

    /**
     * 发送时使用
     * @param string $buffer
     * @param int    $packLength 包长字节数 (4 或 8)
     * @param string $packFormat 字节序 (N=大端 或 V=小端)
     * @return string
     */
    public static function frameEncode(string $buffer, int $packLength = 4, string $packFormat = 'N'): string {
        $packed = "";
        $packFormat = strtoupper($packFormat);
        $length = in_array($packLength, [4, 8]) ? $packLength : 4;
        $format = in_array($packFormat, ["N", "V"]) ? $packFormat : "N";
        $totalLength = $length + strlen($buffer);
        if ($length === 4) {
            $packed = pack($format, $totalLength);
        } elseif ($length === 8) {
            if ($format === 'N') {
                $packed = pack('NN', $totalLength >> 32, $totalLength & 0xFFFFFFFF);
            } else {
                $packed = pack('VV', $totalLength & 0xFFFFFFFF, $totalLength >> 32);
            }
        }
        return $packed . $buffer;
    }
}