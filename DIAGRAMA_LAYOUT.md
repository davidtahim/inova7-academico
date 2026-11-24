# 📐 Diagrama de Layout: Legenda Movida para Direita

## Estrutura HTML Antes (❌ Desorganizado)

```html
<div class="container">
  <div class="page-head">...</div>
  
  <div class="row g-3">
    <!-- Coluna 1: Tabela Grade (8/12) -->
    <div class="col-lg-8">
      <div class="card">
        <h5>Grade de Horários</h5>
        <table>...</table>
        
        <!-- ❌ PROBLEMA: Detalhamento DENTRO de col-lg-8 -->
        <div id="detalhamentoContainer">
          <div class="card">
            <table>...</table>
          </div>
        </div>
        
        <!-- ❌ PROBLEMA: Modal também dentro -->
        <div class="modal">...</div>
      </div>
    </div>
    
    <!-- Coluna 2: Legenda (4/12) -->
    <div class="col-lg-4">
      <div class="card">Legenda</div>
      <div class="card">Resumo</div>
      <div class="card">Informações</div>
    </div>
  </div>
  
  <!-- ❌ PROBLEMA: Duplicação - Legenda também estava abaixo -->
  <div class="card">
    <h5>Horários Detalhados</h5>
    <table>...</table>
  </div>
</div>
```

**Problemas:**
1. Legenda + Resumo aparecem abaixo (não à direita)
2. Detalhamento dentro de col-lg-8 (misturado com Grade)
3. Modal flutuante também dentro de col-lg-8
4. Duplicação de seções

---

## Estrutura HTML Depois (✅ Organizado)

```html
<div class="container">
  <div class="page-head">...</div>
  
  <!-- LINHA 1: Grade (esquerda) + Legenda (direita) -->
  <div class="row g-3">
    <!-- Coluna 1: Grade (8/12) -->
    <div class="col-lg-8">
      <div class="card">
        <h5>Grade de Horários</h5>
        <table>...</table>
        <!-- Grade apenas, sem nada mais -->
      </div>
    </div>
    
    <!-- Coluna 2: Legenda + Resumo + Informações (4/12) -->
    <div class="col-lg-4">
      <div class="card">Legenda</div>
      <div class="card">Resumo</div>
      <div class="card">Informações</div>
    </div>
  </div>
  
  <!-- LINHA 2: Detalhamento (100% fora do row, largura total) -->
  <div id="detalhamentoContainer">
    <div class="card">
      <h5>📋 Detalhamento de Horários</h5>
      <table>...</table>
    </div>
  </div>
  
  <!-- Modal (flutuante, fora da estrutura de grid) -->
  <div class="modal" id="editPreferenceModal">...</div>
  
  <!-- Ações (Voltar, Exportar) -->
  <div class="d-flex">...</div>
</div>
```

**Melhorias:**
1. ✅ Legenda à DIREITA da Grade (col-4)
2. ✅ Detalhamento separado (nova linha)
3. ✅ Modal flutuante, fora da grade
4. ✅ Sem duplicação
5. ✅ Hierarquia clara

---

## Visualização: Desktop (lg ≥ 992px)

### Antes (❌)
```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃                                                           ┃
┃  Disponibilidade de Horários                             ┃
┃  Professor: João da Silva        [Voltar]               ┃
┃                                                           ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃  Grade de Horários (Editável)        ┃
├─────────────┬────┬────┬────┬────┬────┤
│  Horário    │Seg │Ter │Qua │Qui │Sex │
├─────────────┼────┼────┼────┼────┼────┤
│ 07:30-08:20 │ [ ]│[✓] │[✓] │[✓] │[✓] │
│ 08:20-09:10 │ [ ]│[✓] │[✓] │[✓] │[✓] │
│ ...         │ ...│ ...│ ...│ ...│ ...│
└─────────────┴────┴────┴────┴────┴────┘

┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃  Legenda (ABAIXO - PROBLEMA!)        ┃
├─────────────────────────────────────┤
│  ✓ Preferido - Horário preferencial │
│  ◐ Disponível - Pode estar          │
│  ✗ Indisponível - Não pode estar    │
└─────────────────────────────────────┘

┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃  Resumo (AINDA MAIS ABAIXO!)         ┃
├─────────────────────────────────────┤
│  Total: 90                           │
│  Preferidos: 42                      │
│  Disponíveis: 20                     │
│  Indisponíveis: 28                   │
└─────────────────────────────────────┘

┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃  📋 Detalhamento de Horários (ABAIXO)┃
├─────────────┬────┬────┬────┬────┬────┤
│  Dia        │Horário│Pref│Status│Ações
│  Segunda    │07:30  │✓   │Registrado│...
│  Terça      │07:30  │✓   │Registrado│...
└─────────────┴────┴────┴────┴────┴────┘
```

