-- Minimal jarak BBM per kendaraan (aturan klaim). Aditif.
ALTER TABLE claim_rules ADD COLUMN min_km_mobil INT NULL;
ALTER TABLE claim_rules ADD COLUMN min_km_motor INT NULL;
