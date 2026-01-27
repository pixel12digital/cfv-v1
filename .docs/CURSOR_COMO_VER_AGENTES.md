# Como Exibir Todos os Agentes no Cursor

**Objetivo:** Ver e gerenciar agentes, subagentes, skills e ferramentas MCP.

---

## Como exibir a lista de agentes (passo a passo)

### Opção A — Skills do projeto (seus “agentes” por tarefa)

1. Abra o **Explorador de arquivos** (ícone de pastas na barra lateral esquerda ou `Ctrl+Shift+E`).
2. No projeto, abra a pasta **`.cursor`** → **`skills`**.
3. Cada subpasta (ex.: `cfc-pwa-diagnostico`, `cfc-pwa-config-layout`) é um agent skill; dentro há o `SKILL.md`.

**Atalho:** `Ctrl+P` → digite `.cursor/skills` → Enter para ir à pasta de skills.

---

### Opção B — Lista no chat (menu de comandos e skills)

1. Abra o **Chat** (`Ctrl+L`).
2. Na caixa de mensagem, digite **`/`** (barra).
3. Abre o **menu de comandos** com skills e comandos; role a lista para ver todos.

Use **`/`** sempre que quiser “exibir” os agentes/skills disponíveis por nome.

---

### Opção C — Servidores MCP (ferramentas externas)

1. Abra **Configurações:** `Ctrl+,` ou menu **File → Preferences → Settings**.
2. Na busca das configurações, digite **`Cursor MCP`** ou **`Features`**.
3. No menu lateral, vá em **Cursor** ou **Features** e depois **MCP**.
4. Ali aparece a lista de servidores MCP e **Available Tools**.

**Se aparecer “Multi Cursor Paste”:** a busca por “MCP” pegou outra opção. Use **“Cursor MCP”** ou **“Features”** e escolha a seção **MCP** na árvore de configurações.

---

### Opção D — Subagentes customizados (`.cursor/agents/`)

1. **`Ctrl+Shift+P`** → digite **“Open Folder”** ou **“Reveal in File Explorer”**.
2. Abra a pasta **`C:\Users\charl\.cursor\agents`** (ou seu usuário).
3. Cada `.md` nessa pasta é um subagente customizado.

**No projeto:** veja a pasta **`.cursor/agents/`** no explorador de arquivos (se existir).

---

## 1. Agentes integrados (subagentes)

O Cursor traz **3 subagentes** que o agente principal usa automaticamente:

| Subagente | Função |
|-----------|--------|
| **Explore** | Pesquisa e analisa o código |
| **Bash** | Executa comandos no terminal |
| **Browser** | Controla o navegador via MCP |

**Onde “ver”:** Eles aparecem quando o agente os usa (ex.: ao pedir busca no código ou comando no terminal). Não existe uma tela só para listar esses 3; você os vê em uso no chat/Composer.

---

## 2. Subagentes customizados (seus agentes)

**Onde ficam configurados:**

- **No projeto:** pasta `.cursor/agents/`
- **No usuário:** `~/.cursor/agents/` (Windows: `C:\Users\SEU_USUARIO\.cursor\agents\`)

**Como ver os que você tem:**

1. Abrir o **Explorador de arquivos** do Cursor (barra lateral esquerda).
2. Ir em `.cursor/agents/` no projeto **ou** em `~/.cursor/agents/` no seu usuário.
3. Cada arquivo `.md` nessa pasta é um subagente.

**Como abrir a pasta pelo Cursor:**

- **`Ctrl+Shift+P`** → digite **"Reveal in File Explorer"** ou **"Open Folder"**.
- Ou: **Arquivo → Abrir Pasta** e escolher  
  `C:\Users\SEU_USUARIO\.cursor\agents`  
  para ver seus agentes de usuário.

---

## 3. Servidores MCP (ferramentas/“agentes” externos)

**Onde ver todos:**

1. **`Ctrl + ,`** (abre Configurações).
2. No campo de busca, digite **MCP**.
3. Ou ir em **Cursor Settings → Features → MCP** (Features → MCP).

Ali você vê:

- Lista de servidores MCP.
- Botão **"Refresh"** (canto superior direito) para atualizar a lista de ferramentas.
- **"Available Tools"** com as ferramentas de cada servidor.

**Alternativa por arquivos:**

- **Projeto:** `.cursor/mcp.json`
- **Global:** `C:\Users\SEU_USUARIO\.cursor\mcp.json`

Abra esse JSON para ver quais servidores estão configurados.

---

## 4. Skills (habilidades do agente)

**Onde ficam:** Em arquivos `SKILL.md` (por exemplo em `$CODEX_HOME/skills`, ou pastas que você configurou para skills).

**Como usar / “ver” em ação:** No **chat**, digite **`/`** (barra) para abrir o menu de comandos; aí aparecem as skills e comandos disponíveis.

---

## 5. Resumo rápido: onde ver o quê

| O que você quer ver | Onde |
|---------------------|------|
| Subagentes integrados (Explore, Bash, Browser) | Em uso no chat/Composer; não há lista separada |
| **Seus** subagentes customizados | Pasta `.cursor/agents/` (projeto) ou `~/.cursor/agents/` (usuário) |
| Servidores e ferramentas MCP | **`Ctrl + ,`** → buscar **MCP** → **Features → MCP** |
| Skills e comandos por nome | No chat, digitar **`/`** |

---

## 6. Atalhos úteis

| Atalho | Ação |
|--------|------|
| **`Ctrl + ,`** | Abrir Configurações (para ir em MCP) |
| **`Ctrl + Shift + P`** | Paleta de comandos (buscar "MCP", "agents", "Open Folder") |
| **`Ctrl + L`** | Abrir/fechar o Chat (painel do agente) |
| **`Ctrl + I`** | Abrir o Composer (agente em modo multi-arquivo) |
| **`/`** no chat | Menu de comandos e skills |

---

## 7. Ver “todos os agentes” na prática

Para **exibir tudo que funciona como “agentes”** no seu Cursor:

1. **Configurações → Features → MCP**  
   → lista de servidores MCP e ferramentas (“Available Tools”).

2. **Abrir a pasta de agentes:**
   - **`Ctrl + Shift + P`** → **"File: Open Folder"**.
   - Escolher `C:\Users\charl\.cursor\agents` (ou seu usuário).
   - Ver os `.md` = seus subagentes customizados.

3. **No chat, `/`**  
   → ver skills e comandos que o agente pode usar.

Com isso você cobre: subagentes do Cursor, seus subagentes, MCP e skills no mesmo lugar conceitual de “agentes que você possui”.

---

## 8. Sua lista de agentes neste projeto

**Skills (`.cursor/skills/`):**

| Nome | Arquivo | Uso |
|------|---------|-----|
| cfc-pwa-diagnostico | `.cursor/skills/cfc-pwa-diagnostico/SKILL.md` | Diagnóstico PWA, erros 404, “não aparece instalar” |
| cfc-pwa-config-layout | `.cursor/skills/cfc-pwa-config-layout/SKILL.md` | Config e layout PWA (manifest, ícones, tema, botão instalar) |

Para **exibir** essa lista: abra a pasta `.cursor/skills/` no explorador ou digite **`/`** no chat e confira os nomes que aparecem.