### Depois (✅)
```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃                                                           ┃
┃  Disponibilidade de Horários                             ┃
┃  Professor: João da Silva        [Voltar]               ┃
┃                                                           ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┬━━━━━━━━━━━━━━━━━━━━━━━━┓
┃                                 ┃                        ┃
┃  Grade de Horários              ┃  Legenda              ┃
┃  (Editável - Clique para mudar) ┃                       ┃
┃                                 ┃  ✓ Preferido          ┃
┃  Horário  │Seg│Ter│Qua│Qui│Sex │  ◐ Disponível        ┃
┣───────────┼───┼───┼───┼───┼────┫  ✗ Indisponível      ┃
┃ 07:30 ... │[ ]│[✓]│[✓]│[✓]│[✓] ┃                       ┃
┃ 08:20 ... │[ ]│[✓]│[✓]│[✓]│[✓] ┃  Resumo              ┃
┃ 09:10 ... │[ ]│[◐]│[✓]│[◐]│[✓] ┃                       ┃
┃ 10:10 ... │[ ]│[◐]│[✓]│[◐]│[◐] ┃  Total: 90           ┃
┃ 11:00 ... │[ ]│[✓]│[✓]│[✓]│[✓] ┃  Preferidos: 42      ┃
┃ 11:50 ... │[ ]│[◐]│[✓]│[✓]│[✓] ┃  Disponíveis: 20     ┃
┃ ...       │...│...│...│...│... ┃  Indisponíveis: 28   ┃
┃ 22:00 ... │[ ]│[✓]│[ ]│[ ]│[ ] ┃                       ┃
┃                                 ┃  Informações         ┃
┃                                 ┃                       ┃
┃                                 ┃  Semestre: 2024/1    ┃
┃                                 ┃  Período: 15/01-30/06┃
┃                                 ┃                       ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┴━━━━━━━━━━━━━━━━━━━━━━━━┛

┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃  📋 Detalhamento de Horários (ABAIXO, LARGURA TOTAL)      ┃
┃  Horários salvos em tempo real - Clique para editar ou ex ┃
├──────────────────┬──────────────┬──────────────┬──────────┤
│  Dia da Semana   │ Horário      │ Preferência  │ Status   │
├──────────────────┼──────────────┼──────────────┼──────────┤
│  Segunda-feira   │ 07:30-08:20  │ Preferido    │ Registra │
│  Terça-feira     │ 07:30-08:20  │ Preferido    │ Registra │
│  Quarta-feira    │ 07:30-08:20  │ Disponível   │ Registra │
└──────────────────┴──────────────┴──────────────┴──────────┘

[Voltar]  [Exportar como PDF]
```

---

## Visualização: Mobile (<768px)

### Antes (❌)
```
┌─────────────────────────────────┐
│ Disponibilidade de Horários     │
│ Professor: João da Silva        │
│               [Voltar]          │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Grade de Horários               │
│ Horário│Seg│Ter│Qua│Qui│Sex   │
│ ──────┼───┼───┼───┼───┼────── │
│ 07:30 │[:]│[✓]│[✓]│[✓]│[✓]  │  ← Scroll
│ 08:20 │[:]│[✓]│[✓]│[✓]│[✓]  │  ← Horizontal
│ ...   │..│...│...│...│... │  ← necessário
│ 22:00 │[:]│[✓]│[ ]│[ ]│[ ]  │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Legenda (MUITO ESTREITA!)       │
│ ✓ Pref                          │
│ ◐ Disp                          │
│ ✗ Indi                          │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Resumo (TAMBÉM ESTREITO)        │
│ Total: 90                       │
│ Pref: 42 Disp: 20 Indi: 28     │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Detalhamento (SCROLL HORIZONTAL)│
│ Dia│Horário│Pref│Status│Ações  │
│ ──┼───────┼─────┼──────┼────── │
│ Se│07:30  │Pref │Regis │[e][x] │
└─────────────────────────────────┘
```

