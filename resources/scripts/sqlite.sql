-- #!mysql
-- #{ economy
    -- #{ table
CREATE TABLE IF NOT EXISTS players
(
    username VARCHAR(16) NOT NULL PRIMARY KEY,
    balance  FLOAT       NOT NULL DEFAULT 0
);
    -- #}
    -- #{ insert
    -- #  :userName string
    -- #  :balance float
INSERT OR REPLACE INTO players(username, balance)
VALUES (:userName, :balance);
    -- #}
    -- #{ select
SELECT *
FROM players;
    -- #}
-- #}