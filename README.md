# Inova7 Acadêmico

Sistema de gestão acadêmica da coordenação (Laravel 12 · PHP 8.3 · Blade · MySQL). Cobre o ciclo do semestre:

- catálogo de cursos, matrizes e disciplinas;
- habilitação e disponibilidade docente;
- montagem automática da grade horária, com diagnóstico, versões e publicação;
- enturmação;
- reservas de laboratórios por área;
- auditoria documental.

Produção: <https://coordenacao.inova7.com.br> (HostGator, deploy pelo cPanel).

---

## Perfis e permissões

| Perfil | O que faz |
| --- | --- |
| **Administrador de TI** (`admin`) | Tudo: usuários, cadastros, planejamento, importações, auditoria, manutenção do sistema e laboratórios de todas as áreas. |
| **Coordenador** (`coordinator`) | Cadastros acadêmicos, planejamento do semestre (grade, resolvedor, publicação, enturmação), importações e auditoria. |
| **Funcionário — Secretaria/CRA** (`staff`) | Consulta o catálogo, mantém dados de professores, importa planilhas e acessa a auditoria. |
| **Professor** (`teacher`) | Área docente: painel, **Minhas disciplinas** (habilitação) e **Minha disponibilidade** (CCG-FOR-02). |
| **Gestor de laboratórios** (`lab_manager` + área) | Aprova reservas e cadastra os laboratórios da sua área: TI (NTI), Saúde, Comunicação, Engenharias ou Arquitetura. |
| **Aluno** (`student`) | Vê os horários das turmas depois da publicação. |

A matriz de permissões fica em `App\Models\User` (`can*()`), nos Gates de `App\Providers\AppServiceProvider` e na `App\Policies\ProfessorPolicy`. As rotas aplicam o Gate no próprio grupo; páginas fora do perfil mostram a tela 403 com explicação.

## Mapa de áreas (rotas)

| URL | Área | Acesso |
| --- | --- | --- |
| `/` | Apresentação do sistema (visitante) ou início do perfil (logado) | todos |
| `/login` | Entrar | **sem login** |
| `/horarios` | Horários das turmas | todos (alunos e professores após a publicação) |
| `/docente/...` | Painel, disciplinas e disponibilidade do professor | professor |
| `/planejamento/...` | Grade global, diagnóstico, versões, montagem, enturmação, professores por turma | admin, coordenação |
| `/cadastros/...` | Cursos, matrizes, disciplinas (grade por matriz), professores, semestres | admin, coordenação |
| `/consultas/...` | Catálogo somente leitura | admin, coordenação, funcionário |
| `/laboratorios/...` | Reservas de laboratórios e cadastro por área | todos exceto aluno; decide: gestor da área / admin |
| `/calendario-laboratorios` | Calendário público dos laboratórios | **sem login** |
| `/importacoes/...` | Oferta Ubíqua, Base TOTVS, professores | admin, coordenação, funcionário |
| `/sistema/...` | Usuários, auditoria e manutenção | admin (auditoria também coordenação e funcionário) |
| `/perfil` | Dados pessoais e senha | todos |

## Fluxo do semestre

1. **Catálogo** (Cadastros):
   - cursos, cada um com as suas matrizes;
   - em **Disciplinas**, a grade de cada matriz por período: aulas/semana, turno, tipo e sala exigida;
   - a **Visão geral** aponta inconsistências: curso ou matriz duplicados, curso sem matriz, turmas ligadas à matriz errada.
2. **Oferta** (Importações › Oferta Ubíqua): turmas do semestre.
3. **Professores**:
   - marcam a habilitação em **Minhas disciplinas**;
   - preenchem a grade em **Minha disponibilidade** (Posso / Prefiro / Não posso);
   - a coordenação define os limites de aulas por dia e de dias por semana.
4. **Grade** (Planejamento):
   - **Grade global** (arrastar e soltar);
   - **Resolver automaticamente**: Web Worker no navegador, revalidado no servidor;
   - **Diagnóstico**: por que a grade não fecha, com o mapa de disputa por horário;
   - **Versões**: salvar, restaurar, exportar e importar.
5. **Publicar** (Montagem por turma): só então alunos e professores veem os horários.
6. **Enturmação** dos alunos de matrizes antigas: envie os históricos em PDF na própria tela (um ou vários arquivos), escolha a matriz (ex.: SI 2019.1) e gere a enturmação.

Semestre com status **Concluído** é somente consulta: o planejamento e a disponibilidade não aceitam alterações.

### Regras da grade

- **Restrições rígidas:** choque de professor, sala ou turma; aula fora da disponibilidade; carga semanal diferente da prevista.
- **Exceções aceitas:** aula conjunta (mesmo professor e mesma disciplina em turmas diferentes) e estudos dirigidos sobrepostos.
- **Pontuação** (quanto menor, melhor): janelas do professor, limites diários e semanais, disciplina concentrada num dia, horário fora do preferido e disciplina sem professor. Os pesos estão em `App\Services\ScheduleScorer`.

## Laboratórios (Regulamento PED-RGU-18307)

- **Elegibilidade (Art. 8º III):**
  - a disciplina exige o tipo de laboratório pedido na matriz;
  - sem tipo na matriz, carga **prática** > 0 exige laboratório de informática;
  - disciplina que não exige laboratório não ocupa laboratório;
  - o laboratório precisa estar ativo, ter computadores/vagas para a turma e, na TI, os softwares exigidos pela disciplina.
