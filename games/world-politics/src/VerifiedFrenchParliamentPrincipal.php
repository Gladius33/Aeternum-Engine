<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PdoFrenchParliamentStore.php';

/**
 * WP28: verifiable parliamentary officer bound to the individual roll-call
 * archive, not to an invented vote count in player-controlled JSON.
 *
 * The delegate remains the only authority for Council of Ministers minutes,
 * office assignments and constitutional clock. This adapter does not grant
 * additional legal powers.
 */
final class VerifiedFrenchParliamentPrincipal implements FrenchBallotProofPrincipal
{
    public function __construct(
        private readonly FrenchParliamentaryPrincipal $delegate,
        private readonly PdoFrenchParliamentStore $parliament
    ) {
        if ($this->delegate->actorId()!==$this->parliament->principalId()) {
            throw new \DomainException('Parliamentary trusted principal identity mismatch');
        }
    }
    public function actorId():string{return $this->delegate->actorId();}
    public function allows(string $world,string $ability):bool {
        return $this->delegate->allows($world,$ability);
    }
    public function councilDeliberated(string $world,string $billId):bool {
        return $this->delegate->councilDeliberated($world,$billId);
    }
    public function verifiedBillCategory(string $world,string $billId):string {
        return $this->delegate->verifiedBillCategory($world,$billId);
    }
    public function verifiedInstant(string $world,string $operation):string {
        return $this->delegate->verifiedInstant($world,$operation);
    }
    public function verifiedSessionKind(string $world,string $session):string {
        return $this->delegate->verifiedSessionKind($world,$session);
    }

    public function verifiedSignatures(string $world,string $motionId):int {
        return count($this->parliament->certifiedSignatories($world,$motionId));
    }
    public function verifiedDeputySignatories(string $world,string $motionId):array {
        return $this->parliament->certifiedSignatories($world,$motionId);
    }
    public function verifiedConfidenceBallot(string $world,string $statementId):array {
        $report=$this->parliament->certifiedResult($world,$statementId,'confidence');
        return [
            'for'=>$report['tally']['for'],
            'against'=>$report['tally']['against'],
            'abstain'=>$report['tally']['abstain']
        ];
    }
    public function verifiedVotesFor(string $world,string $motionId):int {
        $report=$this->parliament->certifiedResult($world,$motionId);
        if (!in_array($report['kind'],['censure_49_2','censure_49_3'],true)) {
            throw new \DomainException('Only certified parliamentary censure yes votes count');
        }
        return $report['tally']['for'];
    }
    public function verifiedBallotSourceSha(
        string $world,string $matterId,string $kind
    ):string {
        $result=$this->parliament->certifiedResult($world,$matterId,$kind);
        return $result['source_constitution_sha'];
    }
}
