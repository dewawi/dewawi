ALTER TABLE `invoice` ADD `prepayment` DECIMAL(12,4) NULL DEFAULT NULL AFTER `total`;
CREATE TABLE IF NOT EXISTS `creditnoteposset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `creditnotepos` CHANGE `creditnoteid` `parentid` INT(11) NOT NULL;
ALTER TABLE `creditnotepos` ADD `possetid` int(11) NOT NULL AFTER `itemid`;
CREATE TABLE IF NOT EXISTS `deliveryorderposset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `deliveryorderpos` CHANGE `deliveryorderid` `parentid` INT(11) NOT NULL;
ALTER TABLE `deliveryorderpos` ADD `possetid` int(11) NOT NULL AFTER `itemid`;
CREATE TABLE IF NOT EXISTS `invoiceposset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `invoicepos` CHANGE `invoiceid` `parentid` INT(11) NOT NULL;
ALTER TABLE `invoicepos` ADD `possetid` int(11) NOT NULL AFTER `itemid`;
CREATE TABLE IF NOT EXISTS `purchaseorderposset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `purchaseorderpos` CHANGE `purchaseorderid` `parentid` INT(11) NOT NULL;
ALTER TABLE `purchaseorderpos` ADD `possetid` int(11) NOT NULL AFTER `itemid`;
CREATE TABLE IF NOT EXISTS `quoteposset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `quotepos` CHANGE `quoteid` `parentid` INT(11) NOT NULL;
ALTER TABLE `quotepos` ADD `possetid` int(11) NOT NULL AFTER `itemid`;
CREATE TABLE IF NOT EXISTS `quoterequestposset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `quoterequestpos` CHANGE `quoterequestid` `parentid` INT(11) NOT NULL;
ALTER TABLE `quoterequestpos` ADD `possetid` int(11) NOT NULL AFTER `itemid`;
CREATE TABLE IF NOT EXISTS `reminderposset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `reminderpos` CHANGE `reminderid` `parentid` INT(11) NOT NULL;
ALTER TABLE `reminderpos` ADD `possetid` int(11) NOT NULL AFTER `itemid`;
CREATE TABLE IF NOT EXISTS `salesorderposset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `salesorderpos` CHANGE `salesorderid` `parentid` INT(11) NOT NULL;
ALTER TABLE `salesorderpos` ADD `possetid` int(11) NOT NULL AFTER `itemid`;
CREATE TABLE IF NOT EXISTS `processposset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `processpos` CHANGE `processid` `parentid` INT(11) NOT NULL;
ALTER TABLE `processpos` ADD `possetid` int(11) NOT NULL AFTER `itemid`;
CREATE TABLE IF NOT EXISTS `itematr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `itemid` int(11) NOT NULL DEFAULT 0,
  `atrsetid` int(11) NOT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(12,4) DEFAULT NULL,
  `taxrate` decimal(12,4) DEFAULT NULL,
  `priceruleamount` decimal(12,4) DEFAULT NULL,
  `priceruleaction` varchar(255) DEFAULT NULL,
  `quantity` decimal(12,4) DEFAULT NULL,
  `total` decimal(12,4) DEFAULT NULL,
  `currency` varchar(255) DEFAULT NULL,
  `uom` varchar(255) DEFAULT NULL,
  `manufacturerid` int(11) NOT NULL DEFAULT 0,
  `manufacturersku` varchar(255) DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `itematrset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `itemopt` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `itemid` int(11) NOT NULL DEFAULT 0,
  `optsetid` int(11) NOT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(12,4) DEFAULT NULL,
  `taxrate` decimal(12,4) DEFAULT NULL,
  `priceruleamount` decimal(12,4) DEFAULT NULL,
  `priceruleaction` varchar(255) DEFAULT NULL,
  `quantity` decimal(12,4) DEFAULT NULL,
  `total` decimal(12,4) DEFAULT NULL,
  `currency` varchar(255) DEFAULT NULL,
  `uom` varchar(255) DEFAULT NULL,
  `manufacturerid` int(11) NOT NULL DEFAULT 0,
  `manufacturersku` varchar(255) DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `itemoptset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `itemlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `info` text DEFAULT NULL,
  `params` text DEFAULT NULL,
  `templateid` int(11) NOT NULL,
  `language` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `category` ADD `image` VARCHAR(255) NULL DEFAULT NULL AFTER `title`;
ALTER TABLE `category` ADD `description` TEXT NULL DEFAULT NULL AFTER `image`;
ALTER TABLE `category` ADD `footer` TEXT NULL DEFAULT NULL AFTER `description`;
ALTER TABLE `creditnotepos` ADD `masterid` int(11) NOT NULL DEFAULT 0 AFTER `itemid`;
ALTER TABLE `deliveryorderpos` ADD `masterid` int(11) NOT NULL DEFAULT 0 AFTER `itemid`;
ALTER TABLE `invoicepos` ADD `masterid` int(11) NOT NULL DEFAULT 0 AFTER `itemid`;
ALTER TABLE `quotepos` ADD `masterid` int(11) NOT NULL DEFAULT 0 AFTER `itemid`;
ALTER TABLE `reminderpos` ADD `masterid` int(11) NOT NULL DEFAULT 0 AFTER `itemid`;
ALTER TABLE `salesorderpos` ADD `masterid` int(11) NOT NULL DEFAULT 0 AFTER `itemid`;
ALTER TABLE `quoterequestpos` ADD `masterid` int(11) NOT NULL DEFAULT 0 AFTER `itemid`;
ALTER TABLE `purchaseorderpos` ADD `masterid` int(11) NOT NULL DEFAULT 0 AFTER `itemid`;
ALTER TABLE `processpos` ADD `masterid` int(11) NOT NULL DEFAULT 0 AFTER `itemid`;
ALTER TABLE `creditnotepos` ADD `manufacturerid` int(11) NOT NULL DEFAULT 0 AFTER `uom`;
ALTER TABLE `deliveryorderpos` ADD `manufacturerid` int(11) NOT NULL DEFAULT 0 AFTER `uom`;
ALTER TABLE `invoicepos` ADD `manufacturerid` int(11) NOT NULL DEFAULT 0 AFTER `uom`;
ALTER TABLE `quotepos` ADD `manufacturerid` int(11) NOT NULL DEFAULT 0 AFTER `uom`;
ALTER TABLE `reminderpos` ADD `manufacturerid` int(11) NOT NULL DEFAULT 0 AFTER `uom`;
ALTER TABLE `salesorderpos` ADD `manufacturerid` int(11) NOT NULL DEFAULT 0 AFTER `uom`;
ALTER TABLE `quoterequestpos` ADD `manufacturerid` int(11) NOT NULL DEFAULT 0 AFTER `uom`;
ALTER TABLE `purchaseorderpos` ADD `manufacturerid` int(11) NOT NULL DEFAULT 0 AFTER `uom`;
ALTER TABLE `processpos` ADD `manufacturerid` int(11) NOT NULL DEFAULT 0 AFTER `uom`;
ALTER TABLE `creditnotepos` ADD `manufacturersku` varchar(255) DEFAULT NULL AFTER `manufacturerid`;
ALTER TABLE `deliveryorderpos` ADD `manufacturersku` varchar(255) DEFAULT NULL AFTER `manufacturerid`;
ALTER TABLE `invoicepos` ADD `manufacturersku` varchar(255) DEFAULT NULL AFTER `manufacturerid`;
ALTER TABLE `quotepos` ADD `manufacturersku` varchar(255) DEFAULT NULL AFTER `manufacturerid`;
ALTER TABLE `reminderpos` ADD `manufacturersku` varchar(255) DEFAULT NULL AFTER `manufacturerid`;
ALTER TABLE `salesorderpos` ADD `manufacturersku` varchar(255) DEFAULT NULL AFTER `manufacturerid`;
ALTER TABLE `quoterequestpos` ADD `manufacturersku` varchar(255) DEFAULT NULL AFTER `manufacturerid`;
ALTER TABLE `purchaseorderpos` ADD `manufacturersku` varchar(255) DEFAULT NULL AFTER `manufacturerid`;
ALTER TABLE `processpos` ADD `manufacturersku` varchar(255) DEFAULT NULL AFTER `manufacturerid`;
ALTER TABLE `creditnotepos` ADD `pricerulemaster` tinyint(1) NOT NULL DEFAULT 0 AFTER `taxrate`;
ALTER TABLE `deliveryorderpos` ADD `pricerulemaster` tinyint(1) NOT NULL DEFAULT 0 AFTER `taxrate`;
ALTER TABLE `invoicepos` ADD `pricerulemaster` tinyint(1) NOT NULL DEFAULT 0 AFTER `taxrate`;
ALTER TABLE `quotepos` ADD `pricerulemaster` tinyint(1) NOT NULL DEFAULT 0 AFTER `taxrate`;
ALTER TABLE `reminderpos` ADD `pricerulemaster` tinyint(1) NOT NULL DEFAULT 0 AFTER `taxrate`;
ALTER TABLE `salesorderpos` ADD `pricerulemaster` tinyint(1) NOT NULL DEFAULT 0 AFTER `taxrate`;
ALTER TABLE `quoterequestpos` ADD `pricerulemaster` tinyint(1) NOT NULL DEFAULT 0 AFTER `taxrate`;
ALTER TABLE `purchaseorderpos` ADD `pricerulemaster` tinyint(1) NOT NULL DEFAULT 0 AFTER `taxrate`;
ALTER TABLE `processpos` ADD `pricerulemaster` tinyint(1) NOT NULL DEFAULT 0 AFTER `taxrate`;

ALTER TABLE `user` CHANGE `password` `password` VARCHAR(255) NOT NULL;
