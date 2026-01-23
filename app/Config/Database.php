<?php

namespace App\Config;

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        $config = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'dbname' => $_ENV['DB_NAME'] ?? 'cfc_db',
            'charset' => 'utf8mb4',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? ''
        ];

        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            // Para conexões remotas, pode ser necessário configurar timeout
            if ($config['host'] !== 'localhost' && $config['host'] !== '127.0.0.1') {
                $options[\PDO::ATTR_TIMEOUT] = 10;
            }
            
            $this->connection = new \PDO($dsn, $config['username'], $config['password'], $options);
        } catch (\PDOException $e) {
            // Não usar die() para evitar quebrar o fluxo - logar e lançar exceção
            error_log('[Database] Erro na conexão: ' . $e->getMessage());
            
            // Se for erro de limite de conexões, mostrar mensagem amigável
            if (strpos($e->getMessage(), 'max_connections_per_hour') !== false) {
                throw new \PDOException('Limite de conexões ao banco de dados excedido. Por favor, aguarde alguns minutos e tente novamente.');
            }
            
            throw $e;
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function __clone() {}
    public function __wakeup() {}
}
