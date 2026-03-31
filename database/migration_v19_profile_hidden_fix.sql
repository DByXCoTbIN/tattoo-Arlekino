-- v19: добор колонок видимости профиля (MySQL)
ALTER TABLE master_profiles ADD COLUMN profile_hidden_by_master TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE master_profiles ADD COLUMN admin_profile_visibility VARCHAR(20) NULL DEFAULT NULL;
