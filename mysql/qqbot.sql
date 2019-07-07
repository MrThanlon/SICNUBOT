-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- 主机： 127.0.0.1
-- 生成日期： 2019-07-07 15:53:36
-- 服务器版本： 10.3.13-MariaDB
-- PHP 版本： 7.1.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `qqbot`
--

-- --------------------------------------------------------

--
-- 表的结构 `err_log`
--

CREATE TABLE `err_log` (
  `code` bigint(20) UNSIGNED NOT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status_code` int(11) NOT NULL,
  `msg` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `messages`
--

CREATE TABLE `messages` (
  `code` bigint(20) UNSIGNED NOT NULL,
  `sender` text NOT NULL,
  `receiver` text NOT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` int(11) NOT NULL,
  `message` text NOT NULL,
  `reviewer` text DEFAULT NULL,
  `reviewer_msg` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `post_messages`
--

CREATE TABLE `post_messages` (
  `code` bigint(20) UNSIGNED NOT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `message` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `sending_log`
--

CREATE TABLE `sending_log` (
  `code` bigint(20) UNSIGNED NOT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `msg` text NOT NULL,
  `sender` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转储表的索引
--

--
-- 表的索引 `err_log`
--
ALTER TABLE `err_log`
  ADD PRIMARY KEY (`code`),
  ADD UNIQUE KEY `code` (`code`);

--
-- 表的索引 `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`code`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `code_2` (`code`);

--
-- 表的索引 `post_messages`
--
ALTER TABLE `post_messages`
  ADD PRIMARY KEY (`code`),
  ADD UNIQUE KEY `code` (`code`);

--
-- 表的索引 `sending_log`
--
ALTER TABLE `sending_log`
  ADD PRIMARY KEY (`code`),
  ADD UNIQUE KEY `code` (`code`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `err_log`
--
ALTER TABLE `err_log`
  MODIFY `code` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `messages`
--
ALTER TABLE `messages`
  MODIFY `code` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `post_messages`
--
ALTER TABLE `post_messages`
  MODIFY `code` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `sending_log`
--
ALTER TABLE `sending_log`
  MODIFY `code` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
