<?php
/**
 * 接收消息上报
 */

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/include/logwrite.php";
require_once __DIR__ . "/include/utils.php";
header('Content-type: application/json');

//使用Guzzle
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

$client = new Client();

try {
    if (SERVER_HOST !== '*' && SERVER_HOST !== $_SERVER["REMOTE_ADDR"])
        throw new Exception("Host not allowed: {$_SERVER["REMOTE_ADDR"]}", 103);

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
    if (!key_exists($body_json['self_id'], BOT_GROUPS))
        throw new Exception("Wrong QQ number:{$body_json['self_id']}", 102);

    //只接收消息，不理会其他post_type
    if ($body_json['post_type'] !== 'message')
        throw new Exception("Wrong post_type:{$body_json['post_type']}", 105);

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($db->errno)
        throw new Exception("Database error:{$db->errno}", 106);

    //私聊消息，转发到审核群
    if ($body_json['message_type'] === 'private') {
        // 关键词截断
        $body_json['message'] = msg_keyword_split($body_json['message']);
        //SQL过滤
        $filter_str = $db->real_escape_string($body_json['message']);
        //入库
        $db->query("INSERT INTO `messages` (`sender`,`receiver`,`status`,`message`) " .
            "VALUES ('{$body_json['sender']['user_id']}','{$body_json['self_id']}',0,'{$filter_str}')");
        //填充编号+QQ号
        $body_json['message'] =
            "&#91;{$db->insert_id}&#93;&#91;{$body_json['sender']['user_id']}&#93;{$body_json['message']}";
        //发送到审核群
        $res = $client->request('POST', BOT_POST_URLS[$body_json['self_id']] . API_GROUP_URL, [
            'json' => [
                'group_id' => BOT_REVIEW,
                'message' => $body_json['message']
            ]
        ]);
        //异常处理
        if ($res->getStatusCode() !== 200)
            throw new Exception('API error', 107);
        $res_json = json_decode($res->getBody(), true);
        // FIXME: retcode=1是异步API
        if ($res_json['retcode'] !== 1)
            throw new Exception('System Error', 107);
        //自动回复
        if (BOT_REPLY)
            echo json_encode(['reply' => BOT_REPLY]);

    } else if ($body_json['message_type'] === 'group' &&
        $body_json['group_id'] === BOT_REVIEW &&
        $body_json['self_id'] === BOT_CONTROLLER) {
        //来自审核群，处理请求
        if (preg_match('/^([1-9]\d*)([!#=%]?)([\s\S]*)$/', $body_json['message'], $matched_arr)) {
            if ($matched_arr[2] !== '=' && $matched_arr[2] !== '#' && $matched_arr[3])
                //认为是命令错误
                throw new Exception('Wrong review command', 201);

            $query_res = $db->query("SELECT `sender`,`receiver`,`status`,`message` FROM `messages` " .
                "WHERE `code`='{$matched_arr[1]}' LIMIT 1");
            if ($query_res->num_rows === 0)
                //没有数据
                throw new Exception('No this code', 202);

            $res_arr = $query_res->fetch_row();
            $sender = $res_arr[0];
            $receiver = $res_arr[1];
            $status = $res_arr[2];
            $raw_message = $res_arr[3];
            //if ($res_arr[1] != 0)
            //消息已被审核过，按照甲方要求不处理，继续发送一遍
            //throw new Exception('Message has been reviewed', 203);

            if ($matched_arr[2] === '') {
                //审核通过
                //入库
                $db->query("UPDATE `messages` SET " .
                    "`status` = '1'," .
                    "`reviewer` = {$body_json['sender']['user_id']} " .
                    "WHERE `code` = '{$matched_arr[1]}'");
                //日志入库，用于产生serial编号
                $db->query("INSERT INTO `sending_log` (`msg`, `sender`) " .
                    "VALUE ('{$raw_message}','{$sender}')");
                //消息填充
                $message_send = "&#91;{$db->insert_id}&#93;{$raw_message}";
                //随机数种子
                srand(time() + (int)BOT_REVIEW);
                //消息发送
                foreach (BOT_GROUPS as $qq_number => $groups) {
                    foreach ($groups as $group_number) {
                        $message = $message_send;
                        if (BOT_RANDOM_POST)
                            $message .= "\n" . str_repeat(BOT_RANDOM_SYM, rand(BOT_RANDOM_MIN, BOT_RANDOM_MAX));
                        if (BOT_ADVERTISEMENT)
                            $message .= "\n" . BOT_ADVERTISEMENT;
                        $res = $client->request('POST', BOT_POST_URLS[$qq_number] . API_GROUP_URL, [
                            'json' => [
                                'group_id' => $group_number,
                                'message' => $message
                            ]
                        ]);
                        //异常处理
                        if ($res->getStatusCode() !== 200)
                            throw new Exception('API error', 107);
                        $res_json = json_decode($res->getBody(), true);
                        if ($res_json['retcode'] !== 1)
                            throw new Exception('System Error', 107);

                    }
                }
            } else if ($matched_arr[2] === '!') {
                //审核不通过

                //入库
                $db->query("UPDATE `messages` SET " .
                    "`status` = '2'," .
                    "`reviewer` = '{$body_json['sender']['user_id']}' " .
                    "WHERE `code` = '{$matched_arr[1]}'");
                //这里有个坑，不能自动回复，不然会回复到群里而不是私聊
                //发信
                $res = $client->request('POST', BOT_POST_URLS[$receiver] . API_PRIVA_URL, [
                    'json' => [
                        'user_id' => $sender,
                        'message' => BOT_REPLY_REJECT
                    ]
                ]);
                //异常处理
                if ($res->getStatusCode() !== 200)
                    throw new Exception('API error', 107);
                $res_json = json_decode($res->getBody(), true);
                if ($res_json['retcode'] !== 1)
                    throw new Exception('System Error', 107);

            } else if ($matched_arr[2] === '#') {
                //审核带信，不通过

                //SQL过滤
                $reviewer_msg_filter = $db->real_escape_string($matched_arr[3]);
                //入库
                $db->query("UPDATE `messages` SET " .
                    "`status` = '3'," .
                    "`reviewer` = '{$body_json['sender']['user_id']}'," .
                    "`reviewer_msg` = '{$reviewer_msg_filter}' " .
                    "WHERE `code` = '{$matched_arr[1]}'");
                //发信
                $res = $client->request('POST', BOT_POST_URLS[$receiver] . API_PRIVA_URL, [
                    'json' => [
                        'user_id' => $sender,
                        'message' =>
                            "&#91;{$matched_arr[1]}&#93;&#91;管理员QQ{$body_json['sender']['user_id']}&#93;" .
                            "{$reviewer_msg_filter}" //带信填充

                    ]
                ]);
                //异常处理
                if ($res->getStatusCode() !== 200)
                    throw new Exception('API error', 107);
                $res_json = json_decode($res->getBody(), true);
                if ($res_json['retcode'] !== 1)
                    throw new Exception('System Error', 107);

            } else if ($matched_arr[2] === '=') {
                //修改发布内容发布

                //SQL过滤
                $reviewer_msg_filter = $db->real_escape_string($matched_arr[3]);
                //入库
                $db->query("UPDATE `messages` SET " .
                    "`status`= '4'," .
                    "`reviewer` = '{$body_json['sender']['user_id']}'," .
                    "`reviewer_msg` = '{$reviewer_msg_filter}' " .
                    "WHERE `code`='{$matched_arr[1]}'");
                //日志入库，用于产生serial编号
                $db->query("INSERT INTO `sending_log` (`msg`, `sender`) " .
                    "VALUE ('{$reviewer_msg_filter}','{$sender}')");
                //消息填充
                $message_send = "&#91;{$db->insert_id}&#93;{$matched_arr[3]}";
                //随机数种子
                srand(time() + (int)BOT_REVIEW);
                //发信，基本抄上面
                foreach (BOT_GROUPS as $qq_number => $groups) {
                    foreach ($groups as $group_number) {
                        $message = $message_send;
                        if (BOT_RANDOM_POST)
                            $message .= "\n" . str_repeat(BOT_RANDOM_SYM, rand(BOT_RANDOM_MIN, BOT_RANDOM_MAX));
                        if (BOT_ADVERTISEMENT)
                            $message .= "\n" . BOT_ADVERTISEMENT;
                        $res = $client->request('POST', BOT_POST_URLS[$qq_number] . API_GROUP_URL, [
                            'json' => [
                                'group_id' => $group_number,
                                'message' => $message
                            ]
                        ]);
                        //异常处理
                        if ($res->getStatusCode() !== 200)
                            throw new Exception('API error', 107);
                        $res_json = json_decode($res->getBody(), true);
                        if ($res_json['retcode'] !== 1)
                            throw new Exception('System Error', 107);

                    }
                }
            } else if ($matched_arr[2] === '%') {
                // 中文截断，基本抄上面
                $raw_message = msg_all_split($raw_message);
                //入库
                $db->query("UPDATE `messages` SET " .
                    "`status` = '1'," .
                    "`reviewer` = {$body_json['sender']['user_id']} " .
                    "WHERE `code` = '{$matched_arr[1]}'");
                //日志入库，用于产生serial编号
                $db->query("INSERT INTO `sending_log` (`msg`, `sender`) " .
                    "VALUE ('{$raw_message}','{$sender}')");
                //消息填充
                $message_send = "&#91;{$db->insert_id}&#93;{$raw_message}";
                //随机数种子
                srand(time() + (int)BOT_REVIEW);
                //消息发送
                foreach (BOT_GROUPS as $qq_number => $groups) {
                    foreach ($groups as $group_number) {
                        $message = $message_send;
                        if (BOT_RANDOM_POST)
                            $message .= "\n" . str_repeat(BOT_RANDOM_SYM, rand(BOT_RANDOM_MIN, BOT_RANDOM_MAX));
                        if (BOT_ADVERTISEMENT)
                            $message .= "\n" . BOT_ADVERTISEMENT;
                        $res = $client->request('POST', BOT_POST_URLS[$qq_number] . API_GROUP_URL, [
                            'json' => [
                                'group_id' => $group_number,
                                'message' => $message
                            ]
                        ]);
                        //异常处理
                        if ($res->getStatusCode() !== 200)
                            throw new Exception('API error', 107);
                        $res_json = json_decode($res->getBody(), true);
                        if ($res_json['retcode'] !== 1)
                            throw new Exception('System Error', 107);

                    }
                }
            } else
                throw new Exception('Wrong reviewer symbol', 201);
        } else
            //忽略闲聊
            throw new Exception('Wrong reviewer command', 201);

    } else
        throw new Exception("Unknow message type:{$body_json['message_type']}", 105);

} catch (RequestException $e) {
    errlogwrite($e->getCode(), $e->getMessage());
} catch (GuzzleException $e) {
    errlogwrite($e->getCode(), $e->getMessage());
} catch (Exception $e) {
    //错误记录
    errlogwrite($e->getCode(), $e->getMessage());
}
