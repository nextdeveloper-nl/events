<?php

namespace NextDeveloper\Events\Pushers\Support;

use InvalidArgumentException;

/**
 * Guards outbound pusher requests against SSRF.
 *
 * Event pushers let an admin point the platform at an arbitrary URL, so every send must be checked, not just
 * the URL saved on the pusher: DNS can change between save time and send time. resolve() looks the host up
 * right now, rejects private / loopback / link-local / reserved addresses and returns the validated IP so the
 * caller can pin the connection to it (no second lookup = no DNS rebinding window).
 */
class SsrfGuard
{
    /**
     * Validates the URL and returns the pinned connection info.
     *
     * @return array{host: string, port: int, ip: string}
     *
     * @throws InvalidArgumentException When the URL is unsafe or cannot be resolved.
     */
    public static function resolve(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            throw new InvalidArgumentException('Pusher URL is not a valid absolute URL.');
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme !== 'https' && !($scheme === 'http' && config('events.pushers.allow_insecure_http', false))) {
            throw new InvalidArgumentException('Pusher URL must use https.');
        }

        $host = trim($parts['host'], '[]');
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        $ips = self::lookup($host);

        if (empty($ips)) {
            throw new InvalidArgumentException('Pusher host could not be resolved: ' . $host);
        }

        // Every address the host resolves to must be public, otherwise an attacker could mix a public and a
        // private record and hope we connect to the private one.
        if (!config('events.pushers.allow_private_hosts', false)) {
            foreach ($ips as $ip) {
                if (!self::isPublic($ip)) {
                    throw new InvalidArgumentException('Pusher host resolves to a non-public address, blocked.');
                }
            }
        }

        return ['host' => $host, 'port' => (int) $port, 'ip' => $ips[0]];
    }

    /**
     * Guzzle options that pin the connection to the validated IP and disable redirects (a redirect could
     * bounce us to an internal address after the check).
     */
    public static function httpOptions(array $pinned): array
    {
        $ip = str_contains($pinned['ip'], ':') ? '[' . $pinned['ip'] . ']' : $pinned['ip'];

        return [
            'allow_redirects' => false,
            'curl'            => [
                CURLOPT_RESOLVE => [$pinned['host'] . ':' . $pinned['port'] . ':' . $ip],
            ],
        ];
    }

    /**
     * FILTER_FLAG_NO_PRIV_RANGE + NO_RES_RANGE cover 10/8, 172.16/12, 192.168/16, 127/8, 169.254/16, 0/8,
     * 240/4 and the IPv6 equivalents (::1, fc00::/7, fe80::/10).
     */
    private static function isPublic(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * @return string[]
     */
    private static function lookup(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        foreach (dns_get_record($host, DNS_A) ?: [] as $record) {
            $ips[] = $record['ip'];
        }

        foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ipv6'];
        }

        return $ips;
    }
}
