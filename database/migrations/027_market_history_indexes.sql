-- M08 additive indices for resource filtering and private transaction history.
-- DDL commits implicitly. Quiesce writes and verify a complete off-instance
-- backup before running the checksummed migrator on any nonempty database.
ALTER TABLE engine_market_offers
  ADD KEY offer_resource_pair_page (world_id,status,give_resource,want_resource,offer_id),
  ADD KEY offer_buyer_history (world_id,buyer_id,status,offer_id);
