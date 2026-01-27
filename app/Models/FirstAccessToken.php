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
        $r = $this->findWithReason($plainToken);
        return ($r && $r['result'] === 'ok') ? $r['row'] : null;
    }

    /**
     * Busca token e retorna motivo (para log e UI).
     *
     * @param string $plainToken Token em texto puro (como vem na URL)
     * @return array{row: array|null, result: 'ok'|'not_found'|'expired'|'used', hash_prefix: string, found_token_id: int|null, expires_at: string|null, used_at: string|null}
     */
    public function findWithReason($plainToken)
    {
        $now = date('Y-m-d H:i:s');
        $hashPrefix = '';
        $empty = ['row' => null, 'result' => 'not_found', 'hash_prefix' => '', 'found_token_id' => null, 'expires_at' => null, 'used_at' => null];
        if (empty($plainToken) || strlen($plainToken) !== 64) {
            return array_merge($empty, ['result' => 'not_found']);
        }
        $tokenHash = hash('sha256', $plainToken);
        $hashPrefix = substr($tokenHash, 0, 8);
        $stmt = $this->db->prepare("SELECT id, user_id, token_hash, expires_at, used_at FROM first_access_tokens WHERE token_hash = ?");
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return array_merge($empty, ['hash_prefix' => $hashPrefix, 'result' => 'not_found']);
        }
        $usedAt = $row['used_at'] ?? null;
        $expiresAt = $row['expires_at'] ?? null;
        if ($usedAt !== null && $usedAt !== '') {
            return ['row' => null, 'result' => 'used', 'hash_prefix' => $hashPrefix, 'found_token_id' => (int)$row['id'], 'expires_at' => $expiresAt, 'used_at' => $usedAt];
        }
        if ($expiresAt !== null && $expiresAt < $now) {
            return ['row' => null, 'result' => 'expired', 'hash_prefix' => $hashPrefix, 'found_token_id' => (int)$row['id'], 'expires_at' => $expiresAt, 'used_at' => $usedAt];
        }
        return ['row' => $row, 'result' => 'ok', 'hash_prefix' => $hashPrefix, 'found_token_id' => (int)$row['id'], 'expires_at' => $expiresAt, 'used_at' => $usedAt];
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
