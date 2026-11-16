<?php

class Calendar_Model_Event
{
	public static function timezone(string $name): DateTimeZone
	{
		if (!in_array($name, DateTimeZone::listIdentifiers(), true) && $name !== 'UTC') throw new InvalidArgumentException('CALENDAR_INVALID_TIMEZONE');
		return new DateTimeZone($name);
	}

	public static function date(string $value): DateTimeImmutable
	{
		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
		if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01' || $value > '9998-12-31') throw new InvalidArgumentException('CALENDAR_INVALID_DATE');
		return $date;
	}

	// Match all UTC candidates instead of letting PHP silently normalize DST gaps/folds.
	public static function localTime(string $value, DateTimeZone $zone, string $fold = ''): DateTimeImmutable
	{
		if (!in_array($fold, ['', 'earlier', 'later'], true)) throw new InvalidArgumentException('CALENDAR_INVALID_DATE');
		$format = strlen($value) === 19 ? 'Y-m-d\TH:i:s' : 'Y-m-d\TH:i';
		$date = DateTimeImmutable::createFromFormat('!' . $format, $value, new DateTimeZone('UTC'));
		if (!$date || $date->format($format) !== $value) throw new InvalidArgumentException('CALENDAR_INVALID_DATE');
		self::date(substr($value, 0, 10));
		$wall = $date->getTimestamp();
		$candidates = [];
		foreach ($zone->getTransitions($wall - 172800, $wall + 172800) as $transition) {
			$utc = $date->setTimestamp($wall - $transition['offset']);
			if ($utc->setTimezone($zone)->format($format) === $value) $candidates[$utc->getTimestamp()] = $utc;
		}
		ksort($candidates);
		if (!$candidates) throw new InvalidArgumentException('CALENDAR_DST_GAP');
		if (count($candidates) > 1 && $fold === '') throw new InvalidArgumentException('CALENDAR_DST_FOLD');
		return $fold === 'later' ? end($candidates) : reset($candidates);
	}

	public static function dayStart(string $value, DateTimeZone $zone): DateTimeImmutable
	{
		self::date($value);
		// Some zones advance at midnight; a calendar day starts at its first existing time.
		$local = new DateTimeImmutable($value . ' 00:00:00', $zone);
		if ($local->format('Y-m-d') !== $value) throw new InvalidArgumentException('CALENDAR_INVALID_DATE');
		return self::localTime($local->format('Y-m-d\TH:i:s'), $zone, 'earlier');
	}

