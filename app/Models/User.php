<?php

namespace App\Models;

use App\Config\Database;

class User extends Model
{
    protected $table = 'usuarios';

    public static function findByEmail($email)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public static function getUserRoles($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT ur.role, r.nome 
            FROM usuario_roles ur
            JOIN roles r ON r.role = ur.role
            WHERE ur.usuario_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Busca usuário com informações de vinculação (aluno/instrutor)
     * CORREÇÃO: Verifica se tabelas existem antes de fazer JOIN para evitar PDOException
     */
    public function findWithLinks($userId)
    {
        $db = Database::getInstance()->getConnection();
        
        // Primeiro, buscar usuário básico
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return null;
        }
        
        // Tentar buscar instructor_id (verificando se tabela existe)
        try {
            // Tentar tabela instructors (novo sistema)
            $stmt = $db->prepare("SELECT id, name FROM instructors WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $instructor = $stmt->fetch();
            
            if ($instructor) {
                $user['instructor_id'] = $instructor['id'];
                $user['instructor_name'] = $instructor['name'];
            } else {
                // Tentar tabela instrutores (sistema legado)
                try {
                    $stmt = $db->prepare("SELECT id, nome FROM instrutores WHERE usuario_id = ? LIMIT 1");
                    $stmt->execute([$userId]);
                    $instructorLegado = $stmt->fetch();
                    
                    if ($instructorLegado) {
                        $user['instructor_id'] = $instructorLegado['id'];
                        $user['instructor_name'] = $instructorLegado['nome'];
                    } else {
                        // Se não encontrar em nenhuma tabela, usar user_id como fallback
                        $user['instructor_id'] = $userId;
                        $user['instructor_name'] = $user['nome'] ?? null;
                    }
                } catch (\PDOException $e) {
                    // Se tabela instrutores não existir, usar user_id como fallback
                    if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'Base table or view not found') !== false) {
                        $user['instructor_id'] = $userId;
                        $user['instructor_name'] = $user['nome'] ?? null;
                    } else {
                        throw $e; // Re-lançar se não for erro de tabela não encontrada
                    }
                }
            }
        } catch (\PDOException $e) {
            // Se tabela instructors não existir, tentar instrutores ou usar fallback
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'Base table or view not found') !== false) {
                try {
                    $stmt = $db->prepare("SELECT id, nome FROM instrutores WHERE usuario_id = ? LIMIT 1");
                    $stmt->execute([$userId]);
                    $instructorLegado = $stmt->fetch();
                    
                    if ($instructorLegado) {
                        $user['instructor_id'] = $instructorLegado['id'];
                        $user['instructor_name'] = $instructorLegado['nome'];
                    } else {
                        $user['instructor_id'] = $userId;
                        $user['instructor_name'] = $user['nome'] ?? null;
                    }
                } catch (\PDOException $e2) {
                    // Se ambas as tabelas não existirem, usar user_id como fallback
                    $user['instructor_id'] = $userId;
                    $user['instructor_name'] = $user['nome'] ?? null;
                }
            } else {
                throw $e; // Re-lançar se não for erro de tabela não encontrada
            }
        }
        
        // Tentar buscar student_id (verificando se tabela existe)
        try {
            $stmt = $db->prepare("SELECT id, name, full_name FROM students WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $student = $stmt->fetch();
            
            if ($student) {
                $user['student_id'] = $student['id'];
                $user['student_name'] = $student['name'];
                $user['student_full_name'] = $student['full_name'];
            }
        } catch (\PDOException $e) {
            // Se tabela students não existir, simplesmente não adicionar campos de student
            // Não é crítico, então apenas logar e continuar
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'Base table or view not found') !== false) {
                // Tabela não existe, continuar sem campos de student
            } else {
                // Outro erro, logar mas continuar
                error_log('[User::findWithLinks] Erro ao buscar student: ' . $e->getMessage());
            }
        }
        
        return $user;
    }

    /**
     * Lista todos os usuários com informações de vinculação
     * CORREÇÃO: Usa findWithLinks() para cada usuário para evitar problemas com JOINs em tabelas inexistentes
     */
    public function findAllWithLinks($cfcId)
    {
        $db = Database::getInstance()->getConnection();
        
        // Buscar usuários básicos
        $stmt = $db->prepare("
            SELECT u.*,
                   GROUP_CONCAT(ur.role) as roles
            FROM usuarios u
            LEFT JOIN usuario_roles ur ON ur.usuario_id = u.id
            WHERE u.cfc_id = ?
            GROUP BY u.id
            ORDER BY u.nome ASC
        ");
        $stmt->execute([$cfcId]);
        $users = $stmt->fetchAll();
        
        // Para cada usuário, buscar links usando findWithLinks() (que já trata tabelas inexistentes)
        foreach ($users as &$user) {
            $userWithLinks = $this->findWithLinks($user['id']);
            if ($userWithLinks) {
                $user['instructor_id'] = $userWithLinks['instructor_id'] ?? null;
                $user['instructor_name'] = $userWithLinks['instructor_name'] ?? null;
                $user['student_id'] = $userWithLinks['student_id'] ?? null;
                $user['student_name'] = $userWithLinks['student_name'] ?? null;
                $user['student_full_name'] = $userWithLinks['student_full_name'] ?? null;
            }
        }
        
        return $users;
    }

    /**
     * Verifica se um aluno já tem usuário vinculado
     */
    public function hasStudentUser($studentId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM students WHERE id = ? AND user_id IS NOT NULL");
        $stmt->execute([$studentId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    /**
     * Verifica se um instrutor já tem usuário vinculado
     */
    public function hasInstructorUser($instructorId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM instructors WHERE id = ? AND user_id IS NOT NULL");
        $stmt->execute([$instructorId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    /**
     * Atualiza senha do usuário
     */
    public function updatePassword($userId, $hashedPassword)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        return $stmt->rowCount();
    }
}
