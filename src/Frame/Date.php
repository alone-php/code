<?php

namespace AlonePhp\Code\Frame;

use DateInterval;

trait Date {
    /**
     * 获取当前时间
     * @param string|int $time
     * @param string     $format
     * @return string
     */
    public static function getDate(string|int $time = '', string $format = 'Y-m-d H:i:s'): string {
        return date($format, (!empty($time) ? $time : time()));
    }

    /**
     * 生成13位时间
     * @param int|null|string $time
     * @param bool            $date
     * @return int
     */
    public static function getUnix(null|int|string $time = null, bool $date = false): int {
        if (!empty($time)) {
            return sprintf('%.6f', $date ? strtotime($time) : $time) * 1000;
        }
        [$t1, $t2] = explode(" ", microtime());
        return (int) sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }

    /**
     * 13位时间转10位
     * @param string|int $time
     * @return float|int|string
     */
    public static function unixTime(string|int $time): float|int|string {
        return (int) (ceil($time / 1000));
    }

    /**
     * 13位转时间
     * @param string|int $time
     * @param string     $format
     * @return string
     */
    public static function unixDate(string|int $time, string $format = 'Y-m-d H:i:s'): string {
        return static::getDate(static::unixTime($time), $format);
    }

    /**
     * 1=今天,2=昨天,3=本周,4=上周,5=近一周,6=本月,7=上月,8=近一月,9=近三月,10=本季度,11=上季度,12=第1季度,13=第2季度,14=第3季度,15=第4季度,16=当年,17=去年,18=近一年
     * @param string|int   $type   //类型
     * @param string|array $format string|array=返回格式
     * @param int          $time   设置时间
     * @return array
     */
    public static function getDateTime(string|int $type, string|array $format = '', int $time = 0): array {
        $time = ($time > 0 ? $time : time());
        switch ($type) {
            case 1:
                //今天
                $data['top'] = date('Y-m-d 00:00:00', $time);
                $data['end'] = date('Y-m-d 23:59:59', $time);
                break;
            case 2:
                //昨天
                $time = strtotime('-1 day', $time);
                $data['top'] = date('Y-m-d 00:00:00', $time);
                $data['end'] = date('Y-m-d 23:59:59', $time);
                break;
            case 3:
                //本周
                $weekStart = strtotime('monday this week', $time);
                $weekEnd = strtotime('next sunday', $weekStart);
                $data['top'] = date('Y-m-d H:i:s', $weekStart);
                $data['end'] = date('Y-m-d H:i:s', $weekEnd - 1);
                break;
            case 4:
                //上周
                $lastWeekStart = strtotime('last monday', $time);
                $lastWeekEnd = strtotime('next sunday', $lastWeekStart);
                $data['top'] = date('Y-m-d H:i:s', $lastWeekStart);
                $data['end'] = date('Y-m-d H:i:s', $lastWeekEnd - 1);
                break;
            case 5:
                //近一周
                $data['top'] = date('Y-m-d H:i:s', strtotime('-7 day', $time));
                $data['end'] = date('Y-m-d H:i:s', $time);
                break;
            case 6:
                //本月
                $data['top'] = date('Y-m-01 00:00:00', $time);
                $data['end'] = date('Y-m-t 23:59:59', $time);
                break;
            case 7:
                //上月
                $time = strtotime('-1 month', $time);
                $data['top'] = date('Y-m-01 00:00:00', $time);
                $data['end'] = date('Y-m-t 23:59:59', $time);
                break;
            case 8:
                //近一月
                $data['top'] = date('Y-m-d H:i:s', strtotime('-1 month', $time));
                $data['end'] = date('Y-m-d H:i:s', $time);
                break;
            case 9:
                //近三月
                $data['top'] = date('Y-m-d H:i:s', strtotime('-3 month', $time));
                $data['end'] = date('Y-m-d H:i:s', $time);
                break;
            case 10:
                //本季度
                $quarter = ceil((date('n', $time)) / 3);
                $data['top'] = date('Y-m-d H:i:s', mktime(0, 0, 0, $quarter * 3 - 3 + 1, 1, date('Y', $time)));
                $data['end'] = date('Y-m-d H:i:s', $time);
                break;
            case 11:
                //上季度
                $y = date('Y', $time);
                $quarter = (ceil((date('n', $time)) / 3) - 1) * 3;
                $data['top'] = date('Y-m-d H:i:s', mktime(0, 0, 0, $quarter - 3 + 1, 1, $y));
                $data['end'] = date('Y-m-d H:i:s', mktime(23, 59, 59, $quarter, date('t', mktime(0, 0, 0, $quarter, 1, $y)), $y));
                break;
            case 12:
                //第1季度
                $y = date('Y', $time);
                $quarter = 3;
                $data['top'] = date('Y-m-d H:i:s', mktime(0, 0, 0, $quarter - 3 + 1, 1, $y));
                $data['end'] = date('Y-m-d H:i:s', mktime(23, 59, 59, $quarter, date('t', mktime(0, 0, 0, $quarter, 1, $y)), $y));
                break;
            case 13:
                //第2季度
                $y = date('Y', $time);
                $quarter = 2 * 3;
                $data['top'] = date('Y-m-d H:i:s', mktime(0, 0, 0, $quarter - 3 + 1, 1, $y));
                $data['end'] = date('Y-m-d H:i:s', mktime(23, 59, 59, $quarter, date('t', mktime(0, 0, 0, $quarter, 1, $y)), $y));
                break;
            case 14:
                //第3季度
                $y = date('Y', $time);
                $quarter = 3 * 3;
                $data['top'] = date('Y-m-d H:i:s', mktime(0, 0, 0, $quarter - 3 + 1, 1, $y));
                $data['end'] = date('Y-m-d H:i:s', mktime(23, 59, 59, $quarter, date('t', mktime(0, 0, 0, $quarter, 1, $y)), $y));
                break;
            case 15:
                //第4季度
                $y = date('Y', $time);
                $quarter = 4 * 3;
                $data['top'] = date('Y-m-d H:i:s', mktime(0, 0, 0, $quarter - 3 + 1, 1, $y));
                $data['end'] = date('Y-m-d H:i:s', mktime(23, 59, 59, $quarter, date('t', mktime(0, 0, 0, $quarter, 1, $y)), $y));
                break;
            case 16:
                //当年
                $data['top'] = date('Y-01-01 00:00:00', $time);
                $data['end'] = date('Y-m-d H:i:s', $time);
                break;
            case 17:
                //去年
                $data['top'] = date('Y-01-01 00:00:00', strtotime('-1 year', $time));
                $data['end'] = date('Y-12-31 23:59:59', strtotime('-1 year', $time));
                break;
            case 18:
                //近一年
                $data['top'] = date('Y-m-d H:i:s', strtotime('-1 year', $time));
                $data['end'] = date('Y-m-d H:i:s', $time);
                break;
            default:
                //时间
                [$t1, $t2] = explode(" ", (!empty($time) ? $time : microtime()));
                $data['top'] = (float) sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
                $data['end'] = date(((!empty($type) && is_string($type)) ? $type : 'Y-m-d H:i:s'), $time);
                break;
        }
        if (!empty($format)) {
            if (is_array($format)) {
                $data['top'] = date($format[0], strtotime($data['top']));
                $data['end'] = date($format[1], strtotime($data['end']));
            } elseif (is_string($format)) {
                $data['top'] = date($format, strtotime($data['top']));
                $data['end'] = date($format, strtotime($data['end']));
            }
        }
        return $data;
    }

