<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Shared conservative hold bookkeeping; all methods require a caller-owned
 * transaction and a locked account. Evidence is derived from fetched Stripe
 * objects, not a client-supplied refund amount. */
final class StripeHold
{
    private static function table(string $scope,string $suffix):string{
        if(!in_array($scope,['research','game'],true))throw new \LogicException('Unknown scope');
        return 'wp_stripe_'.$scope.'_'.$suffix;
    }
    private static function q(\PDO $db,string $sql,array $p):\PDOStatement{$q=$db->prepare($sql);$q->execute($p);return $q;}
    public static function fence(\PDO $db,string $pi,bool $reversal):bool{
        if(!$db->inTransaction()||!preg_match('/^pi_[A-Za-z0-9_]{5,100}$/D',$pi))throw new \DomainException('Invalid payment fence');
        self::q($db,'INSERT IGNORE INTO wp_stripe_payment_fence(payment_id,reversed,created_at) VALUES(?,0,?)',[$pi,time()]);
        $v=self::q($db,'SELECT reversed FROM wp_stripe_payment_fence WHERE payment_id=? FOR UPDATE',[$pi])->fetchColumn();
        if($v===false)throw new \LogicException('Payment fence missing');
        if($reversal)self::q($db,'UPDATE wp_stripe_payment_fence SET reversed=1 WHERE payment_id=?',[$pi]);
        return (bool)$v||$reversal;
    }
    public static function observe(\PDO $db,string $scope,string $pi,string $reason,int $refunded,bool $disputed):bool{
        if(!$db->inTransaction()||$refunded<0||(!$disputed&&$refunded===0))throw new \DomainException('Invalid reversal');
        $t=self::table($scope,'holds');
        $h=self::q($db,"SELECT * FROM $t WHERE payment_id=? FOR UPDATE",[$pi])->fetch(\PDO::FETCH_ASSOC);
        if($h===false){
            self::q($db,"INSERT INTO $t(payment_id,reason,created_at,refunded_minor,disputed) VALUES(?,?,?,?,?)",[$pi,$reason,time(),$refunded,(int)$disputed]);
            return true;
        }
        // Duplicate deliveries after an operator resolution do not reopen it.
        // More refunded money or newly observed dispute evidence does.
        if($refunded<=(int)$h['refunded_minor']&&(!$disputed||(int)$h['disputed']===1))return $h['resolved_at']===null;
        self::q($db,"UPDATE $t SET reason=?,refunded_minor=GREATEST(refunded_minor,?),disputed=GREATEST(disputed,?),resolved_at=NULL WHERE payment_id=?",[$reason,$refunded,(int)$disputed,$pi]);
        return true;
    }
    public static function resolve(\PDO $db,string $scope,string $pi,string $ref,array $decision,callable $adjust):array{
        if(!$db->inTransaction()||!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$ref))throw new \DomainException('Invalid resolution reference');
        $t=self::table($scope,'holds');$r=self::table($scope,'resolutions');
        $old=self::q($db,"SELECT payment_id,evidence_json FROM $r WHERE resolution_ref=? FOR UPDATE",[$ref])->fetch(\PDO::FETCH_ASSOC);
        if($old!==false){
            $e=json_decode($old['evidence_json'],true,16,JSON_THROW_ON_ERROR);
            if($old['payment_id']!==$pi||$e['decision']!==$decision)throw new \DomainException('Resolution reference conflict');
            return ['status'=>'duplicate'];
        }
        $h=self::q($db,"SELECT * FROM $t WHERE payment_id=? FOR UPDATE",[$pi])->fetch(\PDO::FETCH_ASSOC);
        if($h===false||$h['resolved_at']!==null)throw new \DomainException('No open hold');
        $adjust();
        $e=['refunded_minor'=>(int)$h['refunded_minor'],'disputed'=>(bool)$h['disputed'],'reason'=>$h['reason'],'decision'=>$decision];
        self::q($db,"INSERT INTO $r(resolution_ref,payment_id,evidence_json,created_at) VALUES(?,?,?,?)",[$ref,$pi,json_encode($e,JSON_THROW_ON_ERROR),time()]);
        self::q($db,"UPDATE $t SET resolved_at=? WHERE payment_id=?",[time(),$pi]);
        return ['status'=>'resolved'];
    }
}