- **Sincronização com a grade (Art. 16 I):** cada aula em laboratório vira uma reserva semestral aprovada em `reservas_laboratorio` (`App\Services\GradeLaboratorioSyncService`). Mover ou tirar a aula cancela a reserva. Uma reserva aprovada (prova, evento, avulsa) impede alocar uma aula que choque com ela.
- **Painel por área:**
  - grade semanal com Aula regular, Reserva, Pendente e Livre (monitoria/estudo, Art. 16 II);
  - horário de funcionamento: seg–sex 07:30–22:20, sábado 07:30–12:00 (Art. 4º);
  - pedidos de professores e coordenação ficam pendentes até o gestor da área aprovar.
- **Calendário público** (`/calendario-laboratorios`): mostra só reservas aprovadas, sem dados de quem pediu.

Para a regra valer, cadastre a **carga prática** das disciplinas (ou a sala exigida na matriz) e os **computadores/softwares** dos laboratórios.

---

## Desenvolvimento local

Requisitos: PHP 8.3, Composer 2, MySQL 8 (ou Docker) e Node (opcional, só para testar o resolvedor JavaScript). Extensões PHP: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `intl`.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

A pasta `vendor/` **não é versionada**. Depois de um `git pull` que mude o `composer.json`, ou se aparecer erro de autoload, rode `composer install`.

### Docker (MySQL)

```bash
docker compose up -d --build
docker compose exec -T app php artisan migrate:fresh --seed
```

| Onde roda o Laravel | `DB_HOST` | `DB_PORT` |
| --- | --- | --- |
| Dentro do container | `mysql` | `3306` |
| No Windows/host, com MySQL no Docker | `127.0.0.1` | `3307` |

### Dados

- `DatabaseSeeder`: usuários de demonstração, todos com a senha `senha1234`, exceto a coordenação (`alterar-senha`).
  - `admin@inova7.local` (Administrador de TI)
  - `coordenacao@inova7.local` (coordenação)
  - `secretaria@inova7.local` (funcionário)
  - `professor@inova7.local` (professor)
  - `aluno@inova7.local` (aluno)
- `CargaPlanilhasSeeder`: dados reais das planilhas de 2027.1 e 2026.1 (CSV em `database/seeders/data/`), com salas, cargas, siglas e a grade de 2026.1 copiada para 2027.1.
- **Históricos de alunos** contêm dados pessoais: **nunca** os versione. Importe os PDFs pela tela de Enturmação (CPF/RG são ignorados e o arquivo não é guardado); o caminho por CSV está em `tools/historicos/LEIA-ME.md`.

### Testes

```bash
php vendor/bin/phpunit
```

Os testes usam SQLite em memória (`phpunit.xml`). Os testes do resolvedor JavaScript (`public/js/schedule-solver.js`) rodam com Node, quando ele está instalado.

---

## Produção (HostGator, sem terminal)

1. **Atualizar o código:** cPanel › **Git Version Control** › *Atualizar do remoto* (branch `main`).
2. **Deploy:** *Implantar commit HEAD*. Os passos estão em `.cpanel.yml` (Composer e `artisan` no caminho do site).
3. **Banco e caches:** entre como Administrador de TI em **Sistema › Manutenção**, aplique as migrations pendentes e limpe os caches. A tela mostra o PHP, o banco, o commit implantado e o log de erros.
4. **`.env`:** mantenha `APP_ENV=production` e **`APP_DEBUG=false`**.

Particularidades da hospedagem:

- **Mod_Security:** bloqueia requisições `PUT` vindas de `fetch()` e formulários que enviam links `http://`. Por isso a disponibilidade salva via `POST`, e o Lattes é enviado sem o protocolo.
- **Sem symlink:** o `storage:link` não é permitido. As fotos do disco `public` são entregues pela rota `/storage/{path}`, só para usuários logados.
- **Arquivos do servidor:** o `public/.htaccess` do servidor não é versionado. Não substitua.

### Sincronizar matrizes e disciplinas (local → produção)

No banco que está correto:

```bash
php artisan catalogo:exportar
```

O comando gera `database/data/catalogo/catalogo_<data>.json` e uma migration `*_sincronizar_catalogo.php`. Faça commit e push dos dois na `main` e aplique a migration em **Sistema › Manutenção**.

A sincronização casa tudo por código e nunca exclui cursos, matrizes ou disciplinas. Vínculos de grade só são removidos quando nenhuma turma os usa.

---

## Organização do código

| Onde | O quê |
| --- | --- |
| `app/Services/ScheduleAllocator.php` | Validação e gravação da grade (choques, disponibilidade, laboratórios) — todas as telas passam por aqui |
| `app/Services/ScheduleScorer.php` | Pontuação da grade |
| `app/Services/SolverProblemBuilder.php` + `public/js/schedule-solver.js` | Problema e resolvedor automático (Web Worker) |
| `app/Services/ScheduleDiagnostics.php` | Diagnóstico de inviabilidade e mapa de disputa |
| `app/Services/ScheduleScenarios.php` | Versões da grade (salvar, restaurar, exportar e importar) |
| `app/Services/LabRules.php`, `GradeLaboratorioSyncService.php`, `app/Support/LabAreas.php` | Regras e sincronização dos laboratórios |
| `app/Services/CatalogSync.php`, `CatalogConsistency.php` | Sincronização e consistência do catálogo |
| `app/Services/AvailabilityGrid.php`, `TimeBlocks.php` | Grade de disponibilidade e blocos de horário (A, B, C… por turno) |
| `app/Models/AcademicTerm.php` (`current()`) | Semestre de trabalho, igual em todas as telas |
| `routes/web.php` | Mapa de rotas por área, com o comentário de acesso de cada uma |
| `tests/Feature/` | Testes de cada módulo |
