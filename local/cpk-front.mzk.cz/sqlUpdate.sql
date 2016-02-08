/* Set longer username in user table */
ALTER TABLE user CHANGE username username VARCHAR(64);

/* Create indexed eppn for purposes of connected federative identities */
ALTER TABLE user_card ADD COLUMN eppn VARCHAR(64);
CREATE UNIQUE INDEX user_card_eppn_uq ON user_card(eppn);

/* Create row for storing user's read notifications */
ALTER TABLE user ADD read_notifications VARCHAR(512) NULL DEFAULT NULL;

/* Create and fill table representing citation styles */
CREATE TABLE IF NOT EXISTS `citation_style` (
  `id` int(11) NOT NULL,
  `description` VARCHAR(32),
  `value` VARCHAR(8) UNIQUE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

ALTER TABLE `citation_style`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `citation_style`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

INSERT INTO `vufind`.`citation_style` (`id`, `description`, `value`) 
  VALUES (NULL, 'ČSN ISO 690', '38673'),
         (NULL, 'Harvard', '3'), 
         (NULL, 'NISO/ANSI Z39.29 (2005)', '4'),
         (NULL, 'MLA (7th edition)', '5'), 
         (NULL, 'Turabian (7th edition)', '6'), 
         (NULL, 'Chicago (16th edition)', '7'), 
         (NULL, 'IEEE', '8'), 
         (NULL, 'CSE', '9'), 
         (NULL, 'CSE NY', '10'), 
         (NULL, 'APA', '11'), 
         (NULL, 'ISO 690', '12');


/* Create table for users settings */
CREATE TABLE IF NOT EXISTS `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `citation_style` int(8) NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`), ADD KEY `user_id` (`user_id`);

ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

ALTER TABLE `user_settings`
  ADD FOREIGN KEY (user_id) REFERENCES user(id);
  
ALTER TABLE `user_settings`
  ADD FOREIGN KEY (citation_style) REFERENCES citation_style(id);

/* Create column record_per_page in user_settings table */
ALTER TABLE `user_settings` ADD `records_per_page` TINYINT NULL ;

/* Create column record_per_page in user_settings table */
ALTER TABLE `user_settings` ADD `sorting` VARCHAR(40) NULL ;

/* Create table system for checking DB version */
CREATE TABLE IF NOT EXISTS `system` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(32) NOT NULL,
  `value` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_2` (`key`),
  UNIQUE KEY `id` (`id`),
  KEY `key` (`key`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

/* Increment this column on every sqlUpdate.sql update  */
INSERT INTO `system` (`id`, `key`, `value`) VALUES
(1, 'DB_VERSION', '1');

/* Create table for storing content of Portal pages */
DROP TABLE IF EXISTS `portal_pages`;
CREATE TABLE IF NOT EXISTS `portal_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `pretty_url` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `content` text COLLATE utf8_unicode_ci NOT NULL,
  `language_code` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  `published` tinyint(1) NOT NULL,
  `placement` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `position` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `order_priority` tinyint(4) NOT NULL,
  `last_modified_timestamp` datetime NOT NULL,
  `last_modified_user_id` int(11) NOT NULL
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `title` (`title`),
  UNIQUE KEY `pretty_url` (`pretty_url`),
  KEY `language_code` (`language_code`),
  KEY `pretty_url_2` (`pretty_url`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=2 ;
UPDATE `system` SET `value`='3' WHERE `key`='DB_VERSION';

ALTER TABLE `user_card`
  ADD `major` VARCHAR(100) NULL,
  ADD INDEX ( `major` );
UPDATE `system` SET `value`='4' WHERE `key`='DB_VERSION';