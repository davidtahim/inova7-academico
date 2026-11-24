# 🔧 FIX: Adicionar Coluna `professor_id` em Users

## Problema Identificado

Quando você tentava criar um usuário com perfil "Professor", o sistema falhava silenciosamente porque:

```php
// Controller tenta isso:
$user->update(['professor_id' => $professor->id]);
```

Mas a coluna `professor_id` **não existe** na tabela `users`.

## Solução

Você tem 2 opções:

### Opção 1: Usar artisan migrate (Recomendado)

```bash
cd C:\Users\David\ Tahim\ Alves\ B\Projetos\inova7-academico
php artisan migrate
```

Ou no terminal:
```bash
# Navegue até a pasta do projeto
cd C:\Users\David Tahim Alves B\Projetos\inova7-academico

# Execute a migration
php artisan migrate
```

**Esperado**: Ver mensagem de sucesso
```
Migrating: 2026_11_24_000000_add_professor_id_to_users_table
Migrated:  2026_11_24_000000_add_professor_id_to_users_table (XXms)
```

### Opção 2: Executar SQL Direto (Se artisan não funcionar)

1. **Abra phpMyAdmin** (geralmente em `http://localhost/phpmyadmin`)
2. **Selecione o banco**: `inova7_lara300` (ou o nome do seu banco)
3. **Vá para a aba "SQL"**
4. **Cole este comando:**

```sql
ALTER TABLE `users` ADD COLUMN `professor_id` BIGINT UNSIGNED NULL AFTER `is_active`;
ALTER TABLE `users` ADD CONSTRAINT `users_professor_id_foreign` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE SET NULL;
```

5. **Clique "Executar"**

## Verificação

Após executar a migration, verifique se a coluna foi criada:

**Opção 1: MySQL CLI**
```bash
mysql -u root -p
USE inova7_lara300;
DESCRIBE users;
```

Procure pela linha: `professor_id | bigint(20) unsigned | YES`

**Opção 2: phpMyAdmin**
1. Vá para banco `inova7_lara300`
2. Tabela `users`
3. Aba "Estrutura"
4. Procure por `professor_id`

## Teste Após Fix

1. **Acesse**: `http://127.0.0.1:8000/admin/planejamento/usuarios`
2. **Clique**: "+ Novo Usuário"
3. **Preencha com:**
   - Nome: João Silva
   - Email: joao@test.com
   - Matrícula: 123456
   - Perfil: **Professor**
   - Senha: Abc123def
   - Confirmar: Abc123def
   - Status: ✓ Ativo
4. **Clique**: "✓ Criar Usuário"

**Esperado**: 
- ✅ Redirecionado para a página do usuário
- ✅ Mensagem "Usuário criado com sucesso!"
- ✅ Professor criado automaticamente

## Troubleshooting

### Erro: "Foreign key constraint fails"
```
Errno 1452
Cannot add or update a child row: a foreign key constraint fails
```

**Causa**: Tabela `professors` não existe ou está vazia  
**Solução**: 
```sql
-- Verificar se tabela existe
SHOW TABLES LIKE 'professors';

-- Se não existir, executar todas as migrations
php artisan migrate
```

### Erro: "Duplicate column name"
```
Errno 1060
Duplicate column name 'professor_id'
```

**Causa**: Coluna já existe  
**Solução**: Coluna já foi adicionada, prossiga com teste

### Erro: "Unknown collation"
Tente remover ou adicionar com collation específica:
```sql
ALTER TABLE `users` ADD COLUMN `professor_id` BIGINT UNSIGNED NULL COLLATE utf8mb4_unicode_ci AFTER `is_active`;
```

---

**Data**: 2026-11-24  
**Status**: 🔧 Aguardando execução da migration
