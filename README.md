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

### Windows local

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Acesse:

```text
http://localhost:8000/login
```

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
