<?php
declare(strict_types=1);

require __DIR__ . '/../games/world-politics/src/PoliticalRoster.php';
require __DIR__ . '/../games/world-politics/src/WorldPoliticsApi.php';

use Aeternum\WorldPolitics\{PoliticalRoster, PoliticsAuthorizer, WorldPoliticsApi};

function wpApiCheck(bool $ok, string $name): void
{
    if (!$ok) {
        throw new RuntimeException($name);
    }
    echo "PASS WP01 API: $name\n";
}
$makeRoster = fn(string $id): PoliticalRoster => new PoliticalRoster($id, 'FR', ['prime_minister','economy']);
$roster = $makeRoster('world-a');
$other = $makeRoster('world-b');
$grants = ['world-a' => ['read','create_fictional_person','initialize_scenario','nominate','resolve_nomination']];
$identity = new class($grants) implements PoliticsAuthorizer {
    public function __construct(public array $grants) {}
    public function actorId(): string { return 'test-actor'; }
    public function allows(string $world, string $country, string $ability): bool
    {
        return $country === 'FR' && in_array($ability, $this->grants[$world] ?? [], true);
    }
};
$api = new WorldPoliticsApi([$roster, $other], $identity);
$base = '/api/v1/worlds/world-a/countries/FR/politics';
$read = $api->handle('GET', $base);
wpApiCheck($read['status'] === 200 && $read['body']['people'] === [], 'roster GET and empty snapshot');
wpApiCheck($api->handle('GET', '/api/v1/worlds/world-b/countries/FR/politics')['status'] === 404, 'world read without membership is concealed');
wpApiCheck($api->handle('GET', '/api/v1/worlds/world-a/countries/DE/politics')['status'] === 404, 'cross-country cannot access another roster');
wpApiCheck($api->handle('DELETE', $base)['status'] === 405, 'unsupported HTTP verb denied');

$person = [
    'id'=>'fictional-president', 'kind'=>'fictional', 'name'=>'Personnage fictif',
    'country'=>'FR', 'biography'=>'Biographie inventée.', 'as_of'=>null, 'sources'=>[],
    'programme'=>[['id'=>'education', 'domain'=>'education', 'proposal'=>'Une mesure fictive.', 'sources'=>[]]],
];
$write = fn(string $path,array $body,string $key,string $etag) => $api->handle(
    'POST', $path, $body, ['Idempotency-Key'=>$key, 'If-Match'=>$etag]
);
wpApiCheck($api->handle('POST',$base.'/people',$person)['status'] === 400, 'write requires idempotency key');
wpApiCheck($api->handle('POST',$base.'/people',$person,['Idempotency-Key'=>'create-person-100'])['status'] === 412, 'write requires current ETag');
wpApiCheck($write($base.'/people',$person,'create-person-100','"incorrect"')['status'] === 412, 'stale ETag denied');
$created = $write($base.'/people',$person,'create-person-100',$read['headers']['ETag']);
wpApiCheck($created['status'] === 201, 'fictional person created by authorized call');
wpApiCheck($api->handle('GET',$base.'/people/fictional-president')['body']['person']['kind'] === 'fictional', 'person read endpoint');
wpApiCheck($write($base.'/people',$person,'create-person-100','"incorrect"') === $created, 'same-key retry returns original receipt');
$changed = $person;
$changed['name'] = 'Identité différente';
wpApiCheck($write($base.'/people',$changed,'create-person-100',$created['headers']['ETag'])['status'] === 409, 'changed-body idempotency conflict');
$real = $person; $real['kind'] = 'real'; $real['as_of'] = '2026-11-24'; $real['sources'] = ['https://example.org'];
wpApiCheck($write($base.'/people',$real,'create-person-200',$created['headers']['ETag'])['status'] === 422, 'public creation of real personas denied');
wpApiCheck($api->handle('GET',$base.'/people/unknown')['status'] === 404, 'unknown character');
$head = $write($base.'/head-of-state',['person_id'=>'fictional-president'],'create-scenario-100',$created['headers']['ETag']);
wpApiCheck($head['status'] === 200 && $head['body']['head_of_state'] === 'fictional-president', 'alternative presidency created');
$nomination = $write($base.'/nominations',['office'=>'economy','person_id'=>'fictional-president'],'nomination-100',$head['headers']['ETag']);
wpApiCheck($nomination['status'] === 202 && $roster->snapshot()['cabinet'] === [], 'nomination only proposed');
$resolution = $write($base.'/nominations/economy/resolution',['accepted'=>true],'resolution-100',$nomination['headers']['ETag']);
wpApiCheck($resolution['status'] === 200 && $roster->snapshot()['cabinet']['economy'] === 'fictional-president', 'nomination accepted');
wpApiCheck($write($base.'/nominations/economy/resolution',['accepted'=>true],'resolution-100','"stale"') === $resolution, 'accepted nomination retry is inert');
wpApiCheck($other->snapshot()['people'] === [] && $other->snapshot()['cabinet'] === [], 'another world remains untouched');
$identity->grants['world-a'] = ['read'];
wpApiCheck($write($base.'/nominations',['office'=>'prime_minister','person_id'=>'fictional-president'],'nomination-200',$resolution['headers']['ETag'])['status'] === 403, 'revoked write grant cannot submit command');
wpApiCheck($api->handle('GET',$base)['status'] === 200, 'read-only grant still permits public-authorized projection');
echo "World Politics backend API contract passed.\n";
