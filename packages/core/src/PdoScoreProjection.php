<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/** Derived sparse scores. Caller holds the world lock; no independent commits. */
final readonly class PdoScoreProjection
{
    public function __construct(private PDO $pdo,private string $world,private ScoreRules $rules) {}
    private function q(string $sql,array $args): \PDOStatement
    {
        $s=$this->pdo->prepare($sql);$s->execute($args);return $s;
    }
    public function current(int $journal,bool $write=false): bool
    {
        $value=$this->q('SELECT journal_no FROM engine_score_watermarks WHERE world_id=?'.($write?' FOR UPDATE':''),[$this->world])->fetchColumn();
        return $value!==false && (int)$value===$journal;
    }
    private function mark(int $journal): void
    {
        $this->q('INSERT INTO engine_score_watermarks(world_id,journal_no) VALUES (?,?) ON DUPLICATE KEY UPDATE journal_no=VALUES(journal_no)',[$this->world,$journal]);
    }
    private function put(string $account,int $development,int $military): void
    {
        if ($development<0 || $military<0 || $development>ScoreProjector::MAX_SCORE-$military)
            throw new \OverflowException('Invalid projected score');
        $global=$development+$military;
        if ($global===0) {
            $this->q('DELETE FROM engine_account_scores WHERE world_id=? AND account_id=?',[$this->world,$account]);return;
        }
        $this->q('INSERT INTO engine_account_scores(world_id,account_id,development,military,global_score) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE development=VALUES(development),military=VALUES(military),global_score=VALUES(global_score)',
            [$this->world,$account,$development,$military,$global]);
    }
    public function rebuild(array $state,int $journal): void
    {
        if (!$this->pdo->inTransaction()) throw new \LogicException('Score rebuild requires world transaction');
        $expected=(new ScoreDelta($this->rules))->between([],$state,array_keys($state));
        $this->q('DELETE FROM engine_account_scores WHERE world_id=?',[$this->world]);
        foreach ($expected as $account=>[$development,$military]) $this->put((string)$account,$development,$military);
        $this->mark($journal);
    }
    public function sync(array $before,array $after,?array $keys,int $previous): void
    {
        if (!$this->pdo->inTransaction() || $previous===PHP_INT_MAX) throw new \LogicException('Score sync requires bounded world transaction');
        if (!$this->current($previous,true)) {
            // Also handles an older executable advancing the journal during
            // rollback. Never trust a stale projection across code changes.
            $this->rebuild($after,$previous+1);return;
        }
        foreach ((new ScoreDelta($this->rules))->between($before,$after,$keys) as $account=>[$d,$m]) {
            $row=$this->q('SELECT development,military FROM engine_account_scores WHERE world_id=? AND account_id=? FOR UPDATE',
                [$this->world,(string)$account])->fetch(PDO::FETCH_NUM);
            $this->put((string)$account,($row===false?0:(int)$row[0])+$d,($row===false?0:(int)$row[1])+$m);
        }
        $this->mark($previous+1);
    }
    public function score(string $account): array
    {
        $row=$this->q('SELECT development,military,global_score FROM engine_account_scores WHERE world_id=? AND account_id=?',
            [$this->world,$account])->fetch(PDO::FETCH_NUM);
        return ['development'=>$row===false?0:(int)$row[0],'military'=>$row===false?0:(int)$row[1],'global'=>$row===false?0:(int)$row[2]];
    }
    public function rank(string $account): array
    {
        $mine=$this->score($account)['global'];
        $participants=(int)$this->q('SELECT COUNT(*) FROM engine_account_owners o
            JOIN engine_memberships m ON m.world_id=o.world_id AND m.actor_id=o.actor_id AND m.active=1
            WHERE o.world_id=?',[$this->world])->fetchColumn();
        $ahead=(int)$this->q('SELECT COUNT(*) FROM engine_account_scores s
            JOIN engine_account_owners o ON o.world_id=s.world_id AND o.account_id=s.account_id
            JOIN engine_memberships m ON m.world_id=o.world_id AND m.actor_id=o.actor_id AND m.active=1
            WHERE s.world_id=? AND s.global_score>?',[$this->world,$mine])->fetchColumn();
        return ['position'=>$ahead+1,'participants'=>$participants];
    }
    public function assertConsistent(array $state,int $journal): int
    {
        if (!$this->current($journal)) throw new \UnexpectedValueException('Score projection watermark mismatch');
        $expected=(new ScoreDelta($this->rules))->between([],$state,array_keys($state));
        $rows=$this->q('SELECT account_id,development,military,global_score FROM engine_account_scores WHERE world_id=? ORDER BY account_id',[$this->world]);
        $count=0;
        while ($r=$rows->fetch(PDO::FETCH_NUM)) {
            $d=(int)$r[1];$m=(int)$r[2];
            if (($expected[$r[0]]??null)!==[$d,$m] || (int)$r[3]!==$d+$m)
                throw new \UnexpectedValueException('Score projection divergence');
            unset($expected[$r[0]]);$count++;
        }
        if ($expected!==[]) throw new \UnexpectedValueException('Score projection missing rows');
        return $count;
    }
}
