<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
require __DIR__.'/../games/world-politics/src/EuropeanSpillovers.php';
require __DIR__.'/../games/world-politics/src/FinanceBudget.php';
use Aeternum\WorldPolitics\{EuropeanWithdrawal as E,EuropeanSpillovers as S,EuropeanTradeFeedback as T,FrancCommittedBudget as C,DomesticPolicySimulation as D,DomesticPolicyAuthority,FrenchMacroBaseline as B,SovereignDebtLedger as L,FinanceBudget as F,FrancExchangeMarket as X};
function ckCB(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS committed budget: $m\n";}
function noCB(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Invalid committed budget accepted');}
final class CBPrincipal implements DomesticPolicyAuthority{
    public string $day='2026-11-25';
    public function allows(string $world,string $ability):bool{return $world==='world-test';}
    public function enactedInstrument(string $world,string $policy):?string{return null;}
    public function clockDay(string $world):string{return $this->day;}
}
$d='2026-11-25';$start='2026-12-01';$sha=str_repeat('a',64);
$e=E::request(E::initial($d,true,true,true,true,true,true,true,true,true,false,false,true),$d);
$terms=['effective_on'=>$start,'instrument_sha'=>$sha,'reserve_purchase_authorized'=>true,'commit_daily'=>true,'sector_integration'=>true,'financial_integration'=>true];
noCB(fn()=>E::certify($e,$d,'franc_native_budget',$terms));
$e=E::certify($e,$d,'notification',['national_decision_sha'=>$sha,'notification_sha'=>$sha]);
$e=E::certify($e,$d,'agreement',['effective_on'=>$start,'parliament_consent'=>true,'council_qualified_majority'=>true,'france_agrees'=>true,'agreement_sha'=>$sha]);
$e=E::certify($e,$d,'currency_terms',['effective_on'=>$start,'units_per_eur'=>2,'instrument_sha'=>$sha,'contracts'=>[['id'=>'bond','debtor'=>'fr_treasury','creditor'=>'fr_banks','kind'=>'sovereign_bond','principal_eur_minor'=>100,'governing_law'=>'fr','treatment'=>'convert_certified','evidence_sha'=>$sha]]]);
$e=E::certify($e,$d,'franc_rule',['effective_on'=>$start,'opening_base_franc_minor'=>1000000,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_bank_terms',['effective_on'=>$start,'opening_reserve_allocation_franc_minor'=>100000,'minimum_capital_bps'=>1000,'minimum_liquidity_bps'=>2000,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_bank_crisis_terms',['effective_on'=>$start,'facility_pool_franc_minor'=>100000,'guarantee_pool_franc_minor'=>100000,'collateral_haircut_bps'=>2000,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_fx_terms',['effective_on'=>$start,'official_eur_minor'=>100,'counterparty_eur_minor'=>100,'official_franc_pool_minor'=>500,'counterparty_franc_pool_minor'=>500,'minimum_official_eur_minor'=>50,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_fiscal',['effective_on'=>$start,'converted_opening_cohorts'=>['legacy-state-french_credit_institutions'],'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_fx_order',['effective_on'=>$start,'kind'=>'sell_eur','eur_minor'=>20,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_funding',['effective_on'=>'2026-12-02','spending_requested_franc_minor'=>40,'repayment_requested_franc_minor'=>0,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_native_budget',$terms);
noCB(fn()=>E::certify($e,$d,'franc_native_budget',$terms));
$rev=E::certify($e,$d,'revocation',['national_decision_sha'=>$sha,'notice_sha'=>$sha,'unequivocal_unconditional'=>true]);
ckCB(!isset($rev['franc_native_budget_terms'])&&E::restore($rev)===$rev,'revocation clears terms and replay is exact');

$b=B::load();$model=L::load($b,$d,true);
$catalog=['unused'=>['revenue_delta'=>0,'expense_delta'=>0,'pressure_delta'=>['workers'=>0],'duration_days'=>1,'concessions'=>['workers'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>0,'employment_delta_bps'=>0]];
$make=fn()=>new D('world-test',$d,'EUR',$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],['workers'=>400],$catalog,$b,$model,euTradeEnabled:true,euTradeV2:true,euTradeV3:true,euTradeV4:true);
$sim=$make();$a=new CBPrincipal;
$org=fn($s)=>['state'=>['world_id'=>'world-test','day'=>$s['day'],'eu_withdrawal'=>$s],'etag'=>'"'.$sha.'"'];
$dom=fn()=>['state'=>$sim->snapshot(),'etag'=>'"'.$sim->hash().'"'];
$run=function(string $day)use(&$e,$a,$sim,$org,$dom){$e=E::advance($e,$day);$a->day=$day;$obs=T::observe($org($e),'world-test',$day,T::VERSION_V4,$dom());return $sim->advance($a,$day,null,$obs);};
for($i=26;$i<=29;$i++)$run('2026-11-'.$i);
$before=$run('2026-11-30');ckCB(!isset($before['frf_committed_budget']),'no premature committed budget');
$first=$run($start);$n=$first['frf_committed_budget'];
ckCB($n['effective_from']===$start&&$n['closing_state']['accounts']['official_eur_reserves_minor']>=50&&$n['financing']['market_issued_at_parity_eur_minor']<=$n['financing']['market_capacity_at_parity_eur_minor'],'native daily settlement and capped financing');
ckCB($n['risk']['credit_availability_bps']<=10000&&$n['risk']['credit_availability_bps']>=7000,'bounded risk signal');
$e=E::certify($e,$start,'currency_mark',['effective_on'=>'2026-12-02','value_index_bps'=>8000,'instrument_sha'=>$sha]);
$second=$run('2026-12-02');$s=$second['frf_committed_budget'];
ckCB($s['opening_accounts']===$n['closing_state']['accounts']&&$s['opening_principal']===$n['closing_principal'],'committed daily continuity');
ckCB($s['valuation']['fx_revaluation_frf_minor']>=0&&$s['closing_state']['mark']===8000,'market quote affects persisted native liability');
$sim->crisisDecision($a,'moratorium');$third=$run('2026-12-03');
ckCB($third['frf_committed_budget']['paid_principal']===['FRF'=>0,'EUR'=>0]&&$third['frf_committed_budget']['financing']['market_capacity_at_parity_eur_minor']===0,'moratorium suspends native financing and settlement');
$sim->crisisDecision($a,'resume');$fourth=$run('2026-12-04');
$snapshot=$sim->snapshot();$copy=$make();$copy->restore($snapshot,$sim->hash());ckCB($copy->snapshot()===$snapshot,'full daily state replay');
$bad=$snapshot;$bad['days'][5]['frf_committed_budget']['closing_state']['accounts']['treasury_frf_minor']++;$hash=hash('sha256',json_encode($bad,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));noCB(fn()=>$make()->restore($bad,$hash));
$bad=$snapshot;unset($bad['days'][6]['eu_trade_observation']['frf_committed_observation']);$hash=hash('sha256',json_encode($bad,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));noCB(fn()=>$make()->restore($bad,$hash));
$report=C::project($snapshot);$fin=F::project(F::initial(),$snapshot);$debt=L::project($snapshot);
ckCB($report['latest']['day']==='2026-12-04'&&$fin['currency']==='FRF'&&$debt['currency']==='FRF'&&$fin['debt']===$debt['debt']&&$fin['reference_eur_only']['currency']==='EUR','finance and debt expose committed FRF with reference explicitly separated');
$p=S::project($org($e),$dom());ckCB($p['franc_native_budget']===$report&&$p['franc_exchange_reserves_reference_only'],'spillover reads committed native state');
$priorState=['accounts'=>['treasury_frf_minor'=>0,'treasury_eur_minor'=>0],'primary_arrears'=>0,'claims'=>['foreign'=>['currency'=>'EUR','principal_outstanding_minor'=>100,'principal_due_minor'=>100,'interest_due_minor'=>0]]];
$flow=['primary_expense'=>200,'revenue'=>0,'coupons'=>[],'maturities'=>[],'haircuts'=>[]];
$cap=['normal_daily_base_capacity'=>120,'stress_daily_base_capacity'=>120,'normal_refinancing_bps'=>0,'stress_refinancing_bps'=>0];
$q=C::finance($priorState,$flow,$cap,2,false,null);
ckCB($q['market_issued_at_parity_eur_minor']===120&&$q['issues']['new-stress-domestic-FRF']['amount']===200&&$q['issues']['new-stress-external-EUR']['amount']===20,'native needs independently split constrained financing');
$market=['franc_fx_market_terms'=>['effective_on'=>$start,'market_depth_eur_minor'=>100,'daily_move_cap_bps'=>500],'franc_rule'=>['effective_on'=>$start],'franc_fx_flow_terms'=>['import_eur_share_bps'=>0,'export_eur_share_bps'=>0],'franc_fx_orders'=>[],'franc_fx_capital_orders'=>[]];
$rows=[['day'=>$start,'macro'=>['imports'=>0,'exports'=>0,'price_index_scaled'=>100000000],'frf_committed_budget'=>['risk'=>['new_unpaid_eur_demand_proxy_minor'=>50]]]];
$x=X::quotes($market,'2026-12-02',$start,$rows);ckCB($x['value_index_bps']===9750&&$x['changes'][0]['flow_pressure']['new_unpaid_eur_debt_pressure_proxy_minor']===50,'lagged debt FX pressure');
ckCB(X::quotes($market,$start,$start,$rows)['value_index_bps']===10000,'no same-day FX feedback');

use Aeternum\WorldPolitics\FrancNativeSectors as NS;
ckCB($report['sector_version']===NS::VERSION&&isset($report['future_bond_maturities']),'sector version and forward maturity book persisted');
ckCB(isset($n['banking'],$s['banking'])&&$s['banking']['commercial_bank_balance_sheet_booked']===false,'bank-holder valuation kept separate from commercial bank pilot');
ckCB(!$fourth['frf_committed_budget']['sector_settlement']['discretionary_capital_settlement_suspended'],'cash-funded native capital activated');
$claim=['new-normal-domestic-FRF'=>['currency'=>'FRF','principal_outstanding_minor'=>300,'principal_due_minor'=>0,'interest_due_minor'=>0]];
$issues=['new-normal-domestic-FRF'=>['currency'=>'FRF','amount'=>100]];
$cal=NS::schedule([],$claim,'2026-12-01',$issues,10);
ckCB($cal['new_issue_maturity_on']==='2037-01-01'&&$cal['closing']['2037-01-01']['new-normal-domestic-FRF']===100,'ten year maturity bucket');
$cal=NS::schedule($cal['closing'],$claim,'2026-12-31',$issues,10);
ckCB($cal['closing']['2037-01-01']['new-normal-domestic-FRF']===200,'monthly vintages aggregate without duplicating principal');
NS::checkCalendar($cal['closing'],$claim);
$due=NS::schedule($cal['closing'],$claim,'2037-01-01',[],10);
ckCB($due['due']['new-normal-domestic-FRF']===200&&$due['closing']===[],'maturity released once on exact day');
noCB(fn()=>NS::schedule($cal['closing'],$claim,'2037-01-02',[],10));
$bad=$claim;$bad['new-normal-domestic-FRF']['principal_due_minor']=101;noCB(fn()=>NS::checkCalendar($cal['closing'],$bad));
ckCB(NS::maturityDay('2028-02-29',10)==='2038-03-01','leap-day maturity convention');
$alloc=NS::services(['budget'=>['ordinary_primary_paid_frf_minor'=>120]],['frf_committed_budget'=>['closing_state'=>['primary_arrears'=>40]]],100,2);
ckCB($alloc['current_primary_paid_frf_minor']===80&&$alloc['reference_paid_minor']===40,'public services use paid current native flows after overdue liabilities');
ckCB(NS::credit(['treasury'=>['banking'=>['credit_availability_bps'=>9000]],'frf_committed_budget'=>['risk'=>['credit_availability_bps'=>8500],'banking'=>['credit_availability_bps'=>6000]]])===6000,'most binding bank credit signal');

use Aeternum\WorldPolitics\{FrancFinancialIntegration as FI,FrancNativeBudget as NB,FrancNativeBudgetProjection as NP,IndustrialCapital as IC,PublicEquityLedger as PE,FirmEconomy as FE};
$native=$fourth['frf_committed_budget'];
ckCB($native['financial_version']===FI::VERSION&&$native['bank_balance']['balance_difference_frf_minor']===0&&$native['bank_balance']['combined_modelled_slices']['balance_difference_frf_minor']===0,'committed and combined bank balances reconcile');
$dummy=['closing_state'=>['accounts'=>['treasury_frf_minor'=>10000]],'debt_service_current'=>true,'budget'=>['equity_paid_frf_minor'=>0]];
$settled=FI::capital($dummy,2,100,300,null);
ckCB($settled['closing_state']['accounts']['treasury_frf_minor']===9200&&$settled['budget']['industrial_paid_frf_minor']===200&&$settled['budget']['equity_paid_frf_minor']===600,'capital payments reduce native cash exactly once');
$next=FI::capital($dummy,2,10,20,$settled);ckCB($next['capital_settlement']['cumulative_equity_acquired_at_cost_frf_minor']===640,'capital stock persists');
noCB(fn()=>FI::capital(array_replace($dummy,['debt_service_current'=>false]),2,1,0,null));
noCB(fn()=>FI::capital($dummy,2,6000,0,null));
$industrial=IC::advance(IC::initial('2026-11-30'),'2026-12-01','start-industrial-pilot',1000000000,true,null);
$nativeCash=1000000000*2;$cashFixture=$dummy;$cashFixture['closing_state']['accounts']['treasury_frf_minor']=$nativeCash;
$after=FI::capital($cashFixture,2,$industrial['paid_today_minor'],0,null);
ckCB($industrial['paid_today_minor']===100000000&&$after['closing_state']['accounts']['treasury_frf_minor']===$nativeCash-200000000,'industrial programme cash funded');
$fc=FE::config();$chosen=null;foreach($fc as $id=>$v)if($v['resilience']%3!==0){$chosen=$id;break;}
if($chosen===null)throw new RuntimeException('No consenting equity fixture');
$equity=PE::advance(PE::initial('2026-11-30'),'2026-12-01','offer-voluntary-equity:'.$chosen,$fc,1000000000000,true);
$day=new DateTimeImmutable('2026-12-01');for($i=1;$i<=30;$i++)$equity=PE::advance($equity,$day->modify('+'.$i.' days')->format('Y-m-d'),null,$fc,1000000000000,true);
$cashFixture['closing_state']['accounts']['treasury_frf_minor']=2000000000000;
$after=FI::capital($cashFixture,2,0,$equity['paid_today_minor'],null);
ckCB($equity['paid_today_minor']>0&&$after['budget']['equity_paid_frf_minor']===2*$equity['financial_assets_at_cost_minor'],'consenting equity acquisition settles against native cash');
$bankId='legacy-state-french_credit_institutions-EUR';
$c=['currency'=>'EUR','principal_outstanding_minor'=>1000,'principal_due_minor'=>200,'interest_due_minor'=>20];
$st=['accounts'=>['treasury_frf_minor'=>0,'treasury_eur_minor'=>220,'official_eur_reserves_minor'=>0,'official_franc_pool_minor'=>0,'minimum_official_eur_minor'=>0],'claims'=>[$bankId=>$c],'counter_eur'=>0,'counter_frf'=>0,'primary_arrears'=>0,'advance_liability'=>0,'mark'=>10000];
$flow=['revenue'=>0,'primary_expense'=>0,'advance'=>0,'repay'=>0,'closing_advance'=>0,'equity'=>0,'coupons'=>[],'maturities'=>[],'haircuts'=>[],'issues'=>[],'fx_order'=>null];
$q=NB::step($st,$flow,2,10000,false,false);$bk=FI::bank($q,2,10000,null,null);
ckCB($bk['retained_creditor_cash_by_currency_minor']['EUR']===220&&$bk['assets_frf_minor']===2040&&$bk['equity_frf_minor']===440,'bank principal is asset exchange, coupon is income');
$q2=NB::step($q['closing_state'],$flow,2,8000,false,false);$bk2=FI::bank($q2,2,8000,[...$q,'bank_balance'=>$bk],null);
ckCB($bk2['assets_frf_minor']===2550&&$bk2['balance_difference_frf_minor']===0,'native bank FX revaluation reconciles');
// 12-year settlement kernel: exercise a real post-transition ten-year
// maturity, refinance and carry residual dues; no full world horizon claim.
$id='new-normal-domestic-FRF';$state=$st;$state['accounts']['treasury_eur_minor']=0;$state['claims']=[];$calendar=[];$matured=0;$issued=0;$paid=0;$day=new DateTimeImmutable('2026-12-01');
for($i=0;$i<4384;$i++){
    $date=$day->modify('+'.$i.' days')->format('Y-m-d');
    $issues=$i%30===0?[$id=>['currency'=>'FRF','amount'=>100]]:[];
    $calendarStep=NS::schedule($calendar,$state['claims'],$date,$issues,10);
    $f=array_replace($flow,['revenue'=>50,'primary_expense'=>50,'issues'=>$issues,'maturities'=>$calendarStep['due']]);
    $result=NB::step($state,$f,2,10000,false,false);$state=$result['closing_state'];$calendar=$calendarStep['closing'];NS::checkCalendar($calendar,$state['claims']);
    $matured+=array_sum($calendarStep['due']);$issued+=$result['issued_principal']['FRF'];$paid+=$result['paid_principal']['FRF'];
}
ckCB($matured>0&&$state['claims'][$id]['principal_outstanding_minor']===$issued-$paid,'4384-day native kernel crosses actual ten-year maturities');
