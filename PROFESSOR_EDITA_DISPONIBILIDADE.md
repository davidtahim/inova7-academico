# 📅 Professor Edita Sua Disponibilidade - Nova Funcionalidade

## 🎯 O Que Mudou?

Antes: Apenas admin/coordenador/staff podiam ver e editar disponibilidade
Agora: **Professor também pode editar sua própria disponibilidade!**

---

## 👥 Quem Pode Fazer O Quê?

### 🧑‍🎓 Professor (Novo!)
- ✅ Vê sua própria disponibilidade
- ✅ **Edita** sua própria disponibilidade
- ❌ Não vê a disponibilidade de outros professores

### 👨‍💼 Coordenador (Mantido)
- ✅ Vê disponibilidade de **todos** os professores
- ✅ **Edita** disponibilidade de **todos** os professores
- ✅ Pode atualizar durante o semestre

### 👨‍💻 Admin (Mantido)
- ✅ Vê disponibilidade de **todos** os professores
- ✅ **Edita** disponibilidade de **todos** os professores
- ✅ Acesso irrestrito

### 👨‍💼 Staff (Mantido)
- ✅ Vê disponibilidade de **todos** os professores
- ✅ **Edita** disponibilidade de **todos** os professores

---

## 🔧 Implementação Técnica

### Mudanças no Controller
```php
// ANTES: checkAccess() - Apenas admin/coord/staff
private function checkAccess()
{
    if (!Auth::check() || !in_array(Auth::user()->role, ['coordinator', 'staff', 'admin'])) {
        abort(403);
    }
}

// DEPOIS: checkAccess(Professor) - Professor vê a sua
private function checkAccess(Professor $professor)
{
    $isAdmin = in_array(Auth::user()->role, ['coordinator', 'staff', 'admin']);
    $isOwnProfessor = Auth::user()->role === 'teacher' && 
                      Auth::user()->professor_id === $professor->id;
    
    if (!$isAdmin && !$isOwnProfessor) {
        abort(403);
    }
}
```

### Novo Método: updateAvailability()
```php
public function updateAvailability(Request $request, Professor $professor)
{
    // Valida acesso
    // Valida dados
    // Remove disponibilidades antigas
    // Adiciona novas disponibilidades
    // Redireciona com sucesso
}
```

### Nova Rota
```php
Route::put('/professores/{professor}/disponibilidade', 
    'ProfessorAvailabilityController@updateAvailability')
    ->name('professors.update-availability');
```

### Modelos Atualizados
```php
// User.php
public function professor()
{
    return $this->belongsTo(Professor::class);
}

// Professor.php
public function user()
{
    return $this->hasOne(User::class);
}
```

---

## 📋 Fluxo de Uso

### Professor Editando Sua Disponibilidade

```
1. Professor faz login
   ↓
2. Vai para: Perfil → Minha Disponibilidade
   (ou similar, conforme sua estrutura)
   ↓
3. Vê sua própria grade de horários
   ↓
4. Clica nos horários para marcar preferência:
   - Verde = Prefiro (preferred)
   - Azul = Disponível (available)
   - Cinza = Indisponível (unavailable)
   ↓
5. Clica "Salvar Minha Disponibilidade"
   ↓
6. ✓ Sucesso! Suas preferências foram salvas
```

### Coordenador Editando Disponibilidade de Todos

```
1. Coordenador faz login
   ↓
2. Vai para: Planejamento → Professores
   ↓
3. Clica no botão 📅 de um professor
   ↓
4. Vê a grade de horários do professor
   ↓
5. Ajusta conforme necessário
   ↓
6. Clica "Salvar Disponibilidade"
   ↓
7. ✓ Sucesso! Disponibilidade atualizada
```

---

## 🔐 Segurança

✅ Professor só edita a sua própria
✅ Coordenador edita de qualquer um
✅ Validação de professor_id
✅ Validação de dados (weekday 1-6, horas válidas)
✅ Autenticação obrigatória

---

## 📊 Exemplo de Dados

### User (Professor)
```php
[
    'id' => 1,
    'name' => 'João Silva',
    'email' => 'joao@uni.br',
    'role' => 'teacher',
    'professor_id' => 5,  // ← Vinculado ao Professor
    'is_active' => true
]
```

### Professor
```php
[
    'id' => 5,
    'name' => 'João Silva',
    'registration' => 'UNI123',
    'email' => 'joao@uni.br',
    'active' => true
]
```

### ProfessorAvailability
```php
[
    'professor_id' => 5,
    'academic_term_id' => 1,
    'weekday' => 2,              // Terça
    'starts_at' => '07:30',
    'ends_at' => '08:20',
    'preference' => 'preferred'  // Verde
]
```

---

## 🚀 Como Usar (Para Professor)

### Passo 1: Acessar o Painel
1. Login como professor (role = 'teacher')
2. Vá para: **Perfil → Minha Disponibilidade**
   ou
   **Menu → Disponibilidade**

### Passo 2: Ver Sua Grade
Você verá uma tabela com:
- Dias da semana (segunda a sábado)
- Horários de aula
- Cores indicando sua preferência

