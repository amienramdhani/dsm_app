-- Tanda tangan elektronik (gambar) disimpan sekali di profil pengguna. Aditif, aman dijalankan sekali.
ALTER TABLE claim_users ADD COLUMN sign_img MEDIUMTEXT NULL;
ALTER TABLE claim_ba_sign ADD COLUMN img MEDIUMTEXT NULL;
