<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Immutable pilot identity catalog. Nothing in this data implies company conduct. */
final class IndustrialProgrammeCatalog
{
    public static function companies():array
    {
        $d=json_decode(file_get_contents(__DIR__.'/../data/2026-11-25-cac40-identities-v1.json'),true,32,JSON_THROW_ON_ERROR);
        if(($d['schema']??null)!=='wp53c-cac40-reference-v1'||count($d['companies']??[])!==40)throw new \DomainException('Invalid company catalog');
        $ids=[];$isins=[];
        foreach($d['companies'] as $r){
            if(!preg_match('/^[a-z][a-z0-9-]{0,40}$/D',$r['id'])||!preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/D',$r['isin'])
                ||isset($ids[$r['id']])||isset($isins[$r['isin']]))throw new \DomainException('Duplicate or invalid company identifier');
            $ids[$r['id']]=true;$isins[$r['isin']]=true;
        }
        return $d;
    }
    public static function hasCompany(string $id):bool
    {foreach(self::companies()['companies'] as $r)if($r['id']===$id)return true;return false;}
    public static function packs(array $state):array
    {
        $initial=$state['mode']==='pluralist';
        return [
            ['id'=>'corporations-parallel','label'=>'Corporations alongside unions','status'=>$initial?'available':'blocked_by_current_mode',
                'action'=>'establish-quarterly','effects'=>['autonomous_branch_votes','quarterly_reports','social_and_employer_reactions']],
            ['id'=>'corporations-exclusive','label'=>'Exclusive corporations (contested rupture)','status'=>$initial?'available':'blocked_by_current_mode',
                'action'=>'establish-exclusive-quarterly','effects'=>['autonomous_branch_votes','quarterly_reports','constitutional_conflict','social_and_employer_reactions']],
            ['id'=>'industrial-recovery-eu','label'=>'Industrial recovery within EU','status'=>'not_implemented','action'=>null,
                'requires'=>['industrial_capital_stock','skills_and_energy_constraints','investment_budget','lawful_state_aid','sectoral_trade_feedback']],
            ['id'=>'industrial-sovereignty-withdrawal','label'=>'Industrial recovery with negotiated EU withdrawal','status'=>'not_implemented','action'=>null,
                'requires'=>['constitutional_authority','article_50_negotiation','trade_regime_effective_date','tariff_ledger','industrial_capital_stock','eu_response']],
            ['id'=>'unilateral-tariffs-rupture','label'=>'Unilateral tariff conflict and withdrawal attempt','status'=>'not_implemented','action'=>null,
                'requires'=>['contested_tariff_authority','eu_infringement_response','article_50_negotiation','trade_retaliation','tariff_ledger']],
            ['id'=>'monetary-transition','label'=>'Separate monetary transition','status'=>'not_implemented','action'=>null,
                'requires'=>['currency_balance_sheets','legal_contract_scope','banking_continuity','fx_and_external_debt']],
        ];
    }
}
