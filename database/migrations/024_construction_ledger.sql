-- M04 additive reason for atomic multi-resource construction expenditure.
-- DDL can rebuild a large InnoDB table: quiesce traffic and take a verified,
-- off-instance logical backup before applying on persistent databases.
ALTER TABLE engine_resource_ledger MODIFY COLUMN reason
 ENUM('opening','production','transfer','upgrade_cost','market_reserve',
      'market_payment','market_delivery','market_release','market_claim',
      'construction_cost') NOT NULL;
