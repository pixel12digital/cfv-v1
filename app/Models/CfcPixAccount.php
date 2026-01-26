<?php

namespace App\Models;

class CfcPixAccount extends Model
{
    protected $table = 'cfc_pix_accounts';

    /**
     * Busca todas as contas PIX ativas de um CFC
     */
    public function findByCfc($cfcId, $activeOnly = true)
    {
        $db = \App\Config\Database::getInstance()->getConnection();
        
        $sql = "SELECT * FROM {$this->table} WHERE cfc_id = ?";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY is_default DESC, label ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$cfcId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Busca a conta padrão de um CFC
     */
    public function findDefaultByCfc($cfcId)
    {
        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM {$this->table} WHERE cfc_id = ? AND is_default = 1 AND is_active = 1 LIMIT 1");
        $stmt->execute([$cfcId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Busca uma conta por ID verificando se pertence ao CFC
     */
    public function findByIdAndCfc($id, $cfcId)
    {
        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM {$this->table} WHERE id = ? AND cfc_id = ?");
        $stmt->execute([$id, $cfcId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Define uma conta como padrão (e remove padrão das outras)
     */
    public function setAsDefault($id, $cfcId)
    {
        $db = \App\Config\Database::getInstance()->getConnection();
        $db->beginTransaction();
        
        try {
            // Remover padrão de todas as contas do CFC
            $stmt = $db->prepare("UPDATE {$this->table} SET is_default = 0 WHERE cfc_id = ?");
            $stmt->execute([$cfcId]);
            
            // Definir esta como padrão
            $stmt = $db->prepare("UPDATE {$this->table} SET is_default = 1 WHERE id = ? AND cfc_id = ?");
            $stmt->execute([$id, $cfcId]);
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Busca conta PIX com fallback para dados antigos (retrocompatibilidade)
     */
    public function getPixDataForCfc($cfcId, $accountId = null)
    {
        // Se accountId fornecido, buscar conta específica
        if ($accountId) {
            $account = $this->findByIdAndCfc($accountId, $cfcId);
            if ($account && $account['is_active']) {
                return $account;
            }
        }
        
        // Buscar conta padrão
        $default = $this->findDefaultByCfc($cfcId);
        if ($default) {
            return $default;
        }
        
        // Fallback: buscar qualquer conta ativa
        $accounts = $this->findByCfc($cfcId, true);
        if (!empty($accounts)) {
            return $accounts[0];
        }
        
        // Último fallback: retornar dados antigos da tabela cfcs (retrocompatibilidade)
        $cfcModel = new Cfc();
        $cfc = $cfcModel->find($cfcId);
        
        if ($cfc && !empty($cfc['pix_chave'])) {
            return [
                'id' => null,
                'label' => $cfc['pix_banco'] ?? 'PIX Principal',
                'bank_code' => null,
                'bank_name' => $cfc['pix_banco'] ?? null,
                'holder_name' => $cfc['pix_titular'] ?? 'Titular não informado',
                'pix_key' => $cfc['pix_chave'],
                'pix_key_type' => null,
                'note' => $cfc['pix_observacao'] ?? null
            ];
        }
        
        return null;
    }
}
