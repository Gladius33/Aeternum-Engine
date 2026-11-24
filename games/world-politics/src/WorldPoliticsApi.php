<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

/**
 * A server-supplied access policy. An implementation MUST derive the actor and
 * grants from validated authentication/world membership, never from JSON.
 * Aeternum's authoritative session/role integration remains future work.
 */
interface PoliticsAuthorizer
{
    public function actorId(): string;
    public function allows(string $world, string $country, string $ability): bool;
}

/**
 * Transport-agnostic, REST-shaped WP01 contract adapter.
 *
 * NOT A DEPLOYABLE HTTP SERVER: state and replay receipts are process-local.
 * Expose only after a transactional MariaDB repository and trusted admission
 * adapter replace the in-memory composition, with atomic durable receipts.
 */
final class WorldPoliticsApi
{
    /** @var array<string,PoliticalRoster> */
    private array $rosters = [];
    /** @var array<string,array{fingerprint:string,response:array}> */
    private array $receipts = [];

    /** @param list<PoliticalRoster> $rosters */
    public function __construct(array $rosters, private readonly PoliticsAuthorizer $auth)
    {
        foreach ($rosters as $roster) {
            if (!$roster instanceof PoliticalRoster) {
                throw new \InvalidArgumentException('Untrusted roster');
            }
            $key = $roster->worldId . '/' . $roster->countryCode;
            if (isset($this->rosters[$key])) {
                throw new \InvalidArgumentException('Duplicate world/country');
            }
            $this->rosters[$key] = $roster;
        }
        if ($this->auth->actorId() === '' || strlen($this->auth->actorId()) > 128) {
            throw new \InvalidArgumentException('Invalid authenticated actor');
        }
    }

