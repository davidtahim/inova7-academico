# 🧪 Teste: Fluxo Completo do Professor (Login → Disponibilidade)

**Data**: 2026-11-24  
**Objetivo**: Validar que professor consegue fazer login e preencher disponibilidade

---

## 📋 Pré-Requisitos

- ✅ Database migrations executadas
- ✅ User com role `teacher` criado (ID 10)
- ✅ Professor vinculado ao user (professor_id = 7)
- ✅ Período acadêmico ativo no sistema

---

## 🧪 Teste 1: Login do Professor

### Dados
```
Email: 560127711@prof.uni7.edu.br
Senha: Abc123def
```

### Passos
1. Acesse `http://127.0.0.1:8000/login`
2. Digite email e senha
3. Clique "Entrar"

### Resultado Esperado
```
✅ Login bem-sucedido
✅ Sistema detecta role = 'teacher'
✅ Sistema detecta professor_id = 7
✅ Redireciona para: /admin/planejamento/professores/7/disponibilidade
✅ Carrega view de disponibilidade
```

### O que pode dar errado
| Erro | Causa | Solução |
|------|-------|---------|
| "Usuário não encontrado" | Email errado | Usar `560127711@prof.uni7.edu.br` |
| "Senha incorreta" | Senha errado | Usar `Abc123def` ou redefinir |
| "Redireciona para /home" | professor_id nulo | Verificar column `professor_id` em `users` (ID 10) |
| "Redireciona para /perfil/editar" | professor_id nulo | Mesmo erro acima |

---

## 🧪 Teste 2: Tela de Disponibilidade

### Dados
- Já logado como professor (ID 10)
- URL: `http://127.0.0.1:8000/admin/planejamento/professores/7/disponibilidade`

### Passos
1. Aguarde a página carregar
2. Observe a grade de horários

### Resultado Esperado
```
✅ Título: "Disponibilidade de Horários"
✅ Nome: "Professor: Edson Brito Junior"
✅ Semestre: "2027.1" ou similar
✅ Mensagem: "✏️ Clique nos horários para selecionar sua disponibilidade"
✅ Grid de horários com 6 dias × 15 horários = 90 slots
✅ Todos os slots inicialmente cinzas (Indisponível)
✅ Resumo lateral mostra:
   - Total de horários: 90
   - Preferidos: 0
   - Disponíveis: 0
   - Indisponíveis: 90
```

### O que pode dar errado
| Erro | Causa | Solução |
|------|-------|---------|
| "403 Acesso negado" | Não é professor ou não é prof_id correto | Verificar `checkAccess()` em controller |
| Grade não renderiza | JavaScript erro | Abrir DevTools (F12) → Console |
| Horários não clicáveis | `isEditable = false` | Verificar `canEditAvailability()` |
| Horários aparecem com cores | Já tem disponibilidade salva | Normal, mostra dados anteriores |

---

## 🧪 Teste 3: Preencher Disponibilidade

### Passos - Marcar Preferidos

1. **Segunda-feira, 07:30-08:20**
   - Clique na célula (Seg, 07:30 às 08:20)
   - Resultado: Vira 🟢 Verde "✓ Preferido"

2. **Segunda-feira, 08:20-09:10**
   - Clique na célula (Seg, 08:20 às 09:10)
   - Resultado: Vira 🟢 Verde "✓ Preferido"

3. **Terça-feira, 07:30-08:20**
   - Clique na célula (Ter, 07:30 às 08:20)
   - Resultado: Vira 🟢 Verde "✓ Preferido"

### Resultado Esperado Após 3 Cliques

```
✅ 3 células ficaram 🟢 Verdes
✅ Resumo atualiza em tempo real:
   - Preferidos: 3
   - Disponíveis: 0
   - Indisponíveis: 87
✅ Após ~1 segundo, salva automaticamente
✅ Toast de sucesso: "✓ Disponibilidade salva automaticamente!"
✅ Detalhamento aparece listando os 3 horários
```

### O que pode dar errado
| Erro | Causa | Solução |
|------|-------|---------|
| Célula não muda de cor | JavaScript erro | Abrir DevTools (F12) → Console |
| Resumo não atualiza | `updateSummary()` não chamado | Recarregar página |
| Não salva após 1s | AJAX erro | Verificar DevTools → Network |
| Toast não aparece | Função `showNotification()` não definida | Verificar view JavaScript |

---

## 🧪 Teste 4: Ciclo de Estados

### Passos - Testar Ciclo Completo

1. **Clique 1 vez** em um horário Cinza
   ```
   ⚪ Cinza → 🟢 Verde (Preferido)
   Esperado: "✓ Preferido"
   ```

2. **Clique 2 vezes** no mesmo horário
   ```
   🟢 Verde → 🔵 Azul (Disponível)
   Esperado: "◐ Disponível"
   ```

