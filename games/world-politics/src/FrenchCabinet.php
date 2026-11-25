<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CampaignLifecycle.php';

/** Fictional cabinet, separately from the presidency and parliamentary
 * membership. No real persons, political targeting, or coercive strategies.
 */
final class FrenchCabinet
{
    public const VERSION='wp44-fr-cabinet-v1';
    public const SOURCE='https://qpc360.conseil-constitutionnel.fr/constitution-4-octobre-1958';
    public const PORTFOLIOS=['economy','education','health','justice','interior','defence','foreign_affairs'];
    public static function catalog():array
    {
        $p=['pm-initial'=>['affiliation'=>'civic','eligible'=>true]];
        foreach(['civic','social','liberal','independent'] as $group){
            $p['pm-'.$group]=['affiliation'=>$group,'eligible'=>true];
            foreach(self::PORTFOLIOS as $office)$p[$group.'-'.$office]=['affiliation'=>$group,'eligible'=>true];
        }
        return $p;
    }
    public static function initial(string $day):array
    {
        $ministers=[];
        foreach(self::PORTFOLIOS as $office)$ministers[$office]='civic-'.$office;
        return ['version'=>self::VERSION,'classification'=>'fictional_scenario','source'=>self::SOURCE,
            'president'=>CampaignLifecycle::PLAYER,'presidential_affiliation'=>'civic','prime_minister'=>'pm-initial',
            'appointed_on'=>$day,'generation'=>1,'status'=>'in_office','resignation_on'=>null,
            'ministers'=>$ministers,'archives'=>[],'history'=>[]];
    }
    public static function sync(array $s,array $crisis,string $day):array
    {
        if($crisis['government']==='resignation_required'&&$s['status']==='in_office'){
            $s['status']='resignation_required';
            $s['history'][]=['day'=>$day,'type'=>'resignation_required','cause'=>'constitutional_responsibility'];
        }
        return $s;
    }
    public static function resignation(array $s,string $day,string $pm):array
    {
        if($pm!==$s['prime_minister']||!in_array($s['status'],['in_office','resignation_required'],true))
            throw new \DomainException('Resignation must be presented by incumbent prime minister');
        $s['status']='caretaker';$s['resignation_on']=$day;
        $s['history'][]=['day'=>$day,'type'=>'resignation_presented','prime_minister'=>$pm];
        return $s;
    }
    public static function appoint(array $s,string $day,string $pm,array $assembly):array
    {
        self::eligible($pm,$assembly);
        if($s['status']!=='caretaker'||$s['resignation_on']===null)throw new \DomainException('Certified resignation required before succession');
        if($s['generation']>=100)throw new \DomainException('Cabinet generation limit');
        $s['archives'][]=['generation'=>$s['generation'],'prime_minister'=>$s['prime_minister'],
            'appointed_on'=>$s['appointed_on'],'resignation_on'=>$s['resignation_on'],'ministers'=>$s['ministers']];
        $s['generation']++;$s['prime_minister']=$pm;$s['appointed_on']=$day;$s['resignation_on']=null;
        $s['ministers']=array_fill_keys(self::PORTFOLIOS,null);$s['status']='in_office';
        $s['history'][]=['day'=>$day,'type'=>'prime_minister_appointed','prime_minister'=>$pm,'generation'=>$s['generation']];
        return $s;
    }
    public static function reshuffle(array $s,string $day,array $d,array $assembly):array
    {
        if($s['status']!=='in_office'||$d['prime_minister']!==$s['prime_minister']||$d['president']!==$s['president']
            ||$d['pm_proposal_certified']!==true||$d['presidential_act_certified']!==true||$d['incompatibilities_cleared']!==true)
            throw new \DomainException('Article 8 joint appointment authority required');
        $office=$d['portfolio'];$person=$d['person'];
        if(!is_string($office)||!in_array($office,self::PORTFOLIOS,true))throw new \DomainException('Unknown portfolio');
        if($person!==null){
            self::eligible($person,$assembly);
            if($person===$s['prime_minister']||in_array($person,array_diff_key($s['ministers'],[$office=>true]),true))
                throw new \DomainException('Duplicate officeholder in pilot');
        }
        if($person===$s['ministers'][$office])throw new \DomainException('No portfolio change');
        if(count($s['history'])>=2000)throw new \DomainException('Cabinet history limit');
        $old=$s['ministers'][$office];$s['ministers'][$office]=$person;
        $s['history'][]=['day'=>$day,'type'=>'portfolio_changed','portfolio'=>$office,'previous'=>$old,'person'=>$person];
        return $s;
    }
    private static function eligible(mixed $id,array $assembly):void
    {
        if(!is_string($id)||!(self::catalog()[$id]['eligible']??false)||isset($assembly['deputies'][$id])||$id===CampaignLifecycle::PLAYER)
            throw new \DomainException('Ineligible fictional officeholder');
    }
    public static function requirePrimeMinister(array $s,mixed $id):void
    {
        if($s['status']!=='in_office'||$s['prime_minister']!==$id)throw new \DomainException('Incumbent active prime minister required');
    }
    public static function projection(array $s,array $assembly):array
    {
        $affiliation=self::catalog()[$s['prime_minister']]['affiliation'];
        $groups=[];
        foreach($assembly['deputies'] as $d)$groups[$d['group']]=($groups[$d['group']]??0)+1;
        ksort($groups);
        // The original WP42 fixture names the civic bloc government.
        $g=$affiliation==='civic'&&isset($groups['government'])?'government':$affiliation;
        $support=isset($groups[$g])?[$g]:[];
        foreach($assembly['coalitions'] as $c)if($c['active']&&in_array($g,$c['groups'],true))$support=array_values(array_unique([...$support,...$c['groups']]));
        sort($support);
        $seats=0;foreach($support as $id)$seats+=$groups[$id]??0;
        return ['cabinet'=>$s,'prime_minister_affiliation'=>$affiliation,
            'cohabitation'=>$affiliation!==$s['presidential_affiliation']&&$affiliation!=='independent',
            'declared_support_groups'=>$support,'declared_support_seats'=>$seats,
            'absolute_majority'=>intdiv($assembly['occupied_seats'],2)+1,
            'declared_majority'=>$seats>=intdiv($assembly['occupied_seats'],2)+1,
            'support_is_not_confidence_vote'=>true,'competences'=>[
                'president'=>['appoint_prime_minister','appoint_ministers_on_pm_proposal','promulgate_laws'],
                'prime_minister'=>['direct_government','propose_ministerial_appointments','government_bill_initiative','engage_government_responsibility'],
                'government'=>['determine_and_conduct_national_policy','responsible_to_parliament'],
                'ministers'=>['portfolio_administration']
            ]];
    }
}
