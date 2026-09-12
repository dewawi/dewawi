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

ALTER TABLE `creditnote` ADD `completeddate` datetime DEFAULT NULL AFTER `completed`;
ALTER TABLE `creditnote` ADD `completedby` int(11) NOT NULL DEFAULT 0 AFTER `completeddate`;
ALTER TABLE `creditnote` ADD `cancelleddate` datetime DEFAULT NULL AFTER `cancelled`;
ALTER TABLE `creditnote` ADD `cancelledby` int(11) NOT NULL DEFAULT 0 AFTER `cancelleddate`;

ALTER TABLE `deliveryorder` ADD `completeddate` datetime DEFAULT NULL AFTER `completed`;
ALTER TABLE `deliveryorder` ADD `completedby` int(11) NOT NULL DEFAULT 0 AFTER `completeddate`;
ALTER TABLE `deliveryorder` ADD `cancelleddate` datetime DEFAULT NULL AFTER `cancelled`;
ALTER TABLE `deliveryorder` ADD `cancelledby` int(11) NOT NULL DEFAULT 0 AFTER `cancelleddate`;

ALTER TABLE `invoice` ADD `completeddate` datetime DEFAULT NULL AFTER `completed`;
ALTER TABLE `invoice` ADD `completedby` int(11) NOT NULL DEFAULT 0 AFTER `completeddate`;
ALTER TABLE `invoice` ADD `cancelleddate` datetime DEFAULT NULL AFTER `cancelled`;
ALTER TABLE `invoice` ADD `cancelledby` int(11) NOT NULL DEFAULT 0 AFTER `cancelleddate`;

ALTER TABLE `purchaseorder` ADD `completeddate` datetime DEFAULT NULL AFTER `completed`;
ALTER TABLE `purchaseorder` ADD `completedby` int(11) NOT NULL DEFAULT 0 AFTER `completeddate`;
ALTER TABLE `purchaseorder` ADD `cancelleddate` datetime DEFAULT NULL AFTER `cancelled`;
ALTER TABLE `purchaseorder` ADD `cancelledby` int(11) NOT NULL DEFAULT 0 AFTER `cancelleddate`;

ALTER TABLE `quote` ADD `completeddate` datetime DEFAULT NULL AFTER `completed`;
ALTER TABLE `quote` ADD `completedby` int(11) NOT NULL DEFAULT 0 AFTER `completeddate`;
ALTER TABLE `quote` ADD `cancelleddate` datetime DEFAULT NULL AFTER `cancelled`;
ALTER TABLE `quote` ADD `cancelledby` int(11) NOT NULL DEFAULT 0 AFTER `cancelleddate`;

ALTER TABLE `quoterequest` ADD `completeddate` datetime DEFAULT NULL AFTER `completed`;
ALTER TABLE `quoterequest` ADD `completedby` int(11) NOT NULL DEFAULT 0 AFTER `completeddate`;
ALTER TABLE `quoterequest` ADD `cancelleddate` datetime DEFAULT NULL AFTER `cancelled`;
ALTER TABLE `quoterequest` ADD `cancelledby` int(11) NOT NULL DEFAULT 0 AFTER `cancelleddate`;

ALTER TABLE `reminder` ADD `completeddate` datetime DEFAULT NULL AFTER `completed`;
ALTER TABLE `reminder` ADD `completedby` int(11) NOT NULL DEFAULT 0 AFTER `completeddate`;
ALTER TABLE `reminder` ADD `cancelleddate` datetime DEFAULT NULL AFTER `cancelled`;
ALTER TABLE `reminder` ADD `cancelledby` int(11) NOT NULL DEFAULT 0 AFTER `cancelleddate`;

ALTER TABLE `salesorder` ADD `completeddate` datetime DEFAULT NULL AFTER `completed`;
ALTER TABLE `salesorder` ADD `completedby` int(11) NOT NULL DEFAULT 0 AFTER `completeddate`;
ALTER TABLE `salesorder` ADD `cancelleddate` datetime DEFAULT NULL AFTER `cancelled`;
ALTER TABLE `salesorder` ADD `cancelledby` int(11) NOT NULL DEFAULT 0 AFTER `cancelleddate`;

ALTER TABLE `process` ADD `completeddate` datetime DEFAULT NULL AFTER `completed`;
ALTER TABLE `process` ADD `completedby` int(11) NOT NULL DEFAULT 0 AFTER `completeddate`;
ALTER TABLE `process` ADD `cancelleddate` datetime DEFAULT NULL AFTER `cancelled`;
ALTER TABLE `process` ADD `cancelledby` int(11) NOT NULL DEFAULT 0 AFTER `cancelleddate`;

UPDATE `quote` SET `completed` = 1 WHERE `state` = 105 AND `completed` = 0;
UPDATE `invoice` SET `completed` = 1 WHERE `state` = 105 AND `completed` = 0;
UPDATE `salesorder` SET `completed` = 1 WHERE `state` = 105 AND `completed` = 0;
UPDATE `deliveryorder` SET `completed` = 1 WHERE `state` = 105 AND `completed` = 0;
UPDATE `creditnote` SET `completed` = 1 WHERE `state` = 105 AND `completed` = 0;
UPDATE `reminder` SET `completed` = 1 WHERE `state` = 105 AND `completed` = 0;
UPDATE `purchaseorder` SET `completed` = 1 WHERE `state` = 105 AND `completed` = 0;
UPDATE `quoterequest` SET `completed` = 1 WHERE `state` = 105 AND `completed` = 0;
UPDATE `process` SET `completed` = 1 WHERE `state` = 105 AND `completed` = 0;

UPDATE `quote` SET `cancelled` = 1 WHERE `state` = 106 AND `cancelled` = 0;
UPDATE `invoice` SET `cancelled` = 1 WHERE `state` = 106 AND `cancelled` = 0;
UPDATE `salesorder` SET `cancelled` = 1 WHERE `state` = 106 AND `cancelled` = 0;
UPDATE `deliveryorder` SET `cancelled` = 1 WHERE `state` = 106 AND `cancelled` = 0;
UPDATE `creditnote` SET `cancelled` = 1 WHERE `state` = 106 AND `cancelled` = 0;
UPDATE `reminder` SET `cancelled` = 1 WHERE `state` = 106 AND `cancelled` = 0;
UPDATE `purchaseorder` SET `cancelled` = 1 WHERE `state` = 106 AND `cancelled` = 0;
UPDATE `quoterequest` SET `cancelled` = 1 WHERE `state` = 106 AND `cancelled` = 0;
UPDATE `process` SET `cancelled` = 1 WHERE `state` = 106 AND `cancelled` = 0;
