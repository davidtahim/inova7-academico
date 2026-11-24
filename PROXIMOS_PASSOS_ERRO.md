# 🔧 Próximos Passos Para Resolver o Erro

## ✅ Já Corrigido Na View

Atualizei o arquivo `professors-availability.blade.php` com:

✅ Melhor tratamento de erros
✅ Logs mais detalhados
✅ Headers corretos
✅ Mensagens de erro claras

---

## 🚀 O Que Fazer Agora

### Passo 1: Testar Novamente

```
1. Recarregue a página (Ctrl+Shift+Del para limpar cache)
2. Clique em um horário (deve ficar verde)
3. Clique em "💾 Salvar"
4. Observe o que acontece
```

### Passo 2: Se Ainda Falhar - Debugar

```
1. Pressione F12 (abrir console)
2. Clique em "Salvar" de novo
3. Procure por erros vermelhos
4. Copie a mensagem de erro
```

### Passo 3: Verificar Banco

Se o erro disser "403" ou "Acesso negado":

```bash
ssh seu_usuario@seu_servidor
cd /path/para/inova7-academico

php artisan tinker

# Seu usuário é professor?
$user = App\Models\User::where('email', 'seu_email@uni.br')->first();
$user->role;  # Deve ser 'teacher'

# Você está vinculado?
$user->professor_id;  # Deve ser um número

# Vincule se necessário:
$professor = App\Models\Professor::where('email', $user->email)->first();
$user->professor_id = $professor->id;
$user->save();

exit;
```

---

## 🎯 Cenários Possíveis

### Cenário A: Funciona! ✓

Parabéns! Se salvou com sucesso:
- Clique em vários horários
- Clique em "Salvar"
- Recarregue a página
- Seus dados devem estar lá

### Cenário B: Erro "Failed to fetch"

Procure o error no console (F12):

**Se disser "405":**
```
Rota não está como PUT
Solução: Verifique routes/web.php linha 42
```

**Se disser "403":**
```
Sem permissão
Solução: Verifique banco (Passo 3 acima)
```

**Se disser "500":**
```
Erro no servidor
Solução: Veja logs: tail -f storage/logs/laravel.log
```

**Se não conseguir ler:**
```
Execute no console:
document.querySelector('.btn-success').click();
Copie o error completo
Compartilhe comigo
```

### Cenário C: Funciona mas não salva

Se a página recarrega mas não salva:
```
Pode ser erro de validação
Verifique o error no console
Procure por "400" ou "422"
```

---

## 📊 Checklist Rápido

```bash
# No SSH do servidor:
cd /path/para/inova7-academico

# 1. Verificar rota
php artisan route:list | grep disponibilidade
# Deve mostrar: PUT /admin/planejamento/professores/{professor}/disponibilidade

# 2. Limpar cache
php artisan cache:clear
php artisan route:clear

# 3. Verificar banco
php artisan tinker
$user = App\Models\User::where('id', 1)->first();
echo "Role: " . $user->role;
echo "Professor ID: " . $user->professor_id;
exit;

# 4. Verificar método
grep -n "updateAvailability" app/Http/Controllers/ProfessorAvailabilityController.php
# Deve encontrar o método
```

---

## 🎓 Exemplo de Teste Real

### Cenário: Professor Reginaldo testando

```
1. Recarrega página
2. Clica em 3 horários (Seg 7h, Ter 8h, Qua 9h)
3. Vê as células ficarem verdes ✓
4. Resumo atualiza (3 preferidos) ✓
5. Clica "💾 Salvar Disponibilidade"
6. Console mostra:
   - Status: 200 ✓
   - Resposta: {success: true} ✓
7. Alerta aparece: "✓ Sucesso!" ✓
8. Página recarrega ✓
9. Volta para a mesma página ✓
10. Verifica: seus 3 horários ainda estão verdes! ✓
11. Faz logout ✓
12. Faz login novamente ✓
13. Acessa disponibilidade de novo ✓
14. 3 horários ainda estão verdes! ✓✓✓

SUCESSO!
```

---

## 🔧 Se Nada Funcionar

Faça isso no servidor:

```bash
cd /path/para/inova7-academico

# Limpar tudo
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Reiniciar fila (se tiver)
php artisan queue:restart

# Se usar nginx/apache, reiniciar:
sudo systemctl restart nginx
# ou
sudo systemctl restart apache2

# Recarregue no navegador
```

---

## 📝 Informações Para Compartilhar

Se não conseguir resolver, compartilhe:

1. **Console error (F12):**
   ```
   Copie TODO o texto vermelho
   ```

2. **Request URL:**
   - F12 → Network
   - Clique em "Salvar"
   - Procure por `disponibilidade`
   - Veja qual URL foi requisitada

3. **Logs do servidor:**
   ```bash
   tail -20 storage/logs/laravel.log
   ```

4. **Verificação do banco:**
   ```bash
   php artisan tinker
   $user = App\Models\User::find(1);
   echo "Role: " . $user->role . "\n";
   echo "Prof ID: " . $user->professor_id . "\n";
   exit;
   ```

---

## ✨ Resumo

```
✅ Código atualizado com melhor tratamento de erros
✅ Console mostrará exatamente qual é o problema
✅ Você consegue diagnosticar sozinho agora
✅ Se não conseguir, tenho informações detalhadas
```

**Próximo passo:** Teste e veja o error no console!

