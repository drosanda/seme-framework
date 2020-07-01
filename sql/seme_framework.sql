SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `a_apikey`;
CREATE TABLE `a_apikey` (
  `nation_code` int(3) NOT NULL DEFAULT 62,
  `id` int(4) NOT NULL,
  `code` varchar(8) CHARACTER SET latin1 NOT NULL COMMENT 'alias apikey',
  `name` varchar(24) COLLATE utf8_unicode_ci NOT NULL COMMENT 'apikey for',
  `cdate` datetime DEFAULT NULL COMMENT 'create date',
  `ldate` timestamp NULL DEFAULT NULL COMMENT 'lastupdate',
  `is_active` int(1) NOT NULL DEFAULT 1 COMMENT '1 active, 0 inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='API Key storage';

INSERT INTO `a_apikey` (`nation_code`, `id`, `code`, `name`, `cdate`, `ldate`, `is_active`) VALUES
(62, 1, 'ABCD1234', 'General APIKEY', '2020-06-09 09:47:18', '2020-06-09 02:47:18', 1);


ALTER TABLE `a_apikey`
  ADD PRIMARY KEY (`id`,`nation_code`);
SET FOREIGN_KEY_CHECKS=1;
