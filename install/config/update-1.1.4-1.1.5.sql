ALTER TABLE `media` ADD COLUMN `target` varchar(1024) NULL AFTER `url`;
ALTER TABLE `media` ADD COLUMN `description` text NULL AFTER `title`;

INSERT INTO media (parentid, module, controller, type, title, description, url, target, ordering, clientid)
SELECT shopid, 'shops', 'slide', 'slide', title, description, image, url, ordering, clientid
FROM slide;

ALTER TABLE `slide` DROP COLUMN `image`;
ALTER TABLE `slide` CHANGE COLUMN `url` `target` varchar(1024) DEFAULT NULL;
ALTER TABLE `slide` ADD COLUMN `position` varchar(255) DEFAULT NULL AFTER `target`;

ALTER TABLE `slide` DROP INDEX `url`;
ALTER TABLE `slide` ADD KEY `shopid` (`shopid`);
ALTER TABLE `slide` ADD KEY `deleted` (`deleted`);

ALTER TABLE `country` ADD `deleted` tinyint(1) NOT NULL DEFAULT 0 AFTER `lockedtime`;

ALTER TABLE `creditnote` MODIFY `creditnoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `creditnote` MODIFY `quoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `creditnote` MODIFY `salesorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `creditnote` MODIFY `invoiceid` int(11) NULL DEFAULT NULL;
ALTER TABLE `creditnote` MODIFY `contactid` int(11) NULL DEFAULT NULL;

UPDATE `creditnote` SET `creditnoteid` = NULL WHERE `creditnoteid` = 0;
UPDATE `creditnote` SET `quoteid` = NULL WHERE `quoteid` = 0;
UPDATE `creditnote` SET `salesorderid` = NULL WHERE `salesorderid` = 0;
UPDATE `creditnote` SET `invoiceid` = NULL WHERE `invoiceid` = 0;
UPDATE `creditnote` SET `contactid` = NULL WHERE `contactid` = 0;

ALTER TABLE `creditnotepos` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `creditnotepos` MODIFY `masterid` int(11) NULL DEFAULT NULL;
ALTER TABLE `creditnotepos` MODIFY `manufacturerid` int(11) NULL DEFAULT NULL;

UPDATE `creditnotepos` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `creditnotepos` SET `masterid` = NULL WHERE `masterid` = 0;
UPDATE `creditnotepos` SET `manufacturerid` = NULL WHERE `manufacturerid` = 0;

ALTER TABLE `deliveryorder` MODIFY `deliveryorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `deliveryorder` MODIFY `quoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `deliveryorder` MODIFY `salesorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `deliveryorder` MODIFY `invoiceid` int(11) NULL DEFAULT NULL;
ALTER TABLE `deliveryorder` MODIFY `contactid` int(11) NULL DEFAULT NULL;

UPDATE `deliveryorder` SET `deliveryorderid` = NULL WHERE `deliveryorderid` = 0;
UPDATE `deliveryorder` SET `quoteid` = NULL WHERE `quoteid` = 0;
UPDATE `deliveryorder` SET `salesorderid` = NULL WHERE `salesorderid` = 0;
UPDATE `deliveryorder` SET `invoiceid` = NULL WHERE `invoiceid` = 0;
UPDATE `deliveryorder` SET `contactid` = NULL WHERE `contactid` = 0;

ALTER TABLE `deliveryorderpos` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `deliveryorderpos` MODIFY `masterid` int(11) NULL DEFAULT NULL;
ALTER TABLE `deliveryorderpos` MODIFY `manufacturerid` int(11) NULL DEFAULT NULL;

UPDATE `deliveryorderpos` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `deliveryorderpos` SET `masterid` = NULL WHERE `masterid` = 0;
UPDATE `deliveryorderpos` SET `manufacturerid` = NULL WHERE `manufacturerid` = 0;

