<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * Trusted operator-only moderation service. Not routed through the public API.
 * The CLI caller must authenticate as an authorized server operator externally.
 */
final class PdoSocialModerator
{
    public function __construct(
        private readonly PDO $pdo,private readonly string $world,private readonly Clock $clock
    ) {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world))
            throw new \InvalidArgumentException('Invalid world');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION
            || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES))
            throw new \InvalidArgumentException('Native MySQL PDO required');
    }
    private function query(string $sql,array $args): \PDOStatement
    {
        $stmt=$this->pdo->prepare($sql);
        $stmt->execute($args);
        return $stmt;
    }
    public function review(string $operator,string $report,string $action,string $reason): void
    {
        PdoMemberships::validateId($operator);
        PdoMemberships::validateId($report);
        if (!in_array($action,['dismiss','hide'],true))
            throw new \InvalidArgumentException('Invalid moderation action');
        if (trim($reason)==='' || strlen($reason)>255 || preg_match('//u',$reason)!==1)
            throw new \InvalidArgumentException('Invalid moderation reason');
        $now=$this->clock->now();
        if ($now<0) throw new \DomainException('Invalid clock');
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested moderation transaction');
        $this->pdo->beginTransaction();
        try {
            $row=$this->query('SELECT message_id,status FROM engine_social_reports
                WHERE world_id=? AND report_id=? FOR UPDATE',[$this->world,$report])->fetch(PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('Report unavailable');
            $expected=$action==='hide'?'hidden':'dismissed';
            if ($row['status']!=='open') {
                if ($row['status']!==$expected) throw new \DomainException('Report already decided');
                $previous=$this->query('SELECT operator_id,reason FROM engine_social_moderation_audit
                    WHERE world_id=? AND report_id=?',[$this->world,$report])->fetch(PDO::FETCH_ASSOC);
                if ($previous===false || $previous['operator_id']!==$operator || $previous['reason']!==$reason)
                    throw new \DomainException('Conflicting moderation replay');
                $this->pdo->commit();
                return;
            }
            if ($action==='hide') {
                $this->query('UPDATE engine_social_messages SET hidden=1
                    WHERE world_id=? AND message_id=?',[$this->world,$row['message_id']]);
            }
            $this->query('UPDATE engine_social_reports SET status=?
                WHERE world_id=? AND report_id=?',[$expected,$this->world,$report]);
            $audit=hash('sha256',$this->world.'|report|'.$report);
            $this->query('INSERT INTO engine_social_moderation_audit
                (world_id,audit_id,report_id,operator_id,action,reason,created_at)
                VALUES (?,?,?,?,?,?,?)',[$this->world,$audit,$report,$operator,$action,$reason,$now]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
    /** Operator-only, bounded report queue. Never exposed as a player route. */
    public function openReports(int $limit=50): array
    {
        if ($limit<1 || $limit>100) throw new \InvalidArgumentException('Invalid page size');
        $rows=$this->query("SELECT r.report_id,r.reporter_actor,r.message_id,r.reason,r.created_at,
                m.body,m.sender_actor,m.recipient_actor
            FROM engine_social_reports r
            JOIN engine_social_messages m ON m.world_id=r.world_id AND m.message_id=r.message_id
            WHERE r.world_id=? AND r.status='open'
            ORDER BY r.created_at,r.report_id LIMIT ".($limit+1),[$this->world])->fetchAll(PDO::FETCH_ASSOC);
        $more=count($rows)>$limit;
        if ($more) array_pop($rows);
        return ['reports'=>$rows,'truncated'=>$more];
    }
}
