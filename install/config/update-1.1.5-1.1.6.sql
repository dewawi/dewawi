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
