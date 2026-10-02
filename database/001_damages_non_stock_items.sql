-- =========================================================
-- MGRATION 001: Ruhusu "Uharibifu" kurekodi vitu visivyo stock
-- (Fanya hii MARA MOJA TU kama database yako ipo tayari kabla ya
--  sasisho hili. Kama unasakinisha upya kutoka schema.sql, huhitaji
--  kuendesha faili hili — schema.sql tayari ina mabadiliko haya.)
--
-- Jinsi ya kutumia (phpMyAdmin -> SQL tab -> bandika yote -> Go),
-- au: mysql -u root -p fresh_scents_db < 001_damages_non_stock_items.sql
-- =========================================================

USE fresh_scents_db;

-- 1) Ongeza safu mpya
ALTER TABLE damages
    ADD COLUMN item_name VARCHAR(150) DEFAULT NULL COMMENT 'Jina la kitu kwa vitu visivyo stock' AFTER variant_id,
    ADD COLUMN is_stock_item TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=bidhaa ya stock, 0=kitu kingine' AFTER item_name;

-- 2) Ruhusu variant_id kuwa NULL (kwa vitu visivyo stock)
ALTER TABLE damages MODIFY variant_id INT DEFAULT NULL;

-- 3) Badilisha "quantity" iwe na default ya 1 (haihitajiki kwa vitu
--    visivyo stock, lakini bado ni NOT NULL kwenye jedwali)
ALTER TABLE damages MODIFY quantity INT NOT NULL DEFAULT 1;

-- 4) Badilisha FOREIGN KEY ya variant_id kutoka ON DELETE CASCADE
--    kuwa ON DELETE SET NULL. Jina la FK linaweza kutofautiana kati
--    ya server hadi server — angalia jina sahihi kwa amri hii kwanza:
--
--    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
--    WHERE TABLE_SCHEMA = 'fresh_scents_db' AND TABLE_NAME = 'damages'
--      AND COLUMN_NAME = 'variant_id' AND REFERENCED_TABLE_NAME = 'product_variants';
--
--    Kisha badilisha "damages_ibfk_1" hapa chini na jina halisi
--    lililorudishwa na query hiyo (mara nyingi ndilo hili default).
ALTER TABLE damages DROP FOREIGN KEY damages_ibfk_1;
ALTER TABLE damages ADD CONSTRAINT damages_variant_fk
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL;
