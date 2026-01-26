# Análise de Contraste - Dark Mode PWA Android

## Data: 2026-01-26
## Objetivo: Identificar e corrigir problemas de legibilidade no modo escuro

---

## 🔍 PROBLEMAS IDENTIFICADOS

### **Tela 1: Instrutor - Lista "Aulas de Hoje" (cards brancos)**

**Localização:** `instrutor/dashboard.php` (linhas 1056-1087) e `instrutor/dashboard-mobile.php` (linhas 342-370)

**Problema:**
- Cards têm `background: white` hardcoded (inline style)
- Horários usam `color: #1e293b` (hardcoded) ou `text-muted` (Bootstrap)
- Nomes de alunos usam `color: #1e293b` (hardcoded) ou `text-primary` (link)
- No dark mode, cards permanecem brancos mas textos ficam pálidos

**Código problemático:**
```php
// dashboard.php linha 1056
<div class="aula-item-mobile" style="... background: white; ...">
    <strong style="... color: #1e293b; ..."><?php echo date('H:i', ...); ?></strong>
    <div style="... color: #1e293b; ..."><?php echo $aula['aluno_nome']; ?></div>
</div>

// dashboard-mobile.php linha 367
<small class="text-muted"><?php echo date('H:i', ...); ?></small>
<h6 class="mb-1">
    <a href="#" class="text-primary text-decoration-none"><?php echo $aula['aluno_nome']; ?></a>
</h6>
```

**Solução proposta:**
1. Remover `background: white` hardcoded, usar `var(--theme-card-bg)`
2. Substituir `color: #1e293b` por `var(--theme-text)`
3. Garantir que `text-muted` use `var(--theme-text-muted)` no dark mode
4. Garantir que `text-primary` (links) use `var(--theme-link)` no dark mode

---

### **Tela 2: Instrutor - Dashboard "Próxima Aula"**

**Localização:** `instrutor/dashboard.php` (linha 709) e `instrutor/dashboard-mobile.php` (linha 431)

**Problema:**
- Card "Próxima Aula" usa `border-primary` e `bg-primary` (Bootstrap)
- Link "Iniciar Aula" usa `btn-primary` mas pode ter baixo contraste em card escuro
- Textos secundários no card podem estar pálidos

**Código problemático:**
```php
// dashboard.php linha 709
<div class="card border-primary shadow-sm h-100">
    <div class="card-header bg-primary text-white">
        ...
    </div>
    <div class="card-body">
        <a href="#" class="text-primary">Iniciar Aula</a>
    </div>
</div>
```

**Solução proposta:**
1. Garantir que `.card.border-primary` use tokens no dark mode (já implementado)
2. Garantir que links dentro de cards escuros usem `var(--theme-link)` com contraste adequado
3. Verificar se `text-muted` dentro do card usa token correto

---

### **Tela 3: Admin - Dashboard "Ações Rápidas"**

**Localização:** `admin/dashboard-mobile.php` (linhas 406-416)

**Problema:**
- Botões `btn-outline-primary` e `btn-outline-secondary` têm texto/ícone azul
- Em fundo escuro, o azul fica quase invisível
- Bordas também podem estar discretas demais

**Código problemático:**
```php
// dashboard-mobile.php linha 406
<a href="/admin/financeiro.php" class="btn btn-outline-primary btn-mobile w-100">
    <i class="fas fa-dollar-sign me-2"></i>
    Financeiro
</a>
<a href="/admin/relatorios.php" class="btn btn-outline-secondary btn-mobile w-100">
    <i class="fas fa-chart-bar me-2"></i>
    Relatórios
</a>
```

**Solução proposta:**
1. No dark mode, botões outline devem usar `var(--theme-link)` para borda e texto
2. Garantir contraste mínimo AA (4.5:1) para texto em botões outline
3. Ícones devem herdar a cor do texto do botão

---

### **Tela 4: Login (card escuro sobre fundo azul)**

**Localização:** `login.php` (linhas 834-838) e `assets/css/login.css`

**Problema:**
- Placeholder do email (`seu@email.com`) com baixo contraste
- Link "Esqueci minha senha" em roxo com contraste ruim
- Bordas dos inputs podem estar discretas

**Código problemático:**
```php
// login.php linha 838
<a href="forgot-password.php" class="forgot-password">Esqueci minha senha</a>

// CSS - placeholder
.form-control::placeholder {
    color: #adb5bd; /* Muito fraco em dark mode */
}
```

**Solução proposta:**
1. Placeholder deve usar `var(--theme-input-placeholder)` (já definido)
2. Link "Esqueci minha senha" deve usar `var(--theme-link)` no dark mode
3. Bordas de inputs devem usar `var(--theme-input-border)` com contraste adequado

