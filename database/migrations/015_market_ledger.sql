-- Distinguish escrow reserve, settlement, delivery and release in the committed ledger.
ALTER TABLE engine_resource_ledger MODIFY COLUMN reason ENUM('opening','production','transfer','upgrade_cost','market_reserve','market_payment','market_delivery','market_release') NOT NULL;
