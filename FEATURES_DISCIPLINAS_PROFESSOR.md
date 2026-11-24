# Painel de Disponibilidade de Disciplinas do Professor

## Resumo das Alterações

### 1. Controller - ProfessorAvailabilityController
- ✅ Adicionado método `showSubjects()` - Exibe todas as disciplinas e as selecionadas pelo professor
- ✅ Adicionado método `updateSubjects()` - Sincroniza as disciplinas selecionadas do professor
- ✅ Importado Subject model e Request

### 2. Rotas - routes/web.php
- ✅ Adicionada rota GET `/professores/{professor}/disciplinas` → `admin.professors.subjects` 
- ✅ Adicionada rota PUT `/professores/{professor}/disciplinas` → `admin.professors.update-subjects`

### 3. Views
- ✅ Criada `resources/views/admin/professors-subjects.blade.php`
  - Exibe disciplinas selecionadas do professor
  - Formulário com checkboxes para selecionar/deselecionar disciplinas
  - Resumo com estatísticas (total, habilitado, não habilitado)
  - Botão de salvar e cancelar

- ✅ Atualizada `resources/views/admin/professors/index.blade.php`
  - Adicionado botão 📚 (livros) para acessar disciplinas do professor
  - Botão ao lado do 📅 (horários)

### 4. Seeder - ProfessorSubjectsSeeder.php
- ✅ Criado seeder para adicionar disciplinas ao professor Reginaldo
- ✅ Busca as disciplinas no banco de dados
- ✅ Usa `sync()` para sincronizar relacionamento many-to-many
- ✅ Exibe informações de progresso no console

## Como Executar

### 1. Primeiro, certifique-se de que o professor Reginaldo está criado
```bash
# No SSH do cPanel, execute:
php artisan tinker
```

Dentro do tinker:
```php
App\Models\Professor::where('name', 'like', '%Reginaldo%')->first();
```

Se retornar null, crie o professor primeiro:
```php
App\Models\Professor::create([
    'registration' => 'UNI727777',
    'name' => 'Reginaldo Borba',
    'email' => 'reginaldo@inova7.local',
    'qualification' => 'Mestre',
    'active' => true
]);
```

### 2. Verificar quais disciplinas existem no banco
```php
App\Models\Subject::pluck('name', 'code');
```

Se não houver disciplinas, execute o DatabaseSeeder primeiro:
```bash
php artisan db:seed --class=DatabaseSeeder
```

### 3. Executar o ProfessorSubjectsSeeder
```bash
php artisan db:seed --class=ProfessorSubjectsSeeder
```

Output esperado:
```
Carregando disciplinas para: Reginaldo Borba
✓ Matemática Aplicada (Código: GMAT001)
✓ Lógica Matemática (Código: GMAT002)
✓ Estatística e Probabilidade (Código: GMAT003)
✓ Linguagens de Programação (Código: GPROG001)
✓ Lógica de Programação (Código: GPROG002)
✓ Programação Orientada a Objeto (Código: GPROG003)
✓ Inteligência Artificial (Código: GSIA001)
✓ Cálculo (Código: GMAT004)
✓ 8 disciplinas associadas ao professor Reginaldo!
```

## Usando a Interface Web

1. Acesse **Planejamento > Professores**
2. Localize o professor Reginaldo
3. Clique no botão **📚** (livros) para gerenciar disciplinas
4. Marque/desmarque as disciplinas que o professor pode lecionar
5. Clique em **Salvar Disciplinas**

## Arquitetura

- **Tabela**: `professor_subject` (many-to-many pivot)
  - `professor_id` → fk para professors
  - `subject_id` → fk para subjects
  - `unique(['professor_id', 'subject_id'])` previne duplicatas

- **Relacionamentos**:
  - `Professor::subjects()` - belongsToMany(Subject)
  - `Subject::professors()` - belongsToMany(Professor)

- **Sincronização**:
  - `$professor->subjects()->sync($subjectIds)` - atualiza as disciplinas
  - Remove disciplinas não listadas
  - Adiciona novas disciplinas
  - Mantém as que já existem

## Próximas Melhorias (Opcional)

1. Adicionar pesquisa/filtro de disciplinas
2. Adicionar áreas de conhecimento (filtrar por área)
3. Permitir que professor auto-selecione suas disciplinas
4. Relatório de qualificação por disciplina
5. Sincronizar com sistema externo (ERP/TOTVS)
