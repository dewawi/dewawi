SELECT
  `id`,
  `clientid`,
  `parentid`,
  `name1`,
  `name2`,
  `firstname`,
  `lastname`,
  CASE
    WHEN TRIM(COALESCE(`name1`, '')) != '' AND TRIM(COALESCE(`name2`, '')) != '' THEN 'verify_name_order'
    WHEN TRIM(COALESCE(`name1`, '')) != '' OR TRIM(COALESCE(`name2`, '')) != '' THEN 'split_or_verify_single_name'
    ELSE 'missing_name'
  END AS `review_reason`
FROM `contactperson`
WHERE `deleted` = 0
  AND (
    (
      TRIM(COALESCE(`name1`, '')) != ''
      AND TRIM(COALESCE(`name2`, '')) != ''
      AND TRIM(COALESCE(`firstname`, '')) = TRIM(`name1`)
      AND TRIM(COALESCE(`lastname`, '')) = TRIM(`name2`)
    )
    OR (
      (TRIM(COALESCE(`name1`, '')) != '' OR TRIM(COALESCE(`name2`, '')) != '')
      AND TRIM(COALESCE(`firstname`, '')) = ''
      AND TRIM(COALESCE(`lastname`, '')) = COALESCE(NULLIF(TRIM(`name2`), ''), TRIM(`name1`))
    )
    OR (
      TRIM(COALESCE(`name1`, '')) = ''
      AND TRIM(COALESCE(`name2`, '')) = ''
      AND TRIM(COALESCE(`firstname`, '')) = ''
      AND TRIM(COALESCE(`lastname`, '')) = ''
    )
  )
ORDER BY `clientid`, `parentid`, `ordering`, `id`;
