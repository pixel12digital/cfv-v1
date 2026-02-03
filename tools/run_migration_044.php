<?php
/**
 * Script para executar migration 044: permitir múltiplos tipos de aula prática
 * Altera practice_type de ENUM para VARCHAR para armazenar valores separados por vírgula
 */

require_once __DIR__ . '/../includes/database.php';

try {
    $db = Database::getInstance();
    
    // Verificar tipo atual da coluna
    $result = $db->query("SHOW COLUMNS FROM lessons LIKE 'practice_type'");
    $col = $result->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        echo "Coluna 'practice_type' não existe. Execute migration 043 primeiro.\n";
        exit(1);
    }
    
    if (stripos($col['Type'], 'varchar') !== false) {
        echo "Coluna 'practice_type' já está como VARCHAR (migration 044 já aplicada).\n";
        exit(0);
    }
    
    // Alterar coluna de ENUM para VARCHAR
    $db->query("ALTER TABLE lessons MODIFY COLUMN practice_type VARCHAR(50) NULL DEFAULT NULL COMMENT 'Tipos de aula prática (separados por vírgula): rua, garagem, baliza'");
    
    echo "Migration 044 executada com sucesso!\n";
    echo "Coluna 'practice_type' alterada para VARCHAR(50) - permitindo múltiplos tipos.\n";
    
} catch (Exception $e) {
    echo "Erro ao executar migration: " . $e->getMessage() . "\n";
    exit(1);
}
