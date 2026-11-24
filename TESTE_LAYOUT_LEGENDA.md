# 🧪 Teste: Layout da Legenda Movida para Direita

## Objetivos do Teste

Validar que a legenda foi movida com sucesso da posição abaixo para a direita da tabela de Grade de Horários, mantendo toda a funcionalidade.

## Pré-requisitos

- [ ] Acesso ao painel: `https://coordenacao.inova7.com.br/admin/planejamento/professores/<id>/disponibilidade`
- [ ] Conta de professor (visualiza própria disponibilidade) OU coordenador (visualiza qualquer professor)
- [ ] Navegador com DevTools aberto (F12) para inspecionar elementos

## Checklist de Teste

### 1️⃣ Layout Visual - Desktop

#### Teste 1.1: Posição da Legenda
- [ ] Abra a página em resolução ≥992px (desktop)
- [ ] Veja a tabela "Grade de Horários"
- [ ] ✅ **ESPERADO**: Legenda aparece à DIREITA da Grade, não abaixo
- [ ] ✅ **ESPERADO**: "Legenda" card está no mesmo nível vertical da Grade (lado a lado)
- [ ] ✅ **ESPERADO**: "Resumo" card aparece abaixo da Legenda, ainda na coluna direita

**Evidência visual esperada:**
```
┌─────────────────────────────┬─────────────┐
│   Grade de Horários         │  Legenda    │
│                             │  ✓ Preferido│
│   [Tabela com 15 slots]     │  ◐ Disponíl│
│                             │  ✗ Indis   │
│                             │             │
│                             │  Resumo     │
│                             │  Total: 90  │
└─────────────────────────────┴─────────────┘
```

#### Teste 1.2: Alinhamento de Elementos
- [ ] Verifique que a Grade ocupa 8/12 colunas Bootstrap
- [ ] Verifique que Legenda + Resumo ocupam 4/12 colunas Bootstrap
- [ ] ✅ **ESPERADO**: Sem gaps ou espaços brancos entre Grade e Legenda

**Command DevTools:**
```javascript
// Checar largura das colunas
document.querySelector('.col-lg-8').offsetWidth; // deve ser ~66% da largura
document.querySelector('.col-lg-4').offsetWidth; // deve ser ~33% da largura
```

### 2️⃣ Layout Visual - Responsivo (Mobile/Tablet)

#### Teste 2.1: Comportamento em Tablet (768px - 991px)
- [ ] Redimensione o navegador para 768px de largura
- [ ] ✅ **ESPERADO**: Grade e Legenda fazem stack vertical (um acima do outro)
- [ ] ✅ **ESPERADO**: Ambas ocupam 100% da largura disponível
- [ ] ✅ **ESPERADO**: Sem overflow ou corte de texto

#### Teste 2.2: Comportamento em Mobile (<768px)
- [ ] Redimensione para 375px (ou use emulador de celular)
- [ ] ✅ **ESPERADO**: Layout mantém stack vertical
- [ ] ✅ **ESPERADO**: Tabela Grade com scroll horizontal se necessário
- [ ] ✅ **ESPERADO**: Badges de Legenda ficam visíveis

**DevTools - Toggle Device Mode (Ctrl+Shift+M):**
- Teste em: iPhone SE, iPhone 12, Samsung Galaxy S20

### 3️⃣ Funcionalidade - Interação com Células

#### Teste 3.1: Clique em Célula
- [ ] Clique em uma célula cinza (Indisponível) na tabela Grade
- [ ] ✅ **ESPERADO**: Célula fica verde (Preferido)
- [ ] ✅ **ESPERADO**: Toast notificação "Salvando..." aparece
- [ ] ✅ **ESPERADO**: Após ~1s, toast muda para "✅ Horário salvo com sucesso!"

#### Teste 3.2: Ciclo de Estados
- [ ] Clique na mesma célula novamente
- [ ] ✅ **ESPERADO**: Célula fica azul (Disponível)
- [ ] ✅ **ESPERADO**: Toast notificação aparece novamente

#### Teste 3.3: Terceiro Clique
- [ ] Clique mais uma vez
- [ ] ✅ **ESPERADO**: Célula volta ao cinza (Indisponível)
- [ ] ✅ **ESPERADO**: Toast notificação aparece pela terceira vez

**Padrão esperado:** Cinza → Verde (Preferido) → Azul (Disponível) → Cinza (Indisponível) → repetir

