-- Tambah jenis klaim Operasional (tanpa menghapus data). Aman dijalankan sekali; yang sudah ada dilewati.
INSERT INTO claim_types (category, name, active, locked, sort_no)
SELECT 'Operasional', t.n, 1, 0, t.s FROM (
  SELECT 'ATK & perlengkapan kantor' n, 40 s UNION ALL SELECT 'Listrik, air & internet', 41 UNION ALL SELECT 'Kebersihan & keamanan', 42
  UNION ALL SELECT 'Perbaikan & maintenance', 43 UNION ALL SELECT 'Ongkir & kurir', 44 UNION ALL SELECT 'Operasional lainnya', 45) t
WHERE NOT EXISTS (SELECT 1 FROM claim_types x WHERE x.name = t.n);
