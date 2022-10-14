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
