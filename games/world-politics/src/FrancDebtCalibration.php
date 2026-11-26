<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** A deliberately limited empirical benchmark, not a causal fiscal model.
 * Fit on 2023-24; evaluate fixed coefficients on 2025-26. */
final class FrancDebtCalibration
{
    public const VERSION='wp54r-fr-debt-calibration-v1';
    public static function load():array
    {
        $raw=file_get_contents(__DIR__.'/../data/2026-11-26-fr-debt-calibration-v1.json');
        if($raw===false)throw new \DomainException('Missing debt calibration pack');
        $p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if($p['schema']!==self::VERSION||$p['unit']!=='100000000 EUR'||$p['train_through']!=='2024-12-31'||count($p['quarter_ends'])!==15)throw new \DomainException('Invalid debt calibration pack');
        return $p;
    }
    public static function report(array $model):array
    {
        $p=self::load();$series=$p['quarter_ends'];$dates=array_keys($series);$sorted=$dates;sort($sorted,SORT_STRING);
        if($dates!==$sorted)throw new \DomainException('Unordered debt series');
        $checks=[];$old=$model['source_pack']['observations']??[];$aft=$p['aft'];
        foreach($old as $o){
            if($o['kind']!=='observed')continue;
            if($o['metric']==='maastricht_gross_debt'&&$o['unit']==='EUR'&&preg_match('/^(\d{4})-Q([1-4])$/D',$o['period'],$m)){
                $d=(new \DateTimeImmutable(sprintf('%04d-%02d-01',(int)$m[1],(int)$m[2]*3)))->modify('last day of this month')->format('Y-m-d');
                if(isset($series[$d]))$checks['maastricht_gross_debt:'.$o['period']]=['staged'=>$o['value'],'verified'=>$series[$d]*100000000,'unit'=>'EUR'];
            }elseif($o['metric']==='state_negotiable_outstanding'&&$o['period']===$aft['stock_date']){
                $checks['state_negotiable_outstanding:'.$o['period']]=['staged'=>$o['value'],'verified'=>$aft['negotiable_stock_eur'],'unit'=>'EUR'];
            }elseif(str_starts_with($o['metric'],'state_negotiable_holder_share_')&&$o['period']===$aft['holder_period']){
                $key=substr($o['metric'],strlen('state_negotiable_holder_share_'));
                if(isset($aft['holder_shares_bps'][$key]))$checks[$o['metric']]=['staged'=>$o['value'],'verified'=>$aft['holder_shares_bps'][$key],'unit'=>'basis_points'];
            }
        }
        $matches=0;$mismatches=[];
        foreach($checks as $key=>&$v){$v['matches']=$v['staged']===$v['verified'];if($v['matches'])$matches++;else $mismatches[]=$key;}unset($v);
        $train=[];$holdout=[];$last=null;$sums=[1=>0,2=>0,3=>0,4=>0];$counts=[1=>0,2=>0,3=>0,4=>0];$all=0;$ndays=0;
        foreach($series as $date=>$raw){
            if(!is_int($raw)||$raw<=0||$raw>100000||!preg_match('/^\d{4}-(03-31|06-30|09-30|12-31)$/D',$date))throw new \DomainException('Invalid quarterly stock');
            $value=$raw*10000000000; // hundred million EUR -> cents
            if($last!==null){
                $n=(new \DateTimeImmutable($last['date']))->diff(new \DateTimeImmutable($date))->days;
                if($n<90||$n>92)throw new \DomainException('Missing quarter');
                $delta=$value-$last['value'];$q=intdiv((int)substr($date,5,2),3);
                $r=['from'=>$last['date'],'to'=>$date,'days'=>$n,'quarter'=>$q,'opening_stock_minor'=>$last['value'],'closing_stock_minor'=>$value,'actual_change_minor'=>$delta];
                if($date<=$p['train_through']){$train[]=$r;$sums[$q]+=$delta;$counts[$q]+=$n;$all+=$delta;$ndays+=$n;}else $holdout[]=$r;
            }
            $last=['date'=>$date,'value'=>$value];
        }
        if(count($train)!==8||count($holdout)!==6||$ndays===0||in_array(0,$counts,true))throw new \DomainException('Invalid calibration partition');
        $slope=intdiv($all,$ndays);$season=[];
        foreach($sums as $q=>$n)$season[$q]=intdiv($n,$counts[$q]);
        $errors=['persistence'=>0,'constant_trend'=>0,'seasonal_trend'=>0];$results=[];
        foreach($holdout as $r){
            $pred=['persistence'=>0,'constant_trend'=>$slope*$r['days'],'seasonal_trend'=>$season[$r['quarter']]*$r['days']];
            $err=[];
            foreach($pred as $id=>$v){$err[$id]=abs($v-$r['actual_change_minor']);$errors[$id]+=$err[$id];}
            $results[]=[...$r,'predicted_change_minor'=>$pred,'absolute_error_minor'=>$err];
        }
        $mae=[];foreach($errors as $id=>$v)$mae[$id]=intdiv($v,count($holdout));
        return ['version'=>self::VERSION,'verified_on'=>$p['verified_on'],'sources'=>$p['sources'],'pack_sha'=>hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),
            'source_crosscheck'=>['matches'=>$matches,'checked'=>count($checks),'mismatches'=>$mismatches,'details'=>$checks,'all_other_staged_fields_verified'=>false],
            'aft_verified_anchors'=>$aft,'fit'=>['method'=>'fixed_training_mean_daily_change_and_quarter_specific_mean','train_from'=>$dates[0],'train_through'=>$p['train_through'],'training_intervals'=>count($train),'holdout_intervals'=>count($holdout),'constant_daily_change_eur_minor'=>$slope,'seasonal_daily_change_eur_minor'=>$season,'holdout_quarterly_mae_eur_minor'=>$mae,'holdout'=>$results,'fitted_on_holdout'=>false,'policy_coefficients_changed'=>false,'causal_macro_model_calibrated'=>false],
            'classification'=>'historical_debt_stock_benchmark_not_policy_forecast'];
    }
}
