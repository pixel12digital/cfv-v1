# Correção: .git/index.lock (permissão negada) e push falhando

## 1. O que é o erro do `.git/index.lock`?

- O Git cria um arquivo **`.git/index.lock`** enquanto roda comandos que alteram o índice (`git add`, `git commit`, etc.).
- **"Permission denied"** significa que algo está impedindo a criação desse arquivo, em geral:
  - **Cursor/VS Code** (ou outra IDE) com o repositório aberto e usando o Git internamente.
  - **Outro processo** (outro terminal, backup, antivírus) acessando a pasta `.git`.
  - **Permissões** da pasta `.git` ou do disco (mais raro no seu caso).

## Como corrigir o index.lock

1. **Feche o Cursor** (e qualquer outra IDE que use esse repositório).
2. Abra **PowerShell** ou **Git Bash** **fora** do Cursor (menu Iniciar).
3. Rode os comandos abaixo **dentro da pasta do projeto**:

```powershell
cd c:\xampp\htdocs\cfc-v.1
git add app/Controllers/UsuariosController.php app/Views/usuarios/form.php
git commit -m "fix(usuarios): Enviar no WhatsApp usa telefone do aluno e aviso sem telefone"
git push
```

4. Se ainda der "Permission denied":
   - Feche **todos** os programas que possam usar esse projeto (incluindo explorador de arquivos na pasta).
   - Clique com o botão direito no PowerShell → **Executar como administrador** e rode os mesmos comandos.

5. Se existir um **index.lock antigo** (de um processo que travou), você pode removê-lo **só se tiver certeza de que não há outro git em execução**:

```powershell
Remove-Item -Force "c:\xampp\htdocs\cfc-v.1\.git\index.lock" -ErrorAction SilentlyContinue
```

Depois rode de novo `git add`, `git commit`, `git push`.

---

## 2. O que é o erro de conexão no push?

A mensagem **"Failed to connect to 127.0.0.1 port 9"** indica que o Git (ou o sistema) está tentando usar um **proxy** em `127.0.0.1:9` para acessar o GitHub. Port 9 costuma ser configuração incorreta ou um proxy/VPN que não está ativo.

## Como corrigir o push (proxy)

Rode no **PowerShell** ou **Git Bash**:

```powershell
# Ver se há proxy configurado
git config --global --get http.proxy
git config --global --get https.proxy

# Remover proxy global (se estiver atrapalhando)
git config --global --unset http.proxy
git config --global --unset https.proxy
```

Se você **usa proxy** no trabalho, em vez de remover, ajuste para o endereço e porta corretos, por exemplo:

```powershell
git config --global http.proxy "http://proxy.empresa.com:8080"
git config --global https.proxy "http://proxy.empresa.com:8080"
```

Depois teste de novo:

```powershell
cd c:\xampp\htdocs\cfc-v.1
git push
```

---

## Resumo rápido

| Problema              | Causa provável                    | Ação principal                                                |
|-----------------------|-----------------------------------|---------------------------------------------------------------|
| `.git/index.lock`     | IDE/outro processo segurando .git | Fechar Cursor, rodar git no PowerShell/Git Bash (ou como admin). |
| Push 127.0.0.1 port 9 | Proxy global errado               | `git config --global --unset http.proxy` e `https.proxy`, depois `git push`. |

Depois de corrigir os dois, você pode abrir o Cursor de novo e continuar trabalhando normalmente.
