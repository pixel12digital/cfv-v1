# MISSÃO 500 – Diagnóstico para Charles (produção)

**Objetivo:** Isolar se o erro 500 vem do `.htaccess` ou de outra camada, com risco mínimo.

---

## 1) TESTE BINÁRIO – Desabilitar .htaccess

**No servidor (SSH), rodar:**

```bash
cd /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel

# Renomear .htaccess (desabilitar temporariamente)
mv .htaccess .htaccess.bak

# Confirmar que foi renomeado
ls -la .htaccess*
```

**No navegador, testar cada URL e anotar o resultado:**

| URL | Resultado |
|-----|-----------|
| https://painel.cfcbomconselho.com.br/admin/diagnostico-minimo.php | (OK ou 500) |
| https://painel.cfcbomconselho.com.br/admin/diagnostico-500.php | (OK ou 500) |

**IMPORTANTE:** Se funcionou, **restaurar o .htaccess** antes de sair:
```bash
mv .htaccess.bak .htaccess
```

---

## 2) O QUE DEVOLVER AO CURSOR

Copiar e preencher (sem texto extra):

```
=== RESULTADO DO TESTE COM .HTACCESS RENOMEADO ===

/admin/diagnostico-minimo.php = (OK/500)
/admin/diagnostico-500.php = (OK/500)

Comando usado: mv .htaccess .htaccess.bak
Caminho exato: /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel
```

---

## 3) SE AINDA DER 500 (mesmo sem .htaccess)

Rodar e devolver a saída:

```bash
# Versão do PHP
php -v

# Onde o PHP grava log
php -i | grep -i error_log

# Permissões da pasta do painel
ls -la /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel

# Últimas linhas do log (ajustar caminho se necessário)
tail -50 /home/u502697186/domains/cfcbomconselho.com.br/public_html/logs/php_errors.log
tail -50 /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel/storage/logs/php_errors.log
```

Depois de rodar o `tail`, dar **refresh** em `/admin/diagnostico-minimo.php` e rodar o `tail` de novo para ver se aparece linha nova.

---

## 4) CONTEÚDO DO .HTACCESS (se solicitado)

```bash
cat -n /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel/.htaccess | head -120
```