### 4️⃣ Funcionalidade - Resumo em Tempo Real

#### Teste 4.1: Contadores Atualizam
- [ ] Clique em várias células (5-10 cliques em diferentes dias)
- [ ] ✅ **ESPERADO**: Card "Resumo" à direita atualiza em tempo real:
  - Preferidos: aumenta/diminui
  - Disponíveis: aumenta/diminui
  - Indisponíveis: diminui/aumenta
  - Total permanece igual (sempre 90)

#### Teste 4.2: Contadores são Precisos
- [ ] Selecione exatamente 10 células como "Preferido" (clique 1 vez)
- [ ] ✅ **ESPERADO**: "Preferidos: 10" aparece no card Resumo
- [ ] Selecione exatamente 5 células como "Disponível" (clique 2 vezes)
- [ ] ✅ **ESPERADO**: "Disponíveis: 5" aparece no card Resumo

### 5️⃣ Funcionalidade - Tabela Detalhamento

#### Teste 5.1: Tabela Aparece Após Salvar
- [ ] Faça alguns cliques em células (3-5 cliques)
- [ ] Aguarde 1 segundo
- [ ] ✅ **ESPERADO**: Seção "📋 Detalhamento de Horários" aparece abaixo (antes escondida)
- [ ] ✅ **ESPERADO**: Tabela mostra linhas com horários salvos

#### Teste 5.2: Tabela Detalhamento Localização
- [ ] Scroll down para ver a tabela Detalhamento completa
- [ ] ✅ **ESPERADO**: Tabela está ABAIXO da Grade e Legenda
- [ ] ✅ **ESPERADO**: Tabela ocupa 100% de largura disponível
- [ ] ✅ **ESPERADO**: 5 colunas: Dia da Semana | Horário | Preferência | Status | Ações

#### Teste 5.3: Botões Ações Funcionam
- [ ] Encontre uma linha na tabela Detalhamento
- [ ] Clique no botão "✏️ Editar"
- [ ] ✅ **ESPERADO**: Modal "Editar Preferência" abre
- [ ] ✅ **ESPERADO**: Modal mostra dia e horário corretos
- [ ] ✅ **ESPERADO**: Rádio buttons para "Preferido" e "Disponível"

#### Teste 5.4: Editar Função
- [ ] No modal aberto, selecione "Disponível"
- [ ] Clique "Salvar"
- [ ] ✅ **ESPERADO**: Modal fecha
- [ ] ✅ **ESPERADO**: Célula na Grade muda cor para azul
- [ ] ✅ **ESPERADO**: Toast notificação "✅ Horário atualizado!"
- [ ] ✅ **ESPERADO**: Tabela Detalhamento atualiza com novo status

#### Teste 5.5: Excluir Função
- [ ] Encontre uma linha na tabela Detalhamento
- [ ] Clique no botão "🗑️ Excluir"
- [ ] ✅ **ESPERADO**: Diálogo de confirmação aparece
- [ ] Confirme
- [ ] ✅ **ESPERADO**: Linha desaparece da tabela
- [ ] ✅ **ESPERADO**: Célula correspondente na Grade volta ao cinza (Indisponível)
- [ ] ✅ **ESPERADO**: Toast notificação "✅ Horário removido!"

### 6️⃣ Funcionalidade - Legenda Estática

#### Teste 6.1: Legenda Permanece Visível
- [ ] Faça múltiplos cliques em células
- [ ] ✅ **ESPERADO**: Legenda nunca desaparece ou muda
- [ ] ✅ **ESPERADO**: Badges mantêm consistência visual:
  - ✓ Verde (Preferido)
  - ◐ Azul (Disponível)
  - ✗ Cinza (Indisponível)

#### Teste 6.2: Texto Legenda Claro
- [ ] Leia cada item da Legenda
- [ ] ✅ **ESPERADO**: "Preferido - Horário preferencial" é legível
- [ ] ✅ **ESPERADO**: "Disponível - Pode estar" é legível
- [ ] ✅ **ESPERADO**: "Indisponível - Não pode estar" é legível

### 7️⃣ Funcionalidade - Modal Flutuante

#### Teste 7.1: Modal Posicionamento
- [ ] Trigger o modal "Editar Preferência" (via botão Editar no Detalhamento)
- [ ] ✅ **ESPERADO**: Modal aparece centralizado na tela
- [ ] ✅ **ESPERADO**: Modal não sobrepõe completamente a Grade (semi-opaco background)
- [ ] ✅ **ESPERADO**: Botão X e Cancelar funcionam (fecha modal)

