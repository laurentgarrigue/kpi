-- Extend Id_Evenement / id_evenement columns from VARCHAR(20) to VARCHAR(2000)
-- Pipe-delimited event IDs (e.g. |223|222|217|208|243|239|) were silently truncated
-- at 20 chars, causing saved events to be lost without any error response

ALTER TABLE `kp_user`
  MODIFY COLUMN `Id_Evenement` VARCHAR(2000) NOT NULL DEFAULT '';

ALTER TABLE `kp_user_mandat`
  MODIFY COLUMN `id_evenement` VARCHAR(2000) NOT NULL DEFAULT '';
