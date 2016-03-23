/* Set longer username in user table */
ALTER TABLE user CHANGE username username VARCHAR(64);

/* Create indexed eppn for purposes of connected federative identities */
ALTER TABLE user_card ADD COLUMN eppn VARCHAR(64);
CREATE UNIQUE INDEX user_card_eppn_uq ON user_card(eppn);

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
CREATE TABLE `portal_pages` (
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
  `last_modified_user_id` int(11) NOT NULL,
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

/* Notifications */
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL, /* Even though this is user_id, it has to be called id because of Zend's incompatibility */
  `has_blocks` tinyint(1) NOT NULL,
  `has_fines` tinyint(1) NOT NULL,
  `has_overdues` tinyint(1) NOT NULL,
  `last_fetched` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `notifications`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `notifications`
ADD CONSTRAINT `user_card_id` FOREIGN KEY (`id`) REFERENCES `user_card` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

UPDATE `system` SET `value`='5' WHERE `key`='DB_VERSION';

ALTER TABLE `notifications` ADD `blocks_read` BOOLEAN NOT NULL AFTER `has_overdues`, ADD `fines_read` BOOLEAN NOT NULL AFTER `blocks_read`, ADD `overdues_read` BOOLEAN NOT NULL AFTER `fines_read`;

UPDATE `system` SET `value`='6' WHERE `key`='DB_VERSION';

CREATE TABLE IF NOT EXISTS `institutions` (
  `id` int(11) unsigned NOT NULL,
  `source` varchar(100) NOT NULL DEFAULT '',
  `url` mediumtext NOT NULL,
  `type` enum('Aleph','NCIP') NULL,
  `timeout` int(3) unsigned DEFAULT 10 NOT NULL,
  `bot_username` varchar(64) DEFAULT NULL,
  `bot_password` varchar(64) DEFAULT NULL,
  `logo_url` mediumtext NOT NULL,
  `name_cs` mediumtext NOT NULL,
  `name_en` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

ALTER TABLE `institutions`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `source` (`source`);

ALTER TABLE `institutions`
MODIFY `id` int(11) unsigned NOT NULL AUTO_INCREMENT;

UPDATE `system` SET `value`='7' WHERE `key`='DB_VERSION';

ALTER TABLE `user_card` ADD CONSTRAINT `home_library_link_1` FOREIGN KEY (`home_library`) REFERENCES `vufind`.`institutions`(`source`) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `user` ADD CONSTRAINT `home_library_link_2` FOREIGN KEY (`home_library`) REFERENCES `vufind`.`institutions`(`source`) ON DELETE NO ACTION ON UPDATE NO ACTION;

UPDATE `system` SET `value`='8' WHERE `key`='DB_VERSION';

ALTER TABLE `institutions` ADD `entity_id` MEDIUMTEXT NOT NULL AFTER `url`;

UPDATE `system` SET `value`='9' WHERE `key`='DB_VERSION';

ALTER TABLE `institutions` CHANGE `type` `type` ENUM('Aleph','NCIP','IdP') CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

UPDATE `system` SET `value`='10' WHERE `key`='DB_VERSION';

CREATE TABLE IF NOT EXISTS `aleph_configs` (
`id` int(10) unsigned NOT NULL,
  `source` varchar(100) NOT NULL DEFAULT '',
  `host` mediumtext NOT NULL,
  `dlfport` int(6) NOT NULL,
  `wwwuser` mediumtext NOT NULL,
  `wwwpasswd` mediumtext NOT NULL,
  `maxItemsParsed` int(2) NOT NULL,
  `default_patron` mediumtext NOT NULL,
  `hmac_key` mediumtext NOT NULL,
  `bib` mediumtext NOT NULL,
  `useradm` mediumtext NOT NULL,
  `admlib` mediumtext NOT NULL,
  `available_statuses` mediumtext NOT NULL,
  `dont_show_link` mediumtext NOT NULL,
  `default_required_date` varchar(8) NOT NULL,
  `send_language` tinyint(1) NOT NULL,
  `debug` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `aleph_configs`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `source` (`source`);

ALTER TABLE `aleph_configs`
MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;

ALTER TABLE `aleph_configs`
ADD CONSTRAINT `institution_link` FOREIGN KEY (`source`) REFERENCES `institutions` (`source`) ON DELETE NO ACTION ON UPDATE NO ACTION;

UPDATE `system` SET `value`='11' WHERE `key`='DB_VERSION';

CREATE TABLE IF NOT EXISTS `xcncip2_configs` (
  `id` int(11) unsigned NOT NULL,
  `source` varchar(100) NOT NULL DEFAULT '',
  `url` mediumtext NOT NULL,
  `username` mediumtext,
  `password` mediumtext,
  `cacert` mediumtext NOT NULL,
  `agency` tinytext NOT NULL,
  `maximumItemsCount` int(11) NOT NULL DEFAULT '5',
  `hasUntrustedSSL` tinyint(1) NOT NULL,
  `cannotUseLUIS` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `xcncip2_configs`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `source` (`source`);

ALTER TABLE `xcncip2_configs`
ADD CONSTRAINT `institution_link_2` FOREIGN KEY (`source`) REFERENCES `institutions` (`source`) ON DELETE NO ACTION ON UPDATE NO ACTION;

UPDATE `system` SET `value`='12' WHERE `key`='DB_VERSION';

ALTER TABLE `institutions`
  DROP `bot_username`,
  DROP `bot_password`;

UPDATE `system` SET `value`='13' WHERE `key`='DB_VERSION';

ALTER TABLE `vufind`.`institutions` CHANGE `logo_url` `logo` MEDIUMTEXT NOT NULL;

ALTER TABLE `xcncip2_configs` ADD `paymentUrl` MEDIUMTEXT NULL AFTER `url`;

UPDATE `system` SET `value`='14' WHERE `key`='DB_VERSION';

ALTER TABLE `aleph_configs` ADD `type` MEDIUMTEXT NOT NULL COMMENT 'Which resolver to choose - xserver or solr?' AFTER `dont_show_link`, ADD `solrQueryField` MEDIUMTEXT NOT NULL COMMENT 'solrQueryField must contain the name of field within which are all the IDs located' AFTER `type`, ADD `itemIdentifier` MEDIUMTEXT NOT NULL COMMENT 'itemIdentifier must contain the name of field within which is the ID located' AFTER `solrQueryField`;

ALTER TABLE `aleph_configs` CHANGE `hmac_key` `hmac_key` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'This is used with creating a hash to verify prolongation url';

ALTER TABLE `aleph_configs` CHANGE `default_patron` `default_patron` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'Default patron ID will be included into these queries, where we want to know item''s requestability'; 

ALTER TABLE `aleph_configs` CHANGE `maxItemsParsed` `maxItemsParsed` INT(2) NOT NULL COMMENT 'To disable this feature set this to -1 .. if you unset it, there will be set 10 as default';

ALTER TABLE `aleph_configs` CHANGE `default_required_date` `default_required_date` VARCHAR(8) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'redDate - A colon-separated list used to set the default "not required after" date for holds in the format days:months:years e.g. 0:1:0 will set a "not required after" date of 1 month from the current date';

UPDATE `aleph_configs` SET `type`='solr',`solrQueryField`='barcodes',`itemIdentifier`='barcode';

UPDATE `system` SET `value`='15' WHERE `key`='DB_VERSION';

-- Add aleph_mappings table
CREATE TABLE IF NOT EXISTS `aleph_mappings` (
`id` int(11) NOT NULL,
  `source` varchar(100) NOT NULL DEFAULT '',
  `barcode` enum('z304-address-1','z304-address-2','z304-address-3','z304-address-4') DEFAULT NULL,
  `fullname` enum('z304-address-1','z304-address-2','z304-address-3','z304-address-4') NOT NULL,
  `address` enum('z304-address-1','z304-address-2','z304-address-3','z304-address-4') NOT NULL,
  `city` enum('z304-address-1','z304-address-2','z304-address-3','z304-address-4') NOT NULL,
  `zip` enum('z304-zip') DEFAULT NULL,
  `email` enum('z304-email-address') DEFAULT NULL,
  `user_group` enum('z305-bor-status') DEFAULT NULL,
  `expiration` enum('z305-expiry-date') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `aleph_mappings`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `source` (`source`);

ALTER TABLE `aleph_mappings`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
ALTER TABLE `aleph_mappings`
ADD CONSTRAINT `aleph_mappings` FOREIGN KEY (`source`) REFERENCES `institutions` (`source`) ON DELETE NO ACTION ON UPDATE NO ACTION;

UPDATE `system` SET `value`='16' WHERE `key`='DB_VERSION';

-- Set the foreign key to point only to aleph institutions
ALTER TABLE `aleph_mappings` DROP FOREIGN KEY `aleph_mappings`; ALTER TABLE `aleph_mappings` ADD CONSTRAINT `aleph_mappings` FOREIGN KEY (`source`) REFERENCES `vufind`.`aleph_configs`(`source`) ON DELETE NO ACTION ON UPDATE NO ACTION;

UPDATE `system` SET `value`='17' WHERE `key`='DB_VERSION';

ALTER TABLE `aleph_mappings` ADD `phone` ENUM('z304-sms-number','z304-telephone-1','z304-telephone-2','z304-telephone-3','z304-telephone-4') NULL AFTER `email`;

UPDATE `system` SET `value`='18' WHERE `key`='DB_VERSION';

ALTER TABLE `user_card` DROP FOREIGN KEY `home_library_link_1`;
ALTER TABLE `user` DROP FOREIGN KEY `home_library_link_2`;
ALTER TABLE `aleph_mappings` DROP FOREIGN KEY `aleph_mappings`;
ALTER TABLE `aleph_configs` DROP FOREIGN KEY `institution_link`;
ALTER TABLE `xcncip2_configs` DROP FOREIGN KEY `institution_link_2`;
DROP TABLE `aleph_mappings`;
DROP TABLE `aleph_configs`;
DROP TABLE `xcncip2_configs`;
DROP TABLE `institutions`;
