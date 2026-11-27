<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancCommittedBudget.php';
require_once __DIR__.'/FrenchCabinet.php';

/** WP46 limited fictional annual finance bill. Minor currency units.
 * All budget variants are immutable scenario assumptions. */
final class FinanceBudget
{
    public const VERSION='wp46-finance-v1';
    public const VERSION_2028='wp71-finance-2028-v1';
    public const SERIES='wp81-finance-series-v1';
    public const CORRECTIVE='wp99-corrective-budget-v1';
    public const LAST_YEAR=2047;
    public static function isCorrective(string $id):bool{return preg_match('/^corrective-20[0-9]{2}-health-(increase|reduce)$/D',$id)===1;}
    public static function correctiveCatalog(int $year):array
    {
        if($year<2027||$year>self::LAST_YEAR)throw new \DomainException('Unsupported corrective year');
        // Fictional October-to-December state health appropriation, not social insurance.
        // Exactly 920 million EUR over 92 days, independently of the annual law.
        return [
            'corrective-'.$year.'-health-increase'=>['revenue_delta'=>0,'expense_delta'=>1000000000,'ministries'=>['health'=>1000000000]],
            'corrective-'.$year.'-health-reduce'=>['revenue_delta'=>0,'expense_delta'=>-1000000000,'ministries'=>['health'=>-1000000000]]
        ];
    }
    public static function correctivePolicies():array
    {
        $p=[];for($y=2027;$y<=self::LAST_YEAR;$y++)foreach(self::correctiveCatalog($y) as $id=>$a)
            $p[$id]=['revenue_delta'=>0,'expense_delta'=>$a['expense_delta'],'pressure_delta'=>['workers'=>0,'retired'=>0],'duration_days'=>92];
        return $p;
    }
    public static function eligibleCorrective(array $s,int $year):bool
    {
        if(($s['corrective_enabled']??false)!==true)return false;
        if($year===2027)return true; // inherited scenario baseline, not a certified annual act
        foreach($s['bills'] as $b)if(str_starts_with($b['policy_id'],'budget-'.$year.'-')&&$b['status']==='published')return true;
        return false;
    }
    public const SOURCE='https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000019241054/';
    public static function catalog(int $year=2027):array
    {
        if($year>=2028&&$year<=self::LAST_YEAR){
            // Hypothetical delta relative to the existing aggregate fiscal baseline,
            // NOT a 2028 defense appropriation or a replacement for the LPM.
            $delta=intdiv(634000000000,self::days($year));
            return [
                'budget-'.$year.'-defense-reduce'=>['revenue_delta'=>0,'expense_delta'=>-$delta,'ministries'=>['defense'=>-$delta]],
                'budget-'.$year.'-defense-maintain'=>['revenue_delta'=>0,'expense_delta'=>0,'ministries'=>['defense'=>0]],
                'budget-'.$year.'-defense-increase'=>['revenue_delta'=>0,'expense_delta'=>$delta,'ministries'=>['defense'=>$delta]]
            ];
        }
        if($year!==2027)throw new \DomainException('Unsupported finance year');
        return [
            'budget-2027-services'=>['revenue_delta'=>100000,'expense_delta'=>300000,'ministries'=>['education'=>150000,'health'=>100000,'justice'=>50000]],
            'budget-2027-balance'=>['revenue_delta'=>600000,'expense_delta'=>100000,'ministries'=>['education'=>50000,'health'=>30000,'justice'=>20000]]
        ];
    }
    public static function policies(int $year=2027):array
    {
        $p=[];
        foreach(self::catalog($year) as $id=>$a)$p[$id]=['revenue_delta'=>$a['revenue_delta'],'expense_delta'=>$a['expense_delta'],
            'pressure_delta'=>['workers'=>0,'retired'=>0],'duration_days'=>self::days($year)];
        return $p;
    }
    private static function days(int $year):int{return (int)(new \DateTimeImmutable($year.'-12-31'))->format('z')+1;}
    public static function seriesPolicies():array
    {
        $p=[];for($y=2028;$y<=self::LAST_YEAR;$y++)$p+=self::policies($y);return $p;
    }
    public static function initial(int $year=2027,bool $series=false,bool $corrective=false):array
    {
        self::catalog($year);
        if(($series&&$year!==2028)||($corrective&&!$series))throw new \DomainException('Invalid budget series origin');
        return ['version'=>$series?self::SERIES:($year===2028?self::VERSION_2028:self::VERSION),'classification'=>'fictional_scenario','source'=>self::SOURCE,'year'=>$year,'bills'=>[],...($series?['last_year'=>self::LAST_YEAR]:[]),...($corrective?['corrective_enabled'=>true]:[])];
    }
    public static function digest(string $policy,int $year=2027):string
    {
        $a=(self::isCorrective($policy)?self::correctiveCatalog($year):self::catalog($year))[$policy]??throw new \DomainException('Unknown finance policy');
        return hash('sha256',json_encode(['year'=>$year,'policy'=>$policy,'appropriations'=>$a],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    }
    public static function apply(array $s,array $cab,array $e):array
    {
        $d=$e['data'];$day=$e['day'];$id=$e['evidence_id'];$kind=$e['kind'];
        if(($s['version']??null)===self::SERIES)return self::applySeries($s,$cab,$e);
        $year=$s['year'];$corrective=$s['version']===self::CORRECTIVE;
        $cutoff=sprintf($corrective?'%04d-10-01':'%04d-01-01',$year);
        if(!$corrective&&$s['version']!==self::initial($year)['version'])throw new \DomainException('Invalid finance year pin');
        if(!is_array($d))throw new \InvalidArgumentException('Invalid finance evidence');
        if($kind==='finance_file'){
            self::fields($d,['policy_id','prime_minister','finance_minister','government_project_certified']);
            FrenchCabinet::requirePrimeMinister($cab,$d['prime_minister']);
            if(!isset((($corrective?self::correctiveCatalog($year):self::catalog($year)))[$d['policy_id']])||$d['finance_minister']===null||$d['finance_minister']!==$cab['ministers']['economy']
                ||$d['government_project_certified']!==true||$day>=$cutoff||($corrective?$day<$year.'-01-01':($year>=2028&&$day<sprintf('%04d-01-01',$year-1)))||count($s['bills'])>=20)
                throw new \DomainException('Invalid finance initiative');
            foreach($s['bills'] as $b)if(!in_array($b['status'],['rejected'],true))throw new \DomainException('One active annual finance bill');
            $b=['id'=>$id,'policy_id'=>$d['policy_id'],'text_sha'=>self::digest($d['policy_id'],$year),'filed_on'=>$day,
                'assembly_deadline'=>self::plus($day,40),'parliament_deadline'=>self::plus($day,70),'senate_deadline'=>null,
                'status'=>'assembly_pending','assembly_vote'=>null,'senate_vote'=>null,'transferred_on'=>null,
                'final_on'=>null,'final_route'=>null,'review'=>'none','promulgated_on'=>null,'published_on'=>null,'effective_on'=>null];
        }else{
            $b=$s['bills'][$d['bill_id']??'']??throw new \DomainException('Unknown budget bill');
            if(in_array($b['status'],['published','rejected'],true))throw new \DomainException('Terminal budget bill');
            switch($kind){
                case 'finance_assembly':
                    self::fields($d,['bill_id','text_sha','yes','no','abstain','blank_invalid']);
                    self::text($b,$d);
                    if($b['status']!=='assembly_pending'||$day>$b['assembly_deadline'])throw new \DomainException('Assembly first reading expired');
                    $b['assembly_vote']=self::vote($d,577);$b['status']='assembly_decided';break;
                case 'finance_transfer':
                    self::fields($d,['bill_id','prime_minister','transmission_certified']);
                    FrenchCabinet::requirePrimeMinister($cab,$d['prime_minister']);
                    if($d['transmission_certified']!==true||$day>$b['parliament_deadline']||
                        ($b['status']!=='assembly_decided'&&($b['status']!=='assembly_pending'||$day<$b['assembly_deadline'])))
                        throw new \DomainException('Premature Senate referral');
                    // The special constitutional fifteen-day deadline applies
                    // when the Assembly did not decide within forty days.
                    $b['senate_deadline']=$b['status']==='assembly_pending'?self::plus($day,15):null;
                    $b['transferred_on']=$day;$b['status']='senate_pending';break;
                case 'finance_senate':
                    self::fields($d,['bill_id','text_sha','yes','no','abstain','blank_invalid']);
                    self::text($b,$d);
                    if($b['status']!=='senate_pending'||$day>$b['parliament_deadline']||($b['senate_deadline']!==null&&$day>$b['senate_deadline']))
                        throw new \DomainException('Senate reading unavailable');
                    $b['senate_vote']=self::vote($d,348);$b['status']='senate_decided';break;
                case 'finance_final':
                    self::fields($d,['bill_id','text_sha','route','navette_certified','yes','no','abstain','blank_invalid']);
                    self::text($b,$d);
                    if($b['status']!=='senate_decided'||$day>$b['parliament_deadline']||$d['navette_certified']!==true)
                        throw new \DomainException('Final parliamentary procedure not verified');
                    if($d['route']==='identical'){
                        if(!($b['assembly_vote']['passed']??false)||!($b['senate_vote']['passed']??false)
                            ||$d['yes']!==0||$d['no']!==0||$d['abstain']!==0||$d['blank_invalid']!==0)
                            throw new \DomainException('Identical positive votes required');
                    }elseif($d['route']==='assembly_last_word'){
                        if(!self::vote($d,577)['passed']){$b['status']='rejected';break;}
                    }else throw new \DomainException('Unknown finance final route');
                    $b['status']='adopted';$b['final_on']=$day;$b['final_route']=$d['route'];break;
                case 'finance_review':
                    self::fields($d,['bill_id','text_sha','constitutional','decision_certified']);self::text($b,$d);
                    if($b['status']!=='adopted'||$b['review']!=='none'||$d['decision_certified']!==true||!is_bool($d['constitutional']))
                        throw new \DomainException('Invalid finance review');
                    $b['review']=$d['constitutional']?'cleared':'rejected';
                    if(!$d['constitutional'])$b['status']='rejected';break;
                case 'finance_publish':
                    self::fields($d,['bill_id','text_sha','president','promulgation_certified','publication_certified']);
                    self::text($b,$d);
                    if($b['status']!=='adopted'||$b['review']!=='cleared'||$day>=$cutoff||$d['president']!==$cab['president']
                        ||$d['promulgation_certified']!==true||$d['publication_certified']!==true)
                        throw new \DomainException('No timely reviewed finance law');
                    $b['status']='published';$b['promulgated_on']=$day;$b['published_on']=$day;$b['effective_on']=$cutoff;break;
                default:throw new \InvalidArgumentException('Unknown finance event');
            }
        }
        $s['bills'][$b['id']]=$b;return [$s,['budget'=>$b,'policy_authorized'=>false]];
    }
    private static function policyYear(string $policy):int
    {
        if(!preg_match('/^budget-(20[0-9]{2})-defense-(reduce|maintain|increase)$/D',$policy,$m))throw new \DomainException('Unknown annual policy');
        $y=(int)$m[1];if($y<2028||$y>self::LAST_YEAR)throw new \DomainException('Budget outside campaign horizon');return $y;
    }
    private static function applySeries(array $s,array $cab,array $e):array
    {
        if($s['year']!==2028||$s['last_year']!==self::LAST_YEAR)throw new \DomainException('Invalid series pin');
        $d=$e['data'];if(!is_array($d))throw new \InvalidArgumentException('Invalid finance evidence');
        $file=$e['kind']==='finance_file';
        if($file&&isset($s['bills'][$e['evidence_id']]))throw new \DomainException('Duplicate budget identifier');
        $policy=$file?($d['policy_id']??null):($s['bills'][$d['bill_id']??'']['policy_id']??null);
        if(!is_string($policy))throw new \DomainException('Unknown budget series bill');
        if(self::isCorrective($policy)){
            $y=(int)substr($policy,11,4);
            if(!isset(self::correctiveCatalog($y)[$policy])||!self::eligibleCorrective($s,$y))throw new \DomainException('Corrective budget not enabled or annual law missing');
            $single=['version'=>self::CORRECTIVE,'year'=>$y,'bills'=>array_filter($s['bills'],fn($b)=>str_starts_with($b['policy_id'],'corrective-'.$y.'-'))];
        }else{
            $y=self::policyYear($policy);
            $single=self::initial($y);$single['bills']=array_filter($s['bills'],fn($b)=>!self::isCorrective($b['policy_id'])&&self::policyYear($b['policy_id'])===$y);
        }
        [$next,$result]=self::apply($single,$cab,$e);
        foreach($next['bills'] as $id=>$bill)$s['bills'][$id]=$bill;
        return [$s,$result];
    }
    public static function filingYear(array $s,string $day):int
    {return $s['version']===self::SERIES?min(self::LAST_YEAR,max(2028,(int)substr($day,0,4)+1)):$s['year'];}
    public static function continuity(array $s,array $dom,array $bills):array
    {
        if($s['version']!==self::SERIES)return [];
        $out=[];
        for($y=2028;$y<=min(self::LAST_YEAR,(int)substr($dom['day'],0,4));$y++){
            $matching=array_filter($bills,fn($b)=>!self::isCorrective($b['policy_id'])&&self::policyYear($b['policy_id'])===$y&&$b['status']==='published'&&$b['implementation_authorized']);
            $out[$y]=['year'=>$y,'published_and_authorized'=>count($matching)===1,'continuity_review_required'=>count($matching)!==1];
        }
        return $out;
    }
    public static function proof(array $s,string $policy,string $sha,string $day):?array
    {
        foreach($s['bills'] as $b)if($b['status']==='published'&&$b['policy_id']===$policy&&$b['text_sha']===$sha&&$b['effective_on']===$day)return $b;
        return null;
    }
    public static function project(array $s,array $dom):array
    {
        // Independent treasury projection from the verified actual domestic
        // ledger. Debt is not double-counted as tax receipts. Fixed rate;
        // interest is added here, not written back to the domestic ledger.
        $b=$dom['macro_baseline']??null;
        $openingDebt=$b['opening_debt']??2000000000;$rate=$b['assumed_annual_interest_bps']??300;
        $cash=$dom['days'][0]['opening_balance']??$dom['balance'];$debt=$openingDebt;
        $interest=0;$issued=0;$primary=0;$rows=[];
        foreach($dom['days'] as $d){
            $flow=$d['revenue']-$d['expense'];
            if($b!==null){
                $t=$d['treasury'];$i=$t['interest'];$borrow=$t['new_borrowing'];$cash=$t['cash'];$debt=$t['debt'];
            }else{
                $i=intdiv($debt*300,3650000);$cash+=$flow-$i;$borrow=max(0,-$cash);$cash+=$borrow;$debt+=$borrow;
            }
            $interest+=$i;$issued+=$borrow;$primary+=$flow;
            $rows[]=['day'=>$d['day'],'primary_balance'=>$flow,'interest'=>$i,'new_borrowing'=>$borrow,'cash'=>$cash,'debt'=>$debt,...(isset($d['treasury']['principal_paid'])?['principal_paid'=>$d['treasury']['principal_paid'],'debt_change'=>$d['treasury']['debt_change'],'funding_gap'=>$d['treasury']['funding_gap'],'status'=>$d['treasury']['status']]:[]),...(isset($d['treasury']['crisis'])?['crisis'=>$d['treasury']['crisis']]:[])];
        }
        $bills=$s['bills'];foreach($bills as &$b){
            $b['seventy_day_review_due']=$dom['day']>=$b['parliament_deadline']&&in_array($b['status'],['assembly_pending','assembly_decided','senate_pending','senate_decided'],true);
            $b['implementation_authorized']=($dom['policies'][$b['policy_id']]['authorized_on']??null)!==null;
        }unset($b);
        $continuity=self::continuity($s,$dom,$bills);
        $reference=['classification'=>'fictional_treasury_projection','currency'=>'EUR','unit'=>'minor','annual_interest_bps'=>$rate,
            'opening_debt'=>$openingDebt,'cash'=>$cash,'debt'=>$debt,'interest_total'=>$interest,'borrowing_total'=>$issued,
            'primary_balance_total'=>$primary,'days'=>$rows,'bills'=>$bills,
            'continuity_review_required'=>$s['version']===self::SERIES?($continuity[(int)substr($dom['day'],0,4)]['continuity_review_required']??false):($dom['day']>=sprintf('%04d-01-01',$s['year'])&&!array_filter($bills,fn($b)=>$b['status']==='published'&&$b['implementation_authorized'])),...($s['version']===self::SERIES?['annual_continuity'=>array_values($continuity),'filing_year'=>self::filingYear($s,$dom['day'])]:[])];
        $native=FrancCommittedBudget::project($dom);
        return $native===null?$reference:[...$native,'cash'=>$native['latest']['closing_state']['accounts']['treasury_frf_minor'],'debt'=>$native['consolidated_gross_debt_frf_minor']??$native['latest']['valuation']['closing_gross_debt_frf_minor'],'unit'=>'minor','bills'=>$bills,'continuity_review_required'=>$reference['continuity_review_required'],'reference_eur_only'=>$reference];
    }
    private static function vote(array $d,int $cap):array
    {
        foreach(['yes','no','abstain','blank_invalid'] as $k)if(!is_int($d[$k])||$d[$k]<0||$d[$k]>$cap)throw new \InvalidArgumentException('Invalid budget vote');
        if($d['yes']+$d['no']===0||$d['yes']+$d['no']+$d['abstain']+$d['blank_invalid']>$cap)throw new \DomainException('Invalid turnout');
        return ['yes'=>$d['yes'],'no'=>$d['no'],'abstain'=>$d['abstain'],'blank_invalid'=>$d['blank_invalid'],'passed'=>$d['yes']>$d['no']];
    }
    private static function text(array $b,array $d):void{if($d['text_sha']!==$b['text_sha'])throw new \DomainException('Finance text mismatch');}
    private static function plus(string $day,int $n):string{return (new \DateTimeImmutable($day))->modify('+'.$n.' days')->format('Y-m-d');}
    private static function fields(array $a,array $b):void{$k=array_keys($a);sort($k);sort($b);if($k!==$b)throw new \InvalidArgumentException('Unexpected finance fields');}
}
