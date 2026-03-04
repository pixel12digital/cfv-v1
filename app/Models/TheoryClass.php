<?php

namespace App\Models;

class TheoryClass extends Model
{
    protected $table = 'theory_classes';

    /**
     * Busca turmas de um CFC
     */
    public function findByCfc($cfcId, $status = null)
    {
        $sql = "SELECT tc.*, 
                       COALESCE(tco.name, 'Curso não definido') as course_name,
                       COALESCE(i.name, 'Instrutor não definido') as instructor_name,
                       COUNT(DISTINCT te.id) as enrolled_count
                FROM {$this->table} tc
                LEFT JOIN theory_courses tco ON tc.course_id = tco.id
                LEFT JOIN instructors i ON tc.instructor_id = i.id
                LEFT JOIN theory_enrollments te ON tc.id = te.class_id AND te.status = 'active'
                WHERE tc.cfc_id = ?";
        
        $params = [$cfcId];
        
        if ($status) {
            if (is_array($status)) {
                $placeholders = str_repeat('?,', count($status) - 1) . '?';
                $sql .= " AND tc.status IN ({$placeholders})";
                $params = array_merge($params, $status);
            } else {
                $sql .= " AND tc.status = ?";
                $params[] = $status;
            }
        }
        
        $sql .= " GROUP BY tc.id ORDER BY tc.start_date DESC, tc.created_at DESC";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Busca turma com detalhes completos
     */
    public function findWithDetails($id)
    {
        $stmt = $this->query(
            "SELECT tc.*, 
                    tco.name as course_name,
                    i.name as instructor_name,
                    u.nome as created_by_name
             FROM {$this->table} tc
             INNER JOIN theory_courses tco ON tc.course_id = tco.id
             INNER JOIN instructors i ON tc.instructor_id = i.id
             LEFT JOIN usuarios u ON tc.created_by = u.id
             WHERE tc.id = ?",
            [$id]
        );
        return $stmt->fetch();
    }

    /**
     * Busca turmas de um instrutor
     */
    public function findByInstructor($instructorId, $cfcId)
    {
        $stmt = $this->query(
            "SELECT tc.*, tco.name as course_name
             FROM {$this->table} tc
             INNER JOIN theory_courses tco ON tc.course_id = tco.id
             WHERE tc.instructor_id = ? AND tc.cfc_id = ?
             ORDER BY tc.start_date DESC",
            [$instructorId, $cfcId]
        );
        return $stmt->fetchAll();
    }
}
