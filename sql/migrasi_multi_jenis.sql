-- Satu klaim berisi beberapa jenis (satu kategori). Aditif.
ALTER TABLE claims ADD COLUMN type_ids VARCHAR(200) NULL;
