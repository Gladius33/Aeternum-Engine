<?php
declare(strict_types=1);
use Aeternum\Core\ProductionState;
$production = new ProductionState(500,5000,150,3600,0);
check($production->advanceTo(43200)->stock === 2300,'12-hour lazy production');
$small = new ProductionState(0,20,1,3,0);
check($small->advanceTo(2)->stock === 0 && $small->advanceTo(2)->remainder === 2,'fractional carry retained');
check($small->advanceTo(2)->advanceTo(3) == $small->advanceTo(3),'fractional carry independent of settlement frequency');
check($small->advanceTo(60)->stock === 20 && $small->advanceTo(61)->remainder === 0,'saturation discards excess');
check($small->advanceTo(60)->debit(1)->advanceTo(62)->stock === 19,'no accumulated output while full');
check($small->advanceTo(2)->credit(20)->remainder === 0,'credit to capacity discards fractional carry');
$huge = new ProductionState(0,ProductionState::MAX_UNITS,ProductionState::MAX_UNITS,ProductionState::MAX_INTERVAL,0);
check($huge->advanceTo(PHP_INT_MAX)->stock === ProductionState::MAX_UNITS,'huge time saturates without overflow');
$near = $huge->advanceTo(ProductionState::MAX_INTERVAL-1);
check($near->stock < $near->capacity && $near->advanceTo(ProductionState::MAX_INTERVAL)->stock === $near->capacity,'near-limit exact arithmetic');
rejects(fn()=> $small->advanceTo(-1),DomainException::class,'time regression rejected');
rejects(fn()=> $small->debit(1),DomainException::class,'negative balance rejected');
rejects(fn()=> $small->credit(21),DomainException::class,'capacity respected');
rejects(fn()=> new ProductionState(0,ProductionState::MAX_UNITS+1,1,1,0),InvalidArgumentException::class,'numeric bounds enforced');
for ($rate=0;$rate<10;$rate++) {
    for ($interval=1;$interval<11;$interval++) {
        $base = new ProductionState(3,49,$rate,$interval,0);
        for ($t=0;$t<100;$t++) {
            $a = $base->advanceTo($t);
            $b = $base->advanceTo(intdiv($t,2))->advanceTo($t);
            if ($a != $b || $a->stock !== min(49,3+intdiv($t*$rate,$interval))) throw new RuntimeException('Production partition invariant');
        }
    }
}
check(true,'10,000 deterministic partition and reference-formula cases');
