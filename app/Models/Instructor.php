<?php

namespace App\Models;

class Instructor extends Model
{
    protected $table = 'instructors';

    public function findByCfc($cfcId, $activeOnly = true)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE cfc_id = ?";
            $params = [$cfcId];
            
            if ($activeOnly) {
                $sql .= " AND is_active = 1";
            }
            
            $sql .= " ORDER BY name ASC";
            
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            // Se a tabela não existe (42S02), retornar array vazio
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false) {
                error_log("[Instructor::findByCfc] Tabela '{$this->table}' não existe. Retornando array vazio.");
                return [];
            }
            // Re-throw outros erros
            throw $e;
        }
    }

    public function findActive($cfcId)
    {
        return $this->findByCfc($cfcId, true);
    }

    /**
     * Busca instrutores ativos e com credencial válida (para agenda)
     * Retorna array vazio se a tabela não existir
     */
    public function findAvailableForAgenda($cfcId)
    {
        try {
            $today = date('Y-m-d');
            
            $sql = "SELECT * FROM {$this->table} 
                    WHERE cfc_id = ? 
                      AND is_active = 1
                      AND (credential_expiry_date IS NULL OR credential_expiry_date >= ?)
                    ORDER BY name ASC";
            
            $stmt = $this->query($sql, [$cfcId, $today]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            // Se a tabela não existe (42S02), retornar array vazio
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false) {
                error_log("[Instructor::findAvailableForAgenda] Tabela '{$this->table}' não existe. Retornando array vazio.");
                return [];
            }
            // Re-throw outros erros
            throw $e;
        }
    }

    /**
     * Verifica se credencial está vencida
     */
    public function isCredentialExpired($instructor)
    {
        if (empty($instructor['credential_expiry_date'])) {
            return false; // Sem data = não vencida
        }
        
        $expiryDate = new \DateTime($instructor['credential_expiry_date']);
        $today = new \DateTime();
        
        return $expiryDate < $today;
    }

    /**
     * Busca instrutor com detalhes completos (incluindo endereço)
     * Retorna null se a tabela não existir
     */
    public function findWithDetails($id)
    {
        try {
            $stmt = $this->query(
                "SELECT i.*,
                        c.name as city_name,
                        s.uf as state_uf
                 FROM {$this->table} i
                 LEFT JOIN cities c ON i.address_city_id = c.id
                 LEFT JOIN states s ON i.address_state_id = s.id
                 WHERE i.id = ?",
                [$id]
            );
            return $stmt->fetch();
        } catch (\PDOException $e) {
            // Se a tabela não existe (42S02), retornar null
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false) {
                error_log("[Instructor::findWithDetails] Tabela '{$this->table}' não existe. Retornando null.");
                return null;
            }
            // Re-throw outros erros
            throw $e;
        }
    }
}
