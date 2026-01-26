# Patch - Dark Mode Global PWA Android

## 📦 ENTREGÁVEIS

### **Arquivos Criados:**
1. `assets/css/theme-overrides.css` - Overrides globais e classes utilitárias
2. `public_html/assets/css/theme-overrides.css` - Cópia para produção

### **Arquivos Modificados:**
1. `includes/layout/mobile-first.php` - Adicionado theme-overrides.css
2. `instrutor/dashboard.php` - Removidos hardcodes, aplicadas classes, adicionado CSS
3. `admin/index.php` - Adicionados arquivos de tema
4. `login.php` - Adicionados arquivos de tema, corrigidos links

---

## 🔧 O QUE MUDOU

### **1. Classes Utilitárias Criadas:**
- `.bg-theme-card` - Fundo de cards usando token
- `.text-theme` - Texto principal usando token
- `.text-theme-muted` - Texto secundário usando token
- `.link-theme` - Links usando token

### **2. Overrides Globais (Dark Mode):**
- Botões outline (`.btn-outline-*`) - Usam `var(--theme-link)` no dark
- Cards de aulas (`.aula-item`, `.aula-item-mobile`) - Forçam `var(--theme-card-bg)`
- Inputs e placeholders - Usam tokens de input
- Links (`.forgot-password`, `.text-link`) - Usam `var(--theme-link)`
- Dropdowns - Usam tokens de superfície

### **3. Hardcodes Removidos:**
- `background: white` → `.bg-theme-card`
- `color: #1e293b` → `.text-theme`
- `color: #64748b` → `.text-theme-muted`
- Links roxos → `.link-theme`

---

## 📍 ONDE FICAM AS CORREÇÕES

**Arquivo Principal:** `assets/css/theme-overrides.css`

**Carregado em:**
- `includes/layout/mobile-first.php` (todas as páginas que usam este layout)
- `instrutor/dashboard.php` (dashboard desktop)
- `admin/index.php` (dashboard admin)
- `login.php` (página de login)

---

## ✅ PROBLEMAS RESOLVIDOS

1. ✅ Horários e nomes ilegíveis em cards (Tela 1)
2. ✅ Botões outline quase invisíveis (Tela 3)
3. ✅ Placeholders ilegíveis (Tela 4)
4. ✅ Links com baixo contraste (Tela 2 e 4)

---

## 🎯 PADRÃO PARA NOVAS TELAS

**Carregar:**
```php
<link rel="stylesheet" href="../assets/css/theme-tokens.css">
<link rel="stylesheet" href="../assets/css/theme-overrides.css">
```

**Usar classes:**
```html
<div class="bg-theme-card">
    <strong class="text-theme">Texto</strong>
    <small class="text-theme-muted">Horário</small>
    <a href="#" class="link-theme">Link</a>
</div>
```

**Evitar:**
- ❌ `style="background: white"`
- ❌ `style="color: #1e293b"`
- ✅ Classes utilitárias ou deixar overrides globais cuidarem

---

**Status:** ✅ Implementação completa
