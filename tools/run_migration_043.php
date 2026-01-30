<?php
/**
 * Script para executar migration 043: adicionar practice_type à tabela lessons
 */

require_once __DIR__ . '/../includes/database.php';

try {
    $db = Database::getInstance();
    
    // Verificar se coluna já existe
    $result = $db->query("SHOW COLUMNS FROM lessons LIKE 'practice_type'");
    if ($result->fetch()) {
        echo "Coluna 'practice_type' já existe na tabela lessons.\n";
        exit(0);
    }
    
    // Adicionar coluna
    $db->query("ALTER TABLE lessons ADD COLUMN practice_type ENUM('rua', 'garagem', 'baliza') NULL DEFAULT NULL COMMENT 'Tipo de aula prática: rua, garagem ou baliza' AFTER type");
    
    echo "Migration 043 executada com sucesso!\n";
    echo "Coluna 'practice_type' adicionada à tabela lessons.\n";
    
} catch (Exception $e) {
    echo "Erro ao executar migration: " . $e->getMessage() . "\n";
    exit(1);
}
