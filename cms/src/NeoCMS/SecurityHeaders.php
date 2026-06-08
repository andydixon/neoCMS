<?php

namespace NeoCMS;

/** Applies consistent browser security and cache-control headers to CMS responses. */
final class SecurityHeaders
{
    /** Protect an HTML administration page, optionally allowing the pinned UI CDNs. */
    public static function html(bool $allowUiCdns = false, ?bool $secureTransport = null): void
    {
        self::common($secureTransport);
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');

        $scriptSources = $allowUiCdns
            ? "'self' https://code.jquery.com"
            : "'self'";
        $styleSources = $allowUiCdns
            ? "'self' 'unsafe-inline' https://code.jquery.com"
            : "'self'";

        header("Content-Security-Policy: default-src 'self'; script-src {$scriptSources}; style-src {$styleSources}; img-src 'self' data: blob: https:; font-src 'self' data:; connect-src 'self'; frame-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    }

    /** Protect JSON API responses and prevent authenticated data from being cached. */
    public static function json(?bool $secureTransport = null): void
    {
        self::common($secureTransport);
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        header('Content-Type: application/json');
    }

    /** Emit headers shared by both document and API responses. */
    private static function common(?bool $secureTransport): void
    {
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        $isHttps = $secureTransport ?? (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        );
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }
}
