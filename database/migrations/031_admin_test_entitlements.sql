-- M10: additive, isolated trusted-operator and TEST-ONLY cosmetic entitlement state.
-- Never store card data, real prices, money, provider secrets or financial claims.
CREATE TABLE engine_admin_scopes (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 operator_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 role VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 active TINYINT NOT NULL DEFAULT 1,
 changed_at BIGINT NOT NULL,
 PRIMARY KEY(world_id,operator_id),
 CONSTRAINT m10_scope_world_fk FOREIGN KEY(world_id) REFERENCES engine_worlds(world_id),
 CONSTRAINT m10_scope_role_ck CHECK(role IN ('viewer','manager')),
 CONSTRAINT m10_scope_active_ck CHECK(active IN (0,1))
) ENGINE=InnoDB;
CREATE TABLE engine_admin_audit (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 operator_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 action_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 target_actor VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 payload_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at BIGINT NOT NULL,
 PRIMARY KEY(world_id,request_id),
 KEY m10_audit_order(world_id,created_at,request_id),
 CONSTRAINT m10_audit_world_fk FOREIGN KEY(world_id) REFERENCES engine_worlds(world_id)
) ENGINE=InnoDB;
CREATE TABLE engine_test_payment_events (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 event_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 feature VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 transition VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 operator_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at BIGINT NOT NULL,
 PRIMARY KEY(world_id,event_id),
 KEY m10_test_actor_events(world_id,actor_id,created_at),
 CONSTRAINT m10_test_event_actor_fk FOREIGN KEY(world_id,actor_id)
  REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT m10_test_transition_ck CHECK(transition IN ('grant','revoke')),
 CONSTRAINT m10_test_feature_ck CHECK(feature IN ('profile_badge','ui_theme'))
) ENGINE=InnoDB;
CREATE TABLE engine_premium_entitlements (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 feature VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 enabled TINYINT NOT NULL DEFAULT 0,
 last_event_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 changed_at BIGINT NOT NULL,
 PRIMARY KEY(world_id,actor_id,feature),
 CONSTRAINT m10_ent_actor_fk FOREIGN KEY(world_id,actor_id)
  REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT m10_ent_enabled_ck CHECK(enabled IN (0,1)),
 CONSTRAINT m10_ent_feature_ck CHECK(feature IN ('profile_badge','ui_theme')),
 CONSTRAINT m10_ent_event_fk FOREIGN KEY(world_id,last_event_id)
  REFERENCES engine_test_payment_events(world_id,event_id)
) ENGINE=InnoDB;
