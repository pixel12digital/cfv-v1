<?php

namespace App\Models;

class Student extends Model
{
    protected $table = 'students';

    public function findByCfc($cfcId, $search = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE cfc_id = ?";
        $params = [$cfcId];
        
        if ($search) {
            $searchTerm = "%{$search}%";
            $sql .= " AND (
                name LIKE ? OR 
                full_name LIKE ? OR 
                cpf LIKE ? OR 
                phone LIKE ? OR 
                phone_primary LIKE ? OR
                email LIKE ?
            )";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        $sql .= " ORDER BY COALESCE(full_name, name) ASC";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function findByCpf($cfcId, $cpf)
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        $stmt = $this->query(
            "SELECT * FROM {$this->table} WHERE cfc_id = ? AND cpf = ?",
            [$cfcId, $cpf]
        );
        return $stmt->fetch();
    }

    public function findByEmail($cfcId, $email)
    {
        $stmt = $this->query(
            "SELECT * FROM {$this->table} WHERE cfc_id = ? AND email = ?",
            [$cfcId, $email]
        );
        return $stmt->fetch();
    }

    public function getEnrollments($studentId)
    {
        $stmt = $this->query(
            "SELECT e.*, s.name as service_name 
             FROM enrollments e 
             INNER JOIN services s ON e.service_id = s.id 
             WHERE e.student_id = ? AND e.status != 'cancelada'
             ORDER BY e.created_at DESC",
            [$studentId]
        );
        return $stmt->fetchAll();
    }
    
    /**
     * Retorna o nome completo (full_name ou name como fallback)
     */
    public function getFullName($student)
    {
        return !empty($student['full_name']) ? $student['full_name'] : $student['name'];
    }
    
    /**
     * Retorna o telefone principal (phone_primary ou phone como fallback)
     */
    public function getPrimaryPhone($student)
    {
        return !empty($student['phone_primary']) ? $student['phone_primary'] : ($student['phone'] ?? null);
    }
    
    /**
     * Retorna o nome da cidade (da tabela cities ou campo city como fallback)
     */
    public function getCityName($student)
    {
        if (!empty($student['city_id'])) {
            $cityModel = new City();
            $city = $cityModel->findById($student['city_id']);
            if ($city) {
                return $city['name'];
            }
        }
        // Fallback para campo texto (compatibilidade)
        return $student['city'] ?? null;
    }
}
