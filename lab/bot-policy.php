<?php
declare(strict_types=1);
/** Trusted lab-only planner: receives only normal player API observations. */
final class LabBotPolicy
{
    public const PROFILES=['builder','producer','merchant','balanced'];
    public static function choose(string $pack,string $account,int $index,int $tick,array $choices,array $progress,array $stocks,array $campaign,array $offers): ?array
    {
        $profile=self::PROFILES[($index-1)%4];
        $levels=$progress['levels'];
        $priority=match($pack){
            'medieval'=>$profile==='producer'?['farm','lumberyard','granary','keep']:['lumberyard','farm','granary','keep'],
            'industrial'=>['power_plant','warehouse','steelworks','freight_terminal'],
            'guild'=>['market_hall','caravan_hub','watchtower'],
        };
        $make=static fn(string $type,array $payload):array=>['type'=>$type,'payload'=>$payload];
        $afford=static function(array $costs)use($stocks):bool{
            foreach($costs as $resource=>$units)if(($stocks[$resource]['stock']??-1)<$units)return false;
            return true;
        };
        $level=static fn(string $type):int=>(int)($levels[$type]??0);
        $stock=static fn(string $resource):int=>(int)($stocks[$resource]['stock']??0);
        $available=static fn(string $action):bool=>($campaign['nextActionAt'][$action]??0)<=$tick*30;
        if($pack==='industrial'&&$level('power_plant')>0&&$stock('energy')<180&&$stock('steel')>=8&&$available('industry.generate'))
            return $make('industry.generate',['account'=>$account]);
        if($pack==='guild'&&$level('market_hall')>0&&($profile==='merchant'||$tick%4===($index%4))){
            foreach($offers as $o){
                if(($o['seller']??null)===$account||!isset($o['id'],$o['seller'],$o['wantResource'],$o['wantUnits'],$o['giveResource'],$o['giveUnits']))continue;
                if($o['giveUnits']>=$o['wantUnits']&&$stock($o['wantResource'])>=$o['wantUnits']+10
                    &&$stock($o['giveResource'])+$o['giveUnits']<=($stocks[$o['giveResource']]['capacity']??0))
                    return $make('market.accept',['offer'=>$o['id'],'seller'=>$o['seller'],'buyer'=>$account]);
            }
            $own=false;foreach($offers as $o)if(($o['seller']??null)===$account)$own=true;
            $give=$index%2===0?'grain':'spice';$want=$give==='grain'?'spice':'silver';
            if(!$own&&$stock($give)>=12)return $make('market.create',['seller'=>$account,'giveResource'=>$give,'giveUnits'=>2,'wantResource'=>$want,'wantUnits'=>2,'expiresAt'=>($tick+4)*30]);
        }
        if($pack==='medieval'&&$level('farm')>0&&($campaign['population']??0)<($campaign['capacity']??0)
            &&($campaign['population']??0)<($campaign['victory']['populationRequired']??0)&&$stock('grain')>=($campaign['foodCost']??PHP_INT_MAX)
            &&($profile==='producer'||$tick%3===($index%3)))
            return $make('settlement.feed',['account'=>$account]);
        foreach($priority as $type)foreach($progress['buildings'] as $b){
            if($b['type']===$type&&$b['ready']===true&&$afford($b['next']['costs']))
                return $make('building.start',['building'=>$b['id'],'account'=>$account,'type'=>$type]);
        }
        $present=array_column($choices['buildings'],'type');
        foreach($priority as $type){
            if(in_array($type,$present,true))continue;
            foreach($choices['types'] as $candidate){
                if($candidate['id']!==$type||!$afford($candidate['first']['costs']))continue;
                foreach($candidate['first']['requires'] as $dep=>$required)if($level($dep)<$required)continue 2;
                $site=$choices['sites'][0]??null;
                if($site)return $make('building.place',['building'=>'lab-b-'.substr(hash('sha256',$account."\0".$type),0,48),
                    'account'=>$account,'territory'=>$site['territory_id'],'entity'=>$site['entity_id'],'type'=>$type]);
            }
        }
        if($pack==='medieval'){
            if($level('farm')>0&&($campaign['population']??0)<($campaign['capacity']??0)
                &&($campaign['population']??0)<($campaign['victory']['populationRequired']??0)&&$stock('grain')>=($campaign['foodCost']??PHP_INT_MAX))
                return $make('settlement.feed',['account'=>$account]);
            if(($campaign['workers']??0)>0&&$level('lumberyard')>0&&$stock('grain')>=($campaign['workFoodCost']??PHP_INT_MAX)
                &&($campaign['nextWorkAt']??0)<=$tick*30&&$stock('wood')+($campaign['workYield']??0)<=($stocks['wood']['capacity']??0))
                return $make('settlement.work',['account'=>$account]);
        }
        if($pack==='industrial'){
            if($level('freight_terminal')>0&&$stock('goods')>=15&&$stock('energy')>=15&&$available('industry.ship')
                &&($campaign['delivery']['current']??0)<($campaign['delivery']['required']??0))
                return $make('industry.ship',['account'=>$account]);
            if($level('steelworks')>0&&$level('warehouse')>0&&$stock('steel')>=12&&$stock('energy')>=25
                &&$stock('goods')+20<=($campaign['warehouseCapacity']??0)&&$available('industry.refine'))
                return $make('industry.refine',['account'=>$account]);
        }
        // Research and expeditions use the exact player view; bots do not
        // receive hidden data or issue hostile challenges.
        $module=$campaign['module']??[];
        if(($module['canResearch']??false)===true)return $make('pack.campaign.research',['account'=>$account]);
        if(($module['canExpedition']??false)===true)return $make('pack.campaign.expedition',['account'=>$account]);
        $keys=array_keys($stocks);if(!$keys)return null;
        $resource=$keys[($tick+$index)%count($keys)];
        $plan=(new \Aeternum\Core\ResourceUpgradeBot())->choose($stocks[$resource],$account,$resource,$tick);
        return $plan===null?null:$make($plan->type,$plan->payload);
    }
}
