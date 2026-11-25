<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Never emit exception messages, SQL, credentials or request payloads. */
final class ApiErrorPolicy
{
    public static function response(\Throwable $e): array
    {
        if ($e instanceof \PDOException && in_array((int)($e->errorInfo[1]??0),[1205,1213,2006,2013],true))
            return [503,['error'=>'database_busy','retryable'=>true]];
        return [500,['error'=>'internal_error']];
    }

    /** Stable, deliberately low-cardinality diagnostic record. */
    public static function logRecord(\Throwable $e): string
    {
        [$status,$body]=self::response($e);
        return 'aeternum_api status='.$status.' category='.$body['error'];
    }
}
