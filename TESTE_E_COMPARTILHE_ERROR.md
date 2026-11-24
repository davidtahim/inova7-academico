# 🧪 Como Testar e Compartilhar o Error

## ✅ Já Corrigido

O código foi atualizado com melhor tratamento de erros. Agora você consegue ver exatamente qual é o problema!

---

## 🚀 Teste Rápido (5 minutos)

### Passo 1: Recarregue a Página
```
Pressione: Ctrl+Shift+Del (ou Cmd+Shift+Del no Mac)
Depois: Clique em recarregar
```

### Passo 2: Abra o Console
```
Pressione: F12
Vá para: Aba "Console"
```

### Passo 3: Clique Em Um Horário
```
Clique em qualquer célula
Deve ficar VERDE
Se ficar roxo ou não mudar, há erro
```

### Passo 4: Clique Em "Salvar"
```
Clique no botão "💾 Salvar Disponibilidade"
Observe o console
Deve aparecer alguma mensagem
```

### Passo 5: Veja o Resultado

**SE FUNCIONAR:**
```
✅ Vê "⏳ Salvando..."
✅ Depois "✓ Sucesso!"
✅ Página recarrega
✅ PRONTO! 🎉
```

**SE FALHAR:**
```
❌ Vê alerta vermelho: "Erro ao salvar..."
❌ Vê erro no console
❌ Continua lendo...
```

---

## 🔍 Se Deu Erro - Como Compartilhar

### Método 1: Screenshot do Console

```
1. Pressione F12
2. Clique em "Console"
3. Clique em "Salvar"
4. Tire screenshot (Prnt Scr)
5. Compartilhe comigo
```

**O que vou ver:**
```
Status: 404
Headers: ...
Erro do servidor: ...
❌ Erro ao salvar: ...
```

### Método 2: Copiar Texto do Console

```
1. F12 → Console
2. Clique em "Salvar"
3. Pressione Ctrl+A (selecionar tudo)
4. Pressione Ctrl+C (copiar)
5. Compartilhe comigo
```

---

## 🎯 Erros Mais Comuns

### Erro 1: "Erro ao salvar: HTTP error! status: 405"

**Significa:** Rota não está como PUT

**Solução:**
```bash
# No servidor
php artisan route:list | grep disponibilidade

# Deve aparecer:
# PUT   /admin/planejamento/professores/{professor}/disponibilidade

# Se não aparecer, verifique routes/web.php linha 42
# Deve ser: Route::put(...)
```

### Erro 2: "Erro ao salvar: HTTP error! status: 403"

**Significa:** Sem permissão

**Solução:**
```bash
# No servidor
php artisan tinker

$user = App\Models\User::find(1);
echo "Seu role: " . $user->role . "\n";
echo "Professor ID: " . $user->professor_id . "\n";

# Se professor_id for NULL:
$prof = App\Models\Professor::where('email', $user->email)->first();
$user->professor_id = $prof->id;
$user->save();

echo "Vinculado com sucesso!\n";
exit;
```

### Erro 3: "Failed to fetch"

**Significa:** Problema de rede ou CORS

**Solução:**
```bash
# No servidor
php artisan cache:clear
php artisan route:clear

# Se usar nginx:
sudo systemctl restart nginx

# Recarregue no navegador (Ctrl+Shift+Del)
```

### Erro 4: Vê alerta mas não consegue ler

**Solução:**
```
1. F12 → Console
2. Procure por mensagem vermelha
3. Leia qual é a mensagem
4. Veja acima qual é o erro
5. Se não conseguir, copie o console inteiro
```

---

## 📝 Informações Que Preciso

Se não conseguir resolver, compartilhe:

```
1. ERRO EXATO:
   └─ Qual é a mensagem do console?
   └─ Qual é o status (403, 404, 500)?
   └─ Qual é a URL requisitada?

2. BANCO DE DADOS:
   php artisan tinker
   
   $user = App\Models\User::where('email', 'seu@email.com')->first();
   echo "Role: " . $user->role;
   echo "Prof ID: " . $user->professor_id;
   
   exit;
   
   └─ Qual é o role? (teacher, coordinator, admin?)
   └─ Professor ID é NULL ou um número?

3. LOGS DO SERVIDOR:
   tail -20 storage/logs/laravel.log
   
   └─ Copie as últimas linhas vermelhas

4. VERIFICAÇÃO DA ROTA:
   php artisan route:list | grep disponibilidade
   
   └─ A rota aparece como PUT?
```

---

## ✨ Resumo da Solução

```
❌ ANTES:
   Erro: "Failed to fetch"
   Sem saber o que era

✅ DEPOIS:
   Código com logs detalhados
   Console mostra qual é o erro
   Você consegue diagnosticar sozinho
```

---

## 🎓 Exemplo Real

### Cenário: Professor Reginaldo

```
1. Recarrega página
2. Clica em 3 horários (Seg, Ter, Qua às 7:30)
3. Vê as células ficarem verdes ✓
4. Clica "💾 Salvar"
5. No console aparece:
   - Status: 200 ✓
   - Resposta: {success: true} ✓
6. Alerta: "✓ Sucesso!" ✓
7. Recarrega automaticamente ✓
8. Os 3 horários continuam verdes ✓
9. Faz logout
10. Faz login de novo
11. Acessa disponibilidade
12. Os 3 horários estão lá! ✓✓✓

FUNCIONOU!
```

---

## 🚀 Próximos Passos

1. **Teste agora**
   - Recarregue e tente salvar
   - Observe o console

2. **Se funcionar**
   - Parabéns! Use em produção

3. **Se falhar**
   - Pegue o erro do console
   - Verifique os erros comuns acima
   - Se não conseguir, compartilhe comigo

---

## 📞 Como Compartilhar

**Opção 1: Screenshot**
```
1. F12 → Console
2. Clique "Salvar"
3. Prnt Scr
4. Compartilhe imagem
```

**Opção 2: Texto do Console**
```
1. F12 → Console
2. Clique "Salvar"
3. Ctrl+A e Ctrl+C
4. Cole no chat
```

**Opção 3: Informações Estruturadas**
```
Erro: [Qual é a mensagem?]
Status: [Qual é o código HTTP?]
Role: [teacher/coordinator/admin?]
Professor ID: [Número ou NULL?]
Rota existe: [Sim/Não?]
```

---

## ✅ Checklist Rápido

```
[ ] Recarreguei a página (Ctrl+Shift+Del)
[ ] Abri o console (F12)
[ ] Cliquei em um horário (ficou verde)
[ ] Cliquei em "Salvar"
[ ] Observei o console
[ ] Copiei o error (se houver)
[ ] Vou compartilhar comigo
```

---

**Status:** ✅ Código Atualizado e Pronto Para Testar

**Próximo passo:** Teste agora! 🚀
