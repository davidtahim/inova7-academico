# 🎓 Guia: Preenchimento de Disponibilidade para Professor

**Data**: 2026-11-24  
**Status**: ✅ FUNCIONALIDADE COMPLETA

---

## 📋 Fluxo Completo do Professor

### 1. Login
```
Email: 560127711@prof.uni7.edu.br
Senha: Abc123def
```

### 2. Redirecionamento Automático
```
Sistema detecta: professor_id = 7
Redireciona para: /admin/planejamento/professores/7/disponibilidade
```

### 3. Tela de Disponibilidade
Professor vê uma **grade de horários** com:
- **Dias da semana**: Seg, Ter, Qua, Qui, Sex, Sáb
- **Horários**: 15 slots de aula (07:30 às 22:00)
- **Estados**: Preferido, Disponível, Indisponível

---

## 🖱️ Como Preencher Disponibilidade

### Passo 1: Entender os Estados

| Estado | Cor | Significado |
|--------|-----|-------------|
| **✓ Preferido** | 🟢 Verde | Horário em que gostaria de lecionar |
| **◐ Disponível** | 🔵 Azul | Pode lecionar se necessário |
| **✗ Indisponível** | ⚪ Cinza | Não pode lecionar |

### Passo 2: Clicar nos Horários

1. **Clique no horário** que deseja marcar
2. **Ciclo automático**:
   - 1º clique: ⚪ Cinza → 🟢 Verde (Preferido)
   - 2º clique: 🟢 Verde → 🔵 Azul (Disponível)
   - 3º clique: 🔵 Azul → ⚪ Cinza (Indisponível)
   - E repete...

### Passo 3: Salva Automaticamente

- ✅ Depois de **1 segundo de inatividade**, salva automaticamente
- ✅ Sem necessidade de botão "Salvar"
- ✅ Sem necessidade de confirmar

### Exemplo Prático

```
Quero lecionar sempre de SEGUNDA A SEXTA, 07:30-08:20
Mas também posso estar disponível em outras horas se necessário

SEGUNDA:
  07:30-08:20 → Clique → 🟢 Preferido ✓
  08:20-09:10 → Clique → 🔵 Disponível ✓
  09:10-10:00 → Clique → 🔵 Disponível ✓
  ... (repete para outros dias)

SÁBADO:
  Deixar todos em Cinza (Indisponível)
```

---

## 📊 Resumo em Tempo Real

Na **sidebar direita**, professor vê:

```
📊 Resumo
├─ Total de horários: 90
├─ Preferidos: 25 ✓
├─ Disponíveis: 35 ◐
└─ Indisponíveis: 30 ✗
```

Atualiza **em tempo real** conforme clica.

---

## 📋 Detalhamento de Horários

Depois de salvar, o sistema exibe um **detalhamento de todos os horários** marcados:

```
📋 Detalhamento de Horários

| Dia | Horário | Preferência | Ação |
|-----|---------|-------------|------|
| SEG | 07:30 - 08:20 | Preferido | ✏️ Editar |
| SEG | 08:20 - 09:10 | Disponível | 🗑️ Excluir |
| TER | 07:30 - 08:20 | Preferido | ✏️ Editar |
| ... | ... | ... | ... |
```

---

## 🎯 Funcionalidades Disponíveis

### ✅ Editar Horário
1. Clique no horário na tabela
2. Escolha nova preferência (Preferido ou Disponível)
3. Clique "Salvar"

### ✅ Excluir Horário
1. Clique no X na tabela
2. Horário volta para Indisponível

### ✅ Exportar como PDF
- Botão "📄 Exportar como PDF" (se não estiver editando)
- Gera documento com sua disponibilidade

---

## 🔒 Considerações Importantes

### ✓ Acesso Seguro
- Professor só vê **sua própria** disponibilidade
- Admin e Coordenador podem ver qualquer professor
- Sem risco de interferência

### ✓ Salvamento Automático
- Não precisa se preocupar em salvar
- Alterna automaticamente após 1 segundo
- Se houver erro, recebe notificação

### ✓ Semestres
- Disponibilidade é **por semestre**
- Pode ter diferentes disponibilidades para cada semestre
- Selector de semestre no topo

---

## 📱 Interface Completa

```
┌─────────────────────────────────────────────┐
│ ← Voltar                                    │
│                                             │
│ Disponibilidade de Horários                │
│ Professor: Edson Brito Junior              │
│ Semestre: 2027.1                           │
│ ✏️ Clique nos horários para selecionar    │
└─────────────────────────────────────────────┘

┌──────────────────────────────┐  ┌──────────────┐
│                              │  │ 📊 Resumo    │
│  GRADE DE HORÁRIOS (90 slots)│  ├──────────────┤
│                              │  │ Preferidos: 5│
│  Seg  Ter  Qua  Qui  Sex Sáb │  │ Disponíveis:10│
│  🟢   🟢   🟢   🟢   🟢   ⚪   │  │ Indisponíveis:75│
│  08:20-09:10                 │  └──────────────┘
│  🔵   🔵   🔵   🔵   🔵   ⚪   │
│  09:10-10:00                 │  ℹ️ Semestre
│  ...                         │  2027.1
│                              │  01/01 a 30/06
└──────────────────────────────┘
```

---

## 🆘 Troubleshooting

### "Não consigo clicar nos horários"
- ✅ Verifique se está em modo de edição
- ✅ Veja mensagem "Editável - Clique para mudar" no cabeçalho

### "Minha disponibilidade não está salvando"
- ✅ Aguarde 1 segundo após clicar
- ✅ Verifique console (F12) para erros
- ✅ Recarregue a página

### "Não consigo exportar PDF"
- ✅ PDF está disponível apenas em modo **visualização**
- ✅ Em modo edição, não há botão de PDF

---

## 📚 Próximos Passos

1. ✅ Preencher disponibilidade
2. ⏳ Coordenador usa sua disponibilidade para alocar turmas
3. ⏳ Vê sua grade horária gerada
4. ⏳ Recebe notificações de mudanças

---

## 🔗 Rotas Importantes

| Funcionalidade | URL |
|---|---|
| Disponibilidade | `/admin/planejamento/professores/{id}/disponibilidade` |
| Disciplinas | `/admin/planejamento/professores/{id}/disciplinas` |
| Exportar PDF | `/perfil/disponibilidade/pdf` |

---

**Status**: 🟢 **PRONTO PARA USO**

Professor pode fazer login e começar a preencher sua disponibilidade imediatamente! 🚀
