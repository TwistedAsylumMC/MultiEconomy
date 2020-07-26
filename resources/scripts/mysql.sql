-- #!mysql
-- #{ economy
    -- #{ table
CREATE TABLE IF NOT EXISTS %tableName%
(
    username VARCHAR(16) NOT NULL PRIMARY KEY,
    balance  FLOAT       NOT NULL DEFAULT 0
);
    -- #}
    -- #{ insert
    -- #  :userName string
    -- #  :balance float
INSERT INTO %tableName%(username, balance)
VALUES (:userName, :balance)
ON DUPLICATE KEY UPDATE balance = :balance;
    -- #}
    -- #{ select
SELECT *
FROM %tableName%;
    -- #}
-- #}