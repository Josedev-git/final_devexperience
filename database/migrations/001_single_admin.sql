-- Executar UMA VEZ em instalações anteriores. Não apaga usuários nem entregas.
-- Antes, confirme que há no máximo um admin. Se houver vários, escolha o titular
-- e altere os demais perfis para gestor manualmente antes de aplicar a restrição.
USE `senn_aix`;
SELECT id,name,email FROM users WHERE role='admin';
ALTER TABLE users
 ADD COLUMN admin_singleton TINYINT
 GENERATED ALWAYS AS (CASE WHEN role='admin' THEN 1 ELSE NULL END) STORED,
 ADD UNIQUE KEY uq_users_single_admin(admin_singleton);
