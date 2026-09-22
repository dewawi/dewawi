CREATE TABLE IF NOT EXISTS `pageblock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL DEFAULT 0,
  `pageid` int(11) NOT NULL,
  `type` varchar(255) NOT NULL,
  `data` text DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `activated` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY (`parentid`),
  KEY (`pageid`),
  KEY (`type`),
  KEY (`clientid`),
  KEY (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `shop` ADD `catalogenabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `smtppass`;
ALTER TABLE `shop` ADD `checkoutenabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `catalogenabled`;
ALTER TABLE `shop` ADD `contactenabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `checkoutenabled`;
ALTER TABLE `shop` ADD `inquiryenabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `contactenabled`;

ALTER TABLE `menuitem` ADD `categoryid` int(11) NOT NULL DEFAULT 0 AFTER `pageid`;
ALTER TABLE `menuitem` ADD `variant` varchar(50) NOT NULL DEFAULT 'default' AFTER `title`;
ALTER TABLE `menuitem` ADD INDEX (`categoryid`);

ALTER TABLE `config` ADD `unsubscribeurl` varchar(255) DEFAULT NULL AFTER `smtppass`;
ALTER TABLE `campaign` ADD `unsubscribeurl` varchar(255) DEFAULT NULL AFTER `emailattachment`;

ALTER TABLE `contact` DROP INDEX `clientid`;
ALTER TABLE `contact` DROP INDEX `deleted`;
ALTER TABLE `contact` ADD INDEX `clientid_deleted_catid` (`clientid`, `deleted`, `catid`);

ALTER TABLE `contactperson` DROP INDEX `parentid`;
ALTER TABLE `contactperson` DROP INDEX `module`;
ALTER TABLE `contactperson` DROP INDEX `controller`;
ALTER TABLE `contactperson` ADD INDEX `parentid_clientid_deleted` (`parentid`, `clientid`, `deleted`);

ALTER TABLE `email` DROP INDEX `contactid`;
ALTER TABLE `email` DROP INDEX `module`;
ALTER TABLE `email` DROP INDEX `controller`;
ALTER TABLE `email` DROP INDEX `clientid`;
ALTER TABLE `email` DROP INDEX `deleted`;
ALTER TABLE `email` ADD INDEX `parentid_clientid_deleted` (`parentid`, `clientid`, `deleted`);
ALTER TABLE `email` ADD INDEX `clientid_deleted_suppressed` (`clientid`, `deleted`, `suppressed`);

ALTER TABLE `emailmessage` ADD INDEX `parentid_clientid_deleted_module_controller` (`parentid`, `clientid`, `deleted`, `module`, `controller`);
