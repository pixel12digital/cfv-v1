<?php

namespace App\Models;

use App\Config\Database;

class FirstAccessToken
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Cria um token de primeiro acesso.
     * Retorna o token em texto puro (única vez que pode ser mostrado); persiste apenas o hash.
     *
     * @param int $userId ID do usuário (aluno)
     * @param int $expiresInHours Horas até expirar (padrão 48)
     * @return string|null Token em texto puro ou null em caso de falha
     */
    public function create($userId, $expiresInHours = 48)
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInHours} hours"));

        $stmt = $this->db->prepare("
            INSERT INTO first_access_tokens (user_id, token_hash, expires_at)
            VALUES (?, ?, ?)
        ");
        if ($stmt->execute([$userId, $tokenHash, $expiresAt])) {
            return $token;
        }
        return null;
    }

    /**
     * Busca token válido pelo valor em texto puro.
     *
     * @param string $plainToken Token em texto puro (como vem na URL)
     * @return array|null Linha com id, user_id, etc. ou null
     */
    public function findByPlainToken($plainToken)
    {
        if (empty($plainToken) || strlen($plainToken) !== 64) {
            return null;
        }
        $tokenHash = hash('sha256', $plainToken);
        $stmt = $this->db->prepare("
            SELECT * FROM first_access_tokens
            WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
        ");
        $stmt->execute([$tokenHash]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Marca o token como usado.
     *
     * @param int $tokenId ID do registro do token
     * @return bool
     */
    public function markAsUsed($tokenId)
    {
        $stmt = $this->db->prepare("
            UPDATE first_access_tokens SET used_at = NOW() WHERE id = ?
        ");
        return $stmt->execute([$tokenId]);
    }
}
