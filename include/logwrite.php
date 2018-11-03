<?php
/**
 * 日志写入
 */

//if(!require_once '../config.php')
//    require '../config.php';

/**
 * 用于在数据库中写入日志
 * @param $code
 * @param $msg
 */
function errlogwrite($code, $msg)
{
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    //如果这都失败了的话，我无fuck可说
    $db->query("INSERT INTO `err_log` (`status_code`,`msg`) " .
        "VALUES({$code},'{$msg}')");
}

function msglogwrite($msg){
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    //如果这都失败了的话，我无fuck可说
    $msg_sql = msg_filter($msg);
    $db->query("INSERT INTO `post_messages` (`message`) " .
        "VALUES('{$msg_sql}')");
}


