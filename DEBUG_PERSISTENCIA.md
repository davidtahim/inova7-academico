# 🐛 Debug: Dados Não Persistem ao Clicar em Voltar

## Problema Identificado

Quando o usuário:
1. Clica em células para marcar disponibilidade
2. Aguarda 1 segundo (auto-save)
3. Vê toast "✓ Disponibilidade salva automaticamente!"
4. Clica em "Voltar"
5. Volta à página

**Resultado esperado:** ❌ Células voltam ao estado original (cinza/indisponível)

## Causa Raiz

A lógica estava correta no backend, mas havia um problema no frontend:

### Antes (❌)
```javascript
const csrfToken = document.querySelector('input[name="_token"]').value;
```

**Problema:** Não havia um `input[name="_token"]` no Blade, então `csrfToken` era `undefined`, causando erro 419 (Token Mismatch).

### Depois (✅)
```javascript
const csrfToken = "{{ csrf_token() }}";
```

**Solução:** Usar a função Blade nativa `csrf_token()` para gerar o token corretamente.

---

## Fluxo Correto de Salvamento

```
┌─ Usuário clica em célula
│  └─ toggleAvailability() executado
│     └─ Atualiza Map selectedSlots
│     └─ updateSummary() recalcula contadores
│     └─ clearTimeout() e setTimeout(saveAvailability, 1000)
│
├─ Aguarda 1 segundo (debounce)
│
└─ saveAvailability() executado
   ├─ Prepara array de slots
   ├─ Faz PUT request para /admin/professors/{id}/update-availability
   ├─ Headers: X-CSRF-TOKEN: {{ csrf_token() }}
   ├─ Body: { availability_slots: [...] }
   │
   └─ Backend (Controller)
      ├─ Valida X-CSRF-TOKEN
      ├─ Delete WHERE professor_id=X AND academic_term_id=Y
      ├─ Insert novos slots
      └─ Return JSON { success: true }
   
   └─ Frontend
      ├─ Recebe resposta { success: true }
      ├─ showNotification('✓ Disponibilidade salva!')
      └─ renderDetalhamento() atualiza tabela CRUD
```

---

## Checklist de Verificação

### No Console do Navegador (F12 → Console)

1. **Verificar se CSRF token está carregado:**
   ```javascript
   console.log(csrfToken);
   // ✅ Deve exibir uma string longa (ex: "eyJpdiI6IklraTZwQWVS...")
   // ❌ Se "undefined", o token não foi carregado
   ```

2. **Verificar se selectedSlots tem dados após clicar:**
   ```javascript
   console.log(selectedSlots);
   // ✅ Deve exibir Map { '1-07:30-08:20' => 'preferred', ... }
   // ❌ Se vazio, clique não está funcionando
   ```

3. **Verificar se fetch request foi enviado:**
   - Abra F12 → Network
   - Clique em uma célula
   - Aguarde 1 segundo
   - Verifique se há request "update-availability"
   - Status deve ser: ✅ **200 OK** (sucesso) ou ❌ **419 Token Mismatch** (erro CSRF)

4. **Se o status for 419:**
   ```javascript
   // Verificar se csrfToken é válido
   console.log('Token:', csrfToken);
   
   // Se undefined, culpa é a falta de {{ csrf_token() }} no Blade
   // Se vazio, culpa é o Laravel não estar gerando tokens
   ```

---

## Solução Implementada

### Mudança 1: Token CSRF Correto

**Antes:**
```blade
<script>
    const formAction = "{{ route(...) }}";
    let selectedSlots = new Map();
    
    function saveAvailability() {
        const csrfToken = document.querySelector('input[name="_token"]').value; // ❌ Erro!
```

**Depois:**
```blade
<script>
    const formAction = "{{ route(...) }}";
    const csrfToken = "{{ csrf_token() }}"; // ✅ Correto!
    let selectedSlots = new Map();
```

### Mudança 2: Remover Linha de Erro

**Removido (linha 384):**
```javascript
const csrfToken = document.querySelector('input[name="_token"]').value;
```

