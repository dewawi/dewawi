SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `address`;
CREATE TABLE IF NOT EXISTS `address` (
  `id` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `type` varchar(255) NOT NULL,
  `name1` varchar(255) NOT NULL,
  `name2` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `street` text NOT NULL,
  `postcode` varchar(12) NOT NULL,
  `city` varchar(255) NOT NULL,
  `country` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `archive`;
CREATE TABLE IF NOT EXISTS `archive` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` varchar(255) NOT NULL,
  `data` text NOT NULL,
  `clientid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `category`;
CREATE TABLE IF NOT EXISTS `category` (
  `id` int(11) NOT NULL,
  `parentid` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `client`;
CREATE TABLE IF NOT EXISTS `client` (
  `id` int(11) NOT NULL,
  `company` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `postcode` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `country` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `website` varchar(255) NOT NULL,
  `language` varchar(255) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

INSERT INTO `client` (`id`, `company`, `address`, `postcode`, `city`, `country`, `email`, `website`, `language`, `created`, `createdby`, `modified`, `modifiedby`, `locked`, `lockedtime`) VALUES
(1, 'Company', '', '', '', '', '', '', '', '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0, 0, '0000-00-00 00:00:00');

DROP TABLE IF EXISTS `config`;
CREATE TABLE IF NOT EXISTS `config` (
  `id` int(11) NOT NULL,
  `timezone` varchar(255) NOT NULL,
  `language` varchar(255) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

INSERT INTO `config` (`id`, `timezone`, `language`, `created`, `createdby`, `modified`, `modifiedby`, `locked`, `lockedtime`) VALUES
(1, 'Europe/Berlin', 'de_DE', '0000-00-00 00:00:00', 0, '2015-11-26 11:21:28', 1, 1, '2015-11-26 11:21:28');

DROP TABLE IF EXISTS `contact`;
CREATE TABLE IF NOT EXISTS `contact` (
  `id` int(11) NOT NULL,
  `catid` int(11) NOT NULL,
  `type` varchar(255) NOT NULL,
  `name1` varchar(255) NOT NULL,
  `name2` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `info` text NOT NULL,
  `vatin` varchar(255) NOT NULL,
  `taxfree` tinyint(3) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `country`;
CREATE TABLE IF NOT EXISTS `country` (
  `id` int(11) NOT NULL,
  `code` varchar(2) NOT NULL,
  `name` varchar(255) NOT NULL,
  `language` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `creditnote`;
CREATE TABLE IF NOT EXISTS `creditnote` (
  `id` int(11) NOT NULL,
  `creditnoteid` int(11) NOT NULL,
  `opportunityid` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `info` text NOT NULL,
  `header` text NOT NULL,
  `footer` text NOT NULL,
  `vatin` varchar(255) NOT NULL,
  `creditnotedate` date NOT NULL,
  `orderdate` date NOT NULL,
  `deliverydate` date NOT NULL,
  `paymentmethod` varchar(255) NOT NULL,
  `shippingmethod` varchar(255) NOT NULL,
  `billingname1` varchar(255) NOT NULL,
  `billingname2` varchar(255) NOT NULL,
  `billingdepartment` varchar(255) NOT NULL,
  `billingstreet` text NOT NULL,
  `billingpostcode` varchar(255) NOT NULL,
  `billingcity` varchar(255) NOT NULL,
  `billingcountry` varchar(255) NOT NULL,
  `shippingname1` varchar(255) NOT NULL,
  `shippingname2` varchar(255) NOT NULL,
  `shippingdepartment` varchar(255) NOT NULL,
  `shippingstreet` text NOT NULL,
  `shippingpostcode` varchar(255) NOT NULL,
  `shippingcity` varchar(255) NOT NULL,
  `shippingcountry` varchar(255) NOT NULL,
  `shippingphone` varchar(255) NOT NULL,
  `subtotal` decimal(15,4) NOT NULL,
  `taxes` decimal(15,4) NOT NULL,
  `total` decimal(15,4) NOT NULL,
  `taxfree` tinyint(3) NOT NULL,
  `state` tinyint(3) NOT NULL,
  `completed` tinyint(3) NOT NULL,
  `cancelled` tinyint(3) NOT NULL,
  `contactperson` varchar(255) NOT NULL,
  `templateid` int(11) NOT NULL,
  `language` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `creditnotepos`;
CREATE TABLE IF NOT EXISTS `creditnotepos` (
  `id` int(11) NOT NULL,
  `creditnoteid` int(11) NOT NULL,
  `itemid` int(11) NOT NULL,
  `sku` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(15,4) NOT NULL,
  `taxrate` decimal(15,4) NOT NULL,
  `quantity` decimal(9,4) DEFAULT NULL,
  `total` decimal(15,4) NOT NULL,
  `uom` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `deliveryorder`;
CREATE TABLE IF NOT EXISTS `deliveryorder` (
  `id` int(11) NOT NULL,
  `deliveryorderid` int(11) NOT NULL,
  `opportunityid` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `info` text NOT NULL,
  `header` text NOT NULL,
  `footer` text NOT NULL,
  `vatin` varchar(255) NOT NULL,
  `deliveryorderdate` date NOT NULL,
  `orderdate` date NOT NULL,
  `deliverydate` date NOT NULL,
  `paymentmethod` varchar(255) NOT NULL,
  `shippingmethod` varchar(255) NOT NULL,
  `billingname1` varchar(255) NOT NULL,
  `billingname2` varchar(255) NOT NULL,
  `billingdepartment` varchar(255) NOT NULL,
  `billingstreet` text NOT NULL,
  `billingpostcode` varchar(255) NOT NULL,
  `billingcity` varchar(255) NOT NULL,
  `billingcountry` varchar(255) NOT NULL,
  `shippingname1` varchar(255) NOT NULL,
  `shippingname2` varchar(255) NOT NULL,
  `shippingdepartment` varchar(255) NOT NULL,
  `shippingstreet` text NOT NULL,
  `shippingpostcode` varchar(255) NOT NULL,
  `shippingcity` varchar(255) NOT NULL,
  `shippingcountry` varchar(255) NOT NULL,
  `shippingphone` varchar(255) NOT NULL,
  `subtotal` decimal(15,4) NOT NULL,
  `taxes` decimal(15,4) NOT NULL,
  `total` decimal(15,4) NOT NULL,
  `taxfree` tinyint(3) NOT NULL,
  `state` tinyint(3) NOT NULL,
  `completed` tinyint(3) NOT NULL,
  `cancelled` tinyint(3) NOT NULL,
  `contactperson` varchar(255) NOT NULL,
  `templateid` int(11) NOT NULL,
  `language` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `deliveryorderpos`;
CREATE TABLE IF NOT EXISTS `deliveryorderpos` (
  `id` int(11) NOT NULL,
  `deliveryorderid` int(11) NOT NULL,
  `itemid` int(11) NOT NULL,
  `sku` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(15,4) NOT NULL,
  `taxrate` decimal(15,4) NOT NULL,
  `quantity` decimal(9,4) DEFAULT NULL,
  `total` decimal(15,4) NOT NULL,
  `uom` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `documentrelation`;
CREATE TABLE IF NOT EXISTS `documentrelation` (
  `id` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `documentid` int(11) NOT NULL,
  `module` varchar(255) NOT NULL,
  `controller` varchar(255) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `ebayhistory`;
CREATE TABLE IF NOT EXISTS `ebayhistory` (
  `id` int(11) NOT NULL,
  `userid` varchar(255) NOT NULL,
  `orderid` varchar(255) NOT NULL,
  `contactid` int(11) NOT NULL,
  `invoiceid` int(11) NOT NULL,
  `clientid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `ebayuser`;
CREATE TABLE IF NOT EXISTS `ebayuser` (
  `id` int(11) NOT NULL,
  `userid` varchar(255) NOT NULL,
  `catid` int(11) NOT NULL,
  `templateid` int(11) NOT NULL,
  `language` varchar(255) NOT NULL,
  `token` text NOT NULL,
  `production` int(11) NOT NULL,
  `compatability` int(11) NOT NULL,
  `siteid` int(11) NOT NULL,
  `devid` varchar(255) NOT NULL,
  `appid` varchar(255) NOT NULL,
  `certid` varchar(255) NOT NULL,
  `serverurl` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `email`;
CREATE TABLE IF NOT EXISTS `email` (
  `id` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `footer`;
CREATE TABLE IF NOT EXISTS `footer` (
  `id` int(11) NOT NULL,
  `templateid` int(11) NOT NULL,
  `column` int(11) NOT NULL,
  `text` text NOT NULL,
  `width` int(11) NOT NULL,
  `clientid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `internet`;
CREATE TABLE IF NOT EXISTS `internet` (
  `id` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `internet` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `invoice`;
CREATE TABLE IF NOT EXISTS `invoice` (
  `id` int(11) NOT NULL,
  `invoiceid` int(11) NOT NULL,
  `opportunityid` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `info` text NOT NULL,
  `header` text NOT NULL,
  `footer` text NOT NULL,
  `vatin` varchar(255) NOT NULL,
  `invoicedate` date NOT NULL,
  `orderdate` date NOT NULL,
  `deliverydate` date NOT NULL,
  `paymentmethod` varchar(255) NOT NULL,
  `shippingmethod` varchar(255) NOT NULL,
  `billingname1` varchar(255) NOT NULL,
  `billingname2` varchar(255) NOT NULL,
  `billingdepartment` varchar(255) NOT NULL,
  `billingstreet` text NOT NULL,
  `billingpostcode` varchar(255) NOT NULL,
  `billingcity` varchar(255) NOT NULL,
  `billingcountry` varchar(255) NOT NULL,
  `shippingname1` varchar(255) NOT NULL,
  `shippingname2` varchar(255) NOT NULL,
  `shippingdepartment` varchar(255) NOT NULL,
  `shippingstreet` text NOT NULL,
  `shippingpostcode` varchar(255) NOT NULL,
  `shippingcity` varchar(255) NOT NULL,
  `shippingcountry` varchar(255) NOT NULL,
  `shippingphone` varchar(255) NOT NULL,
  `subtotal` decimal(15,4) NOT NULL,
  `taxes` decimal(15,4) NOT NULL,
  `total` decimal(15,4) NOT NULL,
  `taxfree` tinyint(3) NOT NULL,
  `state` tinyint(3) NOT NULL,
  `completed` tinyint(3) NOT NULL,
  `cancelled` tinyint(3) NOT NULL,
  `contactperson` varchar(255) NOT NULL,
  `ebayorderid` varchar(255) DEFAULT NULL,
  `templateid` int(11) NOT NULL,
  `language` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `invoicepos`;
CREATE TABLE IF NOT EXISTS `invoicepos` (
  `id` int(11) NOT NULL,
  `invoiceid` int(11) NOT NULL,
  `itemid` int(11) NOT NULL,
  `sku` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(15,4) NOT NULL,
  `taxrate` decimal(15,4) NOT NULL,
  `quantity` decimal(9,4) DEFAULT NULL,
  `total` decimal(15,4) NOT NULL,
  `uom` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `item`;
CREATE TABLE IF NOT EXISTS `item` (
  `id` int(11) NOT NULL,
  `catid` int(11) NOT NULL,
  `sku` varchar(255) NOT NULL,
  `barcode` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `info` text NOT NULL,
  `quantity` decimal(10,0) NOT NULL,
  `weight` decimal(15,4) NOT NULL,
  `cost` decimal(15,4) NOT NULL,
  `price` decimal(15,4) NOT NULL,
  `margin` decimal(15,4) NOT NULL,
  `taxid` int(11) NOT NULL,
  `uomid` int(11) NOT NULL,
  `manufacturerid` int(11) NOT NULL,
  `manufacturersku` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `language`;
CREATE TABLE IF NOT EXISTS `language` (
  `id` int(11) NOT NULL,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `language` (`id`, `code`, `name`, `clientid`, `created`, `createdby`, `modified`, `modifiedby`, `locked`, `lockedtime`) VALUES
(1, 'de_DE', 'Deutsch', 100, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0, 0, '0000-00-00 00:00:00');

DROP TABLE IF EXISTS `magentocustomer`;
CREATE TABLE IF NOT EXISTS `magentocustomer` (
  `id` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `magentocustomerid` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `magentohistory`;
CREATE TABLE IF NOT EXISTS `magentohistory` (
  `id` int(11) NOT NULL,
  `magentocustomerid` varchar(255) NOT NULL,
  `orderid` varchar(255) NOT NULL,
  `contactid` int(11) NOT NULL,
  `invoiceid` int(11) NOT NULL,
  `clientid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `magentouser`;
CREATE TABLE IF NOT EXISTS `magentouser` (
  `id` int(11) NOT NULL,
  `userid` varchar(255) NOT NULL,
  `catid` int(11) NOT NULL,
  `templateid` int(11) NOT NULL,
  `language` varchar(255) NOT NULL,
  `key` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `manufacturer`;
CREATE TABLE IF NOT EXISTS `manufacturer` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `module`;
CREATE TABLE IF NOT EXISTS `module` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `menu` text NOT NULL,
  `ordering` int(11) NOT NULL,
  `active` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8;

INSERT INTO `module` (`id`, `name`, `menu`, `ordering`, `active`, `created`, `createdby`, `modified`, `modifiedby`, `locked`, `lockedtime`) VALUES
(1, 'Admin', '', 1, 1, '0000-00-00 00:00:00', 0, '2015-11-24 14:40:56', 1, 0, '2015-11-24 14:40:56'),
(2, 'Contacts', '{\n "CONTACTS": {\n  "title":"CONTACTS",\n  "module":"contacts",\n  "controller":"contact",\n  "action":"index"\n }\n}', 1, 1, '2015-11-21 16:48:39', 1, '2015-11-24 14:40:57', 1, 0, '2015-11-24 14:40:57'),
(3, 'Items', '{\n "ITEMS": {\n  "title":"ITEMS",\n  "module":"items",\n  "controller":"item",\n  "action":"index"\n }\n}', 1, 1, '2015-11-21 16:48:52', 1, '2015-11-24 14:40:50', 1, 0, '2015-11-24 14:40:50'),
(4, 'Processes', '{\n "PROCESSES": {\n  "title":"PROCESSES",\n  "module":"processes",\n  "controller":"process",\n  "action":"index"\n }\n}', 1, 1, '2015-11-21 16:49:01', 1, '2015-11-24 14:40:45', 1, 0, '2015-11-24 14:40:45'),
(5, 'Sales', '{\n "SALES": {\n  "title":"SALES",\n  "module":"sales",\n  "childs": {\n   "10": {\n    "title":"QUOTES",\n    "module":"sales",\n    "controller":"quote",\n    "action":"index"\n   },\n   "20": {\n    "title":"SALES_ORDERS",\n    "module":"sales",\n    "controller":"salesorder",\n    "action":"index"\n   },\n   "30": {\n    "title":"INVOICES",\n    "module":"sales",\n    "controller":"invoice",\n    "action":"index"\n   },\n   "40": {\n    "title":"DELIVERY_ORDERS",\n    "module":"sales",\n    "controller":"deliveryorder",\n    "action":"index"\n   },\n   "50": {\n    "title":"CREDIT_NOTES",\n    "module":"sales",\n    "controller":"creditnote",\n    "action":"index"\n   }\n  }\n }\n}', 1, 1, '2015-11-21 16:52:09', 1, '2015-11-24 14:40:04', 1, 0, '2015-11-24 14:40:04'),
(6, 'Purchases', '{\n "PURCHASES": {\n  "title":"PURCHASES",\n  "module":"purchases",\n  "childs": {\n   "10": {\n    "title":"QUOTE_REQUESTS",\n    "module":"purchases",\n    "controller":"quoterequest",\n    "action":"index"\n   },\n   "20": {\n    "title":"PURCHASE_ORDERS",\n    "module":"purchases",\n    "controller":"purchaseorder",\n    "action":"index"\n   }\n  }\n }\n}', 1, 1, '2015-11-21 17:05:52', 1, '2015-11-24 14:39:48', 1, 0, '2015-11-24 14:39:48'),
(7, 'Statistics', '{\n "STATISTICS": {\n  "title":"STATISTICS",\n  "module":"statistics",\n  "controller":"index",\n  "action":"index"\n }\n}', 1, 1, '2015-11-21 17:05:52', 1, '2015-11-24 14:40:37', 1, 0, '2015-11-24 14:40:37'),
(8, 'eBay', '{\n "EBAY": {\n  "title":"EBAY",\n  "module":"ebay",\n  "controller":"index",\n  "action":"index"\n }\n}', 1, 0, '2015-11-21 17:25:03', 1, '2015-11-24 14:39:15', 1, 0, '2015-11-24 14:39:15'),
(9, 'Magento', '{\n "MAGENTO": {\n  "title":"MAGENTO",\n  "module":"magento",\n  "childs": {\n   "10": {\n    "title":"MENU_MAGENTO_ORDERS",\n    "module":"magento",\n    "controller":"order",\n    "action":"index"\n   },\n   "20": {\n    "title":"MENU_MAGENTO_ITEMS",\n    "module":"magento",\n    "controller":"item",\n    "action":"index"\n   },\n   "30": {\n    "title":"MENU_MAGENTO_CUSTOMERS",\n    "module":"magento",\n    "controller":"customer",\n    "action":"index"\n   }\n  }\n }\n}', 1, 0, '2015-11-24 14:38:48', 1, '2015-11-24 14:38:57', 1, 0, '2015-11-24 14:38:57');

DROP TABLE IF EXISTS `paymentmethod`;
CREATE TABLE IF NOT EXISTS `paymentmethod` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `phone`;
CREATE TABLE IF NOT EXISTS `phone` (
  `id` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `type` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `process`;
CREATE TABLE IF NOT EXISTS `process` (
  `id` int(11) NOT NULL,
  `quoteid` int(11) NOT NULL,
  `salesorderid` int(11) NOT NULL,
  `invoiceid` int(11) NOT NULL,
  `prepaymentinvoiceid` int(11) NOT NULL,
  `deliveryorderid` int(11) NOT NULL,
  `creditnoteid` int(11) NOT NULL,
  `purchaseorderid` varchar(255) NOT NULL,
  `customerid` int(11) NOT NULL,
  `supplierid` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `info` text NOT NULL,
  `notes` text NOT NULL,
  `header` text NOT NULL,
  `footer` text NOT NULL,
  `vatin` varchar(255) NOT NULL,
  `processdate` date NOT NULL,
  `salesorderdate` date NOT NULL,
  `invoicedate` date NOT NULL,
  `invoicetotal` decimal(15,4) NOT NULL,
  `prepaymentinvoicedate` date NOT NULL,
  `deliveryorderdate` date NOT NULL,
  `creditnotedate` date NOT NULL,
  `purchaseorderdate` date NOT NULL,
  `paymentmethod` varchar(255) NOT NULL,
  `shippingmethod` varchar(255) NOT NULL,
  `shipmentnumber` varchar(255) NOT NULL,
  `shipmentdate` date NOT NULL,
  `deliverydate` date NOT NULL,
  `deliverystatus` varchar(255) NOT NULL,
  `itemtype` varchar(255) NOT NULL,
  `suppliername` varchar(255) NOT NULL,
  `supplierordered` tinyint(4) NOT NULL,
  `suppliersalesorderid` varchar(255) NOT NULL,
  `suppliersalesorderdate` date NOT NULL,
  `supplierinvoiceid` varchar(255) NOT NULL,
  `supplierinvoicedate` date NOT NULL,
  `supplierinvoicetotal` decimal(15,4) NOT NULL,
  `supplierpaymentdate` date NOT NULL,
  `supplierdeliverydate` date NOT NULL,
  `supplierorderstatus` varchar(255) NOT NULL,
  `servicedate` date NOT NULL,
  `servicecompleted` tinyint(4) NOT NULL,
  `billingname1` varchar(255) NOT NULL,
  `billingname2` varchar(255) NOT NULL,
  `billingdepartment` varchar(255) NOT NULL,
  `billingstreet` text NOT NULL,
  `billingpostcode` varchar(255) NOT NULL,
  `billingcity` varchar(255) NOT NULL,
  `billingcountry` varchar(255) NOT NULL,
  `shippingname1` varchar(255) NOT NULL,
  `shippingname2` varchar(255) NOT NULL,
  `shippingdepartment` varchar(255) NOT NULL,
  `shippingstreet` text NOT NULL,
  `shippingpostcode` varchar(255) NOT NULL,
  `shippingcity` varchar(255) NOT NULL,
  `shippingcountry` varchar(255) NOT NULL,
  `shippingphone` varchar(255) NOT NULL,
  `subtotal` decimal(15,4) NOT NULL,
  `taxes` decimal(15,4) NOT NULL,
  `total` decimal(15,4) NOT NULL,
  `paymentdate` date NOT NULL,
  `prepayment` tinyint(1) NOT NULL,
  `prepaymenttotal` decimal(15,4) NOT NULL,
  `prepaymentdate` date NOT NULL,
  `paymentstatus` varchar(255) NOT NULL,
  `creditnote` tinyint(1) NOT NULL,
  `creditnotetotal` decimal(15,4) NOT NULL,
  `editpositionsseparately` tinyint(4) NOT NULL,
  `taxfree` tinyint(3) NOT NULL,
  `state` tinyint(3) NOT NULL,
  `completed` tinyint(3) NOT NULL,
  `cancelled` tinyint(3) NOT NULL,
  `contactperson` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `processpos`;
CREATE TABLE IF NOT EXISTS `processpos` (
  `id` int(11) NOT NULL,
  `processid` int(11) NOT NULL,
  `deliveryorderid` int(11) NOT NULL,
  `purchaseorderid` varchar(255) NOT NULL,
  `supplierid` varchar(255) NOT NULL,
  `itemid` int(11) NOT NULL,
  `notes` text NOT NULL,
  `deliveryorderdate` date NOT NULL,
  `itemtype` varchar(255) NOT NULL,
  `purchaseorderdate` date NOT NULL,
  `shippingmethod` varchar(255) NOT NULL,
  `shipmentnumber` varchar(255) NOT NULL,
  `shipmentdate` date NOT NULL,
  `deliverydate` date NOT NULL,
  `deliverystatus` varchar(255) NOT NULL,
  `sku` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(15,4) NOT NULL,
  `taxrate` decimal(15,4) NOT NULL,
  `quantity` decimal(9,4) NOT NULL,
  `total` decimal(15,4) NOT NULL,
  `uom` varchar(255) NOT NULL,
  `suppliername` varchar(255) NOT NULL,
  `suppliersalesorderid` varchar(255) NOT NULL,
  `suppliersalesorderdate` date NOT NULL,
  `supplierinvoiceid` varchar(255) NOT NULL,
  `supplierinvoicedate` date NOT NULL,
  `supplierinvoicetotal` decimal(15,4) NOT NULL,
  `supplierpaymentdate` date NOT NULL,
  `supplierdeliverydate` date NOT NULL,
  `supplierorderstatus` varchar(255) NOT NULL,
  `servicedate` date NOT NULL,
  `serviceexecutedby` varchar(255) NOT NULL,
  `servicecompleted` tinyint(4) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `purchaseorder`;
CREATE TABLE IF NOT EXISTS `purchaseorder` (
  `id` int(11) NOT NULL,
  `purchaseorderid` int(11) NOT NULL,
  `opportunityid` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `info` text NOT NULL,
  `header` text NOT NULL,
  `footer` text NOT NULL,
  `vatin` varchar(255) NOT NULL,
  `purchaseorderdate` date NOT NULL,
  `orderdate` date NOT NULL,
  `deliverydate` date NOT NULL,
  `paymentmethod` varchar(255) NOT NULL,
  `shippingmethod` varchar(255) NOT NULL,
  `billingname1` varchar(255) NOT NULL,
  `billingname2` varchar(255) NOT NULL,
  `billingdepartment` varchar(255) NOT NULL,
  `billingstreet` text NOT NULL,
  `billingpostcode` varchar(255) NOT NULL,
  `billingcity` varchar(255) NOT NULL,
  `billingcountry` varchar(255) NOT NULL,
  `shippingname1` varchar(255) NOT NULL,
  `shippingname2` varchar(255) NOT NULL,
  `shippingdepartment` varchar(255) NOT NULL,
  `shippingstreet` text NOT NULL,
  `shippingpostcode` varchar(255) NOT NULL,
  `shippingcity` varchar(255) NOT NULL,
  `shippingcountry` varchar(255) NOT NULL,
  `shippingphone` varchar(255) NOT NULL,
  `subtotal` decimal(15,4) NOT NULL,
  `taxes` decimal(15,4) NOT NULL,
  `total` decimal(15,4) NOT NULL,
  `taxfree` tinyint(3) NOT NULL,
  `state` tinyint(3) NOT NULL,
  `completed` tinyint(3) NOT NULL,
  `cancelled` tinyint(3) NOT NULL,
  `contactperson` varchar(255) NOT NULL,
  `templateid` int(11) NOT NULL,
  `language` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `purchaseorderpos`;
CREATE TABLE IF NOT EXISTS `purchaseorderpos` (
  `id` int(11) NOT NULL,
  `purchaseorderid` int(11) NOT NULL,
  `itemid` int(11) NOT NULL,
  `sku` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(15,4) NOT NULL,
  `taxrate` decimal(15,4) NOT NULL,
  `quantity` decimal(9,4) DEFAULT NULL,
  `total` decimal(15,4) NOT NULL,
  `uom` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `quote`;
CREATE TABLE IF NOT EXISTS `quote` (
  `id` int(11) NOT NULL,
  `quoteid` int(11) NOT NULL,
  `opportunityid` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `info` text NOT NULL,
  `header` text NOT NULL,
  `footer` text NOT NULL,
  `vatin` varchar(255) NOT NULL,
  `quotedate` date NOT NULL,
  `orderdate` date NOT NULL,
  `deliverydate` date NOT NULL,
  `paymentmethod` varchar(255) NOT NULL,
  `shippingmethod` varchar(255) NOT NULL,
  `billingname1` varchar(255) NOT NULL,
  `billingname2` varchar(255) NOT NULL,
  `billingdepartment` varchar(255) NOT NULL,
  `billingstreet` text NOT NULL,
  `billingpostcode` varchar(255) NOT NULL,
  `billingcity` varchar(255) NOT NULL,
  `billingcountry` varchar(255) NOT NULL,
  `shippingname1` varchar(255) NOT NULL,
  `shippingname2` varchar(255) NOT NULL,
  `shippingdepartment` varchar(255) NOT NULL,
  `shippingstreet` text NOT NULL,
  `shippingpostcode` varchar(255) NOT NULL,
  `shippingcity` varchar(255) NOT NULL,
  `shippingcountry` varchar(255) NOT NULL,
  `shippingphone` varchar(255) NOT NULL,
  `subtotal` decimal(15,4) NOT NULL,
  `taxes` decimal(15,4) NOT NULL,
  `total` decimal(15,4) NOT NULL,
  `taxfree` tinyint(3) NOT NULL,
  `state` tinyint(3) NOT NULL,
  `completed` tinyint(3) NOT NULL,
  `cancelled` tinyint(3) NOT NULL,
  `contactperson` varchar(255) NOT NULL,
  `templateid` int(11) NOT NULL,
  `language` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `quotepos`;
CREATE TABLE IF NOT EXISTS `quotepos` (
  `id` int(11) NOT NULL,
  `quoteid` int(11) NOT NULL,
  `itemid` int(11) NOT NULL,
  `sku` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(15,4) NOT NULL,
  `taxrate` decimal(15,4) NOT NULL,
  `quantity` decimal(9,4) DEFAULT NULL,
  `total` decimal(15,4) NOT NULL,
  `uom` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `quoterequest`;
CREATE TABLE IF NOT EXISTS `quoterequest` (
  `id` int(11) NOT NULL,
  `quoterequestid` int(11) NOT NULL,
  `opportunityid` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `info` text NOT NULL,
  `header` text NOT NULL,
  `footer` text NOT NULL,
  `vatin` varchar(255) NOT NULL,
  `quoterequestdate` date NOT NULL,
  `orderdate` date NOT NULL,
  `deliverydate` date NOT NULL,
  `paymentmethod` varchar(255) NOT NULL,
  `shippingmethod` varchar(255) NOT NULL,
  `billingname1` varchar(255) NOT NULL,
  `billingname2` varchar(255) NOT NULL,
  `billingdepartment` varchar(255) NOT NULL,
  `billingstreet` text NOT NULL,
  `billingpostcode` varchar(255) NOT NULL,
  `billingcity` varchar(255) NOT NULL,
  `billingcountry` varchar(255) NOT NULL,
  `shippingname1` varchar(255) NOT NULL,
  `shippingname2` varchar(255) NOT NULL,
  `shippingdepartment` varchar(255) NOT NULL,
  `shippingstreet` text NOT NULL,
  `shippingpostcode` varchar(255) NOT NULL,
  `shippingcity` varchar(255) NOT NULL,
  `shippingcountry` varchar(255) NOT NULL,
  `shippingphone` varchar(255) NOT NULL,
  `subtotal` decimal(15,4) NOT NULL,
  `taxes` decimal(15,4) NOT NULL,
  `total` decimal(15,4) NOT NULL,
  `taxfree` tinyint(3) NOT NULL,
  `state` tinyint(3) NOT NULL,
  `completed` tinyint(3) NOT NULL,
  `cancelled` tinyint(3) NOT NULL,
  `contactperson` varchar(255) NOT NULL,
  `templateid` int(11) NOT NULL,
  `language` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `quoterequestpos`;
CREATE TABLE IF NOT EXISTS `quoterequestpos` (
  `id` int(11) NOT NULL,
  `quoterequestid` int(11) NOT NULL,
  `itemid` int(11) NOT NULL,
  `sku` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(15,4) NOT NULL,
  `taxrate` decimal(15,4) NOT NULL,
  `quantity` decimal(9,4) DEFAULT NULL,
  `total` decimal(15,4) NOT NULL,
  `uom` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `salesorder`;
CREATE TABLE IF NOT EXISTS `salesorder` (
  `id` int(11) NOT NULL,
  `salesorderid` int(11) NOT NULL,
  `opportunityid` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `info` text NOT NULL,
  `header` text NOT NULL,
  `footer` text NOT NULL,
  `vatin` varchar(255) NOT NULL,
  `salesorderdate` date NOT NULL,
  `orderdate` date NOT NULL,
  `deliverydate` date NOT NULL,
  `paymentmethod` varchar(255) NOT NULL,
  `shippingmethod` varchar(255) NOT NULL,
  `billingname1` varchar(255) NOT NULL,
  `billingname2` varchar(255) NOT NULL,
  `billingdepartment` varchar(255) NOT NULL,
  `billingstreet` text NOT NULL,
  `billingpostcode` varchar(255) NOT NULL,
  `billingcity` varchar(255) NOT NULL,
  `billingcountry` varchar(255) NOT NULL,
  `shippingname1` varchar(255) NOT NULL,
  `shippingname2` varchar(255) NOT NULL,
  `shippingdepartment` varchar(255) NOT NULL,
  `shippingstreet` text NOT NULL,
  `shippingpostcode` varchar(255) NOT NULL,
  `shippingcity` varchar(255) NOT NULL,
  `shippingcountry` varchar(255) NOT NULL,
  `shippingphone` varchar(255) NOT NULL,
  `subtotal` decimal(15,4) NOT NULL,
  `taxes` decimal(15,4) NOT NULL,
  `total` decimal(15,4) NOT NULL,
  `taxfree` tinyint(3) NOT NULL,
  `state` tinyint(3) NOT NULL,
  `completed` tinyint(3) NOT NULL,
  `cancelled` tinyint(3) NOT NULL,
  `contactperson` varchar(255) NOT NULL,
  `templateid` int(11) NOT NULL,
  `language` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `salesorderpos`;
CREATE TABLE IF NOT EXISTS `salesorderpos` (
  `id` int(11) NOT NULL,
  `salesorderid` int(11) NOT NULL,
  `itemid` int(11) NOT NULL,
  `sku` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(15,4) NOT NULL,
  `taxrate` decimal(15,4) NOT NULL,
  `quantity` decimal(9,4) DEFAULT NULL,
  `total` decimal(15,4) NOT NULL,
  `uom` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `shipmenttracking`;
CREATE TABLE IF NOT EXISTS `shipmenttracking` (
  `id` int(11) NOT NULL,
  `carrier` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `shippingaddress`;
CREATE TABLE IF NOT EXISTS `shippingaddress` (
  `id` int(11) NOT NULL,
  `contactid` int(11) NOT NULL,
  `name1` varchar(255) NOT NULL,
  `name2` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `street` text NOT NULL,
  `postcode` varchar(12) NOT NULL,
  `city` varchar(255) NOT NULL,
  `country` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `shippingmethod`;
CREATE TABLE IF NOT EXISTS `shippingmethod` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `state`;
CREATE TABLE IF NOT EXISTS `state` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `standard` tinyint(4) NOT NULL,
  `completed` tinyint(4) NOT NULL,
  `cancelled` tinyint(4) NOT NULL,
  `extra` varchar(255) NOT NULL,
  `module` varchar(255) NOT NULL,
  `controller` varchar(255) NOT NULL,
  `color` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `taxrate`;
CREATE TABLE IF NOT EXISTS `taxrate` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `rate` decimal(15,4) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `template`;
CREATE TABLE IF NOT EXISTS `template` (
  `id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `logo` varchar(255) NOT NULL,
  `website` varchar(255) NOT NULL,
  `default` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL,
  `activated` int(11) NOT NULL,
  `deleted` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `textblock`;
CREATE TABLE IF NOT EXISTS `textblock` (
  `id` int(11) NOT NULL,
  `text` text NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `uom`;
CREATE TABLE IF NOT EXISTS `uom` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `ordering` int(11) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(32) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `smtphost` varchar(255) NOT NULL,
  `smtpauth` varchar(255) NOT NULL,
  `smtpsecure` varchar(255) NOT NULL,
  `smtpuser` varchar(255) NOT NULL,
  `smtppass` varchar(32) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `lockedtime` datetime NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

INSERT INTO `user` (`id`, `username`, `password`, `name`, `email`, `smtphost`, `smtpauth`, `smtpsecure`, `smtpuser`, `smtppass`, `clientid`, `created`, `createdby`, `modified`, `modifiedby`, `locked`, `lockedtime`) VALUES
(1, 'admin', '21232f297a57a5a743894a0e4a801fc3', 'Admin', '', '', '', '', '', '', 1, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0, 0, '0000-00-00 00:00:00');


ALTER TABLE `address`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `archive`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `category`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `client`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `config`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `contact`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `country`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `creditnote`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `creditnotepos`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `deliveryorder`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `deliveryorderpos`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `documentrelation`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `ebayhistory`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `ebayuser`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `email`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contactid` (`contactid`);

ALTER TABLE `footer`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `internet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contactid` (`contactid`);

ALTER TABLE `invoice`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `invoicepos`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `item`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `language`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `magentocustomer`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `magentohistory`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `magentouser`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `manufacturer`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `module`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `paymentmethod`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `phone`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contactid` (`contactid`);

ALTER TABLE `process`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `processpos`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `purchaseorder`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `purchaseorderpos`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `quote`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `quotepos`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `quoterequest`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `quoterequestpos`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `salesorder`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `salesorderpos`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `shipmenttracking`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `shippingaddress`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `shippingmethod`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `state`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `taxrate`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `template`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `textblock`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `uom`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `user`
  ADD PRIMARY KEY (`id`);


ALTER TABLE `address`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `archive`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `client`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;
ALTER TABLE `config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;
ALTER TABLE `contact`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `country`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `creditnote`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `creditnotepos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `deliveryorder`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `deliveryorderpos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `documentrelation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `ebayhistory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `ebayuser`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `email`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `footer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `internet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `invoice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `invoicepos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `item`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `language`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `magentocustomer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `magentohistory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `magentouser`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `manufacturer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `module`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=10;
ALTER TABLE `paymentmethod`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `phone`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `process`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `processpos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `purchaseorder`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `purchaseorderpos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `quote`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `quotepos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `quoterequest`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `quoterequestpos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `salesorder`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `salesorderpos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `shipmenttracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `shippingaddress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `shippingmethod`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `state`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `taxrate`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `template`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `textblock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `uom`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;
