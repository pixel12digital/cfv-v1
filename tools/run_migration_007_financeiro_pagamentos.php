<?php
/**
 * Executa a migration 007 - Tabela financeiro_pagamentos (Contas a Pagar)
 * Usa o mesmo banco do admin (includes/config.php + database.php).
 *
 * Uso: php tools/run_migration_007_financeiro_pagamentos.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/database.php';

echo "=== Migration 007: financeiro_pagamentos (Contas a Pagar) ===\n\n";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    echo "1. Conexão: " . DB_HOST . " / " . DB_NAME . "\n";

    $stmt = $pdo->query("SELECT COUNT(*) as n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'financeiro_pagamentos'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($row['n']) && (int)$row['n'] > 0) {
        echo "2. Tabela 'financeiro_pagamentos' já existe. Nada a fazer.\n";
        echo "\n✅ Concluído.\n";
        exit(0);
    }

    echo "2. Criando tabela financeiro_pagamentos...\n";

    $sqlCreate = "
    CREATE TABLE IF NOT EXISTS financeiro_pagamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fornecedor VARCHAR(200) NOT NULL,
        descricao TEXT DEFAULT NULL,
        categoria ENUM('combustivel', 'manutencao', 'salarios', 'aluguel', 'energia', 'agua', 'telefone', 'internet', 'outros') DEFAULT 'outros',
        valor DECIMAL(10, 2) NOT NULL,
        status ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pendente',
        vencimento DATE NOT NULL,
        data_pagamento DATE DEFAULT NULL,
        forma_pagamento ENUM('pix', 'boleto', 'cartao', 'dinheiro', 'transferencia') DEFAULT 'pix',
        comprovante_url VARCHAR(500) DEFAULT NULL,
        observacoes TEXT DEFAULT NULL,
        criado_por INT DEFAULT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_vencimento (vencimento),
        INDEX idx_categoria (categoria),
        INDEX idx_status_vencimento (status, vencimento),
        FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sqlCreate);
    echo "   ✅ Tabela criada.\n";

    echo "3. Comentário na coluna categoria...\n";
    $pdo->exec("
    ALTER TABLE financeiro_pagamentos
    MODIFY COLUMN categoria ENUM('combustivel', 'manutencao', 'salarios', 'aluguel', 'energia', 'agua', 'telefone', 'internet', 'outros')
    COMMENT 'Categoria da despesa'
    ");
    echo "   ✅ ALTER executado.\n";

    echo "\n✅ Migration 007 executada com sucesso.\n";
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
