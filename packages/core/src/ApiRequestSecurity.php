<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Early HTTP admission. No proxy/header-derived origin grants authority. */
final class ApiRequestSecurity
{
    /** @return ?array{0:int,1:array{error:string}} */
    public static function reject(array $server): ?array
    {
        $uri=$server['REQUEST_URI']??'';
        $method=$server['REQUEST_METHOD']??'';
        if (!is_string($uri) || strlen($uri)>2048) return [414,['error'=>'uri_too_long']];
        $path=explode('?',$uri,2)[0];
        if (!preg_match('~^/v1(?:/[A-Za-z0-9_.:-]+)*$~D',$path)
            || strlen($path)>512 || preg_match('~(?:^|/)\.{1,2}(?:/|$)~D',$path))
            return [404,['error'=>'not_found']];
        // Same-origin client only. Do not rely on Fetch Metadata as auth:
        // non-browser clients may omit it, and still need valid bearers.
        $site=$server['HTTP_SEC_FETCH_SITE']??'';
        if (!in_array($site,['','same-origin','none'],true))
            return [403,['error'=>'cross_origin_denied']];
        if (!in_array($method,['GET','POST'],true))
            return [405,['error'=>'method_not_allowed']];
        if ($method==='POST') {
            $emptyLogout=in_array($path,['/v1/auth/logout','/v1/auth/logout/all'],true);
            $type=$server['CONTENT_TYPE']??'';
            if (!$emptyLogout && (!is_string($type)
                || !preg_match('~^application/json(?:\s*;\s*charset\s*=\s*utf-8)?$~iD',trim($type))))
                return [415,['error'=>'json_required']];
            $length=$server['CONTENT_LENGTH']??'';
            if ($length!=='' && (!is_string($length) || !preg_match('/^(0|[1-9][0-9]{0,9})$/D',$length)))
                return [400,['error'=>'invalid_content_length']];
            $limit=str_ends_with($path,'/economy/commands')?8192:4096;
            if ($length!=='' && (int)$length>$limit) return [413,['error'=>'too_large']];
        }
        $auth=$server['HTTP_AUTHORIZATION']??'';
        if (!is_string($auth) || strlen($auth)>128) return [401,['error'=>'unauthorized']];
        return null;
    }
}
