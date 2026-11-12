<?php

namespace App\Core\Support;

final class MailHostGuard
{
    /**
     * null = host theek hai, warna user ko dikhane wala error message.
     * Hostname resolve hota hai aur har resolved IP check hoti hai.
     */
    public static function error(string $host): ?string
    {
        $host = trim($host);
        if ($host === '') {
            return 'Host is required.';
        }

        $ips = self::resolve($host);
        if ($ips === []) {
            return 'Host could not be resolved.';
        }

        foreach ($ips as $ip) {
            if (self::blocked($ip)) {
                return "Host resolves to a blocked address ({$ip}).";
            }
        }

        return null;
    }

    /** @return string[] */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        foreach ((array) @gethostbynamel($host) as $ip) {
            $ips[] = $ip;
        }
        foreach ((array) @dns_get_record($host, DNS_AAAA) as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    private static function blocked(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return self::blockedV4($ip);
        }

        $bin = @inet_pton($ip);
        if ($bin === false || strlen($bin) !== 16) {
            return true;                       // samajh na aaye to block
        }

        if ($bin === str_repeat("\x00", 16)) {
            return true;                       // ::
        }
        if ($bin === str_repeat("\x00", 15)."\x01") {
            return true;                       // ::1
        }
        if (ord($bin[0]) === 0xFE && (ord($bin[1]) & 0xC0) === 0x80) {
            return true;                       // fe80::/10 (link-local + metadata)
        }
        if (substr($bin, 0, 10) === str_repeat("\x00", 10) && substr($bin, 10, 2) === "\xFF\xFF") {
            return self::blockedV4((string) inet_ntop(substr($bin, 12)));   // ::ffff:a.b.c.d
        }
        if ((ord($bin[0]) & 0xFE) === 0xFC) {
            return self::privateBlocked();     // fc00::/7 unique local
        }

        return false;
    }

    private static function blockedV4(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return true;
        }

        // 0/8, loopback, link-local (169.254.169.254 cloud metadata isi mein hai)
        $always = [['0.0.0.0', '0.255.255.255'], ['127.0.0.0', '127.255.255.255'], ['169.254.0.0', '169.254.255.255']];
        foreach ($always as [$from, $to]) {
            if ($long >= ip2long($from) && $long <= ip2long($to)) {
                return true;
            }
        }

        if (self::privateBlocked()) {
            $private = [['10.0.0.0', '10.255.255.255'], ['172.16.0.0', '172.31.255.255'], ['192.168.0.0', '192.168.255.255']];
            foreach ($private as [$from, $to]) {
                if ($long >= ip2long($from) && $long <= ip2long($to)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Private ranges sirf production mein block hote hain, aur config se allow kiye ja sakte hain */
    private static function privateBlocked(): bool
    {
        return app()->environment('production') && ! config('email.allow_private_hosts', true);
    }
}
