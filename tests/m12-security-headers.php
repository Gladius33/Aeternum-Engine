<?php
declare(strict_types=1);
use Aeternum\Core\ApiResponseSecurity;
$http=ApiResponseSecurity::headers(false);
$tls=ApiResponseSecurity::headers(true);
check(!isset($http['Strict-Transport-Security']), 'M12 no HSTS for plaintext responses');
check(($tls['Strict-Transport-Security']??null)==='max-age=31536000','M12 trusted HTTPS HSTS policy');
check(($tls['Content-Type']??null)==='application/json; charset=utf-8','M12 JSON API MIME policy');
check(($tls['Cache-Control']??null)==='no-store, private' && ($tls['Pragma']??null)==='no-cache',
    'M12 sensitive responses are not cacheable');
check(($tls['X-Content-Type-Options']??null)==='nosniff' && ($tls['X-Frame-Options']??null)==='DENY'
    && ($tls['Referrer-Policy']??null)==='no-referrer','M12 anti-sniff, framing and referrer policy');
check(($tls['Cross-Origin-Resource-Policy']??null)==='same-origin',
    'M12 JSON cannot be embedded cross-origin');
foreach (["default-src 'none'","frame-ancestors 'none'","base-uri 'none'","form-action 'none'"] as $directive) {
    check(str_contains($tls['Content-Security-Policy']??'',$directive),'M12 CSP '.$directive);
}
foreach ($tls as $name=>$value) {
    check((bool)preg_match('/^[A-Za-z][A-Za-z-]+$/D',$name)
        && !str_contains($value,"\r") && !str_contains($value,"\n"),'M12 safe header '.$name);
}