	public static function validate(array $input): array
	{
		$allowed = ['title', 'description', 'start', 'end', 'allday', 'location', 'visibility', 'timezone', 'startfold', 'endfold', 'reminderminutes', 'csrf_token'];
		if (array_diff(array_keys($input), $allowed)) throw new InvalidArgumentException('CALENDAR_INVALID_INPUT');
		foreach ($input as $value) if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) throw new InvalidArgumentException('CALENDAR_INVALID_INPUT');
		$title = trim($input['title'] ?? '');
		$description = trim($input['description'] ?? '');
		$location = trim($input['location'] ?? '');
		if ($title === '' || mb_strlen($title) > 255 || mb_strlen($location) > 255 || mb_strlen($description) > 15000) throw new InvalidArgumentException('CALENDAR_INVALID_TEXT');
		$visibility = $input['visibility'] ?? '';
		if (!in_array($visibility, ['team', 'private'], true) || !in_array($input['allday'] ?? '0', ['0', '1'], true)) throw new InvalidArgumentException('CALENDAR_INVALID_INPUT');
		$zone = self::timezone($input['timezone'] ?? '');
		$allday = (int)($input['allday'] ?? 0);
		$dates = [];
		foreach (['start', 'end'] as $field) {
			$value = $input[$field] ?? '';
			$dates[$field] = $allday ? self::dayStart($value, $zone) : self::localTime($value, $zone, $input[$field . 'fold'] ?? '');
			if ($dates[$field]->format('Y') < '1000' || $dates[$field]->format('Y') > '9999') throw new InvalidArgumentException('CALENDAR_INVALID_DATE');
		}
		if ($dates['end'] <= $dates['start']) throw new InvalidArgumentException('CALENDAR_INVALID_RANGE');
		$reminder = [];
		if (array_key_exists('reminderminutes', $input)) {
			if (!in_array($input['reminderminutes'], ['none', '0', '5', '15', '30', '60', '1440'], true)) throw new InvalidArgumentException('CALENDAR_REMINDER_INVALID');
			$reminder['reminderminutes'] = $input['reminderminutes'] === 'none' ? null : (int)$input['reminderminutes'];
		}
		return $reminder + [
			'title' => $title, 'description' => $description, 'location' => $location,
			'visibility' => $visibility, 'allday' => $allday, 'timezone' => $zone->getName(),
			'start' => $dates['start']->setTimezone($zone)->format('Y-m-d H:i:s'),
			'end' => $dates['end']->setTimezone($zone)->format('Y-m-d H:i:s'),
			'startutc' => $dates['start']->format('Y-m-d H:i:s'), 'endutc' => $dates['end']->format('Y-m-d H:i:s'),
		];
	}

	public static function period(string $date, string $mode, DateTimeZone $zone): array
	{
		$day = self::date($date)->setTimezone(new DateTimeZone('UTC'));
		if (!in_array($mode, ['month', 'week', 'day', 'agenda'], true)) throw new InvalidArgumentException('CALENDAR_INVALID_INPUT');
		$anchor = $mode === 'month' ? $day->modify('first day of this month') : $day;
		$start = in_array($mode, ['month', 'week'], true) ? $anchor->modify('-' . ((int)$anchor->format('N') - 1) . ' days') : $anchor;
		$end = $mode === 'month' ? $start->modify('+42 days') : $start->modify($mode === 'week' ? '+7 days' : ($mode === 'agenda' ? '+30 days' : '+1 day'));
		$step = $mode === 'month' ? 'month' : ($mode === 'week' ? 'week' : ($mode === 'agenda' ? '30 days' : 'day'));
		return [
			'date' => $day->format('Y-m-d'), 'mode' => $mode,
			'first' => $start->format('Y-m-d'), 'until' => $end->format('Y-m-d'),
			'start' => self::dayStart($start->format('Y-m-d'), $zone),
			'end' => self::dayStart($end->format('Y-m-d'), $zone),
			'previous' => $anchor->modify('-1 ' . $step)->format('Y-m-d'), 'next' => $anchor->modify('+1 ' . $step)->format('Y-m-d'),
		];
	}

	public static function present(array $row, int $actor, DateTimeZone $displayZone): array
	{
		$own = (int)$row['ownerid'] === $actor;
		$private = !$own && $row['visibility'] !== 'team';
		$zone = self::timezone($row['timezone'] ?: $displayZone->getName());
		// Old records retain their original tenant-local wall times until explicitly edited.
		$start = $row['startutc'] ? new DateTimeImmutable($row['startutc'], new DateTimeZone('UTC')) : self::localTime(str_replace(' ', 'T', $row['start']), $zone, 'earlier');
		$end = $row['endutc'] ? new DateTimeImmutable($row['endutc'], new DateTimeZone('UTC')) : self::localTime(str_replace(' ', 'T', $row['end']), $zone, 'later');
		$result = [
			'id' => (int)$row['id'], 'ownerid' => (int)$row['ownerid'], 'owner' => (string)($row['owner'] ?? ''),
			'own' => $own, 'private' => $private, 'allday' => (bool)$row['allday'],
			'start' => $start->setTimezone($row['allday'] ? $zone : $displayZone)->format(DateTimeInterface::ATOM),
			'end' => $end->setTimezone($row['allday'] ? $zone : $displayZone)->format(DateTimeInterface::ATOM),
		];
		if (!$private) $result += ['title' => (string)$row['title'], 'description' => (string)$row['description'], 'location' => (string)$row['location'], 'visibility' => $row['visibility'], 'timezone' => $zone->getName()];
		if ($own) {
			$result['reminderminutes'] = $row['reminderminutes'] === null ? 'none' : (string)$row['reminderminutes'];
			foreach (['start' => $start, 'end' => $end] as $field => $instant) {
				$local = $instant->setTimezone($zone)->format((int)$instant->format('s') ? 'Y-m-d\TH:i:s' : 'Y-m-d\TH:i');
				$result[$field . 'local'] = $row['allday'] ? substr($local, 0, 10) : $local;
				$result[$field . 'fold'] = self::localTime($local, $zone, 'earlier')->getTimestamp() === $instant->getTimestamp() ? 'earlier' : 'later';
			}
		}
		return $result;
	}
}
