<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

/**
 * WP01: isolated, deterministic national roster for scenario preparation.
 *
 * This is a pure domain prototype: NO HTTP admission, database persistence,
 * constitutional appointment authority or simulated AI consent is implemented.
 * Nothing here may be invoked as a public command without Aeternum actor/world
 * authorization and transaction/journal integration.
 */
final class PoliticalRoster
{
    /** @var array<string,array<string,mixed>> */
    private array $people = [];
    /** @var array<string,string> */
    private array $cabinet = [];
    /** @var array<string,string> */
    private array $pending = [];
    private ?string $headOfState = null;
    /** @var list<string> */
    private array $offices;

    /** @param list<string> $offices Approved scenario-specific office identifiers */
    public function __construct(
        public readonly string $worldId,
        public readonly string $countryCode,
        array $offices
    ) {
        self::assertIdentifier($worldId);
        if (!preg_match('/^[A-Z]{2}$/D', $countryCode)) {
            throw new \InvalidArgumentException('Invalid ISO country code');
        }
        if (!array_is_list($offices) || $offices === [] || count($offices) > 64) {
            throw new \InvalidArgumentException('Invalid office roster');
        }
        foreach ($offices as $office) {
            self::assertIdentifier($office);
        }
        if (count(array_unique($offices)) !== count($offices)) {
            throw new \InvalidArgumentException('Duplicate office');
        }
        $this->offices = $offices;
        sort($this->offices, SORT_STRING);
    }

    /**
     * Register a dated real-world record or an explicitly fictional character.
     * Sources are HTTPS links; their truth/rights must be independently curated.
     *
     * @param array<string,mixed> $person
     */
    public function addPerson(array $person): void
    {
        self::assertKeys($person, ['id', 'kind', 'name', 'country', 'biography', 'as_of', 'sources', 'programme']);
        self::assertIdentifier($person['id']);
        if (isset($this->people[$person['id']])) {
            throw new \DomainException('Person already registered');
        }
        if (!in_array($person['kind'], ['real', 'fictional'], true)) {
            throw new \InvalidArgumentException('Unknown person provenance');
        }
        if ($person['country'] !== $this->countryCode) {
            throw new \InvalidArgumentException('Person belongs to another national roster');
        }
        self::assertText($person['name'], 160);
        self::assertText($person['biography'], 2000, true);

        $real = $person['kind'] === 'real';
        if ($real) {
            self::assertDay($person['as_of']);
        } elseif ($person['as_of'] !== null) {
            throw new \InvalidArgumentException('Fictional biography cannot claim an observation date');
        }
        self::assertSources($person['sources'], $real);

        if (!is_array($person['programme']) || !array_is_list($person['programme']) || count($person['programme']) > 100) {
            throw new \InvalidArgumentException('Invalid programme list');
        }
        $measureIds = [];
        foreach ($person['programme'] as $measure) {
            if (!is_array($measure)) {
                throw new \InvalidArgumentException('Invalid programme measure');
            }
            self::assertKeys($measure, ['id', 'domain', 'proposal', 'sources']);
            self::assertIdentifier($measure['id']);
            self::assertIdentifier($measure['domain']);
            self::assertText($measure['proposal'], 1000);
            self::assertSources($measure['sources'], $real);
            if (isset($measureIds[$measure['id']])) {
                throw new \InvalidArgumentException('Duplicate programme measure');
            }
            $measureIds[$measure['id']] = true;
        }
        // Store only validated, data-only fields; no active code or object references.
        $this->people[$person['id']] = $person;
    }

    /** Scenario initialization only: does not assert a real-world election result. */
    public function setHeadOfState(string $personId): bool
    {
        $this->requirePerson($personId);
        if ($this->headOfState === $personId) {
            return false;
        }
        if ($this->headOfState !== null) {
            throw new \DomainException('Scenario head of state is already initialized');
        }
        $this->headOfState = $personId;
        return true;
    }

    /** A proposal never automatically appoints a person, real or fictional. */
    public function nominate(string $office, string $personId): bool
    {
        if ($this->headOfState === null) {
            throw new \DomainException('Scenario has no head of state');
        }
        if (!in_array($office, $this->offices, true)) {
            throw new \InvalidArgumentException('Unrecognized office');
        }
        $this->requirePerson($personId);
        if (($this->cabinet[$office] ?? null) === $personId
            || ($this->pending[$office] ?? null) === $personId) {
            return false;
        }
        if (isset($this->pending[$office])) {
            throw new \DomainException('Office already has an unresolved nomination');
        }
        $this->pending[$office] = $personId;
        return true;
    }

    /** Acceptance is a simulation input, NOT a prediction of a real person's decision. */
    public function resolveNomination(string $office, bool $accepted): void
    {
        if (!isset($this->pending[$office])) {
            throw new \DomainException('No nomination pending for office');
        }
        if ($accepted) {
            $this->cabinet[$office] = $this->pending[$office];
        }
        unset($this->pending[$office]);
    }

    /** @return array<string,mixed> Stable data-only projection for future journaling. */
    public function snapshot(): array
    {
        $people = $this->people;
        $cabinet = $this->cabinet;
        $pending = $this->pending;
        ksort($people, SORT_STRING);
        ksort($cabinet, SORT_STRING);
        ksort($pending, SORT_STRING);
        return [
            'world_id' => $this->worldId,
            'country_code' => $this->countryCode,
            'offices' => $this->offices,
            'head_of_state' => $this->headOfState,
            'people' => $people,
            'cabinet' => $cabinet,
            'pending_nominations' => $pending,
        ];
    }

    public function snapshotHash(): string
    {
        return hash('sha256', json_encode($this->snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function requirePerson(string $id): void
    {
        self::assertIdentifier($id);
        if (!isset($this->people[$id])) {
            throw new \DomainException('Person not registered in this world and country');
        }
    }

    private static function assertIdentifier(mixed $id): void
    {
        if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $id)) {
            throw new \InvalidArgumentException('Invalid identifier');
        }
    }

    private static function assertText(mixed $value, int $maxBytes, bool $emptyAllowed = false): void
    {
        if (!is_string($value) || strlen($value) > $maxBytes
            || (!$emptyAllowed && trim($value) === '')
            || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new \InvalidArgumentException('Invalid text');
        }
    }

    private static function assertDay(mixed $day): void
    {
        if (!is_string($day) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day)
            || ($date = \DateTimeImmutable::createFromFormat('!Y-m-d', $day)) === false
            || $date->format('Y-m-d') !== $day) {
            throw new \InvalidArgumentException('Invalid observation date');
        }
    }

    /** @param mixed $links */
    private static function assertSources(mixed $links, bool $required): void
    {
        if (!is_array($links) || !array_is_list($links) || count($links) > 20
            || ($required && $links === []) || (!$required && $links !== [])) {
            throw new \InvalidArgumentException('Invalid provenance sources');
        }
        foreach ($links as $link) {
            if (!is_string($link) || strlen($link) > 500
                || !str_starts_with($link, 'https://')
                || filter_var($link, FILTER_VALIDATE_URL) === false) {
                throw new \InvalidArgumentException('Invalid HTTPS source URL');
            }
        }
        if (count(array_unique($links)) !== count($links)) {
            throw new \InvalidArgumentException('Duplicate provenance source');
        }
    }

    /** @param array<string,mixed> $actual
     *  @param list<string> $expected
     */
    private static function assertKeys(array $actual, array $expected): void
    {
        $keys = array_keys($actual);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($keys !== $expected) {
            throw new \InvalidArgumentException('Unexpected profile or measure fields');
        }
    }
}
