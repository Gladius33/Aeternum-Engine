<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CountryCatalog.php';

 /** Read-only planning reference; no election or office is certified by a projection. */
final class NationalRenewalCalendar
{
    public const VERSION='wp61-national-renewal-2026-11-v1';
    public const START='2026-11';
    public const END='2100-12';

    public static function pack():array
    {
        $raw=file_get_contents(__DIR__.'/../data/2026-11-26-national-renewals-v1.json');
        if($raw===false)throw new \UnexpectedValueException('Missing national renewal pack');
        $p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        $expected=[...CountryCatalog::EU_2026,'CH',...CountryCatalog::STRATEGIC_STARTER];sort($expected,SORT_STRING);
        $actual=array_keys($p['countries']);sort($actual,SORT_STRING);
        if($p['version']!==self::VERSION||$actual!==$expected)throw new \UnexpectedValueException('National coverage mismatch');
        foreach($p['countries'] as $r){
            if(!is_int($r['term_years'])||$r['term_years']<1||$r['term_years']>10
                ||!in_array($r['designation'],['direct','indirect','appointed'],true))
                throw new \UnexpectedValueException('Invalid renewal rule');
            self::month($r['expected_renewal_month']);
        }
        return $p+['sha256'=>hash('sha256',$raw)];
    }
    private static function month(string $m):\DateTimeImmutable
    {
        $d=\DateTimeImmutable::createFromFormat('!Y-m',$m,new \DateTimeZone('UTC'));
        if($d===false||$d->format('Y-m')!==$m||$m<self::START||$m>self::END)
            throw new \InvalidArgumentException('Month outside supported range');
        return $d;
    }
    public static function usPoll(int $year):string
    {
        if($year<2026||$year>2100||$year%2!==0)throw new \InvalidArgumentException('Not a federal general election year');
        $d=new \DateTimeImmutable($year.'-11-01',new \DateTimeZone('UTC'));
        while($d->format('N')!=='1')$d=$d->modify('+1 day');
        return $d->modify('+1 day')->format('Y-m-d');
    }
    /** Inclusive month horizon, capped at 25 years. No implicit results, dissolution or appointment. */
    public static function schedule(string $from,string $through,?string $country=null):array
    {
        $a=self::month($from);$b=self::month($through);
        if($b<$a||$b>$a->modify('+25 years'))throw new \InvalidArgumentException('Invalid calendar horizon');
        $p=self::pack();$rows=$p['countries'];
        if($country!==null)$rows=[$country=>$rows[$country]??throw new \DomainException('No reference for country')];
        $events=[];
        foreach($rows as $cc=>$r){
            $d=self::month($r['expected_renewal_month']);$n=0;
            while($d<=$b){
                if($d>=$a){
                    $m=$d->format('Y-m');$year=(int)$d->format('Y');
                    $events[]=['country'=>$cc,'kind'=>'lower_or_single_chamber_renewal','month'=>$m,
                        'day'=>$cc==='US'?self::usPoll($year):null,'designation'=>$r['designation'],
                        'classification'=>$cc==='US'?'statutory_ordinary_date':($n===0?'reference_expected_month':'scenario_recurrence'),
                        'certifies_result'=>false];
                    if($cc==='US'&&$year%4===0)$events[]=['country'=>'US','kind'=>'presidential_election','month'=>$m,
                        'day'=>self::usPoll($year),'designation'=>'electoral_college','classification'=>'statutory_ordinary_date','certifies_result'=>false];
                }
                $d=$d->modify('+'.$r['term_years'].' years');$n++;
            }
        }
        if($country===null||$country==='CH'){
            for($y=(int)$a->format('Y');$y<=(int)$b->format('Y');$y++){
                $m=$y.'-12';if($m<$from||$m>$through)continue;
                $events[]=['country'=>'CH','kind'=>'confederation_presidency_selection','month'=>$m,'day'=>null,
                    'designation'=>'indirect','classification'=>'ordinary_annual_month','certifies_result'=>false];
                if($y>=2027&&($y-2027)%4===0)$events[]=['country'=>'CH','kind'=>'federal_council_renewal','month'=>$m,'day'=>null,
                    'designation'=>'indirect','classification'=>'ordinary_four_year_month','certifies_result'=>false];
            }
        }
        usort($events,static fn($x,$y)=>[$x['month'],$x['country'],$x['kind']]<=>[$y['month'],$y['country'],$y['kind']]);
        return ['version'=>self::VERSION,'reference_sha'=>$p['sha256'],'from'=>$from,'through'=>$through,'events'=>$events,
            'changes_world_state'=>false,'complete_electoral_law'=>false];
    }
    /** Only the personal term-limit dimension; other eligibility rules remain external.
     * Counts must originate from a trusted verified office history, never a player payload.
     * Null means this module has no applicable verified numerical decision. */
    public static function termLimit(string $country,int $elections,int $consecutive,bool $usOtherTermOverTwoYears=false):?bool
    {
        if($elections<0||$consecutive<0||$consecutive>$elections||($country!=='US'&&$usOtherTermOverTwoYears))
            throw new \InvalidArgumentException('Invalid verified tenure counts');
        return match($country){
            'FR'=>$consecutive<2,
            'US'=>$elections<($usOtherTermOverTwoYears?1:2),
            'MX','KR'=>$elections===0,
            default=>null
        };
    }
    public static function executiveReferences():array
    {
        return [
            'FR'=>['office'=>'president','years'=>5,'limit'=>'two_consecutive_terms','source'=>'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000019241002/2026-08-14'],
            'US'=>['office'=>'president','years'=>4,'limit'=>'two_elections_total_with_succession_exception','source'=>'https://www.archives.gov/founding-docs/amendments-11-27'],
            'MX'=>['office'=>'president','years'=>6,'limit'=>'no_reelection','source'=>'https://www.idea.int/node/157737'],
            'KR'=>['office'=>'president','years'=>5,'limit'=>'no_reelection','source'=>'https://www.idea.int/node/161004'],
            'CH'=>['office'=>'federal_councillor','years'=>4,'limit'=>'reelectable','collective_members'=>7,'rotating_presidency_years'=>1,'source'=>'https://www.admin.ch/fr/election-du-conseil-federal-procedure']
        ];
    }
    public static function report():array
    {
        return self::pack()+['executive_references'=>self::executiveReferences(),
            'default_projection'=>self::schedule('2027-05','2047-05'),
            'all_countries_playable'=>false,'certified_results'=>false,
            'additional_legal_sources'=>['https://uscode.house.gov/us/usc/t2/s7/','https://uscode.house.gov/us/usc/t3/s21/']];
    }
}
