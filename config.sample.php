<?php
/**
 * 变量配置模板
 */

//mysql相关
//数据库地址，在地址前加p:，不加端口号
define('DB_HOST', 'p:example.com');
//数据库端口，如果未修改MySQL配置则为3306
define('DB_PORT', '3306');
//数据库用户名
define('DB_USER', 'put_your_database_username_here');
//数据库密码
define('DB_PASS', 'put_your_database_password_here');
//数据库库名
define('DB_NAME', 'put_your_database_name_here');


//服务器相关，上报IP地址，*为允许全部
define('SERVER_HOST', "*");

//qqbot相关
/**
 * 需要发布已审核内容的群号，可以多账号，形如
 * QQ号1 => [群号1,群号2],
 * QQ号2 => [群号3]
 */
define('BOT_GROUPS', [
    12334 => [
        12331, 12341, 12351
    ],
    12534 => [
        16701, 38331
    ]
]);
//上报URL，如http://localhost:5700
define('BOT_POST_URLS', [
    12334 => 'http://localhost:5700',
    12534 => 'http://localhost:5701'
]);
//审核群的群号
define('BOT_REVIEW',10004);
//审核控制号，可以是上面的其中一个
define('BOT_CONTROLLER', 12334);
//收到消息后的自动回复
define('BOT_REPLY','您的消息已成功转发。如果格式不对请自觉修改格式后重新发给机器人哟。格式：内容+联系方式+校区。感谢您的使用～');
//审核被拒的回复
define('BOT_REPLY_REJECT',
    "您的消息不符合规范哟！\n1.请检查您的消息格式：需求+联系方式+校区\n2.商业信息、变相营销请联系除我之外的管理员缴纳信息费后方可发布哟，一个群5元，目前有4个群哦～");
//广告内容
define('BOT_ADVERTISEMENT',
    "在这里输入广告内容，广告会自动添加到每条消息之后。");
//添加随机后缀，防止被发现，true=启用，false=关闭
define('BOT_RANDOM_POST', true);
//随机后缀串
define("BOT_RANDOM_SYM", '-');
//最长随机串
define('BOT_RANDOM_MAX', 15);
//最短随机串
define('BOT_RANDOM_MIN', 2);
// 屏蔽关键词，遇到关键词时自动加点号分隔，如：代课->代.课
define('BOT_KEYWORD', ["代课"]);

//http-api相关
//用于发送群消息的URL
define('API_GROUP_URL','http://localhost:57002/send_group_msg');
//用于发送私聊消息的URL
define('API_PRIVA_URL','http://localhost:57002/send_private_msg');
//HMAC密钥
define('API_SECRET','put_your_HMAC_secret_here');
//上报消息的格式，目前只支持string，string
define('API_MESSAGE_FORMAT','string');