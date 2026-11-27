<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/InternationalCommitments.php';
require_once __DIR__.'/CommitmentRisk.php';

/** Cross-domain disclosure: historical amounts, simulated obligations, and
 * actual outstanding exposures are distinct accounting concepts. */
final class InternationalFinanceDisclosure
{
    public const VERSION='wp69-international-finance-disclosure-v1';
    public static function report(array $domestic,array $diplomatic):array
    {
        $d=$domestic['state'];$p=$diplomatic['state'];
        if($d['world_id']!==$p['world_id']||$d['day']!==$p['day']||!isset($d['macro_baseline']['aid_reference_v2'],$p['commitments']))
            throw new \DomainException('Incoherent international finance sources');
        $ref=$d['macro_baseline']['aid_reference_v2'];$pack=$ref['source_pack'];$o=$pack['observed'];$c=$o['civil_guide'];
        if(!hash_equals($ref['source_sha'],hash('sha256',json_encode($pack,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES))))
            throw new \DomainException('Aid reference digest mismatch');
        $ledger=InternationalCommitments::restore($p['commitments'])->snapshot();
        if($ledger['world_id']!==$d['world_id']||$ledger['day']!==$d['day'])throw new \DomainException('Commitment source mismatch');
        $observations=[];
        foreach([
            ['direct_budget_aid','grant_or_budget_aid',$c['direct_budget_aid'],'civil'],
            ['civil_fund_grants','grant',$c['civil_fund_grants'],'civil'],
            ['concessional_treasury_loans','reported_loan_amount',$c['concessional_treasury_loans'],'civil'],
            ['afd_2024_2027_loan_ceiling','loan_ceiling_not_disbursement',$c['afd_2024_2027_loan_ceiling'],'civil'],
            ['multilateral_guarantees','reported_guarantee_amount',$c['multilateral_guarantees'],'civil'],
            ['bpifrance_export_guarantees','reported_guarantee_amount',$c['bpifrance_export_guarantees'],'civil'],
            ['eu_support_loan_2026_2027','eu_wide_programme_not_france_share',$o['eu_support_loan_2026_2027'],'eu'],
            ['eu_ukraine_facility_2024_2027','eu_wide_programme_not_france_share',$o['eu_ukraine_facility_2024_2027'],'civil']
        ] as [$id,$kind,$million,$source]){
            if(!is_int($million)||$million<0||$million>1000000)throw new \DomainException('Invalid historical reference amount');
            $observations[$id]=['kind'=>$kind,'reported_minor'=>$million*100000000,'source'=>$pack['sources'][$source],
                'outstanding_at_investiture_minor'=>null,'future_payment_schedule'=>null,'additive_to_other_rows'=>false];
        }
        // Only fictional outstanding obligations, never historical amounts.
        // Maturity buckets are mutually exclusive, exact minor units.
        $buckets=['due_1_30_days'=>0,'due_31_90_days'=>0,'due_91_365_days'=>0,'due_after_365_days'=>0];
        $today=new \DateTimeImmutable($ledger['day']);
        foreach($ledger['records'] as $record)foreach($record['installments'] as $item){
            if($item['status']!=='future')continue;
            $days=(int)$today->diff(new \DateTimeImmutable($item['due']))->format('%r%a');
            if($days<1)throw new \DomainException('Future obligation overdue');
            $key=match(true){$days<=30=>'due_1_30_days',$days<=90=>'due_31_90_days',$days<=365=>'due_91_365_days',default=>'due_after_365_days'};
            $buckets[$key]+=$item['amount_minor'];
        }
        if(array_sum($buckets)!==$ledger['totals']['future_minor'])throw new \DomainException('Maturity reconciliation failed');
        $last=$d['days']===[]?null:$d['days'][array_key_last($d['days'])];
        $hashes=[];
        foreach(['domestic'=>$domestic,'diplomatic'=>$diplomatic] as $k=>$r){
            $h=trim($r['etag'],'"');if(!preg_match('/^[a-f0-9]{64}$/D',$h))throw new \DomainException('Missing verified source digest');
            $hashes[$k]=$h;
        }
        return ['version'=>self::VERSION,'world_id'=>$d['world_id'],'day'=>$d['day'],'currency'=>'EUR',
            'reference_sha'=>$ref['source_sha'],'source_hashes'=>$hashes,'historical_observations'=>$observations,
            'actual_receivables_minor'=>null,'actual_contingent_liabilities_minor'=>null,'actual_future_payables_minor'=>null,
            'actual_inventory_complete'=>false,'historical_rows_additive'=>false,
            'fictional_commitment_totals'=>$ledger['totals'],'fictional_future_maturity_minor'=>$buckets,
            'fictional_total_outstanding_minor'=>$ledger['totals']['future_minor']+$ledger['totals']['arrears_minor'],'fictional_risk'=>isset($p['commitment_risk'])?CommitmentRisk::report($ledger):null,
            'aid_daily_budget_proxy'=>$last['ukraine_aid']??null,'additional_budget_delta_minor'=>null,'additional_treasury_booking'=>false];
    }
}
