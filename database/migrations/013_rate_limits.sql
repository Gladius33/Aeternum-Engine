CREATE TABLE engine_rate_limits (
 bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 window_start BIGINT NOT NULL,
 hits INT NOT NULL,
 INDEX(window_start)
) ENGINE=InnoDB;
