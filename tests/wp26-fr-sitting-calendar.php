<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/FrenchSittingCalendar.php';
use Aeternum\WorldPolitics\FrenchSittingCalendar;
use Aeternum\WorldPolitics\FrenchConstitutionalCrisis;
function wp26(bool $pass,string $label):void {
    if (!$pass) throw new RuntimeException('FAILED WP26: '.$label);
    echo "PASS WP26: ".$label."\n";
}
function wp26Reject(callable $fn,string $label):void {
    try {$fn();}
    catch (\DomainException|\InvalidArgumentException|\UnexpectedValueException $error) {
        wp26(true,$label);return;
    }
    throw new RuntimeException('Expected refusal: '.$label);
}
$dates=[
    '2026-11-24','2026-11-25','2026-11-26','2026-11-27',
    '2026-11-30','2026-12-01','2026-12-02','2026-12-03',
    '2026-12-04','2026-12-07','2026-12-08'
];
$calendar=new FrenchSittingCalendar('fr-sitting-world',$dates,
    'fictional_scenario','https://www.assemblee-nationale.fr/');
wp26($calendar->snapshot()['provenance']==='fictional_scenario',
    'curated fictional sitting days cannot masquerade as historical French parliamentary agenda');
$file=FrenchConstitutionalCrisis::instant('2026-11-24T10:00:00+01:00');
$earliest=$file+172800;
wp26Reject(fn()=>$calendar->schedule(
    'censure-one',$file,$earliest,'2026-11-26T09:59:59+01:00'
),'48 hours must be fully elapsed before voting');
$record=$calendar->schedule(
    'censure-one',$file,$earliest,'2026-11-27T15:00:00+01:00'
);
wp26($record['third_following_sitting_day']==='2026-12-01'
    && $record['vote_day']==='2026-11-27',
    'the third SITTING day after 48-hour expiration is computed, not calendar day three');
wp26($calendar->schedule(
    'censure-one',$file,$earliest,'2026-11-27T15:00:00+01:00'
)===$record,'scheduling same verified slot is idempotent');
wp26Reject(fn()=>$calendar->schedule(
    'censure-one',$file,$earliest,'2026-11-30T15:00:00+01:00'
),'certified parliamentary slot cannot be silently moved');
wp26Reject(fn()=>$calendar->schedule(
    'late-motion',$file,$earliest,'2026-12-02T15:00:00+01:00'
),'third following sitting day is a strict latest deadline');
wp26Reject(fn()=>$calendar->schedule(
    'non-sitting',$file,$earliest,'2026-11-28T15:00:00+01:00'
),'weekend without an explicitly declared sitting cannot host votes');
$constitutional=[
    'world_id'=>'fr-sitting-world',
    'now'=>FrenchConstitutionalCrisis::instant('2026-11-25T23:00:00+01:00'),
    'motions'=>[
        'censure-one'=>[
            'filed_at'=>$file,'earliest_vote_at'=>$earliest,
            'status'=>'filed'
        ]
    ],'spontaneous_motions'=>[]
];
$calendar->assertClockMayAdvance(
    $constitutional,
    FrenchConstitutionalCrisis::instant('2026-11-26T23:59:59+01:00')
);
wp26(true,'progress through day before scheduled 49.3 censure is allowed');
wp26Reject(fn()=>$calendar->assertClockMayAdvance(
    $constitutional,
    FrenchConstitutionalCrisis::instant('2026-11-30T23:59:59+01:00')
),'world clock cannot skip a pending scheduled motion');
wp26Reject(fn()=>$calendar->assertVoteAllowed(
    $constitutional,'censure-one',
    FrenchConstitutionalCrisis::instant('2026-11-27T13:00:00+01:00')
),'parliamentary vote must occur at approved slot, not any hour');
$calendar->assertVoteAllowed(
    $constitutional,'censure-one',
    FrenchConstitutionalCrisis::instant('2026-11-27T15:00:00+01:00')
);
wp26(true,'authorized 49.3 censure vote occurs at the exact approved sitting time');
$complete=$constitutional;
$complete['motions']['censure-one']['status']='rejected';
$calendar->assertClockMayAdvance(
    $complete,
    FrenchConstitutionalCrisis::instant('2026-12-07T23:59:59+01:00')
);
wp26(true,'later dates are permitted once legally verified censure vote has resolved');
$unscheduled=$constitutional;
$unscheduled['motions']['second-motion']=[
    'filed_at'=>$file,'earliest_vote_at'=>$earliest,'status'=>'filed'
];
wp26Reject(fn()=>$calendar->assertClockMayAdvance(
    $unscheduled,
    FrenchConstitutionalCrisis::instant('2026-11-26T11:00:00+01:00')
),'a motion without approved agenda cannot be skipped after its 48-hour period');
wp26Reject(fn()=>$calendar->assertClockMayAdvance(
    $constitutional+['world_id'=>'other-world'],
    FrenchConstitutionalCrisis::instant('2026-11-27T12:00:00+01:00')
),'world identity is checked');
$foreign=$constitutional;$foreign['world_id']='another-world';
wp26Reject(fn()=>$calendar->assertClockMayAdvance(
    $foreign,FrenchConstitutionalCrisis::instant('2026-11-27T12:00:00+01:00')
),'different scenario cannot borrow parliamentary calendar');
$copy=FrenchSittingCalendar::restore($calendar->snapshot());
wp26($copy->hash()===$calendar->hash(),'sittings and certified motion slots reconstruct with same canonical checksum');
$corrupt=$calendar->snapshot();
$corrupt['motions']['censure-one']['vote_day']='2026-12-02';
wp26Reject(fn()=>FrenchSittingCalendar::restore($corrupt),
    'tampered parliamentary calendar schedule is rejected');
wp26Reject(fn()=>new FrenchSittingCalendar(
    'fr-sitting-world',['2026-11-24','2026-11-24','2026-11-25'],
    'fictional_scenario','https://www.assemblee-nationale.fr/'
),'duplicate sitting dates are rejected');
echo "WP26 verified sitting days, latest deadline, and no-skip guard passed.\n";
