<?php
/**
 * 全字符串插值
 * @param string $str
 * @return string
 */
function msg_all_split(string $str)
{
    // 正则匹配，[\x{0000}-\x{4dff}\x{9fa6}-\x{ffff}]匹配非中文，[\x{4e00}-\x{9fa5}]匹配中文
    $pattern = "/[\x{0000}-\x{4dff}\x{9fa6}-\x{ffff}]+|[\x{4e00}-\x{9fa5}][\x{0000}-\x{4dff}\x{9fa6}-\x{ffff}]+[\x{4e00}-\x{9fa5}]|[\x{4e00}-\x{9fa5}]/u";
    preg_match_all($pattern, $str, $matches);
    return implode('.', $matches[0]);
}

/**
 * 关键词插值
 * @param string $str
 * @return string
 */
function msg_keyword_split(string $str)
{
    return array_reduce(BOT_KEYWORD, function ($pre, $cur) {
        if (strpos($pre, $cur) !== false) {
            // 分割
            $cur_split = implode('.', preg_split('/(?<!^)(?!$)/u', $cur));
            $pre = str_replace($cur, $cur_split, $pre);
        }
        return $pre;
    }, $str);
}