<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/AlphaAccounts.php';

/** Data-only credential. Not accepted by ordinary gameplay sessions. */
final class PlayerDataAccess
{
    private static function q(\PDO $db,string $sql,array $p=[]):\PDOStatement{$q=$db->prepare($sql);$q->execute($p);return $q;}
    private static function tx(\PDO $db,callable $f):mixed{
        $db->beginTransaction();try{$r=$f();$db->commit();return $r;}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function setEntitlement(\PDO $db,string $account,int $until):void{
        if($until<0||$until>time()+366*86400)throw new \DomainException('Invalid expiry');
        self::tx($db,function()use($db,$account,$until){
            if(self::q($db,'SELECT account_id FROM wp_accounts WHERE account_id=? FOR UPDATE',[$account])->fetchColumn()===false)throw new \DomainException('Unknown account');
            self::q($db,'INSERT INTO wp_player_api_entitlements(account_id,paid_until) VALUES(?,?) ON DUPLICATE KEY UPDATE paid_until=VALUES(paid_until)',[$account,$until]);
            if($until<=time())self::q($db,'DELETE FROM wp_player_api_keys WHERE account_id=?',[$account]);
        });
    }
    public static function issue(\PDO $db,AlphaSession $session):array{
        return self::tx($db,function()use($db,$session){
            $id=$session->id;
            if((int)self::q($db,'SELECT enabled FROM wp_accounts WHERE account_id=? FOR UPDATE',[$id])->fetchColumn()!==1||!$session->active())throw new AlphaHttpError(401,'authentication_required');
            $expiry=(int)self::q($db,'SELECT paid_until FROM wp_player_api_entitlements WHERE account_id=? FOR UPDATE',[$id])->fetchColumn();
            if($expiry<=time())throw new AlphaHttpError(403,'premium_required');
            $token=bin2hex(random_bytes(32));$expires=min($expiry,time()+90*86400);
            self::q($db,'DELETE FROM wp_player_api_keys WHERE account_id=?',[$id]);
            self::q($db,'INSERT INTO wp_player_api_keys(key_sha,account_id,expires_at) VALUES(?,?,?)',[hash('sha256',$token),$id,$expires]);
            return ['token'=>$token,'expires_at'=>$expires,'scope'=>'own_simulation_read'];
        });
    }
    public static function revoke(\PDO $db,AlphaSession $session):void{
        self::tx($db,function()use($db,$session){
            self::q($db,'SELECT account_id FROM wp_accounts WHERE account_id=? FOR UPDATE',[$session->id]);
            if(!$session->active())throw new AlphaHttpError(401,'authentication_required');
            self::q($db,'DELETE FROM wp_player_api_keys WHERE account_id=?',[$session->id]);
        });
    }
    public static function authenticate(\PDO $db,string $header,bool $lock=false):string{
        if(!preg_match('/^Bearer ([a-f0-9]{64})$/D',$header,$m))throw new AlphaHttpError(401,'data_token_required');
        $r=self::q($db,'SELECT k.account_id FROM wp_player_api_keys k JOIN wp_accounts a USING(account_id) JOIN wp_player_api_entitlements e USING(account_id) WHERE k.key_sha=? AND k.expires_at>? AND e.paid_until>? AND a.enabled=1'.($lock?' FOR UPDATE':''),[hash('sha256',$m[1]),time(),time()])->fetchColumn();
        if(!is_string($r))throw new AlphaHttpError(401,'data_token_required');return $r;
    }
    public static function worlds(\PDO $db,string $header):array{
        return self::tx($db,function()use($db,$header){
            $a=self::authenticate($db,$header,true);
            $rows=self::q($db,'SELECT m.world_id,c.committed_day FROM wp_career_memberships m JOIN wp_alpha_members b USING(world_id,account_id) JOIN wp_world_clock c USING(world_id) WHERE m.account_id=? ORDER BY m.world_id LIMIT 100',[$a])->fetchAll(\PDO::FETCH_ASSOC);
            return ['scope'=>'own_simulation_read','worlds'=>$rows,'coverage'=>'career_worlds_only'];
        });
    }
}
