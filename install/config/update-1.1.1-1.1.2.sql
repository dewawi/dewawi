CREATE TABLE IF NOT EXISTS `slug` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL DEFAULT 0,
  `shopid` int(11) NOT NULL,
  `module` varchar(255) DEFAULT NULL,
  `controller` varchar(255) DEFAULT NULL,
  `entityid` int(11) NOT NULL,
  `slug` text DEFAULT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY (parentid),
  KEY (shopid),
  KEY (module),
  KEY (controller),
  KEY (entityid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `item` DROP `slug`;
ALTER TABLE `category` DROP `slug`;
ALTER TABLE `menuitem` DROP `slug`;
ALTER TABLE `tag` DROP `slug`;

RENAME TABLE `cloud`.`images` TO `cloud`.`media`;
ALTER TABLE `media` ADD `type` varchar(255) DEFAULT NULL AFTER `controller`;
UPDATE `media` SET `type` = 'image';

ALTER TABLE `category` ADD `activated` tinyint(1) NOT NULL DEFAULT 0 AFTER `lockedtime`;
UPDATE `category` SET `activated` = '1' WHERE 1;

ALTER TABLE `menu` ADD `position` varchar(255) DEFAULT NULL AFTER `title`;

ALTER TABLE `item` ADD `subtitle` varchar(255) DEFAULT NULL AFTER `title`;

ALTER TABLE `category` ADD `downloads` text DEFAULT NULL AFTER `metakeyword`;

ALTER TABLE `shoporder` ADD `total` DECIMAL(12,4) NOT NULL AFTER `invoiceid`;

CREATE TABLE IF NOT EXISTS `shoporderpos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shopid` varchar(255) NOT NULL,
  `orderid` varchar(255) NOT NULL,
  `itemid` int(11) NOT NULL,
  `total` decimal(12,4) NOT NULL,
  `quantity` decimal(12,4) DEFAULT NULL,
  `price` decimal(12,4) DEFAULT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY (shopid),
  KEY (orderid),
  KEY (contactid),
  KEY (clientid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `page` ADD `parentid` int(11) NOT NULL DEFAULT 0 AFTER `id`;
ALTER TABLE `page` ADD `type` varchar(255) DEFAULT NULL AFTER `shopid`;

ALTER TABLE `creditnote` ADD `subject` varchar(255) DEFAULT NULL AFTER `title`;
ALTER TABLE `deliveryorder` ADD `subject` varchar(255) DEFAULT NULL AFTER `title`;
ALTER TABLE `invoice` ADD `subject` varchar(255) DEFAULT NULL AFTER `title`;
ALTER TABLE `quote` ADD `subject` varchar(255) DEFAULT NULL AFTER `title`;
ALTER TABLE `reminder` ADD `subject` varchar(255) DEFAULT NULL AFTER `title`;
ALTER TABLE `salesorder` ADD `subject` varchar(255) DEFAULT NULL AFTER `title`;
ALTER TABLE `quoterequest` ADD `subject` varchar(255) DEFAULT NULL AFTER `title`;
ALTER TABLE `purchaseorder` ADD `subject` varchar(255) DEFAULT NULL AFTER `title`;
ALTER TABLE `process` ADD `subject` varchar(255) DEFAULT NULL AFTER `title`;

ALTER TABLE `creditnote` ADD `reference` varchar(255) DEFAULT NULL AFTER `subject`;
ALTER TABLE `deliveryorder` ADD `reference` varchar(255) DEFAULT NULL AFTER `subject`;
ALTER TABLE `invoice` ADD `reference` varchar(255) DEFAULT NULL AFTER `subject`;
ALTER TABLE `quote` ADD `reference` varchar(255) DEFAULT NULL AFTER `subject`;
ALTER TABLE `reminder` ADD `reference` varchar(255) DEFAULT NULL AFTER `subject`;
ALTER TABLE `salesorder` ADD `reference` varchar(255) DEFAULT NULL AFTER `subject`;
ALTER TABLE `quoterequest` ADD `reference` varchar(255) DEFAULT NULL AFTER `subject`;
ALTER TABLE `purchaseorder` ADD `reference` varchar(255) DEFAULT NULL AFTER `subject`;
ALTER TABLE `process` ADD `reference` varchar(255) DEFAULT NULL AFTER `subject`;

ALTER TABLE `item` ADD `specialprice` decimal(12,4) DEFAULT NULL AFTER `price`;

ALTER TABLE `shop` CHANGE `footer` `copyright` varchar(255) DEFAULT NULL;
ALTER TABLE `shop` ADD `footer` text DEFAULT NULL AFTER `title`;
