RENAME TABLE `itemimage` TO `images`;
ALTER TABLE `images` CHANGE `itemid` `parentid` INT(11) NOT NULL;
ALTER TABLE `images` ADD `module` varchar(255) DEFAULT NULL AFTER `parentid`;
ALTER TABLE `images` ADD `controller` varchar(255) DEFAULT NULL AFTER `module`;
UPDATE `images` SET `module` = 'items';
UPDATE `images` SET `controller` = 'item';
