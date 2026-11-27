-- Dataset sequence assignment must follow transaction commit order.
CREATE TABLE wp_game_dataset_order (
 singleton_id TINYINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB;
INSERT INTO wp_game_dataset_order(singleton_id) VALUES(1);
