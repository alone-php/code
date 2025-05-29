<?php

namespace AlonePhp\Code\Frame;

trait Amount {
    /**
     * 随机金额
     * @param int|float $balance 订单金额
     * @param int|float $min     最小
     * @param int|float $max     最大
     * @param array     $arr     现有金额列表
     * @param int       $i
     * @return int|float
     */
    public static function randMoney(int|float $balance, int|float $min, int|float $max, array $arr = [], int $i = 0): int|float {
        $money = static::money(($balance - static::randFloat($min, $max)));
        if (!empty($arr)) {
            if (in_array($money, $arr)) {
                if ($i >= 10) {
                    return 0;
                }
                return static::randMoney($balance, $min, $max, $arr, (++$i));
            }
        }
        return $money;
    }

    /**
     * 金额转换回来第三个为空
     * @param        $int
     * @param int    $decimals
     * @param string $separator
     * @param string $thousands
     * @return string
     */
    public static function money($int, int $decimals = 2, string $thousands = '', string $separator = '.'): string {
        return number_format($int, $decimals, $separator, $thousands);
    }

    /**
     * 金额转换
     * 强制加小数点：sprintf("%01.2f", 0) 显示0.00
     * 补够4位：sprintf("%04d", 2) 显示0002
     * @param string|int|float $money
     * @param bool             $type
     * @return string
     */
    public static function toMoney(string|int|float $money, bool $type = false): string {
        return $type ? floatval(preg_replace('/[^(\-\d).]/', '', $money)) : sprintf("%01.2f", round($money, 2));
    }


    /**
     * 随机小数
     * @param int|float $min  最小
     * @param int|float $max  最大
     * @param bool      $type 是否支持负数
     * @return int|float
     */
    public static function randFloat(int|float $min = 0.01, int|float $max = 0.2, bool $type = false): int|float {
        $min = abs($min);
        $max = abs($max);
        $num = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        $num = sprintf("%.2f", $num);
        return ((rand(1, 2) == 1 && $type) ? (-$num) : $num);
    }

    /**
     * 金额转汉字
     * @param $amount
     * @return string
     */
    public static function moneyChinese($amount): string {
        $capitalNumbers = [
            '零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖',
        ];
        $integerUnits = ['', '拾', '佰', '仟',];
        $placeUnits = ['', '万', '亿', '兆',];
        $decimalUnits = ['角', '分', '厘', '毫',];
        $result = [];
        $arr = explode('.', (string) $amount);
        $integer = trim($arr[0] ?? '', '-');
        $decimal = $arr[1] ?? '';
        if (!((int) $decimal)) {
            $decimal = '';
        }
        $integerNumbers = $integer ? array_reverse(str_split($integer)) : [];
        $last = null;
        foreach (array_chunk($integerNumbers, 4) as $chunkKey => $chunk) {
            if (!((int) implode('', $chunk))) {
                continue;
            }
            array_unshift($result, $placeUnits[$chunkKey]);
            foreach ($chunk as $key => $number) {
                if (!$number && (!$last || $key === 0)) {
                    $last = $number;
                    continue;
                }
                $last = $number;
                if ($number) {
                    array_unshift($result, $integerUnits[$key]);
                }
                array_unshift($result, $capitalNumbers[$number]);
            }
        }
        if (!$result) {
            $result[] = $capitalNumbers[0];
        }
        $result[] = '圆';
        if (!$decimal) {
            $result[] = '整';
        }
        $decimalNumbers = $decimal ? str_split($decimal) : [];
        foreach ($decimalNumbers as $key => $number) {
            $result[] = $capitalNumbers[$number];
            $result[] = $decimalUnits[$key];
        }
        if (str_starts_with((string) $amount, '-')) {
            array_unshift($result, '负');
        }
        return implode('', $result);
    }
}