3. **Clique 3 vezes** no mesmo horário
   ```
   🔵 Azul → ⚪ Cinza (Indisponível)
   Esperado: "✗ Indisponível"
   ```

4. **Clique 4 vezes** no mesmo horário
   ```
   ⚪ Cinza → 🟢 Verde (Preferido) [volta ao início]
   Esperado: "✓ Preferido"
   ```

### Resultado Esperado
```
✅ Ciclo funciona perfeito: cinza → verde → azul → cinza → verde...
✅ Resumo atualiza corretamente cada clique
✅ Texto na célula muda conforme estado
✅ Auto-salva após cada ciclo
```

---

## 🧪 Teste 5: Exemplo Prático - Agenda Realista

### Cenário
Professor quer trabalhar:
- **Seg-Qua**: Horas matutinas são preferidas
- **Seg-Qua**: Horas vespertinas são disponíveis
- **Qui-Sex**: Apenas noite é viável
- **Sáb**: Totalmente indisponível

### Passos de Preenchimento

#### SEGUNDA-FEIRA
```
07:30-08:20 → Clique → 🟢 Preferido
08:20-09:10 → Clique → 🟢 Preferido
09:10-10:00 → Clique → 🟢 Preferido
10:10-11:00 → Clique → 🟢 Preferido
11:00-11:50 → Clique → 🟢 Preferido
11:50-12:40 → Clique → 🟢 Preferido
13:10-14:00 → Clique → 🔵 Disponível (clique 2x)
14:00-14:50 → Clique → 🔵 Disponível (clique 2x)
14:50-15:40 → Clique → 🔵 Disponível (clique 2x)
15:50-16:40 → Clique → 🔵 Disponível (clique 2x)
16:40-17:30 → Clique → 🔵 Disponível (clique 2x)
(deixar noite como Cinza/Indisponível)
(deixar sábado como Cinza/Indisponível)
```

#### TERÇA-FEIRA
```
(Repetir igual segunda-feira)
```

#### QUARTA-FEIRA
```
(Repetir igual segunda-feira)
```

#### QUINTA-FEIRA
```
(deixar manhã Cinza)
(deixar tarde Cinza)
18:30-19:20 → Clique → 🟢 Preferido
19:20-20:10 → Clique → 🟢 Preferido
20:20-21:10 → Clique → 🟢 Preferido
21:10-22:00 → Clique → 🟢 Preferido
```

#### SEXTA-FEIRA
```
(Repetir igual quinta-feira)
```

#### SÁBADO
```
(Deixar todos Cinza/Indisponível)
```

### Resultado Esperado Final
```
✅ Total selecionado: 6 (manhã) + 5 (tarde) + 4 (noite) + 4 (noite) = 23 horários
✅ Preferidos: 20 (6+6+6+2 de seg/ter/qua/qui/sex)
✅ Disponíveis: 10 (5 tardes de seg/ter/qua)
✅ Indisponíveis: 60 (resto)
✅ Resumo atualizado corretamente
✅ Detalhamento mostra todos os 23 horários
✅ Dados persistem no banco de dados
```

---

## 🧪 Teste 6: Recarregar Página

### Passos
1. Após preencher e salvar a disponibilidade (Teste 5)
2. Recarregue a página (F5 ou Ctrl+R)
3. Aguarde carregar

### Resultado Esperado
```
✅ Página carrega novamente
✅ Todos os horários preenchidos reaparecem com cores corretas
✅ Resumo mostra os mesmos números
✅ Detalhamento lista os mesmos horários
✅ Nenhum dado foi perdido
```

### O que pode dar errado
| Erro | Causa | Solução |
|------|-------|---------|
| Horários aparecem Cinza | Dados não salvaram no BD | Verificar `updateAvailability()` em controller |
| Resumo diferente | Contagem errada no BD | Verificar SQL no laravel.log |

---

## 🧪 Teste 7: Modal de Edição (Avançado)

### Passos
1. Clique em um horário já preenchido
2. Aguarde o modal abrir
3. Selecione "✓ Preferido" ou "◐ Disponível"
4. Clique "Salvar"

### Resultado Esperado
```
✅ Modal abre com informação do horário
✅ Opções: "✓ Preferido" e "◐ Disponível"
✅ Clique em "Salvar" atualiza célula
✅ Modal fecha automaticamente
✅ Detalhamento atualiza
```

---

## 🧪 Teste 8: Validações

### Teste 8a: Sem Semestre Ativo

**Setup**: Remover todos os semestres ativos do banco

**Passos**:
1. Tentar acessar `/admin/planejamento/professores/7/disponibilidade`
2. Tentar clicar em um horário

**Resultado Esperado**:
```
✅ Aviso: "Nenhum período acadêmico disponível"
✅ Usuário não consegue editar
```

### Teste 8b: Professor Visualizando Outro Professor

**Setup**: 
- Faça login como professor 10
- Tente acessar URL de outro professor: `/admin/planejamento/professores/999/disponibilidade`

