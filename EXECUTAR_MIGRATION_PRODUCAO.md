# 🚀 Executar Migration em Produção (cPanel)

**Objetivo**: Adicionar coluna `professor_id` à tabela `users` em produção

**URL Produção**: https://coordenacao.inova7.com.br/

---

## 📍 Opção 1: Via Terminal SSH (Recomendado)

### Passo 1: Conectar ao Servidor

```bash
# Usar Git Bash ou WSL se estiver em Windows
ssh usuario@coordenacao.inova7.com.br
# Ou via cPanel Terminal
```

### Passo 2: Navegar para a Raiz do Projeto

```bash
cd /home/inova7-academico/
# Ou verifique o caminho correto
pwd  # Ver diretório atual
ls   # Listar arquivos
```

### Passo 3: Executar as Migrations

```bash
# Opção A: Executar todas as migrations pendentes
php artisan migrate

# Opção B: Executar step by step (mais seguro)
php artisan migrate --step

# Opção C: Ver qual seria executado (sem fazer)
php artisan migrate --pretend
```

### Passo 4: Verificar Status

```bash
php artisan migrate:status
```

Deve aparecer como ✅ quando executada:
```
2026_11_24_000000_add_professor_id_to_users_table ................ [2026-11-24 10:30:00]
```

---

## 📍 Opção 2: Via cPanel File Manager + Database

Se SSH não estiver disponível:

### Passo 1: Abrir cPanel

1. Acesse: https://seu-dominio/cpanel/
2. Login com suas credenciais
3. Localize **"phpMyAdmin"** ou **"MySQL Databases"**

### Passo 2: Executar SQL Direto

Abra o **phpMyAdmin** e execute o seguinte SQL:

```sql
-- Verificar se coluna JÁ existe (evitar erro)
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME='users' AND COLUMN_NAME='professor_id';

-- Se retornar vazio, executar:
ALTER TABLE users ADD COLUMN professor_id INT(10) UNSIGNED NULL AFTER role;

-- Adicionar índice/constraint
ALTER TABLE users ADD KEY users_professor_id_foreign (professor_id);

-- Adicionar foreign key (se tabela `professors` existir)
ALTER TABLE users ADD CONSTRAINT users_professor_id_foreign 
  FOREIGN KEY (professor_id) REFERENCES professors(id) ON DELETE SET NULL;
```

---

## 📍 Opção 3: Via Webhook/Deployment (Automático)

Se usar GitLab CI/CD ou GitHub Actions, adicionar ao `.gitlab-ci.yml`:

```yaml
deploy:
  script:
    - php artisan migrate --force
```

---

## ✅ Validação Pós-Execução

### Verificar via SSH

```bash
# Conectar ao MySQL
mysql -h localhost -u inova7_user -p inova7_lara300

# Executar:
DESCRIBE users;
# Deve mostrar: professor_id | int(10) unsigned | YES | MUL | NULL

# Verificar dados
SELECT id, name, role, professor_id FROM users LIMIT 5;
# User ID 10 deve ter professor_id = 7
```

### Verificar via PHP Artisan

```bash
php artisan tinker
> Schema::hasColumn('users', 'professor_id')
> true  ✓

> $user = User::find(10);
> $user->professor_id
> 7     ✓

> $user->professor
> App\Models\Professor {#...}  ✓
```

---

## 🔄 Se Algo Dar Errado

### Erro: "Migration already exists"

Significa que a migration JÁ foi executada. Verificar BD.

```bash
# Desfazer última migration (usar com CUIDADO!)
php artisan migrate:rollback --step=1

# Depois executar novamente
php artisan migrate
```

### Erro: "Foreign key constraint fails"

A tabela `professors` não existe ou tem ID diferente.

**Solução**: Remover constraint por enquanto:

```sql
ALTER TABLE users DROP FOREIGN KEY users_professor_id_foreign;
-- Manter apenas a coluna
```

Depois add constraint quando `professors` estiver pronta.

### Erro: "COLUMN_NAME already exists"

A coluna JÁ foi adicionada. Apenas verificar dados:

```bash
php artisan tinker
> User::find(10)->professor_id
# Deve retornar 7
```

---

## 📊 Verificação Final

**Local (seu computador)**:
```
php artisan migrate:status → Deve mostrar ✓ 2026_11_24_000000...
php artisan tinker
> User::find(10)->professor_id → Retorna 7
```

**Produção**:
```
php artisan migrate:status → Deve mostrar ✓ 2026_11_24_000000...
Login como professor → Redireciona para /admin/planejamento/professores/7/disponibilidade
```

---

## 🎯 Próxima Ação

Após executar migration com sucesso:

1. ✅ Coluna `professor_id` adicionada
2. ✅ User ID 10 tem `professor_id = 7`
3. ✅ Professor pode fazer login
4. ✅ Redirecionado para disponibilidade
5. ✅ Preenche disponibilidade normalmente

---

**Tempo esperado**: 2-5 minutos

**Nível de risco**: 🟢 Baixo (apenas adiciona coluna nova)

**Reversão**: Possível com `php artisan migrate:rollback --step=1`