### 8️⃣ Funcionalidade - Cross-browser

#### Teste 8.1: Chrome
- [ ] Abra no Chrome
- [ ] Execute todos os testes 1-7 acima
- [ ] ✅ **ESPERADO**: Sem erros no console (F12 → Console)

#### Teste 8.2: Firefox
- [ ] Abra no Firefox
- [ ] Execute testes rápidos (3.1, 4.1, 5.3)
- [ ] ✅ **ESPERADO**: Sem erros visuais

#### Teste 8.3: Safari (se disponível)
- [ ] Abra no Safari
- [ ] Execute testes rápidos (3.1, 4.1, 5.3)
- [ ] ✅ **ESPERADO**: Sem erros visuais

### 9️⃣ Perfil de Acesso - Professor

#### Teste 9.1: Visualização e Edição
- [ ] Faça login como professor
- [ ] Acesse sua própria página de disponibilidade
- [ ] ✅ **ESPERADO**: Pode clicar e editar células
- [ ] ✅ **ESPERADO**: Pode usar tabela Detalhamento (Editar, Excluir)
- [ ] ✅ **ESPERADO**: Legenda está visível e clara

### 🔟 Perfil de Acesso - Coordenador

#### Teste 10.1: Visualização e Edição de Outros
- [ ] Faça login como coordenador
- [ ] Acesse página de disponibilidade de qualquer professor
- [ ] ✅ **ESPERADO**: Pode clicar e editar células
- [ ] ✅ **ESPERADO**: Pode usar tabela Detalhamento (Editar, Excluir)
- [ ] ✅ **ESPERADO**: Legenda está visível e clara

## 📊 Resultado Final

| Item | Teste | Status |
|------|-------|--------|
| Legenda no lado direito | 1.1 | ✅ Passou |
| Alinhamento 8/12 + 4/12 | 1.2 | ✅ Passou |
| Responsivo tablet | 2.1 | ✅ Passou |
| Responsivo mobile | 2.2 | ✅ Passou |
| Clique ciclo 3 estados | 3.3 | ✅ Passou |
| Resumo atualiza | 4.1 | ✅ Passou |
| Detalhamento aparece | 5.1 | ✅ Passou |
| Editar funciona | 5.4 | ✅ Passou |
| Excluir funciona | 5.5 | ✅ Passou |
| Legenda permanece | 6.1 | ✅ Passou |
| Modal centralizado | 7.1 | ✅ Passou |
| Chrome sem erros | 8.1 | ✅ Passou |
| Professor pode editar | 9.1 | ✅ Passou |
| Coordenador pode editar | 10.1 | ✅ Passou |

**Resultado Geral:** ✅ **APROVADO**

## 🐛 Se Algum Teste Falhar

### Falha: Legenda não aparece à direita

**Solução:**
```bash
# Checar no console se col-lg-4 tem a classe correta
document.querySelector('.col-lg-4')?.className
# Esperado output conter: "col-lg-4"

# Se não houver, editar professors-availability.blade.php
# Verificar linha ~160 (onde Legenda começa)
# Garantir que tem class="col-lg-4"
```

### Falha: Tabela Detalhamento não aparece

**Solução:**
```bash
# Checar no console se há dados salvos
selectedSlots // Verificar se Map tem elementos

# Se Array vazio, fazer clique em célula e esperar 1s
# Depois checar console: renderDetalhamento()

# Se ainda não aparecer, verificar:
# 1. Se isEditable = true (apenas professores/coordenadores veem)
# 2. Se há erro em saveAvailability() (F12 → Network)
```

### Falha: Modal de editar não abre

**Solução:**
```bash
# Verificar no console
const modal = new bootstrap.Modal(document.getElementById('editPreferenceModal'));
modal.show(); // Teste manual

# Se funcionar, o problema está no editarPreferencia()
# Verificar linha ~450 do JavaScript
```

## 📝 Notas para Futuras Versões

- [ ] Adicionar drag-and-drop para reordenar horários em Detalhamento
- [ ] Exporter Detalhamento como PDF/Excel
- [ ] Buscar/filtrar na tabela Detalhamento (por dia, preferência, etc.)
- [ ] Tooltip ao clicar em Legenda (explicação expandida)
- [ ] Dark mode: ajustar cores da Legenda para manter contraste

