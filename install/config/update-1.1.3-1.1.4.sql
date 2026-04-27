ALTER TABLE `shop` ADD COLUMN `theme` VARCHAR(100) NOT NULL DEFAULT 'default' AFTER `language`;

ALTER TABLE `item` DROP COLUMN `downloads`;
ALTER TABLE `category` DROP COLUMN `downloads`;
