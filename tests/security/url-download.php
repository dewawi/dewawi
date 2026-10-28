<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/library/DEEC/Emailattachment.php';
require_once dirname(__DIR__, 2) . '/library/DEEC/UrlDownload.php';

// Replace DNS and transport completely: these tests never contact any network target.
class UrlDownloadFixture extends DEEC_UrlDownload
{
    public array $addresses = ['8.8.8.8'];
    public array $options = [];
    public int $resolutions = 0;
    public int $transfers = 0;
    public int $status = 200;
    public string $peer = '8.8.8.8';
    public array $headers = [];
    public array $chunks = ['%PDF-1.4 fixture'];
    public bool $timeout = false;

    protected function resolve(string $host): array { $this->resolutions++; return $this->addresses; }
    protected function transfer(array $options): array
    {
        $this->transfers++;
        $this->options = $options;
        if ($this->timeout) throw new RuntimeException('Simulated transport timeout');
        foreach ($this->headers as $header) {
            if ($options[CURLOPT_HEADERFUNCTION](null, $header) !== strlen($header)) throw new RuntimeException('Header limit enforced');
        }
        foreach ($this->chunks as $chunk) {
            if ($options[CURLOPT_WRITEFUNCTION](null, $chunk) !== strlen($chunk)) throw new RuntimeException('Body limit enforced');
        }
        return [$this->status, $this->peer];
    }
}

$checks = 0;
function checkDownload(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function denyDownload(UrlDownloadFixture $fixture, string $url, int $limit = 10240000): void
{
    $before = glob(sys_get_temp_dir() . '/dewawi-url-*');
    try {
        $download = $fixture->receive($url, $limit);
        unlink($download['path']);
        throw new LogicException('Unsafe download accepted: ' . $url);
    } catch (InvalidArgumentException | RuntimeException $exception) {
        checkDownload(glob(sys_get_temp_dir() . '/dewawi-url-*') === $before, 'Failed transfer left a temporary file');
    }
}

foreach (['127.0.0.1', '127.1', '2130706433', '0177.0.0.1', '0x7f000001', '10.1.2.3', '172.16.0.1', '192.168.1.1', '169.254.169.254', '100.100.100.200', '100.64.0.1', '0.0.0.0', '224.0.0.1', '255.255.255.255', '192.0.2.1', '198.18.0.1', '198.51.100.1', '203.0.113.1', '[::1]', '[::]', '[::ffff:127.0.0.1]', '[::ffff:8.8.8.8]', '[fc00::1]', '[fe80::1]', '[ff02::1]', '[64:ff9b::7f00:1]', '[2001:db8::1]', '[2002:7f00:1::1]', '[2001::1]'] as $host) {
    $fixture = new UrlDownloadFixture();
    denyDownload($fixture, 'http://' . $host . '/file.pdf');
    checkDownload($fixture->transfers === 0 && $fixture->resolutions === 0, 'Unsafe literal caused network resolution or transfer');
}
foreach (['file:///etc/passwd', 'ftp://safe.test/file.pdf', 'gopher://safe.test/file.pdf', 'http://localhost/file.pdf', 'http://safe.test:8080/file.pdf', 'https://safe.test:80/file.pdf', 'http://user:pass@safe.test/file.pdf', "http://safe.test/\r\nfile.pdf", 'http://safe.test/evil%2ffile.pdf', 'http://safe.test/.htaccess', 'http://safe.test/file.pdf#fragment', 'http://safe.test/file.pdf?x=\\'] as $url) {
    $fixture = new UrlDownloadFixture();
    denyDownload($fixture, $url);
    checkDownload($fixture->transfers === 0, 'Malformed URL reached transport');
}
foreach ([[], ['127.0.0.1'], ['10.0.0.1'], ['169.254.169.254'], ['::1'], ['::ffff:127.0.0.1'], ['8.8.8.8', '192.168.1.1'], ['8.8.8.8', 'fc00::1']] as $addresses) {
    $fixture = new UrlDownloadFixture();
    $fixture->addresses = $addresses;
    denyDownload($fixture, 'https://safe.test/file.pdf');
    checkDownload($fixture->transfers === 0, 'Unsafe DNS set reached transport');
}

foreach (['8.8.8.8', '2606:4700:4700::1111'] as $ip) {
    $fixture = new UrlDownloadFixture();
    $fixture->addresses = [$ip];
    $fixture->peer = $ip;
    $download = $fixture->receive('https://safe.test/file.pdf?download=1');
    try {
        checkDownload($download['name'] === 'file.pdf' && file_get_contents($download['path']) === '%PDF-1.4 fixture', 'Valid public transfer lost bytes or filename');
        checkDownload($fixture->resolutions === 1 && $fixture->transfers === 1, 'DNS rebinding caused a second resolution');
        checkDownload($fixture->options[CURLOPT_RESOLVE] === ['safe.test:443:' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)], 'Validated address was not pinned');
        checkDownload($fixture->options[CURLOPT_SSL_VERIFYPEER] && $fixture->options[CURLOPT_SSL_VERIFYHOST] === 2 && $fixture->options[CURLOPT_PROXY] === '', 'TLS or proxy bypass permitted');
        checkDownload(!$fixture->options[CURLOPT_FOLLOWLOCATION] && $fixture->options[CURLOPT_MAXREDIRS] === 0, 'Redirect following enabled');
        checkDownload($fixture->options[CURLOPT_TIMEOUT_MS] > 0 && $fixture->options[CURLOPT_TIMEOUT_MS] <= 15000 && $fixture->options[CURLOPT_CONNECTTIMEOUT] === 5 && $fixture->options[CURLOPT_LOW_SPEED_TIME] === 5, 'Timeout or slow transfer limit missing');
        checkDownload($fixture->options[CURLOPT_MAXFILESIZE_LARGE] === 10240000, 'Declared size limit missing');
    } finally { unlink($download['path']); }
}
foreach ([301, 302, 307, 308, 404, 500] as $status) {
    $fixture = new UrlDownloadFixture();
    $fixture->status = $status;
    denyDownload($fixture, 'https://safe.test/file.pdf');
    checkDownload($fixture->transfers === 1, 'Redirect triggered an extra transfer');
}
foreach (['127.0.0.1', '169.254.169.254', '1.1.1.1', '::1'] as $peer) {
    $fixture = new UrlDownloadFixture();
    $fixture->peer = $peer;
    denyDownload($fixture, 'https://safe.test/file.pdf');
}
foreach ([['headers' => ["Content-Length: 21\r\n"]], ['headers' => [str_repeat('x', 65537)]], ['chunks' => [str_repeat('x', 21)]], ['chunks' => [str_repeat('x', 12), str_repeat('y', 12)]], ['chunks' => []], ['timeout' => true]] as $options) {
    $fixture = new UrlDownloadFixture();
    foreach ($options as $key => $value) $fixture->$key = $value;
    denyDownload($fixture, 'https://safe.test/file.pdf', 20);
}
foreach ([0, 10240001] as $limit) denyDownload(new UrlDownloadFixture(), 'https://safe.test/file.pdf', $limit);
$fixture = new UrlDownloadFixture();
$download = $fixture->receive('https://SAFE.TEST:443/file.pdf');
checkDownload($fixture->options[CURLOPT_URL] === 'https://safe.test/file.pdf' && $fixture->options[CURLOPT_RESOLVE] === ['safe.test:443:8.8.8.8'], 'URL and pinned hostname differ');
unlink($download['path']);
echo "PASS: $checks URL download checks; DNS and HTTP transport fully isolated\n";
