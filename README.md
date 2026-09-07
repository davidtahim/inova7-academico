# Inova7 Acadêmico — Laravel 12

MVP independente para gestão acadêmica de cursos, planejamento semestral, disponibilidade docente, conflitos de horários e auditoria documental. O sistema não utiliza GPT, TOTVS ou APIs externas.

## O que já existe neste pacote

- Dashboard Bootstrap responsivo com layout autenticado e acesso restrito.
- Sistema de autenticação com login e logout funcional.
- Middleware de autenticação para rotas internas e ocultação da sidebar antes do login.
- Estrutura multicurso e matrizes com situações Atual, Ativa e Inativa.
- Semestres, disciplinas, professores, ofertas, alocações e horários.
- Detector inicial de choque de professor, turma e sala.
- Modalidades por oferta: presencial, híbrida, DOL, Navega, Notável Mestre, extensão e estágio.
- Módulo Auditoria com QUA-INT-08 V.25 e item 1.12.1.
- Upload e versionamento de templates HTML auditáveis.
- Templates iniciais CCG-FOR-01 V.08 para manhã e noite.
- Geração do CCG-FOR-01 em PDF a partir da turma cadastrada.
- Dados de demonstração de SI e ADS para 2026.2.
- Seed inicial com usuário de coordenação para login no sistema.
- Ambiente local pronto com Docker Compose para Laravel + MySQL + phpMyAdmin.

## Perfis de acesso

O sistema já contempla os seguintes perfis de usuário:

- Aluno
- Professor
- Coordenador
- Funcionário (Secretaria/CRA)
- Funcionário de TI
- Administrador de TI

### Funcionalidades por perfil

- Aluno: acesso ao painel e visualização de informações acadêmicas.
- Professor: pode atualizar o perfil, selecionar disciplinas e informar disponibilidade por dia e horário.
- Coordenador: acesso ao planejamento acadêmico, operação do sistema e acompanhamento das ofertas.
- Funcionário (Secretaria/CRA): suporte operacional e cadastro de registros.
- Funcionário de TI: responsável pela importação de planilhas de oferta, manutenção técnica do ambiente e gestão de dados acadêmicos importados.
- Administrador de TI: gestão administrativa e configuração do ambiente.

### Permissões específicas

- Importação de ofertas da planilha Ubíqua: restrita ao perfil de Funcionário de TI.
- Acesso ao módulo de importações e ao menu correspondente: liberado somente para usuários com esse perfil.
- Demais perfis autenticados podem entrar no sistema, mas não acessam a funcionalidade de importação.

## Ainda não concluído

- Formulários CRUD completos em todos os módulos.
- Importadores das planilhas Grades, Oferta Ubíqua e Disciplinas.
- Leitura automática de históricos e PPCs.
- Editor visual das marcações do template.
- CCG-FOR-26 e comprovantes de divulgação.
- Cadastro de alunos e histórico individual.
- Rotina de implantação específica do cPanel.
- Autorização granular por permissão mais avançada.

## Usuários de demonstração por perfil

O projeto já inclui usuários de demonstração criados via seed para cada perfil do sistema:

| Perfil | E-mail | Senha |
| --- | --- | --- |
| Aluno | aluno@inova7.local | senha1234 |
| Professor | professor@inova7.local | senha1234 |
| Coordenador | coordenacao@inova7.local | alterar-senha |
| Funcionário (Secretaria/CRA) | secretaria@inova7.local | senha1234 |
| Funcionário de TI | ti@inova7.local | senha1234 |
| Administrador de TI | admin@inova7.local | senha1234 |

> A importação da planilha Ubíqua é restrita ao perfil de Funcionário de TI (`ti@inova7.local`) e ao perfil de administrador de TI (`admin@inova7.local`).

Esses usuários são criados pelo seeder em `database/seeders/DatabaseSeeder.php`.

## Instalação no Windows

### 1. Pré-requisitos

- PHP 8.2 ou superior com extensões `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip` e `intl`.
- Composer 2.
- MySQL 8 ou MariaDB compatível.
- VS Code.

O Laragon é a opção mais simples para ter PHP, MySQL e terminal no Windows.

### 2. Colocar na pasta Projetos

Extraia o pacote para:

```text
C:\Projetos\inova7-academico
```

Abra o PowerShell nessa pasta:

```powershell
cd "C:\Users\SeuUsuario\Projetos\inova7-academico"
code .
```

### 3. Instalar e configurar

Opção recomendada para ambiente local com Docker:

```powershell
docker compose up -d --build
docker compose exec -T app php artisan migrate:fresh --seed
```

Opção tradicional localmente no Windows:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

No arquivo `.env`, use a configuração do banco local ou do container. Para o ambiente Docker, a configuração correta é:

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=inova7_academico
DB_USERNAME=root
DB_PASSWORD=root
```

Acesse `http://127.0.0.1:8000`.

## Acesso ao sistema

Depois de iniciar o projeto, acesse a tela de login em:

```text
http://localhost:8000/login
```

Use a conta de demonstração abaixo:

```text
E-mail: coordenacao@inova7.local
Senha: alterar-senha
```

## Perfil do professor

No perfil do usuário com papel de professor, é possível:

- selecionar as disciplinas que leciona;
- informar sua disponibilidade por dia e período;
- registrar preferência de horários e observações.

Esses dados são armazenados em cadastros de professor e de disponibilidade, e servem de base para o planejamento e alocação docente.

## Template auditável

Os modelos iniciais estão em `resources/audit-templates`. No sistema, acesse **Auditoria → Carregar template**. O arquivo deve ser HTML e usar marcações como:

```text
{{curso}}
{{semestre_letivo}}
{{turma}}
{{segunda_0730}}
```

Ao publicar uma nova versão, a anterior deixa de ser vigente, mas os documentos já emitidos preservam sua referência.

## Segurança antes de produção

Este pacote é um MVP de desenvolvimento. Antes de publicar, implemente autenticação, autorização por perfil, backup, HTTPS, limites de upload e proteção dos documentos privados. Nunca envie o `.env` para Git.

## Template auditável

Os modelos iniciais estão em `resources/audit-templates`. No sistema, acesse **Auditoria → Carregar template**. O arquivo deve ser HTML e usar marcações como:

```text
{{curso}}
{{semestre_letivo}}
{{turma}}
{{segunda_0730}}
```

Ao publicar uma nova versão, a anterior deixa de ser vigente, mas os documentos já emitidos preservam sua referência.

## Segurança antes de produção

Este pacote é um MVP de desenvolvimento. Antes de publicar, implemente autenticação, autorização por perfil, backup, HTTPS, limites de upload e proteção dos documentos privados. Nunca envie o `.env` para Git.
