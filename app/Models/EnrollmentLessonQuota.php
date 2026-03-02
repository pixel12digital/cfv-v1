<?php

namespace App\Models;

class EnrollmentLessonQuota extends Model
{
    protected $table = 'enrollment_lesson_quotas';

    public function findByEnrollment($enrollmentId)
    {
        $stmt = $this->query(
            "SELECT elq.*, lc.code, lc.name as category_name
             FROM {$this->table} elq
             INNER JOIN lesson_categories lc ON elq.lesson_category_id = lc.id
             WHERE elq.enrollment_id = ?
             ORDER BY lc.`order` ASC",
            [$enrollmentId]
        );
        return $stmt->fetchAll();
    }

    public function findByEnrollmentAndCategory($enrollmentId, $categoryId)
    {
        $stmt = $this->query(
            "SELECT * FROM {$this->table} 
             WHERE enrollment_id = ? AND lesson_category_id = ? 
             LIMIT 1",
            [$enrollmentId, $categoryId]
        );
        return $stmt->fetch();
    }

    public function create($data)
    {
        $stmt = $this->query(
            "INSERT INTO {$this->table} (enrollment_id, lesson_category_id, quantity) 
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)",
            [
                $data['enrollment_id'],
                $data['lesson_category_id'],
                $data['quantity']
            ]
        );
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $stmt = $this->query(
            "UPDATE {$this->table} 
             SET quantity = ?
             WHERE id = ?",
            [
                $data['quantity'],
                $id
            ]
        );
        return $stmt->rowCount();
    }

    public function deleteByEnrollment($enrollmentId)
    {
        $stmt = $this->query(
            "DELETE FROM {$this->table} WHERE enrollment_id = ?",
            [$enrollmentId]
        );
        return $stmt->rowCount();
    }

    public function getTotalByEnrollment($enrollmentId)
    {
        $stmt = $this->query(
            "SELECT SUM(quantity) as total FROM {$this->table} 
             WHERE enrollment_id = ?",
            [$enrollmentId]
        );
        $row = $stmt->fetch();
        return (int)($row['total'] ?? 0);
    }

    public function getQuotaWithScheduledCount($enrollmentId, $categoryId)
    {
        $stmt = $this->query(
            "SELECT 
                elq.quantity as contracted,
                COUNT(l.id) as scheduled,
                (elq.quantity - COUNT(l.id)) as remaining
             FROM {$this->table} elq
             LEFT JOIN lessons l ON l.enrollment_id = elq.enrollment_id 
                AND l.lesson_category_id = elq.lesson_category_id
                AND l.type = 'pratica'
                AND l.status != 'cancelada'
             WHERE elq.enrollment_id = ? AND elq.lesson_category_id = ?
             GROUP BY elq.id, elq.quantity",
            [$enrollmentId, $categoryId]
        );
        return $stmt->fetch();
    }

    public function getAllQuotasWithScheduledCount($enrollmentId)
    {
        $stmt = $this->query(
            "SELECT 
                elq.id,
                elq.enrollment_id,
                elq.lesson_category_id,
                lc.code,
                lc.name as category_name,
                elq.quantity as contracted,
                COUNT(l.id) as scheduled,
                (elq.quantity - COUNT(l.id)) as remaining
             FROM {$this->table} elq
             INNER JOIN lesson_categories lc ON elq.lesson_category_id = lc.id
             LEFT JOIN lessons l ON l.enrollment_id = elq.enrollment_id 
                AND l.lesson_category_id = elq.lesson_category_id
                AND l.type = 'pratica'
                AND l.status != 'cancelada'
             WHERE elq.enrollment_id = ?
             GROUP BY elq.id, elq.enrollment_id, elq.lesson_category_id, lc.code, lc.name, elq.quantity
             ORDER BY lc.`order` ASC",
            [$enrollmentId]
        );
        return $stmt->fetchAll();
    }
}
