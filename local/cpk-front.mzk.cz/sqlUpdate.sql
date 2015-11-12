/* Set longer username in user table */
ALTER TABLE user CHANGE username username VARCHAR(64);

/* Create indexed eppn for purposes of connected federative identities */
ALTER TABLE user_card ADD COLUMN eppn VARCHAR(64);
CREATE UNIQUE INDEX user_card_eppn_uq ON user_card(eppn);

/* We will need user's last notificated time to don't spam all the ILSes every click */
ALTER TABLE user ADD last_notificated DATETIME NULL DEFAULT '0000-00-00 00:00:00' AFTER created;
CREATE INDEX last_notificated_dtime ON user(last_notificated);
