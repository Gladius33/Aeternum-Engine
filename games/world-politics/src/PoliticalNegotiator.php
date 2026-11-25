<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ElectoralCycle.php';

/** Bounded, transparent fictional party negotiation and opposition model.
 * No real-world voter targeting, no official political forecast or legal vote.
 */
final class PoliticalNegotiator
{
    public const VERSION='wp34-political-ai-v1';
    public static function evaluate(array $electoral):array
    {
        $out=['version'=>self::VERSION,'classification'=>'fictional_scenario','mode'=>$electoral['country']==='FR'?'human_advisory':'automatic_negotiation',
            'status'=>'awaiting_results','recommendation'=>null,'alternatives'=>[],'opposition'=>[],'legislative_projections'=>[]];
        if($electoral['status']!=='results')return $out;
        $seats=$electoral['result']['seats'];$parties=$electoral['config']['parties'];$majority=$electoral['result']['majority'];
        $ids=array_keys(array_filter($seats,fn($n)=>$n>0));sort($ids,SORT_STRING);
        if(count($ids)>12)throw new \DomainException('Political pilot supports at most 12 represented parties');
        $options=[];
        if($electoral['config']['formation']){
            for($mask=1;$mask<(1<<count($ids));$mask++){
                $members=[];$total=0;$low=['economy'=>0,'services'=>0];$high=['economy'=>1000,'services'=>1000];
                $weighted=['economy'=>0,'services'=>0];$leader=null;$leadSeats=-1;
                foreach($ids as $i=>$id){
                    if(($mask&(1<<$i))===0)continue;
                    $members[]=$id;$n=$seats[$id];$total+=$n;$p=$parties[$id];
                    if($n>$leadSeats){$leader=$p['leader'];$leadSeats=$n;}
                    foreach($low as $axis=>$v){
                        $low[$axis]=max($low[$axis],$p['programme'][$axis]-$p['tolerance']);
                        $high[$axis]=min($high[$axis],$p['programme'][$axis]+$p['tolerance']);
                        $weighted[$axis]+=$n*$p['programme'][$axis];
                    }
                }
                if($low['economy']>$high['economy']||$low['services']>$high['services'])continue;
                $programme=[];foreach($low as $axis=>$v)$programme[$axis]=max($v,min($high[$axis],intdiv($weighted[$axis]+intdiv($total,2),$total)));
                $cost=0;foreach($members as $id){
                    $p=$parties[$id];$cost+=$seats[$id]*(abs($programme['economy']-$p['programme']['economy'])+abs($programme['services']-$p['programme']['services']));
                }
                $options[]=['parties'=>$members,'candidate'=>$leader,'programme'=>$programme,'support_seats'=>$total,
                    'majority'=>$majority,'status'=>$total>=$majority?'majority_agreement':'minority_agreement',
                    'compromise_cost'=>$cost];
            }
            usort($options,static function($a,$b){
                $am=$a['status']==='majority_agreement';$bm=$b['status']==='majority_agreement';
                if($am!==$bm)return $bm<=>$am;
                if($am){
                    // Prefer smaller viable coalition, then smaller weighted compromise.
                    $r=count($a['parties'])<=>count($b['parties']);
                    if($r!==0)return $r;
                    $r=$a['compromise_cost']<=>$b['compromise_cost'];if($r!==0)return $r;
                    $r=$b['support_seats']<=>$a['support_seats'];
                }else $r=$b['support_seats']<=>$a['support_seats'];
                if($r!==0)return $r;
                return strcmp(implode(',',$a['parties']),implode(',',$b['parties']));
            });
            $out['recommendation']=$options[0]??null;$out['alternatives']=array_slice($options,1,4);
        }
        $out['status']=$electoral['config']['formation']?'negotiation_available':'presidential_legislature';
        $agreement=$electoral['coalition'];
        $government=$electoral['government'];
        $aligned=$government['parties']??($agreement!==null&&in_array($agreement['status'],['majority_agreement','minority_agreement'],true)?$agreement['accepted']:[]);
        foreach($ids as $id)$out['opposition'][$id]=['seats'=>$seats[$id],
            'alignment'=>in_array($id,$aligned,true)?($government!==null?'certified_coalition':'negotiating_coalition'):'outside_coalition'];
        foreach(['public_services'=>['economy'=>450,'services'=>750],'fiscal_restraint'=>['economy'=>750,'services'=>400]] as $key=>$programme)
            $out['legislative_projections'][$key]=self::project($electoral,$programme);
        return $out;
    }
    /** Seat-conserving group-level forecast for a fictional programme only. */
    public static function project(array $electoral,array $programme):array
    {
        $keys=array_keys($programme);sort($keys);
        if($keys!==['economy','services'])throw new \InvalidArgumentException('Invalid programme axes');
        foreach($programme as $n)if(!is_int($n)||$n<0||$n>1000)throw new \InvalidArgumentException('Invalid programme value');
        if($electoral['status']!=='results')throw new \DomainException('No final electoral seats');
        $counts=['yes'=>0,'no'=>0,'abstain'=>0];$groups=[];
        foreach($electoral['result']['seats'] as $id=>$n){
            if($n===0)continue;
            $p=$electoral['config']['parties'][$id];$distance=max(abs($programme['economy']-$p['programme']['economy']),abs($programme['services']-$p['programme']['services']));
            $choice=$distance<=$p['tolerance']?'yes':($distance<=$p['tolerance']+100?'abstain':'no');
            $counts[$choice]+=$n;$groups[$id]=['seats'=>$n,'stance'=>$choice,'distance'=>$distance];
        }
        $majority=$electoral['result']['majority'];
        return ['classification'=>'scenario_projection_not_certified_vote','programme'=>$programme,'counts'=>$counts,'groups'=>$groups,
            'absolute_majority'=>$majority,'absolute_majority_support'=>$counts['yes']>=$majority,'legal_effect'=>false];
    }
}
