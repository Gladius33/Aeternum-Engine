CREATE TABLE engine_rate_limit_maintenance (
 singleton TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 minimum_now BIGINT NOT NULL,
 CHECK (singleton=1),
 CHECK (minimum_now>=0)
) ENGINE=InnoDB;
INSERT INTO engine_rate_limit_maintenance(singleton,minimum_now) VALUES(1,0);
