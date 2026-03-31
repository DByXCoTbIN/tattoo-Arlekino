-- v19: добор колонок видимости профиля, если v18 применилась не полностью
ALTER TABLE master_profiles ADD COLUMN profile_hidden_by_master INTEGER NOT NULL DEFAULT 0;
ALTER TABLE master_profiles ADD COLUMN admin_profile_visibility TEXT;
