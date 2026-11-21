-- Existing worlds retain explicitly legacy unbound handler compatibility.
-- New production worlds should use a trusted, pinned RuleSet.
ALTER TABLE engine_worlds ADD COLUMN rules_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'legacy';
