<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/DomesticEnactmentRegistry.php';

/** Trusted local publication/enactment service, never instantiated by HTTP. */
final class LocalLegislativePublisher implements PersistentDomesticPolicyAuthority
{
    public function __construct(private readonly string $world){}
    public function actorId():string{return 'local-legislative-publisher';}
    public function allows(string $world,string $ability):bool{return $world===$this->world&&in_array($ability,['domestic.read','domestic.publish','domestic.enact'],true);}
    public function enactedInstrument(string $world,string $policy):?string{return null;}
    public function clockDay(string $world):string{throw new \DomainException('No clock authority');}
    /** Recoverable two-transaction publication then authorization; same proof
     * may be reused after an interruption, never fabricated by the client. */
    public static function apply(\PDO $db,string $world,string $billId):array
    {
        $base=new self($world);$registry=new DomesticEnactmentRegistry($db,$base);
        $q=$db->prepare("SELECT state_json,state_sha FROM wp_institutional_states WHERE world_id=? AND country_code='FR'");
        $q->execute([$world]);$row=$q->fetch(\PDO::FETCH_ASSOC);
        if($row===false)throw new \DomainException('No national state');
        $inst=InstitutionalSimulation::restore(json_decode($row['state_json'],true,64,JSON_THROW_ON_ERROR));
        if($inst->snapshot()['world_id']!==$world||!hash_equals($row['state_sha'],$inst->hash()))throw new \DomainException('National scope mismatch');
        $bill=$inst->snapshot()['legislation']['bills'][$billId]??null;
        if($bill===null||$bill['status']!=='published'||$bill['final_version']!=='standard')throw new \DomainException('No matching published standard bill');
        $policy=$bill['policy_id'];$store=new PdoDomesticPolicyStore($db,new VerifiedDomesticPolicyAuthority($base,$registry));
        $read=$store->read($world);$p=$read['state']['policies'][$policy]??null;
        if($p===null||$p['effective_on']!==$bill['effect_on'])throw new \DomainException('No matching domestic proposal');
        $proof=$registry->proof($world,$policy);
        if($proof===null)$proof=$registry->publish($world,$policy,'fr_bicameral',$bill['texts']['standard'],
            hash('sha256',json_encode($bill,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)));
        if($p['authorized_on']!==null){
            if($p['instrument_sha']!==$proof)throw new \DomainException('Policy proof conflict');
            return ['status'=>200,'result'=>['policy_id'=>$policy,'policy'=>$p],'etag'=>$read['etag'],'revision'=>$read['revision']];
        }
        return $store->execute($world,'enact',['policy_id'=>$policy],'legislation-'.hash('sha256',$billId),$read['etag']);
    }
}
