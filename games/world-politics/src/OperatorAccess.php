<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Dedicated operator identities; never an impersonation or raw DB bypass.
 * Only a digest of the independently generated bearer is configured. */
final class OperatorAccess
{
    private const PROFILES=['STANDARD_MONTHLY'=>['standard','monthly'],'STANDARD_PAYG'=>['standard','payg'],
        'PRO_MONTHLY'=>['pro','monthly'],'PRO_PAYG'=>['pro','payg']];
    public static function research(string $token):?array{
        if(getenv('WP_ADMIN_RESEARCH_ENABLED')!=='YES'||!preg_match('/^[a-f0-9]{64}$/D',$token))return null;
        $sha=hash('sha256',$token);$matches=[];
        foreach(self::PROFILES as $name=>[$plan,$mode]){
            $id=getenv('WP_ADMIN_RESEARCH_'.$name.'_TENANT')?:'';
            $digest=getenv('WP_ADMIN_RESEARCH_'.$name.'_TOKEN_SHA256')?:'';
            if(preg_match('/^[a-f0-9]{32}$/D',$id)&&preg_match('/^[a-f0-9]{64}$/D',$digest)&&hash_equals($digest,$sha))
                $matches[]=['tenant'=>$id,'plan'=>$plan,'mode'=>$mode];
        }
        return count($matches)===1?$matches[0]:null;
    }
    public static function game(string $account):bool{
        $id=getenv('WP_ADMIN_GAME_ACCOUNT')?:'';
        return getenv('WP_ADMIN_GAME_ENABLED')==='YES'&&preg_match('/^acct-[a-f0-9]{32}$/D',$id)
            &&$id!=='acct-00000000000000000000000000000000'&&hash_equals($id,$account);
    }
}
