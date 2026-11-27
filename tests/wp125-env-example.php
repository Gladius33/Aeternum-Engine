<?php
declare(strict_types=1);
$path=__DIR__.'/../games/world-politics/.env.example';
$lines=file($path,FILE_IGNORE_NEW_LINES);if($lines===false)throw new RuntimeException('Missing environment template');
$vars=[];
foreach($lines as $line){
    if(trim($line)===''||str_starts_with(trim($line),'#'))continue;
    if(!preg_match('/^([A-Z][A-Z0-9_]+)=(.*)$/D',$line,$m)||isset($vars[$m[1]]))throw new RuntimeException('Invalid environment entry');
    $vars[$m[1]]=$m[2];
}
foreach(['WP_DB_PASSWORD','WP_RESEARCH_DB_PASSWORD','WP_DATASET_DB_PASSWORD','WP_ALPHA_INVITE','WP_CAREER_EVIDENCE_KEY','WP_RESEARCH_EVIDENCE_KEY','WP_DATASET_BRIDGE_KEY','WP_STRIPE_SECRET_KEY','WP_STRIPE_PUBLISHABLE_KEY','WP_STRIPE_WEBHOOK_SECRET','WP_ADMIN_RESEARCH_STANDARD_MONTHLY_TOKEN_SHA256','WP_ADMIN_RESEARCH_STANDARD_PAYG_TOKEN_SHA256','WP_ADMIN_RESEARCH_PRO_MONTHLY_TOKEN_SHA256','WP_ADMIN_RESEARCH_PRO_PAYG_TOKEN_SHA256','WP_ADMIN_GAME_ACCOUNT'] as $k)
    if(!array_key_exists($k,$vars)||$vars[$k]!=='')throw new RuntimeException('Secret must be blank: '.$k);
foreach(['WP_ALPHA_ENABLED','WP_RESEARCH_ENABLED','WP_STRIPE_ENABLED','WP_ALPHA_LOOPBACK','WP_RESEARCH_LOOPBACK','WP_ALPHA_INSTALL','WP_RESEARCH_OPERATOR','WP_BILLING_OPERATOR','WP_DATASET_OPERATOR','WP_RESEARCH_TEST','WP_DATASET_AUTO_CAPTURE','WP_ADMIN_RESEARCH_ENABLED','WP_ADMIN_GAME_ENABLED'] as $k)
    if(($vars[$k]??null)!=='NO')throw new RuntimeException('Unsafe default: '.$k);
if($vars['WP_DB_DSN']===$vars['WP_RESEARCH_DB_DSN']||$vars['WP_STRIPE_MODE']!=='test')throw new RuntimeException('Unsafe environment separation');
echo "PASS World Politics safe environment template\n";
