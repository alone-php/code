<?php

use AlonePhp\Code\Frame;

//alone根目录
function alone_path(string $path = ''): string {
    return Frame::dirPath(realpath(__DIR__ . '/../'), $path);
}

//运行根目录
function alone_root_path(string $path = ''): string {
    return Frame::dirPath(realpath(__DIR__ . '/../../'), $path);
}


function alone_ps(mixed $data, bool $echo = true): string {
    $content = '<pre>' . print_r($data, true) . '</pre>';
    if (!empty($echo)) {
        echo $content;
        return '';
    }
    return $content;
}


function alone_ts(string $data, string|int $row = 30, string|int $cols = 200, bool $echo = true): string {
    $content = '<textarea rows="' . $row . '" cols="' . $cols . '">' . $data . '</textarea>';
    if (!empty($echo)) {
        echo $content;
        return '';
    }
    return $content;
}

/**
 * 渲染视图模板
 * @param string      $template 模板文件名（不包含扩展名）
 * @param array       $vars     传递给模板的变量
 * @param string|null $path     模板文件路径
 * @param string      $suffix   模板文件后缀名
 * @return string
 */
function alone_view(string $template, array $vars = [], string|null $path = null, string $suffix = 'html'): string {
    $templatePath = rtrim($path, '/\\') . '/' . trim($template, '/\\') . '.' . $suffix;
    if (is_file($templatePath)) {
        extract($vars);
        ob_start();
        try {
            include $templatePath;
        } catch (Throwable $e) {
            ob_end_clean();
            return "error: " . $e->getMessage();
        }
        return ob_get_clean();
    }
    return "not file: {$templatePath}";
}