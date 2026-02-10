<?php
/**
 * Limpa OPcache do PHP (para ver alterações após git pull).
 * Uso: https://painel.seudominio.com.br/tools/clear-opcache.php?clear=1
 * (Se o Document Root for a raiz do repo; senão use public_html/tools/clear-opcache.php?clear=1)
 * Recomendado: remover ou restringir o arquivo após o uso.
 */
header('Content-Type: text/plain; charset=utf-8');

if (!isset($_GET['clear']) || $_GET['clear'] !== '1') {
    echo "Uso: ?clear=1\n";
    exit;
}

if (!function_exists('opcache_reset')) {
    echo "OPcache não está habilitado ou opcache_reset não disponível.\n";
    exit;
}

if (opcache_reset()) {
    echo "OPcache limpo. Recarregue a página de categorias-despesa (F5).\n";
} else {
    echo "Falha ao limpar OPcache.\n";
}
