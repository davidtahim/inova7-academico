# Funcionalidade: Gerenciamento de Usuários pelo Administrador

## Resumo

Implementada uma funcionalidade completa para que o **administrador possa visualizar, editar e deletar perfis de usuários e professores** do sistema.

## O que foi desenvolvido

### 1. **Rotas Administrativas**
Adicionadas 4 novas rotas em `routes/web.php`:
- `GET /admin/planejamento/usuarios` - Lista todos os usuários
- `GET /admin/planejamento/usuarios/{user}/editar` - Edita o perfil de um usuário
- `PUT /admin/planejamento/usuarios/{user}` - Atualiza os dados do usuário
- `DELETE /admin/planejamento/usuarios/{user}` - Remove um usuário do sistema

**Localização:** [routes/web.php:12-15](/C:/Users/David Tahim Alves B/Projetos/inova7-academico/routes/web.php)

### 2. **Controller - AdminCatalogController**
Implementados 4 novos métodos em `AdminCatalogController`:

#### `usersIndex(Request $request)`
- Lista todos os usuários com filtros por nome, e-mail, matrícula e papel
- Suporta busca (campo `q`) e filtro por papel
- Ordena por nome

#### `userEdit(User $user)`
- Retorna a view de edição do usuário com todos os dados preenchidos
- Passa as opções de papéis para o formulário

#### `userUpdate(Request $request, User $user)`
- Valida os dados do usuário (nome, e-mail, matrícula, papel, senha)
- Permite alterar senha (opcional)
- Se o usuário for professor, sincroniza/cria registro na tabela `professors`
- Retorna mensagem de sucesso

#### `userDestroy(User $user)`
- Remove um usuário do sistema
- Protege contra deleção da própria conta do admin logado
- Retorna mensagem de sucesso ou erro

**Localização:** [app/Http/Controllers/AdminCatalogController.php:709-816](/C:/Users/David Tahim Alves B/Projetos/inova7-academico/app/Http/Controllers/AdminCatalogController.php)

### 3. **Views (Templates Blade)**

#### `admin/users-index.blade.php`
- Tabela responsiva listando todos os usuários
- Colunas: Nome, E-mail, Matrícula, Papel, Status, Ações
- Formulário de busca e filtro por papel
- Botões de ação: "Editar Perfil" e "Excluir"
- Proteção: não permite excluir a própria conta do admin

**Localização:** [resources/views/admin/users-index.blade.php](/C:/Users/David Tahim Alves B/Projetos/inova7-academico/resources/views/admin/users-index.blade.php)

#### `admin/users-form.blade.php`
- Formulário para editar perfil de usuário
- Campos: Nome, E-mail, Matrícula, Papel, Senha (opcional)
- Checkbox para ativar/desativar usuário
- Validação frontend e feedback de erros

**Localização:** [resources/views/admin/users-form.blade.php](/C:/Users/David Tahim Alves B/Projetos/inova7-academico/resources/views/admin/users-form.blade.php)

### 4. **Painel de Administração Atualizado**
- Adicionado card de estatísticas mostrando o total de usuários
- Adicionado link "Usuários" na seção de Cadastros
- Atualizado `planejamentoIndex()` para contar e passar usuários

**Localização:** [resources/views/admin/planejamento/index.blade.php](/C:/Users/David Tahim Alves B/Projetos/inova7-academico/resources/views/admin/planejamento/index.blade.php)

## Funcionalidades

### Listagem de Usuários
- ✅ Visualiza todos os usuários cadastrados
- ✅ Busca por nome, e-mail ou matrícula
- ✅ Filtra por papel (Aluno, Professor, Coordenador, Funcionário, Admin)
- ✅ Exibe status de ativo/inativo
- ✅ Interface responsiva com tabela

### Edição de Perfil
- ✅ Edita nome do usuário
- ✅ Altera e-mail
- ✅ Atualiza matrícula
- ✅ Muda o papel do usuário
- ✅ Permite redefinir senha (com confirmação)
- ✅ Ativa/desativa usuário
- ✅ Se mudar para professor: sincroniza com tabela `professors`

### Remoção de Usuário
- ✅ Deleta usuário do sistema
- ✅ Proteção: não permite deletar a própria conta do admin
- ✅ Confirmação antes de deletar

## Segurança

- ✅ Verificação `ensureAdmin()` em todos os métodos
- ✅ Validação de dados de entrada
- ✅ Proteção contra SQL injection (uso de Eloquent)
- ✅ Proteção contra alteração da própria conta admin
- ✅ Senhas são automaticamente hasheadas pelo Laravel

## Papéis Suportados

- **student** - Aluno
- **teacher** - Professor
- **coordinator** - Coordenador
- **staff** - Funcionário (Secretaria/CRA)
- **admin** - Administrador de TI

## Como Usar

### Acessar a listagem de usuários
1. Faça login como administrador
2. Clique em "Administração" na barra lateral
3. No painel, clique em "Usuários" ou na seção "Cadastros" → "Usuários"

### Editar um usuário
1. Na listagem, clique no botão "Editar Perfil" do usuário desejado
2. Faça as alterações necessárias
3. Clique em "Atualizar Usuário"

### Deletar um usuário
1. Na listagem, clique em "Excluir" (não disponível para a própria conta)
2. Confirme a ação no popup

### Filtrar usuários
1. Digite no campo de busca (nome, e-mail ou matrícula)
2. Selecione um papel na dropdown
3. Clique em "Filtrar"
4. Para limpar, clique em "Limpar"

## Arquivos Modificados

1. `routes/web.php` - Adicionadas rotas
2. `app/Http/Controllers/AdminCatalogController.php` - Adicionados métodos
3. `resources/views/admin/planejamento/index.blade.php` - Painel atualizado

## Arquivos Criados

1. `resources/views/admin/users-index.blade.php` - Listagem de usuários
2. `resources/views/admin/users-form.blade.php` - Formulário de edição

## Próximos Passos (Opcional)

- Adicionar rota para criar novo usuário via admin
- Adicionar paginação para grandes listas
- Adicionar auditoria de alterações de usuários
- Adicionar filtro por data de criação
- Exportar lista de usuários (CSV/PDF)
