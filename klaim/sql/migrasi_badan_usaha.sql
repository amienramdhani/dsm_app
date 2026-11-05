-- Badan usaha dipilih Admin (tanpa menghapus data)
ALTER TABLE claims ADD COLUMN entity_by INT NULL AFTER entity;
