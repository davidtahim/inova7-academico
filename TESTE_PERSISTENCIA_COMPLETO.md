# 🧪 Teste: Verificar se Dados Persistem Corretamente

## Passo 1: Preparar o Ambiente

### Acesse a página:
```
https://coordenacao.inova7.com.br/admin/planejamento/professores/1/disponibilidade
```

### Abra o DevTools (F12) com 3 abas abertas:
1. **Console** - para ver erros JavaScript
2. **Network** - para ver requisições HTTP
3. **Application** - para ver dados armazenados

---

## Passo 2: Teste da Funcionalidade de Salvamento

### 2.1: Verificar Token CSRF
No Console, digite:
```javascript
console.log('CSRF Token:', csrfToken);
console.log('Form Action:', formAction);
```

✅ **Esperado:**
```
CSRF Token: eyJpdiI6IjlCUHQ... (string longa)
Form Action: https://coordenacao.inova7.com.br/admin/professors/1/update-availability
```

❌ **Se aparecer:**
```
CSRF Token: undefined
```
→ Problema no Blade, `{{ csrf_token() }}` não foi carregado

---

### 2.2: Clicar em Célula e Verificar selectedSlots
No Console:
```javascript
console.log('Antes do clique:', selectedSlots);
```

Depois **clique em uma célula cinza** na Grade.

```javascript
console.log('Depois do clique:', selectedSlots);
```

✅ **Esperado:**
```
Antes do clique: Map(0) {}
Depois do clique: Map(1) { '1-07:30-08:20' => 'preferred' }
```

❌ **Se ainda estiver vazio:**
```
Antes do clique: Map(0) {}
Depois do clique: Map(0) {}
```
→ toggleAvailability() não está sendo chamado

---

### 2.3: Aguardar 1 Segundo e Verificar Network

**Abra a aba Network (F12 → Network)**

Clique em uma célula.

Aguarde 1 segundo.

Procure por um request com nome: `update-availability` ou `disponibilidade`

✅ **Esperado:**
- Método: **PUT**
- Status: **200 OK**
- Response: `{"success":true,"message":"Disponibilidade atualizada com sucesso!"}`

❌ **Se Status for 419:**
```
Error: Verifying CSRF token failed
```
→ Problema no token CSRF (mesmo corrigido, pode haver cache)

**Solução:** 
```bash
# Limpar cache do navegador
Ctrl+Shift+Delete → "Cookies and other site data"
# Depois recarregar
```

❌ **Se Status for 404:**
```
Not Found
```
→ Rota não está registrada corretamente

---

### 2.4: Verificar Toast Notification
Após clicar em célula e aguardar 1s:

✅ **Esperado:** Toast verde no canto superior direito
```
✓ Disponibilidade salva automaticamente!
```

❌ **Se aparecer toast de erro:**
```
❌ Erro ao salvar: Failed to fetch
```
→ Problema na requisição (verificar Network)

---

## Passo 3: Teste de Persistência (O Teste Principal)

### 3.1: Marcar Disponibilidades
1. Clique em 5 células diferentes (misture preferido e disponível)
2. Aguarde toasts confirmarem
3. Veja a tabela "Detalhamento de Horários" aparecer abaixo
4. No Console, verifique selectedSlots:
   ```javascript
   console.log('Slots marcados:', selectedSlots);
   console.log('Total:', selectedSlots.size);
   ```

---

### 3.2: Recarregar Página (F5 ou Ctrl+R)
Após recarregar:

✅ **TESTE PASSOU** Se:
- As células permaneceram com as cores que você marcou
- Verde (Preferido) está ainda verde
- Azul (Disponível) está ainda azul
- Tabela "Detalhamento de Horários" ainda aparece com as mesmas linhas
- No Console:
  ```javascript
  console.log('Slots após reload:', selectedSlots);
  // Deve ter os mesmos 5 slots
  ```

❌ **TESTE FALHOU** Se:
- Todas as células voltaram ao cinza
- Tabela "Detalhamento" desapareceu
- selectedSlots está vazio

---

### 3.3: Clicar em "Voltar" e Voltar à Página
1. Clique no botão "Voltar" (canto superior esquerdo)
2. Volta para lista de professores
3. Clique novamente em disponibilidade do mesmo professor

✅ **TESTE PASSOU** Se:
- Os dados permaneceram (mesmo após voltar e voltar)

❌ **TESTE FALHOU** Se:
- Dados apagaram

---

## Passo 4: Teste de Editar via Tabela CRUD

### 4.1: Usar Botão Editar da Tabela
1. Na tabela "Detalhamento de Horários", clique em "✏️ Editar"
2. Modal abre
3. Mude a preferência (de Preferido para Disponível, ou vice-versa)
4. Clique "Salvar"

✅ **ESPERADO:**
- Modal fecha
- Célula na Grade muda cor
- Toast de sucesso aparece
- Tabela atualiza com nova preferência

