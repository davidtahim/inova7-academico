#!/bin/bash

echo "🧪 Test Script - User Management Module"
echo "========================================"
echo ""

# Cores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Teste 1: Verificar se views existem
echo -e "${YELLOW}📋 Teste 1: Verificar existência das views...${NC}"
VIEWS=(
    "resources/views/admin/users-index.blade.php"
    "resources/views/admin/users-create.blade.php"
    "resources/views/admin/users-edit.blade.php"
    "resources/views/admin/users-show.blade.php"
)

for view in "${VIEWS[@]}"; do
    if [ -f "$view" ]; then
        echo -e "${GREEN}✓${NC} $view"
    else
        echo -e "${RED}✗${NC} $view (NÃO ENCONTRADO)"
    fi
done
echo ""

# Teste 2: Verificar controller
echo -e "${YELLOW}📋 Teste 2: Verificar se controller chama as views corretas...${NC}"
CONTROLLER="app/Http/Controllers/UserManagementController.php"

echo "Procurando por 'admin.users-create'..."
if grep -q "view('admin.users-create'" "$CONTROLLER"; then
    echo -e "${GREEN}✓${NC} view('admin.users-create') encontrado"
else
    echo -e "${RED}✗${NC} view('admin.users-create') NÃO encontrado"
fi

echo "Procurando por 'admin.users-show'..."
if grep -q "view('admin.users-show'" "$CONTROLLER"; then
    echo -e "${GREEN}✓${NC} view('admin.users-show') encontrado"
else
    echo -e "${RED}✗${NC} view('admin.users-show') NÃO encontrado"
fi

echo "Procurando por 'admin.users-edit'..."
if grep -q "view('admin.users-edit'" "$CONTROLLER"; then
    echo -e "${GREEN}✓${NC} view('admin.users-edit') encontrado"
else
    echo -e "${RED}✗${NC} view('admin.users-edit') NÃO encontrado"
fi

echo ""
echo -e "${YELLOW}📋 Teste 3: Verificar rotas...${NC}"
if grep -q "admin.users" "routes/web.php"; then
    echo -e "${GREEN}✓${NC} Rotas de user management encontradas"
else
    echo -e "${RED}✗${NC} Rotas de user management NÃO encontradas"
fi
echo ""

echo -e "${YELLOW}📋 Teste 4: Verificar User model methods...${NC}"
if grep -q "isAdminTi" "app/Models/User.php"; then
    echo -e "${GREEN}✓${NC} Método isAdminTi() encontrado"
else
    echo -e "${RED}✗${NC} Método isAdminTi() NÃO encontrado"
fi

if grep -q "roleOptions" "app/Models/User.php"; then
    echo -e "${GREEN}✓${NC} Método roleOptions() encontrado"
else
    echo -e "${RED}✗${NC} Método roleOptions() NÃO encontrado"
fi
echo ""

echo -e "${YELLOW}📋 Resumo de Arquivos Criados/Modificados:${NC}"
echo "✨ CRIADOS:"
echo "  - resources/views/admin/users-index.blade.php (180+ linhas)"
echo "  - resources/views/admin/users-create.blade.php (200+ linhas)"
echo "  - resources/views/admin/users-edit.blade.php (250+ linhas)"
echo "  - resources/views/admin/users-show.blade.php (280+ linhas)"
echo ""
echo "✏️  MODIFICADOS:"
echo "  - app/Http/Controllers/UserManagementController.php (3 mudanças em view())"
echo ""

echo -e "${GREEN}✅ Verificação concluída!${NC}"
echo ""
echo "🚀 Próximos passos:"
echo "   1. Iniciar servidor: php artisan serve"
echo "   2. Testar acesso: http://localhost:8000/admin/planejamento/usuarios"
echo "   3. Verificar se a tabela users tem as colunas esperadas"
echo ""
