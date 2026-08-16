ALTER TABLE `warehouse` CHANGE `active` `activated` tinyint(1) NOT NULL DEFAULT 0 AFTER `lockedtime`;
ALTER TABLE `module` CHANGE `active` `activated` tinyint(1) NOT NULL DEFAULT 0 AFTER `lockedtime`;
ALTER TABLE `itemstock` DROP KEY `itemid`, ADD UNIQUE KEY (`itemid`, `warehouseid`, `clientid`);
ALTER TABLE `item` ADD `parentid` int(11) DEFAULT NULL AFTER `id`, ADD KEY (`parentid`);