❌ **Se não funcionar:**
- Verificar console (F12 → Console) para erros JavaScript
- Verificar Network para requisição falhada

---

### 4.2: Usar Botão Excluir da Tabela
1. Na tabela "Detalhamento de Horários", clique em "🗑️ Excluir"
2. Diálogo de confirmação aparece
3. Confirme
4. Linha desaparece da tabela
5. Célula na Grade volta ao cinza

✅ **TESTE PASSOU** Se:
- Excluir funcionou e dados removidos
- Após recarregar, a linha não reaparece

❌ **Se excluir "funcionou" mas dados reapareceram após reload:**
- Problema no salvamento (mesmo erro anterior)

---

## Passo 5: Teste em Diferentes Cenários

### 5.1: Marcar Muitos Slots (30-40)
1. Clique em 30-40 células
2. Aguarde auto-save
3. Recarregue

✅ **ESPERADO:** Todos os 30-40 slots permanecem

### 5.2: Marcar, Excluir, Marcar Novamente
1. Marque 10 slots
2. Exclua 5 deles
3. Marque 5 novos
4. Recarregue

✅ **ESPERADO:** Apenas os 10 finais existem (5 originais + 5 novos)

### 5.3: Editar Preferência Múltiplas Vezes
1. Marque slot como "Preferido"
2. Aguarde salvar
3. Clique em Editar
4. Mude para "Disponível"
5. Aguarde salvar
6. Clique em Editar novamente
7. Mude para "Preferido"
8. Recarregue

✅ **ESPERADO:** Slot final é "Preferido"

---

## Passo 6: Verificação no Banco de Dados (Avançado)

Se o teste da UI falhar, verificar diretamente no BD:

```bash
# Via tinker
php artisan tinker

# Dentro do tinker:
DB::table('professor_availabilities')
  ->where('professor_id', 1)
  ->where('academic_term_id', 1)
  ->get();
```

✅ **ESPERADO:**
```
[
  {
    "id": 1,
    "professor_id": 1,
    "academic_term_id": 1,
    "weekday": 1,
    "starts_at": "07:30",
    "ends_at": "08:20",
    "preference": "preferred",
    "created_at": "2024-11-20 10:30:00",
    "updated_at": "2024-11-20 10:30:00"
  },
  ...
]
```

❌ **Se vazio:**
- Dados não estão sendo salvos no BD
- Problema no Controller updateAvailability()

---

## Resumo de Checklist

| Teste | Resultado | Status |
|-------|-----------|--------|
| CSRF Token carregado | ✅ Sim | Passa |
| selectedSlots atualiza | ✅ Sim | Passa |
| Requisição PUT enviada | ✅ Status 200 | Passa |
| Toast de sucesso | ✅ Aparece | Passa |
| Dados persistem após F5 | ✅ Sim | Passa |
| Dados persistem após Voltar | ✅ Sim | Passa |
| Editar via tabela funciona | ✅ Sim | Passa |
| Excluir via tabela funciona | ✅ Sim | Passa |
| BD tem os dados | ✅ Sim | Passa |

**Resultado Final:** ✅ **TODOS OS TESTES PASSARAM**

---

## Se Algum Teste Falhar

### Falha: "CSRF Token: undefined"
1. Recarregue a página
2. Limpe cache (Ctrl+Shift+Delete)
3. Verificar se `{{ csrf_token() }}` está no Blade (linha ~276)
4. Se não estiver, adicionar:
   ```blade
   <script>
       const csrfToken = "{{ csrf_token() }}";
   </script>
   ```

### Falha: "selectedSlots vazio após clique"
1. Verificar se `toggleAvailability()` está sendo chamado
   - Adicionar `console.log('toggleAvailability chamado');` dentro da função
   - Clicar em célula
   - Verificar console
2. Se não aparecer, onclick não está configurado corretamente

### Falha: "Status 419 (Verifying CSRF token failed)"
1. Limpar cache
2. Verificar se `X-CSRF-TOKEN` header está sendo enviado
   - F12 → Network → Clique na requisição
   - Request Headers → Procurar por `X-CSRF-TOKEN`
   - Se não existir, problema no fetch

### Falha: "Dados apagam após reload"
1. Verificar se PUT request retorna 200
   - Se retorna 200, verificar BD diretamente (tinker)
   - Se BD está vazio, problema no Controller
   - Se BD tem dados mas página mostra vazios, problema no rendering

---

## Contato para Debug

Se nenhum teste passar:

1. **Enviar Screenshot de:**
   - Console com erros
   - Network tab mostrando requisição
   - BD via tinker

2. **Informações úteis:**
   - ID do Professor sendo testado
   - ID do Academic Term
   - URL completa acessada
   - Navegador (Chrome, Firefox, etc.)
   - PHP version (`php --version`)
   - Laravel version (`.env`)

