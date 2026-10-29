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

UPDATE `shopinquirydata` AS duplicate
JOIN `shopinquirydata` AS original
  ON original.`clientid` = duplicate.`clientid`
  AND original.`shopid` = duplicate.`shopid`
  AND original.`token` = duplicate.`token`
  AND original.`id` < duplicate.`id`
SET duplicate.`token` = SHA2(CONCAT(duplicate.`token`, ':', duplicate.`id`), 256);

ALTER TABLE `shopinquiryform`
  ADD INDEX `clientid_shopid_deleted_id` (`clientid`, `shopid`, `deleted`, `id`);

ALTER TABLE `shopinquirydata`
  ADD `status` varchar(32) NOT NULL DEFAULT 'new',
  ADD `contactid` int(11) DEFAULT NULL,
  ADD `quoteid` int(11) DEFAULT NULL,
  ADD UNIQUE INDEX `clientid_shopid_token` (`clientid`, `shopid`, `token`(64)),
  ADD INDEX `clientid_shopid_formid_deleted` (`clientid`, `shopid`, `formid`, `deleted`),
  ADD INDEX `clientid_status_shopid_formid_deleted` (`clientid`, `status`, `shopid`, `formid`, `deleted`),
  ADD INDEX `clientid_contactid_deleted` (`clientid`, `contactid`, `deleted`),
  ADD INDEX `clientid_quoteid_deleted` (`clientid`, `quoteid`, `deleted`);

-- Preserve an existing active default, otherwise use the first active UOM by ordering and ID.
UPDATE `uom` AS u
LEFT JOIN (
  SELECT candidate.`clientid`, candidate.`id`
  FROM `uom` AS candidate
  LEFT JOIN `uom` AS preferred
    ON preferred.`clientid` = candidate.`clientid`
    AND preferred.`deleted` = 0
    AND (
      (preferred.`default` = 1) > (candidate.`default` = 1)
      OR ((preferred.`default` = 1) = (candidate.`default` = 1) AND (
        preferred.`ordering` < candidate.`ordering`
        OR (preferred.`ordering` = candidate.`ordering` AND preferred.`id` < candidate.`id`)
      ))
    )
  WHERE candidate.`deleted` = 0 AND preferred.`id` IS NULL
) AS chosen ON chosen.`clientid` = u.`clientid`
SET u.`default` = IF(u.`id` = chosen.`id`, 1, 0);

CREATE TABLE IF NOT EXISTS `stockplanning` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL,
  `itemid` int(11) NOT NULL DEFAULT 0,
  `warehouseid` int(11) NOT NULL DEFAULT 0,
  `quantity` decimal(12,4) NOT NULL DEFAULT 0,
  `fulfilled` decimal(12,4) NOT NULL DEFAULT 0,
  `planningdate` date DEFAULT NULL,
  `dateconfirmed` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `allowoverbooking` tinyint(1) NOT NULL DEFAULT 0,
  `contactid` int(11) DEFAULT NULL,
  `referencemodule` varchar(50) DEFAULT NULL,
  `referencetype` varchar(50) DEFAULT NULL,
  `referenceid` int(11) DEFAULT NULL,
  `referencepositionid` int(11) DEFAULT NULL,
  `linkedplanningid` int(11) DEFAULT NULL,
  `externalnumber` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `clientid` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modifiedby` int(11) NOT NULL DEFAULT 0,
  `locked` int(11) NOT NULL DEFAULT 0,
  `lockedtime` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `client_item_warehouse_status_date` (`clientid`, `itemid`, `warehouseid`, `status`, `planningdate`),
  KEY `client_type_status` (`clientid`, `type`, `status`),
  KEY `client_reference` (`clientid`, `referencemodule`, `referencetype`, `referenceid`, `referencepositionid`),
  KEY `client_contact` (`clientid`, `contactid`),
  KEY `client_linkedplanning` (`clientid`, `linkedplanningid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

UPDATE `itemstock`
SET
  `reserved` = COALESCE((
    SELECT SUM(sp.`quantity` - sp.`fulfilled`)
    FROM `stockplanning` AS sp
    WHERE sp.`itemid` = `itemstock`.`itemid`
      AND sp.`warehouseid` = `itemstock`.`warehouseid`
      AND sp.`clientid` = `itemstock`.`clientid`
      AND sp.`type` = 'reservation'
      AND sp.`status` = 'planned'
      AND sp.`deleted` = 0
  ), 0),
  `incoming` = COALESCE((
    SELECT SUM(sp.`quantity` - sp.`fulfilled`)
    FROM `stockplanning` AS sp
    WHERE sp.`itemid` = `itemstock`.`itemid`
      AND sp.`warehouseid` = `itemstock`.`warehouseid`
      AND sp.`clientid` = `itemstock`.`clientid`
      AND sp.`type` = 'incoming'
      AND sp.`status` = 'planned'
      AND sp.`deleted` = 0
  ), 0);
