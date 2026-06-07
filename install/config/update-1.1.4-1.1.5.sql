ALTER TABLE `media` ADD COLUMN `target` varchar(1024) NULL AFTER `url`;
ALTER TABLE `media` ADD COLUMN `description` text NULL AFTER `title`;

INSERT INTO media (
	parentid,
	module,
	controller,
	type,
	title,
	description,
	url,
	target,
	ordering,
	clientid
)
SELECT
	shopid,
	'shops',
	'slide',
	'slide',
	title,
	description,
	image,
	url,
	ordering,
	clientid
FROM slide;

ALTER TABLE `slide` DROP COLUMN `image`;
ALTER TABLE `slide` CHANGE COLUMN `url` `target` varchar(1024) DEFAULT NULL;
ALTER TABLE `slide` ADD COLUMN `position` varchar(255) DEFAULT NULL AFTER `target`;

ALTER TABLE `slide` DROP INDEX `url`;
ALTER TABLE `slide` ADD KEY `shopid` (`shopid`);
ALTER TABLE `slide` ADD KEY `deleted` (`deleted`);
