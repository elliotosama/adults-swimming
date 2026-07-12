ALTER TABLE captains
    ADD COLUMN nickname VARCHAR(255) NULL AFTER captain_name;

UPDATE captains
SET nickname = captain_name
WHERE nickname IS NULL OR nickname = '';

ALTER TABLE captains
    MODIFY nickname VARCHAR(255) NOT NULL;
