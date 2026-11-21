<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Register only application-approved rule artifacts and immutable handlers. */
final class RuleRegistry
{
    /** @var array<string,RuleSet> */
    private array $rules = [];

    public function register(RuleSet $rules): void
    {
        if (isset($this->rules[$rules->key])) throw new \DomainException('Duplicate rules identity');
        $this->rules[$rules->key] = $rules;
    }

    public function get(string $key): RuleSet
    {
        return $this->rules[$key] ?? throw new \DomainException('Unknown rules identity');
    }
}