    /**
     * 对比时间
     * @param $start
     * @param $end
     * @return DateInterval
     */
    public static function diffTime($start, $end): DateInterval {
        return date_diff(date_create(trim($start)), date_create(trim($end)));
    }

    /**
     * @param int   $time     开始时间 unix
     * @param int   $ago      多少秒前的数据
     * @param int   $interval 相隔多少秒
     * @param array $arr      返回开始和结束时间
     * @return array
     */
    public static function calculateTime(int $time, int $ago, int $interval, array $arr = []): array {
        $cur_time = time();
        $top_time = $time;
        $end_time = $time + $interval;
        if (($cur_time - $end_time) <= $ago) {
            $end_time = $cur_time - $ago;
        }
        if ($top_time >= $end_time) {
            $top_time = $end_time - $interval;
        }
        if (($end_time - $top_time) < $interval) {
            $top_time = $end_time - $interval;
        }
        $arr['cur_time'] = $cur_time;
        $arr['top_time'] = $top_time;
        $arr['end_time'] = $end_time;
        return $arr;
    }

    /**
     * 生成时间列表
     * @param string $top  //开始时间YmdHis
     * @param string $end  //结束时间YmdHis
     * @param int    $time //相隔时间
     * @param array  $data
     * @return array
     */
    public static function calculateTimeList(string $top, string $end, int $time = 10, array $data = []): array {
        $topInt = strtotime($top);
        $endInt = strtotime($end);
        while (true) {
            $top_time = date('Y-m-d H:i:s', $topInt);
            $topInt = $topInt + $time;
            $end_time = date('Y-m-d H:i:s', (($topInt >= $endInt) ? $endInt : $topInt));
            $data[] = ['top' => $top_time, 'end' => $end_time];
            if ($topInt >= $endInt) {
                break;
            }
        }
        return $data;
    }

