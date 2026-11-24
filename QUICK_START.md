# 🚀 QUICK START - Painel de Disciplinas do Professor

## ⚡ 3 Passos para Começar

### 1️⃣ Preparar o Professor (via SSH)

```bash
cd /seu/caminho/inova7-academico
php artisan tinker
```

Dentro do tinker:
```php
App\Models\Professor::where('name', 'like', '%Reginaldo%')->first()
  ? echo "✓ Professor existe"
  : App\Models\Professor::create([
      'registration' => 'UNI727777',
      'name' => 'Reginaldo Borba',
      'email' => 'reginaldo@inova7.local',
      'qualification' => 'Mestre',
      'active' => true
    ]);
exit;
```

### 2️⃣ Executar o Seeder

```bash
php artisan db:seed --class=ProfessorSubjectsSeeder
```

Output esperado:
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

### 3️⃣ Acessar no Navegador

1. Login como admin
2. **Planejamento → Professores**
3. Clique no botão **📚** (livros) do Reginaldo
4. Marque/desmarque disciplinas
5. Clique **Salvar Disciplinas**

## ✅ Pronto!

Você acabou de usar o painel de disciplinas! 🎉

---

## 🔍 Verificar se Funcionou

```bash
php artisan tinker

App\Models\Professor::where('name', 'like', '%Reginaldo%')
    ->first()
    ->subjects()
    ->pluck('name');

# Deve mostrar as 8 disciplinas
```

---

## 📌 Lembretes

✓ Apenas admin/coordinator/staff podem acessar  
✓ Os dados são salvos automaticamente  
✓ Você pode atualizar quantas vezes quiser  
✓ Nenhuma disciplina tem limite

---

## 🆘 Problemas?

**"Professor não encontrado"**
→ Execute o passo 1 novamente

**"Nenhuma disciplina aparece"**
→ Execute: `php artisan db:seed --class=DatabaseSeeder`

**"Erro 403 - Acesso Negado"**
→ Login como admin/coordenador/staff

**"Form não salva"**
→ Verifique storage/logs/laravel.log

---

## 📚 Documentação Completa

Veja os arquivos markdown para mais detalhes:
- COMO_USAR_DISCIPLINAS.md
- FEATURES_DISCIPLINAS_PROFESSOR.md
- VALIDATION_DISCIPLINAS.md

---

**Status:** ✅ Pronto para Uso
