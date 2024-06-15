RENAME TABLE `itemimage` TO `images`;
ALTER TABLE `images` CHANGE `itemid` `parentid` INT(11) NOT NULL;
ALTER TABLE `images` ADD `module` varchar(255) DEFAULT NULL AFTER `parentid`;
ALTER TABLE `images` ADD `controller` varchar(255) DEFAULT NULL AFTER `module`;
UPDATE `images` SET `module` = 'items';
UPDATE `images` SET `controller` = 'item';

ALTER TABLE `category` ADD `shopid` int(11) NOT NULL AFTER `parentid`;
ALTER TABLE `category` ADD `subtitle` varchar(255) DEFAULT NULL AFTER `title`;
ALTER TABLE `category` ADD `keyword` varchar(255) DEFAULT NULL AFTER `subtitle`;
ALTER TABLE `category` ADD `slug` varchar(255) DEFAULT NULL AFTER `keyword`;
ALTER TABLE `category` ADD `header` text DEFAULT NULL AFTER `image`;
ALTER TABLE `category` ADD `footer` text DEFAULT NULL AFTER `header`;
ALTER TABLE `category` ADD `shortdescription` text DEFAULT NULL AFTER `description`;
ALTER TABLE `category` ADD `minidescription` text DEFAULT NULL AFTER `shortdescription`;
ALTER TABLE `category` ADD `metatitle` varchar(255) DEFAULT NULL AFTER `minidescription`;
ALTER TABLE `category` ADD `metadescription` varchar(255) DEFAULT NULL AFTER `metatitle`;
ALTER TABLE `category` ADD `metakeyword` varchar(255) DEFAULT NULL AFTER `metadescription`;

ALTER TABLE `item` ADD `shopcatid` int(11) NOT NULL DEFAULT 0 AFTER `catid`;
ALTER TABLE `item` ADD `ebaycatid` int(11) NOT NULL DEFAULT 0 AFTER `shopcatid`;
ALTER TABLE `item` ADD `amazoncatid` int(11) NOT NULL DEFAULT 0 AFTER `ebaycatid`;
ALTER TABLE `item` DROP `shopcategory`;
ALTER TABLE `item` CHANGE `ebaytitle` `ebaytitle` varchar(255) DEFAULT NULL AFTER `shoptitle`;
ALTER TABLE `item` CHANGE `amazontitle` `amazontitle` varchar(255) DEFAULT NULL AFTER `ebaytitle`;
ALTER TABLE `item` ADD `slug` varchar(255) DEFAULT NULL AFTER `amazontitle`;
ALTER TABLE `item` ADD `ebayenabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `shopenabled`;
ALTER TABLE `item` ADD `amazonenabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `ebayenabled`;
ALTER TABLE `item` ADD `shopid` int(11) NOT NULL DEFAULT 0 AFTER `amazoncatid`;

ALTER TABLE `tag` ADD `shopid` int(11) NOT NULL DEFAULT 0 AFTER `id`;
ALTER TABLE `tag` ADD `keyword` varchar(255) DEFAULT NULL AFTER `title`;
ALTER TABLE `tag` ADD `slug` varchar(255) DEFAULT NULL AFTER `keyword`;
ALTER TABLE `tag` ADD `header` text DEFAULT NULL AFTER `controller`;
ALTER TABLE `tag` ADD `footer` text DEFAULT NULL AFTER `header`;
ALTER TABLE `tag` ADD `description` text DEFAULT NULL AFTER `footer`;

ALTER TABLE `manufacturer` ADD `description` text DEFAULT NULL AFTER `name`;