ALTER TABLE `ledger` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `ledger` MODIFY `contactid` int(11) NULL DEFAULT NULL;
ALTER TABLE `ledger` MODIFY `quoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `ledger` MODIFY `salesorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `ledger` MODIFY `invoiceid` int(11) NULL DEFAULT NULL;
ALTER TABLE `ledger` MODIFY `creditnoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `ledger` MODIFY `deliveryorderid` int(11) NULL DEFAULT NULL;

UPDATE `ledger` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `ledger` SET `contactid` = NULL WHERE `contactid` = 0;
UPDATE `ledger` SET `quoteid` = NULL WHERE `quoteid` = 0;
UPDATE `ledger` SET `salesorderid` = NULL WHERE `salesorderid` = 0;
UPDATE `ledger` SET `invoiceid` = NULL WHERE `invoiceid` = 0;
UPDATE `ledger` SET `creditnoteid` = NULL WHERE `creditnoteid` = 0;
UPDATE `ledger` SET `deliveryorderid` = NULL WHERE `deliveryorderid` = 0;

ALTER TABLE `invoice` MODIFY `invoiceid` int(11) NULL DEFAULT NULL;
ALTER TABLE `invoice` MODIFY `quoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `invoice` MODIFY `salesorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `invoice` MODIFY `deliveryorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `invoice` MODIFY `contactid` int(11) NULL DEFAULT NULL;

UPDATE `invoice` SET `invoiceid` = NULL WHERE `invoiceid` = 0;
UPDATE `invoice` SET `quoteid` = NULL WHERE `quoteid` = 0;
UPDATE `invoice` SET `salesorderid` = NULL WHERE `salesorderid` = 0;
UPDATE `invoice` SET `deliveryorderid` = NULL WHERE `deliveryorderid` = 0;
UPDATE `invoice` SET `contactid` = NULL WHERE `contactid` = 0;

ALTER TABLE `invoicepos` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `invoicepos` MODIFY `masterid` int(11) NULL DEFAULT NULL;
ALTER TABLE `invoicepos` MODIFY `manufacturerid` int(11) NULL DEFAULT NULL;

UPDATE `invoicepos` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `invoicepos` SET `masterid` = NULL WHERE `masterid` = 0;
UPDATE `invoicepos` SET `manufacturerid` = NULL WHERE `manufacturerid` = 0;

ALTER TABLE `item` MODIFY `shopcatid` int(11) NULL DEFAULT NULL;
ALTER TABLE `item` MODIFY `ebaycatid` int(11) NULL DEFAULT NULL;
ALTER TABLE `item` MODIFY `amazoncatid` int(11) NULL DEFAULT NULL;
ALTER TABLE `item` MODIFY `warehouseid` int(11) NULL DEFAULT NULL;
ALTER TABLE `item` MODIFY `taxid` int(11) NULL DEFAULT NULL;
ALTER TABLE `item` MODIFY `uomid` int(11) NULL DEFAULT NULL;
ALTER TABLE `item` MODIFY `manufacturerid` int(11) NULL DEFAULT NULL;

UPDATE `item` SET `shopcatid` = NULL WHERE `shopcatid` = 0;
UPDATE `item` SET `ebaycatid` = NULL WHERE `ebaycatid` = 0;
UPDATE `item` SET `amazoncatid` = NULL WHERE `amazoncatid` = 0;
UPDATE `item` SET `warehouseid` = NULL WHERE `warehouseid` = 0;
UPDATE `item` SET `taxid` = NULL WHERE `taxid` = 0;
UPDATE `item` SET `uomid` = NULL WHERE `uomid` = 0;
UPDATE `item` SET `manufacturerid` = NULL WHERE `manufacturerid` = 0;

ALTER TABLE `itematr` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `itematr` MODIFY `manufacturerid` int(11) NULL DEFAULT NULL;
ALTER TABLE `itemopt` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `itemopt` MODIFY `manufacturerid` int(11) NULL DEFAULT NULL;

