<?php
/**
 * Migration 038 - Tabela financeiro_categorias_despesa (categorias editáveis para Contas a Pagar)
 * e alteração de financeiro_pagamentos.categoria de ENUM para VARCHAR(50).
 *
 * Uso: php tools/run_migration_038_categorias_despesa.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/database.php';

echo "=== Migration 038: financeiro_categorias_despesa ===\n\n";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    echo "1. Conexão: " . DB_HOST . " / " . DB_NAME . "\n";

    $stmt = $pdo->query("SELECT COUNT(*) as n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'financeiro_categorias_despesa'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($row['n']) && (int)$row['n'] > 0) {
        echo "2. Tabela 'financeiro_categorias_despesa' já existe.\n";
    } else {
        echo "2. Criando tabela financeiro_categorias_despesa...\n";
        $pdo->exec("
            CREATE TABLE financeiro_categorias_despesa (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(50) NOT NULL UNIQUE,
                nome VARCHAR(100) NOT NULL,
                ativo TINYINT(1) DEFAULT 1,
                ordem INT DEFAULT 0,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_ativo (ativo),
                INDEX idx_ordem (ordem)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "   ✅ Tabela criada.\n";

        $seed = [
            ['combustivel', 'Combustível', 1],
            ['manutencao', 'Manutenção', 2],
            ['salarios', 'Salários', 3],
            ['aluguel', 'Aluguel', 4],
            ['energia', 'Energia', 5],
            ['agua', 'Água', 6],
            ['telefone', 'Telefone', 7],
            ['internet', 'Internet', 8],
            ['outros', 'Outros', 99],
        ];
        $ins = $pdo->prepare("INSERT INTO financeiro_categorias_despesa (slug, nome, ativo, ordem) VALUES (?, ?, 1, ?)");
        foreach ($seed as $s) {
            $ins->execute([$s[0], $s[1], $s[2]]);
        }
        echo "3. Seed: " . count($seed) . " categorias inseridas.\n";
    }

    echo "4. Alterando financeiro_pagamentos.categoria para VARCHAR(50)...\n";
    $pdo->exec("ALTER TABLE financeiro_pagamentos MODIFY COLUMN categoria VARCHAR(50) DEFAULT 'outros'");
    echo "   ✅ Coluna alterada.\n";

    echo "\n✅ Migration 038 concluída.\n";
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
