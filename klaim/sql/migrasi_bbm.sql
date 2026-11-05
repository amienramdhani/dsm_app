-- Migrasi BBM (tanpa menghapus data): jalankan sekali di phpMyAdmin pada database dsm_claim
CREATE TABLE IF NOT EXISTS claim_fuel_price (
  id INT AUTO_INCREMENT PRIMARY KEY,
  effective_from DATE NOT NULL,
  pertalite INT NOT NULL,
  pertamax INT NOT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Harga contoh (DATA TES). Ganti lewat Super Admin > Harga BBM.
INSERT INTO claim_fuel_price (effective_from, pertalite, pertamax, created_by, created_at)
SELECT '2000-01-01', 10000, 20000, 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM claim_fuel_price);
