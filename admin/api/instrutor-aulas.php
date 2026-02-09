<?php
/**
 * API para gerenciamento de aulas por instrutores
 * Permite cancelamento e transferência de aulas
 * 
 * FASE 1 - Implementação: 2024
 * Arquivo: admin/api/instrutor-aulas.php
 * 
 * Segurança:
 * - Apenas usuários com tipo = 'instrutor' podem usar
 * - Valida se a aula pertence ao instrutor logado (aulas.instrutor_id = instrutor_atual)
 * - Registra motivo/justificativa em log de ações
 * 
 * AÇÕES SUPORTADAS ATUALMENTE:
 * - cancelamento: Cancela uma aula prática (requer justificativa)
 * - transferencia: Transfere uma aula prática para outra data/hora (requer justificativa, nova_data, nova_hora)
 * 
 * AÇÕES QUE SERÃO ADICIONADAS (Tarefa 2.2 - Fase 2):
 * - iniciar: Inicia uma aula prática (status 'agendada' → 'em_andamento', registra inicio_at e km_inicial)
 * - finalizar: Finaliza uma aula prática (status 'em_andamento' → 'concluida', registra fim_at e km_final)
 * 
 * NOTA: Requer migration 999-add-campos-km-timestamps-aulas.sql para colunas:
 * - km_inicial INT NULL
 * - km_final INT NULL  
 * - inicio_at TIMESTAMP NULL
 * - fim_at TIMESTAMP NULL
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

function returnJsonSuccess($data = null, $message = 'Sucesso') {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function returnJsonError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

try {
    // Verificar método OPTIONS (CORS)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // Verificar autenticação
    $user = getCurrentUser();
    if (!$user) {
        returnJsonError('Usuário não autenticado', 401);
    }

    // VALIDAÇÃO CRÍTICA: Apenas instrutores podem usar esta API
    if ($user['tipo'] !== 'instrutor') {
        returnJsonError('Acesso negado. Apenas instrutores podem usar esta API.', 403);
    }

    $db = db();

    // FASE 2 - Correção: Usar função centralizada getCurrentInstrutorId()
    // Arquivo: admin/api/instrutor-aulas.php (linha ~61)
    // Mesma lógica, mas agora usando função reutilizável
    $instrutorId = getCurrentInstrutorId($user['id']);
    if (!$instrutorId) {
        // Log detalhado para diagnóstico
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            error_log(sprintf(
                '[INSTRUTOR_AULAS_API] Instrutor não encontrado - usuario_id=%d, tipo=%s, email=%s, timestamp=%s, ip=%s',
                $user['id'],
                $user['tipo'] ?? 'não definido',
                $user['email'] ?? 'não definido',
                date('Y-m-d H:i:s'),
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ));
        }
        returnJsonError('Instrutor não encontrado. Verifique seu cadastro.', 404);
    }

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            // Processar cancelamento ou transferência
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $input = $_POST;
            }

            // Validações obrigatórias
            $tipoAcao = $input['tipo_acao'] ?? null;
            $isBloco = in_array($tipoAcao, ['iniciar_bloco', 'finalizar_bloco']);
            
            if (empty($tipoAcao)) {
                returnJsonError('Tipo de ação é obrigatório');
            }
            if (!$isBloco && empty($input['aula_id'])) {
                returnJsonError('ID da aula é obrigatório');
            }
            if ($isBloco && empty($input['aula_ids'])) {
                returnJsonError('IDs das aulas do bloco são obrigatórios');
            }

            $aulaId = $isBloco ? null : (int)$input['aula_id'];
            $aulaIds = $isBloco ? array_map('intval', array_filter(explode(',', $input['aula_ids']))) : [$aulaId];
            $justificativa = isset($input['justificativa']) ? trim($input['justificativa']) : null;
            $motivo = $input['motivo'] ?? null;
            $novaData = $input['nova_data'] ?? null;
            $novaHora = $input['nova_hora'] ?? null;

            // Validar tipo de ação
            if (!in_array($tipoAcao, ['cancelamento', 'transferencia', 'iniciar', 'finalizar', 'iniciar_bloco', 'finalizar_bloco'])) {
                returnJsonError('Tipo de ação inválido. Use "cancelamento", "transferencia", "iniciar", "finalizar", "iniciar_bloco" ou "finalizar_bloco"');
            }
            
            // Justificativa é obrigatória apenas para cancelamento e transferência
            if (in_array($tipoAcao, ['cancelamento', 'transferencia']) && empty($justificativa)) {
                returnJsonError('Justificativa é obrigatória para ' . $tipoAcao);
            }

            // VALIDAÇÃO CRÍTICA: Verificar se a(s) aula(s) pertence(m) ao instrutor logado
            if ($isBloco) {
                $aulas = [];
                foreach ($aulaIds as $aid) {
                    if ($aid <= 0) continue;
                    $a = $db->fetch("
                        SELECT a.*, al.nome as aluno_nome, al.telefone as aluno_telefone,
                               v.modelo as veiculo_modelo, v.placa as veiculo_placa
                        FROM aulas a
                        JOIN alunos al ON a.aluno_id = al.id
                        LEFT JOIN veiculos v ON a.veiculo_id = v.id
                        WHERE a.id = ? AND a.instrutor_id = ? AND a.tipo_aula = 'pratica'
                    ", [$aid, $instrutorId]);
                    if ($a) $aulas[] = $a;
                }
                if (empty($aulas)) {
                    returnJsonError('Nenhuma aula do bloco encontrada ou não pertence a você', 404);
                }
                $aula = $aulas[0]; // Para compatibilidade com o resto do código
            } else {
                $whereStatus = "a.status != 'cancelada'";
                if (in_array($tipoAcao, ['iniciar', 'finalizar'])) {
                    $whereStatus = "1=1";
                }
                $aula = $db->fetch("
                    SELECT a.*, 
                           al.nome as aluno_nome, al.telefone as aluno_telefone,
                           v.modelo as veiculo_modelo, v.placa as veiculo_placa
                    FROM aulas a
                    JOIN alunos al ON a.aluno_id = al.id
                    LEFT JOIN veiculos v ON a.veiculo_id = v.id
                    WHERE a.id = ? AND a.instrutor_id = ? AND $whereStatus
                ", [$aulaId, $instrutorId]);

                if (!$aula) {
                    returnJsonError('Aula não encontrada ou não pertence a você', 404);
                }
                $aulas = [$aula];
            }

            // Validações específicas por tipo de ação
            if ($tipoAcao === 'transferencia') {
                if (empty($novaData)) {
                    returnJsonError('Nova data é obrigatória para transferência');
                }
                if (empty($novaHora)) {
                    returnJsonError('Novo horário é obrigatório para transferência');
                }

                // Validar formato de data
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $novaData)) {
                    returnJsonError('Formato de data inválido. Use YYYY-MM-DD');
                }

                // Validar que nova data não é no passado
                $dataNova = strtotime($novaData);
                $hoje = strtotime(date('Y-m-d'));
                if ($dataNova < $hoje) {
                    returnJsonError('A nova data não pode ser no passado');
                }

                // Validar conflito de horário (verificar se instrutor já tem aula no mesmo horário)
                $conflito = $db->fetch("
                    SELECT id FROM aulas 
                    WHERE instrutor_id = ? 
                      AND data_aula = ? 
                      AND hora_inicio = ? 
                      AND id != ? 
                      AND status != 'cancelada'
                ", [$instrutorId, $novaData, $novaHora, $aulaId]);

                if ($conflito) {
                    returnJsonError('Você já possui uma aula agendada neste horário');
                }
            }

            // Verificar se aula pode ser cancelada/transferida (regras de negócio)
            $dataAula = strtotime($aula['data_aula']);
            $horaAula = strtotime($aula['hora_inicio']);
            $agora = time();
            $tempoAteAula = ($dataAula + $horaAula) - $agora;
            $horasAteAula = $tempoAteAula / 3600;

            // Regra: mínimo 2 horas de antecedência (apenas para cancelamento e transferência)
            if (in_array($tipoAcao, ['cancelamento', 'transferencia']) && $horasAteAula < 2 && $horasAteAula > 0) {
                returnJsonError('Ação só pode ser realizada com pelo menos 2 horas de antecedência');
            }

            // Validações de status específicas por tipo de ação
            if ($tipoAcao === 'cancelamento' || $tipoAcao === 'transferencia') {
                if ($isBloco) {
                    returnJsonError('Use cancelamento ou transferência por aula individual');
                }
                if ($aula['status'] === 'concluida') {
                    returnJsonError('Aula já foi concluída e não pode ser alterada');
                }
                if ($aula['status'] === 'em_andamento') {
                    returnJsonError('Aula em andamento não pode ser alterada');
                }
            } elseif ($tipoAcao === 'iniciar' || $tipoAcao === 'iniciar_bloco') {
                $paraIniciar = $isBloco ? array_filter($aulas, fn($a) => ($a['status'] ?? '') === 'agendada') : [$aula];
                if (empty($paraIniciar)) {
                    returnJsonError($isBloco ? 'Nenhuma aula agendada no bloco para iniciar' : 'Apenas aulas agendadas podem ser iniciadas');
                }
                if (!$isBloco && $aula['status'] !== 'agendada') {
                    returnJsonError('Apenas aulas agendadas podem ser iniciadas');
                }
            } elseif ($tipoAcao === 'finalizar' || $tipoAcao === 'finalizar_bloco') {
                $paraFinalizar = $isBloco ? array_filter($aulas, fn($a) => in_array($a['status'] ?? '', ['agendada', 'em_andamento'])) : [$aula];
                if (empty($paraFinalizar)) {
                    returnJsonError($isBloco ? 'Nenhuma aula no bloco para finalizar' : 'Apenas aulas em andamento podem ser finalizadas');
                }
                if (!$isBloco && $aula['status'] !== 'em_andamento') {
                    returnJsonError('Apenas aulas em andamento podem ser finalizadas');
                }
            }

            // Processar ação
            if ($tipoAcao === 'cancelamento') {
                // Cancelar a aula
                $observacoesAtualizadas = ($aula['observacoes'] ?? '') . "\n\n[CANCELADA POR INSTRUTOR] " . date('d/m/Y H:i:s') . "\nMotivo: " . ($motivo ?? 'Não informado') . "\nJustificativa: " . $justificativa;
                
                $result = $db->query("
                    UPDATE aulas 
                    SET status = 'cancelada', 
                        observacoes = ?,
                        atualizado_em = NOW()
                    WHERE id = ? AND instrutor_id = ?
                ", [$observacoesAtualizadas, $aulaId, $instrutorId]);

                if (!$result) {
                    returnJsonError('Erro ao cancelar aula', 500);
                }

                // Log de auditoria
                if (defined('LOG_ENABLED') && LOG_ENABLED) {
                    error_log(sprintf(
                        '[INSTRUTOR_CANCELAR_AULA] instrutor_id=%d, usuario_id=%d, aula_id=%d, motivo=%s, timestamp=%s, ip=%s',
                        $instrutorId,
                        $user['id'],
                        $aulaId,
                        $motivo ?? 'não informado',
                        date('Y-m-d H:i:s'),
                        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                    ));
                }

                returnJsonSuccess([
                    'aula_id' => $aulaId,
                    'acao' => 'cancelamento',
                    'status' => 'cancelada'
                ], 'Aula cancelada com sucesso');

            } else if ($tipoAcao === 'transferencia') {
                // Transferir aula (atualizar data/hora)
                $observacoesAtualizadas = ($aula['observacoes'] ?? '') . "\n\n[TRANSFERIDA POR INSTRUTOR] " . date('d/m/Y H:i:s') . "\nData original: " . $aula['data_aula'] . " " . $aula['hora_inicio'] . "\nNova data: " . $novaData . " " . $novaHora . "\nMotivo: " . ($motivo ?? 'Não informado') . "\nJustificativa: " . $justificativa;
                
                $result = $db->query("
                    UPDATE aulas 
                    SET data_aula = ?,
                        hora_inicio = ?,
                        observacoes = ?,
                        atualizado_em = NOW()
                    WHERE id = ? AND instrutor_id = ?
                ", [$novaData, $novaHora, $observacoesAtualizadas, $aulaId, $instrutorId]);

                if (!$result) {
                    returnJsonError('Erro ao transferir aula', 500);
                }

                // Log de auditoria
                if (defined('LOG_ENABLED') && LOG_ENABLED) {
                    error_log(sprintf(
                        '[INSTRUTOR_TRANSFERIR_AULA] instrutor_id=%d, usuario_id=%d, aula_id=%d, data_original=%s, data_nova=%s, motivo=%s, timestamp=%s, ip=%s',
                        $instrutorId,
                        $user['id'],
                        $aulaId,
                        $aula['data_aula'] . ' ' . $aula['hora_inicio'],
                        $novaData . ' ' . $novaHora,
                        $motivo ?? 'não informado',
                        date('Y-m-d H:i:s'),
                        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                    ));
                }

                returnJsonSuccess([
                    'aula_id' => $aulaId,
                    'acao' => 'transferencia',
                    'data_original' => $aula['data_aula'],
                    'hora_original' => $aula['hora_inicio'],
                    'data_nova' => $novaData,
                    'hora_nova' => $novaHora
                ], 'Aula transferida com sucesso');

            } else if ($tipoAcao === 'iniciar' || $tipoAcao === 'iniciar_bloco') {
                // TAREFA 2.2 - Iniciar aula prática (ou bloco)
                // Validar que é aula prática (KM só para práticas)
                if (!isset($aula['tipo_aula']) || $aula['tipo_aula'] !== 'pratica') {
                    returnJsonError('Apenas aulas práticas podem ser iniciadas');
                }

                // Verificar se colunas necessárias existem (proteção contra migration não aplicada)
                $checkColumns = $db->fetchAll("SHOW COLUMNS FROM aulas WHERE Field IN ('inicio_at', 'km_inicial')");
                $columnsFound = array_map(function($col) {
                    return $col['Field'];
                }, $checkColumns);
                
                if (!in_array('inicio_at', $columnsFound) || !in_array('km_inicial', $columnsFound)) {
                    returnJsonError('Estrutura do banco incompleta. Execute a migration: admin/migrations/999-add-campos-km-timestamps-aulas.sql', 500);
                }

                // Exigir km_inicial para aulas práticas
                if (empty($input['km_inicial']) || !is_numeric($input['km_inicial'])) {
                    returnJsonError('KM inicial é obrigatório para aulas práticas');
                }

                $kmInicial = (int)$input['km_inicial'];
                if ($kmInicial < 0) {
                    returnJsonError('KM inicial deve ser um número positivo ou zero');
                }

                $idsParaIniciar = $isBloco ? array_column(array_filter($aulas, fn($a) => ($a['status'] ?? '') === 'agendada'), 'id') : [$aulaId];
                $atualizadas = 0;
                
                foreach ($idsParaIniciar as $aid) {
                    $aParaObs = $db->fetch("SELECT observacoes FROM aulas WHERE id = ?", [$aid]);
                    $observacoesAtualizadas = ($aParaObs['observacoes'] ?? '') . "\n\n[INICIADA POR INSTRUTOR" . ($isBloco ? " - BLOCO" : '') . "] " . date('d/m/Y H:i:s') . "\nKM Inicial: " . $kmInicial . " km";
                    
                    $result = $db->query("
                        UPDATE aulas 
                        SET status = 'em_andamento', 
                            inicio_at = NOW(),
                            km_inicial = ?,
                            observacoes = ?,
                            atualizado_em = NOW()
                        WHERE id = ? 
                          AND instrutor_id = ? 
                          AND status = 'agendada'
                    ", [$kmInicial, $observacoesAtualizadas, $aid, $instrutorId]);
                    
                    if ($result) $atualizadas++;
                }

                if ($atualizadas === 0) {
                    returnJsonError('Nenhuma aula pôde ser iniciada. Verifique se ainda estão agendadas.', 500);
                }

                if (defined('LOG_ENABLED') && LOG_ENABLED) {
                    error_log(sprintf(
                        '[INSTRUTOR_INICIAR_%s] instrutor_id=%d, usuario_id=%d, aula_ids=%s, km_inicial=%d, timestamp=%s, ip=%s',
                        $isBloco ? 'BLOCO' : 'AULA',
                        $instrutorId,
                        $user['id'],
                        implode(',', $idsParaIniciar),
                        $kmInicial,
                        date('Y-m-d H:i:s'),
                        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                    ));
                }

                returnJsonSuccess([
                    'aula_ids' => $idsParaIniciar,
                    'acao' => $isBloco ? 'iniciar_bloco' : 'iniciar',
                    'status' => 'em_andamento',
                    'km_inicial' => $kmInicial,
                    'inicio_at' => date('Y-m-d H:i:s'),
                    'quantidade' => $atualizadas
                ], $isBloco ? "Bloco iniciado com sucesso ({$atualizadas} aula(s))" : 'Aula iniciada com sucesso');

            } else if ($tipoAcao === 'finalizar' || $tipoAcao === 'finalizar_bloco') {
                // TAREFA 2.2 - Finalizar aula prática (ou bloco)
                // Validar que é aula prática (KM só para práticas)
                if (!isset($aula['tipo_aula']) || $aula['tipo_aula'] !== 'pratica') {
                    returnJsonError('Apenas aulas práticas podem ser finalizadas');
                }

                // Verificar se colunas necessárias existem (proteção contra migration não aplicada)
                $checkColumns = $db->fetchAll("SHOW COLUMNS FROM aulas WHERE Field IN ('fim_at', 'km_final', 'km_inicial')");
                $columnsFound = array_map(function($col) {
                    return $col['Field'];
                }, $checkColumns);
                
                if (!in_array('fim_at', $columnsFound) || !in_array('km_final', $columnsFound) || !in_array('km_inicial', $columnsFound)) {
                    returnJsonError('Estrutura do banco incompleta. Execute a migration: admin/migrations/999-add-campos-km-timestamps-aulas.sql', 500);
                }

                // Exigir km_final para aulas práticas
                if (empty($input['km_final']) || !is_numeric($input['km_final'])) {
                    returnJsonError('KM final é obrigatório para aulas práticas');
                }

                $kmFinal = (int)$input['km_final'];
                if ($kmFinal < 0) {
                    returnJsonError('KM final deve ser um número positivo ou zero');
                }

                // Para bloco: usar km_inicial da primeira aula em andamento
                $kmInicialRef = $aula['km_inicial'] ?? null;
                if ($isBloco) {
                    foreach ($aulas as $aa) {
                        if (($aa['status'] ?? '') === 'em_andamento' && isset($aa['km_inicial']) && $aa['km_inicial'] !== null) {
                            $kmInicialRef = $aa['km_inicial'];
                            break;
                        }
                    }
                }
                if ($kmInicialRef !== null && $kmFinal < $kmInicialRef) {
                    returnJsonError('KM final (' . $kmFinal . ' km) não pode ser menor que KM inicial (' . $kmInicialRef . ' km)');
                }

                $idsParaFinalizar = $isBloco ? array_column(array_filter($aulas, fn($a) => ($a['status'] ?? '') === 'em_andamento'), 'id') : [$aulaId];
                $atualizadas = 0;
                
                foreach ($idsParaFinalizar as $aid) {
                    $aParaObs = $db->fetch("SELECT observacoes, km_inicial FROM aulas WHERE id = ?", [$aid]);
                    $observacoesAtualizadas = ($aParaObs['observacoes'] ?? '') . "\n\n[FINALIZADA POR INSTRUTOR" . ($isBloco ? " - BLOCO" : '') . "] " . date('d/m/Y H:i:s') . "\nKM Final: " . $kmFinal . " km";
                    if (isset($aParaObs['km_inicial']) && $aParaObs['km_inicial'] !== null) {
                        $observacoesAtualizadas .= " (Rodados: " . ($kmFinal - $aParaObs['km_inicial']) . " km)";
                    }
                    
                    $result = $db->query("
                        UPDATE aulas 
                        SET status = 'concluida', 
                            fim_at = NOW(),
                            km_final = ?,
                            observacoes = ?,
                            atualizado_em = NOW()
                        WHERE id = ? 
                          AND instrutor_id = ? 
                          AND status = 'em_andamento'
                    ", [$kmFinal, $observacoesAtualizadas, $aid, $instrutorId]);
                    
                    if ($result) $atualizadas++;
                }

                if ($atualizadas === 0) {
                    returnJsonError('Nenhuma aula pôde ser finalizada. Verifique se ainda estão em andamento.', 500);
                }

                if (defined('LOG_ENABLED') && LOG_ENABLED) {
                    error_log(sprintf(
                        '[INSTRUTOR_FINALIZAR_%s] instrutor_id=%d, usuario_id=%d, aula_ids=%s, km_final=%d, timestamp=%s, ip=%s',
                        $isBloco ? 'BLOCO' : 'AULA',
                        $instrutorId,
                        $user['id'],
                        implode(',', $idsParaFinalizar),
                        $kmFinal,
                        date('Y-m-d H:i:s'),
                        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                    ));
                }

                returnJsonSuccess([
                    'aula_ids' => $idsParaFinalizar,
                    'acao' => $isBloco ? 'finalizar_bloco' : 'finalizar',
                    'status' => 'concluida',
                    'km_final' => $kmFinal,
                    'km_inicial' => $kmInicialRef,
                    'fim_at' => date('Y-m-d H:i:s'),
                    'quantidade' => $atualizadas
                ], $isBloco ? "Bloco finalizado com sucesso ({$atualizadas} aula(s))" : 'Aula finalizada com sucesso');
            }

            break;

        case 'GET':
            // Listar aulas do instrutor (opcional, para uso futuro)
            $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d');
            $dataFim = $_GET['data_fim'] ?? date('Y-m-d', strtotime('+30 days'));
            $status = $_GET['status'] ?? null;

            $sql = "
                SELECT a.*, 
                       al.nome as aluno_nome, al.telefone as aluno_telefone,
                       v.modelo as veiculo_modelo, v.placa as veiculo_placa
                FROM aulas a
                JOIN alunos al ON a.aluno_id = al.id
                LEFT JOIN veiculos v ON a.veiculo_id = v.id
                WHERE a.instrutor_id = ?
                  AND a.data_aula >= ?
                  AND a.data_aula <= ?
            ";

            $params = [$instrutorId, $dataInicio, $dataFim];

            if ($status) {
                $sql .= " AND a.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY a.data_aula ASC, a.hora_inicio ASC";

            $aulas = $db->fetchAll($sql, $params);

            returnJsonSuccess($aulas, 'Aulas carregadas');
            break;

        default:
            returnJsonError('Método não permitido', 405);
    }

} catch (Exception $e) {
    error_log('Erro na API instrutor-aulas: ' . $e->getMessage());
    returnJsonError('Erro interno: ' . (DEBUG_MODE ? $e->getMessage() : 'Tente novamente mais tarde'), 500);
}
?>

