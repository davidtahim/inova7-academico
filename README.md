# Inova7 Acadêmico — Laravel 12

MVP independente para gestão acadêmica de cursos, planejamento semestral, disponibilidade docente, conflitos de horários e auditoria documental. O sistema não utiliza GPT, TOTVS ou APIs externas.

## O que já existe neste pacote

- Dashboard Bootstrap responsivo.
- Estrutura multicurso e matrizes com situações Atual, Ativa e Inativa.
- Semestres, disciplinas, professores, ofertas, alocações e horários.
- Detector inicial de choque de professor, turma e sala.
- Modalidades por oferta: presencial, híbrida, DOL, Navega, Notável Mestre, extensão e estágio.
- Módulo Auditoria com QUA-INT-08 V.25 e item 1.12.1.
- Upload e versionamento de templates HTML auditáveis.
- Templates iniciais CCG-FOR-01 V.08 para manhã e noite.
- Geração do CCG-FOR-01 em PDF a partir da turma cadastrada.
- Dados de demonstração de SI e ADS para 2026.2.

## Ainda não concluído

- Autenticação e perfis de acesso.
- Formulários CRUD completos.
- Importadores das planilhas Grades, Oferta Ubíqua e Disciplinas.
- Leitura automática de históricos e PPCs.
- Editor visual das marcações do template.
- CCG-FOR-26 e comprovantes de divulgação.
- Cadastro de alunos e histórico individual.
- Rotina de implantação específica do cPanel.

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
cd C:\Projetos\inova7-academico
code .
```

### 3. Instalar e configurar

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Crie no MySQL um banco chamado `inova7_academico` e ajuste no `.env`:

```dotenv
DB_DATABASE=inova7_academico
DB_USERNAME=root
DB_PASSWORD=
```

Depois execute:

```powershell
php artisan migrate --seed
php artisan serve
```

Acesse `http://127.0.0.1:8000`.

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
