<?php
declare(strict_types=1);

require __DIR__ . '/../games/world-politics/src/PoliticalRoster.php';
use Aeternum\WorldPolitics\PoliticalRoster;

function wpCheck(bool $condition, string $name): void
{
    if (!$condition) throw new RuntimeException($name);
    echo "PASS WP01: $name\n";
}
function wpReject(callable $fn, string $exception, string $name): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        wpCheck($e instanceof $exception, $name);
        return;
    }
    throw new RuntimeException("Expected rejection: $name");
}
$fictional = [
    'id' => 'president-custom',
    'kind' => 'fictional',
    'name' => 'Camille Moreau',
    'country' => 'FR',
    'biography' => 'Personnage fictif créé par le joueur.',
    'as_of' => null,
    'sources' => [],
    'programme' => [[
        'id' => 'education-budget',
        'domain' => 'education',
        'proposal' => 'Augmenter les crédits éducatifs de 5 %.',
        'sources' => [],
    ]],
];
$realFixture = [
    'id' => 'sourced-record',
    'kind' => 'real',
    'name' => 'Fiche de test avec sources',
    'country' => 'FR',
    'biography' => 'Enregistrement synthétique destiné uniquement aux tests.',
    'as_of' => '2026-11-24',
    'sources' => ['https://example.org/biography'],
    'programme' => [[
        'id' => 'public-proposal',
        'domain' => 'health',
        'proposal' => 'Proposition de test, non attribuée à une personnalité réelle.',
        'sources' => ['https://example.org/programme'],
    ]],
];
$offices = ['prime_minister', 'economy', 'defence'];
$roster = new PoliticalRoster('world-france-a', 'FR', $offices);
$roster->addPerson($fictional);
$roster->addPerson($realFixture);
wpCheck($roster->setHeadOfState('president-custom'), 'fictional president scenario initialized');
wpCheck(!$roster->setHeadOfState('president-custom'), 'duplicate scenario initialization is inert');
wpReject(fn() => $roster->setHeadOfState('sourced-record'), DomainException::class, 'head-of-state cannot be silently replaced');
wpCheck($roster->nominate('prime_minister', 'sourced-record'), 'sourced record can be nominated alongside fictional president');
wpCheck(!$roster->nominate('prime_minister', 'sourced-record'), 'duplicate nomination is inert');
wpCheck($roster->snapshot()['cabinet'] === [], 'proposal is not an appointment');
wpReject(fn() => $roster->nominate('prime_minister', 'president-custom'), DomainException::class, 'conflicting pending nomination rejected');
$roster->resolveNomination('prime_minister', true);
wpCheck($roster->snapshot()['cabinet']['prime_minister'] === 'sourced-record', 'accepted proposal becomes appointment');
wpCheck($roster->nominate('economy', 'president-custom'), 'fictional official may be nominated');
$roster->resolveNomination('economy', false);
wpCheck(!isset($roster->snapshot()['cabinet']['economy']), 'decline preserves unoccupied office');
wpReject(fn() => $roster->resolveNomination('economy', true), DomainException::class, 'nomination cannot be replayed after resolution');
wpReject(fn() => $roster->nominate('unknown_office', 'sourced-record'), InvalidArgumentException::class, 'undeclared national office rejected');
wpReject(fn() => $roster->nominate('defence', 'foreign-id'), DomainException::class, 'foreign unregistered identity rejected');
wpReject(fn() => $roster->addPerson($fictional), DomainException::class, 'duplicate identity rejected');

$other = new PoliticalRoster('world-france-b', 'FR', array_reverse($offices));
$other->addPerson($realFixture);
$other->addPerson($fictional);
$other->setHeadOfState('president-custom');
wpCheck($other->snapshot()['cabinet'] === [], 'different world has independent cabinet');
wpCheck($other->snapshotHash() !== $roster->snapshotHash(), 'world identity and appointments affect canonical hash');

$reverse = new PoliticalRoster('world-france-a', 'FR', array_reverse($offices));
$reverse->addPerson($realFixture);
$reverse->addPerson($fictional);
$reverse->setHeadOfState('president-custom');
$reverse->nominate('prime_minister', 'sourced-record');
$reverse->resolveNomination('prime_minister', true);
$reverse->nominate('economy', 'president-custom');
$reverse->resolveNomination('economy', false);
wpCheck($reverse->snapshotHash() === $roster->snapshotHash(), 'canonical snapshot independent of registration order');

$noSources = $realFixture;
$noSources['sources'] = [];
wpReject(fn() => (new PoliticalRoster('test-world', 'FR', $offices))->addPerson($noSources), InvalidArgumentException::class, 'unsourced real profile rejected');
$inventedSources = $fictional;
$inventedSources['sources'] = ['https://example.org/invented'];
wpReject(fn() => (new PoliticalRoster('test-world', 'FR', $offices))->addPerson($inventedSources), InvalidArgumentException::class, 'fictional profiles cannot impersonate sourced historical records');
$badProgram = $fictional;
$badProgram['programme'][0]['sources'] = ['https://example.org/fabricated'];
wpReject(fn() => (new PoliticalRoster('test-world', 'FR', $offices))->addPerson($badProgram), InvalidArgumentException::class, 'fictional proposal source spoof rejected');
$wrongCountry = $fictional;
$wrongCountry['country'] = 'DE';
wpReject(fn() => (new PoliticalRoster('test-world', 'FR', $offices))->addPerson($wrongCountry), InvalidArgumentException::class, 'cross-country roster injection rejected');
$badDate = $realFixture;
$badDate['as_of'] = '2026-02-30';
wpReject(fn() => (new PoliticalRoster('test-world', 'FR', $offices))->addPerson($badDate), InvalidArgumentException::class, 'invalid observation date rejected');
echo "WP01 political identity prototype passed.\n";
