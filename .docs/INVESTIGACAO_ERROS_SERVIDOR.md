# Investigação de erros no servidor – comandos e checagens

Use estes comandos para localizar bugs e erros (PHP, Apache, app) no ambiente **local (XAMPP/Windows)** e em **produção (Linux)**.

---

## 1. Onde os erros são gravados

| Ambiente   | Arquivo de log PHP (app painel)     | Observação |
|-----------|--------------------------------------|------------|
| Produção  | `storage/logs/php_errors.log`        | Definido em `public_html/index.php` quando `APP_ENV=production` |
| Local     | Geralmente log do Apache/PHP (ver abaixo) | Se `APP_ENV` ≠ production, `display_errors=1` e o log padrão do PHP é usado |

Outros logs do projeto:
- `includes/config.php` (admin legado): `logs/php_errors.log` (na raiz do projeto, se existir pasta `logs/`)
- Admin API exames: `admin/logs/exames_simple_errors.log`, `admin/logs/exames_api_errors.log`

---

## 2. Comandos – **Windows (XAMPP)**

Execute no **PowerShell** ou **CMD**, a partir da raiz do projeto (ex.: `c:\xampp\htdocs\cfc-v.1`).

### Ver últimas linhas do log da aplicação (produção)
```powershell
cd c:\xampp\htdocs\cfc-v.1
Get-Content storage\logs\php_errors.log -Tail 100
```

### Acompanhar o log em tempo real (tail)
```powershell
Get-Content storage\logs\php_errors.log -Wait -Tail 50
```

### Log de erros do Apache (XAMPP)
```powershell
Get-Content c:\xampp\apache\logs\error.log -Tail 80
```

### Log de acesso Apache
```powershell
Get-Content c:\xampp\apache\logs\access.log -Tail 50
```

### Verificar se a pasta de log existe e tem permissão de escrita
```powershell
if (Test-Path storage\logs) { "OK: storage\logs existe" } else { "CRIAR: storage\logs" }
```

### Sintaxe PHP nos arquivos principais (evitar erros de parse)
```powershell
c:\xampp\php\php.exe -l public_html\index.php
c:\xampp\php\php.exe -l app\Bootstrap.php
```

### Variável de ambiente (qual ambiente o app acha que está)
```powershell
# Se existir .env na raiz
Get-Content .env | Select-String "APP_ENV"
```

---

## 3. Comandos – **Linux (servidor produção)**

Execute no **SSH** na pasta do projeto (ex.: `~/cfc-v.1` ou caminho do deploy).

### Ver últimas linhas do log da aplicação
```bash
cd /caminho/para/cfc-v.1   # ajuste o caminho
tail -n 100 storage/logs/php_errors.log
```

### Acompanhar o log em tempo real
```bash
tail -f storage/logs/php_errors.log
```

### Procurar erros Fatal / Exception / PDO nos últimos 500 registros
```bash
tail -n 500 storage/logs/php_errors.log | grep -i -E "fatal|exception|error|pdo|sqlstate"
```

### Log do PHP-FPM (se usar PHP-FPM)
```bash
# Caminho típico; pode variar (journald, arquivo, etc.)
sudo tail -n 80 /var/log/php*-fpm.log
# ou
sudo journalctl -u php*-fpm -n 50 --no-pager
```

### Log do Apache
```bash
sudo tail -n 80 /var/log/apache2/error.log
# ou Nginx:
sudo tail -n 80 /var/log/nginx/error.log
```

### Log de acesso (últimas requisições)
```bash
sudo tail -n 50 /var/log/apache2/access.log
```

### Verificar permissões da pasta de log
```bash
ls -la storage/logs/
# O usuário do Apache/PHP (www-data, apache, etc.) deve poder escrever
```

### Sintaxe PHP (lint) em arquivos críticos
```bash
php -l public_html/index.php
php -l app/Bootstrap.php
```

### Onde o PHP está gravando error_log (valor atual)
```bash
php -r "echo ini_get('error_log') ?: 'default (stderr/sapi)'; echo PHP_EOL;"
```

---

## 4. Checagens rápidas no código/servidor

| O que verificar | Como |
|-----------------|------|
| **APP_ENV** | Ver `.env` ou variável de ambiente: em produção o app usa `storage/logs/php_errors.log`. |
| **Erros na tela** | Em produção, `display_errors` deve ser 0; se aparecer stack trace, algo sobrescreve a config. |
| **404 / 500** | Ver `access.log` + `error.log` do Apache/Nginx e `storage/logs/php_errors.log` no horário do request. |
| **API 401 / redirect** | Ver `error_log` e logs da aplicação; verificar cookie de sessão e path no Network do navegador. |

---

## 5. Ordem sugerida para investigar um erro “no servidor”

1. **Confirmar ambiente:** `.env` ou `APP_ENV` (local vs production).
2. **Abrir o log certo:**  
   - Produção: `tail -f storage/logs/php_errors.log` (Linux) ou `Get-Content storage\logs\php_errors.log -Wait -Tail 50` (Windows).  
   - Local: também `storage\logs\php_errors.log` se existir; senão log do Apache.
3. **Reproduzir o problema** (acessar a URL ou ação que falha) e olhar as novas linhas do log.
4. **Cruzar com o horário** em `access.log` / DevTools (Network) para ver o request exato.
5. **Lint nos arquivos alterados:** `php -l <arquivo>` para descartar erro de sintaxe.

Se quiser, na próxima etapa podemos focar em um erro específico (ex.: 500 em uma rota, ou mensagem exata que aparece no log).
