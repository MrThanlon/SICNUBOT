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

//qqbot相关
//QQ账号，仅用于验证，修改之后需要重新在酷Q上登录
define('BOT_QQNUM','10001');
//需要发布已审核内容的群号，可以填写多个
define('BOT_SEND',array(
    '10002',
    '10003'
));
//审核群的群号
define('BOT_REVIEW','10004');
//收到消息后的自动回复
define('BOT_REPLY','您的消息已成功转发。如果格式不对请自觉修改格式后重新发给机器人哟。格式：内容+联系方式+校区。感谢您的使用～');
//审核被拒的回复
define('BOT_REPLY_REJECT',
    "您的消息不符合规范哟！\n1.请检查您的消息格式：需求+联系方式+校区\n2.商业信息、变相营销请联系除我之外的管理员缴纳信息费后方可发布哟，一个群5元，目前有4个群哦～");
//广告内容
define('BOT_ADVERTISEMENT',
    "在这里输入广告内容，广告会自动添加到每条消息之后。");

//http-api相关
//用于发送群消息的URL
define('API_GROUP_URL','http://localhost:57002/send_group_msg');
//用于发送私聊消息的URL
define('API_PRIVA_URL','http://localhost:57002/send_private_msg');
//HMAC密钥
define('API_SECRET','put_your_HMAC_secret_here');
//上报消息的格式，目前只支持string，string
define('API_MESSAGE_FORMAT','string');