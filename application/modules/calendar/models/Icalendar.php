<?php

class Calendar_Model_Icalendar
{
	public static function email(string $value): string
	{
		if (preg_match('/[\x00-\x20\x7f]/', trim($value))) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_ADDRESS');
		$value = DEEC_Filter::email($value) ?? '';
		if (substr_count($value, '@') !== 1) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_ADDRESS');
		[$local, $domain] = explode('@', $value);
		if (preg_match('/[^\x00-\x7f]/', $domain)) $domain = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46) ?: '';
		$value = strtolower($local . '@' . $domain);
		if (strlen($value) > 254 || preg_match('/[^\x00-\x7f]/', $value) || !DEEC_Filter::isValidEmail($value)) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_ADDRESS');
		return $value;
	}

	private static function text(string $value): string
	{
		if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) throw new InvalidArgumentException('CALENDAR_INVALID_TEXT');
		return str_replace(['\\', "\r\n", "\r", "\n", ';', ','], ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'], $value);
	}

	public static function render(array $event, array $participant, string $method): string
	{
		if (!in_array($method, ['REQUEST', 'CANCEL'], true) || !preg_match('/^[a-zA-Z0-9@.\-]+$/D', $event['icaluid'] ?? '') || empty($event['startutc']) || empty($event['endutc'])) throw new InvalidArgumentException('CALENDAR_MAIL_SAVE_FIRST');
		$organizer = self::email($event['organizeremail'] ?? '');
		$recipient = self::email($participant['email'] ?? '');
		$utc = new DateTimeZone('UTC');
		$stamp = (new DateTimeImmutable($event['modified'] ?: $event['created'], $utc))->format('Ymd\THis\Z');
		$lines = ['BEGIN:VCALENDAR', 'PRODID:-//DEWAWI//Team Calendar//EN', 'VERSION:2.0', 'CALSCALE:GREGORIAN', 'METHOD:' . $method, 'BEGIN:VEVENT', 'UID:' . $event['icaluid'], 'DTSTAMP:' . $stamp, 'SEQUENCE:' . (int)$event['icalsequence']];
		foreach ($method === 'REQUEST' ? ['start' => 'DTSTART', 'end' => 'DTEND'] : [] as $field => $property) {
			$lines[] = $event['allday'] ? $property . ';VALUE=DATE:' . str_replace('-', '', substr($event[$field], 0, 10)) : $property . ':' . (new DateTimeImmutable($event[$field . 'utc'], $utc))->format('Ymd\THis\Z');
		}
		$lines[] = 'ORGANIZER:mailto:' . str_replace('%40', '@', rawurlencode($organizer));
		$status = ['invited' => 'NEEDS-ACTION', 'accepted' => 'ACCEPTED', 'declined' => 'DECLINED'][$participant['status']];
		$lines[] = 'ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=' . $status . ($method === 'REQUEST' ? ';RSVP=TRUE' : '') . ':mailto:' . str_replace('%40', '@', rawurlencode($recipient));
		if ($method === 'REQUEST' || $event['cancelled']) $lines[] = 'STATUS:' . ($method === 'CANCEL' ? 'CANCELLED' : 'CONFIRMED');
		$lines[] = 'CLASS:' . ($event['visibility'] === 'private' ? 'PRIVATE' : 'PUBLIC');
		if ($method === 'REQUEST') {
			$lines[] = 'SUMMARY:' . self::text($event['title']);
			$lines[] = 'DESCRIPTION:' . self::text((string)$event['description']);
			$lines[] = 'LOCATION:' . self::text((string)$event['location']);
		}
		$lines[] = 'END:VEVENT'; $lines[] = 'END:VCALENDAR';
		$folded = [];
		foreach ($lines as $line) {
			while (strlen($line) > 75) { $part = mb_strcut($line, 0, 75, 'UTF-8'); $folded[] = $part; $line = ' ' . substr($line, strlen($part)); }
			$folded[] = $line;
		}
		return implode("\r\n", $folded) . "\r\n";
	}
}
