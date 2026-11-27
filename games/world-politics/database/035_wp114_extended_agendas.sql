-- One receipt per objective in a mandate/profile; one direct domestic
-- objective per day. Existing aid receipts retain their original identity.
ALTER TABLE wp_universe_programme_direct DROP PRIMARY KEY,
 ADD PRIMARY KEY(world_id,mandate_start,programme,action_id);
