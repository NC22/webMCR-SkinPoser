CREATE TABLE IF NOT EXISTS `sp_skins` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT 0,
  `name` char(255) DEFAULT NULL,
  `fname` char(255) DEFAULT NULL,
  `dislikes` int(10) DEFAULT 0,
  `likes` int(10) DEFAULT 0,
  `downloads` int(10) DEFAULT 0,
  `ratio` smallint(3) NOT NULL DEFAULT 1,
  `gender` tinyint(1) NOT NULL DEFAULT 2,
  `comments` int(10) NOT NULL DEFAULT 0,
  `comment_last` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `fsize` char(32) DEFAULT 0,
  `hash` char(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `gender` (`gender`),
  KEY `skin_spec` (`gender`, `ratio`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `sp_bad_skins` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `hash` char(32) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `sp_skins_ratio` (
  `ratio` int(10) DEFAULT 0,
  `num` int(10) DEFAULT 1,
  PRIMARY KEY (`ratio`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

UPDATE `groups` SET `sp_upload`='1',`sp_change`='1' WHERE `id`='3';
UPDATE `groups` SET `sp_upload`='0',`sp_change`='1' WHERE `id`='1';