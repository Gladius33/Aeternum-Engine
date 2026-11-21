-- Add a resource consumption reason for delayed upgrades.
ALTER TABLE engine_resource_ledger MODIFY COLUMN reason ENUM('opening','production','transfer','upgrade_cost') NOT NULL;
