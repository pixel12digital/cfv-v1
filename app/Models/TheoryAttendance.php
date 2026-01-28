<?php

namespace App\Models;

class TheoryAttendance extends Model
{
    protected $table = 'theory_attendance';

    /**
     * Busca presença de uma sessão
     */
    public function findBySession($sessionId)
    {
        $stmt = $this->query(
            "SELECT ta.*, 
                    COALESCE(s.full_name, s.name) as student_name, s.cpf as student_cpf,
                    u.nome as marked_by_name
             FROM {$this->table} ta
             INNER JOIN students s ON ta.student_id = s.id
             LEFT JOIN usuarios u ON ta.marked_by = u.id
             WHERE ta.session_id = ?
             ORDER BY s.name ASC",
            [$sessionId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Busca presença de um aluno em uma turma
     */
    public function findByStudentAndClass($studentId, $classId)
    {
        $stmt = $this->query(
            "SELECT ta.*, 
                    ts.starts_at, ts.ends_at,
                    td.name as discipline_name
             FROM {$this->table} ta
             INNER JOIN theory_sessions ts ON ta.session_id = ts.id
             INNER JOIN theory_disciplines td ON ts.discipline_id = td.id
             WHERE ta.student_id = ? AND ts.class_id = ?
             ORDER BY ts.starts_at ASC",
            [$studentId, $classId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Busca presença de um aluno por disciplina
     */
    public function findByStudentAndDiscipline($studentId, $disciplineId, $classId)
    {
        $stmt = $this->query(
            "SELECT ta.*, ts.starts_at, ts.ends_at
             FROM {$this->table} ta
             INNER JOIN theory_sessions ts ON ta.session_id = ts.id
             WHERE ta.student_id = ? 
               AND ts.discipline_id = ? 
               AND ts.class_id = ?
             ORDER BY ts.starts_at ASC",
            [$studentId, $disciplineId, $classId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Marca presença em lote
     * Nota: Não gerencia transação própria - deve ser chamado dentro de uma transação existente
     */
    public function markBatch($sessionId, $attendances)
    {
        // Remover presenças existentes da sessão
        $this->query("DELETE FROM {$this->table} WHERE session_id = ?", [$sessionId]);
        
        // Inserir novas presenças
        foreach ($attendances as $attendance) {
            $this->create([
                'session_id' => $sessionId,
                'student_id' => $attendance['student_id'],
                'status' => $attendance['status'],
                'notes' => $attendance['notes'] ?? null,
                'marked_by' => $_SESSION['user_id'] ?? null,
                'marked_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return true;
    }
}
