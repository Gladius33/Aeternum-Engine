<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
final class DeploymentMode
{
    public static function name():string{
        $v=getenv('WP_APP_ENV')?:'';
        if(!in_array($v,['development','staging','production'],true))throw new \DomainException('Explicit deployment environment required');
        return $v;
    }
    public static function stripeTest():void{
        if(self::name()==='production'||getenv('WP_STRIPE_MODE')!=='test')throw new \DomainException('Stripe test restricted to nonproduction');
    }
    /** A dedicated nonproduction database must be explicitly tagged by an
     * operator. Merely changing an environment variable cannot permit test
     * settlement against an untagged or production DB. */
    public static function assertDatabase(\PDO $db):void{
        self::stripeTest();
        $v=$db->query('SELECT deployment_env FROM wp_stripe_environment WHERE singleton_id=1')->fetchColumn();
        if($v!==self::name())throw new \DomainException('Stripe database environment mismatch');
    }
    public static function bind(\PDO $db):array{
        self::stripeTest();$mode=self::name();
        $q=$db->prepare('INSERT IGNORE INTO wp_stripe_environment(singleton_id,deployment_env) VALUES(1,?)');$q->execute([$mode]);
        self::assertDatabase($db);return ['environment'=>$mode,'stripe_mode'=>'test'];
    }
}