### Depois (✅)
```
┌─────────────────────────────────┐
│ Disponibilidade de Horários     │
│ Professor: João da Silva        │
│               [Voltar]          │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Grade de Horários               │
│ Horário│Seg│Ter│Qua│Qui│Sex  │
│ ──────┼───┼───┼───┼───┼────── │
│ 07:30 │[ ]│[✓]│[✓]│[✓]│[✓]  │  ← Scroll
│ 08:20 │[ ]│[✓]│[✓]│[✓]│[✓]  │  ← Horizontal
│ ...   │..│...│...│...│... │  ← necessário
│ 22:00 │[ ]│[✓]│[ ]│[ ]│[ ]  │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Legenda (100% LARGURA)          │
│ ✓ Preferido                     │
│   Horário preferencial          │
│                                 │
│ ◐ Disponível                    │
│   Pode estar                    │
│                                 │
│ ✗ Indisponível                  │
│   Não pode estar                │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Resumo (100% LARGURA)           │
│ Total de horários: 90           │
│ Preferidos: 42                  │
│ Disponíveis: 20                 │
│ Indisponíveis: 28               │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Informações (100% LARGURA)      │
│ Semestre: 2024/1                │
│ Período: 15/01-30/06            │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 📋 Detalhamento (100% LARGURA)  │
│ Dia│Hora│Pref│Status│Ações      │
│ ──┼─────┼─────┼──────┼────── │
│ Se│07:3│Pref │Regis │[e][x]   │
│    0 - │   │    │     │
│   08:2 │   │    │     │
│   0    │   │    │     │
│ Te│07:3│Pref │Regis │[e][x]   │
│   30 - │   │    │     │
│   08:2 │   │    │     │
│   0    │   │    │     │
└─────────────────────────────────┘

[Voltar]  [Exportar como PDF]
```

---

## Mapa de Colapsíveis

### Bootstrap Grid (12 colunas)

**Desktop (lg ≥ 992px):**
```
┌─ Grid 12 colunas
│
├─ [Linha 1]
│  ├─ [col-lg-8] = 66.67% → Grade de Horários
│  └─ [col-lg-4] = 33.33% → Legenda + Resumo + Informações
│
└─ [Linha 2] (fora do row)
   └─ [100%] → Detalhamento de Horários (full width)
```

**Mobile/Tablet (<992px):**
```
┌─ Grid 12 colunas (stack)
│
├─ [Linha 1]
│  ├─ [col-xs-12] = 100% → Grade de Horários
│  └─ [col-xs-12] = 100% → Legenda + Resumo + Informações (abaixo)
│
└─ [Linha 2] (fora do row)
   └─ [100%] → Detalhamento de Horários (full width)
```

---

## Código HTML - Estrutura Chave

```blade
<!-- Linha 1: Grade (8) + Legenda (4) -->
<div class="row g-3">
    <div class="col-lg-8">
        <!-- Grade de Horários -->
        <div class="card">
            <table class="availability-grid">...</table>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Legenda -->
        <div class="card">
            <div class="d-flex">...</div>
        </div>
        
        <!-- Resumo -->
        <div class="card">
            <div class="stat-row">...</div>
        </div>
        
        <!-- Informações -->
        <div class="card">...</div>
    </div>
</div>

<!-- Linha 2: Detalhamento (100%) -->
<div id="detalhamentoContainer">
    <div class="card">
        <table id="detalhamentoTable">...</table>
    </div>
</div>

<!-- Modal (flutuante) -->
<div class="modal" id="editPreferenceModal">...</div>
```

---

## Propriedades CSS Importantes

```css
/* Row com gap entre colunas */
.row.g-3 {
    --bs-gutter-x: 1.5rem; /* gap horizontal */
    --bs-gutter-y: 1.5rem; /* gap vertical */
}

/* Coluna da Grade: 8/12 (66.67%) */
.col-lg-8 {
    flex: 0 0 auto;
    width: 66.66666667%;
}

/* Coluna da Legenda: 4/12 (33.33%) */
.col-lg-4 {
    flex: 0 0 auto;
    width: 33.33333333%;
}

/* Responsivo: em <992px, ambas 100% */
@media (max-width: 991.98px) {
    .col-lg-8,
    .col-lg-4 {
        width: 100%;
    }
}

/* Detalhamento container: margem superior */
#detalhamentoContainer {
    margin-top: 30px;
}
```

---

## Resumo Visual

| Elemento | Antes | Depois |
|----------|-------|--------|
| **Grade** | 100% (col-12) | 66.67% (col-lg-8) |
| **Legenda** | Abaixo (100%) | Direita (col-lg-4) |
| **Resumo** | Abaixo da Legenda | Dentro da col-lg-4 (abaixo da Legenda) |
| **Detalhamento** | Dentro de col-lg-8 (misturado) | Fora (linha 2, 100%) |
| **Modal** | Dentro de col-lg-8 | Flutuante (overlay) |
| **Responsivo mobile** | Grade 100%, Legenda estreita 100% | Grade 100%, Legenda 100% (stack) |

---

## ✅ Validação Final

- [x] Legenda à direita (col-lg-4)
- [x] Grade à esquerda (col-lg-8)
- [x] Detalhamento abaixo (100%)
- [x] Layout responsivo (mobile stack)
- [x] Nenhuma mudança em funcionalidade
- [x] Sem duplicação de seções
- [x] Hierarquia visual clara

