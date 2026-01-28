<?php

namespace App\Models;

class RescheduleRequest extends Model
{
    protected $table = 'reschedule_requests';

    /**
     * Busca solicitação pendente por aula e aluno
     */
    public function findPendingByLessonAndStudent($lessonId, $studentId)
    {
        $stmt = $this->query(
            "SELECT * FROM {$this->table} 
             WHERE lesson_id = ? AND student_id = ? AND status = 'pending' 
             ORDER BY created_at DESC 
             LIMIT 1",
            [$lessonId, $studentId]
        );
        return $stmt->fetch();
    }

    /**
     * Busca todas as solicitações de um aluno
     */
    public function findByStudent($studentId, $status = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE student_id = ?";
        $params = [$studentId];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Busca solicitações pendentes (para admin/secretaria)
     */
    public function findPending($cfcId = null)
    {
        $sql = "SELECT rr.*, 
                       l.scheduled_date, l.scheduled_time, l.status as lesson_status,
                       COALESCE(s.full_name, s.name) as student_name, s.cpf as student_cpf,
                       u.nome as resolved_by_name
                FROM {$this->table} rr
                INNER JOIN lessons l ON rr.lesson_id = l.id
                INNER JOIN students s ON rr.student_id = s.id
                LEFT JOIN usuarios u ON rr.resolved_by_user_id = u.id
                WHERE rr.status = 'pending'";
        
        $params = [];
        if ($cfcId) {
            $sql .= " AND l.cfc_id = ?";
            $params[] = $cfcId;
        }
        
        $sql .= " ORDER BY rr.created_at ASC";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Busca solicitação com detalhes completos
     */
    public function findWithDetails($id)
    {
        $stmt = $this->query(
            "SELECT rr.*, 
                    l.scheduled_date, l.scheduled_time, l.status as lesson_status,
                    l.instructor_id, l.vehicle_id,
                    COALESCE(s.full_name, s.name) as student_name, s.cpf as student_cpf,
                    u.nome as resolved_by_name
             FROM {$this->table} rr
             INNER JOIN lessons l ON rr.lesson_id = l.id
             INNER JOIN students s ON rr.student_id = s.id
             LEFT JOIN usuarios u ON rr.resolved_by_user_id = u.id
             WHERE rr.id = ?",
            [$id]
        );
        return $stmt->fetch();
    }

    /**
     * Conta solicitações pendentes (para admin/secretaria)
     */
    public function countPending($cfcId = null)
    {
        $sql = "SELECT COUNT(*) as count 
                FROM {$this->table} rr
                INNER JOIN lessons l ON rr.lesson_id = l.id
                WHERE rr.status = 'pending'";
        
        $params = [];
        if ($cfcId) {
            $sql .= " AND l.cfc_id = ?";
            $params[] = $cfcId;
        }
        
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }
}