UPDATE `itematr` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `itematr` SET `manufacturerid` = NULL WHERE `manufacturerid` = 0;
UPDATE `itemopt` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `itemopt` SET `manufacturerid` = NULL WHERE `manufacturerid` = 0;

ALTER TABLE `pricerule` MODIFY `itemcatid` int(11) NULL DEFAULT NULL;
ALTER TABLE `pricerule` MODIFY `contactcatid` int(11) NULL DEFAULT NULL;
ALTER TABLE `pricerulepos` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `pricerulepos` MODIFY `masterid` int(11) NULL DEFAULT NULL;

UPDATE `pricerule` SET `itemcatid` = NULL WHERE `itemcatid` = 0;
UPDATE `pricerule` SET `contactcatid` = NULL WHERE `contactcatid` = 0;
UPDATE `pricerulepos` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `pricerulepos` SET `masterid` = NULL WHERE `masterid` = 0;

ALTER TABLE `process` MODIFY `quoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `process` MODIFY `salesorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `process` MODIFY `invoiceid` int(11) NULL DEFAULT NULL;
ALTER TABLE `process` MODIFY `prepaymentinvoiceid` int(11) NULL DEFAULT NULL;
ALTER TABLE `process` MODIFY `deliveryorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `process` MODIFY `creditnoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `process` MODIFY `purchaseorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `process` MODIFY `contactid` int(11) NULL DEFAULT NULL;
ALTER TABLE `process` MODIFY `supplierid` int(11) NULL DEFAULT NULL;

UPDATE `process` SET `quoteid` = NULL WHERE `quoteid` = 0;
UPDATE `process` SET `salesorderid` = NULL WHERE `salesorderid` = 0;
UPDATE `process` SET `invoiceid` = NULL WHERE `invoiceid` = 0;
UPDATE `process` SET `prepaymentinvoiceid` = NULL WHERE `prepaymentinvoiceid` = 0;
UPDATE `process` SET `deliveryorderid` = NULL WHERE `deliveryorderid` = 0;
UPDATE `process` SET `creditnoteid` = NULL WHERE `creditnoteid` = 0;
UPDATE `process` SET `purchaseorderid` = NULL WHERE `purchaseorderid` = 0;
UPDATE `process` SET `contactid` = NULL WHERE `contactid` = 0;
UPDATE `process` SET `supplierid` = NULL WHERE `supplierid` = 0;

ALTER TABLE `processpos` MODIFY `deliveryorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `processpos` MODIFY `purchaseorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `processpos` MODIFY `supplierid` int(11) NULL DEFAULT NULL;
ALTER TABLE `processpos` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `processpos` MODIFY `masterid` int(11) NULL DEFAULT NULL;
ALTER TABLE `processpos` MODIFY `manufacturerid` int(11) NULL DEFAULT NULL;

UPDATE `processpos` SET `deliveryorderid` = NULL WHERE `deliveryorderid` = 0;
UPDATE `processpos` SET `purchaseorderid` = NULL WHERE `purchaseorderid` = 0;
UPDATE `processpos` SET `supplierid` = NULL WHERE `supplierid` = 0;
UPDATE `processpos` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `processpos` SET `masterid` = NULL WHERE `masterid` = 0;
UPDATE `processpos` SET `manufacturerid` = NULL WHERE `manufacturerid` = 0;

ALTER TABLE `purchaseorder` MODIFY `purchaseorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `purchaseorder` MODIFY `quoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `purchaseorder` MODIFY `salesorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `purchaseorder` MODIFY `invoiceid` int(11) NULL DEFAULT NULL;
ALTER TABLE `purchaseorder` MODIFY `quoterequestid` int(11) NULL DEFAULT NULL;
ALTER TABLE `purchaseorder` MODIFY `contactid` int(11) NULL DEFAULT NULL;

