<?php
/**
 * Service Worker Wrapper
 * 
 * Este arquivo serve o conteúdo do sw.js com o Content-Type correto
 * Necessário quando o arquivo físico não está acessível diretamente
 */

// Ler o conteúdo do sw.js
$swFile = __DIR__ . '/sw.js';

if (file_exists($swFile)) {
    // Definir headers corretos para Service Worker
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: /');
    header('Cache-Control: public, max-age=3600');
    
    // Ler e exibir o conteúdo
    readfile($swFile);
    exit;
} else {
    // Se o arquivo não existe, retornar 404
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Service Worker not found';
    exit;
}