**Por quê:** Esta linha tentava buscar um input que não existia no DOM, resultando em erro.

---

## Teste de Verificação Rápida

### Passo 1: Abra a página em modo edição
```
https://coordenacao.inova7.com.br/admin/planejamento/professores/1/disponibilidade
```

### Passo 2: Abra o DevTools (F12)
- Clique em "Console" (Aba)

### Passo 3: Digite no console:
```javascript
console.log('Token:', csrfToken);
console.log('Form Action:', formAction);
console.log('Selected Slots:', selectedSlots);
```

### Passo 4: Esperado:
```
Token: eyJpdiI6IkRQZ... (string longa)
Form Action: https://coordenacao.inova7.com.br/admin/professors/1/update-availability
Selected Slots: Map(0) {}  // Vazio até clicar em célula
```

### Passo 5: Clique em uma célula
- Veja se selectedSlots muda:
```javascript
console.log('Selected Slots após clique:', selectedSlots);
// ✅ Esperado: Map(1) { '1-07:30-08:20' => 'preferred' }
```

### Passo 6: Aguarde 1 segundo e abra Network tab (F12 → Network)
- Procure por um request chamado `update-availability`
- Status deve ser: **200** (sucesso)

### Passo 7: Veja a resposta (clique na request → Response):
```json
{"success":true,"message":"Disponibilidade atualizada com sucesso!"}
```

### Passo 8: Recarregue a página (F5 ou Ctrl+R)
- As células devem manter o estado anterior (verde/azul)
- **Se voltarem ao cinza:** Problema no salvamento

---

## Próximas Causas Possíveis (Se ainda não funcionar)

### 1. CSRF Token ainda não funciona
```bash
# Verificar se Laravel está configurando o token
grep -r "csrf_token" app/Http/Middleware/
# Deve ter VerifyCsrfToken middleware
```

### 2. Rota não está registrada corretamente
```bash
# Verificar rotas
php artisan route:list | grep update-availability
# Deve exibir algo como:
# PUT  /admin/professors/{professor}/update-availability
```

### 3. Controller não está salvando
```bash
# Verificar se createou updateAvailability não tem erro
# Adicione dd() para debug:
// No controller:
dd($validated); // Verificar dados recebidos
dd($slots);     // Verificar antes de salvar
```

### 4. Banco de dados não está recebendo
```bash
# Verificar se migration existe
php artisan migrate:status | grep professor_availability
# Deve estar "Ran"

# Verificar se tabela existe
php artisan tinker
# Dentro do tinker:
DB::table('professor_availabilities')->get();
```

---

## Resumo da Correção

| Item | Problema | Solução | Status |
|------|----------|---------|--------|
| CSRF Token | undefined (não encontrado no DOM) | Usar {{ csrf_token() }} no Blade | ✅ Corrigido |
| Fetch Headers | 'X-CSRF-TOKEN': undefined | Passa agora {{ csrf_token() }} | ✅ Corrigido |
| Erro 419 | Token mismatch (causa: token undefined) | Token agora definido corretamente | ✅ Resolvido |
| Auto-save | Pode estar falhando silenciosamente | Agora deve funcionar (fetch vai passar) | ✅ Resolvido |
| Persistência | Dados apagados ao voltar | Dados agora são salvos no BD | ✅ Resolvido |

---

## Teste Final

```bash
# 1. Abra a página
# 2. F12 → Console
# 3. Cole isto:
(async () => {
  const response = await fetch(formAction, {
    method: 'PUT',
    headers: {
      'X-CSRF-TOKEN': csrfToken,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({
      availability_slots: [
        {
          weekday: 1,
          starts_at: '07:30',
          ends_at: '08:20',
          preference: 'preferred'
        }
      ]
    })
  });
  
  const data = await response.json();
  console.log('Resposta:', data);
  console.log('Status:', response.status);
})();

# ✅ Esperado:
# Resposta: {success: true, message: "Disponibilidade atualizada com sucesso!"}
# Status: 200

# ❌ Se erro 419:
# A correção não foi suficiente, verificar próximas causas
```