UPDATE `purchaseorder` SET `purchaseorderid` = NULL WHERE `purchaseorderid` = 0;
UPDATE `purchaseorder` SET `quoteid` = NULL WHERE `quoteid` = 0;
UPDATE `purchaseorder` SET `salesorderid` = NULL WHERE `salesorderid` = 0;
UPDATE `purchaseorder` SET `invoiceid` = NULL WHERE `invoiceid` = 0;
UPDATE `purchaseorder` SET `quoterequestid` = NULL WHERE `quoterequestid` = 0;
UPDATE `purchaseorder` SET `contactid` = NULL WHERE `contactid` = 0;

ALTER TABLE `purchaseorderpos` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `purchaseorderpos` MODIFY `masterid` int(11) NULL DEFAULT NULL;
ALTER TABLE `purchaseorderpos` MODIFY `manufacturerid` int(11) NULL DEFAULT NULL;

UPDATE `purchaseorderpos` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `purchaseorderpos` SET `masterid` = NULL WHERE `masterid` = 0;
UPDATE `purchaseorderpos` SET `manufacturerid` = NULL WHERE `manufacturerid` = 0;

ALTER TABLE `quote` MODIFY `quoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `quote` MODIFY `contactid` int(11) NULL DEFAULT NULL;

UPDATE `quote` SET `quoteid` = NULL WHERE `quoteid` = 0;
UPDATE `quote` SET `contactid` = NULL WHERE `contactid` = 0;

ALTER TABLE `quotepos` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `quotepos` MODIFY `masterid` int(11) NULL DEFAULT NULL;
ALTER TABLE `quotepos` MODIFY `manufacturerid` int(11) NULL DEFAULT NULL;

UPDATE `quotepos` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `quotepos` SET `masterid` = NULL WHERE `masterid` = 0;
UPDATE `quotepos` SET `manufacturerid` = NULL WHERE `manufacturerid` = 0;

ALTER TABLE `quoterequest` MODIFY `quoterequestid` int(11) NULL DEFAULT NULL;
ALTER TABLE `quoterequest` MODIFY `quoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `quoterequest` MODIFY `salesorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `quoterequest` MODIFY `invoiceid` int(11) NULL DEFAULT NULL;
ALTER TABLE `quoterequest` MODIFY `contactid` int(11) NULL DEFAULT NULL;

UPDATE `quoterequest` SET `quoterequestid` = NULL WHERE `quoterequestid` = 0;
UPDATE `quoterequest` SET `quoteid` = NULL WHERE `quoteid` = 0;
UPDATE `quoterequest` SET `salesorderid` = NULL WHERE `salesorderid` = 0;
UPDATE `quoterequest` SET `invoiceid` = NULL WHERE `invoiceid` = 0;
UPDATE `quoterequest` SET `contactid` = NULL WHERE `contactid` = 0;

ALTER TABLE `quoterequestpos` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `quoterequestpos` MODIFY `masterid` int(11) NULL DEFAULT NULL;
ALTER TABLE `quoterequestpos` MODIFY `manufacturerid` int(11) NULL DEFAULT NULL;

UPDATE `quoterequestpos` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `quoterequestpos` SET `masterid` = NULL WHERE `masterid` = 0;
UPDATE `quoterequestpos` SET `manufacturerid` = NULL WHERE `manufacturerid` = 0;

ALTER TABLE `reminder` MODIFY `reminderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `reminder` MODIFY `invoiceid` int(11) NULL DEFAULT NULL;
ALTER TABLE `reminder` MODIFY `contactid` int(11) NULL DEFAULT NULL;

UPDATE `reminder` SET `reminderid` = NULL WHERE `reminderid` = 0;
UPDATE `reminder` SET `invoiceid` = NULL WHERE `invoiceid` = 0;
UPDATE `reminder` SET `contactid` = NULL WHERE `contactid` = 0;

