# 🐛 Debug: Clique Não Muda a Disponibilidade

## ❌ Problema
Ao clicar em uma célula da Grade de Horários, nada acontece. A célula não muda de cor.

## 🔧 Diagnóstico

### Passo 1: Abra o DevTools (F12)
- Vá para aba **Console**

### Passo 2: Verifique o log inicial
Você deve ver na console:
```
Script de edição carregado. isEditable: true
selectedSlots inicializados: Map(0) {}
```

✅ **Se aparecer**: Script está carregando corretamente
❌ **Se NÃO aparecer**: 
- Recarregue a página (F5)
- Verifique se você está em modo de edição (procure por "Editável")
- Se a página diz "(Visualização)" em vez de "(Editável)", você não tem permissão para editar

### Passo 3: Clique em uma célula e verifique o console
Após clicar em uma célula cinza, a console deve exibir:
```
toggleAvailability chamado {weekday: "1", start: "07:30", end: "08:20", ...}
Status atual: unavailable
Novo status: preferred
```

✅ **Se aparecer**: Função está sendo chamada
❌ **Se NÃO aparecer**: Clique não está registrando

---

## 🔍 Causas Possíveis

### Causa 1: Você Não tem Permissão para Editar
**Sintomas:**
- Página mostra "(Visualização)" em vez de "(Editável)"
- Células têm `cursor: default` em vez de `cursor: pointer`
- Console não mostra "isEditable: true"

**Solução:**
- Verificar se você é o professor ou coordenador
- Se for professor, certificar de que está vendo SUA própria disponibilidade
- Se for coordenador, verificar se tem permissão

### Causa 2: JavaScript Não Está Carregando
**Sintomas:**
- Console vazia (nenhum log)
- Erro "ReferenceError: toggleAvailability is not defined"

**Solução:**
```bash
# Verifique no console:
typeof toggleAvailability
# ✅ Esperado: "function"
# ❌ Se "undefined": Não carregou
```

**Se não carregou:**
1. Recarregue F5
2. Limpe cache (Ctrl+Shift+Delete)
3. Verifique se há erro no console (Aba Console)
4. Procure por erro como "SyntaxError" ou "Uncaught"

### Causa 3: onclick Não Está Configurado
**Sintomas:**
- Clique registra mas sem console.log
- Ou console vazia

**Debug:**
```bash
# No console, clique em célula e digite:
document.querySelector('.availability-cell').onclick
# ✅ Esperado: function... (mostra função onclick)
# ❌ Se "null": onclick não está configurado
```

### Causa 4: Célula Não Tem Dados Corretos
**Sintomas:**
- Console mostra "toggleAvailability chamado" mas sem dados
- Dataset vazio: `{}`

**Debug:**
```bash
# No console:
document.querySelector('.availability-cell').dataset
# ✅ Esperado: { cellId: "slot-1-07:30-08:20", weekday: "1", start: "07:30", end: "08:20", status: "unavailable" }
# ❌ Se vazio ou parcial: Problema no Blade
```

---

## ✅ Teste Passo a Passo

### 1. Verificar Permissão
```
Abra a página e procure pelo texto:
"Clique nos horários para selecionar sua disponibilidade"
ou
"Editável - Clique para mudar"

✅ Se encontrar: Tem permissão
❌ Se não encontrar: Sem permissão
```

### 2. Verificar Console Logging
```
F12 → Console → Cole isto:
console.log('Script ativo');
// Deve exibir: Script ativo
```

### 3. Clique em Célula
```
Clique em uma célula cinza e procure no console:
toggleAvailability chamado
Status atual: unavailable
Novo status: preferred

✅ Se aparecer: Clique funciona (pode não renderizar visualmente)
❌ Se não aparecer: onclick não está funcionando
```

### 4. Verifique Renderização
```
Mesmo com logs corretos, célula não muda cor?
Verificar CSS:
F12 → Elements → Clique na célula → Inspect

Procure por:
class="availability-cell bg-success text-white"

✅ Se encontrar: CSS está aplicado
❌ Se não: Problema no JavaScript (className não está sendo atualizado)
```

---

## 🛠️ Soluções Rápidas

### Se console.log aparece mas célula não muda:

```javascript
// No console, teste manualmente:
const cell = document.querySelector('.availability-cell');
cell.className = 'availability-cell bg-success text-white';
cell.innerHTML = '<small>✓ Preferido</small>';

// Se célula mudar:
// ✅ CSS/DOM estão OK, problema no JavaScript

// Se célula NÃO mudar:
// ❌ Problema no Bootstrap CSS ou no Blade
```

### Se nothing happens:

```javascript
// Teste a função diretamente:
// Pegue uma célula:
const testCell = document.querySelector('.availability-cell');
console.log('Célula encontrada:', testCell);

// Chame manualmente:
toggleAvailability(testCell);

// Verificar console para logs
```

---

## 🔧 Se Nenhuma Solução Funcionar

Faça isso e envie o output:

```bash
# No console (F12 → Console), Cole isto tudo de uma vez:

console.log('=== DIAGNÓSTICO ===');
console.log('1. isEditable:', typeof toggleAvailability !== 'undefined');
console.log('2. Células encontradas:', document.querySelectorAll('.availability-cell').length);
console.log('3. Primeira célula:', document.querySelector('.availability-cell'));
console.log('4. onclick:', document.querySelector('.availability-cell')?.onclick);
console.log('5. Dados:', document.querySelector('.availability-cell')?.dataset);
console.log('6. URL:', window.location.href);
```

Envie o resultado completo para debug.

---

## 📋 Checklist Final

- [ ] Console mostra "Script de edição carregado"
- [ ] Console mostra "isEditable: true"
- [ ] Página mostra "(Editável)" não "(Visualização)"
- [ ] Clique causa "toggleAvailability chamado" no console
- [ ] Console mostra "Status atual: unavailable"
- [ ] Console mostra "Novo status: preferred"
- [ ] Célula muda de cinza para verde
- [ ] Toast "✓ Disponibilidade salva!" aparece após 1s
- [ ] F5 (reload) mantém a cor verde

Se TODOS estiverem OK: ✅ **FUNCIONA CORRETAMENTE!**

Se ALGUM estiver ❌: Enviar screenshot do console para debug

