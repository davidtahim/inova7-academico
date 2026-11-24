# 🚀 Instruções para Executar Fix - User Management

## 1️⃣ Abra um Terminal

**Windows (Cmd ou PowerShell)**:
1. Pressione `Win + R`
2. Digite `cmd` ou `powershell`
3. Pressione Enter

**VS Code (integrado)**:
1. Pressione `` Ctrl + ` ``
2. Ou: View → Terminal

## 2️⃣ Navegue para a Pasta do Projeto

```bash
cd C:\Users\David Tahim Alves B\Projetos\inova7-academico
```

## 3️⃣ Execute a Migration

```bash
php artisan migrate
```

**Esperado**: Você verá algo assim:
```
Migrating: 2026_11_24_000000_add_professor_id_to_users_table
Migrated:  2026_11_24_000000_add_professor_id_to_users_table (45ms)
```

## 4️⃣ Se Artisan Não Funcionar

Tente este comando alternativo:
```bash
php apply-fix.php
```

Ou execute SQL direto no banco (phpMyAdmin):
```sql
ALTER TABLE `users` ADD COLUMN `professor_id` BIGINT UNSIGNED NULL AFTER `is_active`;
ALTER TABLE `users` ADD CONSTRAINT `users_professor_id_foreign` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE SET NULL;
```

## 5️⃣ Teste Novamente

Depois que a coluna for adicionada:

1. Acesse: http://127.0.0.1:8000/admin/planejamento/usuarios
2. Clique em "+ Novo Usuário"
3. Preencha o formulário **exatamente** assim:
   - **Nome**: João Silva
   - **Email**: joao.silva@test.com
   - **Matrícula**: 123456
   - **Perfil**: Professor
   - **Senha**: Abc123def (mínimo 8 caracteres com letras e números)
   - **Confirmar Senha**: Abc123def (DEVE SER IGUAL)
   - **Status**: ✓ Ativo (checked)

4. Clique: **✓ Criar Usuário**

## ✅ Esperado

- Você deve ser **redirecionado** para a página do usuário
- Deve ver mensagem: **"Usuário criado com sucesso!"**
- Na listagem de usuários deve aparecer o novo usuário

## ❌ Se der erro:

Copie a mensagem de erro e me envie. Pode ser algo como:
- `Coluna não encontrada`
- `Constraint falhou`
- `Validação de dados`
- `Base de dados indisponível`

---

## Resumo da Causa do Problema

O código estava tentando:
```php
$user->update(['professor_id' => $professor->id]);
```

Mas a coluna `professor_id` não existia na tabela `users`, causando erro silencioso.

**Solução**: Adicionar a coluna via migration.

---

**Próxima etapa**: Depois que isso for feito, testaremos os outros perfis (admin, coordenador, etc).
