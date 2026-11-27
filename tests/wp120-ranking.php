<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/CareerScorePublication.php';
use Aeternum\WorldPolitics\CareerScorePublication as P;
function ck120rank(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$row=fn($a,$u,$s,$score,$gain,$t)=>['account'=>$a,'universe'=>$u,'series'=>$s,'score'=>$score,'gain'=>$gain,'tenure'=>$t];
$rows=[$row('a',2,'a2',2000,100,365),$row('b',1,'b1',2100,100,365),$row('a',1,'a1',2100,100,365),$row('c',2,'c1',2100,50,730),$row('d',2,'d1',1000,0,365)];
$r=P::rank($rows);
ck120rank(array_column($r,'account')===['a','b','c','d'],'dedup and stable ties');
ck120rank(array_column($r,'rank')===[1,1,3,4],'competition rank');
ck120rank($r[0]['series']==='a1'&&$r===P::rank(array_reverse($rows)),'best series deterministic');
echo "PASS WP120 ranking ties, per-account dedup, stable ordering\n";
