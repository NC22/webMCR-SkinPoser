<?php if (!defined('MCR')) exit;

getDB()->ask("CREATE TABLE IF NOT EXISTS `{$bd_names['sp_skins']}` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT 0,
  `name` char(255) DEFAULT NULL,
  `fname` char(255) DEFAULT NULL,
  `dislikes` int(10) DEFAULT 0,
  `likes` int(10) DEFAULT 0,
  `downloads` int(10) DEFAULT 0,
  `ratio` smallint(3) NOT NULL DEFAULT 1,
  `gender` tinyint(1) NOT NULL DEFAULT 2,
  `fsize` char(32) DEFAULT 0,
  `comments` int(10) NOT NULL DEFAULT 0,
  `comment_last` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `hash` char(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `gender` (`gender`),
  KEY `skin_spec` (`gender`, `ratio`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");

getDB()->ask("CREATE TABLE IF NOT EXISTS `{$bd_names['sp_bad_skins']}` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `hash` char(32) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");

getDB()->ask("CREATE TABLE IF NOT EXISTS `{$bd_names['sp_skins_ratio']}` (
  `ratio` int(10) DEFAULT 0,
  `num` int(10) DEFAULT 1,
  PRIMARY KEY (`ratio`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");

if (getDB()->isColumnExist($bd_names['groups'], 'skinposer')) 

	getDB()->ask("ALTER TABLE `{$bd_names['groups']}` DROP `skinposer`;"); // depricated field
 
if (!getDB()->isColumnExist($bd_names['groups'], 'sp_upload')) 

	getDB()->ask("ALTER TABLE `{$bd_names['groups']}`	ADD `sp_upload` tinyint(1) NOT NULL DEFAULT 0;");

if (!getDB()->isColumnExist($bd_names['groups'], 'sp_change'))

	getDB()->ask("ALTER TABLE `{$bd_names['groups']}`	ADD `sp_change` tinyint(1) NOT NULL DEFAULT 0;");
        
if (!getDB()->isColumnExist($bd_names['groups'], 'sp_download'))

       getDB()->ask("ALTER TABLE `{$bd_names['groups']}`	ADD `sp_download` tinyint(1) NOT NULL DEFAULT 0;");
       
if (!getDB()->isColumnExist($bd_names['sp_skins'], 'comments')) 

	getDB()->ask("ALTER TABLE `{$bd_names['sp_skins']}` ADD `comments` int(10) NOT NULL DEFAULT 0;");
	
if (!getDB()->isColumnExist($bd_names['sp_skins'], 'comment_last')) 

	getDB()->ask("ALTER TABLE `{$bd_names['sp_skins']}` ADD `comment_last` datetime NOT NULL DEFAULT '0000-00-00 00:00:00';");
	
getDB()->ask("UPDATE `{$bd_names['groups']}` SET `sp_upload`='1',`sp_change`='1',`sp_download`='1' WHERE `id`='3'");
getDB()->ask("UPDATE `{$bd_names['groups']}` SET `sp_upload`='0',`sp_change`='1',`sp_download`='0' WHERE `id`='1'");