**Resultado Esperado**:
```
✅ Erro 403: "Acesso negado"
✅ Mensagem: "Você não tem permissão para visualizar essa disponibilidade"
```

### Teste 8c: Coordenador Editando Professor

**Setup**:
- Faça login como coordenador
- Acesse `/admin/planejamento/professores/7/disponibilidade`

**Resultado Esperado**:
```
✅ Visualiza disponibilidade
✅ Pode editar horários
✅ Pode salvar
✅ Mensagem "Editável - Clique para mudar"
```

---

## 🧪 Teste 9: Exportar PDF

### Passos
1. Acesse `/admin/planejamento/professores/7/disponibilidade`
2. Aguarde carregamento completo
3. Localize botão "📄 Exportar como PDF"
4. Clique nele

### Resultado Esperado (Em Modo Visualização)
```
✅ Botão aparece
✅ Clique abre PDF em nova aba
✅ PDF mostra grade de disponibilidade
✅ Nome do professor no cabeçalho
```

### Resultado Esperado (Em Modo Edição)
```
✅ Botão NÃO aparece (não está em modo visualização)
```

---

## ✅ Checklist de Testes

```
Teste 1 - Login do Professor
  ☐ Login bem-sucedido
  ☐ Redireciona para disponibilidade
  ☐ Semestre carregado
  
Teste 2 - Tela de Disponibilidade
  ☐ Grid renderiza com 90 slots
  ☐ Resumo mostra 0/0/90
  ☐ Mensagem de edição aparece
  
Teste 3 - Marcar Horários
  ☐ Cliques mudam cor
  ☐ Resumo atualiza
  ☐ Auto-salva após 1s
  ☐ Toast de sucesso aparece
  
Teste 4 - Ciclo de Estados
  ☐ Cinza → Verde → Azul → Cinza → Verde
  ☐ Texto muda conforme estado
  ☐ Resumo sempre correto
  
Teste 5 - Agenda Realista
  ☐ 23 horários preenchidos
  ☐ Preferidos e disponíveis corretos
  ☐ Resumo final correto
  
Teste 6 - Recarregar Página
  ☐ Dados persistem
  ☐ Cores reaparecem
  ☐ Resumo igual
  
Teste 7 - Modal de Edição
  ☐ Modal abre
  ☐ Salva corretamente
  ☐ Célula atualiza
  
Teste 8 - Validações
  ☐ Sem semestre: mensagem de erro
  ☐ Outro professor: erro 403
  ☐ Coordenador: consegue editar
  
Teste 9 - Exportar PDF
  ☐ PDF gera corretamente
  ☐ Botão só aparece em visualização
```

---

## 🔧 Troubleshooting - Erros Comuns

### "Undefined variable $roleOptions"
```
Arquivo: resources/views/admin/users-index.blade.php
Linha: 21
Solução: Passou nesta implementação, não deve aparecer mais
```

### "Call to undefined function numbers()"
```
Arquivo: app/Http/Controllers/UserManagementController.php
Linha: 273
Solução: Passou nesta implementação, não deve aparecer mais
```

### Professor não consegue editar disponibilidade
```
Verificar:
1. user.role = 'teacher' ✓
2. user.professor_id = 7 ✓
3. professor.id = 7 ✓
4. AcademicTerm.latest() existe ✓

Log: tail -f storage/logs/laravel.log
```

### Horários não salvam
```
Verificar:
1. Network tab (F12) mostra erro na requisição PUT?
2. CSRF token correto na view?
3. Route 'admin.professors.update-availability' existe?
4. ProfessorAvailability model existe e está correto?

SQL: SELECT * FROM professor_availabilities WHERE professor_id = 7
```

### Página fica carregando
```
Verificar:
1. Semestre ativo: SELECT * FROM academic_terms WHERE ... LIMIT 1
2. PHP error log: tail -f storage/logs/laravel.log
3. Browser console (F12) para erros JavaScript
```

---

## 📝 Notas Finais

- **Salvamento Automático**: Após 1 segundo de inatividade, salva via AJAX
- **Sem Botão Salvar**: Não existe botão explícito de salvar (auto-salva)
- **Semestre Automático**: Usa semestre ativo do sistema
- **Acesso Seguro**: Professor só vê sua disponibilidade; admin vê todos
- **Dados Persistem**: Recarregar página não perde dados

---

## 🚀 Próximas Etapas Após Testes

1. ✅ Professor preenche disponibilidade (você está aqui)
2. ⏳ Coordenador usa disponibilidade para criar grade
3. ⏳ Sistema aloca professores às turmas
4. ⏳ Grade horária publicada
5. ⏳ Professor vê sua grade alocada

---

**Status**: 🟢 **PRONTO PARA TESTE**

Todos os recursos estão implementados. Basta executar os testes acima para validar! 🧪
