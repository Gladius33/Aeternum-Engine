<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/InstitutionalSimulation.php';

/** Reconcile certified simulated publications with pending, exact-date domestic
 * proposals. Called only inside the fenced domestic clock transaction. */
final class DailyLegalActivation
{
    public const VERSION='wp54r-daily-legal-activation-v1';
    public static function candidates(array $l,string $policy,string $effective,string $cutoff):array
    {
        $found=[];
        foreach($l['bills']??[] as $b)
            if($b['policy_id']===$policy&&$b['status']==='published'&&$b['final_version']==='standard'&&$b['effect_on']===$effective&&$b['published_on']<=$cutoff)
                $found[]=['route'=>'fr_bicameral','text'=>$b['texts']['standard'],'instrument'=>$b];
        foreach($l['executive']['acts']??[] as $b)
            if($policy===ExecutiveActs::POLICY&&$b['policy_id']===$policy&&$b['status']==='published'&&$b['kind']!=='ordinance'&&$b['effective_on']===$effective&&$b['published_on']<=$cutoff)
                $found[]=['route'=>'fr_regulatory','text'=>$b['text_sha'],'instrument'=>$b];
        foreach($l['finance']['bills']??[] as $b)
            if($b['policy_id']===$policy&&$b['status']==='published'&&$b['effective_on']===$effective&&$b['published_on']<=$cutoff)
                $found[]=['route'=>'fr_finance','text'=>$b['text_sha'],'instrument'=>$b];
        return $found;
    }
    public static function apply(\PDO $pdo,DomesticPolicySimulation $sim,string $world,string $targetDay,string $publisher):void
    {
        if(!$pdo->inTransaction())throw new \LogicException('Fenced domestic transaction required');
        $s=$sim->snapshot();
        if(!isset($s['daily_legal_version'])||$s['world_id']!==$world||$targetDay!==(new \DateTimeImmutable($s['day']))->modify('+1 day')->format('Y-m-d'))throw new \DomainException('Invalid legal reconciliation scope');
        $q=$pdo->prepare("SELECT state_json,state_sha FROM wp_institutional_states WHERE world_id=? AND country_code='FR'");
        $q->execute([$world]);$n=$q->fetch(\PDO::FETCH_ASSOC);
        if($n===false)throw new \DomainException('Missing national legal source');
        $i=InstitutionalSimulation::restore(json_decode($n['state_json'],true,64,JSON_THROW_ON_ERROR));$is=$i->snapshot();
        if($is['world_id']!==$world||$is['country_code']!=='FR'||$is['day']!==$targetDay||!hash_equals($n['state_sha'],$i->hash())||!isset($is['legislation']))throw new \DomainException('Invalid national legal source');
        foreach($s['policies'] as $policy=>$p){
            if($p['authorized_on']!==null||$p['effective_on']<=$s['day'])continue;
            $found=self::candidates($is['legislation'],$policy,$p['effective_on'],$s['day']);
            // Ambiguity remains unresolved; no arbitrary instrument selection.
            if(count($found)!==1)continue;
            $v=$found[0];$review=hash('sha256',json_encode($v['instrument'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
            $q=$pdo->prepare('SELECT record_json,record_sha FROM wp_domestic_enactments WHERE world_id=? AND policy_id=? FOR UPDATE');
            $q->execute([$world,$policy]);$old=$q->fetch(\PDO::FETCH_ASSOC);
            if($old!==false){
                $r=json_decode($old['record_json'],true,64,JSON_THROW_ON_ERROR);
                if(!hash_equals($old['record_sha'],hash('sha256',$old['record_json']))||$r['world_id']!==$world||$r['policy_id']!==$policy
                    ||$r['classification']!=='simulated_final_act'||$r['assumptions_sha']!==$s['assumptions_sha']||$r['effective_on']!==$p['effective_on']
                    ||$r['published_on']>$s['day']||$r['route']!==$v['route']||$r['final_text_sha']!==$v['text']||$r['review_sha']!==$review)
                    throw new \DomainException('Conflicting persisted legal proof');
                $sha=$old['record_sha'];
            }else{
                $r=['world_id'=>$world,'policy_id'=>$policy,'route'=>$v['route'],'classification'=>'simulated_final_act',
                    'published_on'=>$s['day'],'effective_on'=>$p['effective_on'],'assumptions_sha'=>$s['assumptions_sha'],
                    'proposal_sha'=>$sim->hash(),'constitutional_sha'=>$n['state_sha'],'final_text_sha'=>$v['text'],'review_sha'=>$review,'publisher'=>$publisher];
                $json=json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$sha=hash('sha256',$json);
                $q=$pdo->prepare('INSERT INTO wp_domestic_enactments(world_id,policy_id,record_json,record_sha) VALUES(?,?,?,?)');
                $q->execute([$world,$policy,$json,$sha]);
            }
            $authority=new class($world,$policy,$sha) implements DomesticPolicyAuthority{
                public function __construct(private readonly string $w,private readonly string $p,private readonly string $sha){}
                public function allows(string $world,string $ability):bool{return $world===$this->w&&$ability==='domestic.enact';}
                public function enactedInstrument(string $world,string $policy):?string{return $world===$this->w&&$policy===$this->p?$this->sha:null;}
                public function clockDay(string $world):string{throw new \DomainException('No clock delegation');}
            };
            $sim->authorize($authority,$policy);
        }
    }
}
