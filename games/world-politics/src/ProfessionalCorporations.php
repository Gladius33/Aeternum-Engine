<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/IndustrialProgrammeCatalog.php';

/** Fictional institutional alternatives. No real legal authority or coercive operations. */
final class ProfessionalCorporations
{
    public const VERSION='wp53b-corporations-v1';
    public const SECTORS=['agriculture','industry','construction','commerce','transport','health','education','digital','finance','other-services'];
    public static function initial(string $day):array
    {
        $branches=[];
        foreach(self::SECTORS as $k)$branches[$k]=['wage_index_bps'=>10000,'report'=>null,'revote_on'=>null,'public_ownership_request'=>null];
        return ['version'=>self::VERSION,'day'=>$day,'mode'=>'pluralist','frequency'=>null,'next_report'=>null,
            'legal_conflict'=>false,'union_legal_status'=>'protected','worker_opposition'=>0,'employer_resistance'=>0,
            'branches'=>$branches,'classification'=>'fictional_uncalibrated'];
    }
    private static function nextReport(string $day,string $frequency):string
    {
        $d=new \DateTimeImmutable($day);$y=(int)$d->format('Y');$m=(int)$d->format('n');
        if($frequency==='annual')return sprintf('%04d-01-01',$y+1);
        $m=3*intdiv($m-1,3)+4;if($m>12){$m=1;$y++;}
        return sprintf('%04d-%02d-01',$y,$m);
    }
    private static function parse(string $action):array
    {
        if(in_array($action,['establish-quarterly','establish-annual','establish-exclusive-quarterly','establish-exclusive-annual','impose-exclusive','restore-pluralism','start-industrial-pilot','pause-industrial-pilot','resume-industrial-pilot'],true))return [$action,null];
        $p=explode(':',$action);
        if(count($p)===2&&in_array($p[0],['request-company-nationalisation','offer-voluntary-equity'],true)&&IndustrialProgrammeCatalog::hasCompany($p[1]))return $p;
        if(count($p)!==2||!in_array($p[0],['approve','return','request-nationalisation'],true)||!in_array($p[1],self::SECTORS,true))
            throw new \InvalidArgumentException('Unknown corporation decision');
        return $p;
    }
    public static function validateEvent(array $state,string $action):void
    {
        [$kind,$sector]=self::parse($action);
        if(str_starts_with($kind,'establish-')&&$state['mode']!=='pluralist')throw new \DomainException('Corporations already established');
        if($kind==='impose-exclusive'&&$state['mode']!=='parallel')throw new \DomainException('Parallel corporations required');
        if($kind==='restore-pluralism'&&$state['mode']==='pluralist')throw new \DomainException('Already pluralist');
        if($kind==='offer-voluntary-equity')return;
        if($kind==='request-company-nationalisation'){
            if(isset($state['company_requests'][$sector]))throw new \DomainException('Company review already requested');
            return;
        }
        if($sector!==null){
            if($state['mode']==='pluralist')throw new \DomainException('No active corporations');
            $b=$state['branches'][$sector];
            if(in_array($kind,['approve','return'],true)&&($b['report']['status']??null)!=='pending_state_review')
                throw new \DomainException('No pending branch report');
            if($kind==='request-nationalisation'&&$b['public_ownership_request']!==null)throw new \DomainException('Request already recorded');
        }
    }
    private static function toward(int $a,int $b):int
    {$d=$b-$a;return $a+($d===0?0:($d>0?1:-1)*max(1,intdiv(abs($d),20)));}
    /** Daily state uses previous committed macro evidence only. */
    public static function step(array $state,string $day,?string $action,?array $macro,?array $minimumContext=null):array
    {
        if($day!==(new \DateTimeImmutable($state['day']))->modify('+1 day')->format('Y-m-d'))throw new \DomainException('Nonconsecutive corporation day');
        $state['day']=$day;
        $minimumActive=($minimumContext['active']??false)===true;
        $wasMinimum=($state['minimum_wage_regime']??null)==='state_validated_branches';
        if($wasMinimum&&!$minimumActive){
            // Repeal ends the branch-minimum law, not ordinary collective bargaining.
            unset($state['minimum_wage_regime'],$state['minimum_reference_minor']);
            foreach($state['branches'] as &$b){unset($b['minimum_net_minor']);if(isset($b['report']['proposed_minimum_minor'])&&$b['report']['status']==='pending_state_review'){$b['report']['status']='cancelled';$b['revote_on']=null;}}unset($b);
        }
        // Statutory default review after seven days, only if no explicit State
        // decision for that day. No approval before both colleges vote.
        $automaticReview=false;
        if($minimumActive&&$wasMinimum&&$action===null){
            foreach($state['branches'] as $sector=>$b){
                $r=$b['report'];
                if(($r['status']??null)!=='pending_state_review'||!isset($r['proposed_minimum_minor'])||$r['day']>(new \DateTimeImmutable($day))->modify('-7 days')->format('Y-m-d'))continue;
                $action='approve:'.$sector;$automaticReview=true;break;
            }
        }
        if($action!==null){
            self::validateEvent($state,$action);[$kind,$sector]=self::parse($action);
            if(str_starts_with($kind,'establish-')){
                $exclusive=str_starts_with($kind,'establish-exclusive-');
                $state['mode']=$exclusive?'exclusive_contested':'parallel';$state['frequency']=str_ends_with($kind,'quarterly')?'quarterly':'annual';
                $state['next_report']=self::nextReport($day,$state['frequency']);
                if($exclusive){$state['legal_conflict']=true;$state['union_legal_status']='ban_claimed_constitutionally_contested';}
            }elseif($kind==='impose-exclusive'){
                $state['mode']='exclusive_contested';$state['legal_conflict']=true;
                $state['union_legal_status']='ban_claimed_constitutionally_contested';
            }elseif($kind==='restore-pluralism'){
                $state['mode']='pluralist';$state['legal_conflict']=false;$state['union_legal_status']='protected';
                $state['frequency']=null;$state['next_report']=null;
                foreach($state['branches'] as &$b){if(($b['report']['status']??null)==='pending_state_review')$b['report']['status']='cancelled';$b['revote_on']=null;}unset($b);
            }elseif(in_array($kind,['start-industrial-pilot','pause-industrial-pilot','resume-industrial-pilot'],true)){
                // Separately pinned industrial capital ledger owns this action.
            }elseif($kind==='offer-voluntary-equity'){
                // Separate financial ledger owns consent and settlement.
            }elseif($kind==='request-company-nationalisation'){
                $state['company_requests'][$sector]=['day'=>$day,'status'=>'pending_legal_and_fiscal_review','ownership_transferred'=>false,
                    'valuation'=>null,'domestic_asset_scope'=>null,'public_interest_review'=>'pending'];
            }elseif($kind==='request-nationalisation'){
                $state['branches'][$sector]['public_ownership_request']=['day'=>$day,'status'=>'pending_legal_and_fiscal_review','ownership_transferred'=>false];
            }else{
                $b=&$state['branches'][$sector];
                if($kind==='approve'){
                    if($minimumActive&&isset($b['report']['proposed_minimum_minor'],$b['minimum_net_minor'])){
                        $b['minimum_net_minor']=$b['report']['proposed_minimum_minor'];$b['report']['state_review_kind']=$automaticReview?'statutory_default_review':'explicit_state_review';
                    }else $b['wage_index_bps']=max(10000,intdiv($b['wage_index_bps']*(10000+$b['report']['proposed_wage_change_bps']),10000));
                    $b['report']['status']='approved';
                }else{
                    $b['report']['status']='returned';$b['revote_on']=(new \DateTimeImmutable($day))->modify('+7 days')->format('Y-m-d');
                }
                $b['report']['state_decision_day']=$day;unset($b);
            }
        }
        if($minimumActive){
            if($state['mode']==='pluralist'){
                $state['mode']='parallel';$state['frequency']='quarterly';$state['next_report']=self::nextReport($day,'quarterly');
            }
            if(!$wasMinimum){
                $opening=$minimumContext['opening_floor_minor'];
                if(!is_int($opening)||$opening<1||$opening>100000000)throw new \DomainException('Invalid opening branch minimum');
                $state['minimum_wage_regime']='state_validated_branches';$state['minimum_reference_minor']=$opening;
                foreach($state['branches'] as &$b){
                    $b['minimum_net_minor']=$opening;
                    if(($b['report']['status']??null)==='pending_state_review'){$b['report']['status']='cancelled';$b['revote_on']=null;}
                }unset($b);
            }
        }
        $active=$state['mode']!=='pluralist';$exclusive=$state['mode']==='exclusive_contested';
        $u=max(0,min(3000,$macro['unemployment_bps']??800));
        $state['worker_opposition']=self::toward($state['worker_opposition'],$exclusive?min(900,420+intdiv($u,5)):($active?80:0));
        $state['employer_resistance']=self::toward($state['employer_resistance'],$exclusive?min(850,250+intdiv($u,4)):($active?60:0));
        $period=$active&&$state['next_report']===$day;
        if($period)$state['next_report']=self::nextReport($day,$state['frequency']);
        foreach($state['branches'] as $id=>&$b){
            $revote=$b['revote_on']===$day;
            if(!$active||(!$period&&!$revote)||($b['report']['status']??null)==='pending_state_review')continue;
            $round=$revote?($b['report']['round']+1):1;
            $proposal=max(0,min(500,200+intdiv(800-$u,10)-($round-1)*25));
            if($minimumActive){
                // Transparent synthetic sector sensitivity, not observed branch wages.
                $offset=(array_search($id,self::SECTORS,true)-4)*15;
                $inflation=max(-1000,min(3000,(int)($macro['annual_inflation_bps']??200)));
                $proposal=max(-500,min(500,intdiv($inflation,4)+intdiv(800-$u,4)+$offset-($round-1)*25));
            }
            // Two autonomous fictional colleges; neither the government nor a
            // single social class can certify a branch proposal unilaterally.
            $worker=max(0,min(10000,6500+2*$proposal-intdiv($state['worker_opposition'],2)));
            $employer=max(0,min(10000,6800-5*$proposal-$state['employer_resistance']));
            $passed=$worker>=5000&&$employer>=5000;
            $b['report']=['day'=>$day,'round'=>$round,'proposed_wage_change_bps'=>$proposal,
                'employee_yes_bps'=>$worker,'employer_yes_bps'=>$employer,
                'status'=>$passed?'pending_state_review':'branch_vote_failed','state_decision_day'=>null];
            if($minimumActive)$b['report']['proposed_minimum_minor']=max(1,intdiv($b['minimum_net_minor']*(10000+$proposal),10000));
            $b['revote_on']=$passed?null:(new \DateTimeImmutable($day))->modify('+7 days')->format('Y-m-d');
        }unset($b);
        return $state;
    }
    public static function actionFor(array $events,string $target):?string
    {
        $day=(new \DateTimeImmutable($target))->modify('-1 day')->format('Y-m-d');
        foreach($events as $e)if($e['day']===$day)return $e['action'];
        return null;
    }
}
