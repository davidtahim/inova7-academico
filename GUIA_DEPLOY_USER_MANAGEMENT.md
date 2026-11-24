# 🚀 Guia de Deploy: User Management Module

**Versão**: 2.1  
**Data**: 2026-11-24  
**Status**: ✅ Pronto para Produção

---

## 📋 Pré-requisitos

- [ ] PHP 8.3+
- [ ] Laravel 11+
- [ ] MySQL 5.7+
- [ ] Tabela `users` com colunas esperadas
- [ ] Acesso SSH/cPanel ao servidor

---

## 🔍 Verificação Pré-Deploy

### 1. Verificar Estrutura do Banco

Execute em SSH ou phpMyAdmin:

```sql
-- Verificar colunas esperadas
DESCRIBE users;

-- Esperado:
-- id, name, email, password, role, is_active, 
-- registration_number, must_change_password, last_login_at, created_at, updated_at
```

### 2. Validar Migrações

```bash
cd /path/to/inova7-academico

# Ver status das migrações
php artisan migrate:status

# Se houver pending, executar:
php artisan migrate
```

### 3. Testar Localmente

```bash
# Iniciar servidor de desenvolvimento
php artisan serve

# Acessar
# http://localhost:8000/admin/planejamento/usuarios
```

---

## 📦 Arquivos para Deploy

Garantir que estes arquivos estão no servidor:

### Views (4 arquivos)
```
✅ resources/views/admin/users-index.blade.php
✅ resources/views/admin/users-create.blade.php
✅ resources/views/admin/users-edit.blade.php
✅ resources/views/admin/users-show.blade.php
```

### Controllers (1 arquivo)
```
✅ app/Http/Controllers/UserManagementController.php
   (com 3 mudanças em nomes de views)
```

### Model (já deve existir)
```
✅ app/Models/User.php
   (com 10 métodos de RBAC: isAdminTi, hasRole, etc)
```

### Rotas (já deve existir em routes/web.php)
```
✅ 9 rotas de user management na group admin.planejamento
```

---

## 🧪 Teste de Integração (Checklist)

### Teste 1: Acesso à Listagem
```
GET /admin/planejamento/usuarios
Esperado:
  ✅ Sem erro "View not found"
  ✅ Sem erro "Undefined variable"
  ✅ Tabela com usuários carrega
  ✅ Filtros visíveis
  ✅ Paginação funciona
```

### Teste 2: Criação de Usuário
```
1. GET /admin/planejamento/usuarios/novo
   ✅ Formulário carrega
   
2. POST com dados válidos:
   - Nome: "João Silva"
   - Email: "joao@inova7.com.br"
   - Matrícula: "12345"
   - Perfil: "teacher"
   - Senha: "Abc123def"
   - Confirmar: "Abc123def"
   - Status: Ativo (checked)
   
   ✅ Usuário criado
   ✅ Redirecionado para show
   ✅ Professor record criado automaticamente
```

### Teste 3: Validações
```
1. Senha < 8 chars
   ✅ Erro: "Mínimo 8 caracteres com letras e números"
   
2. Senhas diferentes
   ✅ Erro: "Esta confirmação não corresponde"
   
3. Email duplicado
   ✅ Erro: "O campo email já foi utilizado"
   
4. Sem confirmação de senha
   ✅ Erro: "O campo Confirmar Senha é obrigatório"
```

### Teste 4: Edição
```
GET /admin/planejamento/usuarios/1/editar
✅ Formulário pré-preenchido
✅ Pode editar nome/email/perfil/status
✅ Alterações salvam corretamente
```

### Teste 5: Visualização
```
GET /admin/planejamento/usuarios/1
✅ Mostra todos os dados do usuário
✅ Buttons de ação funcionam
```

### Teste 6: Redefinir Senha
```
POST /admin/planejamento/usuarios/1/redefinir-senha
✅ Nova senha gerada
✅ Usuário recebe mensagem de sucesso
✅ must_change_password setado para true
```

### Teste 7: Alternar Status
```
POST /admin/planejamento/usuarios/1/toggle-status
✅ Status muda de ativo para inativo
✅ Usuário inativo não consegue fazer login
```

### Teste 8: Deletar Usuário
```
DELETE /admin/planejamento/usuarios/1
✅ Usuário removido
✅ Redirecionado para listagem
✅ Mensagem de sucesso aparece

PROTEÇÕES:
✅ Não consegue deletar a si mesmo
✅ Não consegue deletar último admin
```

---

## 🔐 Teste de Segurança

### 1. Acesso Anonymous
```
GET /admin/planejamento/usuarios (não autenticado)
Esperado:
  ❌ Redireciona para /login
```

