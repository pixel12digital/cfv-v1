<?php
/**
 * AgendamentoGuards - Sistema de validação de regras de negócio para agendamentos
 * Implementa as guardas conforme checklist: exames, provas, financeiro, conflitos
 * 
 * @author Sistema CFC
 * @version 1.0
 * @since 2024
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../admin/includes/ExamesRulesService.php';
require_once __DIR__ . '/../../admin/includes/FinanceiroRulesService.php';

class AgendamentoGuards {
    private $db;
    private $examesRules;
    private $financeiroRules;
    
    public function __construct() {
        $this->db = db();
        $this->examesRules = new ExamesRulesService();
        $this->financeiroRules = new FinanceiroRulesService();
    }
    
    /**
     * Verificar se aluno pode agendar aula teórica (exames OK)
     * Usa ExamesRulesService::podeAgendarProvaTeorica() internamente
     * 
     * @param int $alunoId ID do aluno
     * @return array Resultado da validação (mantém formato legado para compatibilidade)
     */
    public function verificarExamesOK($alunoId) {
        try {
            // Usar service centralizado
            $resultado = $this->examesRules->podeAgendarProvaTeorica($alunoId);
            
            // Converter formato do service para formato legado (compatibilidade)
            return [
                'permitido' => $resultado['ok'],
                'motivo' => $resultado['mensagem'],
                'tipo' => $resultado['codigo']
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao verificar exames via service (aluno_id={$alunoId}): " . $e->getMessage());
            return [
                'permitido' => false,
                'motivo' => 'Erro ao verificar exames',
                'tipo' => 'erro_sistema'
            ];
        }
    }
    
    /**
     * Verificar se aluno pode agendar aula prática (prova teórica aprovada)
     * Usa ExamesRulesService::podeAgendarAulaPratica() internamente
     * 
     * @param int $alunoId ID do aluno
     * @return array Resultado da validação (mantém formato legado para compatibilidade)
     */
    public function verificarProvaTeoricaAprovada($alunoId) {
        try {
            // Usar service centralizado
            $resultado = $this->examesRules->podeAgendarAulaPratica($alunoId);
            
            // Converter formato do service para formato legado (compatibilidade)
            return [
                'permitido' => $resultado['ok'],
                'motivo' => $resultado['mensagem'],
                'tipo' => $resultado['codigo']
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao verificar prova teórica via service (aluno_id={$alunoId}): " . $e->getMessage());
            return [
                'permitido' => false,
                'motivo' => 'Erro ao verificar prova teórica',
                'tipo' => 'erro_sistema'
            ];
        }
    }
    
    /**
     * Verificar situação financeira do aluno usando FinanceiroRulesService
     * @param int $alunoId ID do aluno
     * @param bool $flagAtiva Se a verificação financeira está ativa
     * @return array Resultado da validação
     */
    public function verificarSituacaoFinanceira($alunoId, $flagAtiva = true) {
        if (!$flagAtiva) {
            return [
                'permitido' => true,
                'motivo' => 'Verificação financeira desabilitada',
                'tipo' => 'financeiro_desabilitado'
            ];
        }
        
        // Usar FinanceiroRulesService
        $resultado = $this->financeiroRules->podeAgendar($alunoId);
        
        // Converter formato do service para formato legado (compatibilidade)
        return [
            'permitido' => $resultado['ok'],
            'motivo' => $resultado['mensagem'],
            'tipo' => $resultado['codigo']
        ];
    }
    
    
    /**
     * Verificar conflitos de horário (aluno, instrutor, veículo)
     * @param array $dadosAula Dados da aula
     * @param int $aulaIdExcluir ID da aula a excluir da verificação (para edição)
     * @return array Resultado da validação
     */
    public function verificarConflitos($dadosAula, $aulaIdExcluir = null) {
        try {
            $dataAula = $dadosAula['data_aula'];
            $horaInicio = $dadosAula['hora_inicio'];
            $horaFim = $dadosAula['hora_fim'];
            $alunoId = $dadosAula['aluno_id'];
            $instrutorId = $dadosAula['instrutor_id'];
            $veiculoId = $dadosAula['veiculo_id'] ?? null;
            
            // 1. Verificar conflito do aluno
            $conflitoAluno = $this->verificarConflitoAluno($alunoId, $dataAula, $horaInicio, $horaFim, $aulaIdExcluir);
            if (!$conflitoAluno['disponivel']) {
                return $conflitoAluno;
            }
            
            // 2. Verificar conflito do instrutor
            $conflitoInstrutor = $this->verificarConflitoInstrutor($instrutorId, $dataAula, $horaInicio, $horaFim, $aulaIdExcluir);
            if (!$conflitoInstrutor['disponivel']) {
                return $conflitoInstrutor;
            }
            
            // 3. Verificar conflito do veículo (se aplicável)
            if ($veiculoId) {
                $conflitoVeiculo = $this->verificarConflitoVeiculo($veiculoId, $dataAula, $horaInicio, $horaFim, $aulaIdExcluir);
                if (!$conflitoVeiculo['disponivel']) {
                    return $conflitoVeiculo;
                }
            }
            
            return [
                'disponivel' => true,
                'motivo' => 'Nenhum conflito detectado',
                'tipo' => 'sem_conflitos'
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao verificar conflitos: " . $e->getMessage());
            return [
                'disponivel' => false,
                'motivo' => 'Erro ao verificar conflitos',
                'tipo' => 'erro_sistema'
            ];
        }
    }
    
    /**
     * Verificar conflito específico do aluno
     */
    private function verificarConflitoAluno($alunoId, $dataAula, $horaInicio, $horaFim, $aulaIdExcluir = null) {
        $sql = "SELECT COUNT(*) as total FROM aulas 
                WHERE aluno_id = ? 
                AND data_aula = ? 
                AND status != 'cancelada'
                AND ((hora_inicio <= ? AND hora_fim > ?) 
                     OR (hora_inicio < ? AND hora_fim >= ?)
                     OR (hora_inicio >= ? AND hora_fim <= ?))";
        
        $params = [$alunoId, $dataAula, $horaInicio, $horaInicio, $horaFim, $horaFim, $horaInicio, $horaFim];
        
        if ($aulaIdExcluir) {
            $sql .= " AND id != ?";
            $params[] = $aulaIdExcluir;
        }
        
        $resultado = $this->db->fetch($sql, $params);
        
        if ($resultado['total'] > 0) {
            return [
                'disponivel' => false,
                'motivo' => 'Aluno já possui aula agendada neste horário',
                'tipo' => 'conflito_aluno'
            ];
        }
        
        return ['disponivel' => true];
    }
    
    /**
     * Verificar conflito específico do instrutor
     */
    private function verificarConflitoInstrutor($instrutorId, $dataAula, $horaInicio, $horaFim, $aulaIdExcluir = null) {
        $sql = "SELECT COUNT(*) as total FROM aulas 
                WHERE instrutor_id = ? 
                AND data_aula = ? 
                AND status != 'cancelada'
                AND ((hora_inicio <= ? AND hora_fim > ?) 
                     OR (hora_inicio < ? AND hora_fim >= ?)
                     OR (hora_inicio >= ? AND hora_fim <= ?))";
        
        $params = [$instrutorId, $dataAula, $horaInicio, $horaInicio, $horaFim, $horaFim, $horaInicio, $horaFim];
        
        if ($aulaIdExcluir) {
            $sql .= " AND id != ?";
            $params[] = $aulaIdExcluir;
        }
        
        $resultado = $this->db->fetch($sql, $params);
        
        if ($resultado['total'] > 0) {
            // Buscar nome do instrutor para mensagem mais específica
            $nomeInstrutor = $this->db->fetchColumn("SELECT COALESCE(u.nome, i.nome) FROM instrutores i LEFT JOIN usuarios u ON i.usuario_id = u.id WHERE i.id = ?", [$instrutorId]);
            return [
                'disponivel' => false,
                'motivo' => "👨‍🏫 INSTRUTOR INDISPONÍVEL: O instrutor {$nomeInstrutor} já possui aula agendada no horário {$horaInicio} às {$horaFim}. Escolha outro horário ou instrutor.",
                'tipo' => 'conflito_instrutor'
            ];
        }
        
        return ['disponivel' => true];
    }
    
    /**
     * Verificar conflito específico do veículo
     */
    private function verificarConflitoVeiculo($veiculoId, $dataAula, $horaInicio, $horaFim, $aulaIdExcluir = null) {
        $sql = "SELECT COUNT(*) as total FROM aulas 
                WHERE veiculo_id = ? 
                AND data_aula = ? 
                AND status != 'cancelada'
                AND ((hora_inicio <= ? AND hora_fim > ?) 
                     OR (hora_inicio < ? AND hora_fim >= ?)
                     OR (hora_inicio >= ? AND hora_fim <= ?))";
        
        $params = [$veiculoId, $dataAula, $horaInicio, $horaInicio, $horaFim, $horaFim, $horaInicio, $horaFim];
        
        if ($aulaIdExcluir) {
            $sql .= " AND id != ?";
            $params[] = $aulaIdExcluir;
        }
        
        $resultado = $this->db->fetch($sql, $params);
        
        if ($resultado['total'] > 0) {
            // Buscar informações do veículo para mensagem mais específica
            $infoVeiculo = $this->db->fetch("SELECT placa, modelo, marca FROM veiculos WHERE id = ?", [$veiculoId]);
            $veiculoInfo = "{$infoVeiculo['marca']} {$infoVeiculo['modelo']} - {$infoVeiculo['placa']}";
            return [
                'disponivel' => false,
                'motivo' => "🚗 VEÍCULO INDISPONÍVEL: O veículo {$veiculoInfo} já está em uso no horário {$horaInicio} às {$horaFim}. Escolha outro horário ou veículo.",
                'tipo' => 'conflito_veiculo'
            ];
        }
        
        return ['disponivel' => true];
    }
    
    /**
     * Verificar limite de aulas por instrutor por dia (máximo 3)
     * @param int $instrutorId ID do instrutor
     * @param string $dataAula Data da aula
     * @param int $aulaIdExcluir ID da aula a excluir da contagem
     * @return array Resultado da validação
     */
    public function verificarLimiteDiarioInstrutor($instrutorId, $dataAula, $aulaIdExcluir = null) {
        try {
            $sql = "SELECT COUNT(*) as total FROM aulas 
                    WHERE instrutor_id = ? 
                    AND data_aula = ? 
                    AND status != 'cancelada'";
            
            $params = [$instrutorId, $dataAula];
            
            if ($aulaIdExcluir) {
                $sql .= " AND id != ?";
                $params[] = $aulaIdExcluir;
            }
            
            $resultado = $this->db->fetch($sql, $params);
            $totalAulas = $resultado['total'];
            
            $limiteMaximo = 3; // Configurável
            
            if ($totalAulas >= $limiteMaximo) {
                return [
                    'disponivel' => false,
                    'motivo' => "Instrutor já possui {$totalAulas} aulas agendadas para este dia. Limite máximo: {$limiteMaximo} aulas por dia.",
                    'tipo' => 'limite_diario_instrutor',
                    'total_aulas' => $totalAulas,
                    'limite' => $limiteMaximo
                ];
            }
            
            return [
                'disponivel' => true,
                'total_aulas' => $totalAulas,
                'limite' => $limiteMaximo,
                'aulas_restantes' => $limiteMaximo - $totalAulas
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao verificar limite diário do instrutor: " . $e->getMessage());
            return [
                'disponivel' => false,
                'motivo' => 'Erro ao verificar limite diário',
                'tipo' => 'erro_sistema'
            ];
        }
    }
    
    /**
     * Verificar limite de aulas práticas por aluno por dia (máximo 3)
     * @param int $alunoId ID do aluno
     * @param string $dataAula Data da aula
     * @param int $aulasNovas Quantidade de aulas novas sendo agendadas
     * @return array Resultado da validação
     */
    public function verificarLimiteDiarioAluno($alunoId, $dataAula, $aulasNovas = 1) {
        try {
            $sql = "SELECT COUNT(*) as total FROM aulas 
                    WHERE aluno_id = ? 
                    AND data_aula = ? 
                    AND status != 'cancelada' 
                    AND tipo_aula = 'pratica'";
            
            $resultado = $this->db->fetch($sql, [$alunoId, $dataAula]);
            $totalAulas = $resultado['total'];
            $totalComNovas = $totalAulas + $aulasNovas;
            
            $limiteMaximo = 3; // Configurável
            
            if ($totalComNovas > $limiteMaximo) {
                return [
                    'disponivel' => false,
                    'motivo' => "🚫 LIMITE DE AULAS EXCEDIDO: O aluno já possui {$totalAulas} aulas práticas agendadas para este dia. Com {$aulasNovas} nova(s) aula(s) prática(s), excederia o limite máximo de {$limiteMaximo} aulas práticas por dia.",
                    'tipo' => 'limite_diario_aluno',
                    'total_aulas' => $totalAulas,
                    'aulas_novas' => $aulasNovas,
                    'limite' => $limiteMaximo
                ];
            }
            
            return [
                'disponivel' => true,
                'total_aulas' => $totalAulas,
                'aulas_novas' => $aulasNovas,
                'limite' => $limiteMaximo,
                'aulas_restantes' => $limiteMaximo - $totalComNovas
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao verificar limite diário do aluno: " . $e->getMessage());
            return [
                'disponivel' => false,
                'motivo' => 'Erro ao verificar limite diário',
                'tipo' => 'erro_sistema'
            ];
        }
    }
    
    /**
     * Verificar duração da aula (deve ser exatamente 50 minutos)
     * @param string $horaInicio Hora de início
     * @param string $horaFim Hora de fim
     * @return array Resultado da validação
     */
    public function verificarDuracaoAula($horaInicio, $horaFim) {
        try {
            $inicio = strtotime($horaInicio);
            $fim = strtotime($horaFim);
            $duracao = ($fim - $inicio) / 60; // Duração em minutos
            
            if ($duracao != 50) {
                return [
                    'valida' => false,
                    'motivo' => "A aula deve ter exatamente 50 minutos de duração. Duração atual: {$duracao} minutos",
                    'tipo' => 'duracao_invalida',
                    'duracao_atual' => $duracao,
                    'duracao_esperada' => 50
                ];
            }
            
            return [
                'valida' => true,
                'motivo' => 'Duração da aula está correta (50 minutos)',
                'tipo' => 'duracao_valida',
                'duracao' => $duracao
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao verificar duração da aula: " . $e->getMessage());
            return [
                'valida' => false,
                'motivo' => 'Erro ao verificar duração da aula',
                'tipo' => 'erro_sistema'
            ];
        }
    }
    
    /**
     * Verificar horário de funcionamento do CFC
     * @param string $horaInicio Hora de início
     * @param string $horaFim Hora de fim
     * @return array Resultado da validação
     */
    public function verificarHorarioFuncionamento($horaInicio, $horaFim) {
        // NOTA: Restrição de horário (07:00-22:00) removida em Jan/2026
        // Agora permite agendamentos em qualquer horário do dia
        // Mantida a estrutura do método para compatibilidade com chamadas existentes
        return [
            'valida' => true,
            'motivo' => 'Horário permitido (sem restrição de janela)',
            'tipo' => 'horario_valido',
            'hora_inicio' => $horaInicio,
            'hora_fim' => $horaFim
        ];
    }
    
    /**
     * Validação completa para agendamento de aula
     * @param array $dadosAula Dados da aula
     * @param int $aulaIdExcluir ID da aula a excluir da verificação (para edição)
     * @return array Resultado da validação completa
     */
    public function validarAgendamentoCompleto($dadosAula, $aulaIdExcluir = null) {
        try {
            $resultado = [
                'valido' => true,
                'motivo' => 'Agendamento válido',
                'tipo' => 'agendamento_valido',
                'validacoes' => []
            ];
            
            $tipoAula = $dadosAula['tipo_aula'];
            $alunoId = $dadosAula['aluno_id'];
            $horaInicio = $dadosAula['hora_inicio'];
            $horaFim = $dadosAula['hora_fim'];
            
            // 1. Verificar duração da aula
            $duracao = $this->verificarDuracaoAula($horaInicio, $horaFim);
            $resultado['validacoes']['duracao'] = $duracao;
            if (!$duracao['valida']) {
                $resultado['valido'] = false;
                $resultado['motivo'] = $duracao['motivo'];
                $resultado['tipo'] = $duracao['tipo'];
                return $resultado;
            }
            
            // 2. Verificar horário de funcionamento
            $horario = $this->verificarHorarioFuncionamento($horaInicio, $horaFim);
            $resultado['validacoes']['horario'] = $horario;
            if (!$horario['valida']) {
                $resultado['valido'] = false;
                $resultado['motivo'] = $horario['motivo'];
                $resultado['tipo'] = $horario['tipo'];
                return $resultado;
            }
            
            // 3. Verificar guardas específicas por tipo de aula
            // Usa ExamesRulesService centralizado para validações de aptidão
            if ($tipoAula === 'teorica') {
                // Validação de exames médico e psicotécnico (via service centralizado)
                $exames = $this->verificarExamesOK($alunoId);
                $resultado['validacoes']['exames'] = $exames;
                if (!$exames['permitido']) {
                    $resultado['valido'] = false;
                    // CORREÇÃO: mensagem corrigida (era "Prática bloqueada" incorretamente)
                    $resultado['motivo'] = $exames['motivo']; // Mensagem já vem completa do service
                    $resultado['tipo'] = $exames['tipo'] ?? 'exames_nao_aprovados';
                    return $resultado;
                }
            } elseif ($tipoAula === 'pratica') {
                // Validação de prova teórica aprovada (via service centralizado)
                $provaTeorica = $this->verificarProvaTeoricaAprovada($alunoId);
                $resultado['validacoes']['prova_teorica'] = $provaTeorica;
                if (!$provaTeorica['permitido']) {
                    $resultado['valido'] = false;
                    // Mensagem: "Prática bloqueada: [motivo]" mantida para compatibilidade com API atual
                    $resultado['motivo'] = "Prática bloqueada: " . $provaTeorica['motivo'];
                    $resultado['tipo'] = $provaTeorica['tipo'] ?? 'prova_teorica_nao_aprovada';
                    return $resultado;
                }
                
                // Verificar limite de aulas práticas por dia
                $limiteAluno = $this->verificarLimiteDiarioAluno($alunoId, $dadosAula['data_aula']);
                $resultado['validacoes']['limite_aluno'] = $limiteAluno;
                if (!$limiteAluno['disponivel']) {
                    $resultado['valido'] = false;
                    $resultado['motivo'] = $limiteAluno['motivo'];
                    $resultado['tipo'] = $limiteAluno['tipo'];
                    return $resultado;
                }
            }
            
            // 4. Verificar situação financeira (se flag ativa)
            $financeiro = $this->verificarSituacaoFinanceira($alunoId, true);
            $resultado['validacoes']['financeiro'] = $financeiro;
            if (!$financeiro['permitido']) {
                $resultado['valido'] = false;
                $resultado['motivo'] = "Financeiro bloqueado: " . $financeiro['motivo'];
                $resultado['tipo'] = 'financeiro_bloqueado';
                return $resultado;
            }
            
            // 5. Verificar conflitos
            $conflitos = $this->verificarConflitos($dadosAula, $aulaIdExcluir);
            $resultado['validacoes']['conflitos'] = $conflitos;
            if (!$conflitos['disponivel']) {
                $resultado['valido'] = false;
                $resultado['motivo'] = $conflitos['motivo'];
                $resultado['tipo'] = $conflitos['tipo'];
                return $resultado;
            }
            
            // 6. Verificar limite diário do instrutor
            $limiteInstrutor = $this->verificarLimiteDiarioInstrutor($dadosAula['instrutor_id'], $dadosAula['data_aula'], $aulaIdExcluir);
            $resultado['validacoes']['limite_instrutor'] = $limiteInstrutor;
            if (!$limiteInstrutor['disponivel']) {
                $resultado['valido'] = false;
                $resultado['motivo'] = $limiteInstrutor['motivo'];
                $resultado['tipo'] = $limiteInstrutor['tipo'];
                return $resultado;
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("Erro na validação completa: " . $e->getMessage());
            return [
                'valido' => false,
                'motivo' => 'Erro interno na validação',
                'tipo' => 'erro_sistema',
                'validacoes' => []
            ];
        }
    }
}
?>
