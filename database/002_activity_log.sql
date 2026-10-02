-- =========================================================
-- MGRATION 002: Ongeza jedwali la "Activity Log"
-- (Fanya hii MARA MOJA TU kama database yako ipo tayari kabla ya
--  sasisho hili. Kama unasakinisha upya kutoka schema.sql, huhitaji
--  kuendesha faili hili — schema.sql tayari ina mabadiliko haya.)
--
-- Jinsi ya kutumia (phpMyAdmin -> SQL tab -> bandika yote -> Go),
-- au: mysql -u root -p fresh_scents_db < 002_activity_log.sql
-- =========================================================

USE fresh_scents_db;

CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL,
    admin_name VARCHAR(120) DEFAULT NULL COMMENT 'Jina la mtumiaji wakati wa kitendo (linabaki hata akifutwa)',
    module VARCHAR(40) NOT NULL COMMENT 'mfano: sales, stock, damages, payroll, expenses, auth, products, admins',
    action VARCHAR(30) NOT NULL COMMENT 'mfano: create, update, delete, login, logout',
    description VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;
