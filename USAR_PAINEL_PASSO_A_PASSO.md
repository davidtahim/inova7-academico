# 📚 Como Usar o Painel de Disciplinas - Passo a Passo

## 🎯 O Que é Este Painel?

Um local onde você (admin/coordenador/staff) pode indicar quais disciplinas cada professor está qualificado para lecionar.

**Exemplo:** Professor Reginaldo Borba pode lecionar Matemática, Lógica, etc.

---

## ⚡ PASSO 1: Preparar os Dados (via SSH/Psy Shell)

Você está no Psy Shell (console PHP interativo). Vamos preparar os dados:

### 1.1 Verificar se o Professor Reginaldo Existe

```php
App\Models\Professor::where('name', 'like', '%Reginaldo%')->first();
```

**Se retornar um objeto Professor:**
✓ Ótimo! O professor já existe. Vá para o Passo 2.

**Se retornar null (nada):**
Você precisa criar o professor. Execute:

```php
App\Models\Professor::create([
    'registration' => 'UNI727777',
    'name' => 'Reginaldo Borba',
    'email' => 'reginaldo@inova7.local',
    'qualification' => 'Mestre',
    'active' => true
]);
```

Depois execute novamente:
```php
App\Models\Professor::where('name', 'like', '%Reginaldo%')->first();
```

Agora deve retornar o professor criado.

### 1.2 Sair do Tinker

```php
exit;
```

---

## ⚡ PASSO 2: Executar o Seeder

Agora saia do Psy Shell e execute o comando de seed:

```bash
php artisan db:seed --class=ProfessorSubjectsSeeder
```

**Resultado esperado:**
```
Carregando disciplinas para: Reginaldo Borba
✓ Matemática Aplicada (Código: GMAT001)
✓ Lógica Matemática (Código: GMAT002)
✓ Estatística e Probabilidade (Código: GMAT003)
✓ Linguagens de Programação (Código: GPROG001)
✓ Lógica de Programação (Código: GPROG002)
✓ Programação Orientada a Objeto (Código: GPROG003)
✓ Inteligência Artificial (Código: GSIA001)
✓ Cálculo (Código: GMAT004)
✓ 8 disciplinas associadas ao professor Reginaldo!
```

✓ Se aparecer a mensagem de sucesso, pronto! Vá para o Passo 3.

❌ Se retornar erro: veja a seção Troubleshooting ao final.

---

## ⚡ PASSO 3: Acessar o Painel no Navegador

### 3.1 Abra seu navegador e acesse o sistema

```
https://seu-dominio/inova7
```

### 3.2 Faça login

- **Usuário:** admin (ou coordenador/staff)
- **Senha:** Sua senha

### 3.3 Navegue até o painel

```
Menu Superior → Planejamento → Professores
```

Você verá uma lista de professores com ações:

```
[Editar]  [📅]  [📚]  [Excluir]
```

Onde:
- 📅 = Ver disponibilidade de HORÁRIOS
- 📚 = Gerenciar DISCIPLINAS ← CLIQUE AQUI

---

## ⚡ PASSO 4: Usar o Painel de Disciplinas

### 4.1 Localize o Professor Reginaldo

Na listagem de professores, procure por "Reginaldo Borba".

### 4.2 Clique no Botão 📚 (Livros)

Você será levado ao painel de disciplinas do Reginaldo.

### 4.3 Veja as Informações

Você verá:

**À Esquerda:**
- Título: "Disponibilidade de Disciplinas"
- Seção: "Disciplinas Habilitadas"
- Lista de disciplinas que Reginaldo já pode lecionar (com ✓)

**À Direita (Resumo):**
- **Total de disciplinas:** Número total no sistema
- **Habilitado em:** 8 (ou quantas o seeder carregou)
- **Não habilitado:** Total - Habilitado

**Embaixo (Formulário):**
- Título: "Todas as Disciplinas"
- Uma longa lista com **checkboxes**
- Marque = Professor habilidado naquela disciplina
- Desmarque = Professor NÃO habilitado

---

## ⚡ PASSO 5: Marcar/Desmarcar Disciplinas

### 5.1 Para Habilitar uma Disciplina

1. Procure na seção "Todas as Disciplinas"
2. Encontre uma que NÃO tem checkbox marcado
3. Marque o checkbox ☐ → ☑
4. A disciplina agora está "habilitada" (selecionada)

### 5.2 Para Desabilitar uma Disciplina

1. Procure na seção "Todas as Disciplinas"
2. Encontre uma que JÁ tem checkbox marcado ☑
3. Desmarque o checkbox ☑ → ☐
4. A disciplina agora está "desabilitada" (não selecionada)

### 5.3 Exemplo Prático

**Cenário:** Reginaldo está habilitado em 8 disciplinas, mas você quer adicionar "Português"

1. Role para baixo até encontrar "Português"
2. Marque o checkbox de "Português"
3. Vá para o passo 6 (Salvar)

---

## ⚡ PASSO 6: Salvar as Mudanças

### 6.1 Localize o Botão "Salvar Disciplinas"

No final da página, embaixo do formulário, você verá dois botões:

```
[Cancelar]  [Salvar Disciplinas]
```

### 6.2 Clique em "Salvar Disciplinas"

O sistema:
1. Valida os dados
2. Salva no banco de dados
3. Redireciona de volta para a mesma página
4. Mostra mensagem de sucesso:

```
✓ Disciplinas atualizadas com sucesso!
```

### 6.3 Verificar as Mudanças

A página deve recarregar e você verá:

