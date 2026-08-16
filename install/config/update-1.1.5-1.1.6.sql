ALTER TABLE `warehouse` CHANGE `active` `activated` tinyint(1) NOT NULL DEFAULT 0 AFTER `lockedtime`;
ALTER TABLE `module` CHANGE `active` `activated` tinyint(1) NOT NULL DEFAULT 0 AFTER `lockedtime`;