### Passo 3: Marcar Seus Horários
Clique nos horários para mudar:
- 🟩 Verde (Prefiro) = Horário preferido
- 🟦 Azul (Disponível) = Posso lecionar
- ⬜ Cinza (Indisponível) = Não posso

### Passo 4: Salvar
Clique no botão **"Salvar Minha Disponibilidade"**

✓ Pronto! Suas preferências foram registradas!

---

## 🚀 Como Usar (Para Coordenador)

Mesmo fluxo anterior, mas acesso a TODOS os professores:

```
Planejamento → Professores → 📅 (qualquer professor)
```

Coordenador pode editar a disponibilidade de qualquer um!

---

## 🔄 Fluxo de Dados (Backend)

```
Request: PUT /admin/planejamento/professores/{professor}/disponibilidade
  ↓
ProfessorAvailabilityController@updateAvailability()
  ↓
checkAccess($professor) ← Verifica permissão
  ↓
canEditAvailability($professor) ← Professor vê a sua?
  ↓
Valida: subject_ids[], prefências, horários
  ↓
DELETE ProfessorAvailability WHERE professor_id = X
  ↓
INSERT novas ProfessorAvailability
  ↓
Redireciona com sucesso ✓
```

---

## 📌 Configuração Necessária

### 1. Adicionar Coluna ao Banco (Se Necessário)
```sql
ALTER TABLE users ADD COLUMN professor_id BIGINT UNSIGNED NULL;
ALTER TABLE users ADD FOREIGN KEY (professor_id) REFERENCES professors(id);
```

### 2. Atualizar Usuários Existentes
Se você já tem professores no sistema:

```php
// No tinker:
$professores = App\Models\Professor::all();
foreach ($professores as $prof) {
    $user = App\Models\User::where('email', $prof->email)->first();
    if ($user && $user->role === 'teacher') {
        $user->professor_id = $prof->id;
        $user->save();
    }
}
```

### 3. Criar Novos Usuários de Professores
Ao criar um novo professor via web:
- Crie primeiro o User com role = 'teacher'
- Crie o Professor
- Vincule: User::professor_id = Professor::id

---

## ✨ Próximos Passos (Opcional)

1. **Criar Menu para Professor**
   - Adicionar link no menu do professor
   - Ir para: `route('admin.professors.availability', auth()->user()->professor->id)`

2. **Notificações**
   - Notificar coordenador quando professor atualizar
   - Notificar professor quando coordenador alterar sua disponibilidade

3. **Histórico**
   - Registrar alterações (audit log)
   - Ver quem alterou quando

4. **Relatórios**
   - Relatório de horários disponíveis
   - Comparar preferência vs alocação real

---

## 🧪 Teste a Funcionalidade

### 1. Preparar Dados (SSH)
```bash
php artisan tinker

# Criar um usuário professor vinculado
$prof = App\Models\Professor::find(1);
$user = App\Models\User::create([
    'name' => 'Professor Teste',
    'email' => 'prof@test.br',
    'password' => bcrypt('senha123'),
    'role' => 'teacher',
    'professor_id' => $prof->id,
    'is_active' => true
]);

exit;
```

### 2. Testar Acesso Professor
1. Logout
2. Login como professor (email: prof@test.br)
3. Acessar: `/admin/planejamento/professores/{prof-id}/disponibilidade`
4. Deve ver e poder editar ✓

### 3. Testar Acesso Coordenador
1. Logout
2. Login como coordenador
3. Acessar: `Planejamento → Professores → 📅`
4. Deve ver e editar de QUALQUER professor ✓

---

## 📄 Documentação Incluída

Este arquivo: **PROFESSOR_EDITA_DISPONIBILIDADE.md**

Próximas:
- Atualizar COMO_USAR_DISCIPLINAS.md para incluir professores
- Atualizar QUICK_START.md com novo fluxo

---

## ❓ Dúvidas Frequentes

**P: Professor consegue ver a disponibilidade de outro professor?**
R: Não. Apenas a sua própria. Coordenador vê todos.

**P: Se professor editar, coordenador consegue modificar?**
R: Sim. Coordenador sempre pode editar qualquer um.

**P: Posso remover professor do acesso?**
R: Sim. No controller, remova 'teacher' de `canEditAvailability()`.

**P: Preciso migrar dados?**
R: Se já tem professores, vincule Users aos Professors (ver SQL acima).

---

## 🎯 Status

- ✅ Controller atualizado
- ✅ Rotas adicionadas
- ✅ Modelos atualizados (User + Professor)
- ✅ Lógica de acesso implementada
- ⏳ View precisa ser atualizada (ver próxima seção)

---

## 👀 Próxima Etapa

**Atualizar a View:** `professors-availability.blade.php`

A view precisa de:
1. Formulário com inputs escondidos (para slots)
2. Botão "Salvar" que submete via PUT
3. Verificação de `$isEditable` para mostrar botão
4. Se não editável, apenas mostrar (read-only)

Ver: **ATUALIZAR_VIEW_DISPONIBILIDADE.md** (próximo documento)

---

**Status:** ✅ Backend Implementado
**Data:** 2024
**Próximo:** Atualizar View + Testar
