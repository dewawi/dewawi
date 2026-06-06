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
