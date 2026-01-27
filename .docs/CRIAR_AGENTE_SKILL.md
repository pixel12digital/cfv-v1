# Como Criar um Agente Skill no Cursor

**Objetivo:** Criar um Skill (habilidade) que o agente do Cursor usa automaticamente quando fizer sentido.

---

## Onde o Skill fica

Skills são pastas com um arquivo `SKILL.md`:

| Escopo | Caminho | Uso |
|--------|--------|-----|
| **Projeto** | `.cursor/skills/nome-do-skill/` | Só neste repositório |
| **Pessoal** | `~/.cursor/skills/nome-do-skill/` | Em todos os seus projetos |

**Não usar** `~/.cursor/skills-cursor/` — é reservado ao Cursor.

---

## Estrutura mínima

```
.cursor/skills/meu-skill/
└── SKILL.md
```

Obrigatório: **SKILL.md** com frontmatter YAML + texto em Markdown.

### Exemplo de SKILL.md

```markdown
---
name: meu-skill
description: Faz X e Y. Usar quando o usuário pedir Z ou mencionar A, B.
---

# Meu Skill

## O que fazer

1. Primeiro passo.
2. Segundo passo.
3. Resultado esperado.

## Exemplo

Entrada: "faça X"
Saída: [formato esperado]
```

---

## Campos obrigatórios no frontmatter

| Campo | Regras | Exemplo |
|-------|--------|---------|
| `name` | Até 64 caracteres, minúsculas, números e hífens | `cfc-pwa-diagnostico` |
| `description` | Até 1024 caracteres, em terceira pessoa | "Diagnostica problemas de PWA..." |

A **description** é o que o agente usa para decidir quando aplicar o skill. Inclua:
- **O quê** o skill faz
- **Quando** usar (palavras‑chave, tipo de pergunta, contexto)

---

## Como o Cursor usa o Skill

1. **Descoberta:** o agente lê as descriptions dos skills (projeto + pessoal).
2. **Uso automático:** quando a conversa combina com a description, o agente pode aplicar o skill.
3. **Comando `/`:** no chat, ao digitar `/`, aparecem skills e comandos; dá para chamar o skill por nome.

---

## Skills de exemplo neste projeto

Existem dois skills PWA:

1. **Diagnóstico PWA** — `.cursor/skills/cfc-pwa-diagnostico/SKILL.md`
   - Usar quando: "Instalar aplicativo" não aparecer, erros 404 em `sw.js` ou manifest, Service Worker ou `beforeinstallprompt`.

2. **PWA Configurações e Layout** — `.cursor/skills/cfc-pwa-config-layout/SKILL.md`
   - Usar quando: configurar manifest, ícones, tema, cores, botão instalar, footer de instalação, meta tags, layout mobile-first.

Para ver no Cursor: abra a pasta `.cursor/skills/` ou use `/` no chat e procure por "cfc" ou "pwa".

---

## Passo a passo para criar um novo Skill

1. **Definir nome**  
   Ex.: `gerar-commit-cfc` → pasta `.cursor/skills/gerar-commit-cfc/`.

2. **Criar a pasta**  
   Ex.: `mkdir .cursor/skills/gerar-commit-cfc` (ou via Cursor).

3. **Criar SKILL.md**  
   - Bloco YAML no topo com `name` e `description`.
   - Seções em Markdown com instruções, exemplos e, se quiser, links para outros arquivos (ex.: `reference.md`).

4. **Testar**  
   - Falar no chat sobre o assunto do skill e ver se o agente o aplica.
   - Ou usar `/` e escolher o skill pelo nome.

---

## Onde NÃO configurar

- **Configurações do editor** (`Ctrl + ,`) mostram preferências do editor (ex.: "Multi Cursor Paste" ao buscar "MCP").
- **MCP** (servidores e ferramentas externas) fica em **Cursor Settings → Features → MCP**, não dentro de um SKILL.md.
- **Skills** = arquivos `SKILL.md` nas pastas `.cursor/skills/` ou `~/.cursor/skills/`.

---

## Resumo

| O que você quer | Onde fazer |
|-----------------|------------|
| **Criar um Skill** | Criar `.cursor/skills/nome-do-skill/SKILL.md` (ou em `~/.cursor/skills/`) |
| **Ver Skills do projeto** | Abrir a pasta `.cursor/skills/` no Cursor |
| **Usar um Skill** | Conversar sobre o tema ou, no chat, digitar `/` e escolher o skill |
| **Ajustar MCP/ferramentas** | Cursor Settings → Features → MCP (não é Skill) |
