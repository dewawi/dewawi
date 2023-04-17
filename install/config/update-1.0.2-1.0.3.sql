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
