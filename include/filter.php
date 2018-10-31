<?php
/**
 * 消息过滤，去除SQL语句等
 */

function msg_filter($msg){
    return str_replace('\'','\\\'',$msg);
}