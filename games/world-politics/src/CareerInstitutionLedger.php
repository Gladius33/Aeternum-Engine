<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CareerScoreContract.php';
require_once __DIR__.'/ConstitutionalRevision.php';
require_once __DIR__.'/PresidentialMandate.php';
require_once __DIR__.'/InstitutionalRulebook.php';
require_once __DIR__.'/ComparativeGovernment.php';

/** WP118–119 opt-in institutional ledger. Documentary identities never authorize acts. */
final class CareerInstitutionLedger
{
    public const VERSION='wp118-119-career-ledger-v1';
    public const SOURCE='https://www.legifrance.gouv.fr/loda/id/JORFTEXT000000571356/';
    private const MINISTRIES=['economy','education','health','justice','interior','defence','foreign_affairs'];
    public static function check(bool $ok,string $why):void{if(!$ok)throw new \DomainException('Career: '.$why);}
    public static function id(string $s):void{self::check(preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/D',$s)===1,'invalid identifier');}
    public static function fields(array $r,array $keys):void{$k=array_keys($r);sort($k);sort($keys);self::check($k===$keys,'invalid fields');}
    public static function canonical(mixed $x):mixed{
        if(is_array($x)){if(!array_is_list($x))ksort($x,SORT_STRING);foreach($x as &$v)$v=self::canonical($v);unset($v);}
        else self::check(is_string($x)||is_int($x)||is_bool($x)||$x===null,'noncanonical scalar');
        return $x;
    }
    public static function json(mixed $x):string{return json_encode(self::canonical($x),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    public static function digest(mixed $x):string{return hash('sha256',self::json($x));}
    public static function day(string $s):void{CareerScoreContract::elapsed(CareerScoreContract::START,$s);}
    public static function initial(string $world,string $country,string $mode,string $day):array{
        self::id($world);self::day($day);self::check(InstitutionalRulebook::hasRules($country)&&in_array($mode,['career','research'],true),'reviewed country required');
        if($country!=='FR'){
            $rules=InstitutionalRulebook::forCountry($country);
            return ['version'=>self::VERSION,'world'=>$world,'country'=>$country,'mode'=>$mode,'state_id'=>$country,'regime'=>$rules['system'],'constitution_revision'=>1,
                'constitution_sha'=>self::digest($rules),'constitution_rules'=>['country_rulebook'=>$rules['version'],'source'=>$rules['policy_source'],'head_of_state'=>$rules['head_of_state'],'head_of_government'=>$rules['head_of_government']],'day'=>$day,'sequence'=>0,'last_event_sha'=>str_repeat('0',64),
                'careers'=>[],'mandates'=>[],'active'=>[],'generations'=>[],'series'=>[],'assembly'=>'active','senate'=>'not_modelled','assembly_changed_on'=>$day,'government'=>'vacant','research_observation'=>null,'foreign_evidence'=>[]];
        }
        return ['version'=>self::VERSION,'world'=>$world,'country'=>$country,'mode'=>$mode,'state_id'=>'FR','regime'=>'fr-fifth-republic','constitution_revision'=>1,
            'constitution_sha'=>self::digest(['source'=>self::SOURCE,'rules'=>'fr-career-v1']),'constitution_rules'=>['term_limit'=>2,'next_term_years'=>5,'effective_clauses'=>[]],'day'=>$day,'sequence'=>0,'last_event_sha'=>str_repeat('0',64),
            'careers'=>[],'mandates'=>[],'active'=>[],'generations'=>[],'series'=>[],'assembly'=>'active','senate'=>'active','assembly_changed_on'=>$day,
            'government'=>'vacant','research_observation'=>null];
    }
    private static function office(string $office):array{
        if($office==='president')return ['kind'=>'president','powers'=>['president.request_appointment','president.request_dissolution','president.request_revision','president.represent']];
        if($office==='prime-minister')return ['kind'=>'pm','powers'=>['government.propose_ministers','government.coordinate','government.request_confidence','government.present_resignation']];
        if(str_starts_with($office,'minister-')&&in_array(substr($office,9),self::MINISTRIES,true))return ['kind'=>'minister','powers'=>['ministry.'.substr($office,9).'.propose']];
        if(preg_match('/^deputy-([1-9][0-9]{0,2})$/D',$office,$m)&&((int)$m[1])<=577)return ['kind'=>'deputy','powers'=>['assembly.vote','assembly.propose']];
        if(preg_match('/^senator-([1-9][0-9]{0,2})$/D',$office,$m)&&((int)$m[1])<=348)return ['kind'=>'senator','powers'=>['senate.vote','senate.propose']];
        throw new \DomainException('Unknown office');
    }
    private static function compatible(string $a,string $b):bool{return $a==='minister'&&$b==='minister';}
    private static function close(array &$s,string $office,string $day,string $reason):array{
        self::check(isset($s['active'][$office]),'vacant office');$id=$s['active'][$office];$m=$s['mandates'][$id];
        self::check($m['start']<=$day&&$day<=$m['expires']&&($reason!=='expiry'||$day===$m['expires']),'invalid closure day');
        $s['mandates'][$id]['end']=$day;$s['mandates'][$id]['end_reason']=$reason;unset($s['active'][$office]);
        if($m['series']!==null){$s['series'][$m['series']]['end']=$day;$s['series'][$m['series']]['end_reason']=$reason;}
        return $m;
    }
    private static function grant(array &$s,array $g,string $day,string $reason,?array $previous,string $command):void{
        self::fields($g,['office','career','expires','interim']);self::id($g['career']);$office=$g['office'];$rule=self::office($office);self::day($g['expires']);
        self::check(isset($s['careers'][$g['career']])&&!isset($s['active'][$office])&&$g['expires']>$day&&is_bool($g['interim']),'grant scope');
        self::check(!$g['interim']||$office==='president','interim scope');
        if($rule['kind']==='deputy')self::check($s['assembly']==='active','assembly not active');
        if($rule['kind']==='minister')self::check(isset($s['active']['prime-minister']),'government not formed');
        foreach($s['active'] as $other=>$mid)if($s['mandates'][$mid]['career']===$g['career'])self::check(self::compatible($rule['kind'],self::office($other)['kind']),'incompatible offices');
        $generation=($s['generations'][$office]??0)+1;$s['generations'][$office]=$generation;
        $id='mandate-'.hash('sha256',self::json([$s['world'],$office,$generation,$command]));
        $series=null;$terms=0;
        if($office==='president'&&!$g['interim']){
            self::check(in_array($reason,['election','renewal','succession'],true),'presidential certification required');
            if($reason==='renewal'){
                self::check($previous!==null&&!$previous['interim']&&$previous['career']===$g['career']&&$previous['expires']===$day&&$previous['series']!==null&&($s['constitution_rules']['term_limit']===null||$previous['terms']<$s['constitution_rules']['term_limit']),'invalid presidential renewal');
                $series=$previous['series'];$terms=$previous['terms']+1;$s['series'][$series]['end']=null;$s['series'][$series]['end_reason']=null;
            }else{
                self::check($previous===null||$previous['career']!==$g['career']||$previous['interim'],'continuous term requires renewal');
                // Track consecutive terms even after an explicitly closed same-day vacancy.
                $latest=null;foreach($s['mandates'] as $old)if($old['office']==='president'&&!$old['interim']&&($latest===null||$old['generation']>$latest['generation']))$latest=$old;
                self::check($latest===null||$latest['career']!==$g['career']||$latest['end']!==$day,'same-day renewal cannot reset terms');
                $series='series-'.hash('sha256',$id);$terms=$latest!==null&&$latest['career']===$g['career']?$latest['terms']+1:1;self::check($s['constitution_rules']['term_limit']===null||$terms<=$s['constitution_rules']['term_limit'],'consecutive presidential term limit');$first=$s['careers'][$g['career']]['first_series']===null;
                $s['series'][$series]=['career'=>$g['career'],'start'=>$day,'end'=>null,'end_reason'=>null,'credit_days'=>null,'credit_verified'=>false,'first'=>$first,'rankable'=>false];
                if($first)$s['careers'][$g['career']]['first_series']=$series;
            }
            $limit=(new \DateTimeImmutable($day))->modify('+'.$s['constitution_rules']['next_term_years'].' years')->format('Y-m-d');
            self::check($g['expires']<=$limit,'presidential term exceeds effective duration');
        }
        if($g['interim'])self::check($reason==='succession','interim requires succession evidence');
        $powers=$g['interim']?['president.represent']:$rule['powers'];
        $s['mandates'][$id]=['id'=>$id,'office'=>$office,'career'=>$g['career'],'generation'=>$generation,'start'=>$day,'expires'=>$g['expires'],'end'=>null,'end_reason'=>null,'interim'=>$g['interim'],'constitution_revision'=>$s['constitution_revision'],'powers'=>$powers,'series'=>$series,'terms'=>$terms];
        $s['active'][$office]=$id;
    }
    /** Deterministic NPC identities, never synthetic authentication accounts.
     * This event is replayable and the roster is scoped to its own world. */
    public static function autonomousRoster(array $s):array{
        self::check($s['country']==='FR'&&$s['mode']==='career','French career AI only');
        $ids=[];
        for($i=1;$i<=3;$i++)$ids[]='ai-'.substr(hash('sha256','wp119-npc-v1|'.$s['world'].'|'.$i),0,40);
        return $ids;
    }
    private static function admitAutonomousRoster(array &$s,array $e):void{
        $p=$e['payload'];self::fields($p,['careers']);
        self::check($e['actor']==='career-ai-clerk'&&$p['careers']===self::autonomousRoster($s)&&
            $e['evidence_sha']===self::digest(['kind'=>'fictional-ai-roster-v1','world'=>$s['world'],'day'=>$e['day'],'payload'=>$p]),'AI roster evidence');
        foreach($p['careers'] as $id){
            self::check(!isset($s['careers'][$id]),'AI roster already admitted');
            foreach($s['careers'] as $c)self::check($c['character']!==$id,'AI identity collision');
            $s['careers'][$id]=['account'=>null,'character'=>$id,'admitted_on'=>$e['day'],'mode'=>$e['day']===CareerScoreContract::START?'opening':'late_takeover','first_series'=>null,'controller'=>'ai'];
        }
    }
    /** Frozen documentary identities; simulated acts are not real statements,
     * a confirmed candidacy or a prediction. Never invent an AI person. */
    public static function realAutonomousRoster(array $s):array{
        self::check($s['mode']==='career'&&InstitutionalRulebook::hasRules($s['country']),'documented AI roster only');
        if($s['country']!=='FR'){
            $r=json_decode(file_get_contents(__DIR__.'/../data/wp119-real-comparative-roster-v1.json'),true,32,JSON_THROW_ON_ERROR);
            self::check($r['version']==='wp119-real-comparative-roster-v1'&&$r['source_git_blob']==='3fab407ba4ac1bf8097baf03b93082ceb3336ab5','comparative roster edition');
            $rows=$r['countries'][$s['country']]??[];$out=[];
            foreach($rows as $p){self::id($p['id']);self::check(!isset($out[$p['id']])&&str_starts_with($p['source'],'https://')&&$p['name']!==''&&$p['programme_complete']===false,'comparative documentary identity');$out[$p['id']]=$p;}
            self::check(count($out)>=2,'missing comparative identity roster');return $out;
        }
        $r=json_decode(file_get_contents(__DIR__.'/../data/wp119-real-fr-roster-v1.json'),true,32,JSON_THROW_ON_ERROR);
        self::check($r['version']==='wp119-real-fr-roster-v1'&&$r['country']==='FR'&&$r['final_ballot_verified']===false,'roster edition');
        $out=[];foreach($r['people'] as $p){self::id($p['id']);self::check(!isset($out[$p['id']])&&str_starts_with($p['source'],'https://')&&$p['name']!=='','documentary identity');$out[$p['id']]=$p;}
        self::check(count($out)===22,'frozen roster count');return $out;
    }
    private static function admitRealAutonomousRoster(array &$s,array $e):void{
        $p=$e['payload'];self::fields($p,['people']);
        self::check($e['actor']==='career-ai-clerk'&&self::json($p['people'])===self::json(self::realAutonomousRoster($s))&&
            $e['evidence_sha']===self::digest(['kind'=>'real-ai-roster-v1','world'=>$s['world'],'day'=>$e['day'],'payload'=>$p]),'documented roster evidence');
        foreach($p['people'] as $id=>$person){
            self::check(!isset($s['careers'][$id]),'real identity already admitted');
            foreach($s['careers'] as $c)self::check($c['character']!==$id,'identity collision');
            $s['careers'][$id]=['account'=>null,'character'=>$id,'admitted_on'=>$e['day'],'mode'=>$e['day']===CareerScoreContract::START?'opening':'late_takeover','first_series'=>null,'controller'=>'ai','person_reference'=>$person['person_reference'],'identity'=>$person];
        }
    }
    /** A human may take the existing documented character, never create a
     * duplicate. The trusted store verifies the approved programme edition. */
    private static function claimRealCareer(array &$s,array $e):void{
        $p=$e['payload'];self::fields($p,['career','account','edition','programme_sha']);
        self::id($p['career']);self::id($p['account']);self::id($p['edition']);
        $old=$s['careers'][$p['career']]??null;
        self::check($e['actor']==='career-admission'&&$old!==null&&($old['controller']??null)==='ai'&&$old['account']===null&&isset($old['person_reference'])&&
            ($p['edition']===$old['person_reference']||str_starts_with($p['edition'],$old['person_reference'].'-v'))&&preg_match('/^[a-f0-9]{64}$/D',$p['programme_sha'])===1&&
            $e['evidence_sha']===self::digest(['kind'=>'approved-real-career-claim-v1','world'=>$s['world'],'day'=>$e['day'],'payload'=>$p]),'real career claim evidence');
        foreach($s['careers'] as $other)self::check($other['account']!==$p['account'],'account already has career');
        $s['careers'][$p['career']]['account']=$p['account'];
        $s['careers'][$p['career']]['controller']='human';
        $s['careers'][$p['career']]['claimed_on']=$e['day'];
        $s['careers'][$p['career']]['programme_edition']=$p['edition'];
        $s['careers'][$p['career']]['programme_sha']=$p['programme_sha'];
    }
    /** Reuse the WP112 fictional vacancy timing, only for an already
     * certified interim whose mandate expires on the modeled handover day.
     * No assumption is made about the cause or legal certification of vacancy. */
    private static function vacancyCalendar(array $interim):?array{
        if(!$interim['interim'])return null;
        $start=new \DateTimeImmutable($interim['start']);
        if($interim['expires']!==$start->modify('+36 days')->format('Y-m-d'))return null;
        return ['first'=>$start->modify('+21 days')->format('Y-m-d'),'second'=>$start->modify('+35 days')->format('Y-m-d'),'candidacy_lock'=>$interim['start']];
    }
    /** Deterministic fictional election fixture, not calibrated opinion or real voter data.
     * At least two admitted eligible careers are required; no fabricated human accounts. */
    public static function automaticBallot(array $s,string $day):?array{
        self::check($s['country']==='FR'&&$s['mode']==='career','French career ballot only');
        $mid=$s['active']['president']??null;$old=$mid===null?null:$s['mandates'][$mid];
        if($old===null){
            if($day!==CareerScoreContract::START)return null;
            $dates=['first'=>$day,'second'=>$day,'candidacy_lock'=>$day];
        }else{
            if($old['expires']!==$day)return null;
            $dates=$old['interim']?self::vacancyCalendar($old):FrenchElectionCalendar::presidentialPoll($day);
            if($dates===null)return null;
        }
        $occupied=[];foreach($s['active'] as $office=>$id)if($office!=='president')$occupied[$s['mandates'][$id]['career']]=true;
        $eligible=[];$outgoing=null;
        $realRoster=false;foreach($s['careers'] as $c)if(isset($c['person_reference'])){$realRoster=true;break;}
        if($old!==null&&$old['interim']){
            foreach($s['mandates'] as $m)if($m['office']==='president'&&!$m['interim']&&($outgoing===null||$m['generation']>$outgoing['generation']))$outgoing=$m;
        }
        foreach($s['careers'] as $id=>$c){
            if($realRoster&&($c['controller']??null)==='ai'&&!isset($c['person_reference']))continue;
            // Conservative vacancy fixture: the interrupted incumbent is not
            // a candidate until the reason-specific eligibility adapter exists.
            if($outgoing!==null&&$id===$outgoing['career'])continue;
            if($c['admitted_on']>$dates['candidacy_lock']||isset($occupied[$id]))continue;
            if($old!==null&&$id===$old['career']&&$s['constitution_rules']['term_limit']!==null&&$old['terms']>=$s['constitution_rules']['term_limit'])continue;
            $eligible[$id]=1000+hexdec(substr(hash('sha256','wp119-synthetic-ballot-v1|'.$s['world'].'|'.$day.'|'.$id),0,4));
        }
        self::check(count($eligible)>=2&&count($eligible)<=30,'automatic ballot needs 2–30 eligible unencumbered careers');
        ksort($eligible,SORT_STRING);$total=array_sum($eligible);$votes=[];
        foreach($eligible as $id=>$weight)$votes[$id]=intdiv(900000*$weight,$total);
        $rank=array_keys($votes);usort($rank,fn($a,$b)=>$votes[$b]<=>$votes[$a]?:strcmp($a,$b));
        // Do not arbitrarily decide exact ties: require independent adjudication.
        $valid=array_sum($votes);$first=['votes'=>$votes,'blank_invalid'=>10000];$second=null;
        if($votes[$rank[0]]*2<=$valid){
            self::check(!isset($rank[2])||$votes[$rank[1]]!==$votes[$rank[2]],'qualification tie');
            [$a,$b]=$rank;$n=intdiv(900000*$eligible[$a],$eligible[$a]+$eligible[$b]);
            self::check($n!==450000,'runoff tie');
            $second=['votes'=>[$a=>$n,$b=>900000-$n],'blank_invalid'=>10000];
        }
        return ['ballot'=>['registered'=>1000000,'first'=>$first,'second'=>$second],'first_round_day'=>$dates['first'],'second_round_day'=>$dates['second']];
    }
    /** Recompute the national fictional ballot using the existing two-round count.
     * Only a server-side simulation evidence service can sign this operation.
     * It is not a prediction, nor a population-calibrated vote generator. */
    public static function ballotTransition(array $s,array $e):array{
        $p=$e['payload'];$day=$e['day'];self::fields($p,['ballot','first_round_day','second_round_day']);
        self::check($e['actor']==='career-election-clerk'&&$e['evidence_sha']===self::digest(['kind'=>'fictional-presidential-ballot-v1','world'=>$s['world'],'day'=>$day,'payload'=>$p]),'ballot evidence binding');
        self::day($p['first_round_day']);self::day($p['second_round_day']);
        $current=$s['active']['president']??null;$old=$current===null?null:$s['mandates'][$current];
        if($day===CareerScoreContract::START){
            self::check($old===null&&$p['first_round_day']===$day&&$p['second_round_day']===$day,'opening scenario ballot convention');
        }else{
            self::check($old!==null&&$old['expires']===$day,'election requires term expiry');
            $dates=$old['interim']?self::vacancyCalendar($old):FrenchElectionCalendar::presidentialPoll($day);
            self::check($dates!==null&&$p['first_round_day']===$dates['first']&&$p['second_round_day']===$dates['second'],'ballot dates differ from calendar');
            if($old['interim'])self::check(self::json($p)===self::json(self::automaticBallot($s,$day)),'vacancy ballot differs from certified fixture');
        }
        foreach(array_keys($p['ballot']['first']['votes']) as $id)self::check(isset($s['careers'][$id])&&$s['careers'][$id]['admitted_on']<=$p['first_round_day'],'candidate not admitted');
        $count=PresidentialMandate::countElection($p['ballot'],false);$winner=$count['first']['winner']??$count['second']['winner']??null;
        self::check(is_string($winner)&&isset($s['careers'][$winner]),'unresolved ballot');
        $reason=$old===null?'election':(!$old['interim']&&$old['career']===$winner?'renewal':'succession');
        $date=new \DateTimeImmutable($day);$year=(int)$date->format('Y')+$s['constitution_rules']['next_term_years'];$month=(int)$date->format('m');
        $first=$date->setDate($year,$month,1);$expires=$first->setDate($year,$month,min((int)$date->format('d'),(int)$first->format('t')))->format('Y-m-d');
        return ['reason'=>$reason,'close'=>$old===null?[]:['president'],'grants'=>[['office'=>'president','career'=>$winner,'expires'=>$expires,'interim'=>false]]];
    }
    /** Institution-only cabinet decisions; the account and generation are derived from a verified session. */
    public static function cabinetTransition(array &$s,array $e):?array{
        $p=$e['payload'];self::fields($p,['kind','mandate','generation','target','portfolio','proposal']);
        self::check(is_int($p['generation'])&&is_string($p['mandate'])&&is_string($p['kind'])&&$e['evidence_sha']===self::digest(['kind'=>'session-cabinet-act-v1','world'=>$s['world'],'day'=>$e['day'],'actor'=>$e['actor'],'payload'=>$p]),'cabinet evidence binding');
        $day=$e['day'];$kind=$p['kind'];$account=$e['actor'];$mandate=$p['mandate'];$generation=$p['generation'];
        $ability=match($kind){'appoint_pm'=>'president.request_appointment','propose_minister'=>'government.propose_ministers','confirm_minister'=>'president.request_appointment','resign_pm'=>'government.present_resignation',default=>throw new \DomainException('Unknown cabinet act')};
        self::check(self::allows($s,$account,$mandate,$generation,$ability,$day),'cabinet authority denied');
        $end=(new \DateTimeImmutable(CareerScoreContract::START))->modify('+'.CareerScoreContract::HORIZON.' days')->format('Y-m-d');
        if($kind==='appoint_pm'){
            self::check(is_string($p['target'])&&isset($s['careers'][$p['target']])&&$p['portfolio']===null&&$p['proposal']===null&&!isset($s['active']['prime-minister']),'PM appointment requires vacant office');
            return ['reason'=>'appointment','close'=>[],'grants'=>[['office'=>'prime-minister','career'=>$p['target'],'expires'=>$end,'interim'=>false]]];
        }
        if($kind==='resign_pm'){
            self::check($p['target']===null&&$p['portfolio']===null&&$p['proposal']===null,'resignation shape');
            $close=array_values(array_filter(array_keys($s['active']),fn($o)=>$o==='prime-minister'||str_starts_with($o,'minister-')));
            return ['reason'=>'resignation','close'=>$close,'grants'=>[]];
        }
        if($kind==='propose_minister'){
            self::check(is_string($p['target'])&&isset($s['careers'][$p['target']])&&is_string($p['portfolio'])&&in_array($p['portfolio'],self::MINISTRIES,true)&&$p['proposal']===null,'minister proposal shape');
            $id='proposal-'.hash('sha256',self::json([$s['world'],$e['actor'],$e['command']]));
            self::check(!isset($s['appointment_proposals'][$id]),'duplicate appointment proposal');
            $s['appointment_proposals'][$id]=['id'=>$id,'target'=>$p['target'],'portfolio'=>$p['portfolio'],'pm_mandate'=>$mandate,'day'=>$day,'status'=>'pending'];return null;
        }
        self::check($p['target']===null&&$p['portfolio']===null&&is_string($p['proposal']),'confirmation shape');
        $proposal=$s['appointment_proposals'][$p['proposal']]??null;
        self::check($proposal!==null&&$proposal['status']==='pending'&&($s['active']['prime-minister']??null)===$proposal['pm_mandate']&&$day<$s['mandates'][$proposal['pm_mandate']]['expires'],'stale or missing PM proposal');
        $office='minister-'.$proposal['portfolio'];$s['appointment_proposals'][$p['proposal']]['status']='confirmed';
        return ['reason'=>'reshuffle','close'=>isset($s['active'][$office])?[$office]:[],'grants'=>[['office'=>$office,'career'=>$proposal['target'],'expires'=>$end,'interim'=>false]]];
    }
    /** Country-specific government authority derives from the existing comparative
     * accountability journal, not the French appointment model or a role string. */
    public static function foreignGovernment(array &$s,array $e):void{
        $p=$e['payload'];self::fields($p,['government_json','expires']);
        self::check($s['country']!=='FR'&&$e['actor']==='career-comparative-clerk'&&is_string($p['government_json'])&&strlen($p['government_json'])<=1000000,'foreign government scope');
        $gov=ComparativeGovernment::restore(json_decode($p['government_json'],true,64,JSON_THROW_ON_ERROR))->snapshot();
        $day=$e['day'];$last=$gov['history'][array_key_last($gov['history'])]??null;
        self::check($gov['world_id']===$s['world']&&$gov['country']===$s['country']&&$gov['day']===$day&&$last!==null,'government source scope');
        $proof=$last['evidence'];$id=$proof['evidence_id'];
        self::check(substr($proof['at']??$proof['day'],0,10)===$day&&!isset($s['foreign_evidence'][$id]),'stale or reused foreign evidence');
        self::check($e['evidence_sha']===self::digest(['kind'=>'comparative-government-replay-v1','world'=>$s['world'],'country'=>$s['country'],'payload'=>$p]),'foreign evidence binding');
        $office=str_replace('_','-',InstitutionalRulebook::forCountry($s['country'])['head_of_government']);
        $previous=$s['active'][$office]??null;
        if($gov['status']==='in_office'){
            self::check(in_array($last['action'],['accountability_resolve','certified_electoral_formation'],true)&&isset($s['careers'][$gov['incumbent']])&&is_string($p['expires']),'certified foreign successor required');
            self::day($p['expires']);self::check($p['expires']>$day,'foreign expiry');
            if($s['country']==='US')self::check($p['expires']<=(new \DateTimeImmutable($day))->modify('+4 years')->format('Y-m-d'),'US maximum modeled term');
            if($previous!==null){
                self::check($s['mandates'][$previous]['career']!==$gov['incumbent'],'same incumbent does not create new mandate');
                self::close($s,$office,$day,'succession');
            }
            $generation=($s['generations'][$office]??0)+1;$s['generations'][$office]=$generation;
            $mandate='mandate-'.hash('sha256',self::json([$s['world'],$s['country'],$office,$generation,$e['command']]));
            $s['mandates'][$mandate]=['id'=>$mandate,'office'=>$office,'career'=>$gov['incumbent'],'generation'=>$generation,'start'=>$day,'expires'=>$p['expires'],'end'=>null,'end_reason'=>null,'interim'=>false,'constitution_revision'=>$s['constitution_revision'],'powers'=>['foreign.government.represent'],'series'=>null,'terms'=>0];
            $s['active'][$office]=$mandate;$s['government']='in_office';
        }else{
            self::check(in_array($gov['status'],['caretaker','election_pending','removed_successor_pending'],true)&&$p['expires']===null&&$previous!==null&&$s['mandates'][$previous]['career']===$gov['incumbent'],'foreign closure proof');
            self::close($s,$office,$day,'constitutional_transition');$s['government']=$gov['status'];
        }
        $s['foreign_evidence'][$id]=$e['evidence_sha'];
    }
    /** Applies only institution-certified commands, never raw player inputs. */
    public static function apply(array $s,array $e):array{
        self::fields($e,['world','country','day','world_revision','command','actor','operation','payload','evidence_sha']);
        self::id($e['command']);self::id($e['actor']);self::day($e['day']);
        self::check($e['world']===$s['world']&&$e['country']===$s['country']&&$e['day']>=$s['day']&&is_int($e['world_revision'])&&$e['world_revision']>=0&&preg_match('/^[a-f0-9]{64}$/D',$e['evidence_sha'])===1,'event scope');
        $p=$e['payload'];$day=$e['day'];$op=$e['operation'];
        if($s['country']!=='FR')self::check(in_array($op,['admit','admit_real_ai_roster','foreign_government','research_observe'],true),'French powers unavailable outside France');
        if($s['mode']==='career'&&$op==='presidential_ballot'){$p=self::ballotTransition($s,$e);$op='transition';}
        if($s['mode']==='career'&&$op==='cabinet_act'){$p=self::cabinetTransition($s,$e);$op=$p===null?'cabinet_proposal_recorded':'transition';}
        if($s['mode']==='research'){
            self::check($op==='research_observe','research cannot grant mandates');
            self::fields($p,['regime_label','assembly_status','legality']);
            self::check(in_array($p['regime_label'],['republic','monarchy','other','contested'],true)&&in_array($p['assembly_status'],['active','disrupted','suspended','restored'],true)&&in_array($p['legality'],['disputed','unverified','recognized'],true),'research categories');
            $s['research_observation']=['day'=>$day,...$p,'runtime_authority'=>false,'rankable'=>false];
        }elseif($op==='admit_real_ai_roster'){
            self::admitRealAutonomousRoster($s,$e);
        }elseif($op==='claim_real_career'){
            self::claimRealCareer($s,$e);
        }elseif($op==='admit_ai_roster'){
            self::admitAutonomousRoster($s,$e);
        }elseif($op==='admit'){
            self::fields($p,['career','account','character','mode']);foreach(['career','account','character'] as $k)self::id($p[$k]);
            self::check(!isset($s['careers'][$p['career']])&&in_array($p['mode'],['opening','late_takeover'],true)&&($p['mode']==='opening')===($day===CareerScoreContract::START),'admission mode');
            foreach($s['careers'] as $c)self::check($c['account']!==$p['account']&&$c['character']!==$p['character'],'duplicate career');
            $s['careers'][$p['career']]=['account'=>$p['account'],'character'=>$p['character'],'admitted_on'=>$day,'mode'=>$p['mode'],'first_series'=>null];
        }elseif($op==='foreign_government'){
            self::foreignGovernment($s,$e);
        }elseif($op==='cabinet_proposal_recorded'){
            self::check($e['operation']==='cabinet_act','invalid proposal event');
        }elseif($op==='transition'){
            self::fields($p,['reason','close','grants']);
            self::check(in_array($p['reason'],['election','renewal','succession','appointment','reshuffle','resignation','censure','expiry'],true)&&array_is_list($p['close'])&&array_is_list($p['grants'])&&count($p['close'])<=1000&&count($p['grants'])<=1000&&count($p['close'])+count($p['grants'])>0,'transition shape');
            self::check(count(array_unique($p['close']))===count($p['close']),'duplicate closure');
            $previous=[];foreach($p['close'] as $office){self::office($office);$previous[$office]=self::close($s,$office,$day,$p['reason']);}
            if(isset($previous['prime-minister']))foreach($s['active'] as $office=>$mid)self::check(self::office($office)['kind']!=='minister','cabinet closure incomplete');
            $grants=$p['grants'];usort($grants,fn($a,$b)=>(($a['office']==='prime-minister'?0:1)<=>($b['office']==='prime-minister'?0:1))?:strcmp($a['office'],$b['office']));
            if(in_array($p['reason'],['resignation','censure','expiry'],true))self::check($grants===[],'closure only');
            foreach($grants as $g){
                $kind=self::office($g['office'])['kind'];
                if(in_array($kind,['pm','minister'],true))self::check(in_array($p['reason'],['appointment','reshuffle'],true),'cabinet appointment evidence required');
                if(in_array($kind,['deputy','senator'],true))self::check(in_array($p['reason'],['election','renewal','succession'],true),'legislative election evidence required');
                self::grant($s,$g,$day,$p['reason'],$previous[$g['office']]??null,$e['command']);
            }
            if($p['reason']==='censure')self::check(isset($previous['prime-minister'])&&count(array_filter(array_keys($previous),fn($o)=>!in_array(self::office($o)['kind'],['pm','minister'],true)))===0,'censure scope');
            if($p['reason']==='renewal')self::check(count($previous)===1&&isset($previous['president'])&&count($grants)===1&&$grants[0]['office']==='president','renewal scope');
            $s['government']=isset($s['active']['prime-minister'])?'in_office':'vacant';
        }elseif($op==='dissolve_assembly'){
            self::fields($p,['presidential_mandate']);$mid=$s['active']['president']??null;
            self::check($s['assembly']==='active'&&$mid!==null&&$mid===$p['presidential_mandate']&&!$s['mandates'][$mid]['interim']&&$day<$s['mandates'][$mid]['expires'],'only incumbent president, not interim');
            if(isset($s['last_dissolution_election']))self::check($day>=(new \DateTimeImmutable($s['last_dissolution_election']))->modify('+1 year')->format('Y-m-d'),'dissolution interval');
            foreach(array_keys($s['active']) as $office)if(self::office($office)['kind']==='deputy')self::close($s,$office,$day,'dissolution');
            $s['assembly']='dissolved';$s['assembly_changed_on']=$day;$s['last_dissolution']=$day;
        }elseif($op==='restore_assembly'){
            self::fields($p,[]);self::check($s['assembly']==='dissolved','assembly not dissolved');
            $elapsed=CareerScoreContract::elapsed($s['assembly_changed_on'],$day);self::check($elapsed>=20&&$elapsed<=40,'constitutional election window');$s['assembly']='active';$s['assembly_changed_on']=$day;$s['last_dissolution_election']=$day;
        }elseif($op==='constitution_revision'){
            self::fields($p,['previous_sha','revision_state_json','procedure']);
            self::check($p['procedure']==='article89'&&$p['previous_sha']===$s['constitution_sha']&&is_string($p['revision_state_json'])&&strlen($p['revision_state_json'])<=1000000,'revision evidence');
            // Preserve the original serialization for the existing strict revision replay.
            $revision=ConstitutionalRevision::restore(json_decode($p['revision_state_json'],true,64,JSON_THROW_ON_ERROR))->snapshot();
            self::check($revision['world_id']===$s['world']&&$revision['day']===$day&&$revision['constitution_version']+1>$s['constitution_revision'],'effective revision scope');
            $clauses=$revision['effective_clauses'];
            foreach($s['constitution_rules']['effective_clauses'] as $key=>$old)self::check(isset($clauses[$key])&&self::digest($clauses[$key])===self::digest($old),'effective clause cannot disappear or change silently');
            // Only these two prospective rules are composed. Any other clause
            // needs a separate reviewed mapping instead of guessed consequences.
            foreach(array_keys($clauses) as $key)self::check(in_array((string)$key,['6','6-duration'],true),'unmapped constitutional clause');
            $limit=ConstitutionalRevision::termLimitProof($revision,$s['world'],$day);
            $duration=ConstitutionalRevision::termLengthProof($revision,$s['world'],$day);
            $s['constitution_rules']=['term_limit'=>$limit===null?2:null,'next_term_years'=>$duration===null?5:7,'effective_clauses'=>$clauses];
            $s['constitution_revision']=$revision['constitution_version']+1;
            $s['constitution_sha']=self::digest(['source'=>self::SOURCE,'revision'=>$s['constitution_revision'],'rules'=>$s['constitution_rules']]);
        }else throw new \DomainException('Unsupported career event');
        // No unresolved expired grant may survive a committed transition day.
        foreach($s['active'] as $mid)self::check($s['mandates'][$mid]['expires']>$day,'expired office requires certified closure');
        $s['day']=$day;$s['sequence']++;$s['last_event_sha']=self::digest(['previous'=>$s['last_event_sha'],'event'=>$e]);return $s;
    }
    public static function allows(array $s,string $account,string $mandate,int $generation,string $ability,string $day):bool{
        $m=$s['mandates'][$mandate]??null;if($s['mode']!=='career'||$m===null)return false;
        return $day>=$s['day']&&$day>=$m['start']&&$day<$m['expires']&&$m['end']===null&&($s['active'][$m['office']]??null)===$mandate&&$m['generation']===$generation&&$s['careers'][$m['career']]['account']===$account&&in_array($ability,$m['powers'],true);
    }
}
/** Signature verifies the trusted adapter's attestation, not the underlying law.
 * The secret is process-side, never passed to HTTP callers or persisted. */
final class CareerInstitutionEvidence
{
    public function __construct(private readonly string $secret){CareerInstitutionLedger::check(strlen($secret)>=32,'weak evidence key');}
    public function verify(array $event,string $signature):void{
        CareerInstitutionLedger::check(hash_equals(hash_hmac('sha256',CareerInstitutionLedger::json($event),$this->secret),$signature),'unverified institutional evidence');
    }
}
