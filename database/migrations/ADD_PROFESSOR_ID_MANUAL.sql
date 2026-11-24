-- SQL para adicionar coluna professor_id à tabela users
-- Execute isso no seu banco de dados (phpMyAdmin ou MySQL CLI)

-- Verificar se a coluna já existe
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='professor_id';

-- Adicionar a coluna se não existir
ALTER TABLE `users` ADD COLUMN `professor_id` BIGINT UNSIGNED NULL AFTER `is_active`;

-- Adicionar constraint de chave estrangeira
ALTER TABLE `users` ADD CONSTRAINT `users_professor_id_foreign` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE SET NULL;

-- Verificar se foi adicionado corretamente
-- DESCRIBE users;
-- SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' ORDER BY ORDINAL_POSITION;
