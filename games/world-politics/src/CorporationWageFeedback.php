<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Version-pinned illustrative private payroll channel; not observed sector weights. */
final class CorporationWageFeedback
{
    public const VERSION='wp54r-wage-feedback-v1';
    private const WEIGHTS=['agriculture'=>300,'industry'=>1600,'construction'=>800,'commerce'=>1700,
        'transport'=>700,'health'=>800,'education'=>400,'digital'=>600,'finance'=>700,'other-services'=>2400];
    public static function premium(array $state):int
    {
        $sum=0;
        foreach(self::WEIGHTS as $id=>$weight){
            $index=$state['branches'][$id]['wage_index_bps']??null;
            if(!is_int($index)||$index<10000||$index>100000000)throw new \DomainException('Invalid branch wage index');
            $sum+=min(13000,$index)*$weight;
        }
        return intdiv($sum,10000)-10000;
    }
}
