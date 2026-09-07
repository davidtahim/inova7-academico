# Inova7 Acadêmico — Laravel 12

Aplicação acadêmica para gestão de cursos, planejamento semestral, disponibilidade docente, conflitos de horários e auditoria documental.

## Visão geral

O sistema já contempla:

- autenticação com login e logout;
- dashboard com layout autenticado;
- cursos, matrizes, semestres e disciplinas;
- professores, ofertas e alocações de horários;
- importação de planilhas de oferta;
- auditoria documental com templates HTML;
- ambiente local pronto com Docker e MySQL.

## Perfis de acesso

| Perfil | Descrição |
| --- | --- |
| Aluno | visualiza informações acadêmicas e painel pessoal. |
| Professor | informa disponibilidade e acompanha disciplinas. |
| Coordenador | gerencia planejamento e ofertas acadêmicas. |
| Funcionário (Secretaria/CRA) | suporte operacional e registros administrativos. |
| Funcionário de TI | importa planilhas e mantém dados acadêmicos importados. |
| Administrador de TI | gestão administrativa e configuração do ambiente. |

> A importação da planilha Ubíqua é restrita ao perfil de Funcionário de TI e Administrador de TI.

## Usuários de demonstração

| Perfil | E-mail | Senha |
| --- | --- | --- |
| Aluno | aluno@inova7.local | senha1234 |
| Professor | professor@inova7.local | senha1234 |
| Coordenador | coordenacao@inova7.local | alterar-senha |
| Funcionário (Secretaria/CRA) | secretaria@inova7.local | senha1234 |
| Funcionário de TI | ti@inova7.local | senha1234 |
| Administrador de TI | admin@inova7.local | senha1234 |

Esses usuários são criados em `database/seeders/DatabaseSeeder.php`.

## Como rodar localmente

### Requisitos

- PHP 8.2+
- Composer 2
- MySQL 8
- VS Code
- extensões: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `intl`

### Docker

```powershell
docker compose up -d --build
docker compose exec -T app php artisan migrate:fresh --seed
```

No ambiente Docker, a aplicação acessa o MySQL pelo nome do serviço:

```env
DB_HOST=mysql
DB_PORT=3306
```

### Windows local (Laravel no host, MySQL no Docker)

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Quando a aplicação roda no host do Windows e o MySQL está no Docker, use:

```env
DB_HOST=127.0.0.1
DB_PORT=3307
```

> Importante: `DB_HOST=mysql` só funciona quando a app está dentro do container. `DB_HOST=127.0.0.1` e porta `3307` são usados quando a app roda no Windows host e o MySQL está exposto pelos containers.

Acesse:

```text
http://localhost:8000/login
```

## Ambientes e configuração do banco

| Ambiente | Host do banco | Porta |
| --- | --- | --- |
| Laravel dentro do container Docker | `mysql` | `3306` |
| Laravel rodando no Windows/host com MySQL no Docker | `127.0.0.1` | `3307` |
| Produção | host interno/real do banco | porta do serviço real |

Se o host e a porta estiverem trocados, a aplicação quebra ao iniciar a sessão e ao tentar salvar usuário, porque o Laravel tenta acessar a tabela `sessions` e a tabela `users` no banco errado.

## Módulos principais

- gestão acadêmica
- planejamento de ofertas
- importação de dados
- auditoria documental
- templating de documentos acadêmicos

## Roadmap atual

- [x] autenticação
- [x] dashboard e layout
- [x] perfis de usuário
- [x] importação da oferta Ubíqua
- [x] usuários de demonstração
- [ ] formulários CRUD completos
- [ ] leitura automática de históricos e PPCs
- [ ] editor visual dos templates
- [ ] integrações e geração de comprovantes adicionais

## Segurança

Este projeto ainda é um MVP. Antes de produção, é importante reforçar:

- backup do banco;
- HTTPS;
- controle de permissões mais granular;
- proteção de arquivos privados;
- validação de uploads e limites de tamanho.

Nunca envie o arquivo `.env` para repositório público.
