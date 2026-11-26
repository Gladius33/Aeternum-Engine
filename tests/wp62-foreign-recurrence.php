<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\InstitutionalSimulation as I;
use Aeternum\WorldPolitics\ComparativeElectionCalendar as C;
use Aeternum\WorldPolitics\ForeignRecurringElections as F;
function ck62(bool $ok,string $label):void {if(!$ok)throw new RuntimeException('WP62 FAIL '.$label);echo "PASS WP62: $label\n";}
function make62(string $cc,bool $rec=true):I{
    $i=new I('world-test',$cc,F::START);
    $seats=match($cc){'DE'=>['bundestag'=>100],'ES'=>['congress'=>100],'GB'=>['commons'=>100],
        'IT'=>['chamber'=>100,'senate'=>50],'JP'=>['representatives'=>100],'US'=>['house'=>100,'senate'=>50]};
    $i->enableAccountability('initial-government',$seats);
    $i->enableElectoral(C::config($cc,F::START));$i->enablePoliticalAI();
    if($rec)$i->enableForeignRecurring();
    return $i;
}
foreach(['DE','ES','GB','IT','JP','US'] as $cc){
    $a=make62($cc);$a->advanceTo('2047-05-14');$s=$a->snapshot();
    ck62(count($s['foreign_recurring']['archive'])>=3,'multiple elections '.$cc);
    ck62(I::restore($s)->snapshot()===$s,'archive replay '.$cc);
    ck62($s['accountability']['incumbent']==='initial-government','no automatic executive assignment '.$cc);
    $b=make62($cc);foreach(['2029-12-31','2034-12-31','2039-12-31','2047-05-14'] as $day){$b->advanceTo($day);$b=I::restore($b->snapshot());}
    ck62($b->snapshot()===$s,'chunk-invariant ordinary election history '.$cc);
    $ids=array_column($s['events'],'id');ck62(count($ids)===count(array_unique($ids)),'unique autonomous negotiation IDs '.$cc);
    $bad=$s;$bad['foreign_recurring']['archive'][0]['config']['poll_day']='2027-05-15';
    try{I::restore($bad);throw new RuntimeException('Tampered archive accepted');}catch(DomainException $expected){}
}
$us=make62('US');$us->advanceTo('2030-11-05');$s=$us->snapshot();
ck62($s['electoral']['config']['poll_day']==='2030-11-05'&&$s['electoral']['status']==='results'&&$s['electoral']['government']===null,'US midterm is not a presidential election');
$legacy=make62('GB',false);$legacy->advanceTo('2047-05-14');
ck62(!isset($legacy->snapshot()['foreign_recurring'])&&$legacy->snapshot()['electoral']['config']['poll_day']==='2029-07-19','legacy snapshot semantics unchanged');
