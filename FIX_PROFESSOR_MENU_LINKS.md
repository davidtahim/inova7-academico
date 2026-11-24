# 🔧 FIX: Corrigir Links de Menu do Professor

**Problema**: Link "Minha disponibilidade" redireciona para `/perfil/editar#disponibilidade` em vez de `/admin/planejamento/professores/{id}/disponibilidade`

**Data**: 2026-11-24

---

## ✅ Solução Aplicada

### Arquivo Modificado
`resources/views/layouts/app.blade.php` (Linha ~92-103)

### Antes
```blade
@if (Auth::user()->role === 'teacher')
    <details class="nav-section-group" open>
        <summary class="nav-section">
            <span>Professor</span>
            <span class="nav-caret">▾</span>
        </summary>
        <a class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}"
            href="{{ route('profile.edit') }}#disciplinas">◌ Minhas disciplinas</a>
        <a class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}"
            href="{{ route('profile.edit') }}#disponibilidade">◔ Minha disponibilidade</a>
    </details>
@endif
```

### Depois
```blade
@if (Auth::user()->role === 'teacher')
    <details class="nav-section-group" open>
        <summary class="nav-section">
            <span>Professor</span>
            <span class="nav-caret">▾</span>
        </summary>
        @if (Auth::user()->professor_id && Auth::user()->professor)
            <a class="nav-link {{ request()->routeIs('admin.professors.subjects') ? 'active' : '' }}"
                href="{{ route('admin.professors.subjects', Auth::user()->professor) }}">◌ Minhas disciplinas</a>
            <a class="nav-link {{ request()->routeIs('admin.professors.availability') ? 'active' : '' }}"
                href="{{ route('admin.professors.availability', Auth::user()->professor) }}">◔ Minha disponibilidade</a>
        @else
            <a class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}"
                href="{{ route('profile.edit') }}#disciplinas">◌ Minhas disciplinas</a>
            <a class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}"
                href="{{ route('profile.edit') }}#disponibilidade">◔ Minha disponibilidade</a>
        @endif
    </details>
@endif
```

---

## 🔄 Fluxo Corrigido

### Antes do Fix
```
1. Professor faz login
   ↓
2. LoginController redireciona para disponibilidade ✓
   → /admin/planejamento/professores/7/disponibilidade
   ↓
3. Professor clica "Minha disponibilidade" no menu
   ↓
4. Layout redireciona para /perfil/editar#disponibilidade ✗
   ↓
5. UserController carrega perfil em vez de disponibilidade ✗
```

### Depois do Fix
```
1. Professor faz login
   ↓
2. LoginController redireciona para disponibilidade ✓
   → /admin/planejamento/professores/7/disponibilidade
   ↓
3. Professor clica "Minha disponibilidade" no menu
   ↓
4. Layout redireciona para disponibilidade (correto) ✓
   → /admin/planejamento/professores/7/disponibilidade
   ↓
5. ProfessorAvailabilityController renderiza grid ✓
```

---

## 🧪 Teste

### Passos
1. Faça login como professor (email: `560127711@prof.uni7.edu.br`)
2. Sistema redireciona para `/admin/planejamento/professores/7/disponibilidade`
3. Clique no menu "Minha disponibilidade" (lado esquerdo)
4. Verificar URL na barra de endereço

### Resultado Esperado
```
✅ URL: /admin/planejamento/professores/7/disponibilidade
✅ Grid de horários carrega normalmente
✅ Consegue editar disponibilidade
✅ Dados salvam corretamente
```

### Resultado Anterior (Bug)
```
❌ URL: /perfil/editar#disponibilidade
❌ Grid de horários NÃO aparece
❌ Redireciona novamente ou mostra formulário de perfil
```

---

## 🎯 Lógica do Fix

### Verificação Condicional
```blade
@if (Auth::user()->professor_id && Auth::user()->professor)
    <!-- Redirecionar para rota correta -->
@else
    <!-- Fallback para perfil (caso profile esteja configurado) -->
@endif
```

### Condições
- ✅ Se professor_id EXISTE e relacionamento CARREGA
  - Use rota: `admin.professors.subjects` / `admin.professors.availability`
  
- ⚠️ Se professor_id NÃO existe ou relacionamento É NULO
  - Use rota antiga: `profile.edit#disciplinas` / `profile.edit#disponibilidade`
  - Permite que professor complete seu perfil

---

## 📝 Resumo das Mudanças

| Aspecto | Antes | Depois |
|--------|-------|--------|
| Link "Minhas disciplinas" | `/perfil/editar#disciplinas` | `/admin/planejamento/professores/{id}/disciplinas` |
| Link "Minha disponibilidade" | `/perfil/editar#disponibilidade` | `/admin/planejamento/professores/{id}/disponibilidade` |
| Validação | Nenhuma | Verifica `professor_id` e relacionamento |
| Fallback | Não | Sim, se professor não estiver vinculado |
| Active State | Comparação com rota `profile.edit` | Comparação com rota específica `admin.professors.*` |

---

## ✅ Validação

### Local (seu computador)
1. ✅ Modificação aplicada em `app.blade.php`
2. ✅ Professor faz login → redireciona corretamente
3. ✅ Clica "Minha disponibilidade" → vai para grid
4. ✅ Clica "Minhas disciplinas" → vai para disciplinas

### Produção
Após fazer deploy:
1. ✅ Mesmo teste que local
2. ✅ Verificar logs: `tail -f storage/logs/laravel.log`
3. ✅ Sem erros de redirecionamento

---

## 🚀 Deploy

### Passo 1: Git Commit Local
```bash
git add resources/views/layouts/app.blade.php
git commit -m "fix: corrigir links de menu do professor para rota correta de disponibilidade"
```

### Passo 2: Push para Produção
```bash
git push origin main
# Ou conforme seu workflow de deploy
```

### Passo 3: Clear Cache (Pode ser Necessário)
```bash
# No servidor em produção
php artisan view:clear
php artisan config:clear
php artisan cache:clear
```

---

## 🔗 Rotas Envolvidas

| Rota | Controller | View | Função |
|------|-----------|------|--------|
| `admin.professors.availability` | `ProfessorAvailabilityController@show` | `professors-availability.blade.php` | Grid de disponibilidade |
| `admin.professors.subjects` | `ProfessorAvailabilityController@showSubjects` | `professors-subjects.blade.php` | Seleção de disciplinas |
| `profile.edit` | `UserController@editProfile` | `profile-edit.blade.php` | Perfil do usuário (fallback) |

---

## ⚙️ Configuração de Routing

### Routes
```php
// Em routes/web.php
Route::middleware(['web', 'auth'])->group(function () {
    // Rota de disponibilidade do professor
    Route::get('/admin/planejamento/professores/{professor}/disponibilidade', 
        [ProfessorAvailabilityController::class, 'show'])
        ->name('admin.professors.availability');
    
    // Rota de disciplinas do professor
    Route::get('/admin/planejamento/professores/{professor}/disciplinas', 
        [ProfessorAvailabilityController::class, 'showSubjects'])
        ->name('admin.professors.subjects');
});
```

---

## 🎓 Resultado Final

**Fluxo do Professor Corrigido**:
```
1. Login → Disponibilidade (correto)
2. Menu "Minha disponibilidade" → Disponibilidade (correto)
3. Menu "Minhas disciplinas" → Disciplinas (correto)
4. Ambas as páginas carregam sem erro
5. Dados salvam e persistem
```

---

**Status**: ✅ **CORRIGIDO**

O link agora redireciona para a rota correta! 🚀
