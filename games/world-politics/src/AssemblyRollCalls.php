<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrenchParliament.php';

/** WP42 immutable fictional Assembly fixture. No actual persons, poll data,
 * voter targeting or user-selected score parameters. */
final class AssemblyRollCalls
{
    public const VERSION='wp42-individual-assembly-v1';
    public static function initial(string $world,string $day):array
    {
        $groups=[];
        foreach([['government','government',300],['opposition','opposition',-300],['independent','nonaligned',0]] as [$id,$alignment,$position]){
            $groups[]=['id'=>$id,'name'=>'Fictional '.$id,'alignment'=>$alignment,
                'positions'=>array_fill_keys(FrenchParliament::TOPICS,$position),'whip_bps'=>7500];
        }
        $members=[];
        for($n=1;$n<=577;$n++){
            $members[]=['id'=>sprintf('deputy-%03d',$n),'seat'=>$n,
                'group'=>$n<=240?'government':($n<=480?'opposition':'independent'),
                'control'=>'ai','loyalty_bps'=>6000+($n%5)*500,'attendance_bps'=>9500,
                'independence'=>(($n*37)%301)-150];
        }
        $p=new FrenchParliament($world,'fictional-assembly-one',$day,'fictional_scenario',
            'https://www.assemblee-nationale.fr/',$groups,$members);
        $p->coalition('initial-government',['government'],true);
        return $p->snapshot();
    }
    public static function fromElection(array $e,string $day):FrenchParliament
    {
        if($e['status']!=='results'||$e['seats_total']!==577||$e['result']['vacant_seats']!==0)
            throw new \DomainException('Full fictional Assembly required');
        $groups=[];
        foreach($e['config']['parties'] as $id=>$p){
            if(($e['result']['seats'][$id]??0)===0)continue;
            $position=$id==='civic'?200:($id==='social'?-200:0);
            $groups[]=['id'=>$id,'name'=>'Fictional '.$id,'alignment'=>$id==='civic'?'government':($id==='social'?'opposition':'nonaligned'),
                'positions'=>array_fill_keys(FrenchParliament::TOPICS,$position),'whip_bps'=>7500];
        }
        $members=[];$seat=0;$counts=array_fill_keys(array_keys($e['config']['parties']),0);
        foreach($e['result']['districts'] as $district=>$d){
            $seat++;
            if(array_sum($d['seats'])!==1)throw new \DomainException('Invalid elected district');
            $winner=array_key_first(array_filter($d['seats'],fn($n)=>$n===1));
            if($winner===null||!isset($counts[$winner]))throw new \DomainException('Unknown elected group');
            $counts[$winner]++;
            $members[]=['id'=>sprintf('elected-%03d',$seat),'seat'=>$seat,'group'=>$winner,'control'=>'ai',
                'loyalty_bps'=>6000+($seat%5)*500,'attendance_bps'=>9500,'independence'=>(($seat*37)%301)-150];
        }
        if($seat!==577||$counts!==$e['result']['seats'])throw new \DomainException('Elected seat conservation failed');
        return new FrenchParliament($e['world_id'],'fictional-elected-assembly',$day,'fictional_scenario',
            'https://www.assemblee-nationale.fr/',$groups,$members);
    }
    public static function resolve(FrenchParliament $p,FrenchConstitutionalCrisis $c,string $id,string $kind):array
    {
        // The matter is bound to the exact pre-vote constitutional snapshot.
        // No operator-supplied tally, pressure or concessions.
        $p->openMatter($id,$kind,$kind==='censure_49_3'?'social_policy':'general_policy',0,[],$c->hash());
        $r=$p->resolve($id);
        if($r['occupied_seats']!==$c->snapshot()['assembly_members']||$r['source_constitution_sha']!==$c->hash())
            throw new \DomainException('Roll call constitutional scope mismatch');
        return $r;
    }
}
