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
