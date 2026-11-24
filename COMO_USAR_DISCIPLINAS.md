# 📚 Como Usar - Painel de Disciplinas do Professor

## 🎯 O Que É?

Um painel no sistema onde administradores, coordenadores e staff podem indicar quais disciplinas cada professor está qualificado para lecionar.

**Exemplo:** Professor Reginaldo Borba pode lecionar estas 8 disciplinas:
- Matemática Aplicada
- Lógica Matemática
- Estatística e Probabilidade
- Linguagens de Programação
- Lógica de Programação
- Programação Orientada a Objeto
- Inteligência Artificial
- Cálculo

---

## 🚀 Passo a Passo

### 1. Acesse o Painel de Professores
```
Menu → Planejamento → Professores
```

### 2. Localize o Professor
Encontre "Reginaldo Borba" (ou outro professor) na listagem.

Você verá esta linha de ações:
```
[Editar] [📅] [📚] [Excluir]
```

Onde:
- 📅 = Ver disponibilidade de horários
- 📚 = Gerenciar disciplinas ← CLIQUE AQUI

### 3. Clique no Botão 📚 (Livros)
O sistema abrirá a página de gerenciamento de disciplinas.

### 4. Visualize as Informações

Você verá:

**À Esquerda:**
- Disciplinas que o professor está habilitado (marcadas com ✓)

**À Direita (Resumo):**
- Total de disciplinas no banco
- Quantas o professor está habilitado
- Quantas o professor NÃO está habilitado

**Embaixo (Formulário):**
- Lista de TODAS as disciplinas com checkboxes
- Marque = Professor habilidado
- Desmarque = Professor não habilidado

### 5. Faça as Alterações

**Para Habilitar uma Disciplina:**
1. Procure a disciplina na lista "Todas as Disciplinas"
2. Marque a checkbox
3. A disciplina passa a ficar "habilitada"

**Para Desabilitar uma Disciplina:**
1. Procure a disciplina na lista "Todas as Disciplinas"
2. Desmarque a checkbox
3. A disciplina deixa de ficar "habilitada"

### 6. Salve as Mudanças

Clique no botão **"Salvar Disciplinas"**

Você verá a mensagem:
```
✓ Disciplinas atualizadas com sucesso!
```

Pronto! As mudanças foram salvas no banco de dados.

---

## 📝 Notas Importantes

✅ **Apenas admin, coordenador e staff podem acessar este painel**
- Se você não vê o botão 📚, não tem permissão

✅ **Os dados são salvos imediatamente**
- Não precisa de confirmação adicional

✅ **Você pode entrar e sair quantas vezes quiser**
- As mudanças são persistentes

✅ **Não há limite de disciplinas**
- Um professor pode estar habilitado em quantas quiser

---

## 🔄 Fluxo Completo

```
1. Planejamento → Professores
                    ↓
2. Clica no 📚 do Professor
                    ↓
3. Vê as disciplinas selecionadas
                    ↓
4. Marca/desmarque checkboxes
                    ↓
5. Clica "Salvar Disciplinas"
                    ↓
6. ✓ Sucesso! Dados salvos no banco
```

---

## ❓ Dúvidas Frequentes

**P: Posso marcar o mesmo professor em duas disciplinas?**
R: Não é a mesma coisa. Aqui você marca quais disciplinas 1 professor pode lecionar. Se você quer atribuir um professor específico a uma turma, isso é feito em outro lugar (Planejamento de Oferta).

**P: O que acontece se desmarcar todas as disciplinas?**
R: O professor ficará com "0 disciplinas habilitadas". Ele ainda existirá no sistema, mas não estará qualificado para nenhuma disciplina.

**P: Como adiciono uma nova disciplina ao banco?**
R: Isso é feito em **Planejamento → Disciplinas**. Lá você pode criar/editar disciplinas. Depois aparecem automaticamente aqui.

**P: Posso editar de outro lugar?**
R: Não. Este painel é a única interface para gerenciar disciplinas de um professor. Mas você pode acessar quantas vezes quiser.

---

## 🎓 Exemplo Prático

### Cenário: Reginaldo está habilitado em 7 disciplinas, mas você quer adicionar "Português"

1. Acesse **Planejamento → Professores**
2. Clique no 📚 de Reginaldo
3. Localize "Português" na seção "Todas as Disciplinas"
4. Marque o checkbox de Português
5. Clique "Salvar Disciplinas"
6. ✓ Pronto! Reginaldo agora está habilitado em 8 disciplinas (incluindo Português)

No resumo você verá:
- **Habilitado em:** 8
- **Não habilitado:** (total - 8)

---

## 🔐 Segurança

Este painel é **protegido**:
- Apenas usuários com permissão podem acessar
- Não há como entrar "de fora"
- Os dados são validados antes de salvar
- As mudanças são registradas no banco

---

## ✨ Resumo

| Item | Descrição |
|------|-----------|
| **O que faz?** | Gerencia quais disciplinas um professor pode lecionar |
| **Quem acessa?** | Admin, Coordenador, Staff |
| **Como acessar?** | Planejamento → Professores → 📚 |
| **Como salvar?** | Marque/desmarque → Clique "Salvar Disciplinas" |
| **Dados persistem?** | Sim, automaticamente |
| **Limite?** | Nenhum, sem limite de disciplinas |

---

## 📞 Precisa de Ajuda?

Se tiver dúvidas ou problemas:
1. Verifique se está logado como admin/coordenador/staff
2. Verifique se o professor existe
3. Verifique se há disciplinas cadastradas
4. Contacte o administrador do sistema

---

**Última atualização:** 2024
**Status:** ✅ Pronto para Uso
