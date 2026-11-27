<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/InstitutionalSimulation.php';
require_once __DIR__.'/CareerInstitutionLedger.php';

/** An explicit alternate-history opening, not an assertion of 2027 incumbency.
 * No synthetic party leader or invented programme enters this profile. */
final class CareerForeignWorld
{
    public static function opening(array $state):InstitutionalSimulation{
        $cc=$state['country'];$day=$state['day'];
        CareerInstitutionLedger::check($cc!=='FR'&&$state['mode']==='career'&&$day===CareerScoreContract::START,'comparative opening only');
        $ids=[];foreach(CareerInstitutionLedger::realAutonomousRoster($state) as $id=>$person)
            if(in_array('head_of_government',$person['observed_roles'],true))$ids[]=$id;
        CareerInstitutionLedger::check(count($ids)===1&&isset($state['careers'][$ids[0]]),'documentary executive reference required');
        $seats=match($cc){
            'DE'=>['bundestag'=>100],'ES'=>['congress'=>100],'GB'=>['commons'=>100],
            'IT'=>['chamber'=>100,'senate'=>50],'JP'=>['representatives'=>100],'US'=>['house'=>100,'senate'=>50]
        };
        $inst=new InstitutionalSimulation($state['world'],$cc,$day);
        $inst->enableAccountability($ids[0],$seats);
        return $inst;
    }
    /** Parliamentary offices have no invented fixed personal electoral term.
     * The expiry is the simulation horizon; independently certified closure
     * can occur earlier. The US opening has the separate pinned term boundary. */
    public static function boundary(string $cc):string{
        return $cc==='US'?'2029-01-20':(new \DateTimeImmutable(CareerScoreContract::START))->modify('+'.CareerScoreContract::HORIZON.' days')->format('Y-m-d');
    }
    public static function json(array $v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
}