    /**
     * HTTP frameworks must supply ONLY a verified server-side principal.
     * Returns {status:int, headers:array<string,string>, body:array<string,mixed>}.
     *
     * @param array<string,mixed>|null $payload
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
     */
    public function handle(string $method, string $path, ?array $payload = null, array $headers = []): array
    {
        if (strlen($path) > 512 || !preg_match(
            '~^/api/v1/worlds/([a-z][a-z0-9_-]{0,63})/countries/([A-Z]{2})/politics(?:/(people|head-of-state|nominations)(?:/([a-z][a-z0-9_-]{0,63}))?(?:/(resolution))?)?$~D',
            $path, $matches
        )) {
            return self::problem(404, 'route_not_found');
        }
        $world = $matches[1];
        $country = $matches[2];
        $resource = $matches[3] ?? '';
        $item = $matches[4] ?? '';
        $action = $matches[5] ?? '';
        $roster = $this->rosters[$world . '/' . $country] ?? null;
        if ($roster === null || !$this->auth->allows($world, $country, 'read')) {
            return self::problem(404, 'not_found');
        }

        if ($method === 'GET') {
            if ($payload !== null) {
                return self::problem(400, 'get_body_forbidden');
            }
            if ($resource === '' && $item === '' && $action === '') {
                return self::ok(200, $roster->snapshot(), $roster->snapshotHash());
            }
            if ($resource === 'people' && $item !== '' && $action === '') {
                $person = $roster->snapshot()['people'][$item] ?? null;
                return $person === null ? self::problem(404, 'not_found')
                    : self::ok(200, ['person' => $person], $roster->snapshotHash());
            }
            return self::problem(405, 'method_not_allowed');
        }

        if ($method !== 'POST') {
            return self::problem(405, 'method_not_allowed');
        }
        $ability = match (true) {
            $resource === 'people' && $item === '' && $action === '' => 'create_fictional_person',
            $resource === 'head-of-state' && $item === '' && $action === '' => 'initialize_scenario',
            $resource === 'nominations' && $item === '' && $action === '' => 'nominate',
            $resource === 'nominations' && $item !== '' && $action === 'resolution' => 'resolve_nomination',
            default => null,
        };
        if ($ability === null) {
            return self::problem(405, 'method_not_allowed');
        }
        if (!$this->auth->allows($world, $country, $ability)) {
            return self::problem(403, 'forbidden');
        }
        if (!is_array($payload) || !array_is_list($payload) && count($payload) > 30) {
            return self::problem(400, 'invalid_json_object');
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($json) > 16000) {
            return self::problem(413, 'payload_too_large');
        }
        $key = $headers['Idempotency-Key'] ?? '';
        if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_-]{12,100}$/D', $key)) {
            return self::problem(400, 'idempotency_key_required');
        }
        // Scope every receipt to the authenticated actor, verb and route.
        $receiptId = hash('sha256', $this->auth->actorId() . "\n" . $method . "\n" . $path . "\n" . $key);
        $fingerprint = hash('sha256', $json);
        if (isset($this->receipts[$receiptId])) {
            $prior = $this->receipts[$receiptId];
            return hash_equals($prior['fingerprint'], $fingerprint)
                ? $prior['response'] : self::problem(409, 'idempotency_conflict');
        }
        $expected = $headers['If-Match'] ?? null;
        if (!is_string($expected) || !hash_equals('"' . $roster->snapshotHash() . '"', $expected)) {
            return self::problem(412, 'stale_or_missing_precondition');
        }
        try {
            if ($ability === 'create_fictional_person') {
                if (($payload['kind'] ?? null) !== 'fictional') {
                    return self::problem(422, 'only_fictional_profiles_can_be_player_created');
                }
                $roster->addPerson($payload);
                $status = 201;
                $result = ['person_id' => $payload['id']];
            } elseif ($ability === 'initialize_scenario') {
                self::exactFields($payload, ['person_id']);
                $roster->setHeadOfState(self::str($payload['person_id']));
                $status = 200;
                $result = ['head_of_state' => $payload['person_id']];
            } elseif ($ability === 'nominate') {
                self::exactFields($payload, ['office', 'person_id']);
                $roster->nominate(self::str($payload['office']), self::str($payload['person_id']));
                $status = 202;
                $result = ['office' => $payload['office'], 'person_id' => $payload['person_id'], 'state' => 'proposed'];
            } else {
                self::exactFields($payload, ['accepted']);
                if (!is_bool($payload['accepted'])) {
                    throw new \InvalidArgumentException('Acceptance must be boolean');
                }
                $roster->resolveNomination($item, $payload['accepted']);
                $status = 200;
                $result = ['office' => $item, 'state' => $payload['accepted'] ? 'accepted' : 'declined'];
            }
        } catch (\InvalidArgumentException $e) {
            return self::problem(422, 'invalid_request');
        } catch (\DomainException $e) {
            return self::problem(409, 'state_conflict');
        }
        $response = self::ok($status, $result, $roster->snapshotHash());
        $this->receipts[$receiptId] = ['fingerprint' => $fingerprint, 'response' => $response];
        return $response;
    }

    /** @param array<string,mixed> $payload @param list<string> $keys */
    private static function exactFields(array $payload, array $keys): void
    {
        $actual = array_keys($payload);
        sort($actual, SORT_STRING);
        sort($keys, SORT_STRING);
        if ($actual !== $keys) {
            throw new \InvalidArgumentException('Unexpected payload fields');
        }
    }

    private static function str(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Expected string');
        }
        return $value;
    }

    /** @param array<string,mixed> $body */
    private static function ok(int $status, array $body, string $hash): array
    {
        return ['status' => $status, 'headers' => [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
            'ETag' => '"' . $hash . '"',
        ], 'body' => $body];
    }

    private static function problem(int $status, string $code): array
    {
        return ['status' => $status, 'headers' => [
            'Content-Type' => 'application/problem+json',
            'Cache-Control' => 'no-store',
        ], 'body' => [
            'type' => 'about:blank',
            'status' => $status,
            'code' => $code,
        ]];
    }
}