**Se adicionou uma disciplina:**
- Aparece na seção "Disciplinas Habilitadas"
- O resumo atualiza: "Habilitado em: 9" (antes era 8)
- O checkbox continua marcado

**Se removeu uma disciplina:**
- Desaparece da seção "Disciplinas Habilitadas"
- O resumo atualiza: "Habilitado em: 7" (antes era 8)
- O checkbox fica desmarcado

---

## ⚡ PASSO 7: Cancelar (Opcional)

Se você mudou de ideia e quer DESCARTAR as alterações:

1. Clique no botão **"Cancelar"**
2. Você voltará para a listagem de professores
3. As mudanças NÃO serão salvas

---

## 🔄 Fluxo Completo Resumido

```
1. SSH: Preparar dados (criar professor se necessário)
   ↓
2. SSH: Executar seeder (php artisan db:seed...)
   ↓
3. Navegador: Login
   ↓
4. Navegador: Planejamento → Professores
   ↓
5. Navegador: Clique no 📚 de Reginaldo
   ↓
6. Navegador: Marque/desmarque disciplinas
   ↓
7. Navegador: Clique "Salvar Disciplinas"
   ↓
8. ✓ Sucesso! Disciplinas atualizadas
```

---

## ❓ Dúvidas Frequentes

### P: O que é "Habilitado"?
R: Significa que o professor está qualificado/autorizado para lecionar essa disciplina. Se um professor está "habilitado em Matemática", ele pode lecionar turmas de Matemática.

### P: Quanto tempo leva para salvar?
R: Deve ser imediato (menos de 1 segundo).

### P: Posso mudar de novo?
R: Sim! Você pode entrar no painel quantas vezes quiser e fazer quantas alterações quiser.

### P: O que acontece se desmarcar todas?
R: O professor ficará com "0 disciplinas habilitadas". Ele ainda existe, mas não está qualificado para nenhuma disciplina.

### P: Posso habilitar no mesmo professor em 50 disciplinas?
R: Sim! Não há limite. Um professor pode estar habilitado em quantas disciplinas quiser.

### P: Essas mudanças afetam as aulas já criadas?
R: Não. Este painel apenas indica a qualificação do professor. As aulas já criadas não são afetadas.

### P: Apenas disciplinas aparecem aqui?
R: Sim. Este painel é APENAS para gerenciar disciplinas. Para gerenciar horários, use o botão 📅.

---

## 🆘 Troubleshooting

### "Erro: Professor Reginaldo não encontrado"
**Solução:**
1. Volte ao Passo 1
2. Execute:
   ```php
   App\Models\Professor::create([
       'registration' => 'UNI727777',
       'name' => 'Reginaldo Borba',
       'email' => 'reginaldo@inova7.local',
       'qualification' => 'Mestre',
       'active' => true
   ]);
   ```
3. Tente novamente

### "Erro: Nenhuma disciplina encontrada"
**Solução:**
1. Execute: `php artisan db:seed --class=DatabaseSeeder`
2. Depois: `php artisan db:seed --class=ProfessorSubjectsSeeder`

### "Erro 403 - Acesso Negado"
**Solução:**
1. Verifique seu role (deve ser admin, coordinator ou staff)
2. Faça logout e login novamente
3. Se persistir, contacte o administrador

### "O formulário não funciona / não salva"
**Solução:**
1. Verifique se está usando navegador atualizado
2. Limpe o cache do navegador (Ctrl+Shift+Delete)
3. Tente outro navegador
4. Verifique o console do navegador (F12) para ver erros

### "As disciplinas desapareceram depois que salvei"
**Isso NÃO deve acontecer.** Se acontecer:
1. Recarregue a página (F5)
2. Se persistir, contacte o administrador

---

## ✨ Resumo Rápido

| Ação | Como Fazer |
|------|-----------|
| **Preparar dados** | SSH: Passos 1-2 |
| **Acessar painel** | Planejamento → Professores → 📚 |
| **Habilitar disciplina** | Marque o checkbox |
| **Desabilitar disciplina** | Desmarque o checkbox |
| **Salvar** | Clique "Salvar Disciplinas" |
| **Cancelar** | Clique "Cancelar" |
| **Ver resumo** | Olhe para o lado direito (Resumo) |

---

## 🎓 Exemplo Prático Completo

**Cenário:** Você quer que Reginaldo também possa lecionar "Português" (que não estava antes)

### Passo a Passo:
1. SSH: `php artisan db:seed --class=ProfessorSubjectsSeeder` ✓
2. Navegador: Faça login
3. Navegador: Planejamento → Professores
4. Navegador: Clique no 📚 de Reginaldo
5. Navegador: Veja que "Habilitado em: 8" (lado direito)
6. Navegador: Role até encontrar "Português" na lista "Todas as Disciplinas"
7. Navegador: Marque o checkbox de "Português"
8. Navegador: Clique "Salvar Disciplinas"
9. ✓ Sucesso! Veja a mensagem verde
10. Navegador: Note que agora "Habilitado em: 9" (aumentou)
11. Navegador: Veja "Português" aparecer na seção "Disciplinas Habilitadas"

**Pronto!** Reginaldo agora está habilitado em 9 disciplinas (incluindo Português).

---

## 📞 Precisa de Mais Ajuda?

- Leia: **COMO_USAR_DISCIPLINAS.md** (versão mais detalhada)
- Leia: **QUICK_START.md** (versão rápida)
- Contacte: Administrador do sistema

---

**Status:** ✅ Pronto para Usar
**Data:** 2024
