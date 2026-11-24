# 🔧 FIX: Modal de Redefinição de Senha

## Problema

Ao tentar redefinir a senha de um usuário, era exibido erro:
```
validation.required
```

## Causa

O formulário original era apenas um botão de submit simples:
```html
<form action="{{ route('admin.users.reset-password', $user) }}" method="POST">
    @csrf
    <button type="submit">🔑 Redefinir Senha</button>
</form>
```

Mas o controller esperava campos POST:
```php
$validated = $request->validate([
    'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
]);
```

**Resultado**: Formulário não tinha `password` nem `password_confirmation`, causando erro de validação.

## Solução

Implementei um **Modal Bootstrap** que:
1. Solicita a **nova senha** (input)
2. Solicita **confirmação da senha** (input)
3. Valida antes de submeter
4. Exibe mensagens de erro se houver

### Mudanças

**Arquivos Modificados**:
- `resources/views/admin/users-edit.blade.php`
- `resources/views/admin/users-show.blade.php`

**Mudanças em Ambas**:
1. Substituir botão simples por um botão que abre modal:
```html
<!-- ANTES -->
<form action="{{ route('admin.users.reset-password', $user) }}" method="POST">
    @csrf
    <button type="submit" onclick="return confirm(...)">
        🔑 Redefinir Senha
    </button>
</form>

<!-- DEPOIS -->
<button type="button" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
    🔑 Redefinir Senha
</button>
```

2. Adicionar modal no final da view:
```html
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.users.reset-password', $user) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <!-- Input: password -->
                    <!-- Input: password_confirmation -->
                </div>
                <div class="modal-footer">
                    <button type="submit">🔑 Redefinir Senha</button>
                </div>
            </form>
        </div>
    </div>
</div>
```

## Fluxo Agora

1. **Clique**: Botão "🔑 Redefinir Senha"
2. **Modal Abre**: Pede nova senha e confirmação
3. **Validação**: Valida na hora (Bootstrap)
4. **Submit**: POST com `password` + `password_confirmation`
5. **Controller**: Recebe campos, valida e redefine
6. **Sucesso**: Modal fecha, mensagem exibida

## Teste

1. Acesse: `/admin/planejamento/usuarios/{id}/editar`
2. Clique: "🔑 Redefinir Senha" (na sidebar)
3. Modal aparece
4. Preencha:
   - Nova Senha: `Abc123def`
   - Confirmar: `Abc123def`
5. Clique: "🔑 Redefinir Senha"
6. **Esperado**: Modal fecha, mensagem "Senha redefinida com sucesso!"

## Validações do Modal

- ✅ Campos obrigatórios
- ✅ Mínimo 8 caracteres
- ✅ Deve ter letras e números
- ✅ Senhas devem ser iguais (rule: `confirmed`)
- ✅ Mensagens de erro exibidas em vermelho

## Bônus: User Experience

- Modal tem design consistente com o resto do sistema
- Alert informativo sobre consequências
- Confirm original removido (melhor UX)
- Campos de erro mostram validação inline

---

**Data**: 2026-11-24  
**Status**: ✅ CORRIGIDO E TESTADO
