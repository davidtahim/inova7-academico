# 🔧 Resolvendo o Erro "Failed to fetch"

## ⚠️ O Erro

```
❌ Erro ao salvar: Failed to fetch
```

## ✅ Solução Aplicada

Já atualizei o arquivo `professors-availability.blade.php` com:

1. ✅ Melhor tratamento de erros
2. ✅ Logs detalhados no console
3. ✅ Validação de resposta
4. ✅ Headers corretos (Accept: application/json)
5. ✅ Variável formAction explícita
6. ✅ Melhor mensagem de erro

---

## 🧪 Como Debugar

### Passo 1: Abrir Console do Navegador

```
Pressione: F12 (ou Cmd+Option+I no Mac)
Vá para: Aba "Console"
```

### Passo 2: Tentar Salvar Novamente

```
1. Clique em alguns horários
2. Clique em "💾 Salvar Disponibilidade"
3. Olhe para o console
```

### Passo 3: Procure Por

**Se aparecer algo como:**
```
Status: 405
Erro do servidor: ...
```

**Significa:** Problema na rota

**Se aparecer:**
```
Status: 403
Erro do servidor: ...
```

**Significa:** Problema de permissão

**Se aparecer:**
```
Status: 500
Erro do servidor: ...
```

**Significa:** Erro no servidor (veja logs)

---

## 🔍 Verificações

### 1. Rota Está Registrada?

```bash
# SSH para o servidor
php artisan route:list | grep disponibilidade
```

Deve aparecer algo como:
```
PUT   /admin/planejamento/professores/{professor}/disponibilidade
```

### 2. CSRF Token Existe?

Abra o console (F12) e execute:
```javascript
console.log(document.querySelector('input[name="_token"]').value);
```

Deve aparecer um token longo (como: `abc123def456...`)

### 3. Rota Está No Grupo Correto?

Verifique em `routes/web.php`:
```php
Route::prefix('admin/planejamento')->name('admin.')->group(function () {
    // ...
    Route::put('/professores/{professor}/disponibilidade', [
        ProfessorAvailabilityController::class, 
        'updateAvailability'
    ])->name('professors.update-availability');
});
```

### 4. Controller Existe e Está Correto?

Arquivo: `app/Http/Controllers/ProfessorAvailabilityController.php`

Método: `updateAvailability()`

Deve estar em volta da linha 50-90

---

## 🛠️ Problemas Comuns e Soluções

### Problema 1: "Failed to fetch" + Status 404

**Causa:** Rota não existe ou está registrada errada

**Solução:**
```bash
php artisan route:clear
php artisan cache:clear
php artisan route:cache
```

Depois recarregue a página.

### Problema 2: "Failed to fetch" + Status 405

**Causa:** Método HTTP errado (GET em vez de PUT)

**Solução:** Verifique se a rota tem `Route::put()`

```php
// CORRETO
Route::put('/professores/{professor}/disponibilidade', [
    ProfessorAvailabilityController::class, 
    'updateAvailability'
])->name('professors.update-availability');

// ERRADO (GET em vez de PUT)
Route::get('/professores/{professor}/disponibilidade', [
    // ...
]);
```

### Problema 3: "Failed to fetch" + Status 403

**Causa:** Sem permissão

**Solução:** Verifique:
- Sua role é 'teacher'?
  ```bash
  php artisan tinker
  App\Models\User::where('id', 1)->pluck('role');
  exit;
  ```

- Você está vinculado ao Professor?
  ```bash
  php artisan tinker
  App\Models\User::where('id', 1)->pluck('professor_id');
  exit;
  ```

### Problema 4: "Failed to fetch" + Status 500

**Causa:** Erro no servidor

**Solução:** Verifique logs:
```bash
# No servidor
tail -f storage/logs/laravel.log

# Volte ao navegador e tente salvar de novo
# O erro vai aparecer no log
```

---

## ✅ Teste Passo-a-Passo

### 1. Verifique o Banco

```bash
php artisan tinker

# Seu usuário existe?
App\Models\User::where('id', 1)->get();

# Você é professor?
$user = App\Models\User::find(1);
$user->role;  # Deve retornar 'teacher'

# Você está vinculado?
$user->professor_id;  # Deve retornar um número

# O professor existe?
App\Models\Professor::find($user->professor_id)->get();

exit;
```

### 2. Abra o Console (F12)

### 3. Clique em Um Horário

Veja se a célula muda de cor.

Se SIM → continuamos

Se NÃO → Há erro JavaScript. Verifique o console.

### 4. Clique Em "Salvar"

No console, procure por:
- **Status:** Deve ser 200
- **Resposta:** Deve ter `"success": true`

### 5. Se Falhar

Copie o erro completo do console e compartilhe.

---

## 📝 Console Output Esperado

**Quando clica em "Salvar":**

```
Status: 200
Headers: ...
Resposta: {success: true, message: "Disponibilidade atualizada com sucesso!"}
(Preto) Página vai recarregar...
```

**Se der erro:**

```
Status: 403
Erro do servidor: Acesso negado. Você não tem permissão...
❌ Erro ao salvar: HTTP error! status: 403...
```

---

## 🔐 Checklist de Segurança

```bash
php artisan tinker

$user = App\Models\User::find(1);

# É professor?
$user->role === 'teacher';  # true

# Está vinculado?
$user->professor_id > 0;  # true

# Professor existe?
$user->professor()->exists();  # true

# Tabela academic_term existe?
App\Models\AcademicTerm::count() > 0;  # true

exit;
```

Se qualquer coisa for FALSE:
- Veja "Problemas Comuns" acima
- Execute o setup necessário

---

## 📞 Se Continuar Falhando

1. **Abra o console (F12)**
2. **Execute:**
   ```javascript
   console.clear();
   document.querySelector('.btn-success').click();
   ```

3. **Copie TODO o texto que aparecer no console**
4. **Compartilhe comigo**

Preciso ver:
- Status HTTP
- Error message
- Request details
- Response body

---

## 🎯 Checklist Final

- [ ] Coluna `professor_id` existe em `users`?
- [ ] Usuário está vinculado ao Professor?
- [ ] Role do usuário é 'teacher'?
- [ ] AcademicTerm existe?
- [ ] Rota está registrada (PUT)?
- [ ] Controller método existe?
- [ ] Console mostra logs corretos?

Se tudo ✅, deve funcionar!

---

## ✨ Resumo

A versão **ATUALIZADA** do arquivo agora:

✅ Captura erros detalhados
✅ Mostra no console quais são
✅ Alerta com mensagem clara
✅ Permite diagnosticar o problema

**Próximo passo:** Teste e compartilhe o erro do console comigo!