ALTER TABLE `reminderpos` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `reminderpos` MODIFY `masterid` int(11) NULL DEFAULT NULL;
ALTER TABLE `reminderpos` MODIFY `manufacturerid` int(11) NULL DEFAULT NULL;

UPDATE `reminderpos` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `reminderpos` SET `masterid` = NULL WHERE `masterid` = 0;
UPDATE `reminderpos` SET `manufacturerid` = NULL WHERE `manufacturerid` = 0;

ALTER TABLE `salesorder` MODIFY `salesorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `salesorder` MODIFY `quoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `salesorder` MODIFY `contactid` int(11) NULL DEFAULT NULL;

UPDATE `salesorder` SET `salesorderid` = NULL WHERE `salesorderid` = 0;
UPDATE `salesorder` SET `quoteid` = NULL WHERE `quoteid` = 0;
UPDATE `salesorder` SET `contactid` = NULL WHERE `contactid` = 0;

ALTER TABLE `salesorderpos` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `salesorderpos` MODIFY `masterid` int(11) NULL DEFAULT NULL;
ALTER TABLE `salesorderpos` MODIFY `manufacturerid` int(11) NULL DEFAULT NULL;

UPDATE `salesorderpos` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `salesorderpos` SET `masterid` = NULL WHERE `masterid` = 0;
UPDATE `salesorderpos` SET `manufacturerid` = NULL WHERE `manufacturerid` = 0;

ALTER TABLE `task` MODIFY `quoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `task` MODIFY `salesorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `task` MODIFY `invoiceid` int(11) NULL DEFAULT NULL;
ALTER TABLE `task` MODIFY `prepaymentinvoiceid` int(11) NULL DEFAULT NULL;
ALTER TABLE `task` MODIFY `deliveryorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `task` MODIFY `creditnoteid` int(11) NULL DEFAULT NULL;
ALTER TABLE `task` MODIFY `purchaseorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `task` MODIFY `contactid` int(11) NULL DEFAULT NULL;
ALTER TABLE `task` MODIFY `supplierid` int(11) NULL DEFAULT NULL;

UPDATE `task` SET `quoteid` = NULL WHERE `quoteid` = 0;
UPDATE `task` SET `salesorderid` = NULL WHERE `salesorderid` = 0;
UPDATE `task` SET `invoiceid` = NULL WHERE `invoiceid` = 0;
UPDATE `task` SET `prepaymentinvoiceid` = NULL WHERE `prepaymentinvoiceid` = 0;
UPDATE `task` SET `deliveryorderid` = NULL WHERE `deliveryorderid` = 0;
UPDATE `task` SET `creditnoteid` = NULL WHERE `creditnoteid` = 0;
UPDATE `task` SET `purchaseorderid` = NULL WHERE `purchaseorderid` = 0;
UPDATE `task` SET `contactid` = NULL WHERE `contactid` = 0;
UPDATE `task` SET `supplierid` = NULL WHERE `supplierid` = 0;

ALTER TABLE `taskpos` MODIFY `deliveryorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `taskpos` MODIFY `purchaseorderid` int(11) NULL DEFAULT NULL;
ALTER TABLE `taskpos` MODIFY `supplierid` int(11) NULL DEFAULT NULL;
ALTER TABLE `taskpos` MODIFY `itemid` int(11) NULL DEFAULT NULL;
ALTER TABLE `taskpos` MODIFY `masterid` int(11) NULL DEFAULT NULL;

UPDATE `taskpos` SET `deliveryorderid` = NULL WHERE `deliveryorderid` = 0;
UPDATE `taskpos` SET `purchaseorderid` = NULL WHERE `purchaseorderid` = 0;
UPDATE `taskpos` SET `supplierid` = NULL WHERE `supplierid` = 0;
UPDATE `taskpos` SET `itemid` = NULL WHERE `itemid` = 0;
UPDATE `taskpos` SET `masterid` = NULL WHERE `masterid` = 0;
