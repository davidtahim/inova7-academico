# 🔍 Diagnóstico: Professor Login Redirect Não Funciona

**Problema**: Professor faz login e é redirecionado para `/perfil/editar#disponibilidade` em vez de `/admin/planejamento/professores/7/disponibilidade`

**URL Produção**: https://coordenacao.inova7.com.br/

---

## 🎯 Causa Provável

A coluna `professor_id` não foi adicionada à tabela `users` **no servidor de produção**.

No servidor local, a migração pode ter sido executada, mas no servidor remoto ela pode não ter rodado.

---

## ✅ Solução

### Passo 1: Verificar se a migration foi executada

**Local** (seu computador):
```bash
php artisan migrate:status
```

**Produção** (via SSH):
```bash
ssh usuario@coordenacao.inova7.com.br
cd /home/inova7-academico/
php artisan migrate:status
```

Procure por uma migration tipo:
```
2026_11_24_000000_add_professor_id_to_users_table ................ [2026-11-24 xx:xx:xx]
```

### Passo 2: Se NÃO estiver marcada como executada

Execute as migrations pendentes:

**Produção**:
```bash
php artisan migrate
```

Ou de forma mais segura:
```bash
php artisan migrate --step
```

### Passo 3: Verificar se a coluna existe no BD

**Produção** - Conectar ao MySQL:
```bash
mysql -h localhost -u inova7_user -p inova7_lara300
```

Depois executar:
```sql
DESCRIBE users;
-- Procurar por: professor_id | int(10) unsigned | YES   | MUL | NULL
```

---

## 🔧 Alternativa: Executar Migration Manualmente

Se o `php artisan migrate` não funcionar (permissões, etc):

### Via SSH / Terminal do cPanel

```bash
# Entrar no diretório
cd /home/inova7-academico/

# Conectar ao MySQL
mysql -h localhost -u inova7_user -p inova7_lara300

# Executar SQL direto
ALTER TABLE users ADD COLUMN professor_id INT(10) UNSIGNED NULL AFTER role;
ALTER TABLE users ADD KEY users_professor_id_foreign (professor_id);
ALTER TABLE users ADD CONSTRAINT users_professor_id_foreign FOREIGN KEY (professor_id) REFERENCES professors(id) ON DELETE SET NULL;
```

---

## 🧪 Teste Pós-Solução

### Local (seu computador):
1. Faça login como professor
2. Verifique URL: deve ser `/admin/planejamento/professores/7/disponibilidade`
3. Veja logs: `tail -f storage/logs/laravel.log | grep "Teacher login"`

### Produção:
1. Acesse https://coordenacao.inova7.com.br/login
2. Login como professor: `560127711@prof.uni7.edu.br`
3. Verifique URL: deve ser `/admin/planejamento/professores/7/disponibilidade`

---

## 📋 Verificação Completa

Executar no servidor **local** e comparar com **produção**:

```php
// In Laravel tinker:
$ php artisan tinker
> $user = User::find(10); // User com role 'teacher'
> $user->professor_id; // Deve retornar 7
> $user->professor; // Deve retornar o objeto Professor ou null
> $user->isTeacher(); // Deve retornar true
> Schema::hasColumn('users', 'professor_id'); // Deve retornar true
```

---

## 🚀 Próximos Passos

1. ✅ Verificar se migration foi executada em produção
2. ✅ Executar migration se necessário
3. ✅ Testar login do professor
4. ✅ Confirmar redirecionamento para `/admin/planejamento/professores/7/disponibilidade`
5. ✅ Professor consegue preencher disponibilidade

**Espero por feedback!**
