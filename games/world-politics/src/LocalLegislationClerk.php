<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/PdoInstitutionalStore.php';

/** Explicitly trusted local scenario clerk. Never instantiate from HTTP input.
 * An operator is responsible for certifying fictional procedural facts.
 */
final class LocalLegislationClerk implements LegislativeInstitutionalAuthorizer
{
    public function __construct(private readonly array $evidence){}
    public function actorId():string{return 'local-legislation-clerk';}
    public function allows(string $world,string $country,string $ability):bool
    {
        return $world===($this->evidence['world_id']??null)&&$country==='FR'
            &&in_array($ability,['read_institutions','legislation_evidence'],true);
    }
    public function roleFor(string $world,string $country,string $action):string
    {
        if($action!=='legislation_evidence'||!$this->allows($world,$country,$action))throw new \DomainException('Clerk scope denied');
        return 'legislation_clerk';
    }
    public function verifiedLegislativeEvidence(string $world,string $country,string $reference):array
    {
        if(!$this->allows($world,$country,'legislation_evidence')||$reference!==($this->evidence['evidence_id']??null))
            throw new \DomainException('Evidence scope denied');
        return $this->evidence;
    }
}
