<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * Read-only, CLI-only operational state. No tokens, credentials, raw emails,
 * IPs, payloads, private player IDs or SQL error strings are returned.
 */
final class OperationsSnapshot
{
    private const CAP = 10000;
    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
        private readonly string $migrationDirectory
    ) {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION
            || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES))
            throw new \InvalidArgumentException('Native PDO MySQL required');
    }

    /** @return array<string,mixed> */
    public function snapshot(array $configuredWorlds,array $approvedRules=[]): array
    {
        WorldRuntime::assertBindings($configuredWorlds,$approvedRules);
        if ($this->pdo->inTransaction()) throw new \LogicException('Snapshot within transaction');
        if (count($configuredWorlds)>32) throw new \InvalidArgumentException('Too many configured worlds');
        $now=$this->clock->now();
        if ($now<0) throw new \DomainException('Invalid clock');
        $files=glob(rtrim($this->migrationDirectory,'/').'/*.sql') ?: [];
        sort($files,SORT_STRING);
        if ($files===[]) throw new \RuntimeException('Missing migration directory');
        $known=$this->pdo->query('SELECT filename,digest,completed FROM engine_schema_migrations ORDER BY filename')->fetchAll(PDO::FETCH_ASSOC);
        $schemaGood=count($files)===count($known);
        $dirty=0;
        foreach ($known as $index=>$row) {
            if ((int)$row['completed']!==1) $dirty++;
            if (!isset($files[$index])
                || $row['filename']!==basename($files[$index])
                || !hash_equals((string)$row['digest'],hash_file('sha256',$files[$index]) ?: '')) {
                $schemaGood=false;
            }
        }
        if ($dirty>0) $schemaGood=false;
        $worlds=[];
        foreach ($configuredWorlds as $id=>$pack) {
            if (!is_string($id)
                || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$id)
                || !$pack instanceof GamePack)
                throw new \InvalidArgumentException('Invalid approved world');
            $key=$this->statement('SELECT rules_key FROM engine_worlds WHERE world_id=?',[$id])->fetchColumn();
            $verified=$key!==false&&hash_equals(($approvedRules[$id]??$pack->rules())->key,(string)$key);
            if ($key===false) {
                $worlds[]=['world'=>$id,'initialized'=>false,'rulesVerified'=>false,'dueEvents'=>0,
                    'dueEventsCapped'=>false,'oldestDueSeconds'=>null,'outboxPending'=>0];
                continue;
            }
            $due=$this->bounded('SELECT event_id FROM engine_events
                WHERE world_id=? AND completed=0 AND due_at<=? ORDER BY due_at,sequence_no LIMIT 10001',
                [$id,$now]);
            $oldest=$this->statement('SELECT MIN(due_at) FROM engine_events
                WHERE world_id=? AND completed=0 AND due_at<=?',[$id,$now])->fetchColumn();
            $pending=$this->bounded('SELECT journal_no FROM engine_outbox
                WHERE world_id=? AND delivered_at IS NULL LIMIT 10001',[$id]);
            $worlds[]=[
                'world'=>$id,'initialized'=>true,'rulesVerified'=>$verified,
                'dueEvents'=>min($due,self::CAP),
                'dueEventsCapped'=>$due>self::CAP,
                'oldestDueSeconds'=>$oldest===null?null:max(0,$now-(int)$oldest),
                'outboxPending'=>min($pending,self::CAP),
                'outboxPendingCapped'=>$pending>self::CAP
            ];
        }
        $active=$this->bounded('SELECT token_hash FROM engine_sessions
            WHERE revoked=0 AND expires_at>? LIMIT 10001',[$now]);
        $expired=$this->bounded('SELECT token_hash FROM engine_sessions
            WHERE expires_at<=? LIMIT 10001',[$now]);
        $pendingOnboarding=$this->bounded("SELECT actor_id FROM engine_onboarding
            WHERE status='pending' LIMIT 10001",[]);
        return [
            'schemaVersion'=>1,'checkedAt'=>$now,
            'migrations'=>[
                'expected'=>count($files),'applied'=>count($known),
                'dirty'=>$dirty,'verified'=>$schemaGood
            ],
            'sessions'=>['active'=>min($active,self::CAP),'expired'=>min($expired,self::CAP),
                'capped'=>$active>self::CAP || $expired>self::CAP],
            'pendingOnboarding'=>min($pendingOnboarding,self::CAP),
            'pendingOnboardingCapped'=>$pendingOnboarding>self::CAP,
            'worlds'=>$worlds,
            'healthy'=>$schemaGood && $worlds!==[] && !in_array(false,array_column($worlds,'initialized'),true)
                && !in_array(false,array_column($worlds,'rulesVerified'),true)
        ];
    }

    private function statement(string $sql,array $params): \PDOStatement
    {
        $s=$this->pdo->prepare($sql);
        $s->execute($params);
        return $s;
    }

    private function bounded(string $sql,array $params): int
    {
        $s=$this->statement($sql,$params);
        $n=0;
        while ($s->fetch(PDO::FETCH_NUM)!==false) $n++;
        $s->closeCursor();
        return $n;
    }
}
