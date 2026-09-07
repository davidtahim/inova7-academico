# Situação do projeto

## Decisões confirmadas

- Laravel 12, MySQL e Bootstrap.
- Hospedagem futura em subdomínio da `inova7.com.br` no cPanel da HostGator.
- Sem integração com GPT, TOTVS ou Classis.
- Importação de planilhas e documentos fornecidos pela coordenação.
- Estrutura preparada para todos os cursos, começando por SI e ADS.
- Classificações semestrais obtidas da Planilha de Oferta Ubíqua.
- Classis permanece fora do sistema.
- CCG-FOR-01 emitido a partir de template carregado no módulo Auditoria.
- Documentos emitidos são versionados e não podem ser sobrescritos.
- O sistema já possui autenticação com login/logout e acesso restrito ao dashboard.
- O layout da aplicação oculta a sidebar antes do login e exibe o painel somente para usuários autenticados.
- O projeto foi estruturado para rodar localmente com Docker Compose (Laravel + MySQL + phpMyAdmin).

## Status atual da implementação

### Concluído

- Dashboard responsivo.
- Tela de login funcional.
- Autenticação de usuários.
- Proteção de rotas internas por middleware `auth`.
- Seed inicial com usuário de coordenação.
- Estrutura de semestres, cursos, matrizes, disciplinas, professores, ofertas e horários.
- Módulo de auditoria com templates e documentos.
- Migrações base e ambiente Docker funcional.

### Em andamento / pendente

- CRUD completo dos módulos administrativos.
- Importadores e integração com planilhas.
- Geração e divulgação de documentos complementares.
- Cadastro de alunos e históricos.
- Controle de perfis específicos por papel e permissões mais granulares.
- Deploy final em ambiente de produção.

## Prioridade funcional

1. Fechamento da disponibilidade docente.
2. Alocação por turma, dia e horário.
3. Detecção de conflitos entre cursos.
4. Conferência das horas para cadastro manual no TOTVS.
5. Emissão e divulgação do CCG-FOR-01.
6. Organização das evidências do item 1.12.1 da auditoria.
7. Cadastro de alunos, históricos e demais itens da auditoria.

## Conta padrão do sistema

- E-mail: `coordenacao@inova7.local`
- Senha: `alterar-senha`

## Fontes já estudadas

- Disciplinas 2026.2.xlsx.
- Planilha de Oferta Ubíqua 2026.2.
- Grades.xlsx.
- Matriz GRA-MAT-0228-F.
- Modelos CCG-FOR-01 manhã e noite.
- Auditoria Curso 2025.
- QUA-INT-08 V.25.

Os documentos originais não acompanham este pacote por conterem dados institucionais. Eles devem ser adicionados posteriormente pelo módulo de importação/auditoria.
