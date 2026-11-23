<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * M09 private player mail and durable moderation primitives.
 * Every world/player is resolved by the HTTP layer; caller cannot substitute a
 * sender. Membership rows are locked in actor-id order to serialize blocks and
 * sends, and all changes (including notifications) commit together.
 */
final class PdoSocialMessages
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $world,
        private readonly Clock $clock
    ) {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world))
            throw new \InvalidArgumentException('Invalid world');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION
            || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES))
            throw new \InvalidArgumentException('Native MySQL PDO required');
    }
    private function sql(string $sql,array $params=[]): \PDOStatement
    {
        $q=$this->pdo->prepare($sql);
        $q->execute($params);
        return $q;
    }
    private function tx(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested social transaction');
        $this->pdo->beginTransaction();
        try {
            $result=$callback();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
    private function timestamp(): int
    {
        $now=$this->clock->now();
        if ($now<0) throw new \DomainException('Invalid clock');
        return $now;
    }
    /** Locks all relevant membership records with a consistent primary-key order. */
    private function requireActors(string ...$actors): void
    {
        $ids=array_values(array_unique($actors));
        sort($ids,SORT_STRING);
        foreach ($ids as $id) {
            PdoMemberships::validateId($id);
            if ($this->sql('SELECT actor_id FROM engine_memberships
                WHERE world_id=? AND actor_id=? AND active=1 FOR UPDATE',[$this->world,$id])
                ->fetchColumn()===false) throw new \DomainException('Membership required');
        }
    }
    private function validText(string $body,int $min,int $max): void
    {
        if (strlen($body)<$min || strlen($body)>$max || trim($body)===''
            || preg_match('//u',$body)!==1
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$body))
            throw new \InvalidArgumentException('Invalid text');
    }
    public function block(string $actor,string $target): void
    {
        if ($actor===$target) throw new \InvalidArgumentException('Cannot block self');
        $this->tx(function() use($actor,$target):void {
            $this->requireActors($actor,$target);
            $this->sql('INSERT IGNORE INTO engine_social_blocks(world_id,actor_id,blocked_actor,created_at)
                VALUES (?,?,?,?)',[$this->world,$actor,$target,$this->timestamp()]);
        });
    }
    public function unblock(string $actor,string $target): void
    {
        PdoMemberships::validateId($target);
        $this->tx(function() use($actor,$target):void {
            $this->requireActors($actor);
            $this->sql('DELETE FROM engine_social_blocks WHERE world_id=? AND actor_id=? AND blocked_actor=?',
                [$this->world,$actor,$target]);
        });
    }
    public function blocksFor(string $actor): array
    {
        return $this->tx(function()use($actor):array {
            $this->requireActors($actor);
            return $this->sql('SELECT blocked_actor FROM engine_social_blocks
                WHERE world_id=? AND actor_id=? ORDER BY blocked_actor LIMIT 101',
                [$this->world,$actor])->fetchAll(PDO::FETCH_COLUMN);
        });
    }
    public function send(string $sender,string $recipient,string $messageId,string $body): void
    {
        PdoMemberships::validateId($messageId);
        if ($sender===$recipient) throw new \DomainException('Cannot message self');
        $this->validText($body,1,2000);
        $this->tx(function() use($sender,$recipient,$messageId,$body):void {
            $this->requireActors($sender,$recipient);
            $old=$this->sql('SELECT sender_actor,recipient_actor,body FROM engine_social_messages
                WHERE world_id=? AND message_id=? FOR UPDATE',[$this->world,$messageId])->fetch(PDO::FETCH_ASSOC);
            if ($old!==false) {
                if ($old!==['sender_actor'=>$sender,'recipient_actor'=>$recipient,'body'=>$body])
                    throw new \DomainException('Conflicting message ID');
                return; // Exact durable replay: never send a second notification.
            }
            $blocked=$this->sql('SELECT 1 FROM engine_social_blocks WHERE world_id=?
                AND ((actor_id=? AND blocked_actor=?) OR (actor_id=? AND blocked_actor=?))
                LIMIT 1',[$this->world,$sender,$recipient,$recipient,$sender])->fetchColumn();
            if ($blocked!==false) throw new \DomainException('Messaging blocked');
            $now=$this->timestamp();
            $this->sql('INSERT INTO engine_social_messages
                (world_id,message_id,sender_actor,recipient_actor,body,created_at)
                VALUES (?,?,?,?,?,?)',[$this->world,$messageId,$sender,$recipient,$body,$now]);
            $notice=hash('sha256',$this->world.'|message|'.$messageId);
            $this->sql('INSERT INTO engine_social_notifications
                (world_id,notification_id,actor_id,kind,subject_id,created_at)
                VALUES (?,?,?,?,?,?)',[$this->world,$notice,$recipient,'message',$messageId,$now]);
        });
    }
    /**
     * Cursor is the last visible (timestamp, ID) pair, ordered newest first.
     * Both values are required together; the database filters authorization
     * and hidden/blocked messages BEFORE applying the keyset boundary.
     */
    private function validateInboxCursor(?int $beforeTime, ?string $beforeId): void
    {
        if (($beforeTime===null)!==($beforeId===null))
            throw new \InvalidArgumentException('Incomplete inbox cursor');
        if ($beforeTime!==null) {
            if ($beforeTime<0) throw new \InvalidArgumentException('Negative cursor timestamp');
            PdoMemberships::validateId($beforeId);
        }
    }

    public function inbox(string $actor, ?int $beforeTime=null, ?string $beforeId=null): array
    {
        $this->validateInboxCursor($beforeTime,$beforeId);
        return $this->tx(function()use($actor,$beforeTime,$beforeId):array {
            $this->requireActors($actor);
            $params=[$this->world,$actor,$actor];
            $query='SELECT m.message_id,m.sender_actor,m.body,m.created_at
                FROM engine_social_messages m WHERE m.world_id=? AND m.recipient_actor=?
                  AND m.hidden=0 AND NOT EXISTS (
                    SELECT 1 FROM engine_social_blocks b
                    WHERE b.world_id=m.world_id AND b.actor_id=? AND b.blocked_actor=m.sender_actor
                  )';
            if ($beforeTime!==null) {
                $query.=' AND (m.created_at < ? OR (m.created_at = ? AND m.message_id < ?))';
                array_push($params,$beforeTime,$beforeTime,$beforeId);
            }
            $query.=' ORDER BY m.created_at DESC,m.message_id DESC LIMIT 51';
            $rows=$this->sql($query,$params)->fetchAll(PDO::FETCH_ASSOC);
            $more=count($rows)>50;
            if ($more) array_pop($rows);
            $last=$more?$rows[count($rows)-1]:null;
            return ['messages'=>array_map(static fn(array $m):array=>[
                'id'=>$m['message_id'],'from'=>$m['sender_actor'],'text'=>$m['body'],
                'createdAt'=>(int)$m['created_at']
            ],$rows),'truncated'=>$more,
                'nextAfter'=>$last===null?null:[
                    'createdAt'=>(int)$last['created_at'],'id'=>$last['message_id']]];
        });
    }

    public function notifications(string $actor, ?int $beforeTime=null, ?string $beforeId=null): array
    {
        $this->validateInboxCursor($beforeTime,$beforeId);
        return $this->tx(function() use($actor,$beforeTime,$beforeId):array {
            $this->requireActors($actor);
            $params=[$this->world,$actor];
            $query='SELECT notification_id,kind,subject_id,created_at,read_at
                FROM engine_social_notifications
                WHERE world_id=? AND actor_id=?';
            if ($beforeTime!==null) {
                $query.=' AND (created_at < ? OR (created_at = ? AND notification_id < ?))';
                array_push($params,$beforeTime,$beforeTime,$beforeId);
            }
            $query.=' ORDER BY created_at DESC,notification_id DESC LIMIT 51';
            $rows=$this->sql($query,$params)->fetchAll(PDO::FETCH_ASSOC);
            $more=count($rows)>50;
            if ($more) array_pop($rows);
            $last=$more?$rows[count($rows)-1]:null;
            return ['notifications'=>array_map(static fn(array $n):array=>[
                'id'=>$n['notification_id'],'kind'=>$n['kind'],'subject'=>$n['subject_id'],
                'createdAt'=>(int)$n['created_at'],
                'readAt'=>$n['read_at']===null?null:(int)$n['read_at']
            ],$rows),'truncated'=>$more,
                'nextAfter'=>$last===null?null:[
                    'createdAt'=>(int)$last['created_at'],'id'=>$last['notification_id']]];
        });
    }

    public function acknowledge(string $actor,string $id): void
    {
        PdoMemberships::validateId($id);
        $this->tx(function()use($actor,$id):void {
            $this->requireActors($actor);
            $row=$this->sql('SELECT read_at FROM engine_social_notifications
                WHERE world_id=? AND notification_id=? AND actor_id=? FOR UPDATE',
                [$this->world,$id,$actor])->fetch(PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('Notification unavailable');
            if ($row['read_at']!==null) return;
            $this->sql('UPDATE engine_social_notifications SET read_at=?
                WHERE world_id=? AND notification_id=? AND actor_id=?',
                [$this->timestamp(),$this->world,$id,$actor]);
        });
    }
    public function report(string $actor,string $reportId,string $messageId,string $reason): void
    {
        PdoMemberships::validateId($reportId);
        PdoMemberships::validateId($messageId);
        $this->validText($reason,5,255);
        $this->tx(function()use($actor,$reportId,$messageId,$reason):void {
            $this->requireActors($actor);
            $message=$this->sql('SELECT sender_actor,recipient_actor FROM engine_social_messages
                WHERE world_id=? AND message_id=? FOR UPDATE',
                [$this->world,$messageId])->fetch(PDO::FETCH_ASSOC);
            if ($message===false || !in_array($actor,[$message['sender_actor'],$message['recipient_actor']],true))
                throw new \DomainException('Message unavailable');
            $old=$this->sql('SELECT reporter_actor,message_id,reason FROM engine_social_reports
                WHERE world_id=? AND report_id=? FOR UPDATE',[$this->world,$reportId])->fetch(PDO::FETCH_ASSOC);
            if ($old!==false) {
                if ($old!==['reporter_actor'=>$actor,'message_id'=>$messageId,'reason'=>$reason])
                    throw new \DomainException('Report ID conflict');
                return;
            }
            $this->sql('INSERT INTO engine_social_reports
                (world_id,report_id,reporter_actor,message_id,reason,created_at)
                VALUES (?,?,?,?,?,?)',
                [$this->world,$reportId,$actor,$messageId,$reason,$this->timestamp()]);
        });
    }
}
