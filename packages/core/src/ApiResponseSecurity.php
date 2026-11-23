<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Immutable JSON-only HTTP response policy; do not apply the restrictive CSP to the HTML client. */
final class ApiResponseSecurity
{
    /** @return array<string,string> */
    public static function headers(bool $https): array
    {
        $headers=[
            'Content-Type'=>'application/json; charset=utf-8',
            'Cache-Control'=>'no-store, private',
            'Pragma'=>'no-cache',
            'X-Content-Type-Options'=>'nosniff',
            'Referrer-Policy'=>'no-referrer',
            'X-Frame-Options'=>'DENY',
            'Content-Security-Policy'=>"default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; object-src 'none'",
            'Permissions-Policy'=>'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Resource-Policy'=>'same-origin',
            'X-Permitted-Cross-Domain-Policies'=>'none'
        ];
        if ($https) $headers['Strict-Transport-Security']='max-age=31536000';
        return $headers;
    }

    public static function emit(bool $https): void
    {
        foreach (self::headers($https) as $name=>$value) header($name.': '.$value);
    }
}