### 2. Acesso Non-Admin
```
Login como: teacher/professor
GET /admin/planejamento/usuarios
Esperado:
  ❌ 403 Forbidden (não autorizado)
```

### 3. Auto-Deleção
```
Login como: admin_ti
GET /admin/planejamento/usuarios/1 (seu próprio id)
Esperado:
  ❌ Botão "Deletar" não aparece (oculto)
```

### 4. Remover Direitos
```
Login como: admin_ti
GET /admin/planejamento/usuarios/1/editar (seu próprio id)
POST alterando role para "coordinator"
Esperado:
  ❌ Erro: "Você não pode remover seus próprios direitos"
```

### 5. Desativar a Si Mesmo
```
POST /admin/planejamento/usuarios/1/toggle-status (seu próprio id)
Esperado:
  ❌ Botão não aparece/não funciona
```

### 6. Último Admin Protegido
```
Se há apenas 1 admin_ti no sistema:
DELETE /admin/planejamento/usuarios/[admin_id]
Esperado:
  ❌ Bloqueado: "Não é possível remover o último admin"
```

---

## ⚠️ Troubleshooting

### Erro: View not found
```
❌ View [admin.users-index] not found

✅ Solução:
  1. Verificar arquivo existe: resources/views/admin/users-index.blade.php
  2. Verificar controller chama: view('admin.users-index', ...)
  3. Executar: php artisan view:clear
  4. Recarregar página
```

### Erro: Undefined variable
```
❌ Undefined variable: $roles

✅ Solução:
  1. Verificar controller passa $roles: 'roles' => User::roleOptions()
  2. Verificar view usa {{ $roles }}
  3. Verificar nome da variável é consistente
```

### Erro: Validação de Senha
```
❌ validation.confirmed

✅ Solução:
  1. Verificar view tem DOIS campos:
     - <input name="password" ... />
     - <input name="password_confirmation" ... />
  2. Nomes devem ser EXATOS: password e password_confirmation
  3. Ambos devem estar no formulário
```

### Erro: 403 Forbidden
```
❌ 403 You are not authorized to perform this action

✅ Solução:
  1. Verificar usuário logado é admin_ti
  2. Verificar role do usuário no banco: SELECT role FROM users WHERE id=X
  3. Verificar User model tem método isAdminTi()
```

### Erro: Coluna não existe
```
❌ SQLSTATE[42S22]: Column 'registration_number' not found

✅ Solução:
  1. Controller já verifica com Schema::hasColumn()
  2. Se erro ocorre, significa coluna realmente falta
  3. Executar migration que cria coluna
  4. php artisan migrate
```

---

## 📞 Contato de Suporte

### Logs Importantes
```bash
# Ver últimos erros
tail -f storage/logs/laravel.log

# Ver erros SQL
tail -f storage/logs/laravel-sql.log

# Ver erros de acesso
tail -f var/log/apache2/error.log  # Apache
tail -f var/log/nginx/error.log    # Nginx
```

### Verificação de Permissões
```bash
# Garantir permissões de pasta
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/

# Se ainda der erro:
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

---

## 🎯 Checklist Final de Deploy

- [ ] Arquivos copiados para servidor
- [ ] Migrações executadas (`php artisan migrate`)
- [ ] Permissões de pasta corretas (755)
- [ ] Cache limpo (`php artisan cache:clear`)
- [ ] Views compiladas (`php artisan view:clear`)
- [ ] Teste: Acesso anônimo → redireciona para login
- [ ] Teste: Acesso admin → vê listagem
- [ ] Teste: Acesso non-admin → 403
- [ ] Teste: Criar usuário → funciona
- [ ] Teste: Validações → funcionam
- [ ] Teste: Redefinir senha → funciona
- [ ] Teste: Deletar → protegido
- [ ] Teste: Login com novo usuário → funciona
- [ ] Teste: Logout → funciona

---

## 🚀 Após Aprovação

1. **Integração de Menu**
   - Adicionar "Usuários e Acessos" na navbar
   - Condicional para admin-TI only

2. **Notificações**
   - Enviar email quando novo usuário criado
   - Enviar email quando senha redefinida

3. **Auditoria**
   - Registrar quem criou/editou/deletou usuários
   - Manter histórico de mudanças

4. **Relatórios**
   - Exportar usuários em PDF/Excel
   - Importar usuários em lote

---

**Versão**: 2.1  
**Status**: ✅ **PRONTO PARA PRODUÇÃO**  
**Próxima Review**: 2027-01-24 (após 1 mês em produção)

