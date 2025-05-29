<?php

namespace AlonePhp\Code\Frame;

trait Bank{
    /**
     * 获取银行列表
     * @return array
     */
    public static function getBankList(): array {
        static $BankList = [];
        if (empty($BankList)) {
            $BankList = static::isJson(@file_get_contents(__DIR__ . '/../../file/bank.json'));
        }
        return $BankList;
    }

    /**
     * 通过名称获取代码
     * @param string $title
     * @param string $default
     * @return mixed
     */
    public static function getBankCode(string $title, string $default = ''): mixed {
        $data = self::getBankList();
        foreach ($data as $v) {
            if ($v['title'] == trim($title)) {
                return $v['code'];
            }
        }
        return $default;
    }

    /**
     * 通过代码获取名称
     * @param string $code
     * @param string $default
     * @return mixed
     */
    public static function getBankTitle(string $code, string $default = ''): mixed {
        $data = self::getBankList();
        foreach ($data as $v) {
            if ($v['code'] == trim($code)) {
                return $v['title'];
            }
        }
        return $default;
    }
}