<?php

final class DEEC_ContactpersonFormatter
{
	public static function displayName(array $person, bool $includeTitle = true): string
	{
		$parts = [];

		if ($includeTitle) {
			$parts[] = trim((string)($person['title'] ?? ''));
		}

		$parts[] = trim((string)($person['firstname'] ?? ''));
		$parts[] = trim((string)($person['lastname'] ?? ''));

		return implode(' ', array_filter($parts, static function(string $part): bool {
			return $part !== '';
		}));
	}

	public static function formalSalutation(array $person): string
	{
		$salutation = trim((string)($person['salutation'] ?? ''));
		$lastname = trim((string)($person['lastname'] ?? ''));

		if ($lastname === '') {
			return 'Guten Tag,';
		}

		$title = trim((string)($person['title'] ?? ''));
		$name = trim($title . ' ' . $lastname);

		if (strcasecmp($salutation, 'Herr') === 0) {
			return 'Sehr geehrter Herr ' . $name . ',';
		}

		if (strcasecmp($salutation, 'Frau') === 0) {
			return 'Sehr geehrte Frau ' . $name . ',';
		}

		return 'Guten Tag,';
	}

	public static function recipientLabel(array $recipient): string
	{
		$email = trim((string)($recipient['email'] ?? ''));
		$name = self::displayName($recipient);

		return $name === '' ? $email : $email . ' (' . $name . ')';
	}
}
