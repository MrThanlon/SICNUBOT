<?php
/**
 * 接收消息上报
 */

require "vendor/autoload.php";
require "config.php";
require "include/url.php";
require "include/filter.php";
require "include/logwrite.php";
header('Content-type: application/json');

//使用Guzzle
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

$client = new Client();

try {

    if ($_SERVER["REMOTE_ADDR"] != "127.0.0.1" and
        $_SERVER["REMOTE_ADDR"] != "::1")//不接收本地以外的来源，为了安全性
        throw new Exception("Not localhost:{$_SERVER["REMOTE_ADDR"]}", 103);

    if ($_SERVER["REQUEST_METHOD"] != "POST")
        throw new Exception("Bad request method:{$_SERVER["REQUEST_METHOD"]}", 104);

    $header = getallheaders();
    $body = file_get_contents('php://input', true);
    $hash = substr($header['X-Signature'], 5);

    if (hash_hmac('sha1', $body, API_SECRET) != $hash)
        throw new Exception("HMAC unmatched:{$hash}", 101);

    //写日志
    msglogwrite($body);

    $body_json = json_decode($body, true);

    //核对QQ号
    if ($body_json['self_id'] != BOT_QQNUM)
        throw new Exception("Wrong QQ number:{$body_json['self_id']}", 102);

    //只接收消息，不理会其他post_type
    if ($body_json['post_type'] != 'message')
        throw new Exception("Wrong post_type:{$body_json['post_type']}", 105);

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($db->errno)
        throw new Exception("Database error:{$db->errno}", 106);

    //私聊消息，转发到审核群
    if ($body_json['message_type'] == 'private') {
        //SQL过滤
        $filter_str = msg_filter($body_json['message']);
        //入库
        $db->query("INSERT INTO `messages` (`sender`,`status`,`message`) " .
            "VALUES ('{$body_json['sender']['user_id']}',0,'{$filter_str}')");
        //填充编号+QQ号
        $body_json['message'] =
            "&#91;{$db->insert_id}&#93;&#91;{$body_json['sender']['user_id']}&#93;{$body_json['message']}";
        //发送到审核群
        $res = $client->request('POST', API_GROUP_URL, [
            'json' => [
                'group_id' => BOT_REVIEW,
                'message' => $body_json['message']
            ]
        ]);
        //异常处理
        if ($res->getStatusCode() != 200)
            throw new Exception('API error', 107);
        $res_json = json_decode($res->getBody(), true);
        if ($res_json['retcode'] != 0)
            throw new Exception('System Error', 107);
        sleep(1);//响应慢一点
        //自动回复
        if (BOT_REPLY)
            echo json_encode(['reply' => BOT_REPLY]);

    } else if ($body_json['message_type'] == 'group' &&
        $body_json['group_id'] == BOT_REVIEW) {
        //来自审核群，处理请求
        if (preg_match('/^([1-9]\d*)([!|#|=]?)([\s\S]*)$/', $body_json['message'], $matched_arr)) {
            $query_res = $db->query("SELECT `sender`,`status`,`message` FROM `messages` " .
                "WHERE `code`='{$matched_arr[1]}' LIMIT 1");

            if ($query_res->num_rows == 0)
                //没有数据
                throw new Exception('No this code', 202);

            $res_arr = $query_res->fetch_row();
            //if ($res_arr[1] != 0)
            //消息已被审核过，按照甲方要求不处理，继续发送一遍
            //throw new Exception('Message has been reviewed', 203);

            if ($matched_arr[2] == '') {
                if ($matched_arr[3])
                    //没有符号，但是有其他消息，认为是命令错误
                    throw new Exception('Wrong review command', 201);
                //审核通过

                //入库
                $db->query("UPDATE `messages` SET " .
                    "`status` = '1'," .
                    "`reviewer` = {$body_json['sender']['user_id']} " .
                    "WHERE `code` = '{$matched_arr[1]}'");
                //日志入库，用于产生serial编号
                $db->query("INSERT INTO `sending_log` (`msg`, `sender`) " .
                    "VALUE ('$res_arr[2]','$res_arr[0]')");
                //消息填充
                //$message_send = "&#91;{$db->insert_id}&#93;&#91;{$res_arr[0]}&#93;{$res_arr[2]}";
                $message_send = "&#91;{$db->insert_id}&#93;{$res_arr[2]}";
                //消息发送
                foreach (BOT_SEND as $value) {
                    $res = $client->request('POST', API_GROUP_URL, [
                        'json' => [
                            'group_id' => $value,
                            'message' => $message_send
                        ]
                    ]);
                    sleep(1);//发送慢一点，防封杀
                    //异常处理
                    if ($res->getStatusCode() != 200)
                        throw new Exception('API error', 107);
                    $res_json = json_decode($res->getBody(), true);
                    if ($res_json['retcode'] != 0)
                        throw new Exception('System Error', 107);

                }
            } else if ($matched_arr[2] == '!') {
                //审核不通过

                //入库
                $db->query("UPDATE `messages` SET " .
                    "`status` = '2'," .
                    "`reviewer` = '{$body_json['sender']['user_id']}' " .
                    "WHERE `code` = '{$matched_arr[1]}'");
                //这里有个坑，不能自动回复，不然会回复到群里而不是私聊
                //发信
                $res = $client->request('POST', API_PRIVA_URL, [
                    'json' => [
                        'user_id' => $res_arr[0],
                        'message' => BOT_REPLY_REJECT
                    ]
                ]);
                //异常处理
                if ($res->getStatusCode() != 200)
                    throw new Exception('API error', 107);
                $res_json = json_decode($res->getBody(), true);
                if ($res_json['retcode'] != 0)
                    throw new Exception('System Error', 107);

            } else if ($matched_arr[2] == '#') {
                //审核带信

                //SQL过滤
                $reviewer_msg_filter = msg_filter($matched_arr[3]);
                //入库
                $db->query("UPDATE `messages` SET " .
                    "`status` = '3'," .
                    "`reviewer` = '{$body_json['sender']['user_id']}'," .
                    "`reviewer_msg` = '{$reviewer_msg_filter}' " .
                    "WHERE `code` = '{$matched_arr[1]}'");
                //发信
                $res = $client->request('POST', API_PRIVA_URL, [
                    'json' => [
                        'user_id' => $res_arr[0],
                        'message' => //带信填充
                            "&#91;{$matched_arr[1]}&#93;&#91;{$body_json['sender']['user_id']}&#93;" .
                            "{$reviewer_msg_filter}"
                    ]
                ]);
                //异常处理
                if ($res->getStatusCode() != 200)
                    throw new Exception('API error', 107);
                $res_json = json_decode($res->getBody(), true);
                if ($res_json['retcode'] != 0)
                    throw new Exception('System Error', 107);

            } else if ($matched_arr[2] == '=') {
                //修改发布内容发布

                //SQL过滤
                $reviewer_msg_filter = msg_filter($matched_arr[3]);
                //入库
                $db->query("UPDATE `messages` SET " .
                    "`status`= '4'," .
                    "`reviewer` = '{$body_json['sender']['user_id']}'," .
                    "`reviewer_msg` = '{$reviewer_msg_filter}' " .
                    "WHERE `code`='{$matched_arr[1]}'");
                //日志入库，用于产生serial编号
                $db->query("INSERT INTO `sending_log` (`msg`, `sender`) " .
                    "VALUE ('$reviewer_msg_filter','$res_arr[0]')");
                //发信
                $res = $client->request('POST', API_PRIVA_URL, [
                    'json' => [
                        'user_id' => $res_arr[0],
                        'message' => //带信填充
                            "&#91;{$db->insert_id}&#93;&#91;{$res_arr[0]}&#93;" .
                            "&#91;管理员{$body_json['sender']['user_id']}修订&#93;" .
                            "{$reviewer_msg_filter}"
                    ]
                ]);
                //异常处理
                if ($res->getStatusCode() != 200)
                    throw new Exception('API error', 107);
                $res_json = json_decode($res->getBody(), true);
                if ($res_json['retcode'] != 0)
                    throw new Exception('System Error', 107);


            } else
                throw new Exception('Wrong reviewer symbol', 201);
        } else
            //忽略闲聊
            throw new Exception('Wrong reviewer command', 201);

    } else
        throw new Exception("Unknow message type:{$body_json['message_type']}", 105);

} catch (RequestException $e) {
    //file_put_contents('logs/error.txt', 'RE:' . $e->getMessage() . "\n", FILE_APPEND);
    errlogwrite($e->getCode(), $e->getMessage());
} catch (GuzzleException $e) {
    //file_put_contents('logs/error.txt', 'GE:' . $e->getMessage() . "\n", FILE_APPEND);
    errlogwrite($e->getCode(), $e->getMessage());
} catch (Exception $e) {
    //错误记录
    //file_put_contents('logs/error.txt', $e->getMessage() . "\n", FILE_APPEND);
    errlogwrite($e->getCode(), $e->getMessage());
}
