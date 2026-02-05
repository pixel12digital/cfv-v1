<?php

namespace App\Models;

class Enrollment extends Model
{
    protected $table = 'enrollments';

    public function findByStudent($studentId, $cfcId = null)
    {
        $sql = "SELECT e.*, s.name as service_name, s.category as service_category
                FROM {$this->table} e
                INNER JOIN services s ON e.service_id = s.id
                WHERE e.student_id = ? AND (e.deleted_at IS NULL)";
        $params = [$studentId];
        
        if ($cfcId !== null) {
            $sql .= " AND e.cfc_id = ?";
            $params[] = $cfcId;
        }
        
        $sql .= " ORDER BY e.created_at DESC";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function findWithDetails($id)
    {
        $stmt = $this->query(
            "SELECT e.*, 
                    s.name as service_name, s.category as service_category,
                    st.name as student_name, st.cpf as student_cpf,
                    st.full_name, st.email, st.phone, st.phone_primary,
                    st.street, st.number, st.neighborhood, st.cep, st.city, st.state_uf
             FROM {$this->table} e
             INNER JOIN services s ON e.service_id = s.id
             INNER JOIN students st ON e.student_id = st.id
             WHERE e.id = ? AND (e.deleted_at IS NULL)",
            [$id]
        );
        return $stmt->fetch();
    }

    public function calculateFinalPrice($basePrice, $discountValue, $extraValue)
    {
        return max(0, $basePrice - $discountValue + $extraValue);
    }
}
