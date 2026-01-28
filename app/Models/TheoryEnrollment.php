<?php

namespace App\Models;

class TheoryEnrollment extends Model
{
    protected $table = 'theory_enrollments';

    /**
     * Busca matrículas de uma turma
     */
    public function findByClass($classId)
    {
        $stmt = $this->query(
            "SELECT te.*, 
                    COALESCE(s.full_name, s.name) as student_name, s.cpf as student_cpf,
                    e.id as enrollment_id, e.status as enrollment_status
             FROM {$this->table} te
             INNER JOIN students s ON te.student_id = s.id
             LEFT JOIN enrollments e ON te.enrollment_id = e.id
             WHERE te.class_id = ?
             ORDER BY s.name ASC",
            [$classId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Busca matrículas de um aluno
     */
    public function findByStudent($studentId)
    {
        $stmt = $this->query(
            "SELECT te.*, 
                    tc.name as class_name, tc.status as class_status,
                    tco.name as course_name,
                    i.name as instructor_name
             FROM {$this->table} te
             INNER JOIN theory_classes tc ON te.class_id = tc.id
             INNER JOIN theory_courses tco ON tc.course_id = tco.id
             INNER JOIN instructors i ON tc.instructor_id = i.id
             WHERE te.student_id = ?
             ORDER BY te.enrolled_at DESC",
            [$studentId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Verifica se aluno já está matriculado na turma
     */
    public function isEnrolled($classId, $studentId)
    {
        $stmt = $this->query(
            "SELECT id FROM {$this->table} 
             WHERE class_id = ? AND student_id = ? AND status = 'active'",
            [$classId, $studentId]
        );
        return $stmt->fetch() !== false;
    }
}
