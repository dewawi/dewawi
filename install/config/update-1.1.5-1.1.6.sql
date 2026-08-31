ALTER TABLE `warehouse` CHANGE `active` `activated` tinyint(1) NOT NULL DEFAULT 0 AFTER `lockedtime`;
ALTER TABLE `module` CHANGE `active` `activated` tinyint(1) NOT NULL DEFAULT 0 AFTER `lockedtime`;
ALTER TABLE `itemstock` DROP KEY `itemid`, ADD UNIQUE KEY (`itemid`, `warehouseid`, `clientid`);
ALTER TABLE `item` ADD `parentid` int(11) DEFAULT NULL AFTER `id`, ADD KEY (`parentid`);

CREATE TABLE IF NOT EXISTS `itemvariantopt` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `itemid` int(11) NOT NULL,
  `itemoptid` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY (itemid, itemoptid, clientid),
  KEY (itemid),
  KEY (itemoptid),
  KEY (clientid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `itematr` ADD `masterid` int(11) DEFAULT NULL AFTER `itemid`;
ALTER TABLE `itemopt` ADD `masterid` int(11) DEFAULT NULL AFTER `itemid`;

ALTER TABLE `item` DROP COLUMN `quantity`;

ALTER TABLE `uom` ADD `default` tinyint(1) NOT NULL DEFAULT 0 AFTER `ordering`;

ALTER TABLE `creditnote` ADD COLUMN `pdfshowimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowcover`;
ALTER TABLE `creditnote` ADD COLUMN `pdfshowpositionimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowimages`;

ALTER TABLE `deliveryorder` ADD COLUMN `pdfshowimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowcover`;
ALTER TABLE `deliveryorder` ADD COLUMN `pdfshowpositionimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowimages`;

ALTER TABLE `invoice` ADD COLUMN `pdfshowimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowcover`;
ALTER TABLE `invoice` ADD COLUMN `pdfshowpositionimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowimages`;

ALTER TABLE `quote` ADD COLUMN `pdfshowimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowcover`;
ALTER TABLE `quote` ADD COLUMN `pdfshowpositionimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowimages`;

ALTER TABLE `reminder` ADD COLUMN `pdfshowimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowcover`;
ALTER TABLE `reminder` ADD COLUMN `pdfshowpositionimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowimages`;

ALTER TABLE `salesorder` ADD COLUMN `pdfshowimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowcover`;
ALTER TABLE `salesorder` ADD COLUMN `pdfshowpositionimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowimages`;

ALTER TABLE `quoterequest` ADD COLUMN `pdfshowimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowcover`;
ALTER TABLE `quoterequest` ADD COLUMN `pdfshowpositionimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowimages`;

ALTER TABLE `purchaseorder` ADD COLUMN `pdfshowimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowcover`;
ALTER TABLE `purchaseorder` ADD COLUMN `pdfshowpositionimages` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdfshowimages`;

UPDATE `quote` SET `pdfshowimages` = 1 WHERE `pdfshowimages` = 0;

ALTER TABLE `creditnotepos` ADD `cost` decimal(12,4) DEFAULT NULL AFTER `description`;
ALTER TABLE `deliveryorderpos` ADD `cost` decimal(12,4) DEFAULT NULL AFTER `description`;
ALTER TABLE `invoicepos` ADD `cost` decimal(12,4) DEFAULT NULL AFTER `description`;
ALTER TABLE `quotepos` ADD `cost` decimal(12,4) DEFAULT NULL AFTER `description`;
ALTER TABLE `reminderpos` ADD `cost` decimal(12,4) DEFAULT NULL AFTER `description`;
ALTER TABLE `salesorderpos` ADD `cost` decimal(12,4) DEFAULT NULL AFTER `description`;
ALTER TABLE `quoterequestpos` ADD `cost` decimal(12,4) DEFAULT NULL AFTER `description`;
ALTER TABLE `purchaseorderpos` ADD `cost` decimal(12,4) DEFAULT NULL AFTER `description`;
ALTER TABLE `processpos` ADD `cost` decimal(12,4) DEFAULT NULL AFTER `description`;

ALTER TABLE `creditnote` ADD `contactpersonid` int(11) DEFAULT NULL AFTER `contactperson`;
ALTER TABLE `creditnote` ADD `responsible` varchar(255) DEFAULT NULL AFTER `contactpersonid`;
ALTER TABLE `creditnote` ADD `responsibleid` int(11) DEFAULT NULL AFTER `responsible`;

ALTER TABLE `deliveryorder` ADD `contactpersonid` int(11) DEFAULT NULL AFTER `contactperson`;
ALTER TABLE `deliveryorder` ADD `responsible` varchar(255) DEFAULT NULL AFTER `contactpersonid`;
ALTER TABLE `deliveryorder` ADD `responsibleid` int(11) DEFAULT NULL AFTER `responsible`;

ALTER TABLE `invoice` ADD `contactpersonid` int(11) DEFAULT NULL AFTER `contactperson`;
ALTER TABLE `invoice` ADD `responsible` varchar(255) DEFAULT NULL AFTER `contactpersonid`;
ALTER TABLE `invoice` ADD `responsibleid` int(11) DEFAULT NULL AFTER `responsible`;

ALTER TABLE `purchaseorder` ADD `contactpersonid` int(11) DEFAULT NULL AFTER `contactperson`;
ALTER TABLE `purchaseorder` ADD `responsible` varchar(255) DEFAULT NULL AFTER `contactpersonid`;
ALTER TABLE `purchaseorder` ADD `responsibleid` int(11) DEFAULT NULL AFTER `responsible`;

ALTER TABLE `quote` ADD `contactpersonid` int(11) DEFAULT NULL AFTER `contactperson`;
ALTER TABLE `quote` ADD `responsible` varchar(255) DEFAULT NULL AFTER `contactpersonid`;
ALTER TABLE `quote` ADD `responsibleid` int(11) DEFAULT NULL AFTER `responsible`;

ALTER TABLE `quoterequest` ADD `contactpersonid` int(11) DEFAULT NULL AFTER `contactperson`;
ALTER TABLE `quoterequest` ADD `responsible` varchar(255) DEFAULT NULL AFTER `contactpersonid`;
ALTER TABLE `quoterequest` ADD `responsibleid` int(11) DEFAULT NULL AFTER `responsible`;

ALTER TABLE `reminder` ADD `contactpersonid` int(11) DEFAULT NULL AFTER `contactperson`;
ALTER TABLE `reminder` ADD `responsible` varchar(255) DEFAULT NULL AFTER `contactpersonid`;
ALTER TABLE `reminder` ADD `responsibleid` int(11) DEFAULT NULL AFTER `responsible`;

ALTER TABLE `salesorder` ADD `contactpersonid` int(11) DEFAULT NULL AFTER `contactperson`;
ALTER TABLE `salesorder` ADD `responsible` varchar(255) DEFAULT NULL AFTER `contactpersonid`;
ALTER TABLE `salesorder` ADD `responsibleid` int(11) DEFAULT NULL AFTER `responsible`;

ALTER TABLE `process` ADD `contactpersonid` int(11) DEFAULT NULL AFTER `contactperson`;
ALTER TABLE `process` ADD `responsible` varchar(255) DEFAULT NULL AFTER `contactpersonid`;
ALTER TABLE `process` ADD `responsibleid` int(11) DEFAULT NULL AFTER `responsible`;

UPDATE `creditnote` SET `responsible` = `contactperson` WHERE `contactperson` IS NOT NULL AND `contactperson` != '';
UPDATE `deliveryorder` SET `responsible` = `contactperson` WHERE `contactperson` IS NOT NULL AND `contactperson` != '';
UPDATE `invoice` SET `responsible` = `contactperson` WHERE `contactperson` IS NOT NULL AND `contactperson` != '';
UPDATE `purchaseorder` SET `responsible` = `contactperson` WHERE `contactperson` IS NOT NULL AND `contactperson` != '';
UPDATE `quote` SET `responsible` = `contactperson` WHERE `contactperson` IS NOT NULL AND `contactperson` != '';
UPDATE `quoterequest` SET `responsible` = `contactperson` WHERE `contactperson` IS NOT NULL AND `contactperson` != '';
UPDATE `reminder` SET `responsible` = `contactperson` WHERE `contactperson` IS NOT NULL AND `contactperson` != '';
UPDATE `salesorder` SET `responsible` = `contactperson` WHERE `contactperson` IS NOT NULL AND `contactperson` != '';
UPDATE `process` SET `responsible` = `contactperson` WHERE `contactperson` IS NOT NULL AND `contactperson` != '';

UPDATE `creditnote` SET `contactperson` = NULL WHERE `responsible` IS NOT NULL;
UPDATE `deliveryorder` SET `contactperson` = NULL WHERE `responsible` IS NOT NULL;
UPDATE `invoice` SET `contactperson` = NULL WHERE `responsible` IS NOT NULL;
UPDATE `purchaseorder` SET `contactperson` = NULL WHERE `responsible` IS NOT NULL;
UPDATE `quote` SET `contactperson` = NULL WHERE `responsible` IS NOT NULL;
UPDATE `quoterequest` SET `contactperson` = NULL WHERE `responsible` IS NOT NULL;
UPDATE `reminder` SET `contactperson` = NULL WHERE `responsible` IS NOT NULL;
UPDATE `salesorder` SET `contactperson` = NULL WHERE `responsible` IS NOT NULL;
UPDATE `process` SET `contactperson` = NULL WHERE `responsible` IS NOT NULL;

ALTER TABLE `user` ADD `position` varchar(255) DEFAULT NULL AFTER `name`;
ALTER TABLE `user` ADD `phone` varchar(255) DEFAULT NULL AFTER `email`;
ALTER TABLE `user` ADD `mobile` varchar(255) DEFAULT NULL AFTER `phone`;
