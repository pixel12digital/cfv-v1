<?php

namespace App\Models;

class Lesson extends Model
{
    protected $table = 'lessons';

    /**
     * Busca aulas com detalhes completos
     * Trata caso onde tabela instructors não existe
     */
    public function findWithDetails($id)
    {
        try {
            $stmt = $this->query(
                "SELECT l.*,
                        s.name as student_name, s.cpf as student_cpf,
                        e.id as enrollment_id, e.financial_status,
                        i.name as instructor_name,
                        v.plate as vehicle_plate, v.model as vehicle_model,
                        u.nome as created_by_name,
                        uc.nome as canceled_by_name
                 FROM {$this->table} l
                 INNER JOIN students s ON l.student_id = s.id
                 INNER JOIN enrollments e ON l.enrollment_id = e.id
                 LEFT JOIN instructors i ON l.instructor_id = i.id
                 LEFT JOIN vehicles v ON l.vehicle_id = v.id
                 LEFT JOIN usuarios u ON l.created_by = u.id
                 LEFT JOIN usuarios uc ON l.canceled_by = uc.id
                 WHERE l.id = ?",
                [$id]
            );
            return $stmt->fetch();
        } catch (\PDOException $e) {
            // Se a tabela instructors não existe, fazer query sem JOIN
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'instructors') !== false) {
                error_log("[Lesson::findWithDetails] Tabela 'instructors' não existe. Fazendo query sem JOIN.");
                $stmt = $this->query(
                    "SELECT l.*,
                            s.name as student_name, s.cpf as student_cpf,
                            e.id as enrollment_id, e.financial_status,
                            NULL as instructor_name,
                            v.plate as vehicle_plate, v.model as vehicle_model,
                            u.nome as created_by_name,
                            uc.nome as canceled_by_name
                     FROM {$this->table} l
                     INNER JOIN students s ON l.student_id = s.id
                     INNER JOIN enrollments e ON l.enrollment_id = e.id
                     LEFT JOIN vehicles v ON l.vehicle_id = v.id
                     LEFT JOIN usuarios u ON l.created_by = u.id
                     LEFT JOIN usuarios uc ON l.canceled_by = uc.id
                     WHERE l.id = ?",
                    [$id]
                );
                return $stmt->fetch();
            }
            // Re-throw outros erros
            throw $e;
        }
    }

    /**
     * Busca aulas por período
     * Trata caso onde tabela instructors não existe
     */
    public function findByPeriod($cfcId, $startDate, $endDate, $filters = [])
    {
        try {
            $sql = "SELECT l.*,
                           s.name as student_name,
                           i.name as instructor_name,
                           v.plate as vehicle_plate
                    FROM {$this->table} l
                    INNER JOIN students s ON l.student_id = s.id
                    LEFT JOIN instructors i ON l.instructor_id = i.id
                    LEFT JOIN vehicles v ON l.vehicle_id = v.id
                    WHERE l.cfc_id = ? 
                      AND l.scheduled_date BETWEEN ? AND ?";
            
            $params = [$cfcId, $startDate, $endDate];
            
            // Filtro por status
            if (!empty($filters['status'])) {
                $sql .= " AND l.status = ?";
                $params[] = $filters['status'];
            } else {
                // Se não há filtro de status específico, filtrar canceladas por padrão
                // (a menos que show_canceled esteja ativo)
                if (empty($filters['show_canceled'])) {
                    $sql .= " AND l.status != 'cancelada'";
                }
            }
            
            // Filtro por tipo
            if (!empty($filters['type'])) {
                $sql .= " AND l.type = ?";
                $params[] = $filters['type'];
            }
            
            // Filtro por instrutor
            if (!empty($filters['instructor_id'])) {
                $sql .= " AND l.instructor_id = ?";
                $params[] = $filters['instructor_id'];
            }
            
            // Filtro por veículo (apenas para práticas)
            if (!empty($filters['vehicle_id']) && (empty($filters['type']) || $filters['type'] === 'pratica')) {
                $sql .= " AND l.vehicle_id = ?";
                $params[] = $filters['vehicle_id'];
            }
            
            $sql .= " ORDER BY l.scheduled_date ASC, l.scheduled_time ASC";
            
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            // Se a tabela instructors não existe, fazer query sem JOIN
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'instructors') !== false) {
                error_log("[Lesson::findByPeriod] Tabela 'instructors' não existe. Fazendo query sem JOIN.");
                $sql = "SELECT l.*,
                               s.name as student_name,
                               NULL as instructor_name,
                               v.plate as vehicle_plate
                        FROM {$this->table} l
                        INNER JOIN students s ON l.student_id = s.id
                        LEFT JOIN vehicles v ON l.vehicle_id = v.id
                        WHERE l.cfc_id = ? 
                          AND l.scheduled_date BETWEEN ? AND ?";
                
                $params = [$cfcId, $startDate, $endDate];
                
                // Aplicar mesmos filtros
                if (!empty($filters['status'])) {
                    $sql .= " AND l.status = ?";
                    $params[] = $filters['status'];
                } else {
                    if (empty($filters['show_canceled'])) {
                        $sql .= " AND l.status != 'cancelada'";
                    }
                }
                
                if (!empty($filters['type'])) {
                    $sql .= " AND l.type = ?";
                    $params[] = $filters['type'];
                }
                
                if (!empty($filters['instructor_id'])) {
                    $sql .= " AND l.instructor_id = ?";
                    $params[] = $filters['instructor_id'];
                }
                
                if (!empty($filters['vehicle_id']) && (empty($filters['type']) || $filters['type'] === 'pratica')) {
                    $sql .= " AND l.vehicle_id = ?";
                    $params[] = $filters['vehicle_id'];
                }
                
                $sql .= " ORDER BY l.scheduled_date ASC, l.scheduled_time ASC";
                
                $stmt = $this->query($sql, $params);
                return $stmt->fetchAll();
            }
            // Re-throw outros erros
            throw $e;
        }
    }

    /**
     * Busca aulas por período com dedupe de sessões teóricas (para admin/secretaria)
     * Agrupa lessons teóricas por theory_session_id para evitar duplicação
     */
    public function findByPeriodWithTheoryDedupe($cfcId, $startDate, $endDate, $filters = [])
    {
        // Query para aulas práticas (normais) - mesma estrutura de colunas que teórica
        $sqlPratica = "SELECT l.id,
                             l.cfc_id,
                             l.student_id,
                             l.enrollment_id,
                             l.instructor_id,
                             l.vehicle_id,
                             l.type,
                             l.status as lesson_status,
                             NULL as session_status,
                             l.status as status,
                             l.scheduled_date,
                             l.scheduled_time,
                             l.duration_minutes,
                             l.started_at,
                             l.completed_at,
                             l.notes,
                             l.created_by,
                             l.created_at,
                             l.updated_at,
                             l.canceled_at,
                             l.canceled_by,
                             l.cancel_reason,
                             l.km_start,
                             l.km_end,
                             l.theory_session_id,
                             NULL as class_id,
                             NULL as discipline_id,
                             NULL as discipline_name,
                             NULL as class_name,
                             i.name as instructor_name,
                             s.name as student_name,
                             v.plate as vehicle_plate,
                             1 as student_count,
                             'pratica' as lesson_type,
                             NULL as student_names
                       FROM {$this->table} l
                       INNER JOIN students s ON l.student_id = s.id
                       LEFT JOIN instructors i ON l.instructor_id = i.id
                       LEFT JOIN vehicles v ON l.vehicle_id = v.id
                       WHERE l.cfc_id = ?
                         AND l.scheduled_date BETWEEN ? AND ?
                         AND l.type = 'pratica'";
        
        $params = [$cfcId, $startDate, $endDate];
        
        // Query para aulas teóricas (agrupadas por theory_session_id) - mesma estrutura de colunas
        $sqlTeoria = "SELECT MIN(l.id) as id,
                             l.cfc_id,
                             MIN(l.student_id) as student_id,
                             MIN(l.enrollment_id) as enrollment_id,
                             l.instructor_id,
                             NULL as vehicle_id,
                             l.type,
                             MIN(l.status) as lesson_status,
                             ts.status as session_status,
                             COALESCE(ts.status, MIN(l.status)) as status,
                             l.scheduled_date,
                             l.scheduled_time,
                             MIN(l.duration_minutes) as duration_minutes,
                             MIN(l.started_at) as started_at,
                             MIN(l.completed_at) as completed_at,
                             MIN(l.notes) as notes,
                             MIN(l.created_by) as created_by,
                             MIN(l.created_at) as created_at,
                             MIN(l.updated_at) as updated_at,
                             MIN(l.canceled_at) as canceled_at,
                             MIN(l.canceled_by) as canceled_by,
                             MIN(l.cancel_reason) as cancel_reason,
                             MIN(l.km_start) as km_start,
                             MIN(l.km_end) as km_end,
                             l.theory_session_id,
                             ts.class_id,
                             ts.discipline_id,
                             td.name as discipline_name,
                             tc.name as class_name,
                             i.name as instructor_name,
                             NULL as student_name,
                             NULL as vehicle_plate,
                             COUNT(DISTINCT l.student_id) as student_count,
                             'teoria' as lesson_type,
                             GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as student_names
                      FROM {$this->table} l
                      INNER JOIN students s ON l.student_id = s.id
                      INNER JOIN theory_sessions ts ON l.theory_session_id = ts.id
                      INNER JOIN theory_disciplines td ON ts.discipline_id = td.id
                      INNER JOIN theory_classes tc ON ts.class_id = tc.id
                      LEFT JOIN instructors i ON l.instructor_id = i.id
                      WHERE l.cfc_id = ?
                        AND l.scheduled_date BETWEEN ? AND ?
                        AND l.type = 'teoria'
                        AND l.theory_session_id IS NOT NULL
                      GROUP BY l.theory_session_id, l.scheduled_date, l.scheduled_time, ts.class_id, ts.discipline_id, ts.status, l.instructor_id, l.cfc_id, l.type";
        
        $paramsTeoria = [$cfcId, $startDate, $endDate];
        
        // Aplicar filtros comuns
        // Filtro por tipo
        if (!empty($filters['type'])) {
            if ($filters['type'] === 'pratica') {
                // Só práticas
                $sqlTeoria = ''; // Não incluir teóricas
            } elseif ($filters['type'] === 'teoria') {
                // Só teóricas
                $sqlPratica = ''; // Não incluir práticas
            }
        }
        
        // Filtro por status
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'concluida' || $filters['status'] === 'done') {
                if (!empty($sqlPratica)) {
                    $sqlPratica .= " AND l.status = 'concluida'";
                }
                if (!empty($sqlTeoria)) {
                    $sqlTeoria .= " AND ts.status = 'done'";
                }
            } elseif ($filters['status'] === 'cancelada' || $filters['status'] === 'canceled') {
                if (!empty($sqlPratica)) {
                    $sqlPratica .= " AND l.status = 'cancelada'";
                }
                if (!empty($sqlTeoria)) {
                    $sqlTeoria .= " AND ts.status = 'canceled'";
                }
            } elseif ($filters['status'] === 'agendada' || $filters['status'] === 'scheduled') {
                if (!empty($sqlPratica)) {
                    $sqlPratica .= " AND l.status = 'agendada'";
                }
                if (!empty($sqlTeoria)) {
                    // Para teóricas, verificar l.status (que é 'agendada') e ts.status (que é 'scheduled')
                    $sqlTeoria .= " AND l.status = 'agendada' AND ts.status = 'scheduled'";
                }
            } elseif ($filters['status'] === 'em_andamento' || $filters['status'] === 'in_progress') {
                if (!empty($sqlPratica)) {
                    $sqlPratica .= " AND l.status = 'em_andamento'";
                }
                if (!empty($sqlTeoria)) {
                    $sqlTeoria .= " AND ts.status = 'in_progress'";
                }
            } else {
                if (!empty($sqlPratica)) {
                    $sqlPratica .= " AND l.status = ?";
                    $params[] = $filters['status'];
                }
                if (!empty($sqlTeoria)) {
                    $sqlTeoria .= " AND ts.status = ?";
                    $paramsTeoria[] = $filters['status'];
                }
            }
        } else {
            // Se não há filtro de status específico, filtrar canceladas por padrão
            if (empty($filters['show_canceled'])) {
                if (!empty($sqlPratica)) {
                    $sqlPratica .= " AND l.status != 'cancelada'";
                }
                if (!empty($sqlTeoria)) {
                    // Para teóricas, excluir apenas se a lesson OU a sessão estiver cancelada
                    // (não excluir se apenas uma estiver cancelada, mas excluir se ambas estiverem)
                    $sqlTeoria .= " AND l.status != 'cancelada' AND ts.status != 'canceled'";
                }
            }
        }
        
        // Filtro por instrutor (para práticas e teóricas)
        if (!empty($filters['instructor_id'])) {
            if (!empty($sqlPratica)) {
                $sqlPratica .= " AND l.instructor_id = ?";
                $params[] = $filters['instructor_id'];
            }
            if (!empty($sqlTeoria)) {
                $sqlTeoria .= " AND l.instructor_id = ?";
                $paramsTeoria[] = $filters['instructor_id'];
            }
        }
        
        // Filtro por veículo (apenas para práticas)
        if (!empty($filters['vehicle_id']) && (empty($filters['type']) || $filters['type'] === 'pratica')) {
            if (!empty($sqlPratica)) {
                $sqlPratica .= " AND l.vehicle_id = ?";
                $params[] = $filters['vehicle_id'];
            }
        }
        
        // UNION das duas queries
        if (!empty($sqlPratica) && !empty($sqlTeoria)) {
            $sql = "({$sqlPratica}) UNION ({$sqlTeoria})";
            $allParams = array_merge($params, $paramsTeoria);
        } elseif (!empty($sqlPratica)) {
            $sql = $sqlPratica;
            $allParams = $params;
        } elseif (!empty($sqlTeoria)) {
            $sql = $sqlTeoria;
            $allParams = $paramsTeoria;
        } else {
            return [];
        }
        
        $sql .= " ORDER BY scheduled_date ASC, scheduled_time ASC";
        
        // Log SQL para auditoria quando há filtro de data específico
        if ($startDate === $endDate) {
            error_log("=== AGENDA SQL AUDIT (findByPeriodWithTheoryDedupe) ===");
            error_log("Date filter: {$startDate} (exact day)");
            error_log("SQL: " . $sql);
            error_log("Params: " . json_encode($allParams));
        }
        
        try {
            $stmt = $this->query($sql, $allParams);
            $results = $stmt->fetchAll();
            
            // Log resultados para auditoria
            if ($startDate === $endDate) {
                error_log("Results count: " . count($results));
                foreach ($results as $idx => $result) {
                    error_log("Result {$idx}: scheduled_date={$result['scheduled_date']}, status={$result['status']}, type={$result['type']}");
                }
                error_log("=== END AGENDA SQL AUDIT ===");
            }
            
            return $results;
        } catch (\PDOException $e) {
            // Se a tabela instructors não existe, refazer queries sem JOIN
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'instructors') !== false) {
                error_log("[Lesson::findByPeriodWithTheoryDedupe] Tabela 'instructors' não existe. Refazendo queries sem JOIN.");
                // Refazer as queries sem JOIN com instructors
                $sqlPratica = str_replace('LEFT JOIN instructors i ON l.instructor_id = i.id', '', $sqlPratica);
                $sqlPratica = str_replace('i.name as instructor_name,', 'NULL as instructor_name,', $sqlPratica);
                $sqlTeoria = str_replace('LEFT JOIN instructors i ON l.instructor_id = i.id', '', $sqlTeoria);
                $sqlTeoria = str_replace('i.name as instructor_name,', 'NULL as instructor_name,', $sqlTeoria);
                
                // Reconstruir UNION
                if (!empty($sqlPratica) && !empty($sqlTeoria)) {
                    $sql = "({$sqlPratica}) UNION ({$sqlTeoria})";
                    $allParams = array_merge($params, $paramsTeoria);
                } elseif (!empty($sqlPratica)) {
                    $sql = $sqlPratica;
                    $allParams = $params;
                } elseif (!empty($sqlTeoria)) {
                    $sql = $sqlTeoria;
                    $allParams = $paramsTeoria;
                } else {
                    return [];
                }
                
                $sql .= " ORDER BY scheduled_date ASC, scheduled_time ASC";
                
                $stmt = $this->query($sql, $allParams);
                return $stmt->fetchAll();
            }
            // Re-throw outros erros
            throw $e;
        }
    }

    /**
     * Busca aulas do instrutor com dedupe de sessões teóricas
     * Agrupa lessons teóricas por theory_session_id para evitar duplicação
     * Trata caso onde tabela instructors não existe
     */
    public function findByInstructorWithTheoryDedupe($instructorId, $cfcId, $filters = [])
    {
        try {
            $today = date('Y-m-d');
            $now = date('H:i:s');
            
            // Query para aulas práticas (normais)
            $sqlPratica = "SELECT l.*,
                                  s.name as student_name,
                                  v.plate as vehicle_plate,
                                  NULL as theory_session_id,
                                  1 as student_count,
                                  'pratica' as lesson_type
                           FROM {$this->table} l
                           INNER JOIN students s ON l.student_id = s.id
                           LEFT JOIN vehicles v ON l.vehicle_id = v.id
                           WHERE l.instructor_id = ?
                             AND l.cfc_id = ?
                             AND l.type = 'pratica'";
            
            $params = [$instructorId, $cfcId];
            
            // Query para aulas teóricas (agrupadas por theory_session_id)
            $sqlTeoria = "SELECT MIN(l.id) as id,
                                 l.cfc_id,
                                 MIN(l.student_id) as student_id,
                                 MIN(l.enrollment_id) as enrollment_id,
                                 l.instructor_id,
                                 NULL as vehicle_id,
                                 l.type,
                                 MIN(l.status) as status,
                                 l.scheduled_date,
                                 l.scheduled_time,
                                 MIN(l.duration_minutes) as duration_minutes,
                                 MIN(l.started_at) as started_at,
                                 MIN(l.completed_at) as completed_at,
                                 MIN(l.notes) as notes,
                                 MIN(l.created_by) as created_by,
                                 MIN(l.created_at) as created_at,
                                 MIN(l.updated_at) as updated_at,
                                 l.theory_session_id,
                                 ts.class_id,
                                 COUNT(DISTINCT l.student_id) as student_count,
                                 'teoria' as lesson_type,
                                 GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as student_names,
                                 NULL as student_name,
                                 NULL as vehicle_plate
                          FROM {$this->table} l
                          INNER JOIN students s ON l.student_id = s.id
                          INNER JOIN theory_sessions ts ON l.theory_session_id = ts.id
                          WHERE l.instructor_id = ?
                            AND l.cfc_id = ?
                            AND l.type = 'teoria'
                            AND l.theory_session_id IS NOT NULL";
            
            $paramsTeoria = [$instructorId, $cfcId];
            
            // Aplicar filtro de data se fornecido (OBRIGATÓRIO para view=list com date)
            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $dateFilter = " AND l.scheduled_date BETWEEN ? AND ?";
                $sqlPratica .= $dateFilter;
                $sqlTeoria .= $dateFilter;
                $params[] = $filters['start_date'];
                $params[] = $filters['end_date'];
                $paramsTeoria[] = $filters['start_date'];
                $paramsTeoria[] = $filters['end_date'];
            }
            
            // Aplicar filtros de status ANTES do GROUP BY
            if (!empty($filters['status'])) {
                $statusFilter = " AND l.status = ?";
                $sqlPratica .= $statusFilter;
                $sqlTeoria .= $statusFilter;
                $params[] = $filters['status'];
                $paramsTeoria[] = $filters['status'];
            } elseif (!empty($filters['tab'])) {
                if ($filters['tab'] === 'proximas') {
                    $sqlPratica .= " AND l.status IN ('agendada', 'em_andamento')
                                    AND (l.scheduled_date > ? OR (l.scheduled_date = ? AND l.scheduled_time >= ?))";
                    $sqlTeoria .= " AND l.status IN ('agendada', 'em_andamento')
                                    AND (l.scheduled_date > ? OR (l.scheduled_date = ? AND l.scheduled_time >= ?))";
                    $params = array_merge($params, [$today, $today, $now]);
                    $paramsTeoria = array_merge($paramsTeoria, [$today, $today, $now]);
                } elseif ($filters['tab'] === 'historico') {
                    $sqlPratica .= " AND l.status IN ('concluida', 'cancelada', 'no_show')";
                    $sqlTeoria .= " AND l.status IN ('concluida', 'cancelada', 'no_show')";
                }
            } else {
                // Padrão: excluir canceladas
                if (empty($filters['show_canceled'])) {
                    $sqlPratica .= " AND l.status != 'cancelada'";
                    $sqlTeoria .= " AND l.status != 'cancelada'";
                }
            }
            
            // GROUP BY para teóricas (após WHERE, antes de HAVING se necessário)
            $sqlTeoria .= " GROUP BY l.theory_session_id, l.scheduled_date, l.scheduled_time, ts.class_id, l.instructor_id, l.cfc_id, l.type";
            
            // UNION e ordenação
            $sql = "({$sqlPratica}) UNION ({$sqlTeoria})";
            $allParams = array_merge($params, $paramsTeoria);
            
            if (!empty($filters['tab']) && $filters['tab'] === 'proximas') {
                $sql .= " ORDER BY scheduled_date ASC, scheduled_time ASC";
            } elseif (!empty($filters['tab']) && $filters['tab'] === 'historico') {
                $sql .= " ORDER BY scheduled_date DESC, scheduled_time DESC";
            } else {
                $sql .= " ORDER BY scheduled_date DESC, scheduled_time DESC";
            }
            
            $stmt = $this->query($sql, $allParams);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            // Log do erro para debug
            error_log("[Lesson::findByInstructorWithTheoryDedupe] PDOException capturada:");
            error_log("  SQLSTATE: " . $e->getCode());
            error_log("  Mensagem: " . $e->getMessage());
            error_log("  Arquivo: " . $e->getFile() . ":" . $e->getLine());
            
            // Se for erro de tabela não encontrada ou qualquer erro de schema, retornar array vazio
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false || 
                strpos($e->getMessage(), 'Base table or view not found') !== false) {
                error_log("[Lesson::findByInstructorWithTheoryDedupe] Tabela não encontrada. Retornando array vazio.");
                return [];
            }
            
            // Re-throw outros erros para serem capturados pelo controller
            throw $e;
        }
    }

    /**
     * Verifica conflito de horário para instrutor
     */
    public function hasInstructorConflict($instructorId, $scheduledDate, $scheduledTime, $durationMinutes, $excludeLessonId = null, $cfcId = null)
    {
        $startTime = $scheduledTime;
        $endTime = date('H:i:s', strtotime("+{$durationMinutes} minutes", strtotime($scheduledTime)));
        
        $sql = "SELECT COUNT(*) as count
                FROM {$this->table}
                WHERE instructor_id = ?
                  AND scheduled_date = ?
                  AND status NOT IN ('cancelada', 'concluida', 'no_show')
                  AND (
                    (scheduled_time <= ? AND DATE_ADD(scheduled_time, INTERVAL duration_minutes MINUTE) > ?)
                    OR
                    (scheduled_time < ? AND DATE_ADD(scheduled_time, INTERVAL duration_minutes MINUTE) >= ?)
                    OR
                    (scheduled_time >= ? AND scheduled_time < ?)
                  )";
        
        $params = [
            $instructorId,
            $scheduledDate,
            $startTime, $startTime,
            $endTime, $endTime,
            $startTime, $endTime
        ];
        
        // Filtrar por CFC se fornecido (importante para multi-CFC)
        if ($cfcId !== null) {
            $sql .= " AND cfc_id = ?";
            $params[] = $cfcId;
        }
        
        if ($excludeLessonId) {
            $sql .= " AND id != ?";
            $params[] = $excludeLessonId;
        }
        
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Verifica conflito de horário para veículo
     */
    public function hasVehicleConflict($vehicleId, $scheduledDate, $scheduledTime, $durationMinutes, $excludeLessonId = null, $cfcId = null)
    {
        if (!$vehicleId) {
            return false; // Veículo é obrigatório
        }
        
        $startTime = $scheduledTime;
        $endTime = date('H:i:s', strtotime("+{$durationMinutes} minutes", strtotime($scheduledTime)));
        
        $sql = "SELECT COUNT(*) as count
                FROM {$this->table}
                WHERE vehicle_id = ?
                  AND scheduled_date = ?
                  AND status NOT IN ('cancelada', 'concluida', 'no_show')
                  AND (
                    (scheduled_time <= ? AND DATE_ADD(scheduled_time, INTERVAL duration_minutes MINUTE) > ?)
                    OR
                    (scheduled_time < ? AND DATE_ADD(scheduled_time, INTERVAL duration_minutes MINUTE) >= ?)
                    OR
                    (scheduled_time >= ? AND scheduled_time < ?)
                  )";
        
        $params = [
            $vehicleId,
            $scheduledDate,
            $startTime, $startTime,
            $endTime, $endTime,
            $startTime, $endTime
        ];
        
        // Filtrar por CFC se fornecido (importante para multi-CFC)
        if ($cfcId !== null) {
            $sql .= " AND cfc_id = ?";
            $params[] = $cfcId;
        }
        
        if ($excludeLessonId) {
            $sql .= " AND id != ?";
            $params[] = $excludeLessonId;
        }
        
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Busca aulas de um aluno
     * Trata caso onde tabela instructors não existe
     */
    public function findByStudent($studentId, $limit = null)
    {
        try {
            $sql = "SELECT l.*,
                           i.name as instructor_name,
                           v.plate as vehicle_plate,
                           ts.discipline_id,
                           td.name as discipline_name,
                           ts.class_id,
                           tc.name as class_name
                    FROM {$this->table} l
                    LEFT JOIN instructors i ON l.instructor_id = i.id
                    LEFT JOIN vehicles v ON l.vehicle_id = v.id
                    LEFT JOIN theory_sessions ts ON l.theory_session_id = ts.id
                    LEFT JOIN theory_disciplines td ON ts.discipline_id = td.id
                    LEFT JOIN theory_classes tc ON ts.class_id = tc.id
                    WHERE l.student_id = ?
                    ORDER BY l.scheduled_date DESC, l.scheduled_time DESC";
            
            if ($limit) {
                $sql .= " LIMIT " . (int)$limit;
            }
            
            $stmt = $this->query($sql, [$studentId]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            // Se a tabela instructors não existe, fazer query sem JOIN
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'instructors') !== false) {
                error_log("[Lesson::findByStudent] Tabela 'instructors' não existe. Fazendo query sem JOIN.");
                $sql = "SELECT l.*,
                               NULL as instructor_name,
                               v.plate as vehicle_plate,
                               ts.discipline_id,
                               td.name as discipline_name,
                               ts.class_id,
                               tc.name as class_name
                        FROM {$this->table} l
                        LEFT JOIN vehicles v ON l.vehicle_id = v.id
                        LEFT JOIN theory_sessions ts ON l.theory_session_id = ts.id
                        LEFT JOIN theory_disciplines td ON ts.discipline_id = td.id
                        LEFT JOIN theory_classes tc ON ts.class_id = tc.id
                        WHERE l.student_id = ?
                        ORDER BY l.scheduled_date DESC, l.scheduled_time DESC";
                
                if ($limit) {
                    $sql .= " LIMIT " . (int)$limit;
                }
                
                $stmt = $this->query($sql, [$studentId]);
                return $stmt->fetchAll();
            }
            // Re-throw outros erros
            throw $e;
        }
    }

    /**
     * Busca aulas de uma matrícula
     * Trata caso onde tabela instructors não existe
     */
    public function findByEnrollment($enrollmentId)
    {
        try {
            $stmt = $this->query(
                "SELECT l.*,
                        i.name as instructor_name,
                        v.plate as vehicle_plate
                 FROM {$this->table} l
                 LEFT JOIN instructors i ON l.instructor_id = i.id
                 LEFT JOIN vehicles v ON l.vehicle_id = v.id
                 WHERE l.enrollment_id = ?
                 ORDER BY l.scheduled_date ASC, l.scheduled_time ASC",
                [$enrollmentId]
            );
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            // Se a tabela instructors não existe, fazer query sem JOIN
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'instructors') !== false) {
                error_log("[Lesson::findByEnrollment] Tabela 'instructors' não existe. Fazendo query sem JOIN.");
                $stmt = $this->query(
                    "SELECT l.*,
                            NULL as instructor_name,
                            v.plate as vehicle_plate
                     FROM {$this->table} l
                     LEFT JOIN vehicles v ON l.vehicle_id = v.id
                     WHERE l.enrollment_id = ?
                     ORDER BY l.scheduled_date ASC, l.scheduled_time ASC",
                    [$enrollmentId]
                );
                return $stmt->fetchAll();
            }
            // Re-throw outros erros
            throw $e;
        }
    }

    /**
     * Busca a próxima aula agendada de um aluno
     * Trata caso onde tabela instructors não existe
     */
    public function findNextByStudent($studentId)
    {
        try {
            $today = date('Y-m-d');
            $stmt = $this->query(
                "SELECT l.*,
                        i.name as instructor_name,
                        v.plate as vehicle_plate
                 FROM {$this->table} l
                 LEFT JOIN instructors i ON l.instructor_id = i.id
                 LEFT JOIN vehicles v ON l.vehicle_id = v.id
                 WHERE l.student_id = ?
                   AND l.status = 'agendada'
                   AND (l.scheduled_date > ? OR (l.scheduled_date = ? AND l.scheduled_time >= CURTIME()))
                 ORDER BY l.scheduled_date ASC, l.scheduled_time ASC
                 LIMIT 1",
                [$studentId, $today, $today]
            );
            return $stmt->fetch();
        } catch (\PDOException $e) {
            // Se a tabela instructors não existe, fazer query sem JOIN
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'instructors') !== false) {
                error_log("[Lesson::findNextByStudent] Tabela 'instructors' não existe. Fazendo query sem JOIN.");
                $today = date('Y-m-d');
                $stmt = $this->query(
                    "SELECT l.*,
                            NULL as instructor_name,
                            v.plate as vehicle_plate
                     FROM {$this->table} l
                     LEFT JOIN vehicles v ON l.vehicle_id = v.id
                     WHERE l.student_id = ?
                       AND l.status = 'agendada'
                       AND (l.scheduled_date > ? OR (l.scheduled_date = ? AND l.scheduled_time >= CURTIME()))
                     ORDER BY l.scheduled_date ASC, l.scheduled_time ASC
                     LIMIT 1",
                    [$studentId, $today, $today]
                );
                return $stmt->fetch();
            }
            // Re-throw outros erros
            throw $e;
        }
    }
}
