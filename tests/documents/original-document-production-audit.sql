-- Read-only operator diagnostic for Atalay & Roos.
-- Confirmed mapping: tenant 100, contact.id 80560, contact.contactid 58101.
-- Recommended invocation is documented in original-document-audit.md.
SET @clientid := 100;
SET @contact_record_id := 80560;
SET @contact_number := 58101;
SET @focus_date := '2026-05-01';

START TRANSACTION READ ONLY;

SELECT
	d.module,
	d.controller,
	d.id,
	d.clientid,
	d.contactid,
	@contact_record_id AS contact_record_id,
	@contact_number AS contact_number,
	d.document_number,
	d.document_date,
	d.filename,
	d.state,
	d.completed,
	d.completeddate,
	d.deleted,
	CASE WHEN d.document_date >= @focus_date THEN 1 ELSE 0 END AS focus_from_may_2026,
	a.id AS attachment_id,
	a.filename AS attachment_filename,
	a.filesize AS attachment_filesize,
	a.location AS attachment_location,
	a.deleted AS attachment_deleted
FROM (
	SELECT 'sales' AS module, 'quote' AS controller, id, clientid, contactid, CAST(quoteid AS CHAR) AS document_number, quotedate AS document_date, filename, state, completed, completeddate, deleted FROM quote
	UNION ALL
	SELECT 'sales', 'salesorder', id, clientid, contactid, CAST(salesorderid AS CHAR), salesorderdate, filename, state, completed, completeddate, deleted FROM salesorder
	UNION ALL
	SELECT 'sales', 'invoice', id, clientid, contactid, CAST(invoiceid AS CHAR), invoicedate, filename, state, completed, completeddate, deleted FROM invoice
	UNION ALL
	SELECT 'sales', 'deliveryorder', id, clientid, contactid, CAST(deliveryorderid AS CHAR), deliveryorderdate, filename, state, completed, completeddate, deleted FROM deliveryorder
	UNION ALL
	SELECT 'sales', 'creditnote', id, clientid, contactid, CAST(creditnoteid AS CHAR), creditnotedate, filename, state, completed, completeddate, deleted FROM creditnote
	UNION ALL
	SELECT 'sales', 'reminder', id, clientid, contactid, CAST(reminderid AS CHAR), reminderdate, filename, state, completed, completeddate, deleted FROM reminder
	UNION ALL
	SELECT 'purchases', 'quoterequest', id, clientid, contactid, CAST(quoterequestid AS CHAR), quoterequestdate, filename, state, completed, completeddate, deleted FROM quoterequest
	UNION ALL
	SELECT 'purchases', 'purchaseorder', id, clientid, contactid, CAST(purchaseorderid AS CHAR), purchaseorderdate, filename, state, completed, completeddate, deleted FROM purchaseorder
) d
JOIN contact c
	ON c.id = @contact_record_id
	AND c.contactid = @contact_number
	AND c.clientid = @clientid
	AND c.deleted = 0
LEFT JOIN emailattachment a
	ON a.documentid = d.id
	AND a.module = d.module
	AND a.controller = d.controller
	AND a.clientid = d.clientid
	AND a.filename = d.filename
WHERE d.clientid = @clientid
	AND d.contactid = @contact_number
ORDER BY d.document_date, d.module, d.controller, d.id, a.deleted, a.id;

ROLLBACK;
