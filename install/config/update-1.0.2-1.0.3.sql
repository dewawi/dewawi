CREATE TABLE IF NOT EXISTS `comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(255) DEFAULT NULL,
  `controller` varchar(255) DEFAULT NULL,
  `parentid` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY (parentid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `contact` ADD `pinned` tinyint(1) NOT NULL DEFAULT 0 AFTER `cashdiscountpercent`;
ALTER TABLE `item` ADD `pinned` tinyint(1) NOT NULL DEFAULT 0 AFTER `packweight`;
ALTER TABLE `creditnote` ADD `pinned` tinyint(11) NOT NULL DEFAULT 0 AFTER `filename`;
ALTER TABLE `deliveryorder` ADD `pinned` tinyint(11) NOT NULL DEFAULT 0 AFTER `filename`;
ALTER TABLE `invoice` ADD `pinned` tinyint(11) NOT NULL DEFAULT 0 AFTER `filename`;
ALTER TABLE `quote` ADD `pinned` tinyint(11) NOT NULL DEFAULT 0 AFTER `filename`;
ALTER TABLE `reminder` ADD `pinned` tinyint(11) NOT NULL DEFAULT 0 AFTER `filename`;
ALTER TABLE `salesorder` ADD `pinned` tinyint(11) NOT NULL DEFAULT 0 AFTER `filename`;
ALTER TABLE `quoterequest` ADD `pinned` tinyint(11) NOT NULL DEFAULT 0 AFTER `filename`;
ALTER TABLE `purchaseorder` ADD `pinned` tinyint(11) NOT NULL DEFAULT 0 AFTER `filename`;
ALTER TABLE `process` ADD `pinned` tinyint(11) NOT NULL DEFAULT 0 AFTER `contactperson`;
ALTER TABLE `task` ADD `pinned` tinyint(11) NOT NULL DEFAULT 0 AFTER `responsible`;

ALTER TABLE `email` ADD `module` varchar(255) DEFAULT NULL AFTER `id`;
ALTER TABLE `email` ADD `controller` varchar(255) DEFAULT NULL AFTER `module`;
ALTER TABLE `email` CHANGE `contactid` `parentid` int(11) NOT NULL;
ALTER TABLE `phone` ADD `module` varchar(255) DEFAULT NULL AFTER `id`;
ALTER TABLE `phone` ADD `controller` varchar(255) DEFAULT NULL AFTER `module`;
ALTER TABLE `phone` CHANGE `contactid` `parentid` int(11) NOT NULL;
ALTER TABLE `internet` ADD `module` varchar(255) DEFAULT NULL AFTER `id`;
ALTER TABLE `internet` ADD `controller` varchar(255) DEFAULT NULL AFTER `module`;
ALTER TABLE `internet` CHANGE `contactid` `parentid` int(11) NOT NULL;
UPDATE `email` SET `module` = 'contacts';
UPDATE `email` SET `controller` = 'contact';
UPDATE `phone` SET `module` = 'contacts';
UPDATE `phone` SET `controller` = 'contact';
UPDATE `internet` SET `module` = 'contacts';
UPDATE `internet` SET `controller` = 'contact';

CREATE TABLE IF NOT EXISTS `contactperson` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contactid` int(11) NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `gender` varchar(255) DEFAULT NULL,
  `name1` varchar(255) DEFAULT NULL,
  `name2` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `salutation` varchar(255) DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY (contactid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `emailmessage` ADD `parentid` int(11) NOT NULL AFTER `documentid`;
ALTER TABLE `emailmessage` CHANGE `module` `module` varchar(255) DEFAULT NULL AFTER `parentid`;
ALTER TABLE `emailmessage` CHANGE `controller` `controller` varchar(255) DEFAULT NULL AFTER `module`;

ALTER TABLE `creditnote` ADD `notes` text DEFAULT NULL AFTER `info`;
ALTER TABLE `deliveryorder` ADD `notes` text DEFAULT NULL AFTER `info`;
ALTER TABLE `invoice` ADD `notes` text DEFAULT NULL AFTER `info`;
ALTER TABLE `quote` ADD `notes` text DEFAULT NULL AFTER `info`;
ALTER TABLE `reminder` ADD `notes` text DEFAULT NULL AFTER `info`;
ALTER TABLE `salesorder` ADD `notes` text DEFAULT NULL AFTER `info`;
ALTER TABLE `quoterequest` ADD `notes` text DEFAULT NULL AFTER `info`;
ALTER TABLE `purchaseorder` ADD `notes` text DEFAULT NULL AFTER `info`;
ALTER TABLE `contact` ADD `notes` text DEFAULT NULL AFTER `info`;
