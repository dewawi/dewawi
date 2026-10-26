ALTER TABLE `contactperson` ADD `firstname` varchar(255) DEFAULT NULL AFTER `name2`;
ALTER TABLE `contactperson` ADD `lastname` varchar(255) DEFAULT NULL AFTER `firstname`;
ALTER TABLE `contactperson` ADD `position` varchar(255) DEFAULT NULL AFTER `lastname`;

UPDATE `contactperson`
SET
  `firstname` = CASE
    WHEN TRIM(COALESCE(`name1`, '')) != '' AND TRIM(COALESCE(`name2`, '')) != '' THEN TRIM(`name1`)
    ELSE NULL
  END,
  `lastname` = CASE
    WHEN TRIM(COALESCE(`name2`, '')) != '' THEN TRIM(`name2`)
    WHEN TRIM(COALESCE(`name1`, '')) != '' THEN TRIM(`name1`)
    ELSE NULL
  END
WHERE TRIM(COALESCE(`firstname`, '')) = ''
  AND TRIM(COALESCE(`lastname`, '')) = '';

CREATE TABLE IF NOT EXISTS `contactrole` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL,
  `role` varchar(32) NOT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clientid_parentid_role` (`clientid`, `parentid`, `role`),
  KEY `parentid_clientid_deleted` (`parentid`, `clientid`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `contactrole` (`parentid`, `role`, `clientid`, `created`, `createdby`, `modified`, `modifiedby`, `deleted`)
SELECT
  `id`,
  LOWER(TRIM(`type`)),
  `clientid`,
  COALESCE(`created`, NOW()),
  `createdby`,
  `modified`,
  `modifiedby`,
  0
FROM `contact`
WHERE LOWER(TRIM(COALESCE(`type`, ''))) IN ('customer', 'supplier', 'prospect', 'partner');
