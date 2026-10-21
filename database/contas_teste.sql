-- SENN AI-X / somente para demonstração local no XAMPP.
-- Senha das três contas novas: Demo-Senn-2026!
-- Não cria admin. Não altera senha/perfil de contas que já existam.
USE `senn_aix`;
START TRANSACTION;
INSERT INTO users(name,email,password_hash,role,active,privacy_accepted_at)
SELECT 'Gestor de teste','gestor@example.test','$2y$10$kvMGE.C6VgNubgWdM9FLKOMdNJtUqTpPIQJar/rpaIa/jUse0gDjK','gestor',1,NOW()
WHERE NOT EXISTS(SELECT 1 FROM users WHERE email='gestor@example.test');
INSERT INTO users(name,email,password_hash,role,active,privacy_accepted_at)
SELECT 'Despachante de teste','despachante@example.test','$2y$10$kvMGE.C6VgNubgWdM9FLKOMdNJtUqTpPIQJar/rpaIa/jUse0gDjK','despachante',1,NOW()
WHERE NOT EXISTS(SELECT 1 FROM users WHERE email='despachante@example.test');
INSERT INTO users(name,email,password_hash,role,active,privacy_accepted_at)
SELECT 'Motorista de teste','motorista@example.test','$2y$10$kvMGE.C6VgNubgWdM9FLKOMdNJtUqTpPIQJar/rpaIa/jUse0gDjK','motorista',1,NOW()
WHERE NOT EXISTS(SELECT 1 FROM users WHERE email='motorista@example.test');
COMMIT;
SELECT name,email,role,active FROM users
WHERE email IN ('gestor@example.test','despachante@example.test','motorista@example.test');
