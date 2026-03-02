<?php

namespace App\Models;

class LessonCategory extends Model
{
    protected $table = 'lesson_categories';

    public function findAll()
    {
        $stmt = $this->query("SELECT * FROM {$this->table} ORDER BY `order` ASC");
        return $stmt->fetchAll();
    }

    public function findActive()
    {
        $stmt = $this->query(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY `order` ASC"
        );
        return $stmt->fetchAll();
    }

    public function findByCode($code)
    {
        $stmt = $this->query(
            "SELECT * FROM {$this->table} WHERE code = ? LIMIT 1",
            [$code]
        );
        return $stmt->fetch();
    }

    public function create($data)
    {
        $stmt = $this->query(
            "INSERT INTO {$this->table} (code, name, description, `order`, is_active) 
             VALUES (?, ?, ?, ?, ?)",
            [
                $data['code'],
                $data['name'],
                $data['description'] ?? null,
                $data['order'] ?? 0,
                $data['is_active'] ?? 1
            ]
        );
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $stmt = $this->query(
            "UPDATE {$this->table} 
             SET code = ?, name = ?, description = ?, `order` = ?, is_active = ?
             WHERE id = ?",
            [
                $data['code'],
                $data['name'],
                $data['description'] ?? null,
                $data['order'] ?? 0,
                $data['is_active'] ?? 1,
                $id
            ]
        );
        return $stmt->rowCount();
    }
}
