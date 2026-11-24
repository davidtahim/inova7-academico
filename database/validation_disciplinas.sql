-- Script de Validação - Painel de Disciplinas do Professor
-- Execute no seu banco de dados para verificar se tudo está correto

-- 1. Verificar se a tabela pivot existe
SELECT 'Verificando tabela professor_subject...' as status;
SHOW TABLES LIKE 'professor_subject';

-- 2. Contar professores com nome "Reginaldo"
SELECT 'Professores com "Reginaldo" no nome:' as status;
SELECT id, name, email, qualification FROM professors WHERE name LIKE '%Reginaldo%';

-- 3. Contar total de disciplinas
SELECT 'Total de disciplinas no banco:' as status;
SELECT COUNT(*) as total FROM subjects;

-- 4. Listar algumas disciplinas como exemplo
SELECT 'Amostra de disciplinas:' as status;
SELECT id, code, name FROM subjects LIMIT 10;

-- 5. Verificar se professor Reginaldo já tem disciplinas associadas
SELECT 'Disciplinas do professor Reginaldo:' as status;
SELECT s.code, s.name 
FROM professor_subject ps
JOIN subjects s ON ps.subject_id = s.id
JOIN professors p ON ps.professor_id = p.id
WHERE p.name LIKE '%Reginaldo%'
ORDER BY s.name;

-- 6. Contar quantas disciplinas tem associadas
SELECT 'Contagem de disciplinas do Reginaldo:' as status;
SELECT COUNT(*) as total_disciplinas
FROM professor_subject ps
JOIN professors p ON ps.professor_id = p.id
WHERE p.name LIKE '%Reginaldo%';

-- 7. Verificar integridade das foreign keys
SELECT 'Verificando integridade das FKs:' as status;
SELECT 'Orphaned professor_id' as issue
FROM professor_subject ps
LEFT JOIN professors p ON ps.professor_id = p.id
WHERE p.id IS NULL
UNION ALL
SELECT 'Orphaned subject_id' as issue
FROM professor_subject ps
LEFT JOIN subjects s ON ps.subject_id = s.id
WHERE s.id IS NULL;

-- 8. Ver estrutura da tabela pivot
DESCRIBE professor_subject;

-- 9. Contar professores ativos
SELECT 'Professores ativos:' as status;
SELECT COUNT(*) as total FROM professors WHERE active = 1;

-- 10. Verificar se existem termos acadêmicos
SELECT 'Termos acadêmicos cadastrados:' as status;
SELECT id, code, status, starts_at, ends_at FROM academic_terms ORDER BY code;
