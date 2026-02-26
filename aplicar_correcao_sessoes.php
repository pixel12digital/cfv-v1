<?php
// SCRIPT PARA APLICAR A CORREÇÃO - Criar tabela sessoes
// Executar este script para corrigir o problema de login

require_once 'includes/config.php';
require_once 'includes/database.php';

echo "<h2>🔧 APLICANDO CORREÇÃO - Criando tabela 'sessoes'</h2>";

try {
    $db = db();
    
    // 1. Verificar se tabela já existe
    echo "<h3>1. Verificando se tabela 'sessoes' já existe</h3>";
    $checkTable = $db->fetch("SHOW TABLES LIKE 'sessoes'");
    
    if ($checkTable) {
        echo "✅ Tabela 'sessoes' já existe<br>";
    } else {
        echo "❌ Tabela 'sessoes' não existe. Criando...<br>";
        
        // 2. Criar tabela sessoes
        echo "<h3>2. Criando tabela 'sessoes'</h3>";
        
        $sql = "
        CREATE TABLE `sessoes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `usuario_id` int(11) NOT NULL COMMENT 'ID do usuário dono da sessão',
          `token` varchar(255) NOT NULL COMMENT 'Token único da sessão',
          `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address de origem',
          `user_agent` text DEFAULT NULL COMMENT 'User agent do navegador',
          `expira_em` timestamp NOT NULL COMMENT 'Data/hora de expiração da sessão',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora de criação',
          PRIMARY KEY (`id`),
          KEY `idx_usuario_id` (`usuario_id`),
          KEY `idx_token` (`token`),
          KEY `idx_expira_em` (`expira_em`),
          KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de sessões do sistema de autenticação'
        ";
        
        $db->query($sql);
        echo "✅ Tabela 'sessoes' criada com sucesso<br>";
        
        // 3. Verificar estrutura
        echo "<h3>3. Verificando estrutura da tabela criada</h3>";
        $structure = $db->fetchAll("DESCRIBE sessoes");
        
        echo "<table border='1'>";
        echo "<tr><th>Coluna</th><th>Tipo</th><th>Extra</th></tr>";
        
        foreach ($structure as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>✅ CORREÇÃO APLICADA COM SUCESSO</h3>";
    echo "<div style='background: #e6ffe6; padding: 10px; border: 1px solid green;'>";
    echo "Tabela 'sessoes' está pronta para uso.<br>";
    echo "O sistema de autenticação agora pode registrar e validar sessões corretamente.";
    echo "</div>";
    
} catch (Exception $e) {
    echo "❌ Erro ao aplicar correção: " . $e->getMessage() . "<br>";
    echo "<div style='background: #ffe6e6; padding: 10px; border: 1px solid red;'>";
    echo "Verifique se você tem permissões para criar tabelas no banco de dados.";
    echo "</div>";
}

?>
