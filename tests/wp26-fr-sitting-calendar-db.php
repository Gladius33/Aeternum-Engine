<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/PdoFrenchSittingCalendarStore.php';
use Aeternum\WorldPolitics\{
    FrenchSittingAuthority,FrenchConstitutionalCrisis,FrenchSittingCalendar,
    PdoFrenchConstitutionalStore,PdoFrenchSittingCalendarStore
};
function wp26db(bool $ok,string $label):void
{
    if (!$ok) throw new RuntimeException('FAILED WP26 database: '.$label);
    echo "PASS WP26 database: ".$label."\n";
}
function wp26dbReject(callable $fn,string $label):void
{
    try {$fn();}
    catch (\DomainException|\InvalidArgumentException|\UnexpectedValueException $error) {
        wp26db(true,$label);return;
    }
    throw new RuntimeException('Expected refusal: '.$label);
}
if (getenv('WP_TEST_ISOLATED')!=='YES'
    || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"Isolated wp_test MariaDB required\n");exit(2);
}
$dsn=getenv('WP_TEST_DSN');$user=getenv('WP_TEST_USER');
$password=getenv('WP_TEST_PASSWORD');
$connect=static fn()=>new PDO($dsn,$user,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo=$connect();
foreach ([
    'wp_fr_sitting_journal','wp_fr_sitting_calendars',
    'wp_fr_constitutional_journal','wp_fr_constitutional_receipts',
    'wp_fr_constitutional_states'
] as $table) $pdo->exec('DROP TABLE IF EXISTS '.$table);
foreach([
    '009_fr_constitutional_49_3.sql','012_fr_sitting_calendar.sql'
] as $file) {
    $sql=preg_replace('/^\s*--[^\n]*$/m','',
        file_get_contents(__DIR__.'/../games/world-politics/database/'.$file)
    );
    foreach (explode(';',$sql) as $query) {
        if (trim($query)!=='') $pdo->exec(trim($query));
    }
}
$world='fr-sitting-wp26';
$authority=new class implements FrenchSittingAuthority {
    public string $now='2026-11-24T09:00:00+01:00';
    public bool $valid=true;
    public string $approvedVote='2026-11-27T15:00:00+01:00';
    public int $votes=289;
    public function actorId():string{return 'trusted-france-assembly-scheduler';}
    public function allows(string $world,string $ability):bool {
        return $world==='fr-sitting-wp26' && $this->valid
            && in_array($ability,[
                'fr.constitution.read','fr.49_3.engage','fr.censure.file',
                'fr.censure.vote','fr.constitution.clock',
                'fr.calendar.bootstrap','fr.calendar.read','fr.calendar.schedule'
            ],true);
    }
    public function verifiedInstant(string $world,string $operation):string {
        return $this->now;
    }
    public function councilDeliberated(string $world,string $bill):bool {
        return true;
    }
    public function verifiedBillCategory(string $world,string $bill):string {
        return 'finance';
    }
    public function verifiedSignatures(string $world,string $motion):int {
        return 58;
    }
    public function verifiedVotesFor(string $world,string $motion):int {
        return $this->votes;
    }
    public function verifiedCensureVoteAt(string $world,string $motion):string {
        return $this->approvedVote;
    }
};
$calendarStore=new PdoFrenchSittingCalendarStore($connect(),$authority);
$calendarStore->seed(new FrenchSittingCalendar($world,[
    '2026-11-24','2026-11-25','2026-11-26','2026-11-27',
    '2026-11-30','2026-12-01','2026-12-02','2026-12-03','2026-12-04'
], 'fictional_scenario','https://www.assemblee-nationale.fr/'));
$frStore=new PdoFrenchConstitutionalStore(
    $connect(),$authority,$calendarStore
);
$frStore->seed(new FrenchConstitutionalCrisis(
    $world,'regular-2026',
    FrenchConstitutionalCrisis::instant($authority->now),577
));
wp26db((int)$pdo->query(
    "SELECT sitting_calendar_required FROM wp_fr_constitutional_states"
)->fetchColumn()===1,
    'a French constitutional world pins requirement for a reviewed sitting calendar');
wp26dbReject(fn()=>(new PdoFrenchConstitutionalStore(
    $connect(),$authority
))->read($world),
    'restarting without required French sitting calendar fails closed');
$initial=$frStore->read($world);
$engage=$frStore->execute($world,'engage',[
    'bill_id'=>'budget-a','category'=>'finance','at'=>$authority->now
], 'budget-engagement-0001',$initial['etag']);
wp26db($engage['status']===202,'certified France finance 49.3 applied');
$authority->now='2026-11-24T10:00:00+01:00';
$file=$frStore->execute($world,'file_motion',[
    'motion_id'=>'motion-budget','at'=>$authority->now
],'motion-budget-filed-0001',$engage['etag']);
wp26db($file['status']===202,'verified deputy signatures filed during 24-hour period');
$authority->now='2026-11-26T10:00:00+01:00';
$before=$frStore->read($world);
wp26dbReject(fn()=>$frStore->execute($world,'clock',[
    'at'=>$authority->now
],'unscheduled-world-tick',$before['etag']),
    'globally advancing an unscheduled pending censure past 48 hours is forbidden');
$slot=$calendarStore->schedule($world,'motion-budget',$frStore);
wp26db($slot['vote_day']==='2026-11-27'
    && $slot['third_following_sitting_day']==='2026-12-01',
    'conference-approved motion vote is within third sitting day');
wp26db((int)$pdo->query(
    'SELECT COUNT(*) FROM wp_fr_sitting_journal'
)->fetchColumn()===1,'conference agenda is written atomically to its own audit journal');
wp26db($calendarStore->schedule($world,'motion-budget',$frStore)===$slot,
    'conference scheduling is idempotent');
$authority->approvedVote='2026-11-30T15:00:00+01:00';
wp26dbReject(fn()=>$calendarStore->schedule($world,'motion-budget',$frStore),
    'conference cannot quietly reschedule an already approved motion slot');
$authority->now='2026-11-27T14:00:00+01:00';
wp26dbReject(fn()=>$frStore->execute($world,'vote_motion',[
    'motion_id'=>'motion-budget','at'=>$authority->now
],'unscheduled-vote-at-1400',$before['etag']),
    'valid 289 yes votes do not bypass the conference-approved session time');
$authority->now='2026-11-27T16:00:00+01:00';
wp26dbReject(fn()=>$frStore->execute($world,'clock',[
    'at'=>$authority->now
],'skip-scheduled-vote',$before['etag']),
    'world cannot advance beyond a scheduled vote without a verified ballot');
$authority->now='2026-11-27T15:00:00+01:00';
$passed=$frStore->execute($world,'vote_motion',[
    'motion_id'=>'motion-budget','at'=>$authority->now
],'verified-censure-vote-0001',$before['etag']);
wp26db($passed['status']===200
    && $passed['result']['government']==='resignation_required'
    && $passed['result']['regime_changed']===false,
    '289 certified votes trigger Article 50 resignation only at approved session');
$authority->now='2026-11-30T23:59:59+01:00';
$post=$frStore->execute($world,'clock',[
    'at'=>$authority->now
],'post-censure-day-close',$passed['etag']);
wp26db($post['status']===200
    && $post['result']['government']==='resignation_required',
    'resolved parliamentary censure allows subsequent world-day advancement');
wp26db((int)$pdo->query(
    "SELECT COUNT(*) FROM wp_fr_constitutional_journal WHERE operation='vote_motion'"
)->fetchColumn()===1,
    'exactly one permanent vote journal event after retries and denied attempts');
$restartedCalendar=new PdoFrenchSittingCalendarStore($connect(),$authority);
$restartedConstitution=new PdoFrenchConstitutionalStore(
    $connect(),$authority,$restartedCalendar
);
wp26db($restartedConstitution->read($world)['state']['government']==='resignation_required',
    'a fresh server connection resumes both pinned calendar and constitutional state');
$authority->valid=false;
wp26dbReject(fn()=>$restartedConstitution->read($world),
    'revoked Assembly scheduler cannot access even previously recorded vote');
$authority->valid=true;
$pdo->exec(
    "UPDATE wp_fr_sitting_calendars SET state_sha=REPEAT('0',64)
     WHERE world_id='fr-sitting-wp26'"
);
wp26dbReject(fn()=>$restartedConstitution->read($world),
    'corrupt calendar checksum prevents an apparently healthy constitutional state being served');
echo "WP26 French sitting dates and persistent censure enforcement passed.\n";