    /**
     * 获取本周星期指定天的日期
     * @param int $s    要获取的星期(1-7)
     * @param int $data //当前时间
     * @return int
     */
    public static function getWDate(int $s = 1, int $data = 0): int {
        return ($data > 0 ? $data : time()) - (60 * 60 * 24 * ((date('w', ($data > 0 ? $data : time())) ?: 7) - $s));
    }

    /**
     * 生成月日星期几
     * @param      $time  //时间
     * @param      $layer //语言
     * @param bool $type
     * @return string
     */
    public static function getWeek($time, $layer, bool $type = false): string {
        $time = !empty($time) ? (!empty($type) ? strtotime($time) : $time) : time();
        if ($layer == 'en-us') {
            $weekArr = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
            $week = date("d M", $time) . " (" . $weekArr[date("w", $time)] . ")";
        } else {
            $weekArr = ["日", "一", "二", "三", "四", "五", "六"];
            $week = date("m月d日", $time) . " 星期" . $weekArr[date("w", $time)];
        }
        return $week;
    }

    /**
     * 月帐期数表列表
     * @param int    $time //系统时间
     * @param string $date //月帐单已知年度第一期数
     * @param array  $topData
     * @param int    $j
     * @param string $current
     * @return array
     */
    public static function periodBillTime(int $time = 0, string $date = '', array $topData = [], int $j = 0, string $current = ''): array {
        $time = (!empty($time) ? $time : (strtotime(date('Y-m-d')) - 60 * 60 * 8));
        $start = strtotime((!empty($date) ? $date : '2020-12-28'));
        $end = strtotime("+27 day", $start);
        $data[date("Y-m-d", $start)] = date("Y-m-d", $end);
        $is = 0;
        for ($i = 1; $i <= 25; $i++) {
            $d = $i * 28;
            $s = strtotime("+$d day", $start);
            $e = strtotime("+$d day", $end);
            if ($s <= $time && $e >= $time) {
                $current = date("Y-m-d", $s);
                $is = $i;
            }
            $data[date("Y-m-d", $s)] = date("Y-m-d", $e);
        }
        $j = $j + 1;
        if (empty($is)) {
            return self::periodBillTime($time, date("Y-m-d", strtotime("+1 day", strtotime(end($data)))), $data, $j);
        } else {
            $data = array_slice($data, 0, $is + 4, true);
            if ($j > 1 && count($data) <= 13) {
                $data = array_slice($topData, count($topData) - 13, 13, true) + $data;
            }
        }
        return ['list' => $data, 'start' => $current, 'end' => $data[$current]];
    }

    /**
     * 获取语义化时间
     * @param $time
     * @return string
     */
    public static function humanDate($time): string {
        $timestamp = is_numeric($time) ? $time : strtotime($time);
        $dur = time() - $timestamp;
        if ($dur < 0) {
            return date('Y-m-d', $timestamp);
        } else {
            if ($dur < 60) {
                return $dur . '秒前';
            } else {
                if ($dur < 3600) {
                    return floor($dur / 60) . '分钟前';
                } else {
                    if ($dur < 86400) {
                        return floor($dur / 3600) . '小时前';
                    } else {
                        if ($dur < 2592000) { // 30天内
                            return floor($dur / 86400) . '天前';
                        }
                    }
                }
            }
        }
        return date('Y-m-d', $timestamp);
    }
}