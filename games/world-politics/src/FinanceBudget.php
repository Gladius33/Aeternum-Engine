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
    public const SOURCE='https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000019241054/';
    public static function catalog():array
    {
        return [
            'budget-2027-services'=>['revenue_delta'=>100000,'expense_delta'=>300000,'ministries'=>['education'=>150000,'health'=>100000,'justice'=>50000]],
            'budget-2027-balance'=>['revenue_delta'=>600000,'expense_delta'=>100000,'ministries'=>['education'=>50000,'health'=>30000,'justice'=>20000]]
        ];
    }
    public static function policies():array
    {
        $p=[];
        foreach(self::catalog() as $id=>$a)$p[$id]=['revenue_delta'=>$a['revenue_delta'],'expense_delta'=>$a['expense_delta'],
            'pressure_delta'=>['workers'=>0,'retired'=>0],'duration_days'=>365];
        return $p;
    }
    public static function initial():array
    {return ['version'=>self::VERSION,'classification'=>'fictional_scenario','source'=>self::SOURCE,'year'=>2027,'bills'=>[]];}
    public static function digest(string $policy):string
    {return hash('sha256',json_encode(['year'=>2027,'policy'=>$policy,'appropriations'=>self::catalog()[$policy]],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));}
    public static function apply(array $s,array $cab,array $e):array
    {
        $d=$e['data'];$day=$e['day'];$id=$e['evidence_id'];$kind=$e['kind'];
        if(!is_array($d))throw new \InvalidArgumentException('Invalid finance evidence');
        if($kind==='finance_file'){
            self::fields($d,['policy_id','prime_minister','finance_minister','government_project_certified']);
            FrenchCabinet::requirePrimeMinister($cab,$d['prime_minister']);
            if(!isset(self::catalog()[$d['policy_id']])||$d['finance_minister']===null||$d['finance_minister']!==$cab['ministers']['economy']
                ||$d['government_project_certified']!==true||$day>='2027-01-01'||count($s['bills'])>=20)
                throw new \DomainException('Invalid finance initiative');
            foreach($s['bills'] as $b)if(!in_array($b['status'],['rejected'],true))throw new \DomainException('One active annual finance bill');
            $b=['id'=>$id,'policy_id'=>$d['policy_id'],'text_sha'=>self::digest($d['policy_id']),'filed_on'=>$day,
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
                    if($b['status']!=='adopted'||$b['review']!=='cleared'||$day>='2027-01-01'||$d['president']!==$cab['president']
                        ||$d['promulgation_certified']!==true||$d['publication_certified']!==true)
                        throw new \DomainException('No timely reviewed finance law');
                    $b['status']='published';$b['promulgated_on']=$day;$b['published_on']=$day;$b['effective_on']='2027-01-01';break;
                default:throw new \InvalidArgumentException('Unknown finance event');
            }
        }
        $s['bills'][$b['id']]=$b;return [$s,['budget'=>$b,'policy_authorized'=>false]];
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
        $reference=['classification'=>'fictional_treasury_projection','currency'=>'EUR','unit'=>'minor','annual_interest_bps'=>$rate,
            'opening_debt'=>$openingDebt,'cash'=>$cash,'debt'=>$debt,'interest_total'=>$interest,'borrowing_total'=>$issued,
            'primary_balance_total'=>$primary,'days'=>$rows,'bills'=>$bills,
            'continuity_review_required'=>$dom['day']>='2027-01-01'&&!array_filter($bills,fn($b)=>$b['status']==='published'&&$b['implementation_authorized'])];
        $native=FrancCommittedBudget::project($dom);
        return $native===null?$reference:[...$native,'cash'=>$native['latest']['closing_state']['accounts']['treasury_frf_minor'],'debt'=>$native['latest']['valuation']['closing_gross_debt_frf_minor'],'unit'=>'minor','bills'=>$bills,'continuity_review_required'=>$reference['continuity_review_required'],'reference_eur_only'=>$reference];
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
