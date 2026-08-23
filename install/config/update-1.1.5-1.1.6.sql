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
