-- M05: additive settlement work and provisioning reasons.
-- Like migration 024, this ENUM DDL can rebuild InnoDB tables; back up and
-- rehearse on a restored copy before running against persistent worlds.
ALTER TABLE engine_resource_ledger MODIFY COLUMN reason
 ENUM('opening','production','transfer','upgrade_cost','market_reserve',
      'market_payment','market_delivery','market_release','market_claim',
      'construction_cost','construction_refund','construction_claim',
      'settlement_food','settlement_work') NOT NULL;
