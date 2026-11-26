<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** WP37: fictional campaign objective and certified narrative outcomes.
 * An outcome is not inferred from public approval, unrest or a strategy for
 * retaining real-world power. No coercive action model or predictive threshold.
 * The game ends only when the player's actual scenario office is lost.
 */
final class CampaignLifecycle
{
    public const VERSION='wp37-campaign-v1';
    public const PLAYER='campaign-president';
    private const TYPES=['cabinet_resigned','cabinet_reconstituted','constitutional_crisis_opened',
        'constitutional_crisis_resolved','revolution_attempted','revolution_failed','revolution_succeeded',
        'presidential_election_lost','presidential_mandate_ended','presidential_resignation','presidential_removal','presidential_death',
        'leadership_displaced','leadership_abroad','leadership_returned'];
    public static function initial(string $world,string $day):array
    {
        self::id($world);self::day($day);
        return ['version'=>self::VERSION,'classification'=>'fictional_scenario','world_id'=>$world,'country'=>'FR',
            'player_character'=>self::PLAYER,'office'=>'head_of_state','start_day'=>$day,'day'=>$day,
            'status'=>'active','office_retained'=>true,'cabinet_status'=>'in_office','constitutional_status'=>'continuity',
            'revolution_status'=>'none','regime_changed'=>false,'ended_on'=>null,'reason'=>null,'events'=>[]];
    }
    /** An explicitly authored, server-side narrative event; no client authoring. */
    public static function event(string $world,string $day,string $id,string $type):array
    {
        $e=['world_id'=>$world,'country'=>'FR','day'=>$day,'id'=>$id,'type'=>$type,'source'=>'curated_fictional_outcome'];
        self::validate($e);return $e;
    }
    public static function validate(array $e):void
    {
        $keys=array_keys($e);sort($keys);
        if($keys!==['country','day','id','source','type','world_id']||$e['country']!=='FR'
            ||$e['source']!=='curated_fictional_outcome'||!in_array($e['type'],self::TYPES,true))
            throw new \InvalidArgumentException('Invalid fictional campaign event');
        self::id($e['world_id']);self::id($e['id']);self::day($e['day']);
    }
    public static function advance(array $s,string $day,?array $e):array
    {
        self::day($day);
        if($s['version']!==self::VERSION||$s['status']!=='active'
            ||$day!==(new \DateTimeImmutable($s['day']))->modify('+1 day')->format('Y-m-d'))
            throw new \DomainException('Campaign is terminal or date is invalid');
        if($e!==null){
            self::validate($e);
            if($e['world_id']!==$s['world_id']||$e['day']!==$day)throw new \DomainException('Campaign event scope mismatch');
            foreach($s['events'] as $prior)if($prior['id']===$e['id'])throw new \DomainException('Duplicate campaign event');
            switch($e['type']){
                case 'leadership_displaced':
                case 'leadership_abroad':
                case 'leadership_returned':
                    if(!isset($s['continuity']))throw new \DomainException('Continuity scenario required');
                    $old=$s['continuity']['location_status'];
                    $new=match($e['type']){'leadership_displaced'=>'relocated_domestically','leadership_abroad'=>'abroad','leadership_returned'=>'domestic'};
                    if($old===$new||($new==='domestic'&&$old==='domestic'))throw new \DomainException('Duplicate continuity transition');
                    $s['continuity']=['location_status'=>$new,'recognition_status'=>$new==='abroad'?'requires_independent_review':'not_disputed',
                        'last_event_day'=>$day];
                    // Physical absence is not a certified vacancy or loss of office.
                    break;
                case 'cabinet_resigned':
                    if($s['cabinet_status']!=='in_office')throw new \DomainException('Cabinet already resigned');
                    $s['cabinet_status']='caretaker';break;
                case 'cabinet_reconstituted':
                    if($s['cabinet_status']!=='caretaker')throw new \DomainException('No caretaker cabinet');
                    $s['cabinet_status']='in_office';break;
                case 'constitutional_crisis_opened':
                    if($s['constitutional_status']!=='continuity')throw new \DomainException('Crisis already open');
                    $s['constitutional_status']='disputed';break;
                case 'constitutional_crisis_resolved':
                    if($s['constitutional_status']!=='disputed'||$s['revolution_status']==='attempted')
                        throw new \DomainException('Unresolved revolution or no crisis');
                    $s['constitutional_status']='continuity';break;
                case 'revolution_attempted':
                    if($s['constitutional_status']!=='disputed'||$s['revolution_status']==='attempted')
                        throw new \DomainException('No eligible fictional crisis episode');
                    $s['revolution_status']='attempted';break;
                case 'revolution_failed':
                    if($s['revolution_status']!=='attempted')throw new \DomainException('No pending revolution');
                    $s['revolution_status']='failed';break;
                case 'revolution_succeeded':
                    if($s['constitutional_status']!=='disputed'||$s['revolution_status']!=='attempted')
                        throw new \DomainException('No certified pending revolutionary transition');
                    $s['revolution_status']='succeeded';$s['constitutional_status']='order_replaced';
                    $s['regime_changed']=true;
                    self::end($s,$day,$e['type']);break;
                default:
                    // Loss of the presidency is terminal, even when the regime survives.
                    self::end($s,$day,$e['type']);break;
            }
            $s['events'][]=$e;
        }
        $s['day']=$day;return $s;
    }
    private static function end(array &$s,string $day,string $reason):void
    {
        $s['status']='game_over';$s['office_retained']=false;$s['ended_on']=$day;$s['reason']=$reason;
    }
    /** Called only by the replayed, scope-bound presidential calendar. */
    public static function applyPresidentialOutcome(array $s,array $p):array
    {
        if($s['status']!=='active'||$p['world_id']!==$s['world_id']||$p['day']!==$s['day']||$p['status']!=='ended'
            ||$p['officeholder']===self::PLAYER||!in_array($p['reason'],['presidential_election_lost','presidential_mandate_ended'],true))
            throw new \DomainException('Invalid presidential outcome');
        self::end($s,$s['day'],$p['reason']);
        $s['events'][]=['world_id'=>$s['world_id'],'country'=>'FR','day'=>$s['day'],'id'=>'presidential-calendar-outcome',
            'type'=>$p['reason'],'source'=>'simulated_presidential_calendar'];
        return $s;
    }
    /** Reporting only. Loss of the office freezes the completed tenure;
     * disputed legal continuity is disclosed separately, not awarded points. */
    public static function tenure(array $s):array
    {
        $end=$s['ended_on']??$s['day'];$days=(int)(new \DateTimeImmutable($s['start_day']))->diff(new \DateTimeImmutable($end))->format('%r%a');
        if($days<0)throw new \DomainException('Invalid tenure interval');
        $vacancy=in_array($s['reason'],['presidential_resignation','presidential_death','presidential_removal'],true);
        $interim=null;
        if($vacancy){
            $d=new \DateTimeImmutable($end);
            $interim=['office'=>'senate_president_or_government_if_unavailable','earliest_poll'=>$d->modify('+20 days')->format('Y-m-d'),'latest_poll'=>$d->modify('+35 days')->format('Y-m-d'),'exception'=>'force_majeure_certified_by_constitutional_council','unavailable_articles'=>[11,12,49,50,89],'post_campaign_election_simulated'=>false];
        }
        return ['start_day'=>$s['start_day'],'through_day'=>$end,'elapsed_office_days'=>$days,'frozen'=>$s['ended_on']!==null,'constitutional_status'=>$s['constitutional_status'],'recognition_status'=>$s['continuity']['recognition_status']??'not_disputed','interim'=>$interim,'score_eligible'=>false];
    }
    public static function summary(array $s):array
    {
        return ['status'=>$s['status'],'office_retained'=>$s['office_retained'],'office'=>$s['office'],
            'regime_changed'=>$s['regime_changed'],'ended_on'=>$s['ended_on'],'reason'=>$s['reason'],...(isset($s['continuity'])?['continuity'=>$s['continuity']]:[])];
    }
    /** Validate the entire future queue before accepting a new immutable event.
     * Skip empty days through deterministic date advancement, capped at the twenty-year simulation horizon.
     */
    public static function validateQueue(array $current,array $events):void
    {
        if($current['status']!=='active')throw new \DomainException('Campaign already ended');
        usort($events,fn($a,$b)=>strcmp($a['day'],$b['day']));
        $seen=[];$ids=[];
        foreach($events as $e){
            self::validate($e);
            if(isset($seen[$e['day']])||isset($ids[$e['id']]))throw new \DomainException('Duplicate event day or identity');
            $seen[$e['day']]=true;$ids[$e['id']]=true;
            if($e['day']<=$current['day']||$e['day']>(new \DateTimeImmutable($current['start_day']))->modify('+7305 days')->format('Y-m-d'))
                throw new \DomainException('Event outside campaign horizon');
            // No event-free transition changes campaign state except its date.
            $current['day']=(new \DateTimeImmutable($e['day']))->modify('-1 day')->format('Y-m-d');
            $current=self::advance($current,$e['day'],$e);
        }
    }
    private static function id(mixed $v):void{if(!is_string($v)||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$v))throw new \InvalidArgumentException('Invalid campaign identifier');}
    private static function day(mixed $v):void{if(!is_string($v)||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v))===false||$d->format('Y-m-d')!==$v)throw new \InvalidArgumentException('Invalid campaign day');}
}
