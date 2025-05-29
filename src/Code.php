<?php

namespace AlonePhp\Code;

class Code {
    /**
     * 验证码
     * @param int    $width
     * @param int    $height
     * @param int    $codeLen
     * @param string $str
     * @param string $code
     * @return array
     */
    public static function staleCode(int $width = 60, int $height = 20, int $codeLen = 4, string $str = '', string $code = ''): array {
        $str = !empty ($str) ? $str : 'abcdefghkmnprstuvwxyzABCDEFGHKMNPRSTUVWXYZ23456789';
        for ($i = 0; $i < $codeLen; $i++) {
            $code .= $str [mt_rand(0, strlen($str) - 1)];
        }
        $img = imagecreate($width, $height);
        $bg = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);
        $gray = imagecolorallocate($img, 204, 213, 204);
        imagerectangle($img, 0, 0, $width - 1, $height - 1, $gray);
        $rand = imagecolorallocate($img, 204, 213, 204);
        for ($i = 0; $i < 80; $i++)
            imagesetpixel($img, rand(0, $width), rand(0, $height), $rand);
        $black = imagecolorallocate($img, 0, 0, 204);
        $widths = ($width - 40) / 2;
        for ($i = 0; $i < $codeLen; $i++) {
            imagestring($img, 5, $widths, ($height - 16) / 2, substr($code, $i, 1), $black);
            $widths += 10;
        }
        return [
            'code' => $code,
            'img'  => 'data:image/png;base64,' . base64_encode(Frame::obCache(function() use ($img) {
                    imagepng($img);
                    imagedestroy($img);
                }))
        ];
    }

    /**
     * 验证码
     * @param int    $width
     * @param int    $height
     * @param int    $codeLen
     * @param string $str
     * @param int    $fontSize
     * @param string $code
     * @return array
     */
    public static function oldCode(int $width = 130, int $height = 50, int $codeLen = 4, string $str = '', int $fontSize = 20, string $code = ''): array {
        error_reporting(0);
        $str = !empty ($str) ? $str : 'abcdefghkmnprstuvwxyzABCDEFGHKMNPRSTUVWXYZ23456789';
        $img = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($img, mt_rand(157, 255), mt_rand(157, 255), mt_rand(157, 255));
        imagefilledrectangle($img, 0, $height, $width, 0, $color);
        $len = strlen($str) - 1;
        for ($i = 0; $i < $codeLen; $i++)
            $code .= $str [mt_rand(0, $len)];
        for ($i = 0; $i < 6; $i++) {
            $color = imagecolorallocate($img, mt_rand(0, 156), mt_rand(0, 156), mt_rand(0, 156));
            imageline($img, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height), $color);
        }
        for ($i = 0; $i < 100; $i++) {
            $color = imagecolorallocate($img, mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255));
            imagestring($img, mt_rand(1, 5), mt_rand(0, $width), mt_rand(0, $height), '*', $color);
        }
        $font = __DIR__ . '/../file/oldCode.ttf';
        $x = $width / $codeLen;
        for ($i = 0; $i < $codeLen; $i++) {
            $fontcolor = imagecolorallocate($img, mt_rand(0, 156), mt_rand(0, 156), mt_rand(0, 156));
            imagettftext($img, $fontSize, mt_rand(-30, 30), $x * $i + mt_rand(1, 5), $height / 1.4, $fontcolor, $font, $code [$i]);
        }
        return [
            'code' => $code,
            'img'  => 'data:image/png;base64,' . base64_encode(Frame::obCache(function() use ($img) {
                    imagepng($img);
                    imagedestroy($img);
                }))
        ];
    }
}