---

## 📋 CORREÇÕES NECESSÁRIAS

### **1. Cards com background hardcoded**

**Arquivos afetados:**
- `instrutor/dashboard.php` (linhas 1056, 1060, 1079, 1083)
- `instrutor/dashboard-mobile.php` (cards de aulas)

**Ação:**
- Remover `background: white` inline
- Adicionar classe CSS que usa `var(--theme-card-bg)`
- Substituir `color: #1e293b` por `var(--theme-text)`

### **2. Textos com cores hardcoded**

**Arquivos afetados:**
- `instrutor/dashboard.php` (múltiplas linhas)
- `instrutor/dashboard-mobile.php` (horários e nomes)

**Ação:**
- Criar regra CSS para `.aula-item strong` usar `var(--theme-text)`
- Garantir que `.text-muted` use token no dark mode (já implementado)
- Garantir que links `.text-primary` usem `var(--theme-link)` (já implementado)

### **3. Botões outline no Admin**

**Arquivos afetados:**
- `admin/dashboard-mobile.php` (linhas 406-416)
- CSS de botões outline

**Ação:**
- Adicionar regras específicas para dark mode em botões outline
- Garantir contraste AA mínimo

### **4. Placeholders e links no Login**

**Arquivos afetados:**
- `login.php`
- `assets/css/login.css`
- `assets/css/simple-login.css`

**Ação:**
- Aplicar `var(--theme-input-placeholder)` nos placeholders
- Aplicar `var(--theme-link)` no link "Esqueci minha senha"

---

## 🎯 PRIORIZAÇÃO

### **Crítico (Ilegível)**
1. ✅ Horários e nomes nos cards de aulas (Tela 1)
2. ✅ Botões outline do Admin (Tela 3)
3. ✅ Placeholder do email (Tela 4)

### **Alto (Difícil leitura)**
4. ✅ Link "Iniciar Aula" no card escuro (Tela 2)
5. ✅ Link "Esqueci minha senha" (Tela 4)

### **Médio (Pode melhorar)**
6. Textos secundários em cards escuros
7. Bordas de inputs em dark mode

---

## 🔧 ABORDAGEM DE CORREÇÃO

### **Estratégia:**
1. **Manter tokens existentes** - Não alterar `theme-tokens.css`
2. **Aplicar tokens em elementos hardcoded** - Substituir cores fixas por variáveis
3. **Adicionar regras específicas** - Para casos que não herdam automaticamente
4. **Testar contraste** - Garantir AA mínimo (4.5:1) em todos os casos

### **Ordem de implementação:**
1. Cards de aulas (Tela 1) - Maior impacto
2. Botões outline (Tela 3) - Alta visibilidade
3. Login (Tela 4) - Primeira impressão
4. Ajustes finos (Tela 2) - Polimento

---

## ✅ VALIDAÇÃO

Após correções, validar:
- [ ] Contraste AA (4.5:1) em todos os textos principais
- [ ] Contraste AA em textos secundários (3:1 mínimo)
- [ ] Placeholders legíveis em dark mode
- [ ] Links visíveis e clicáveis
- [ ] Botões outline com borda e texto visíveis
- [ ] Cards adaptam cor de fundo no dark mode
- [ ] Nenhum texto "sumindo" ou ilegível

---

## 📝 NOTAS TÉCNICAS

### **Tokens já disponíveis:**
- `--theme-text` - Texto principal (#f1f5f9 em dark)
- `--theme-text-muted` - Texto secundário (#94a3b8 em dark)
- `--theme-link` - Links (#60a5fa em dark)
- `--theme-input-placeholder` - Placeholders (#94a3b8 em dark)
- `--theme-card-bg` - Fundo de cards (#1e293b em dark)
- `--theme-input-bg` - Fundo de inputs (#1e293b em dark)

### **Regras CSS já implementadas:**
- Dark mode para `.text-muted` ✅
- Dark mode para links `a:not(.btn)` ✅
- Dark mode para inputs ✅
- Dark mode para cards `.border-primary` ✅

### **O que falta:**
- Remover cores hardcoded em PHP (inline styles)
- Aplicar tokens em elementos específicos
- Garantir que cards usem `--theme-card-bg` no dark mode
- Melhorar contraste de botões outline

---

## 🚀 PRÓXIMOS PASSOS

1. **Fase 1:** Corrigir cards de aulas (remover hardcoded, aplicar tokens)
2. **Fase 2:** Corrigir botões outline (melhorar contraste)
3. **Fase 3:** Corrigir login (placeholders e links)
4. **Fase 4:** Testes e ajustes finos

---

**Status:** ✅ Análise completa - Pronto para implementação
