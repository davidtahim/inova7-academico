# 🧪 Como Testar a Nova Disponibilidade Interativa

## 📋 O que foi corrigido

Agora o painel de disponibilidade permite **EDIÇÃO INTERATIVA**:

✅ **Clique nos horários** para selecionar/desselecionar
✅ **Cores diferentes** para indicar preferência
✅ **Botão de salvar** para confirmar as alterações
✅ **Funciona para professor E coordenador**

---

## 🚀 Teste 1: Como Professor

### Pré-requisito
- Você precisa estar vinculado a um `Professor` no banco
- Sua `role` precisa ser `'teacher'`

### Teste Passo a Passo

1. **Login como Professor**
   ```
   Email: seu_email_de_professor@uni.br
   Senha: sua_senha
   ```

2. **Acesse a Disponibilidade**
   - Vá para: `Planejamento → Professores`
   - Encontre o seu professor na lista
   - Clique no botão 📅 (calendário)
   - **OU acesse diretamente:**
     ```
     http://seu-site/admin/planejamento/professores/{seu-professor-id}/disponibilidade
     ```

3. **Veja o Painel**
   - Você verá uma tabela de horários (Seg-Sex)
   - Cada célula representa um horário
   - Deve haver um **botão "💾 Salvar Disponibilidade"** no rodapé

4. **Clique nos Horários**
   - Clique em uma célula vazia → fica VERDE (Preferido)
   - Clique novamente → fica AZUL (Disponível)
   - Clique novamente → fica CINZA (Indisponível)
   - Continue clicando para alternar

5. **Veja o Resumo Atualizar**
   - Na lateral direita, você verá:
     - Preferidos: X
     - Disponíveis: Y
     - Indisponíveis: Z
   - Esses números devem atualizar quando você clica

6. **Salve**
   - Clique em "💾 Salvar Disponibilidade"
   - Deve aparecer mensagem: **"✓ Sucesso! Sua disponibilidade foi salva."**
   - A página vai recarregar em 2 segundos

7. **Verifique**
   - Após recarregar, os seus horários devem estar salvos
   - Faça logout e login novamente
   - Acesse a disponibilidade de novo
   - Seus horários devem estar lá! ✓

---

## 🚀 Teste 2: Como Coordenador

### Pré-requisito
- Sua `role` precisa ser `'coordinator'`

### Teste Passo a Passo

1. **Login como Coordenador**
   ```
   Email: coordenador@uni.br
   Senha: sua_senha
   ```

2. **Acesse a Disponibilidade de um Professor**
   - Vá para: `Planejamento → Professores`
   - Escolha qualquer professor
   - Clique no botão 📅 (calendário)

3. **Você deve conseguir EDITAR**
   - Clique nos horários para mudar a preferência do professor
   - O resumo deve atualizar
   - Clique em "💾 Salvar Disponibilidade"
   - Deve salvar com sucesso

4. **Teste Acesso Irrestrito**
   - Acesse de vários professores diferentes
   - Coordenador consegue editar de TODOS ✓

---

## 🧪 Teste 3: Verificar Segurança

### Teste: Professor não consegue editar outro

1. **Como Professor A**
   - Tente acessar URL direta de outro professor:
     ```
     /admin/planejamento/professores/999/disponibilidade
     ```
   - Deve retornar erro: **403 Acesso Negado** ✓

2. **Como Coordenador**
   - Acesse a URL do mesmo professor 999
   - Deve funcionar normalmente ✓

---

## ⚠️ Se não funcionar...

### Problema: Painel não permite edição (ainda apenas visualização)

**Solução:**

1. Verifique o banco:
   ```bash
   # SSH para o servidor
   
   # Verificar coluna professor_id em users
   mysql -u usuario -p banco_dados
   DESC users;
   ```

   - Procure por `professor_id`
   - Se não existir, criar:
   ```sql
   ALTER TABLE users ADD COLUMN professor_id BIGINT UNSIGNED NULL;
   ALTER TABLE users ADD FOREIGN KEY (professor_id) 
       REFERENCES professors(id) ON DELETE SET NULL;
   ```

2. Vincular usuários aos professores:
   ```bash
   php artisan tinker
   ```

   ```php
   $professors = App\Models\Professor::all();
   foreach ($professors as $prof) {
       $user = App\Models\User::where('email', $prof->email)->first();
       if ($user && $user->role === 'teacher') {
           $user->professor_id = $prof->id;
           $user->save();
           echo "Vinculado: {$user->name} -> {$prof->name}\n";
       }
   }
   exit;
   ```

3. Recarregue a página

### Problema: Botão "Salvar" não funciona

**Solução:**

1. Abra o console do navegador:
   - Pressione `F12`
   - Vá para a aba "Console"
   - Procure por erro vermelho

2. Verificar CSRF Token:
   - Pressione `F12` → "Network"
   - Clique em "Salvar"
   - Procure por erro de CSRF
   - Se houver, fazer logout e login de novo

3. Verificar rota:
   - Na aba "Network" (F12)
   - Procure pelo request `PUT /professores/`
   - Verifique se o status é 200 (sucesso) ou 4xx/5xx (erro)

### Problema: Resumo não atualiza

**Solução:**

- Pressione `F12` → "Console"
- Clique em um horário
- Nenhum erro deve aparecer
- Se houver erro JavaScript, copie e compartilhe

---

## 📊 Checklist de Teste

- [ ] Professor consegue acessar sua disponibilidade
- [ ] Professor consegue clicar nos horários
- [ ] Cores mudam ao clicar (verde → azul → cinza)
- [ ] Resumo atualiza os números
- [ ] Botão "Salvar" funciona
- [ ] Mensagem de sucesso aparece
- [ ] Dados são salvos no banco
- [ ] Após logout/login, dados continuam lá
- [ ] Coordenador consegue editar de qualquer professor
- [ ] Professor não consegue editar outro professor
- [ ] Admin consegue editar de qualquer professor

---

## 💡 Dicas

1. **Testar em Incógnita:**
   - Abra uma janela anônima
   - Faça login com outro usuário
   - Teste com múltiplas contas simultaneamente

2. **Console do Navegador:**
   - Se algo não funcionar, verifique o console (F12)
   - Erros JavaScript aparecem em vermelho
   - Veja a request no Network (F12 → Network)

3. **Banco de Dados:**
   - Verifique se os dados foram salvos:
   ```bash
   php artisan tinker
   
   App\Models\ProfessorAvailability::where('professor_id', 1)->get();
   ```

4. **Limpeza:**
   - Se quiser limpar os dados de teste:
   ```bash
   php artisan tinker
   
   App\Models\ProfessorAvailability::truncate();
   ```

---

## ✅ Pronto!

Você conseguiu? Excelente! Agora:

1. ✅ Professor edita sua disponibilidade
2. ✅ Coordenador edita de todos
3. ✅ Segurança implementada
4. ✅ Painel interativo funcionando

**Status:** 🎉 FUNCIONALIDADE COMPLETA

---

**Próximos passos (opcionais):**
- Adicionar notificações
- Criar relatórios
- Integrar com alocação de turmas
- Dashboard com estatísticas

