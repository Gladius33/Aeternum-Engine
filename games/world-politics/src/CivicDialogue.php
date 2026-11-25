<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** WP36: abstract fictional civic associations and collective bargaining.
 * Indices are scenario quantities, not actual participation or service forecasts.
 * No tactics, individual targeting, coercive responses or regime transitions.
 * PublicOpinion owns the authoritative daily journal and deterministic replay.
 */
final class CivicDialogue
{
    public const VERSION='wp36-civic-dialogue-v1';
    public static function initial(string $day):array
    {
        $groups=[];
        foreach(self::groups() as $id=>$g){
            $groups[$id]=['type'=>$g['type'],'cohort'=>$g['cohort'],'phase'=>'idle','episode'=>0,
                'opened_on'=>null,'phase_since'=>$day,'elevated_days'=>0,'recovery_days'=>0,
                'meetings'=>0,'last_resolution'=>null,'participation_index'=>0,
                'collective_action'=>'none'];
        }
        return ['version'=>self::VERSION,'classification'=>'fictional_scenario','day'=>$day,
            'groups'=>$groups,'service_continuity_index'=>1000,'regime_changed'=>false];
    }
    private static function groups():array
    {
        return ['workers-union'=>['type'=>'trade_union','cohort'=>'workers'],
            'retirees-association'=>['type'=>'civic_association','cohort'=>'retired']];
    }
    /** Advance exactly once from verified domestic pressures. All constants
     * are explicit gameplay assumptions; no legal strike procedure is claimed.
     */
    public static function advance(array $state,string $day,array $pressure,array $baseline):array
    {
        if($state['version']!==self::VERSION||$day!==(new \DateTimeImmutable($state['day']))->modify('+1 day')->format('Y-m-d'))
            throw new \DomainException('Invalid civic dialogue transition');
        $state['day']=$day;
        foreach(self::groups() as $id=>$spec){
            $g=$state['groups'][$id];$cohort=$spec['cohort'];
            if(!isset($pressure[$cohort],$baseline[$cohort])||!is_int($pressure[$cohort])||!is_int($baseline[$cohort])
                ||$pressure[$cohort]<0||$pressure[$cohort]>1000||$baseline[$cohort]<0||$baseline[$cohort]>1000)
                throw new \InvalidArgumentException('Missing or invalid civic cohort');
            $excess=$pressure[$cohort]-$baseline[$cohort];
            $g['elevated_days']=$excess>=150?min(3650,$g['elevated_days']+1):0;
            $g['recovery_days']=$excess<=40?min(3650,$g['recovery_days']+1):0;
            $phase=$g['phase'];
            if($phase==='idle'&&$g['elevated_days']>=2){
                $g['phase']='consultation_requested';$g['episode']++;$g['opened_on']=$day;$g['phase_since']=$day;
            }elseif($phase==='consultation_requested'){
                $g['phase']='dialogue_open';$g['phase_since']=$day;$g['meetings']++;
            }elseif(in_array($phase,['dialogue_open','unresolved'],true)){
                if($g['recovery_days']>=3){
                    $g['phase']='relief_observed';$g['phase_since']=$day;
                    $g['last_resolution']=['day'=>$day,'episode'=>$g['episode'],'kind'=>'pressure_relieved_in_model',
                        'legal_agreement'=>false];
                }elseif($phase==='dialogue_open'&&$g['elevated_days']>=4){
                    $g['phase']='unresolved';$g['phase_since']=$day;
                }
            }elseif($phase==='relief_observed'){
                $g['phase']='idle';$g['phase_since']=$day;$g['opened_on']=null;
            }
            $target=$g['phase']==='unresolved'?max(0,min(1000,2*$excess)):0;
            $diff=$target-$g['participation_index'];
            if($diff!==0)$g['participation_index']+=($diff>0?1:-1)*max(1,intdiv(abs($diff),4));
            $g['collective_action']=$g['participation_index']===0?'none':
                ($spec['type']==='trade_union'?'abstract_work_withdrawal':'abstract_civic_mobilization');
            $state['groups'][$id]=$g;
        }
        // A bounded illustrative continuity index, never a physical output
        // estimate and never applied again to the domestic economic ledger.
        $state['service_continuity_index']=1000-intdiv($state['groups']['workers-union']['participation_index'],5);
        return $state;
    }
}
