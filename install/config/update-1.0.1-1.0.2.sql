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

CREATE TABLE IF NOT EXISTS `task` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quoteid` int(11) NOT NULL DEFAULT 0,
  `salesorderid` int(11) NOT NULL DEFAULT 0,
  `invoiceid` int(11) NOT NULL DEFAULT 0,
  `prepaymentinvoiceid` int(11) NOT NULL DEFAULT 0,
  `deliveryorderid` int(11) NOT NULL DEFAULT 0,
  `creditnoteid` int(11) NOT NULL DEFAULT 0,
  `purchaseorderid` int(11) NOT NULL DEFAULT 0,
  `customerid` int(11) NOT NULL DEFAULT 0,
  `supplierid` int(11) NOT NULL DEFAULT 0,
  `title` varchar(255) DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT 0,
  `startdate` date DEFAULT NULL,
  `duedate` date DEFAULT NULL,
  `reminder` tinyint(1) NOT NULL DEFAULT 0,
  `remindertype` varchar(255) DEFAULT NULL,
  `remindertime` datetime DEFAULT NULL,
  `description` text DEFAULT NULL,
  `info` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `header` text DEFAULT NULL,
  `footer` text DEFAULT NULL,
  `vatin` varchar(255) DEFAULT NULL,
  `taskdate` date DEFAULT NULL,
  `salesorderdate` date DEFAULT NULL,
  `invoicedate` date DEFAULT NULL,
  `invoicetotal` decimal(12,4) DEFAULT NULL,
  `prepaymentinvoicedate` date DEFAULT NULL,
  `deliveryorderdate` date DEFAULT NULL,
  `creditnotedate` date DEFAULT NULL,
  `purchaseorderdate` date DEFAULT NULL,
  `paymentmethod` varchar(255) DEFAULT NULL,
  `shippingmethod` varchar(255) DEFAULT NULL,
  `shipmentnumber` varchar(255) DEFAULT NULL,
  `shipmentdate` date DEFAULT NULL,
  `deliverydate` date DEFAULT NULL,
  `deliverystatus` varchar(255) DEFAULT NULL,
  `itemtype` varchar(255) DEFAULT NULL,
  `suppliername` varchar(255) DEFAULT NULL,
  `supplierordered` tinyint(1) NOT NULL DEFAULT 0,
  `suppliersalesorderid` varchar(255) DEFAULT NULL,
  `suppliersalesorderdate` date DEFAULT NULL,
  `supplierinvoiceid` varchar(255) DEFAULT NULL,
  `supplierinvoicedate` date DEFAULT NULL,
  `supplierinvoicetotal` decimal(12,4) DEFAULT NULL,
  `supplierpaymentdate` date DEFAULT NULL,
  `supplierdeliverydate` date DEFAULT NULL,
  `supplierorderstatus` varchar(255) DEFAULT NULL,
  `servicedate` date DEFAULT NULL,
  `servicecompleted` tinyint(1) NOT NULL DEFAULT 0,
  `billingname1` varchar(255) DEFAULT NULL,
  `billingname2` varchar(255) DEFAULT NULL,
  `billingdepartment` varchar(255) DEFAULT NULL,
  `billingstreet` text DEFAULT NULL,
  `billingpostcode` varchar(255) DEFAULT NULL,
  `billingcity` varchar(255) DEFAULT NULL,
  `billingcountry` varchar(255) DEFAULT NULL,
  `shippingname1` varchar(255) DEFAULT NULL,
  `shippingname2` varchar(255) DEFAULT NULL,
  `shippingdepartment` varchar(255) DEFAULT NULL,
  `shippingstreet` text DEFAULT NULL,
  `shippingpostcode` varchar(255) DEFAULT NULL,
  `shippingcity` varchar(255) DEFAULT NULL,
  `shippingcountry` varchar(255) DEFAULT NULL,
  `shippingphone` varchar(255) DEFAULT NULL,
  `subtotal` decimal(12,4) DEFAULT NULL,
  `taxes` decimal(12,4) DEFAULT NULL,
  `total` decimal(12,4) DEFAULT NULL,
  `currency` varchar(255) DEFAULT NULL,
  `paymentdate` date DEFAULT NULL,
  `prepayment` tinyint(1) NOT NULL DEFAULT 0,
  `prepaymenttotal` decimal(12,4) DEFAULT NULL,
  `prepaymentdate` date DEFAULT NULL,
  `paymentstatus` varchar(255) DEFAULT NULL,
  `creditnote` tinyint(1) NOT NULL DEFAULT 0,
  `creditnotetotal` decimal(12,4) DEFAULT NULL,
  `editpositionsseparately` tinyint(1) NOT NULL DEFAULT 0,
  `taxfree` tinyint(1) NOT NULL DEFAULT 0,
  `state` int(11) NOT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `cancelled` tinyint(1) NOT NULL DEFAULT 0,
  `contactperson` varchar(255) DEFAULT NULL,
  `responsible` int(11) NOT NULL DEFAULT 0,
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

CREATE TABLE IF NOT EXISTS `taskpos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `deliveryorderid` int(11) DEFAULT 0,
  `purchaseorderid` int(11) DEFAULT 0,
  `supplierid` int(11) DEFAULT 0,
  `itemid` int(11) DEFAULT 0,
  `masterid` int(11) NOT NULL DEFAULT 0,
  `possetid` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `deliveryorderdate` date DEFAULT NULL,
  `itemtype` varchar(255) DEFAULT NULL,
  `purchaseorderdate` date DEFAULT NULL,
  `shippingmethod` varchar(255) DEFAULT NULL,
  `shipmentnumber` varchar(255) DEFAULT NULL,
  `shipmentdate` date DEFAULT NULL,
  `deliverydate` date DEFAULT NULL,
  `deliverystatus` varchar(255) DEFAULT NULL,
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
  `suppliername` varchar(255) DEFAULT NULL,
  `suppliersalesorderid` varchar(255) DEFAULT NULL,
  `suppliersalesorderdate` date DEFAULT NULL,
  `supplierinvoiceid` varchar(255) DEFAULT NULL,
  `supplierinvoicedate` date DEFAULT NULL,
  `supplierinvoicetotal` decimal(12,4) DEFAULT NULL,
  `supplierpaymentdate` date DEFAULT NULL,
  `supplierdeliverydate` date DEFAULT NULL,
  `supplierorderstatus` varchar(255) DEFAULT NULL,
  `servicedate` date DEFAULT NULL,
  `serviceexecutedby` varchar(255) DEFAULT NULL,
  `servicecompleted` tinyint(1) NOT NULL DEFAULT 0,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `taskposset` (
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

ALTER TABLE `emailmessage` ADD `response` TEXT NULL DEFAULT NULL AFTER `messagesentby`;
ALTER TABLE `email` ADD `password` varchar(255) NOT NULL AFTER `email`;
ALTER TABLE `user` ADD `emailsignature` TEXT NULL DEFAULT NULL AFTER `emailsender`;
ALTER TABLE `client` ADD `token` varchar(255) DEFAULT NULL AFTER `id`;
UPDATE `client` SET `token` = LEFT(MD5(RAND()), 16) WHERE 1;
