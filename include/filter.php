<?php
/**
 * 消息过滤，去除SQL语句等
 */

/**
 * 消息过滤，用real_escape_string，没必要造轮子了
 * @param $msg
 * @return mixed
 */
function msg_filter($msg){
    return str_replace('\'','\\\'',$msg);
}