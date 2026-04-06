CREATE TABLE IF NOT EXISTS `calendarevent` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `start` datetime NOT NULL,
  `end` datetime NOT NULL,
  `description` text DEFAULT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `permission` ADD `calendar` text DEFAULT NULL AFTER `default`;

CREATE TABLE IF NOT EXISTS `shopinquiryform` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shopid` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `fields` text DEFAULT NULL,
  `language` varchar(255) DEFAULT NULL,
  `quoteheader` text DEFAULT NULL,
  `quotefooter` text DEFAULT NULL,
  `quotetemplateid` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `shopinquirydata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `formid` int(11) NOT NULL,
  `shopid` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `data` TEXT NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `itematr` ADD `value` varchar(255) DEFAULT NULL AFTER `title`;
ALTER TABLE `itemopt` ADD `value` varchar(255) DEFAULT NULL AFTER `title`;

ALTER TABLE `increment` ADD `shoporderid` int(11) NOT NULL AFTER `salesorderid`;

ALTER TABLE `shoporder` ADD `orderdate` date DEFAULT NULL AFTER `invoiceid`;

RENAME TABLE `cloud`.`inventory` TO `cloud`.`ledger`;
ALTER TABLE `ledger` CHANGE `inventorydate` `ledgerdate` DATE NULL DEFAULT NULL;

ALTER TABLE `warehouse` ADD `deleted` tinyint(1) NOT NULL DEFAULT 0 AFTER `lockedtime`;

ALTER TABLE `address` CHANGE `contactid` `parentid` INT(11) NOT NULL;
ALTER TABLE `address` DROP INDEX `contactid`;
ALTER TABLE `address` ADD INDEX `parentid` (`parentid`);

ALTER TABLE `bankaccount` CHANGE `contactid` `parentid` INT(11) NOT NULL;
ALTER TABLE `bankaccount` ADD INDEX `parentid` (`parentid`);

ALTER TABLE `contactperson` CHANGE `contactid` `parentid` INT(11) NOT NULL;
ALTER TABLE `contactperson` DROP INDEX `contactid`;
ALTER TABLE `contactperson` ADD INDEX `parentid` (`parentid`);

ALTER TABLE `address` ADD COLUMN `module` VARCHAR(255) NULL AFTER `id`;
ALTER TABLE `address` ADD COLUMN `controller` VARCHAR(255) NULL AFTER `module`;
ALTER TABLE `address` ADD INDEX `module` (`module`);
ALTER TABLE `address` ADD INDEX `controller` (`controller`);

ALTER TABLE `contactperson` ADD COLUMN `module` VARCHAR(255) NULL AFTER `id`;
ALTER TABLE `contactperson` ADD COLUMN `controller` VARCHAR(255) NULL AFTER `module`;
ALTER TABLE `contactperson` ADD INDEX `module` (`module`);
ALTER TABLE `contactperson` ADD INDEX `controller` (`controller`);

ALTER TABLE `bankaccount` ADD COLUMN `module` VARCHAR(255) NULL AFTER `id`;
ALTER TABLE `bankaccount` ADD COLUMN `controller` VARCHAR(255) NULL AFTER `module`;
ALTER TABLE `bankaccount` ADD INDEX `module` (`module`);
ALTER TABLE `bankaccount` ADD INDEX `controller` (`controller`);

UPDATE `address` SET `module` = 'contacts', `controller` = 'contact' WHERE `module` IS NULL OR `controller` IS NULL;
UPDATE `contactperson` SET `module` = 'contacts', `controller` = 'contact' WHERE `module` IS NULL OR `controller` IS NULL;
UPDATE `bankaccount` SET `module` = 'contacts', `controller` = 'contact' WHERE `module` IS NULL OR `controller` IS NULL;
UPDATE `phone` SET `module` = 'contacts', `controller` = 'contact' WHERE `module` IS NULL OR `controller` IS NULL;

ALTER TABLE `process` CHANGE `customerid` `contactid` int(11) NOT NULL DEFAULT 0;
ALTER TABLE `task` CHANGE `customerid` `contactid` int(11) NOT NULL DEFAULT 0;

