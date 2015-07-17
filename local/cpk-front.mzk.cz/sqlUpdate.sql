ALTER TABLE user CHANGE username username VARCHAR(64);
ALTER TABLE user_card ADD COLUMN eppn VARCHAR(64);
CREATE UNIQUE INDEX user_card_eppn_uq ON user_card(eppn);
