<?php

namespace AlonePhp\Code;

class Check {
    /**
     * 8-24宇符，必须为大写字母、小写字母、数字和符号的组合，不能包含空格
     * @param string $data
     * @param int    $top
     * @param int    $end
     * @return bool|int
     */
    public static function passPreg(string $data, int $top = 8, int $end = 24): bool|int {
        return preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\[\]{}\-=_+|;:,.<>\/?])(?!.*\s).{" . ($end > 0 ? $top . "," . $end : $top) . "}+$/", $data);
    }

    /**
     * 5-12字符（可包含大小写英文字母，数字或下划线）
     * @param string $data
     * @param int    $top
     * @param int    $end
     * @return bool|int
     */
    public static function userPreg(string $data, int $top = 5, int $end = 12): bool|int {
        return preg_match("/^[a-zA-Z0-9_]{" . ($end > 0 ? $top . "," . $end : $top) . "}$/", $data);
    }

    /**
     * 验证字符串不支持符号
     * 用户名由6-12位数字/字母组成
     * @param string $data //数据
     * @param int    $top  //最小长度
     * @param int    $end  //最大长度
     * @return false|int
     */
    public static function isStr(string $data, int $top, int $end = 0): bool|int {
        return preg_match("/^[A-Za-z0-9]{" . ($end > 0 ? $top . "," . $end : $top) . "}$/", $data);
    }

    /**
     * @param string $data
     * @param int    $top
     * @param int    $end
     * @return bool|int
     */
    public static function isLen(string $data, int $top, int $end): bool|int {
        return !empty((strlen($data) >= $top && strlen($data) <= $end));
    }

    /**
     * 验证字符串支持符号
     * 密码由6-16位数字/字母/符号组成
     * @param string $data //数据
     * @param int    $top  //最小长度
     * @param int    $end  //最大长度
     * @return false|int
     */
    public static function isStrSign(string $data, int $top, int $end = 0): bool|int {
        return preg_match("/(?=.{" . ($end > 0 ? $top . "," . $end : $top) . "})(?=.*\d)(?=.*[a-z])[\x20-\x7f]*/i", $data);
    }

    /**
     * 验证姓名
     * @param $name
     * @return bool|int
     */
    public static function isName($name): bool|int {
        return preg_match('/^[\x{4e00}-\x{9fa5}]{2,4}$/u', $name);
    }

    /**
     * 验证邮箱
     * @param $data
     * @return bool|int
     */
    public static function isMail($data): bool|int {
        return preg_match('/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/', $data);
    }

    /**
     * 验证国内手机
     * @param $data
     * @return false|int
     */
    public static function isMobile($data): bool|int {
        return preg_match("/^1(3|4|5|6|7|8|9)\d{9}$/", $data);
    }

    /**
     *  匹配 陆港澳台手机号码带区号
     * @param $phone
     * @return bool
     */
    public static function isPhone($phone): bool {
        return preg_match("/^\+86((13|14|15|16|17|18|19)[0-9])\d{8}$|^\+852([1-9])\d{7}$|^\+886[0][9]\d{8}$|^\+8536\d{7}$/", $phone);
    }

    /**
     * 验证是否ip
     * @param $Ip
     * @return bool
     */
    public static function isIp($Ip): bool {
        return preg_match("/^[\d]+\.[\d]+\.[\d]+\.[\d]+$/isU", $Ip);
    }

    /**
     * 验证大陆身份证号码
     * @param $value
     * @return bool
     */
    public static function idCard($value): bool {
        $id = strtoupper($value);
        $arr_split = [];
        if (preg_match("/(^\d{15}$)|(^\d{17}([0-9]|X)$)/", $id)) {
            //检查15位
            if (15 == strlen($id)) {
                preg_match("/^(\d{6})+(\d{2})+(\d{2})+(\d{2})+(\d{3})$/", $id, $arr_split);
                //检查生日日期是否正确
                $dtm_birth = "19" . $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
                if (\strtotime($dtm_birth)) {
                    return true;
                }
            } else {
                //检查18位
                preg_match("/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/", $id, $arr_split);
                $dtm_birth = $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
                //检查生日日期是否正确
                if (strtotime($dtm_birth)) {
                    //检验18位身份证的校验码是否正确。
                    //校验位按照ISO 7064:1983.MOD 11-2的规定生成，X能够认为是数字10。
                    $arr_ch = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
                    $arr_int = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
                    $sign = 0;
                    for ($i = 0; $i < 17; $i++) {
                        $b = (int) $id[$i];
                        $w = $arr_int[$i];
                        $sign += $b * $w;
                    }
                    $n = $sign % 11;
                    $val_num = $arr_ch[$n];
                    if ($val_num == substr($id, 17, 1)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * 身份证性别
     * true=男，false=女
     * @param string $val
     * @return bool
     */
    public static function cardSex(string $val): bool {
        return !((substr($val, 16, 1)) % 2 === 0);
    }

    /**
     *  根据身份证号码获取生日
     * @param string $card
     * @return string
     */
    public static function cardBirth(string $card): string {
        $bir = substr($card, 6, 8);
        $year = (int) substr($bir, 0, 4);
        $month = (int) substr($bir, 4, 2);
        $day = (int) substr($bir, 6, 2);
        return $year . "-" . $month . "-" . $day;
    }

    /**
     *  根据身份证号码计算年龄
     * @param $card
     * @return float|int
     */
    public static function cardAge($card): float|int {
        $date = strtotime(substr($card, 6, 8));
        $today = strtotime('today');
        $diff = floor(($today - $date) / 86400 / 365);
        return strtotime(substr($card, 6, 8) . ' +' . $diff . 'years') > $today ? ($diff + 1) : $diff;
    }

    /**
     *  根据身份证号，返回对应的生肖
     * @param $card
     * @return string
     */
    public static function cardZodiac($card): string {
        $start = 1901;
        $end = (int) substr($card, 6, 4);
        $x = ($start - $end) % 12;
        $val = '';
        if ($x == 1 || $x == -11)
            $val = '鼠';
        if ($x == 0)
            $val = '牛';
        if ($x == 11 || $x == -1)
            $val = '虎';
        if ($x == 10 || $x == -2)
            $val = '兔';
        if ($x == 9 || $x == -3)
            $val = '龙';
        if ($x == 8 || $x == -4)
            $val = '蛇';
        if ($x == 7 || $x == -5)
            $val = '马';
        if ($x == 6 || $x == -6)
            $val = '羊';
        if ($x == 5 || $x == -7)
            $val = '猴';
        if ($x == 4 || $x == -8)
            $val = '鸡';
        if ($x == 3 || $x == -9)
            $val = '狗';
        if ($x == 2 || $x == -10)
            $val = '猪';
        return $val;
    }

    /**
     *  根据身份证号，返回对应的星座
     * @param $card
     * @return string
     */
    public static function cardConstellation($card): string {
        $b = substr($card, 10, 4);
        $m = (int) substr($b, 0, 2);
        $d = (int) substr($b, 2);
        $val = '';
        if (($m == 1 && $d <= 21) || ($m == 2 && $d <= 19)) {
            $val = "水瓶座";
        } elseif (($m == 2 && $d > 20) || ($m == 3 && $d <= 20)) {
            $val = "双鱼座";
        } elseif (($m == 3 && $d > 20) || ($m == 4 && $d <= 20)) {
            $val = "白羊座";
        } elseif (($m == 4 && $d > 20) || ($m == 5 && $d <= 21)) {
            $val = "金牛座";
        } elseif (($m == 5 && $d > 21) || ($m == 6 && $d <= 21)) {
            $val = "双子座";
        } elseif (($m == 6 && $d > 21) || ($m == 7 && $d <= 22)) {
            $val = "巨蟹座";
        } elseif (($m == 7 && $d > 22) || ($m == 8 && $d <= 23)) {
            $val = "狮子座";
        } elseif (($m == 8 && $d > 23) || ($m == 9 && $d <= 23)) {
            $val = "处女座";
        } elseif (($m == 9 && $d > 23) || ($m == 10 && $d <= 23)) {
            $val = "天秤座";
        } elseif (($m == 10 && $d > 23) || ($m == 11 && $d <= 22)) {
            $val = "天蝎座";
        } elseif (($m == 11 && $d > 22) || ($m == 12 && $d <= 21)) {
            $val = "射手座";
        } elseif (($m == 12 && $d > 21) || ($m == 1 && $d <= 20)) {
            $val = "魔羯座";
        }
        return $val;
    }

    /**
     * 验证香港身份证号码
     * @param $value
     * @return bool
     */
    public static function idCardHk($value): bool {
        return preg_match("/[A-Z]{1,2}[0-9]{6}([0-9A])/", $value);
    }

    /**
     * 验证澳门身份证
     * @param $value
     * @return bool
     */
    public static function idCardMo($value): bool {
        return preg_match("/^[1|5|7][0-9]{6}\([0-9Aa]\)/", $value);
    }

    /**
     * 验证台湾身份证
     * @param $value
     * @return bool
     */
    public static function idCardTwn($value): bool {
        return preg_match("/[A-Z][0-9]{9}/", $value);
    }

    /**
     * 验证回乡证
     * @param $value
     * @return bool|int
     */
    public static function isHrp($value): bool|int {
        return preg_match("/(H|M)(\d{10})$/", $value);
    }

    /**
     * 验证台胞证
     * @param $value
     * @return bool|int
     */
    public static function isMtp($value): bool|int {
        return preg_match("/(^\d{8})$/", $value);
    }

    /**
     * 验证护照
     * @param $value
     * @return bool|int
     */
    public static function IsPassport($value): bool|int {
        return preg_match("/^1[45][0-9]{7}|([P|p|S|s]\d{7})|([S|s|G|g]\d{8})|([Gg|Tt|Ss|Ll|Qq|Dd|Aa|Ff]\d{8})|([H|h|M|m]\d{8，10})$/", $value);
    }
}