<?php

namespace App\Services\Copilot;

use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;
use fivefilters\Readability\Readability;
use Illuminate\Support\Facades\Http;

/**
 * Fetch a web page and return its clean, readable main text.
 *
 * SECURITY (SSRF): fetching a model/attacker-supplied URL is a classic SSRF
 * sink. Defence, deny-by-default:
 *  - only http/https schemes;
 *  - every resolved IP of the host must be publicly routable (private,
 *    loopback, link-local incl. 169.254.169.254 cloud metadata, and reserved
 *    ranges are refused);
 *  - redirects are followed MANUALLY, re-validating each hop (max N);
 *  - the connection is pinned to the validated IP (CURLOPT_RESOLVE) to close
 *    the DNS-rebinding window;
 *  - response body is size-capped while streaming; only text/html + text/plain
 *    are parsed.
 */
class WebFetch
{
    /**
     * @return array<string,mixed>  {ok, url, title, content} or {ok:false, url, error}
     */
    public function fetch(string $url): array
    {
        try {
            $current = $url;
            $redirects = 0;
            $maxRedirects = (int) config('copilot.web.max_redirects', 3);

            while (true) {
                $safe = $this->resolveSafe($current);

                $response = Http::timeout((int) config('copilot.web.fetch_timeout', 15))
                    ->withHeaders(['User-Agent' => 'TakeOneCoach/1.0 (+https://takeone.bh)'])
                    ->withOptions([
                        'allow_redirects' => false,
                        'stream' => true,
                        'curl' => [CURLOPT_RESOLVE => ["{$safe['host']}:{$safe['port']}:{$safe['ip']}"]],
                    ])
                    ->get($current);

                $status = $response->status();
                if ($status >= 300 && $status < 400 && $response->header('Location')) {
                    if (++$redirects > $maxRedirects) {
                        return ['ok' => false, 'url' => $url, 'error' => 'Too many redirects.'];
                    }
                    $current = $this->absoluteUrl($current, $response->header('Location'));

                    continue;
                }

                if ($status >= 400) {
                    return ['ok' => false, 'url' => $current, 'error' => 'The page returned HTTP '.$status.'.'];
                }

                $contentType = strtolower((string) $response->header('Content-Type'));
                $isHtml = str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml');
                $isText = str_contains($contentType, 'text/plain');
                if (! $isHtml && ! $isText) {
                    return ['ok' => false, 'url' => $current, 'error' => 'Unsupported content type ('.($contentType ?: 'unknown').').'];
                }

                $body = $this->readCapped($response);

                return $isHtml
                    ? $this->extractHtml($current, $body)
                    : ['ok' => true, 'url' => $current, 'title' => '', 'content' => $this->trimText($body)];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'url' => $url, 'error' => $e->getMessage()];
        }
    }

    /** Validate scheme/host/IPs and return the host+port+pinned IP to connect to. */
    private function resolveSafe(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException('Only http and https URLs are allowed.');
        }

        $host = trim($parts['host'] ?? '', '[]'); // unwrap IPv6 literals
        if ($host === '') {
            throw new \RuntimeException('Invalid URL.');
        }

        $blocked = array_map('strtolower', (array) config('copilot.web.blocked_hosts', []));
        if (in_array(strtolower($host), $blocked, true)) {
            throw new \RuntimeException('This host is blocked.');
        }

        $ips = $this->resolveIps($host);
        if ($ips === []) {
            throw new \RuntimeException('Could not resolve host.');
        }
        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new \RuntimeException('Refusing to fetch a private or reserved address.');
            }
        }

        return [
            'host' => $host,
            'port' => $parts['port'] ?? ($scheme === 'https' ? 443 : 80),
            'ip' => $ips[0],
        ];
    }

    /** @return array<int,string> */
    private function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = array_merge($ips, $v4);
        }
        $aaaa = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $rec) {
                if (! empty($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /** Read the streamed body up to the byte cap so a huge page can't OOM us. */
    private function readCapped($response): string
    {
        $max = (int) config('copilot.web.max_bytes', 3_000_000);
        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $stream->eof() && strlen($buffer) < $max) {
            $buffer .= $stream->read(16384);
        }
        $stream->close();

        return $buffer;
    }

    private function extractHtml(string $url, string $html): array
    {
        try {
            $config = (new Configuration())
                ->setFixRelativeURLs(true)
                ->setOriginalURL($url);

            $readability = new Readability($config);
            $readability->parse($html);

            return [
                'ok' => true,
                'url' => $url,
                'title' => (string) $readability->getTitle(),
                'content' => $this->trimText(strip_tags((string) $readability->getContent())),
            ];
        } catch (ParseException $e) {
            // Fall back to a raw strip when readability can't find an article.
            return [
                'ok' => true,
                'url' => $url,
                'title' => '',
                'content' => $this->trimText(strip_tags($html)),
            ];
        }
    }

    private function trimText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $text = trim((string) $text);

        return mb_substr($text, 0, (int) config('copilot.web.max_chars', 12000));
    }

    private function absoluteUrl(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $p = parse_url($base);
        $scheme = $p['scheme'] ?? 'https';
        $host = $p['host'] ?? '';
        $port = isset($p['port']) ? ':'.$p['port'] : '';

        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$host}{$port}{$location}";
        }

        $path = $p['path'] ?? '/';
        $dir = rtrim(substr($path, 0, strrpos($path, '/') ?: 0), '/');

        return "{$scheme}://{$host}{$port}{$dir}/{$location}";
    }
}