CREATE TABLE IF NOT EXISTS `shop` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) DEFAULT NULL,
  `timezone` varchar(255) DEFAULT NULL,
  `language` varchar(255) DEFAULT NULL,
  `analytics` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `footer` varchar(255) DEFAULT NULL,
  `emailsender` varchar(255) DEFAULT NULL,
  `smtphost` varchar(255) DEFAULT NULL,
  `smtpauth` varchar(255) DEFAULT NULL,
  `smtpsecure` varchar(255) DEFAULT NULL,
  `smtpuser` varchar(255) DEFAULT NULL,
  `smtppass` varchar(32) DEFAULT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `activated` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY (url),
  KEY (clientid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `slide` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shopid` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `activated` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY (url),
  KEY (clientid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `page` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shopid` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `header` text DEFAULT NULL,
  `footer` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `metatitle` varchar(255) DEFAULT NULL,
  `metadescription` varchar(255) DEFAULT NULL,
  `metakeyword` varchar(255) DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `activated` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY (url),
  KEY (clientid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shopid` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `activated` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY (shopid),
  KEY (clientid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `menuitem` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menuid` int(11) NOT NULL,
  `pageid` int(11) NOT NULL,
  `parentid` int(11) NOT NULL DEFAULT 0,
  `title` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `activated` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY (slug),
  KEY (menuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `tagentity` ADD INDEX(`tagid`);
ALTER TABLE `tagentity` ADD INDEX(`entityid`);
ALTER TABLE `tagentity` ADD INDEX(`module`);
ALTER TABLE `tagentity` ADD INDEX(`controller`);
ALTER TABLE `tagentity` ADD INDEX(`deleted`);

ALTER TABLE `category` ADD INDEX(`slug`);
ALTER TABLE `category` ADD INDEX(`shopid`);
ALTER TABLE `category` ADD INDEX(`type`);
ALTER TABLE `category` ADD INDEX(`clientid`);
ALTER TABLE `category` ADD INDEX(`deleted`);

ALTER TABLE `contact` ADD INDEX(`contactid`);
ALTER TABLE `contact` ADD INDEX(`name1`);
ALTER TABLE `contact` ADD INDEX(`name2`);
ALTER TABLE `contact` ADD INDEX(`clientid`);
ALTER TABLE `contact` ADD INDEX(`deleted`);

ALTER TABLE `address` ADD INDEX(`type`);
ALTER TABLE `address` ADD INDEX(`department`);
ALTER TABLE `address` ADD INDEX(`street`);
ALTER TABLE `address` ADD INDEX(`postcode`);
ALTER TABLE `address` ADD INDEX(`city`);
ALTER TABLE `address` ADD INDEX(`country`);

ALTER TABLE `phone` ADD INDEX(`module`);
ALTER TABLE `phone` ADD INDEX(`controller`);
ALTER TABLE `phone` ADD INDEX(`type`);
ALTER TABLE `phone` ADD INDEX(`phone`);
ALTER TABLE `phone` ADD INDEX(`clientid`);
ALTER TABLE `phone` ADD INDEX(`deleted`);

ALTER TABLE `email` ADD INDEX(`module`);
ALTER TABLE `email` ADD INDEX(`controller`);
ALTER TABLE `email` ADD INDEX(`email`);
ALTER TABLE `email` ADD INDEX(`clientid`);
ALTER TABLE `email` ADD INDEX(`deleted`);

ALTER TABLE `internet` ADD INDEX(`module`);
ALTER TABLE `internet` ADD INDEX(`controller`);
ALTER TABLE `internet` ADD INDEX(`internet`);
ALTER TABLE `internet` ADD INDEX(`clientid`);
ALTER TABLE `internet` ADD INDEX(`deleted`);

ALTER TABLE `shoporder` ADD INDEX(`shopid`);
ALTER TABLE `shoporder` ADD INDEX(`orderid`);
ALTER TABLE `shoporder` ADD INDEX(`contactid`);
ALTER TABLE `shoporder` ADD INDEX(`clientid`);

ALTER TABLE `item` ADD INDEX(`catid`);
ALTER TABLE `item` ADD INDEX(`sku`);
ALTER TABLE `item` ADD INDEX(`clientid`);

ALTER TABLE `itematr` ADD INDEX(`itemid`);
ALTER TABLE `itematr` ADD INDEX(`atrsetid`);
ALTER TABLE `itematr` ADD INDEX(`ordering`);

ALTER TABLE `itemopt` ADD INDEX(`itemid`);
ALTER TABLE `itemopt` ADD INDEX(`optsetid`);
ALTER TABLE `itemopt` ADD INDEX(`ordering`);

ALTER TABLE `images` ADD INDEX(`parentid`);
ALTER TABLE `images` ADD INDEX(`module`);
ALTER TABLE `images` ADD INDEX(`controller`);
