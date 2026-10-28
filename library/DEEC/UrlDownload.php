<?php

class DEEC_UrlDownload
{
	public function receive(string $url, int $limit = 10240000): array
	{
		$started = microtime(true);
		$parts = parse_url($url);
		if (strlen($url) > 2000 || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || !$parts
			|| !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])
			|| isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || $limit < 1 || $limit > 10240000) {
			throw new InvalidArgumentException('Invalid download URL');
		}
		$host = strtolower(trim($parts['host'], '[]'));
		if (!filter_var($host, FILTER_VALIDATE_IP) && (!preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/iD', $host)
			|| !str_contains($host, '.') || preg_match('/^(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*$/iD', $host))) throw new InvalidArgumentException('Invalid download host');
		$port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
		if ($port !== ($parts['scheme'] === 'https' ? 443 : 80)) throw new InvalidArgumentException('Invalid download port');
		$url = $parts['scheme'] . '://' . (str_contains($host, ':') ? '[' . $host . ']' : $host) . ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
		$addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
		if (microtime(true) - $started > 5) throw new RuntimeException('Download DNS resolution timed out');
		if (!$addresses) throw new RuntimeException('Download host is unavailable');
		foreach ($addresses as $address) {
			if (!$this->isPublicAddress($address)) throw new InvalidArgumentException('Download address is not public');
		}
		$filename = rawurldecode(basename($parts['path'] ?? '')) ?: 'download';
		if (!DEEC_Emailattachment::validFilename($filename) || $filename[0] === '.') throw new InvalidArgumentException('Invalid download filename');
		$path = tempnam(sys_get_temp_dir(), 'dewawi-url-');
		if ($path === false) throw new RuntimeException('Download staging is unavailable');
		$stream = fopen($path, 'wb');
		$size = 0;
		$headerSize = 0;
		$complete = false;
		try {
			if (!$stream) throw new RuntimeException('Download staging is unavailable');
			$ip = str_contains($addresses[0], ':') ? '[' . $addresses[0] . ']' : $addresses[0];
			$options = [
				CURLOPT_URL => $url, CURLOPT_PROXY => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
				CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT_MS => max(1, (int)((15 - (microtime(true) - $started)) * 1000)),
				CURLOPT_MAXFILESIZE_LARGE => $limit,
				CURLOPT_LOW_SPEED_LIMIT => 1024, CURLOPT_LOW_SPEED_TIME => 5, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip], CURLOPT_FRESH_CONNECT => true, CURLOPT_FORBID_REUSE => true,
				CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use ($limit, &$headerSize): int {
					$headerSize += strlen($header);
					if ($headerSize > 65536 || (preg_match('/^Content-Length:\s*(\d+)/i', $header, $match) && (float)$match[1] > $limit)) return 0;
					return strlen($header);
				},
				CURLOPT_WRITEFUNCTION => static function ($handle, string $data) use ($stream, $limit, &$size): int {
					if (strlen($data) > $limit - $size) return 0;
					$written = fwrite($stream, $data);
					$size += $written ?: 0;
					return $written ?: 0;
				},
			];
			[$status, $peer] = $this->transfer($options);
			if ($status !== 200 || !$this->isPublicAddress($peer) || inet_pton($peer) !== inet_pton($addresses[0]) || $size < 1) throw new RuntimeException('Download failed');
			$complete = true;
			return ['path' => $path, 'name' => $filename, 'size' => $size];
		} finally {
			if (is_resource($stream)) fclose($stream);
			if (!$complete) unlink($path);
		}
	}

	protected function resolve(string $host): array
	{
		$records = dns_get_record($host, DNS_A | DNS_AAAA);
		return array_values(array_filter(array_map(static fn($record) => $record['ip'] ?? $record['ipv6'] ?? null, $records ?: [])));
	}

	protected function isPublicAddress(string $address): bool
	{
		if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
		$packed = inet_pton($address);
		if (strlen($packed) === 4) {
			$bytes = unpack('C4', $packed);
			return $bytes[1] !== 0 && $bytes[1] < 224 && !($bytes[1] === 100 && $bytes[2] >= 64 && $bytes[2] <= 127)
				&& !($bytes[1] === 192 && (($bytes[2] === 0 && in_array($bytes[3], [0, 2], true)) || ($bytes[2] === 88 && $bytes[3] === 99)))
				&& !($bytes[1] === 198 && (in_array($bytes[2], [18, 19], true) || ($bytes[2] === 51 && $bytes[3] === 100)))
				&& !($bytes[1] === 203 && $bytes[2] === 0 && $bytes[3] === 113);
		}
		// Accept global unicast only; exclude mapped, transition and documentation networks.
		return (ord($packed[0]) & 0xe0) === 0x20 && substr($packed, 0, 2) !== "\x20\x02"
			&& substr($packed, 0, 3) !== "\x20\x01\x00" && substr($packed, 0, 4) !== "\x20\x01\x0d\xb8";
	}

	protected function transfer(array $options): array
	{
		$handle = curl_init();
		try {
			curl_setopt_array($handle, $options);
			if (curl_exec($handle) === false) throw new RuntimeException('Download transfer failed: ' . curl_error($handle));
			return [(int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE), (string)curl_getinfo($handle, CURLINFO_PRIMARY_IP)];
		} finally {
			curl_close($handle);
		}
	}
}
