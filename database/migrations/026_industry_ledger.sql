-- M06: allow industrial fuel, generation, manufacturing and transport ledger entries.
-- ENUM DDL may rebuild InnoDB tables: take a consistent backup, rehearse
-- against a nonempty clone, and verify journal replay before production use.
ALTER TABLE engine_resource_ledger MODIFY COLUMN reason
 ENUM('opening','production','transfer','upgrade_cost','market_reserve',
      'market_payment','market_delivery','market_release','market_claim',
      'construction_cost','construction_refund','construction_claim',
      'settlement_food','settlement_work','industry_fuel','industry_generation',
      'industry_feedstock','industry_power','industry_manufacture',
      'industry_freight','industry_transport') NOT NULL;
