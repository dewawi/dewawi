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
