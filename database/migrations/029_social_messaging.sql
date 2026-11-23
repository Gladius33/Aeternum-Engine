-- M09 messaging, blocks, reporting, review audit and persistent notifications.
-- Additive InnoDB objects; follow docs/M09_SOCIAL.md for rollback/restore.
CREATE TABLE engine_social_blocks (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 blocked_actor VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at BIGINT NOT NULL,
 PRIMARY KEY(world_id,actor_id,blocked_actor),
 CONSTRAINT social_block_actor_fk FOREIGN KEY(world_id,actor_id) REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT social_block_target_fk FOREIGN KEY(world_id,blocked_actor) REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT social_block_not_self_ck CHECK(actor_id<>blocked_actor)
) ENGINE=InnoDB;

CREATE TABLE engine_social_messages (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 message_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 sender_actor VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 recipient_actor VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 body TEXT NOT NULL,
 created_at BIGINT NOT NULL,
 hidden TINYINT NOT NULL DEFAULT 0,
 PRIMARY KEY(world_id,message_id),
 KEY social_message_recipient(world_id,recipient_actor,created_at,message_id),
 KEY social_message_sender(world_id,sender_actor,created_at,message_id),
 CONSTRAINT social_msg_sender_fk FOREIGN KEY(world_id,sender_actor) REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT social_msg_recipient_fk FOREIGN KEY(world_id,recipient_actor) REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT social_msg_hidden_ck CHECK(hidden IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE engine_social_reports (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 report_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 reporter_actor VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 message_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 reason VARCHAR(255) NOT NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'open',
 created_at BIGINT NOT NULL,
 PRIMARY KEY(world_id,report_id),
 KEY social_reports_queue(world_id,status,created_at,report_id),
 CONSTRAINT social_report_actor_fk FOREIGN KEY(world_id,reporter_actor) REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT social_report_message_fk FOREIGN KEY(world_id,message_id) REFERENCES engine_social_messages(world_id,message_id),
 CONSTRAINT social_report_status_ck CHECK(status IN ('open','dismissed','hidden'))
) ENGINE=InnoDB;

CREATE TABLE engine_social_notifications (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 notification_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 kind VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 subject_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at BIGINT NOT NULL,
 read_at BIGINT NULL,
 PRIMARY KEY(world_id,notification_id),
 KEY social_notification_inbox(world_id,actor_id,created_at,notification_id),
 CONSTRAINT social_notification_actor_fk FOREIGN KEY(world_id,actor_id) REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT social_notification_kind_ck CHECK(kind IN ('message','invitation','invitation_accepted'))
) ENGINE=InnoDB;

CREATE TABLE engine_social_moderation_audit (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 audit_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 report_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 operator_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 action VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 reason VARCHAR(255) NOT NULL,
 created_at BIGINT NOT NULL,
 PRIMARY KEY(world_id,audit_id),
 CONSTRAINT social_audit_report_fk FOREIGN KEY(world_id,report_id) REFERENCES engine_social_reports(world_id,report_id),
 CONSTRAINT social_audit_action_ck CHECK(action IN ('dismiss','hide'))
) ENGINE=InnoDB;
