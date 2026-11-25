<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/PdoInstitutionalStore.php';

/** Explicitly trusted local scenario clerk. Never instantiate from HTTP input.
 * An operator is responsible for certifying fictional procedural facts.
 */
final class LocalRevisionClerk implements RevisionInstitutionalAuthorizer
{
    public function __construct(private readonly array $evidence){}
    public function actorId():string{return 'local-revision-clerk';}
    public function allows(string $world,string $country,string $ability):bool
    {
        return $world===($this->evidence['world_id']??null)&&$country==='FR'
            &&in_array($ability,['read_institutions','revision_evidence'],true);
    }
    public function roleFor(string $world,string $country,string $action):string
    {
        if($action!=='revision_evidence'||!$this->allows($world,$country,$action))throw new \DomainException('Clerk scope denied');
        return 'revision_clerk';
    }
    public function verifiedRevisionEvidence(string $world,string $country,string $reference):array
    {
        if(!$this->allows($world,$country,'revision_evidence')||$reference!==($this->evidence['evidence_id']??null))
            throw new \DomainException('Evidence scope denied');
        return $this->evidence;
    }
}
