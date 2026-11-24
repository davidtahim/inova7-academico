# 🧪 Guia Completo de Teste - Tabela CRUD de Disponibilidade

## ✅ Pré-requisitos

- [ ] Banco de dados atualizado
- [ ] User com professor_id preenchido
- [ ] Academic term ativo na sessão
- [ ] Navegador moderno (Chrome, Firefox, Safari, Edge)

---

## 🎯 Cenários de Teste

### Cenário 1️⃣: Professor Editando Sua Própria Disponibilidade

**Preparação:**
1. Faça login como professor
2. Acesse: `https://seu-site/admin/planejamento/professores/1/disponibilidade`

**Teste:**
```
✓ Verificar se a tabela aparece
  - 6 colunas de dias (Seg, Ter, Qua, Qui, Sex, Sáb)
  - 15 linhas de horários (07:30-08:20, 08:20-09:10, etc)
  - Células cinzas (indisponíveis) por padrão

✓ Clicar em uma célula
  - Célula muda para VERDE (Preferido)
  - Resumo no lado atualiza: Preferidos +1

✓ Clicar novamente na mesma célula
  - Célula muda para AZUL (Disponível)
  - Resumo atualiza: Preferidos -1, Disponíveis +1

✓ Clicar mais uma vez
  - Célula volta para CINZA (Indisponível)
  - Resumo atualiza: Disponíveis -1

✓ Clicar em várias células (rápido)
  - Todas mudam de cor instantaneamente
  - Nenhum delay
  - Resumo se atualiza em tempo real

✓ Aguardar 1 segundo após parar de clicar
  - Toast aparece: "✓ Disponibilidade salva automaticamente!"
  - Toast desaparece após 3 segundos

✓ Recarregar a página (Ctrl+R)
  - Todas as cores marcadas permancem
  - Dados foram salvos no banco ✓

✓ Fazer logout
✓ Fazer login novamente
✓ Acessar a mesma disponibilidade
  - Dados ainda lá (persistência confirmada) ✓
```

---

### Cenário 2️⃣: Coordenador Editando Disponibilidade de Um Professor

**Preparação:**
1. Faça login como coordenador (ou admin)
2. Acesse: `https://seu-site/admin/planejamento/professores/2/disponibilidade`
   (use um professor_id diferente do seu)

**Teste:**
```
✓ Verificar se consegue acessar
  - Tabela aparece normalmente
  - Não mostra erro 403 (Forbidden)

✓ Seguir todos os testes do Cenário 1

✓ Verificar se a célula permite edição
  - Coordenador consegue clicar
  - Estados mudam
  - Dados salvam

✓ Voltar para a lista de professores
✓ Acessar outro professor
✓ Editar sua disponibilidade também
  - Funciona igual ✓
```

---

### Cenário 3️⃣: Visualização de Disponibilidade (Sem Edição)

**Preparação:**
1. Faça login como professor
2. Acesse a disponibilidade de outro professor:
   `https://seu-site/admin/planejamento/professores/2/disponibilidade`

**Teste:**
```
✓ Tabela aparece em modo visualização
  - Header diz: "(Visualização)"
  - Nenhuma instrução de clique

✓ Clicar numa célula
  - NADA acontece (sem onclick)
  - Tabela permanece cinza

✓ Botão "Salvar" está ausente
  - Apenas botões "Voltar" e "Exportar PDF" aparecem

✓ Clicar em "Exportar PDF"
  - PDF baixa corretamente
  - Mostra disponibilidade do professor
```

---

### Cenário 4️⃣: Testes de Erro

**Teste 1: Sem Academic Term Ativo**
```
1. Limpar a sessão de academic_term_id
2. Acessar disponibilidade
✓ Ver mensagem de erro (400)
✓ Ou redirecionar para selecionar term
```

**Teste 2: Professor Tentando Acessar Outro Professor**
```
1. Professor logado
2. Acessar: /admin/planejamento/professores/999/disponibilidade
✓ Ver erro 403 (Forbidden)
✓ Não conseguir editar
```

**Teste 3: Sem Internet (Simular Erro de Rede)**
```
1. Abrir DevTools (F12)
2. Ir para Network tab
3. Desabilitar internet (ou usar Chrome offline mode)
4. Clicar numa célula
5. Aguardar 1 segundo
✓ Toast mostra erro: "Erro ao salvar"
6. Reabilitar internet
7. Clicar em outra célula
✓ Toast mostra sucesso (salvou no retry)
```

**Teste 4: Servidor Retorna Erro 500**
```
1. Usar DevTools para interceptar request
2. Simular erro 500
3. Clicar numa célula
✓ Toast mostra: "Erro ao salvar: 500"
✓ Célula mantém a cor (não revert)
4. Usuário pode clicar de novo para tentar
```

---

## 📋 Checklist de Funcionalidades

### Visual
- [ ] Tabela com 6 colunas (Seg-Sáb)
- [ ] Tabela com 15 linhas (horários corretos)
- [ ] Células cinzas por padrão
- [ ] Células ficam verdes ao clicar (preferido)
- [ ] Células ficam azuis ao clicar novamente (disponível)
- [ ] Células voltam cinzas ao terceiro clique
- [ ] Resumo de contadores no lado direito
- [ ] Toast notification no canto superior direito