ALTER TABLE `creditnote` ADD COLUMN `pdfshowprices` TINYINT(1) NOT NULL DEFAULT 0 AFTER `templateid`,
ALTER TABLE `creditnote` ADD COLUMN `pdfshowdiscounts` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowprices`,
ALTER TABLE `creditnote` ADD COLUMN `pdfshowoptions` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowdiscounts`,
ALTER TABLE `creditnote` ADD COLUMN `pdfshowattributes` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowoptions`,
ALTER TABLE `creditnote` ADD COLUMN `pdfshowcover` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowattributes`;

ALTER TABLE `deliveryorder` ADD COLUMN `pdfshowprices` TINYINT(1) NOT NULL DEFAULT 0 AFTER `templateid`,
ALTER TABLE `deliveryorder` ADD COLUMN `pdfshowdiscounts` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowprices`,
ALTER TABLE `deliveryorder` ADD COLUMN `pdfshowoptions` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowdiscounts`,
ALTER TABLE `deliveryorder` ADD COLUMN `pdfshowattributes` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowoptions`,
ALTER TABLE `deliveryorder` ADD COLUMN `pdfshowcover` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowattributes`;

ALTER TABLE `invoice` ADD COLUMN `pdfshowprices` TINYINT(1) NOT NULL DEFAULT 0 AFTER `templateid`,
ALTER TABLE `invoice` ADD COLUMN `pdfshowdiscounts` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowprices`,
ALTER TABLE `invoice` ADD COLUMN `pdfshowoptions` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowdiscounts`,
ALTER TABLE `invoice` ADD COLUMN `pdfshowattributes` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowoptions`,
ALTER TABLE `invoice` ADD COLUMN `pdfshowcover` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowattributes`;

ALTER TABLE `quote` ADD COLUMN `pdfshowprices` TINYINT(1) NOT NULL DEFAULT 0 AFTER `templateid`,
ALTER TABLE `quote` ADD COLUMN `pdfshowdiscounts` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowprices`,
ALTER TABLE `quote` ADD COLUMN `pdfshowoptions` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowdiscounts`,
ALTER TABLE `quote` ADD COLUMN `pdfshowattributes` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowoptions`,
ALTER TABLE `quote` ADD COLUMN `pdfshowcover` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowattributes`;

ALTER TABLE `reminder` ADD COLUMN `pdfshowprices` TINYINT(1) NOT NULL DEFAULT 0 AFTER `templateid`,
ALTER TABLE `reminder` ADD COLUMN `pdfshowdiscounts` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowprices`,
ALTER TABLE `reminder` ADD COLUMN `pdfshowoptions` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowdiscounts`,
ALTER TABLE `reminder` ADD COLUMN `pdfshowattributes` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowoptions`,
ALTER TABLE `reminder` ADD COLUMN `pdfshowcover` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowattributes`;

ALTER TABLE `salesorder` ADD COLUMN `pdfshowprices` TINYINT(1) NOT NULL DEFAULT 0 AFTER `templateid`,
ALTER TABLE `salesorder` ADD COLUMN `pdfshowdiscounts` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowprices`,
ALTER TABLE `salesorder` ADD COLUMN `pdfshowoptions` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowdiscounts`,
ALTER TABLE `salesorder` ADD COLUMN `pdfshowattributes` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowoptions`,
ALTER TABLE `salesorder` ADD COLUMN `pdfshowcover` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowattributes`;

ALTER TABLE `quoterequest` ADD COLUMN `pdfshowprices` TINYINT(1) NOT NULL DEFAULT 0 AFTER `templateid`,
ALTER TABLE `quoterequest` ADD COLUMN `pdfshowdiscounts` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowprices`,
ALTER TABLE `quoterequest` ADD COLUMN `pdfshowoptions` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowdiscounts`,
ALTER TABLE `quoterequest` ADD COLUMN `pdfshowattributes` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowoptions`,
ALTER TABLE `quoterequest` ADD COLUMN `pdfshowcover` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowattributes`;

ALTER TABLE `purchaseorder` ADD COLUMN `pdfshowprices` TINYINT(1) NOT NULL DEFAULT 0 AFTER `templateid`,
ALTER TABLE `purchaseorder` ADD COLUMN `pdfshowdiscounts` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowprices`,
ALTER TABLE `purchaseorder` ADD COLUMN `pdfshowoptions` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowdiscounts`,
ALTER TABLE `purchaseorder` ADD COLUMN `pdfshowattributes` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowoptions`,
ALTER TABLE `purchaseorder` ADD COLUMN `pdfshowcover` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowattributes`;
