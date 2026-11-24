# 🔍 Debug: Dados não aparecem ao recarregar

## Problema Relatado

> "Os dados não aparecem quando eu recarrego a página"

Tabela "Detalhamento de Horários" não renderiza ao recarregar a página, mesmo que os dados estejam salvos no banco.

---

## Causa Provável

A função `renderDetalhamento()` é chamada na inicialização, mas existem 2 cenários possíveis:

1. **Cenário A**: `selectedSlots` não está sendo inicializado corretamente do DOM
2. **Cenário B**: `selectedSlots.size === 0` mesmo com dados no DOM
3. **Cenário C**: A tabela está renderizando mas o container está oculto

---

## Teste de Debug

**Abra F12 → Console** e procure pelos seguintes logs:

```
✓ Script de edição carregado. isEditable: true
✓ selectedSlots inicializados: Map(X) {...}
✓ selectedSlots.size: X (onde X > 0)
✓ Chamando renderDetalhamento() na inicialização...
✓ renderDetalhamento() completa
```

---

## Se os logs NÃO aparecerem:

### Log 1: "Script de edição carregado"
- **Significa**: Script não carregou
- **Causas possíveis**:
  - Sem permissão (`$isEditable = false`)
  - JavaScript desabilitado
  - Erro de sintaxe antes deste log
- **Solução**: Verificar se `isEditable: true` ou `isEditable: false` aparece

### Log 2: "selectedSlots inicializados"
- **Significa**: DOM não foi parseado quando script rodou
- **Causas possíveis**:
  - Script está no `<head>` (antes do HTML)
  - Erro ao buscar `.availability-cell`
- **Solução**: Usar `DOMContentLoaded` event

### Log 3: "selectedSlots.size"
- **Significa**: Sem dados selecionados
- **Causas possíveis**:
  - Dados não salvos no BD
  - `data-status` não está sendo setado corretamente
  - Nenhum `data-status != 'unavailable'`
- **Solução**: Verificar se células têm `data-status="preferred"` ou `data-status="available"`

---

## Logs Esperados no Console

```
Script de edição carregado. isEditable: true
selectedSlots inicializados: Map(42) {
  '2-07:30-08:20' => 'preferred',
  '2-08:20-09:10' => 'preferred',
  '3-07:30-08:20' => 'preferred',
  ...
}
selectedSlots.size: 42
Chamando renderDetalhamento() na inicialização...
renderDetalhamento() chamada
selectedSlots.size: 42
selectedSlots: Map(42) {...}
renderDetalhamento() completo
```

---

## Se aparecer "Nenhum slot selecionado":

Significa que `selectedSlots.size === 0`, ou seja, nenhum horário foi marcado como "preferred" ou "available" no HTML.

**Causas:**
1. Dados não estão sendo carregados do Backend
2. Controller não está passando corretamente os dados para a View
3. HTML não está renderizando corretamente os `data-status`

**Verificar:**
```javascript
// No console, rode:
document.querySelectorAll('.availability-cell').forEach(cell => {
    console.log('Cell:', cell.dataset.weekday, cell.dataset.start, cell.dataset.status);
});
```

Deve mostrar algo como:
```
Cell: 2 07:30 preferred
Cell: 2 08:20 preferred
Cell: 2 09:10 preferred
...
```

---

## Próximas Ações

**Teste agora:**
1. F5 (recarregar)
2. F12 → Console
3. Procure pelos logs acima
4. Me reporte o que você vê:
   - ✅ Qual log aparece?
   - ❌ Qual log NÃO aparece?
   - 🔍 Qual é o valor de `selectedSlots.size`?

---

## Código Adicionado para Debug

```javascript
console.log('Script de edição carregado. isEditable:', true);
...
console.log('selectedSlots inicializados:', selectedSlots);
console.log('selectedSlots.size:', selectedSlots.size);

console.log('Chamando renderDetalhamento() na inicialização...');
renderDetalhamento();
console.log('renderDetalhamento() completo');

function renderDetalhamento() {
    console.log('renderDetalhamento() chamada');
    const container = document.getElementById('detalhamentoContainer');
    const tbody = document.getElementById('detalhamentoContent');
    
    console.log('selectedSlots.size:', selectedSlots.size);
    console.log('selectedSlots:', selectedSlots);
    
    if (selectedSlots.size === 0) {
        console.log('Nenhum slot selecionado, escondendo container');
        container.style.display = 'none';
        return;
    }
    // ... resto do código
}
```

---

## Resumo

| Item | Status |
|------|--------|
| Clique funciona | ✅ Sim |
| Auto-save funciona | ✅ Sim |
| Dados salvam no BD | ✅ Sim (não testado neste reload) |
| **Dados aparecem ao recarregar** | ❓ **Precisa debug** |

**Me envie os logs do Console para diagnosticar!** 🔍