### Interatividade
- [ ] Clique em célula → muda cor instantaneamente
- [ ] Múltiplos cliques funcionam
- [ ] Sem lag ou delay
- [ ] Resumo se atualiza em tempo real

### Auto-save
- [ ] Aguarda 1 segundo após parar de clicar
- [ ] Toast de sucesso aparece
- [ ] Dados salvam no banco
- [ ] Página não recarrega
- [ ] Dados persistem após reload
- [ ] Dados persistem após logout/login

### Responsividade
- [ ] Desktop: tabela normal
- [ ] Tablet: se adapta bem
- [ ] Mobile: scroll horizontal funciona

### Segurança
- [ ] Professor edita apenas seu
- [ ] Coordenador edita de qualquer um
- [ ] Admin edita de qualquer um
- [ ] Erro 403 ao tentar acessar sem permissão
- [ ] CSRF token validado

### Erros
- [ ] Erro de rede mostra toast
- [ ] Erro 500 mostra toast
- [ ] Usuário pode tentar novamente
- [ ] Sem perda de dados

---

## 🐛 Como Debugar Problemas

### Problema: "Clique não muda a cor"

**Solução:**
1. Abrir DevTools (F12)
2. Ir para Console
3. Verificar se há erro JavaScript
4. Procurar por: `toggleAvailability is not defined`
5. Se houver: a função não foi incluída no arquivo
6. Verificar se o `<script>` está incluído na view

### Problema: "Toast não aparece após 1 segundo"

**Solução:**
1. Console (F12) → Network
2. Clicar numa célula
3. Aguardar 1 segundo
4. Procurar por PUT request para `/professors/X/availability`
5. Se não aparecer: função saveAvailability() não foi chamada
6. Se aparecer com erro 500: Backend está com problema

### Problema: "Dados não salvam (Failed to fetch)"

**Solução:**
1. Console (F12) → Network
2. Clicar numa célula
3. Ver request PUT
4. Se falhar: verificar
   - CSRF token válido?
   - Accept header = application/json?
   - Content-Type = application/json?
   - Response é JSON?
5. Verificar console.log() em saveAvailability()

### Problema: "Erro 403 Forbidden"

**Solução:**
1. Verificar se professor_id do user está correto
2. Verificar se tem user.professor_id
3. Se coordenador: verificar role (coordinator, staff, admin)
4. Backend checkAccess() deve retornar true

### Problema: "Tabela vazia (sem horários)"

**Solução:**
1. Verificar se generateTimeSlots() tem 15 itens
2. Verificar se $timeSlots é passado para view
3. No controller: `'timeSlots' => $this->generateTimeSlots()`
4. No view: `@foreach($timeSlots as $slot)`

---

## 📊 Teste de Performance

### Teste 1: Múltiplos Cliques Rápidos
```
1. Clicar em 20 células rápidamente
2. Verificar:
   - Nenhuma demora (< 100ms por clique)
   - Cores mudam instantaneamente
   - Resumo atualiza sem lag
   - Apenas 1 request PUT é enviado (debounce funciona)
3. Console → Network
   - Ver apenas 1 PUT request (não 20!)
```

### Teste 2: Salvar com Internet Lenta
```
1. Chrome DevTools → Network
2. Throttle: "Slow 3G"
3. Clicar numa célula
4. Aguardar salvamento (pode demorar mais)
5. Ver toast quando completar
```

---

## ✨ Resultado Esperado

Quando tudo está funcionando:

```
1. Acessa página
   ✓ Tabela aparece com 6 dias × 15 horários

2. Clica em célula
   ✓ Cor muda instantaneamente para verde

3. Clica novamente
   ✓ Cor muda para azul

4. Para de clicar
   ✓ Toast: "✓ Disponibilidade salva automaticamente!"

5. Recarrega página
   ✓ Cores permanecem (dados salvos)

6. Faz logout
   ✓ Faz login novamente

7. Acessa disponibilidade
   ✓ Cores ainda lá

✅ SUCESSO! Tabela CRUD funciona 100%
```

---

## 🎓 Regras de Teste

1. **Sempre limpar cache** antes de testes (Ctrl+Shift+Del)
2. **Testar em modo anônimo** para evitar cache do navegador
3. **Testar com devtools abertos** para ver erros
4. **Testar com internet lenta** (Chrome throttle)
5. **Testar com diferentes usuários** (professor, coordenador, admin)
6. **Testar diferentes professores** (próprio e alheio)
7. **Testar após logout/login** (persistência)
8. **Testar em diferentes navegadores** (Chrome, Firefox, Safari, Edge)

---

## 📞 Suporte

Se algo não funcionar:

1. Verificar console (F12) para erros
2. Verificar Network (F12) para requests
3. Verificar database para dados salvos
4. Verificar logs do Laravel (`storage/logs/laravel.log`)
5. Abrir issue com:
   - Navegador e versão
   - SO (Windows, Mac, Linux)
   - Passos para reproduzir
   - Screenshot ou video
   - Console errors

---

**Versão:** 1.0  
**Data:** 2024  
**Status:** ✅ Pronto para Teste  

