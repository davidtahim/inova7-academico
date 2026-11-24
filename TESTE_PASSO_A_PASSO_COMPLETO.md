# 🎬 Teste Passo a Passo - Clique → Salva → Renderiza

## 📸 Pré-requisitos

- ✅ Navegador com DevTools (F12)
- ✅ Acesso à: https://coordenacao.inova7.com.br/admin/planejamento/professores/1/disponibilidade
- ✅ Papel de usuário: Professor ou Coordenador

---

## 🎯 Teste 1: Clique Básico (3 min)

### Passo 1: Abrir DevTools
```
Pressione: F12
Vá para: Console Tab
```

### Passo 2: Verificar Logs Iniciais
Você deve ver no console:
```
✓ Script de edição carregado. isEditable: true
✓ selectedSlots inicializados: Map(X) {...}
✓ selectedSlots.size: X
✓ Chamando renderDetalhamento() na inicialização...
✓ renderDetalhamento() completo
```

**Se aparecer "selectedSlots.size: 0"** = Nenhum dado salvo (normal na primeira vez)

### Passo 3: Clicar em Célula Vazia
```
1. Localize uma célula CINZA (Indisponível)
2. Clique nela
```

**Esperado:**
```
✓ Célula muda para VERDE (Preferido)
✓ Console mostra: "toggleAvailability chamado"
✓ Console mostra: "Novo status: preferred"
```

### Passo 4: Aguardar Toast
```
Aguarde ~1 segundo
```

**Esperado:**
```
✓ Notificação apareça: "✓ Disponibilidade salva automaticamente!"
✓ Toast desaparece após 3 segundos
```

### Passo 5: Verificar Network
```
Vá para: F12 → Network Tab
```

**Esperado:**
```
✓ Requisição: PUT /admin/planejamento/professores/1/disponibilidade
✓ Status: 200 OK (verde)
✓ Request Payload: { availability_slots: [{...}] }
✓ Response: { "success": true, "message": "..." }
```

---

## 🎯 Teste 2: Tabela Renderiza (5 min)

### Passo 1: Verificar Tabela
Após o toast desaparecer, procure pela seção:
```
📋 Detalhamento de Horários
Horários salvos em tempo real - Clique para editar ou excluir
```

### Passo 2: Verificar Linhas
A tabela deve ter pelo menos 1 linha:
```
┌───────────────────────┬──────────┬─────────────┬───────────────┐
│ Dia da Semana         │ Horário  │ Preferência │ Status        │
├───────────────────────┼──────────┼─────────────┼───────────────┤
│ Segunda-feira         │ 07:30... │ ✓ Preferido │ Registrado... │
└───────────────────────┴──────────┴─────────────┴───────────────┘
```

### Passo 3: Verificar Resumo
À direita (ou abaixo em mobile), deve aparecer:
```
Resumo:
  Total de horários: 90
  Preferidos: 1 (aumentou)
  Disponíveis: 0
  Indisponíveis: 89 (diminuiu)
```

### Passo 4: Clicar Novamente
Clique na **mesma célula** ou em **outra célula**:
```
1. Célula VERDE → Clique → Fica AZUL (Disponível)
   ou
2. Célula CINZA → Clique → Fica VERDE (Preferido)
```

**Esperado:**
```
✓ Cor muda imediatamente
✓ Toast aparece após ~1 segundo
✓ Tabela atualiza (nova linha ou linha modificada)
✓ Resumo atualiza
```

---

## 🎯 Teste 3: Persistência após F5 (5 min)

### Passo 1: Ter pelo menos 2-3 Cliques Salvos
(Faça o Teste 1 e 2 algumas vezes)

### Passo 2: Pressionar F5
```
Recarregue a página: F5 (ou Ctrl+R)
```

### Passo 3: Aguardar Carregamento
Aguarde até que a página carregue completamente.

### Passo 4: Verificar Console
F12 → Console

**Esperado:**
```
✓ Script de edição carregado. isEditable: true
✓ selectedSlots inicializados: Map(X) {...}
✓ selectedSlots.size: X (deve ser > 0!)
✓ Chamando renderDetalhamento() na inicialização...
✓ renderDetalhamento() completo
```

### Passo 5: Verificar Tabela
Procure pela seção "Detalhamento de Horários"

**Esperado:**
```
✓ Tabela APARECE IMEDIATAMENTE (sem clicar em nada)
✓ Tabela tem TODAS as linhas que você clicou
✓ Resumo mostra números CORRETOS
```

### Passo 6: Verificar Células
Cada célula que você havia marcado deve estar com a **cor preservada**

