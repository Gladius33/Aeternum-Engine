<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * Read-only aggregate for operator decisions. Never returns logins, actors,
 * invitation hashes, recovery secrets, addresses or session tokens.
 */
final class PdoOnboardingStatus
{
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION
            || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES))
            throw new \InvalidArgumentException('Require native PDO MySQL exceptions');
    }

    public function snapshot(string $world): array
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world))
            throw new \InvalidArgumentException('Invalid world ID');
        $now=$this->clock->now();
        if ($now<0) throw new \DomainException('Invalid clock');
        // Snapshot is an operationally useful near-live aggregate. The three
        // SELECTs are deliberately nonlocking; no consistent point-in-time
        // guarantee is implied while other PHP workers write.
        $s=$this->pdo->prepare(
            "SELECT COALESCE(SUM(status='ready'),0) ready,
                    COALESCE(SUM(status='pending'),0) pending,
                    COALESCE(SUM(status='pending' AND created_at<=?),0) stale_pending,
                    MIN(CASE WHEN status='pending' THEN created_at ELSE NULL END) oldest_pending
               FROM engine_onboarding WHERE world_id=?"
        );
        $s->execute([max(0,$now-900),$world]);
        $on=$s->fetch(PDO::FETCH_ASSOC);
        $s=$this->pdo->prepare(
            "SELECT COALESCE(SUM(actor_id IS NOT NULL),0) claimed,
                    COALESCE(SUM(actor_id IS NULL AND revoked=0 AND expires_at>?),0) available,
                    COALESCE(SUM(actor_id IS NULL AND revoked=1),0) revoked,
                    COALESCE(SUM(actor_id IS NULL AND revoked=0 AND expires_at<=?),0) expired
               FROM engine_signup_invitations WHERE world_id=?"
        );
        $s->execute([$now,$now,$world]);
        $in=$s->fetch(PDO::FETCH_ASSOC);
        $s=$this->pdo->prepare(
            'SELECT COUNT(*) FROM engine_sessions s
             INNER JOIN engine_onboarding o ON o.actor_id=s.actor_id
             WHERE o.world_id=? AND s.revoked=0 AND s.expires_at>?'
        );
        $s->execute([$world,$now]);
        $sessions=(int)$s->fetchColumn();
        return [
            'world'=>$world,'observedAt'=>$now,
            'enrollment'=>[
                'ready'=>(int)$on['ready'],
                'pending'=>(int)$on['pending'],
                'pendingOver15Minutes'=>(int)$on['stale_pending'],
                'oldestPendingAgeSeconds'=>$on['oldest_pending']===null?null:max(0,$now-(int)$on['oldest_pending'])
            ],
            'invitations'=>[
                'claimed'=>(int)$in['claimed'],
                'available'=>(int)$in['available'],
                'revoked'=>(int)$in['revoked'],
                'expired'=>(int)$in['expired']
            ],
            'activeSessions'=>$sessions
        ];
    }
}