**Esperado:**
```
✓ Célula 1: VERDE (como você deixou)
✓ Célula 2: AZUL (como você deixou)
✓ Célula 3: CINZA (como você deixou)
```

---

## 🎯 Teste 4: Edição Continuada (5 min)

### Passo 1: Mudar Preferência
Clique em célula **VERDE** (Preferido):
```
Verde → Clique → Azul (Disponível)
```

### Passo 2: Verificar Tabela
A linha na tabela deve **atualizar** (linha existente muda, não cria nova)

**Esperado:**
```
✓ Linha: "... | ◐ Disponível | ..."
✓ Toast: "✓ Disponibilidade salva!"
```

### Passo 3: Clicar Novamente
Clique na mesma célula:
```
Azul → Clique → Cinza (Indisponível)
```

**Esperado:**
```
✓ Célula volta ao cinza
✓ Linha desaparece da tabela (pois não há mais preferência)
✓ Toast: "✓ Disponibilidade salva!"
✓ Resumo: Preferidos/Disponíveis diminuem
```

---

## 🎯 Teste 5: Teste de Estresse (10 min)

### Passo 1: Clicar Muitas Vezes
Clique em 10-20 células diferentes:
```
Célula 1 → Verde → Toast
Célula 2 → Azul → Toast
Célula 3 → Verde → Toast
...
```

**Esperado:**
```
✓ Cada clique funciona
✓ Cada toast aparece
✓ Tabela cresce a cada clique
✓ Resumo atualiza corretamente
✓ Nenhum erro no console
```

### Passo 2: Recarregar
```
F5 (recarregar)
```

**Esperado:**
```
✓ Tabela aparece com TODAS as 10-20 linhas
✓ Resumo mostra: Preferidos + Disponíveis + Indisponíveis = Total
✓ Células estão com cores corretas
✓ Nenhum erro no console
```

---

## ✅ Checklist de Sucesso

Marque cada item que funciona:

### Fase 1: Clique e Salvar
- [ ] Clique altera cor da célula
- [ ] Toast aparece após 1 segundo
- [ ] Network mostra requisição PUT 200 OK
- [ ] Console mostra logs corretos

### Fase 2: Renderização
- [ ] Tabela "Detalhamento" aparece após salvar
- [ ] Tabela tem número correto de linhas
- [ ] Resumo mostra números corretos
- [ ] Nenhum erro no console

### Fase 3: Persistência
- [ ] F5 (recarregar)
- [ ] Tabela aparece IMEDIATAMENTE
- [ ] Células estão com cores PRESERVADAS
- [ ] Resumo está CORRETO
- [ ] Nenhum erro no console

### Fase 4: Edição
- [ ] Mudar cor de célula funciona
- [ ] Tabela atualiza linha existente
- [ ] Remover preferência (cinza) remove linha
- [ ] Resumo sempre correto

### Fase 5: Estresse
- [ ] 10+ cliques funcionam
- [ ] F5 carrega todos os dados
- [ ] Nenhum erro em nenhum passo

---

## 🔴 Se Algo Não Funcionar

### Clique não funciona
```
F12 → Console
Procure por: "toggleAvailability chamado"
  - Se aparecer → Clique foi registrado
  - Se NÃO aparecer → Sem permissão ou JS não carregou
```

### Toast não aparece
```
F12 → Network
Procure por: PUT /disponibilidade
  - Status 200 → Salvou no BD, mas toast pode estar fora da tela
  - Status 419 → CSRF token inválido
  - Status 403 → Sem permissão
```

### Tabela não aparece
```
F12 → Console
Procure por: "selectedSlots.size"
  - Se > 0 → renderDetalhamento() deveria ter criado tabela
  - Se = 0 → Nenhum slot foi marcado como preferido/disponível
```

### Dados sumirem após F5
```
F12 → Console → Recarregar
Procure por: "selectedSlots.size"
  - Se = 0 → Backend não carregou dados do BD
  - Se > 0 → Dados estão carregados, mas tabela não renderiza
```

---

## 📞 Me Reporte

Se algo não funcionar, copie:
1. **Console logs** (F12 → Console → Copie tudo)
2. **Network response** (F12 → Network → PUT request → Response)
3. **Qual etapa falhou?** (clique, toast, tabela, persistência?)
4. **Qual é o erro exato?** (mensagem vermelha no console)

---

## 🎉 Se Tudo Funcionar

**Sistema está 100% pronto para produção!** 🚀

Próximos passos:
1. Testar com vários professores
2. Testar em diferentes navegadores
3. Deploy para produção
4. Monitorar por 24h

**Parabéns!** 🏆

