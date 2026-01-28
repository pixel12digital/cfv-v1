<?php
/**
 * Página de Detalhes da Turma Teórica com Edição Inline
 * Exibe informações completas da turma e permite edição direta nos campos
 */

// Obter turma_id da URL
$turmaId = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : 0;

// Verificar se há turma_id
if (!$turmaId) {
    echo '<div class="alert alert-danger">Turma não especificada.</div>';
    return;
}

// Processar agendamento de aula via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'agendar_aula') {
    $dadosAula = [
        'turma_id' => isset($_POST['turma_id']) ? (int)$_POST['turma_id'] : 0,
        'disciplina' => $_POST['disciplina'] ?? '',
        'instrutor_id' => isset($_POST['instrutor_id']) ? (int)$_POST['instrutor_id'] : 0,
        'data_aula' => $_POST['data_aula'] ?? '',
        'hora_inicio' => $_POST['hora_inicio'] ?? '',
        'quantidade_aulas' => isset($_POST['quantidade_aulas']) ? (int)$_POST['quantidade_aulas'] : 1,
        'criado_por' => getCurrentUser()['id'] ?? 1
    ];
    
    // Detectar se é requisição AJAX
    $isAjax = (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
    ) || (
        !empty($_SERVER['HTTP_ACCEPT']) && 
        strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
    ) || (
        isset($_POST['ajax']) && $_POST['ajax'] === 'true'
    );
    
    if ($isAjax && $dadosAula['turma_id'] && $dadosAula['disciplina']) {
        $resultado = $turmaManager->agendarAula($dadosAula);
        
        // Limpar qualquer output anterior
        if (ob_get_level()) {
            ob_clean();
        }
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit;
    }
}

// Processar remoção de aluno da turma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'remover_aluno') {
    $alunoId = isset($_POST['aluno_id']) ? (int)$_POST['aluno_id'] : 0;

    if ($alunoId) {
        $resultado = $turmaManager->removerAluno($turmaId, $alunoId);
        
        // Detectar se é requisição AJAX (múltiplas formas)
        $isAjax = (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) || (
            !empty($_SERVER['HTTP_ACCEPT']) && 
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
        ) || (
            isset($_POST['ajax']) && $_POST['ajax'] === 'true'
        );
        
        if ($isAjax) {
            // Limpar qualquer output anterior
            if (ob_get_level()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($resultado);
            exit;
        } else {
            // Se não for AJAX, mostrar mensagem na página
            if ($resultado['sucesso']) {
                echo '<div class="alert alert-success">' . $resultado['mensagem'] . '</div>';
            } else {
                echo '<div class="alert alert-danger">' . $resultado['mensagem'] . '</div>';
            }
            return;
        }
    } else {
        $resultado = ['sucesso' => false, 'mensagem' => '❌ ID do aluno inválido.'];
        
        $isAjax = (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        );
        
        if ($isAjax) {
            if (ob_get_level()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($resultado);
            exit;
        }
    }
}

// Obter dados da turma
$resultadoTurma = $turmaManager->obterTurma($turmaId);
if (!$resultadoTurma['sucesso']) {
    echo '<div class="alert alert-danger">Erro ao carregar turma: ' . $resultadoTurma['mensagem'] . '</div>';
    return;
}

$turma = $resultadoTurma['dados'];

// Obter dados do usuário atual
$user = getCurrentUser();

// Obter tipos de curso disponíveis
$tiposCurso = [
    'formacao_45h' => 'Curso de Formação de Condutores - Permissão 45h',
    'formacao_acc_20h' => 'Curso de Formação de Condutores - ACC 20h',
    'reciclagem_infrator' => 'Curso de Reciclagem para Condutor Infrator',
    'atualizacao' => 'Curso de Atualização'
];

// Obter salas cadastradas usando o mesmo método da página de criação
$salasCadastradas = $turmaManager->obterSalasDisponiveis($user['cfc_id'] ?? 1);

// Função para obter nome da sala pelo ID
function obterNomeSala($salaId, $salasCadastradas) {
    foreach ($salasCadastradas as $sala) {
        if ($sala['id'] == $salaId) {
            return $sala['nome'];
        }
    }
    return 'Sala não encontrada';
}

// Gerar horários disponíveis (05:00 às 23:10, intervalos de 50min)
// Expandido para permitir aulas fora do horário comercial padrão
$horariosDisponiveis = [
    '05:00', '05:50', '06:00', '06:50', // Horários muito cedo
    '07:00', '07:50', '08:40', '09:30', '10:20', '11:10',
    '12:00', '12:50', // Horário do almoço (adicionado)
    '13:00', '13:50', '14:40', '15:30', '16:20', '17:10', '18:00',
    '18:50', '19:40', '20:30', '21:10', '22:00', '22:50', '23:10' // Horários noturnos
];

// Obter disciplinas do curso para agendamento
$disciplinasCurso = $turmaManager->obterDisciplinasParaAgendamento($turmaId);

// Obter instrutores disponíveis para agendamento (após ter $turma definido)
try {
    // CORREÇÃO (12/12/2025): Usar CFC da turma, não fallback para CFC 1 (inexistente)
    // Se turma não tiver CFC definido, usar CFC do usuário (só se existir e estiver ativo)
    $cfcId = null;
    
    if (!empty($turma['cfc_id'])) {
        // Validar se o CFC da turma existe e está ativo
        $cfcTurma = $db->fetch("SELECT id, ativo FROM cfcs WHERE id = ?", [$turma['cfc_id']]);
        if ($cfcTurma && $cfcTurma['ativo']) {
            $cfcId = $turma['cfc_id'];
        }
    }
    
    // Se não encontrou CFC válido da turma, tentar usar CFC do usuário
    if (!$cfcId && !empty($user['cfc_id'])) {
        $cfcUsuario = $db->fetch("SELECT id, ativo FROM cfcs WHERE id = ?", [$user['cfc_id']]);
        if ($cfcUsuario && $cfcUsuario['ativo']) {
            $cfcId = $user['cfc_id'];
        }
    }
    
    // Se ainda não encontrou, buscar primeiro CFC ativo
    if (!$cfcId) {
        $primeiroCfcAtivo = $db->fetch("SELECT id FROM cfcs WHERE ativo = 1 ORDER BY id LIMIT 1");
        if ($primeiroCfcAtivo) {
            $cfcId = $primeiroCfcAtivo['id'];
            error_log("[Turmas Teoricas Detalhes] CFC não encontrado, usando primeiro CFC ativo: {$cfcId}");
        }
    }
    
    // Se ainda não tem CFC, não buscar instrutores (vai dar erro claro)
    if (!$cfcId) {
        error_log("[Turmas Teoricas Detalhes] ERRO: Nenhum CFC válido encontrado para buscar instrutores");
        $instrutores = [];
    } else {
        // CORREÇÃO (12/12/2025): Query robusta para lidar com diferentes tipos de campo ativo
        // Suporta: TINYINT(1) (0/1), BOOLEAN (TRUE/FALSE), e valores NULL
        $instrutores = $db->fetchAll("
            SELECT i.id, 
                   COALESCE(i.nome, u.nome, 'Instrutor sem nome') as nome,
                   i.categoria_habilitacao 
            FROM instrutores i 
            LEFT JOIN usuarios u ON i.usuario_id = u.id 
            WHERE (i.ativo = 1 OR i.ativo = TRUE OR (i.ativo IS NOT NULL AND i.ativo != 0))
              AND i.cfc_id = ?
            ORDER BY COALESCE(u.nome, i.nome, '') ASC
        ", [$cfcId]);
    }
    
    // CORREÇÃO (12/12/2025): Removidos fallbacks que buscam instrutores de outros CFCs
    // Instrutores devem ser do mesmo CFC da turma. Se não houver instrutores ativos no CFC,
    // a lista ficará vazia e o usuário será informado.
    
    // Log para debug
    if (!$cfcId) {
        error_log("[Turmas Teoricas Detalhes] Nenhum CFC válido encontrado - não foi possível buscar instrutores");
    } else if (empty($instrutores)) {
        error_log("[Turmas Teoricas Detalhes] Nenhum instrutor ativo encontrado no CFC {$cfcId}. Verifique se há instrutores cadastrados neste CFC.");
    } else {
        error_log("[Turmas Teoricas Detalhes] Instrutores encontrados: " . count($instrutores) . " para CFC ID: {$cfcId}");
    }
} catch (Exception $e) {
    error_log("Erro ao buscar instrutores: " . $e->getMessage());
    $instrutores = [];
}

// Obter progresso das disciplinas
$progressoDisciplinas = $turmaManager->obterProgressoDisciplinas($turmaId);

// Obter aulas agendadas
try {
    $aulasAgendadas = $db->fetchAll(
        "SELECT * FROM turma_aulas_agendadas WHERE turma_id = ? ORDER BY data_aula, hora_inicio",
        [$turmaId]
    );
} catch (Exception $e) {
    $aulasAgendadas = [];
}

// Calcular estatísticas (para seção de detalhes, não calendário)
$totalAulasDetalhes = count($aulasAgendadas);
$totalMinutosAgendados = array_sum(array_column($aulasAgendadas, 'duracao_minutos'));

// Calcular carga horária total do curso baseado nas disciplinas obrigatórias
try {
    $cargaHorariaTotalCurso = $db->fetch(
        "SELECT SUM(aulas_obrigatorias * 50) as total_minutos 
         FROM disciplinas_configuracao 
         WHERE curso_tipo = ? AND ativa = 1",
        [$turma['curso_tipo']]
    );
    $totalMinutosCurso = (int)($cargaHorariaTotalCurso['total_minutos'] ?? 0);
} catch (Exception $e) {
    $totalMinutosCurso = 0;
}

// Obter alunos matriculados (se a tabela existir)
try {
    $totalAlunos = (int)$db->fetchColumn(
        "SELECT COUNT(*) FROM turma_matriculas WHERE turma_id = ? AND status IN ('matriculado', 'cursando')",
        [$turmaId]
    );
} catch (Exception $e) {
    $totalAlunos = 0;
}

// Obter disciplinas selecionadas
$disciplinasSelecionadas = $turmaManager->obterDisciplinasSelecionadas($turmaId);
$disciplinasPorId = [];
foreach ($disciplinasSelecionadas as $disciplinaSelecionada) {
    $disciplinaIdTemp = $disciplinaSelecionada['disciplina_id'] ?? null;
    if ($disciplinaIdTemp) {
        $disciplinasPorId[$disciplinaIdTemp] = $disciplinaSelecionada['nome_disciplina']
            ?? $disciplinaSelecionada['nome_original']
            ?? 'Disciplina sem nome';
    }
}

// Obter alunos matriculados na turma
// Incluir helper de categoria CNH
require_once __DIR__ . '/../includes/helpers_cnh.php';

try {
    $alunosMatriculados = $db->fetchAll("
        SELECT 
            tm.id AS matricula_id,
            tm.aluno_id,
            tm.status,
            tm.data_matricula,
            tm.observacoes,
            tm.frequencia_percentual,
            a.nome,
            a.cpf,
            a.categoria_cnh,
            a.telefone,
            a.email,
            c.nome as cfc_nome,
            -- Incluir categoria da matrícula ativa (prioridade 1)
            m_ativa.categoria_cnh as categoria_cnh_matricula,
            m_ativa.tipo_servico as tipo_servico_matricula
        FROM turma_matriculas tm
        JOIN alunos a ON tm.aluno_id = a.id
        JOIN cfcs c ON a.cfc_id = c.id
        LEFT JOIN (
            SELECT aluno_id, categoria_cnh, tipo_servico
            FROM matriculas
            WHERE status = 'ativa'
        ) m_ativa ON a.id = m_ativa.aluno_id
        WHERE tm.turma_id = ? 
          AND tm.status IN ('matriculado', 'cursando', 'concluido', 'evadido', 'transferido')
        ORDER BY tm.data_matricula DESC, a.nome
    ", [$turmaId]);
} catch (Exception $e) {
    $alunosMatriculados = [];
}

// Debug: Verificar disciplinas obtidas
echo "<!-- DEBUG: Total de disciplinas obtidas: " . count($disciplinasSelecionadas) . " -->";
echo "<!-- DEBUG: Turma ID: " . $turmaId . " -->";
echo "<!-- DEBUG: Curso tipo: " . ($turma['curso_tipo'] ?? 'N/A') . " -->";
foreach ($disciplinasSelecionadas as $index => $disciplina) {
    echo "<!-- DEBUG: Disciplina $index: " . var_export($disciplina, true) . " -->";
    echo "<!-- DEBUG: Disciplina $index ID: " . var_export($disciplina['disciplina_id'] ?? 'N/A', true) . " -->";
    echo "<!-- DEBUG: Disciplina $index Nome: " . var_export($disciplina['nome_disciplina'] ?? 'N/A', true) . " -->";
}

// Obter estatísticas de aulas para cada disciplina
$estatisticasDisciplinas = [];
$historicoAgendamentos = [];

foreach ($disciplinasSelecionadas as $disciplina) {
    $disciplinaId = $disciplina['disciplina_id'];
    
    // Buscar aulas agendadas para esta disciplina (excluindo canceladas)
    $aulasAgendadas = $db->fetch(
        "SELECT COUNT(*) as total FROM turma_aulas_agendadas WHERE turma_id = ? AND disciplina = ? AND status != 'cancelada'",
        [$turmaId, $disciplinaId]
    );
    
    // Buscar aulas realizadas (status = 'realizada')
    $aulasRealizadas = $db->fetch(
        "SELECT COUNT(*) as total FROM turma_aulas_agendadas WHERE turma_id = ? AND disciplina = ? AND status = 'realizada'",
        [$turmaId, $disciplinaId]
    );
    
    // Buscar histórico completo de agendamentos para esta disciplina
    // Ordenar por ordem_disciplina para garantir ordem correta e excluir canceladas do histórico
    $agendamentosDisciplina = $db->fetchAll(
        "SELECT 
            taa.*,
            COALESCE(u.nome, i.nome, 'Não informado') as instrutor_nome,
            COALESCE(s.nome, 'Não informada') as sala_nome
         FROM turma_aulas_agendadas taa
         LEFT JOIN instrutores i ON taa.instrutor_id = i.id
         LEFT JOIN usuarios u ON i.usuario_id = u.id
         LEFT JOIN salas s ON taa.sala_id = s.id
         WHERE taa.turma_id = ? 
         AND taa.disciplina = ? 
         AND taa.status != 'cancelada'
         ORDER BY taa.ordem_disciplina ASC, taa.data_aula ASC, taa.hora_inicio ASC",
        [$turmaId, $disciplinaId]
    );
    
    $totalAgendadas = $aulasAgendadas['total'] ?? 0;
    $totalRealizadas = $aulasRealizadas['total'] ?? 0;
    $totalObrigatorias = $disciplina['carga_horaria_padrao'] ?? 0;
    $totalFaltantes = max(0, $totalObrigatorias - $totalAgendadas);
    
    $estatisticasDisciplinas[$disciplinaId] = [
        'agendadas' => $totalAgendadas,
        'realizadas' => $totalRealizadas,
        'faltantes' => $totalFaltantes,
        'obrigatorias' => $totalObrigatorias
    ];
    
    $historicoAgendamentos[$disciplinaId] = $agendamentosDisciplina;
}

// Paleta base de cores por disciplina (utilizada em múltiplos componentes)
$paletaCoresDisciplinas = [
    'legislacao_transito' => [
        'base' => '#fbbc04',
        'fundo' => '#fef7e0',
    ],
    'direcao_defensiva' => [
        'base' => '#34a853',
        'fundo' => '#e6f4ea',
    ],
    'primeiros_socorros' => [
        'base' => '#ea4335',
        'fundo' => '#fce8e6',
    ],
    'meio_ambiente_cidadania' => [
        'base' => '#4285f4',
        'fundo' => '#e8f0fe',
    ],
    'mecanica_basica' => [
        'base' => '#9c27b0',
        'fundo' => '#f3e5f5',
    ],
];

function obterCorDisciplina(string $disciplinaSlug, array $paleta, string $tipo = 'base'): string
{
    $slugNormalizado = strtolower($disciplinaSlug);
    if (isset($paleta[$slugNormalizado][$tipo])) {
        return $paleta[$slugNormalizado][$tipo];
    }

    // Fallback suave (azul neutro)
    return $tipo === 'fundo' ? '#e8f1fd' : '#2563eb';
}

/* Helpers de badge e metadados */
function formatarPeriodoTurma(array $turma): array {
    $inicio = isset($turma['data_inicio']) && $turma['data_inicio'] ? date('d/m/Y', strtotime($turma['data_inicio'])) : null;
    $fim = isset($turma['data_fim']) && $turma['data_fim'] ? date('d/m/Y', strtotime($turma['data_fim'])) : null;
    return [$inicio, $fim];
}

function obterModalidadeTexto(?string $modalidade): string {
    if (!$modalidade) {
        return 'Modalidade não definida';
    }
    return $modalidade === 'online' ? 'Online' : ($modalidade === 'hibrida' ? 'Híbrida' : 'Presencial');
}

function obterStatusBadgeTexto(string $status): string {
    $statusLabels = [
        'criando' => 'Agendando',
        'agendando' => 'Agendando',
        'completa' => 'Agendado',
        'ativa' => 'Em andamento',
        'finalizada' => 'Concluída'
    ];
    return $statusLabels[$status] ?? ucfirst($status);
}

function formatarDiaSemanaCurto(DateTimeImmutable $data): string
{
    static $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    return $dias[(int) $data->format('w')] ?? $data->format('D');
}

function formatarMesCurto(DateTimeImmutable $data): string
{
    static $meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    return $meses[(int) $data->format('n') - 1] ?? $data->format('M');
}

function formatarCabecalhoProximas(DateTimeImmutable $data): string
{
    return sprintf('%s, %02d %s', formatarDiaSemanaCurto($data), $data->format('d'), formatarMesCurto($data));
}

function formatarStatusAulaProxima(string $status): array
{
    $status = strtolower(trim($status));
    $mapeamento = [
        'confirmada' => ['label' => 'Confirmada', 'classe' => 'confirmada'],
        'reagendada' => ['label' => 'Reagendada', 'classe' => 'reagendada'],
        'cancelada' => ['label' => 'Cancelada', 'classe' => 'cancelada'],
        'pendente' => ['label' => 'Pendente', 'classe' => 'pendente'],
        'aguardando' => ['label' => 'Aguardando', 'classe' => 'pendente'],
        'agendada' => ['label' => 'Agendada', 'classe' => 'agendada'],
    ];

    if (isset($mapeamento[$status])) {
        return $mapeamento[$status];
    }

    return ['label' => ucfirst($status ?: 'Agendada'), 'classe' => 'agendada'];
}

function criarBadgeTempo(DateTimeImmutable $agora, DateTimeImmutable $inicio): ?array
{
    if ($inicio <= $agora) {
        return ['label' => 'agora', 'classe' => 'now'];
    }

    $intervalo = $agora->diff($inicio);

    if ($intervalo->days === 0) {
        if ($intervalo->h === 0) {
            $minutos = max(1, $intervalo->i);
            return ['label' => "em {$minutos} min", 'classe' => 'soon'];
        }
        return ['label' => "em {$intervalo->h}h", 'classe' => 'soon'];
    }

    if ($intervalo->days === 1) {
        return ['label' => 'amanhã', 'classe' => 'tomorrow'];
    }

    if ($intervalo->days < 7) {
        return ['label' => "em {$intervalo->days}d", 'classe' => 'later'];
    }

    return null;
}

function formatarDuracaoMinutos(?int $duracao): string
{
    if (!$duracao || $duracao <= 0) {
        return '50 min';
    }

    if ($duracao < 60) {
        return "{$duracao} min";
    }

    $horas = intdiv($duracao, 60);
    $minutos = $duracao % 60;
    if ($minutos === 0) {
        return $horas === 1 ? '1h' : "{$horas}h";
    }

    return sprintf('%dh %02d', $horas, $minutos);
}

function montarAriaLabelProxima(array $aula, DateTimeImmutable $inicio, ?DateTimeImmutable $fim, ?int $duracaoMin): string
{
    $partes = [];
    $partes[] = sprintf('%s às %s', $inicio->format('d/m'), $inicio->format('H:i'));
    if (!empty($aula['nome'])) {
        $partes[] = $aula['nome'];
    }
    if (!empty($aula['instrutor'])) {
        $partes[] = $aula['instrutor'];
    }
    if (!empty($aula['sala'])) {
        $partes[] = 'Sala ' . $aula['sala'];
    }
    if ($duracaoMin) {
        $partes[] = sprintf('%d minutos', $duracaoMin);
    } elseif ($fim) {
        $partes[] = sprintf('até %s', $fim->format('H:i'));
    }

    return implode(' — ', $partes);
}

?>

<style>
/* ==========================================
   ESTILOS PARA CARDS DE ESTATÍSTICAS
   ========================================== */
.disciplina-stats-card {
    position: relative;
    cursor: pointer !important;
}

.disciplina-stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
}

.disciplina-stats-card:active {
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
}

/* ==========================================
   ESTILOS PARA EDIÇÃO INLINE
   ========================================== */
.inline-edit {
    position: relative;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 6px;
    transition: all 0.2s ease;
    border: 2px solid transparent;
    display: inline-block;
    min-width: 150px;
    word-wrap: break-word;
    overflow-wrap: break-word;
    white-space: normal;
    max-width: 100%;
    box-sizing: border-box;
}

.inline-edit:hover {
    background-color: #f8f9fa;
    border-color: transparent;
}

.inline-edit.editing {
    background-color: white;
    border-color: #023A8D;
    box-shadow: 0 0 0 3px rgba(2, 58, 141, 0.1);
    padding: 8px 12px;
}

.inline-edit input, 
.inline-edit select, 
.inline-edit textarea {
    border: none;
    background: transparent;
    width: 100%;
    font-size: inherit;
    font-weight: inherit;
    color: inherit;
    padding: 0;
    margin: 0;
    min-height: auto;
    line-height: inherit;
    font-family: inherit;
}

.inline-edit input:focus, 
.inline-edit select:focus, 
.inline-edit textarea:focus {
    outline: none;
    background: white;
    padding: 8px 12px;
    border-radius: 4px;
    box-shadow: 0 0 0 2px rgba(2, 58, 141, 0.2);
    border: 1px solid #023A8D;
    min-width: 200px;
    max-width: 100%;
    position: relative;
    z-index: 1060;
}

.edit-icon {
    position: absolute;
    top: 4px;
    right: 4px;
    opacity: 0.6;
    transition: opacity 0.3s ease;
    color: #023A8D;
    font-size: 12px;
    cursor: pointer;
    z-index: 1060;
}

.inline-edit:hover .edit-icon {
    opacity: 1;
    transform: scale(1.1);
}

.masthead .inline-edit .edit-icon {
    opacity: 0;
    visibility: hidden;
    transform: scale(1);
    transition: opacity 0.2s ease, visibility 0.2s ease, transform 0.2s ease;
}

.masthead .inline-edit:hover .edit-icon,
.masthead .inline-edit:focus-within .edit-icon,
.masthead .inline-edit.show-icon .edit-icon {
    opacity: 1;
    visibility: visible;
    transform: scale(1.1);
}

#edit-scope-basicas .edit-icon {
    opacity: 0 !important;
    visibility: hidden !important;
    transition: opacity 0.18s ease, visibility 0.18s ease;
    color: #023A8D;
}

#edit-scope-basicas .inline-edit:hover .edit-icon,
#edit-scope-basicas .inline-edit:focus-within .edit-icon,
#edit-scope-basicas .inline-edit.show-icon .edit-icon {
    opacity: 1 !important;
    visibility: visible !important;
}

/* ==========================================
   ESTILOS ESPECÍFICOS POR CAMPO
   ========================================== */
.inline-edit[data-field="nome"] {
    font-size: 1.5rem;
    font-weight: bold;
    color: #023A8D;
    min-width: 200px;
    max-width: 100%;
}
.inline-edit[data-field="curso_tipo"] {
    min-width: 400px !important;
    max-width: none !important;
    width: fit-content !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    white-space: nowrap !important;
    display: block !important;
    vertical-align: top !important;
    line-height: 1.4 !important;
    overflow: visible !important;
    text-overflow: unset !important;
}

.inline-edit[data-field="data_inicio"], 
.inline-edit[data-field="data_fim"] {
    font-family: monospace;
    background: #f8f9fa;
    border-radius: 4px;
    min-width: 120px;
    max-width: 100%;
    padding: 4px 8px;
    border: none;
}

.inline-edit[data-field="status"] {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
}
.inline-edit[data-field="modalidade"],
.inline-edit[data-field="sala_id"] {
    font-weight: 500;
}

.inline-edit[data-field="observacoes"] {
    font-style: italic;
    color: #666;
    min-width: 300px;
    max-width: 100%;
    width: 100%;
    word-wrap: break-word;
    overflow-wrap: break-word;
    white-space: normal;
    text-align: left !important;
    display: block !important;
    padding: 12px !important;
    margin: 0 !important;
    box-sizing: border-box;
}

/* ==========================================
   ESTILOS PARA DISCIPLINAS - REORGANIZADO
   ========================================== */

/* Títulos das seções */
.section-title {
    color: #023A8D;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}

/* Seção de disciplinas cadastradas */
.disciplinas-cadastradas-section {
    margin-bottom: 30px;
}

/* Estatísticas de Aulas - KPIs em Chips Padronizados */
.aulas-stats-container {
    display: grid;
    grid-template-columns: repeat(3, minmax(110px, 1fr));
    gap: 10px;
    margin-top: 12px;
    max-width: 100%;
}

.stat-item {
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.4;
    transition: all 0.2s ease;
}

.stat-label {
    font-weight: 500;
    letter-spacing: 0.01em;
    font-size: 11px;
}

.stat-value {
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 10px;
    min-width: 24px;
    text-align: center;
    background: rgba(255, 255, 255, 0.5);
    font-size: 13px;
}

/* KPI Agendadas - Neutro/Azul */
.stat-agendadas {
    background: #e3f2fd;
    color: #1565c0;
}

.stat-agendadas .stat-value {
    background: rgba(255, 255, 255, 0.5);
    color: #0d47a1;
}

/* KPI Realizadas - Verde (Contraste AA: 4.5:1) */
.stat-realizadas {
    background: #c8e6c9;
    color: #1b5e20;
}

.stat-realizadas .stat-value {
    background: rgba(255, 255, 255, 0.6);
    color: #194d19;
}

/* KPI Faltantes - Âmbar (Contraste AA: 4.5:1) */
.stat-faltantes {
    background: #ffe0b2;
    color: #c35c00;
}

.stat-faltantes .stat-value {
    background: rgba(255, 255, 255, 0.6);
    color: #b34900;
}

/* Estilos para botões de ação na tabela */
.btn-group .btn {
    min-width: 32px !important;
    min-height: 32px !important;
    padding: 6px 8px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    visibility: visible !important;
    opacity: 1 !important;
    border-width: 1px !important;
    font-size: 12px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
    transition: all 0.2s ease !important;
    position: relative !important;
    z-index: 10 !important;
}

/* Sobrescrever qualquer CSS conflitante para ícones */
.btn-group .btn i,
.btn-group .btn i.fas,
.btn-group .btn i.fa-edit,
.btn-group .btn i.fa-times {
    font-family: "Font Awesome 6 Free" !important;
    font-weight: 900 !important;
    font-style: normal !important;
    font-variant: normal !important;
    text-rendering: auto !important;
    line-height: 1 !important;
    -webkit-font-smoothing: antialiased !important;
    -moz-osx-font-smoothing: grayscale !important;
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative !important;
    z-index: 11 !important;
}
/* Tamanhos específicos para ícones */
.btn-group .btn i.fa-edit {
    font-size: 14px !important;
}
.btn-group .btn i.fa-times {
    font-size: 12px !important;
}
.btn-group .btn:hover {
    transform: scale(1.05) !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
}
/* Estilos específicos para garantir visibilidade dos ícones */
.btn-group .btn-outline-primary {
    color: #0d6efd !important;
    border-color: #0d6efd !important;
    background-color: #ffffff !important;
    border-width: 2px !important;
    font-weight: bold !important;
    min-width: 32px !important;
    min-height: 32px !important;
    padding: 6px 8px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative !important;
    z-index: 10 !important;
}
.btn-group .btn-outline-primary:hover {
    color: #ffffff !important;
    background-color: #0d6efd !important;
    border-color: #0d6efd !important;
}
.btn-group .btn-outline-primary i.fas,
.btn-group .btn-outline-primary i.fa-edit {
    color: #0d6efd !important;
    font-size: 14px !important;
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
    font-weight: 900 !important;
    font-family: "Font Awesome 6 Free" !important;
    line-height: 1 !important;
    text-rendering: auto !important;
    -webkit-font-smoothing: antialiased !important;
    -moz-osx-font-smoothing: grayscale !important;
    position: relative !important;
    z-index: 11 !important;
}

.btn-group .btn-outline-primary:hover i,
.btn-group .btn-outline-primary:hover i.fas,
.btn-group .btn-outline-primary:hover i.fa-edit {
    color: #ffffff !important;
}

.btn-group .btn-outline-danger {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
    background-color: #fff5f5 !important;
    border-width: 1px !important;
}

.btn-group .btn-outline-danger:hover {
    color: #fff !important;
    background-color: #dc3545 !important;
    border-color: #dc3545 !important;
}

.btn-group .btn-outline-danger i {
    color: #dc3545 !important;
    font-size: 12px !important;
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.btn-group .btn-outline-danger:hover i {
    color: #fff !important;
}

/* Responsividade para estatísticas */
@media (max-width: 768px) {
    .aulas-stats-container {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        margin-top: 10px;
    }
    
    .stat-item:last-child {
        grid-column: 1 / -1;
        max-width: 50%;
    }
    
    .stat-item {
        font-size: 10px;
        padding: 5px 10px;
    }
    
    .stat-label {
        font-size: 10px;
    }
    
    .stat-value {
        padding: 2px 6px;
        min-width: 22px;
        font-size: 12px;
    }
    
    .disciplina-header-clickable {
        position: relative;
    }
    
    .disciplina-info-display {
        display: block;
        padding-right: 70px;
    }
    
    .disciplina-nome-display {
        margin-bottom: 10px;
    }
    
    .disciplina-nome-display h6 {
        flex-wrap: wrap;
        padding-right: 0;
    }
    
    .disciplina-actions-menu {
        position: absolute;
        top: 18px;
        right: 46px;
        z-index: 5;
    }
    
    .disciplina-chevron {
        position: absolute;
        top: 20px;
        right: 20px;
        margin-left: 0;
        z-index: 5;
    }
    
    .btn-group .btn {
        min-width: 28px !important;
        min-height: 28px !important;
        padding: 4px 6px !important;
    }
}

@media (max-width: 576px) {
    .aulas-stats-container {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .stat-item:last-child {
        grid-column: 1 / -1;
        max-width: 100%;
    }
}

.disciplina-cadastrada-card {
    background: #ffffff;
    border: 1px solid #e9ecef;
    border-left: 4px solid #023A8D;
    border-radius: 8px;
    padding: 0;
    margin-bottom: 16px;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.disciplina-cadastrada-card:hover {
    box-shadow: 0 4px 12px rgba(2, 58, 141, 0.12);
    transform: translateY(-2px);
    border-left-width: 4px;
}

.disciplina-info-display {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    position: relative;
}

.disciplina-nome-display {
    flex: 1;
}

.disciplina-nome-display h6 {
    margin: 0;
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    gap: 10px;
    line-height: 1.3;
}

.disciplina-nome-display h6 i.fa-graduation-cap {
    color: #6c757d !important;
    font-size: 1.1rem;
}

.disciplina-actions-menu {
    position: relative;
    display: inline-flex;
    flex-shrink: 0;
}

.disciplina-menu-trigger {
    background: transparent;
    border: none;
    color: #6c757d;
    font-size: 18px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: all 0.2s ease;
    line-height: 1;
}

.disciplina-menu-trigger:hover {
    background: #f8f9fa;
    color: #023A8D;
}

.disciplina-menu-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    min-width: 200px;
    z-index: 1000;
    display: none;
    margin-top: 4px;
}

.disciplina-menu-dropdown.show {
    display: block;
}

.disciplina-menu-item {
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #495057;
    font-size: 14px;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s ease;
}

.disciplina-menu-item:hover {
    background: #f8f9fa;
}

.disciplina-menu-item i {
    width: 16px;
    text-align: center;
}

.disciplina-menu-item.danger {
    color: #dc3545;
}

.disciplina-menu-item.danger:hover {
    background: #fff5f5;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #d4edda;
    color: #155724;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-top: 5px;
}

.status-badge i {
    color: #28a745;
}

.disciplina-detalhes-display {
    display: flex;
    gap: 30px;
    flex: 1;
    justify-content: center;
}

.detalhe-item {
    text-align: center;
}

.detalhe-label {
    display: block;
    font-size: 0.8rem;
    color: #6c757d;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.detalhe-valor {
    display: block;
    font-size: 1.2rem;
    font-weight: 700;
    color: #023A8D;
}

.disciplina-acoes {
    display: flex;
    gap: 10px;
}

/* Formulário de edição */
.disciplina-edit-form {
    background: #fff;
    border: 1px solid #007bff;
    border-radius: 8px;
    padding: 20px;
    margin-top: 15px;
}

.edit-form-header {
    margin-bottom: 15px;
}

.edit-form-header h6 {
    color: #007bff;
    margin: 0;
    font-weight: 600;
}

/* Seção para adicionar disciplinas */
.adicionar-disciplina-section {
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
}

.nova-disciplina-form {
    background: white;
    border-radius: 6px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Responsividade */
@media (max-width: 768px) {
    .disciplina-info-display {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .disciplina-detalhes-display {
        flex-direction: column;
        gap: 15px;
        width: 100%;
    }
    
    .disciplina-acoes {
        width: 100%;
        justify-content: flex-end;
    }
}

/* Card principal da disciplina */
.disciplina-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    overflow: hidden;
}

.disciplina-card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
}

/* Cabeçalho da disciplina */
.disciplina-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 16px 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.disciplina-title {
    flex: 1;
}

.disciplina-nome {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 4px;
}

.disciplina-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #d4edda;
    color: #155724;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.disciplina-status i {
    color: #28a745;
}


/* Conteúdo da disciplina */
.disciplina-content {
    padding: 20px;
}

/* Inline layout for details + action on wider screens */
@media (min-width: 576px) {
    .disciplina-content {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .disciplina-details {
        flex: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 0;
    }
    .disciplina-actions {
        margin-left: auto;
    }
}

/* Mobile: stack items and make action button full width */
@media (max-width: 575.98px) {
    .disciplina-actions {
        margin-top: 12px;
        width: 100%;
    }
    .disciplina-actions .btn-edit-disciplina {
        width: 100%;
    }
}
.disciplina-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}
/* Layout otimizado para desktop - detalhes da disciplina */
@media (min-width: 768px) {
    .disciplina-details {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 40px;
        margin-bottom: 20px;
    }
    
    .detail-item {
        flex: 1;
        text-align: center;
        padding: 16px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }
    
    .detail-item label {
        font-size: 0.8rem;
        color: #6c757d;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        display: block;
    }
    
    .horas-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #023A8D;
    }
    
    .aulas-count {
        font-size: 1.2rem;
        color: #495057;
        font-weight: 600;
    }
}

/* Estilos base para detail-item (mobile) */
.detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-item label {
    font-size: 0.85rem;
    color: #6c757d;
    font-weight: 500;
    margin: 0;
}

.carga-horaria-display {
    display: flex;
    align-items: baseline;
    gap: 4px;
}

.horas-value {
    font-size: 1.2rem;
    font-weight: 600;
    color: #023A8D;
}

.horas-label {
    font-size: 0.9rem;
    color: #6c757d;
}

.aulas-count {
    font-size: 1rem;
    color: #495057;
    font-weight: 500;
}

/* Campos de edição - Layout otimizado para desktop */
.disciplina-edit-fields {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.edit-row {
    margin-bottom: 0;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-size: 0.85rem;
    color: #495057;
    font-weight: 600;
    margin: 0;
}

/* Layout em linha para desktop */
@media (min-width: 768px) {
    .disciplina-edit-fields {
        display: grid;
        grid-template-columns: 1fr 200px;
        gap: 20px;
        align-items: end;
    }
    
    .edit-row {
        margin-bottom: 0;
    }
    
    .form-group {
        margin-bottom: 0;
    }
    
    /* Primeira coluna - Disciplina */
    .edit-row:first-child {
        grid-column: 1;
    }
    
    /* Segunda coluna - Carga horária */
    .edit-row:last-child {
        grid-column: 2;
    }
    
    /* Campo de horas compacto */
    .input-group {
        width: 100%;
    }
    
    .disciplina-horas {
        width: 100%;
        text-align: center;
        font-weight: 600;
        font-size: 1rem;
    }
    
    .input-group-text {
        background: #023A8D;
        color: white;
        border: 1px solid #023A8D;
        font-weight: 600;
        padding: 0.5rem 0.75rem;
        min-width: 45px;
    }
    
    /* Select de disciplina melhorado */
    .form-select {
        border: 2px solid #023A8D;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 0.95rem;
        font-weight: 500;
        background-color: white;
        transition: all 0.2s ease;
    }
    
    .form-select:focus {
        border-color: #1a4ba8;
        box-shadow: 0 0 0 0.2rem rgba(2, 58, 141, 0.25);
        outline: none;
    }
    
    .form-select:hover {
        border-color: #1a4ba8;
    }
}

/* Layout para dispositivos móveis */
@media (max-width: 767px) {
    .disciplina-edit-fields {
        padding: 16px;
    }
    
    .edit-row {
        margin-bottom: 16px;
    }
    
    .edit-row:last-child {
        margin-bottom: 0;
    }
    
    .form-select {
        border: 2px solid #023A8D;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 0.95rem;
        font-weight: 500;
    }
    
    .disciplina-horas {
        width: 100%;
        text-align: center;
        font-weight: 600;
        font-size: 1rem;
    }
    
    .input-group-text {
        background: #023A8D;
        color: white;
        border: 1px solid #023A8D;
        font-weight: 600;
        padding: 0.5rem 0.75rem;
        min-width: 45px;
    }
}

/* Botões de ação */
.disciplina-actions {
    padding: 16px 20px;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
}

.btn-edit-disciplina {
    background: #023A8D;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-edit-disciplina:hover {
    background: #1a4ba8;
    transform: translateY(-1px);
    box-shadow: none;
}

.btn-edit-disciplina.btn-save-mode {
    background: linear-gradient(135deg, #28a745, #20c997);
    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
}

.btn-edit-disciplina.btn-save-mode:hover {
    background: linear-gradient(135deg, #218838, #1e7e34);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
}

/* Estilos para o campo vazio (quando não há disciplinas) */
.disciplina-item {
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    color: #6c757d;
}

.disciplina-row-layout {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 10px;
}

.disciplina-field-container {
    flex-grow: 1;
}

.disciplina-field-container .form-select {
    border: 1px solid #ced4da;
    border-radius: 6px;
    padding: 10px 12px;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    background-color: white;
}

.disciplina-field-container .form-select:focus {
    border-color: #023A8D;
    box-shadow: 0 0 0 0.2rem rgba(2, 58, 141, 0.25);
    outline: none;
}

.disciplina-horas {
    width: 80px;
    text-align: center;
}

.disciplina-info {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 8px;
    font-style: italic;
}
/* ==========================================
   ESTILOS PARA CARDS DE ESTATÍSTICAS
   ========================================== */
.estatisticas-wrapper {
    margin-top: 18px;
}

#tab-estatisticas .estatisticas-container {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 20px;
    align-items: stretch;
}

#tab-estatisticas .stat-card {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 8px;
    padding: 14px 18px;
    min-height: 92px;
    background: #ffffff;
    border: 1px solid #E1E6EE;
    border-radius: 12px;
    box-shadow: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    position: relative;
}

#tab-estatisticas .stat-card::before {
    display: none !important;
}

#tab-estatisticas .stat-card:hover,
#tab-estatisticas .stat-card:focus-within {
    border-color: #023A8D33;
    box-shadow: 0 2px 6px rgba(2, 58, 141, 0.08);
}

#tab-estatisticas .stat-card:focus-within {
    outline: none;
}

#tab-estatisticas .stat-header {
    display: flex;
    align-items: center;
    gap: 10px;
}

#tab-estatisticas .stat-icon {
    font-size: 0;
    line-height: 0;
}

#tab-estatisticas .stat-icon i {
    font-size: 24px;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 999px;
    background: var(--brand-600, #023A8D);
    box-shadow: 0 0 0 1px rgba(2, 58, 141, 0.2), 0 1px 2px rgba(2, 58, 141, 0.15);
    transition: box-shadow 0.2s ease, background 0.2s ease;
}

#tab-estatisticas .stat-number {
    font-size: 26px;
    font-weight: 700;
    color: #101828;
    line-height: 1.1;
}

#tab-estatisticas .stat-label {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #475467;
    line-height: 1.2;
}

#tab-estatisticas .estatisticas-progress {
    margin: 0;
    padding: 12px 20px 16px;
    background: #ffffff;
    border: 1px solid #E1E6EE;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

#tab-estatisticas .estatisticas-progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

#tab-estatisticas .estatisticas-progress-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #023A8D;
    display: flex;
    align-items: center;
    gap: 8px;
}

#tab-estatisticas .estatisticas-progress-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

#tab-estatisticas .progress-badge {
    background: rgba(2, 58, 141, 0.12);
    color: #023A8D;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    line-height: 1.2;
}

#tab-estatisticas .progress-chip {
    background: #F1F3F9;
    color: #475467;
    border-radius: 999px;
    padding: 3px 10px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    height: 24px;
    line-height: 1;
}

#tab-estatisticas .progress-bar {
    width: 100%;
    height: 6px;
    border-radius: 4px;
    background: #E5E9F3;
    overflow: hidden;
}

#tab-estatisticas .progress-bar-fill {
    height: 100%;
    background: var(--brand-600, #023A8D);
    border-radius: 4px;
    transition: width 0.3s ease;
}

@media (max-width: 1024px) {
    #tab-estatisticas .estatisticas-container {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    #tab-estatisticas .estatisticas-container {
        grid-template-columns: 1fr;
    }
    
    #tab-estatisticas .stat-card {
        min-height: 88px;
    }
}

/* ==========================================
   ESTILOS PARA STATUS E BADGES
   ========================================== */
/* Status e Badges - Organizados */
.status-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.3s ease;
    letter-spacing: 0.5px;
}

.status-badge:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* Cores dos status */
.status-criando { 
    background: #fff3cd; 
    color: #856404; 
    border: 1px solid #ffeaa7;
}

.status-agendando { 
    background: #cce5ff; 
    color: #004085; 
    border: 1px solid #74c0fc;
}

.status-completa { 
    background: #d4edda; 
    color: #155724; 
    border: 1px solid #c3e6cb;
}

.status-ativa { 
    background: #d1ecf1; 
    color: #0c5460; 
    border: 1px solid #bee5eb;
}

.status-concluida { 
    background: #e2e3e5; 
    color: #383d41; 
    border: 1px solid #ced4da;
}

/* ==========================================
   ESTILOS PARA BOTÕES E AÇÕES
   ========================================== */
/* Botões - Estilos organizados */
.add-disciplina-btn {
    background: linear-gradient(135deg, #023A8D, #1a4ba8);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    margin-top: 15px;
    transition: all 0.3s ease;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(2, 58, 141, 0.2);
}

.add-disciplina-btn:hover {
    background: linear-gradient(135deg, #1a4ba8, #023A8D);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(2, 58, 141, 0.3);
}

.disciplina-edit-item {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    position: relative;
    z-index: 1060;
}

.disciplina-edit-item:hover {
    border-color: #023A8D;
    box-shadow: 0 2px 8px rgba(2, 58, 141, 0.1);
}

.disciplina-edit-item.editing {
    border-color: #28a745 !important;
    box-shadow: 0 4px 12px rgba(40,167,69,0.2);
    background: #f8fff9;
}

/* ==========================================
   ESTILOS PARA EDIÇÃO DE CARGA HORÁRIA
   ========================================== */
.inline-edit-carga {
    cursor: pointer;
    padding: 2px 4px;
    border-radius: 3px;
    transition: all 0.2s ease;
}
.inline-edit-carga:hover {
    background: #e3f2fd;
    color: #1976d2;
}

.inline-edit-carga.editing {
    background: #e8f5e8;
    border: 1px solid #28a745;
    color: #155724;
}
/* ==========================================
   ESTILOS PARA SANFONA DE DISCIPLINAS
   ========================================== */

/* Card de disciplina com sanfona */
.disciplina-accordion {
    transition: all 0.3s ease;
    border-left: 4px solid #023A8D;
}

.disciplina-accordion:hover {
    box-shadow: 0 4px 16px rgba(2, 58, 141, 0.15);
    transform: translateY(-2px);
}

/* Cabeçalho clicável */
.disciplina-header-clickable {
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 18px 20px;
    border-radius: 8px 8px 0 0;
}

.disciplina-header-clickable:hover {
    background: #fafbfc;
}

.disciplina-header-clickable:active {
    background: #f1f3f5;
}

.disciplina-header-clickable:focus {
    outline: 2px solid #023A8D;
    outline-offset: -2px;
}

/* Chevron animado */
.disciplina-chevron {
    transition: transform 0.3s ease;
    color: #6c757d;
    font-size: 0.9rem;
    margin-left: auto;
    flex-shrink: 0;
}

.disciplina-accordion.expanded .disciplina-chevron {
    transform: rotate(180deg);
}

/* Conteúdo da sanfona */
.disciplina-detalhes-content {
    border-top: 1px solid #e9ecef;
    background: #fafbfc;
    border-radius: 0 0 8px 8px;
    overflow: hidden;
    animation: slideDown 0.3s ease-out;
}

.disciplina-detalhes-data {
    padding: 20px;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 3000px;
    }
}

/* Painel superior do accordion expandido */
.disciplina-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}

.disciplina-panel-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #023A8D;
    font-size: 1.05rem;
    font-weight: 600;
    margin: 0;
}

.disciplina-panel-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.disciplina-quick-filters {
    display: flex;
    gap: 8px;
    align-items: center;
}

.quick-filter-select {
    padding: 6px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 13px;
    background: white;
    color: #495057;
    cursor: pointer;
    transition: all 0.2s ease;
}

.quick-filter-select:hover {
    border-color: #023A8D;
}

.quick-filter-select:focus {
    outline: none;
    border-color: #023A8D;
    box-shadow: 0 0 0 3px rgba(2, 58, 141, 0.1);
}

/* Loading spinner */
.disciplina-loading {
    padding: 20px;
    text-align: center;
}

.spinner-border {
    width: 2rem;
    height: 2rem;
    border-width: 0.2em;
}

/* Tabela de aulas - Densidade Reduzida */
.aulas-table,
.table-responsive table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    font-size: 13px;
}

.aulas-table th,
.table-responsive table th,
.table-primary th {
    background: #023A8D;
    color: white;
    padding: 10px 12px;
    font-weight: 600;
    font-size: 12px;
    text-align: left;
    border: none;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.aulas-table td,
.table-responsive table td {
    padding: 8px 12px;
    border-bottom: 1px solid #f1f3f5;
    font-size: 13px;
    text-align: left;
    vertical-align: middle;
    line-height: 1.4;
}

.aulas-table tbody tr,
.table-responsive table tbody tr {
    transition: background 0.15s ease;
}

.aulas-table tbody tr:hover,
.table-responsive table tbody tr:hover {
    background: #fafbfc;
}

.aulas-table tbody tr:last-child td,
.table-responsive table tbody tr:last-child td {
    border-bottom: none;
}

/* Status badges na tabela - Chips Semânticos */
.status-badge-table,
.badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    line-height: 1.3;
}

/* Status: AGENDADA - Neutro */
.status-agendada,
.badge.bg-warning {
    background: #f8f9fa !important;
    color: #495057 !important;
    border: 1px solid #dee2e6;
}

/* Status: REALIZADA - Verde */
.status-realizada,
.badge.bg-success {
    background: #d1f4e0 !important;
    color: #1e7d3c !important;
    border: 1px solid #9ae6b4;
}

/* Status: CANCELADA - Vermelho */
.status-cancelada,
.badge.bg-danger {
    background: #fee !important;
    color: #c41e3a !important;
    border: 1px solid #fca5a5;
}

/* Status: REAGENDADA - Azul */
.status-reagendada,
.badge.bg-info {
    background: #dbeafe !important;
    color: #1e40af !important;
    border: 1px solid #93c5fd;
}

/* Informações do instrutor */
.instrutor-info {
    text-align: left;
    font-size: 0.85rem;
}

.instrutor-nome {
    font-weight: 600;
    color: #023A8D;
    margin-bottom: 2px;
}

.instrutor-contato {
    color: #6c757d;
    font-size: 0.8rem;
}

/* Estatísticas da disciplina */
.disciplina-stats-summary {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.stat-card-mini {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    border-left: 4px solid #023A8D;
    transition: all 0.3s ease;
}

.stat-card-mini:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-number-mini {
    font-size: 1.5rem;
    font-weight: bold;
    color: #023A8D;
    margin-bottom: 5px;
}

.stat-label-mini {
    font-size: 0.8rem;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Progress bar */
.progress-container {
    margin-top: 15px;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: 0.9rem;
    font-weight: 600;
}

.progress-bar-custom {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #023A8D, #1a4ba8);
    border-radius: 4px;
    transition: width 0.3s ease;
}

/* ==========================================
   SKELETON LOADING STATES
   ========================================== */
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s infinite;
    border-radius: 4px;
}

@keyframes skeleton-loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.skeleton-card {
    background: white;
    border: 1px solid #e9ecef;
    border-left: 4px solid #dee2e6;
    border-radius: 8px;
    padding: 18px 20px;
    margin-bottom: 16px;
}

.skeleton-title {
    height: 20px;
    width: 40%;
    margin-bottom: 12px;
}

.skeleton-text {
    height: 14px;
    width: 100%;
    margin-bottom: 8px;
}

.skeleton-text-short {
    height: 14px;
    width: 60%;
}

.skeleton-chips {
    display: flex;
    gap: 10px;
    margin-top: 12px;
}

.skeleton-chip {
    height: 24px;
    width: 80px;
    border-radius: 16px;
}

/* ==========================================
   EMPTY STATES
   ========================================== */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state-icon {
    font-size: 3.5rem;
    color: #dee2e6;
    margin-bottom: 20px;
    opacity: 0.6;
}

.empty-state-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
}

.empty-state-description {
    font-size: 0.95rem;
    color: #6c757d;
    margin-bottom: 24px;
    line-height: 1.5;
}

.empty-state-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #023A8D;
    color: white;
    border-radius: 8px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
}

.empty-state-action:hover {
    background: #012454;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(2, 58, 141, 0.3);
}

/* ==========================================
   ACESSIBILIDADE
   ========================================== */
/* Foco visível em todos os elementos interativos */
button:focus,
a:focus,
select:focus,
input:focus,
.disciplina-header-clickable:focus {
    outline: 2px solid #023A8D;
    outline-offset: 2px;
}

/* Tooltips acessíveis */
[title] {
    position: relative;
}

/* Melhorar contraste dos ícones monocromáticos */
.icon-mono {
    color: #495057;
}

/* Estados de hover acessíveis */
.btn-group .btn:focus,
.action-btn:focus {
    outline: 2px solid #023A8D;
    outline-offset: 2px;
    z-index: 20;
}

/* ==========================================
   CONTROLES: EXPANDIR/RECOLHER TODOS
   ========================================== */
.disciplinas-global-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 12px 16px;
    background: #f8f9fa;
    border-radius: 8px;
}

.global-controls-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.global-control-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border: 1px solid #dee2e6;
    background: white;
    color: #495057;
    font-size: 13px;
    font-weight: 500;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.global-control-btn:hover {
    border-color: #023A8D;
    color: #023A8D;
    background: #f0f7ff;
}

.global-control-btn i {
    font-size: 12px;
}

/* ==========================================
   RESPONSIVIDADE - TABELA MOBILE
   ========================================== */
/* Em telas mobile, transformar tabela em lista stacked */
@media (max-width: 768px) {
    .table-responsive {
        overflow-x: visible;
    }
    
    .table-responsive table {
        display: block;
    }
    
    .table-responsive thead {
        display: none;
    }
    
    .table-responsive tbody {
        display: block;
    }
    
    .table-responsive tr {
        display: block;
        margin-bottom: 16px;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 12px;
        background: white;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    
    .table-responsive td {
        display: block;
        text-align: left !important;
        padding: 6px 0 !important;
        border: none;
    }
    
    .table-responsive td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #495057;
        display: inline-block;
        margin-right: 8px;
        min-width: 100px;
    }
    
    /* Ajustar chips e badges em mobile */
    .badge,
    .status-badge-table {
        margin-top: 4px;
    }
    
    /* Ações por linha em mobile */
    .btn-group {
        display: flex;
        gap: 8px;
        margin-top: 8px;
        justify-content: flex-start;
    }
}

/* Responsividade para tabela */
@media (max-width: 768px) {
    .masthead {
        align-items: flex-start;
    }

    .masthead-top {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .masthead-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .action-inline {
        display: none;
    }

    .action-menu-trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        border: 1px solid var(--gray-300);
        border-radius: 8px;
        background: white;
        padding: 8px 10px;
        color: var(--gray-700);
    }

    .aulas-table {
        font-size: 0.8rem;
    }
    
    .aulas-table th,
    .aulas-table td {
        padding: 8px 4px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .stat-card-mini {
        padding: 10px;
    }
    
    .stat-number-mini {
        font-size: 1.2rem;
    }
}

@media (max-width: 576px) {
    .aulas-table {
        font-size: 0.75rem;
    }
    
    .aulas-table th,
    .aulas-table td {
        padding: 6px 2px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .instrutor-info {
        font-size: 0.8rem;
    }
}

/* ==========================================
   ESTILOS PARA TABELA DE ALUNOS MATRICULADOS
   ========================================== */
.alunos-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.alunos-table th {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-right: 1px solid #dee2e6;
}

.alunos-table td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
    border-right: 1px solid #dee2e6;
    transition: background-color 0.2s;
}

.alunos-table tr:hover {
    background-color: #f8f9fa;
}

.aluno-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.status-matriculado {
    background: #d4edda;
    color: #155724;
}

.status-cursando {
    background: #cce5ff;
    color: #004085;
}

.status-transferido {
    background: #fff3cd;
    color: #856404;
}

.status-concluido {
    background: #d1ecf1;
    color: #0c5460;
}

.action-buttons {
    display: flex;
    gap: 5px;
    justify-content: center;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 8px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--gray-800);
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.action-btn:hover {
    transform: translateY(-1px);
    box-shadow: none;
}
.action-btn-tonal {
    background: rgba(37, 99, 235, 0.12);
    color: var(--primary-dark);
}

.action-btn-tonal:hover {
    background: rgba(37, 99, 235, 0.18);
}

.action-btn-outline-danger {
    border-color: rgba(239, 68, 68, 0.4);
    color: var(--danger-color);
    background: transparent;
}

.action-btn-outline-danger:hover {
    background: rgba(239, 68, 68, 0.08);
}

.masthead {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
}

.masthead-top {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.masthead-left {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.page-title {
    margin: 0;
    color: var(--primary-dark);
    font-size: 1.6rem;
    font-weight: 700;
}
.icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: inherit;
    line-height: 1;
}

.icon-18 {
    font-size: 1.125rem;
}
.turma-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    color: var(--gray-600);
    font-size: 0.95rem;
}

.turma-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--gray-700);
}
.turma-meta-item i {
    color: var(--gray-500);
}

.turma-meta-separator {
    color: var(--gray-400);
}
.breadcrumb-nav {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: var(--gray-600);
}
.breadcrumb-nav a {
    color: var(--primary-dark);
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.breadcrumb-nav a:hover {
    text-decoration: underline;
    color: #1a4ba8;
}

.breadcrumb-nav a i {
    font-size: 0.85rem;
    color: var(--gray-500);
}

.breadcrumb-separator {
    color: var(--gray-400);
}
.current-context {
    font-weight: 600;
    color: var(--gray-800);
}
.masthead-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.action-inline {
    display: inline-flex;
}

.action-menu {
    position: relative;
}

.action-menu-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    background: white;
    padding: 8px 10px;
    color: var(--gray-700);
}

.action-menu-trigger:hover,
.action-menu-trigger:focus {
    background: var(--gray-100);
    color: var(--primary-dark);
}
.action-menu-dropdown {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    box-shadow: var(--shadow-md);
    min-width: 200px;
    padding: 6px;
    display: none;
    z-index: 2000;
}

.action-menu-dropdown.open {
    display: block;
}
.action-menu-dropdown button,
.action-menu-dropdown a {
    width: 100%;
    padding: 10px 12px;
    border: none;
    background: transparent;
    color: var(--gray-700);
    text-align: left;
    font-size: 0.95rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.action-menu-dropdown button:hover,
.action-menu-dropdown a:hover {
    background: var(--gray-100);
    color: var(--primary-dark);
}

.action-menu-dropdown .danger {
    color: var(--danger-color);
}

@media (max-width: 768px) {
    .masthead {
        align-items: flex-start;
    }

    .masthead-top {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .masthead-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .action-inline {
        display: none;
    }

    .action-menu-trigger {
        display: inline-flex;
    }
}

/* ==========================================
   RESPONSIVIDADE
   ========================================== */
@media (max-width: 768px) {
    .disciplina-row-layout {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .inline-edit[data-field="curso_tipo"] {
        min-width: 100% !important;
        white-space: normal !important;
    }
    
    .disciplina-field-container {
        width: 100%;
    }
}

/* Estilos para o histórico de agendamentos */
.historico-agendamentos {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-top: 10px;
}

.historico-agendamentos h6 {
    color: #023A8D;
    font-weight: 600;
    border-bottom: 2px solid #023A8D;
    padding-bottom: 8px;
    margin-bottom: 20px;
}

.historico-agendamentos .table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.historico-agendamentos .table thead th {
    background: #023A8D;
    color: white;
    border: none;
    font-weight: 600;
    text-align: center;
    padding: 12px 8px;
}

.historico-agendamentos .table tbody td {
    text-align: center;
    vertical-align: middle;
    padding: 12px 8px;
    border-color: #e9ecef;
}

.historico-agendamentos .table tbody tr:hover {
    background-color: #f8f9fa;
}

.historico-agendamentos .badge {
    font-size: 0.75rem;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 500;
}

/* Responsividade da tabela */
@media (max-width: 768px) {
    .historico-agendamentos .table-responsive {
        font-size: 0.875rem;
    }
    
    .historico-agendamentos .table thead th,
    .historico-agendamentos .table tbody td {
        padding: 8px 4px;
    }
    
    .historico-agendamentos .badge {
        font-size: 0.7rem;
        padding: 4px 8px;
    }
}
</style>
<!-- Cabeçalho -->
<?php 
// Verificar se é administrador (variável pode vir do arquivo pai ou precisamos verificar aqui)
$isAdminLocal = isset($isAdmin) ? $isAdmin : (function_exists('isAdmin') ? isAdmin() : false);
$salaNome = obterNomeSala($turma['sala_id'], $salasCadastradas);
$salaDisplay = trim((string)$salaNome) !== '' ? $salaNome : 'Sala não definida';
[$periodoInicio, $periodoFim] = formatarPeriodoTurma($turma);
$modalidadeTexto = obterModalidadeTexto($turma['modalidade'] ?? null);
$observacoesTexto = trim((string)($turma['observacoes'] ?? ''));
?>
<div class="masthead">
    <nav class="breadcrumb-nav" aria-label="Breadcrumb">
        <a href="?page=turmas-teoricas" title="Voltar para Gestão de Turmas">
            <i class="fas fa-arrow-left"></i>
            Gestão de Turmas
        </a>
        <span class="breadcrumb-separator">›</span>
        <span class="current-context"><?= htmlspecialchars($turma['nome']) ?></span>
    </nav>
    <div class="masthead-top">
        <h1 class="page-title">
            <span class="inline-edit" data-field="nome" data-type="text" data-value="<?= htmlspecialchars($turma['nome']) ?>">
                <?= htmlspecialchars($turma['nome']) ?>
                <i class="fas fa-edit edit-icon"></i>
            </span>
            <span class="status-badge status-<?= $turma['status'] ?> inline-edit" data-field="status" data-type="select" data-value="<?= $turma['status'] ?>" style="margin-left: 12px;">
                <?= obterStatusBadgeTexto($turma['status']) ?>
                <i class="fas fa-edit edit-icon"></i>
            </span>
        </h1>
        <div class="masthead-actions">
            <button onclick="abrirModalInserirAlunos()" class="action-btn action-btn-tonal action-inline" type="button">
                <i class="fas fa-user-plus"></i>
                <span>Inserir Alunos</span>
            </button>
            <div class="action-menu">
                <button type="button" class="action-btn action-menu-trigger" aria-haspopup="true" aria-expanded="false" onclick="toggleActionMenu(this)">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="action-menu-dropdown" role="menu">
                    <button type="button" onclick="closeAllActionMenus(); abrirModalInserirAlunos()">
                        <i class="fas fa-user-plus"></i>
                        Inserir Alunos
                    </button>
                    <button type="button" onclick="closeAllActionMenus(); window.print()">
                        <i class="fas fa-print"></i>
                        Imprimir Detalhes
                    </button>
                    <?php if ($isAdminLocal && isset($turma)): ?>
                    <button type="button" class="danger" onclick="closeAllActionMenus(); excluirTurmaCompleta(<?= $turma['id'] ?>, '<?= htmlspecialchars(addslashes($turma['nome'])) ?>')">
                        <i class="fas fa-trash-alt"></i>
                        Excluir Turma
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="turma-meta">
        <span class="turma-meta-item">
            <i class="fas fa-door-open icon icon-18"></i>
            <?= htmlspecialchars($salaDisplay) ?>
        </span>
        <span class="turma-meta-separator">•</span>
        <span class="turma-meta-item">
            <i class="fas fa-calendar icon icon-18"></i>
            <?= htmlspecialchars($periodoInicio ?: 'Data inicial indefinida') ?>
            &ndash;
            <?= htmlspecialchars($periodoFim ?: 'Data final indefinida') ?>
        </span>
        <span class="turma-meta-separator">•</span>
        <span class="turma-meta-item">
            <i class="fas fa-chalkboard-teacher icon icon-18"></i>
            <?= htmlspecialchars($modalidadeTexto) ?>
        </span>
    </div>
</div>

<!-- Sistema de Abas -->
<style>
.tabs-container {
    background: transparent;
    border-radius: 0;
    box-shadow: none;
    margin-bottom: 20px;
    overflow: visible;
}

.tabs-header {
    display: flex;
    background: transparent;
    border-bottom: 1px solid var(--gray-200);
    overflow-x: auto;
}

.tab-button {
    padding: 12px 20px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--gray-500);
    transition: color 0.2s ease, border-color 0.2s ease;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 0 0 auto;
}

.tab-button:hover {
    color: var(--primary-dark);
}

.tab-button.active {
    color: var(--primary-dark);
    background: transparent;
    border-bottom-color: var(--primary-dark);
}

.tab-button i {
    font-size: 1rem;
}

.tab-content {
    display: none;
    padding: 0;
    animation: fadeIn 0.3s ease;
    background: transparent;
    border-bottom: none !important;
    /* CRÍTICO: Zerar padding-bottom e margin-bottom para evitar faixa em branco */
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
    min-height: auto !important;
}

.tab-content.active {
    display: block;
    border-bottom: none !important;
    /* CRÍTICO: Zerar padding-bottom e margin-bottom mesmo quando ativo */
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}

/* Remover borda inferior específica da aba calendário */
#tab-calendario {
    border-bottom: none !important;
    /* CRÍTICO: Zerar espaçamento inferior da aba calendário */
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
    min-height: auto !important;
}

#tab-calendario * {
    border-bottom: none !important;
}

/* CRÍTICO: Remover pseudo-elementos da aba calendário que podem criar espaço */
#tab-calendario::after,
#tab-calendario::before,
.tab-content::after,
.tab-content::before {
    display: none !important;
    content: none !important;
    height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
}

/* CRÍTICO: Zerar margin-bottom do último elemento dentro da aba calendário */
#tab-calendario > *:last-child {
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

/* CRÍTICO: Garantir que o wrapper do calendário não tenha espaçamento extra */
#tab-calendario .container-fluid,
#tab-calendario .row,
#tab-calendario .col,
#tab-calendario .col-md,
#tab-calendario .col-lg,
#tab-calendario .col-xl {
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}

/* CRÍTICO: Se houver gap/row-gap em Grid/Flex, zerar para o último item */
#tab-calendario [style*="display: grid"],
#tab-calendario [style*="display: flex"] {
    gap: 0 !important;
    row-gap: 0 !important;
}

/* CRÍTICO: Último filho do conteúdo da página não deve ter margin-bottom */
#tab-calendario:last-child,
.tab-content:last-child {
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

/* CRÍTICO: Remover espaçamento extra dos wrappers externos (admin-main, wizard-content, etc.) */
.admin-main,
.wizard-content,
.turma-wizard,
.admin-container,
.main-content,
.content-wrapper,
.page-wrapper {
    padding-bottom: 0 !important;
}

/* CRÍTICO: Zerar margin-top do .admin-main (a compensação da navbar está no body.padding-top) */
.admin-main,
.admin-container {
    margin-top: 0 !important;
}

/* CRÍTICO: Remover qualquer inline style que possa estar aplicando margin-top */
.admin-main[style*="margin-top"],
.admin-container[style*="margin-top"] {
    margin-top: 0 !important;
}

/* CRÍTICO: Reduzir padding-top do wrapper principal para respiro consistente */
.admin-main {
    padding-top: 14px !important;
}

@media (max-width: 768px) {
    .admin-main {
        padding-top: 10px !important;
    }
}

@media (max-width: 480px) {
    .admin-main {
        padding-top: 8px !important;
    }
}

/* CRÍTICO: Garantir que o primeiro elemento dentro do admin-main não tenha margin-top */
.admin-main > *:first-child {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

/* CRÍTICO: Zerar margin-top do page-header (header da seção "Gestão de Turmas") */
.page-header,
.admin-main .page-header {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

/* CRÍTICO: Remover pseudo-elementos do page-header que possam criar espaço */
.page-header::before,
.page-header::after,
.admin-main .page-header::before,
.admin-main .page-header::after {
    display: none !important;
    content: none !important;
    height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
}

/* CRÍTICO: Remover pseudo-elementos do primeiro filho que possam criar espaço */
.admin-main > *:first-child::before,
.admin-main > *:first-child::after {
    display: none !important;
    content: none !important;
    height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
}

/* CRÍTICO: Garantir que o último elemento dentro do admin-main não tenha margin-bottom */
.admin-main > *:last-child,
.wizard-content > *:last-child,
.turma-wizard > *:last-child {
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

/* CRÍTICO: Remover pseudo-elementos dos wrappers externos que podem criar espaço */
.admin-main::after,
.admin-main::before,
.wizard-content::after,
.wizard-content::before,
.turma-wizard::after,
.turma-wizard::before {
    display: none !important;
    content: none !important;
    height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
}

/* CRÍTICO: Se houver gap/row-gap em Grid/Flex nos wrappers externos, zerar */
.admin-main[style*="display: grid"],
.admin-main[style*="display: flex"],
.wizard-content[style*="display: grid"],
.wizard-content[style*="display: flex"] {
    gap: 0 !important;
    row-gap: 0 !important;
}

/* CRÍTICO: Garantir que não haja gap no topo do primeiro grupo de elementos */
.admin-main > *:first-child {
    margin-top: 0 !important;
}
/* CRÍTICO: Se o admin-main usar Grid/Flex, garantir que o primeiro row/item não tenha gap superior */
.admin-main[style*="display: grid"] > *:first-child,
.admin-main[style*="display: flex"] > *:first-child {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

/* Exceto para elementos que precisam de borda superior (como headers) */
#tab-calendario .timeline-header {
    border-bottom: 1px solid #dadce0 !important;
}

/* =====================================================
   RESPONSIVIDADE - COMPACTAR LAYOUT PARA MAXIMIZAR GRID
   ===================================================== */

/* CRÍTICO: Neutralizar qualquer classe Bootstrap que possa zerar gap/margens */
.google-calendar-header.g-0,
.google-calendar-header.gx-0,
.google-calendar-header.gy-0,
.google-calendar-header .row,
.google-calendar-header .col {
    gap: inherit !important;
    margin: inherit !important;
}
/* CRÍTICO: Garantir espaçamento nos filhos diretos do cabeçalho (fallback) */
/* Aplicar margin-right em todos exceto o último e o filtro (que usa margin-left: auto) */
.google-calendar-header > *:not(:last-child):not(#filtro-disciplina-calendario) {
    margin-right: 14px !important;
}

/* Desktop (≥1200px) - Toolbar + badge em linha única */
@media (min-width: 1200px) {
    .google-calendar-header {
        flex-wrap: nowrap !important;
        column-gap: 14px !important;
        row-gap: 6px !important;
    }
    
    .google-calendar-header > *:not(:last-child):not(#filtro-disciplina-calendario) {
        margin-right: 14px !important;
    }
}

/* Tablet (768-1199px) - Toolbar em 2 linhas */
@media (min-width: 768px) and (max-width: 1199px) {
    .google-calendar-header {
        column-gap: 12px !important;
        row-gap: 6px !important;
    }
    
    .google-calendar-header > *:not(:last-child):not(#filtro-disciplina-calendario) {
        margin-right: 12px !important;
    }
    
    /* Quando quebrar linha, garantir espaçamento vertical */
    .google-calendar-header > * {
        margin-bottom: 6px !important;
    }
    
    .timeline-calendar {
        min-height: calc(100vh - 200px) !important;
        /* Remover max-height para permitir que o calendário se expanda conforme necessário */
        /* max-height removido para permitir scroll e mostrar todas as aulas */
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }
}
/* Mobile (<768px) - Toolbar colapsada */
@media (max-width: 767px) {
    .google-calendar-header {
        flex-direction: row !important;
        flex-wrap: wrap !important;
        column-gap: 12px !important;
        row-gap: 8px !important;
        padding: 6px 0 !important;
    }
    
    .google-calendar-header > *:not(:last-child):not(#filtro-disciplina-calendario) {
        margin-right: 12px !important;
    }
    
    /* Quando quebrar linha, garantir espaçamento vertical */
    .google-calendar-header > * {
        margin-bottom: 8px !important;
    }
    
    /* Garantir altura mínima de toque confortável em mobile */
    .google-calendar-header button,
    .google-calendar-header select {
        min-height: 40px !important;
    }
    
    .google-calendar-header h3 {
        font-size: 16px !important;
    }
    
    /* Filtro vai para linha própria em mobile se necessário */
    .google-calendar-header #filtro-disciplina-calendario {
        margin-left: 0 !important;
        margin-right: 0 !important;
        width: 100% !important;
    }
    
    .timeline-day-header {
        min-height: 32px !important;
        height: 32px !important;
        padding: 1px !important;
    }
    
    .timeline-day-header .dia-nome {
        font-size: 7px !important;
    }
    
    .timeline-day-header .dia-data {
        font-size: 14px !important;
    }
    
    .timeline-header {
        min-height: 32px !important;
        height: 32px !important;
    }
    
    .timeline-calendar {
        min-height: calc(100vh - 160px) !important;
        /* Remover max-height para permitir que o calendário se expanda conforme necessário */
        /* max-height removido para permitir scroll e mostrar todas as aulas */
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }
    
    .timeline-hour-marker {
        height: 45px !important;
    }
    
    .timeline-hour-label {
        font-size: 8px !important;
    }
}
/* Reduzir espaçamentos verticais entre seções */
#tab-calendario > div {
    margin-top: 4px !important;
    margin-bottom: 4px !important;
}

#tab-calendario > div:first-child {
    margin-top: 0 !important;
}

/* Ajustar altura dinâmica do calendário via JavaScript */

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .tabs-header {
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .tab-button {
        padding: 12px 16px;
        font-size: 0.85rem;
    }
}
</style>
<script>
// Definir showTab no escopo global ANTES dos botões para evitar erros
window.showTab = function(tabName) {
    // Esconder todos os conteúdos de abas
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.classList.remove('active');
    });
    
    // Remover classe active de todos os botões
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });
    
    // Mostrar a aba selecionada
    const selectedTab = document.getElementById('tab-' + tabName);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Ativar o botão correspondente
    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(button => {
        if (button.onclick && button.onclick.toString().includes("'" + tabName + "'")) {
            button.classList.add('active');
        } else {
            // Método alternativo: verificar pelo texto do botão
            const buttonText = button.textContent.toLowerCase().trim();
            if (buttonText.includes(tabName.toLowerCase())) {
                button.classList.add('active');
            }
        }
    });
    
    // Salvar aba ativa no localStorage
    localStorage.setItem('turmaDetalhesAbaAtiva', tabName);
    localStorage.setItem('turma-tab-active', tabName);
    
    // CRÍTICO: Inicializar calendário quando a aba for exibida pela primeira vez
    if (tabName === 'calendario') {
        // Usar requestAnimationFrame para garantir que o container está visível e medido
        requestAnimationFrame(() => {
            if (typeof window.inicializarCalendarioSemana === 'function') {
                window.inicializarCalendarioSemana({ forceFetch: true, remeasure: true });
            }
        });
    }

    // Garantir que as estatísticas estejam atualizadas sempre que a aba for exibida
    if (tabName === 'estatisticas') {
        requestAnimationFrame(() => {
            if (typeof window.atualizarEstatisticasTurma === 'function') {
                window.atualizarEstatisticasTurma();
            }
        });
    }
};

function closeAllActionMenus() {
    document.querySelectorAll('.action-menu-dropdown.open').forEach(menu => {
        menu.classList.remove('open');
        const trigger = menu.previousElementSibling;
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
    document.removeEventListener('click', handleActionMenuOutside);
}

function handleActionMenuOutside(event) {
    if (event.target.closest('.action-menu')) {
        return;
    }
    closeAllActionMenus();
}

function toggleActionMenu(trigger) {
    const dropdown = trigger.nextElementSibling;
    if (!dropdown) {
        return;
    }

    const willOpen = !dropdown.classList.contains('open');
    closeAllActionMenus();

    if (willOpen) {
        dropdown.classList.add('open');
        trigger.setAttribute('aria-expanded', 'true');
        document.addEventListener('click', handleActionMenuOutside);
    }
}

function updateTurmaHeaderName(newName) {
    const safeName = newName && newName.trim() !== '' ? newName : 'Turma sem nome';
    const titleEl = document.querySelector('.page-title');
    const breadcrumbEl = document.querySelector('.breadcrumb-nav .current-context');
    if (titleEl) {
        titleEl.textContent = safeName;
    }
    if (breadcrumbEl) {
        breadcrumbEl.textContent = safeName;
    }
}
</script>
<div class="tabs-container">
    <div class="tabs-header">
        <button class="tab-button active" onclick="showTab('resumo')">
            <i class="fas fa-home"></i>
            <span>Resumo</span>
        </button>
        <button class="tab-button" onclick="showTab('disciplinas')">
            <i class="fas fa-book"></i>
            <span>Disciplinas</span>
        </button>
        <button class="tab-button" onclick="showTab('alunos')">
            <i class="fas fa-users"></i>
            <span>Alunos</span>
        </button>
        <button class="tab-button" onclick="showTab('calendario')">
            <i class="fas fa-calendar-alt"></i>
            <span>Calendário</span>
        </button>
        <button class="tab-button" onclick="showTab('estatisticas')">
            <i class="fas fa-chart-bar"></i>
            <span>Estatísticas</span>
        </button>
        <?php if ($isAdmin || hasPermission('secretaria')): ?>
        <!-- AJUSTE 2025-12 - Aba Diário / Presenças para admin/secretaria -->
        <button class="tab-button" onclick="window.location.href='?page=turma-diario&turma_id=<?= $turmaId ?>'">
            <i class="fas fa-book-open"></i>
            <span>Diário / Presenças</span>
        </button>
        <?php endif; ?>
    </div>

    <!-- Aba Resumo -->
    <div id="tab-resumo" class="tab-content active">
<!-- Informações Básicas -->
<div id="edit-scope-basicas" style="padding: 20px; margin-bottom: 20px;">
    <h4 style="color: #023A8D; margin-bottom: 20px;">
        <i class="fas fa-graduation-cap me-2"></i>Informações Básicas
    </h4>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; align-items: flex-start; justify-items: start;">
        <div>
            <p style="color: #666; margin-bottom: 10px;">
                <span class="inline-edit" data-field="curso_tipo" data-type="select" data-value="<?= htmlspecialchars($turma['curso_tipo'] ?? '') ?>">
                    <?= htmlspecialchars($tiposCurso[$turma['curso_tipo']] ?? 'Curso não especificado') ?>
                    <i class="fas fa-edit edit-icon"></i>
                </span>
            </p>
            <div style="margin-bottom: 15px; text-align: left;">
                <h6 style="color: #023A8D; margin-bottom: 6px;">Observações</h6>
                <div style="color: #666; font-style: italic; text-align: left;">
                    <span class="inline-edit" data-field="observacoes" data-type="textarea" data-value="<?= htmlspecialchars($observacoesTexto) ?>">
                        <?php if ($observacoesTexto !== ''): ?>
                            <?= nl2br(htmlspecialchars($observacoesTexto)) ?>
                        <?php else: ?>
                            <span style="color: #999; font-style: italic;">Nenhuma observação cadastrada</span>
                        <?php endif; ?>
                        <i class="fas fa-edit edit-icon"></i>
                    </span>
                </div>
            </div>
        </div>
        
        <div style="display: grid; gap: 10px;">
            <div style="display: flex; align-items: center;">
                <i class="fas fa-building me-2" style="color: #023A8D; width: 20px;"></i>
                <span><strong>Sala:</strong> 
                    <span class="inline-edit" data-field="sala_id" data-type="select" data-value="<?= $turma['sala_id'] ?>">
                        <?= htmlspecialchars(obterNomeSala($turma['sala_id'], $salasCadastradas)) ?>
                        <i class="fas fa-edit edit-icon"></i>
                    </span>
                </span>
            </div>
            
            <div style="display: flex; align-items: center;">
                <i class="fas fa-calendar-alt me-2" style="color: #023A8D; width: 20px;"></i>
                <span><strong>Período:</strong> 
                    <span class="inline-edit" data-field="data_inicio" data-type="date" data-value="<?= $turma['data_inicio'] ?>">
                        <?= date('d/m/Y', strtotime($turma['data_inicio'])) ?>
                        <i class="fas fa-edit edit-icon"></i>
                    </span>
                    - 
                    <span class="inline-edit" data-field="data_fim" data-type="date" data-value="<?= $turma['data_fim'] ?>">
                        <?= date('d/m/Y', strtotime($turma['data_fim'])) ?>
                        <i class="fas fa-edit edit-icon"></i>
                    </span>
                </span>
            </div>
            
            <div style="display: flex; align-items: center;">
                <i class="fas fa-users me-2" style="color: #023A8D; width: 20px;"></i>
                <span><strong>Alunos:</strong> <?= $totalAlunos ?>/<?= $turma['max_alunos'] ?></span>
            </div>
            
            <div style="display: flex; align-items: center;">
                <i class="fas fa-clock me-2" style="color: #023A8D; width: 20px;"></i>
                <span><strong>Modalidade:</strong> 
                    <span class="inline-edit" data-field="modalidade" data-type="select" data-value="<?= $turma['modalidade'] ?>">
                        <?= obterModalidadeTexto($turma['modalidade']) ?>
                        <i class="fas fa-edit edit-icon"></i>
                    </span>
                </span>
            </div>
        </div>
    </div>
    
</div>

        <!-- Resumo Rápido -->
        <style>
        .resumo-turma-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 20px;
            margin-top: 20px;
        }

        .resumo-turma-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
            font-weight: 600;
            color: #023A8D;
            margin-bottom: 12px;
        }

        .resumo-turma-header i {
            font-size: 1.1rem;
        }

        .resumo-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

.resumo-kpi {
    background: #ffffff;
    border: 1px solid #e5e7ef;
    border-radius: 10px;
    padding: 10px 16px;
    min-height: 84px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 8px;
        }

.resumo-kpi-value {
    font-size: 1.9rem;
            line-height: 1.1;
            font-weight: 700;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }

        .resumo-kpi-value--primary {
            color: #023A8D;
        }

        .resumo-kpi-value--warning {
            color: #F7931E;
        }

        .resumo-kpi-value--alert {
            color: #dc3545;
        }

.resumo-kpi-sub {
    font-size: 1rem;
    font-weight: 500;
    color: #94a3b8;
        }

        .resumo-kpi-label {
            font-size: 0.82rem;
            font-weight: 500;
            color: #6b7280;
        }

        .resumo-proximas {
            margin-top: 24px;
            border-top: 1px solid #e5e7eb;
            padding-top: 18px;
            position: relative;
        }

        .resumo-proximas.is-loading .resumo-proximas-body {
            display: none;
        }

        .resumo-proximas.is-loading .resumo-proximas-skeleton {
            display: grid;
        }

        .resumo-proximas-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .resumo-proximas-header span {
            font-weight: 600;
            color: #111827;
        }

        .resumo-proximas-header-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .resumo-proximas-link {
            border: none;
            background: transparent;
            color: #023A8D;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .resumo-proximas-link:hover {
            background: rgba(2, 58, 141, 0.08);
        }

        .resumo-proximas-link--ghost {
            color: #4b5563;
        }

        .resumo-proximas-link--ghost:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .resumo-proximas-skeleton {
            display: none;
            gap: 12px;
        }

        .resumo-proximas-skeleton-item {
            height: 56px;
            border-radius: 12px;
            background: linear-gradient(90deg, #f3f4f6 0%, #e5e7eb 50%, #f3f4f6 100%);
            background-size: 200% 100%;
            animation: resumoProximasShimmer 1.2s ease-in-out infinite;
        }

        @keyframes resumoProximasShimmer {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        .resumo-proximas-body {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .resumo-proximas-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .resumo-proximas-group-header {
            font-size: 0.85rem;
            font-weight: 600;
            color: #1f2937;
            text-transform: capitalize;
            letter-spacing: 0.02em;
        }

        .resumo-proximas-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .resumo-proximas-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px 14px;
            position: relative;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .resumo-proximas-item:hover {
            border-color: rgba(2, 58, 141, 0.35);
            box-shadow: 0 12px 24px -15px rgba(2, 58, 141, 0.45);
            transform: translateY(-1px);
        }

        .resumo-proximas-item.has-conflict {
            border-color: #fca5a5;
        }

        .resumo-proximas-line {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .resumo-proximas-line-main {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            min-width: 0;
        }

        .resumo-proximas-line-meta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
            flex-shrink: 0;
        }

        .resumo-proximas-hour {
            font-weight: 600;
            font-size: 0.95rem;
            color: #111827;
        }

        .resumo-proximas-sep {
            color: #9ca3af;
            font-size: 0.82rem;
        }

        .resumo-proximas-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
            background: var(--pill-bg, #e5e7eb);
            color: var(--pill-color, #1f2937);
            white-space: nowrap;
        }

        .resumo-proximas-duration {
            font-size: 0.82rem;
            color: #4b5563;
            font-weight: 500;
            white-space: nowrap;
        }

        .resumo-proximas-status {
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            padding: 4px 10px;
            border-radius: 999px;
            text-transform: uppercase;
            background: #e5e7eb;
            color: #1f2937;
        }

        .resumo-proximas-status--confirmada {
            background: #dcfce7;
            color: #166534;
        }

        .resumo-proximas-status--reagendada {
            background: #ede9fe;
            color: #5b21b6;
        }

        .resumo-proximas-status--cancelada {
            background: #fee2e2;
            color: #b91c1c;
        }

        .resumo-proximas-status--pendente,
        .resumo-proximas-status--agendada {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .resumo-proximas-badge {
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            padding: 4px 10px;
            border-radius: 999px;
            text-transform: uppercase;
            background: #f3f4f6;
            color: #374151;
        }

        .resumo-proximas-badge--now {
            background: #fee2e2;
            color: #b91c1c;
        }

        .resumo-proximas-badge--soon {
            background: #fef3c7;
            color: #92400e;
        }

        .resumo-proximas-badge--tomorrow {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .resumo-proximas-badge--later {
            background: #f3f4f6;
            color: #374151;
        }

        .resumo-proximas-conflict {
            color: #b91c1c;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .resumo-proximas-actions {
            border: none;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .resumo-proximas-actions:hover {
            background: rgba(2, 58, 141, 0.08);
            color: #023A8D;
        }

        .resumo-proximas-actions-menu {
            position: absolute;
            top: calc(100% + 6px);
            right: 14px;
            min-width: 190px;
            padding: 6px 0;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            box-shadow: 0 18px 28px -18px rgba(15, 23, 42, 0.45);
            z-index: 30;
            display: flex;
            flex-direction: column;
        }

        .resumo-proximas-actions-menu[hidden] {
            display: none;
        }

        .resumo-proximas-actions-item {
            border: none;
            background: transparent;
            padding: 8px 14px;
            font-size: 0.85rem;
            color: #111827;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .resumo-proximas-actions-item:hover {
            background: #f3f4f6;
            color: #023A8D;
        }

        .resumo-proximas-subline {
            font-size: 0.78rem;
            color: #4b5563;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .resumo-proximas-meta {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .resumo-proximas-empty {
            margin-top: 12px;
            padding: 16px;
            border-radius: 12px;
            border: 1px dashed #aec8f5;
            background: #f8fafc;
            color: #4b5563;
            font-size: 0.9rem;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .resumo-proximas-empty a {
            color: #023A8D;
            text-decoration: none;
            font-weight: 600;
        }

        .resumo-proximas-empty a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .resumo-proximas-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .resumo-proximas-header-actions {
                width: 100%;
                justify-content: flex-start;
            }
            .resumo-proximas-line {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }
            .resumo-proximas-line-meta {
                margin-left: 0;
            }
        }

        @media (max-width: 1024px) {
            .resumo-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .resumo-kpi-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
<?php
        // Calcular totais para resumo
        $totalAulasObrigatorias = 0;
        $totalAulasAgendadas = 0;
        foreach ($estatisticasDisciplinas as $stats) {
            $totalAulasObrigatorias += $stats['obrigatorias'];
            $totalAulasAgendadas += $stats['agendadas'];
        }
        $percentualGeral = $totalAulasObrigatorias > 0 ? round(($totalAulasAgendadas / $totalAulasObrigatorias) * 100, 1) : 0;
        $percentualFormatado = number_format($percentualGeral, 1, ',', '.');
        $percentualClasse = 'resumo-kpi-value resumo-kpi-value--primary';
        if ($percentualGeral < 50) {
            $percentualClasse = 'resumo-kpi-value resumo-kpi-value--alert';
        } elseif ($percentualGeral < 75) {
            $percentualClasse = 'resumo-kpi-value resumo-kpi-value--warning';
        }

        $kpiAulasValor = $totalAulasObrigatorias > 0
            ? sprintf('%d<span class="resumo-kpi-sub">/%d</span>', $totalAulasAgendadas, $totalAulasObrigatorias)
            : (string) $totalAulasAgendadas;

        $totalDisciplinasSelecionadas = count($disciplinasSelecionadas);

        $agora = new DateTimeImmutable('now');
        $proximasAulas = [];

        foreach ($historicoAgendamentos as $disciplinaId => $agendamentosDisciplina) {
            foreach ($agendamentosDisciplina as $agendamento) {
                $dataAula = $agendamento['data_aula'] ?? null;
                if (!$dataAula) {
                    continue;
                }

                $statusAula = strtolower(trim((string)($agendamento['status'] ?? '')));
                if ($statusAula === 'realizada') {
                    continue;
                }

                $horaInicioStr = trim((string)($agendamento['hora_inicio'] ?? ''));
                if ($horaInicioStr === '') {
                    $horaInicioStr = '00:00:00';
                }

                try {
                    $inicio = new DateTimeImmutable($dataAula . ' ' . $horaInicioStr);
                } catch (Exception $e) {
                    continue;
                }

                $horaFimStr = trim((string)($agendamento['hora_fim'] ?? ''));
                $fim = null;
                if ($horaFimStr !== '') {
                    try {
                        $fim = new DateTimeImmutable($dataAula . ' ' . $horaFimStr);
                    } catch (Exception $e) {
                        $fim = null;
                    }
                }

                $duracaoMinutos = (int)($agendamento['duracao_minutos'] ?? 0);
                if ($fim instanceof DateTimeImmutable && $duracaoMinutos <= 0) {
                    $duracaoMinutos = (int) max(0, round(($fim->getTimestamp() - $inicio->getTimestamp()) / 60));
                } elseif (!$fim instanceof DateTimeImmutable && $duracaoMinutos > 0) {
                    $fim = $inicio->modify('+' . $duracaoMinutos . ' minutes');
                }

                if ($fim instanceof DateTimeImmutable && $fim <= $agora) {
                    continue;
                }

                if (!$fim instanceof DateTimeImmutable && $inicio < $agora) {
                    // Não conseguimos determinar se ainda está ocorrendo -> ignorar
                    continue;
                }

                $estaEmAndamento = $inicio <= $agora && (!$fim || $fim > $agora);
                $badgeTempo = criarBadgeTempo($agora, $inicio);
                if ($estaEmAndamento) {
                    $badgeTempo = ['label' => 'agora', 'classe' => 'now'];
                }

                $nomeAula = trim((string)($agendamento['nome_aula'] ?? ''));
                if ($nomeAula === '') {
                    $nomeAula = $disciplinasPorId[$disciplinaId] ?? $agendamento['nome_disciplina'] ?? 'Aula agendada';
                }
                $nomeAula = preg_replace('/\s*-\s*Aula\s*\d+$/i', '', $nomeAula);

                $instrutorNome = trim((string)($agendamento['instrutor_nome'] ?? ''));
                $salaNome = trim((string)($agendamento['sala_nome'] ?? ''));
                $salaNormalizada = $salaNome !== '' ? preg_replace('/^Sala\s+/i', '', $salaNome) : '';

                $disciplinaSlug = strtolower((string)($agendamento['disciplina'] ?? $disciplinaId ?? ''));
                $statusFormatado = formatarStatusAulaProxima($statusAula);
                $duracaoTexto = formatarDuracaoMinutos($duracaoMinutos);
                $ariaLabel = montarAriaLabelProxima(
                    [
                        'nome' => $nomeAula,
                        'instrutor' => $instrutorNome,
                        'sala' => $salaNormalizada,
                    ],
                    $inicio,
                    $fim,
                    $duracaoMinutos
                );

                $proximasAulas[] = [
                    'id' => $agendamento['id'] ?? null,
                    'timestamp' => $inicio->getTimestamp(),
                    'fim_timestamp' => $fim instanceof DateTimeImmutable ? $fim->getTimestamp() : null,
                    'inicio' => $inicio,
                    'fim' => $fim,
                    'data_iso' => $inicio->format('Y-m-d'),
                    'hora' => $inicio->format('H:i'),
                    'nome' => $nomeAula,
                    'disciplina_nome' => $disciplinaNome = $disciplinasPorId[$disciplinaId] ?? $agendamento['nome_disciplina'] ?? 'Disciplina',
                    'disciplina_slug' => $disciplinaSlug,
                    'instrutor' => $instrutorNome,
                    'sala' => $salaNormalizada,
                    'duracao_min' => $duracaoMinutos,
                    'duracao_texto' => $duracaoTexto,
                    'status_label' => $statusFormatado['label'],
                    'status_class' => $statusFormatado['classe'],
                    'status_raw' => $statusAula,
                    'badge' => $badgeTempo,
                    'is_ongoing' => $estaEmAndamento,
                    'aria_label' => $ariaLabel,
                    'data_aula' => $dataAula,
                    'hora_inicio_raw' => $horaInicioStr,
                    'hora_fim_raw' => $horaFimStr,
                ];
            }
        }

        usort($proximasAulas, static function (array $a, array $b) {
            if ($a['is_ongoing'] !== $b['is_ongoing']) {
                return $a['is_ongoing'] ? -1 : 1;
            }
            if ($a['timestamp'] === $b['timestamp']) {
                return ($a['fim_timestamp'] ?? PHP_INT_MAX) <=> ($b['fim_timestamp'] ?? PHP_INT_MAX);
            }
            return $a['timestamp'] <=> $b['timestamp'];
        });

        // Detectar conflitos (sobreposição no mesmo dia)
        $totalAulas = count($proximasAulas);
        for ($i = 0; $i < $totalAulas; $i++) {
            $proximasAulas[$i]['tem_conflito'] = false;
        }

        for ($i = 0; $i < $totalAulas; $i++) {
            for ($j = $i + 1; $j < $totalAulas; $j++) {
                if ($proximasAulas[$i]['data_iso'] !== $proximasAulas[$j]['data_iso']) {
                    continue;
                }

                $inicioI = $proximasAulas[$i]['timestamp'];
                $fimI = $proximasAulas[$i]['fim_timestamp'] ?? null;
                $inicioJ = $proximasAulas[$j]['timestamp'];
                $fimJ = $proximasAulas[$j]['fim_timestamp'] ?? null;

                if ($fimI === null || $fimJ === null) {
                    continue;
                }

                $sobrepoe = $inicioI < $fimJ && $inicioJ < $fimI;
                if ($sobrepoe) {
                    $proximasAulas[$i]['tem_conflito'] = true;
                    $proximasAulas[$j]['tem_conflito'] = true;
                }
            }
        }

        $proximasAulasDisplay = array_slice($proximasAulas, 0, 5);

        // Agrupar por data para exibição
        $proximasAulasAgrupadas = [];
        foreach ($proximasAulasDisplay as $aulaResumo) {
            $dataIso = $aulaResumo['data_iso'];
            if (!isset($proximasAulasAgrupadas[$dataIso])) {
                $proximasAulasAgrupadas[$dataIso] = [
                    'cabecalho' => formatarCabecalhoProximas($aulaResumo['inicio']),
                    'itens' => [],
                ];
            }
            $proximasAulasAgrupadas[$dataIso]['itens'][] = $aulaResumo;
        }
        ?>
        <div class="resumo-turma-card">
            <div class="resumo-turma-header">
                <i class="fas fa-info-circle"></i>
                <span>Resumo da Turma</span>
            </div>
            <div class="resumo-kpi-grid">
                <div class="resumo-kpi">
                    <span class="resumo-kpi-value resumo-kpi-value--primary"><?= $kpiAulasValor ?></span>
                    <span class="resumo-kpi-label">Aulas agendadas</span>
                </div>
                <div class="resumo-kpi">
                    <span class="<?= $percentualClasse ?>"><?= $percentualFormatado ?>%</span>
                    <span class="resumo-kpi-label">Progresso geral</span>
                </div>
                <div class="resumo-kpi">
                    <span class="resumo-kpi-value resumo-kpi-value--primary"><?= $totalAlunos ?></span>
                    <span class="resumo-kpi-label">Alunos matriculados</span>
                </div>
                <div class="resumo-kpi">
                    <span class="resumo-kpi-value resumo-kpi-value--primary"><?= $totalDisciplinasSelecionadas ?></span>
                    <span class="resumo-kpi-label">Disciplinas</span>
                </div>
            </div>
        <?php if (!empty($proximasAulasDisplay)): ?>
        <div class="resumo-proximas is-loading" data-proximas-aulas>
            <div class="resumo-proximas-header">
                <span>Próximas aulas</span>
                <div class="resumo-proximas-header-actions">
                    <button type="button" class="resumo-proximas-link resumo-proximas-link--ghost" onclick="showTab('calendario'); document.getElementById('tab-calendario')?.scrollIntoView({behavior: 'smooth'});">
                        Ver todas
                    </button>
                    <button type="button" class="resumo-proximas-link" onclick="showTab('calendario'); document.getElementById('tab-calendario')?.scrollIntoView({behavior: 'smooth'});">
                        Abrir calendário
                        <i class="fas fa-arrow-up-right-from-square" style="font-size: 0.8rem;"></i>
                    </button>
                </div>
            </div>
            <div class="resumo-proximas-skeleton" aria-hidden="true">
                <div class="resumo-proximas-skeleton-item"></div>
                <div class="resumo-proximas-skeleton-item"></div>
                <div class="resumo-proximas-skeleton-item"></div>
            </div>
            <div class="resumo-proximas-body">
                <?php foreach ($proximasAulasAgrupadas as $grupo): ?>
                <div class="resumo-proximas-group">
                    <div class="resumo-proximas-group-header"><?= htmlspecialchars($grupo['cabecalho']) ?></div>
                    <ul class="resumo-proximas-list">
                        <?php foreach ($grupo['itens'] as $aulaResumo): ?>
                        <?php
                            $corDisciplina = obterCorDisciplina($aulaResumo['disciplina_slug'], $paletaCoresDisciplinas, 'base');
                            $corDisciplinaFundo = obterCorDisciplina($aulaResumo['disciplina_slug'], $paletaCoresDisciplinas, 'fundo');
                            $temIdAula = !empty($aulaResumo['id']);
                        ?>
                        <li class="resumo-proximas-item<?= $aulaResumo['tem_conflito'] ? ' has-conflict' : '' ?>"
                            data-aula-id="<?= htmlspecialchars((string)($aulaResumo['id'] ?? '')) ?>"
                            data-aula-nome="<?= htmlspecialchars($aulaResumo['nome'], ENT_QUOTES) ?>"
                            data-data-aula="<?= htmlspecialchars($aulaResumo['data_aula']) ?>"
                            data-hora-inicio="<?= htmlspecialchars($aulaResumo['hora_inicio_raw']) ?>"
                            data-hora-fim="<?= htmlspecialchars($aulaResumo['hora_fim_raw']) ?>"
                            aria-label="<?= htmlspecialchars($aulaResumo['aria_label']) ?>">
                            <div class="resumo-proximas-line">
                                <div class="resumo-proximas-line-main">
                                    <span class="resumo-proximas-hour"><?= htmlspecialchars($aulaResumo['hora']) ?></span>
                                    <span class="resumo-proximas-sep">•</span>
                                    <span class="resumo-proximas-pill" style="--pill-color: <?= htmlspecialchars($corDisciplina) ?>; --pill-bg: <?= htmlspecialchars($corDisciplinaFundo) ?>;">
                                        <?= htmlspecialchars($aulaResumo['disciplina_nome']) ?>
                                    </span>
                                    <span class="resumo-proximas-sep">•</span>
                                    <span class="resumo-proximas-duration"><?= htmlspecialchars($aulaResumo['duracao_texto']) ?></span>
                                </div>
                                <div class="resumo-proximas-line-meta">
                                    <?php if ($aulaResumo['tem_conflito']): ?>
                                    <span class="resumo-proximas-conflict" title="Conflito de horário">
                                        <i class="fas fa-triangle-exclamation"></i>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($aulaResumo['badge'])): ?>
                                    <span class="resumo-proximas-badge resumo-proximas-badge--<?= htmlspecialchars($aulaResumo['badge']['classe']) ?>">
                                        <?= htmlspecialchars($aulaResumo['badge']['label']) ?>
                                    </span>
                                    <?php endif; ?>
                                    <span class="resumo-proximas-status resumo-proximas-status--<?= htmlspecialchars($aulaResumo['status_class']) ?>">
                                        <?= htmlspecialchars($aulaResumo['status_label']) ?>
                                    </span>
                                    <?php if ($temIdAula): ?>
                                    <button type="button" class="resumo-proximas-actions" data-quick-actions-trigger aria-label="Ações rápidas para <?= htmlspecialchars($aulaResumo['nome'], ENT_QUOTES) ?>">
                                        <i class="fas fa-ellipsis-vertical"></i>
                                    </button>
                                    <div class="resumo-proximas-actions-menu" role="menu" hidden>
                                        <button type="button" class="resumo-proximas-actions-item" data-quick-action="calendar">
                                            <i class="fas fa-calendar-day"></i>
                                            Abrir no calendário
                                        </button>
                                        <button type="button" class="resumo-proximas-actions-item" data-quick-action="edit">
                                            <i class="fas fa-pen"></i>
                                            Editar
                                        </button>
                                        <button type="button" class="resumo-proximas-actions-item" data-quick-action="cancel">
                                            <i class="fas fa-ban"></i>
                                            Cancelar
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="resumo-proximas-subline">
                                <?php if (!empty($aulaResumo['instrutor'])): ?>
                                <span class="resumo-proximas-meta">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <?= htmlspecialchars($aulaResumo['instrutor']) ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($aulaResumo['instrutor']) && !empty($aulaResumo['sala'])): ?>
                                <span class="resumo-proximas-sep">•</span>
                                <?php endif; ?>
                                <?php if (!empty($aulaResumo['sala'])): ?>
                                <span class="resumo-proximas-meta">
                                    <i class="fas fa-door-open"></i>
                                    Sala <?= htmlspecialchars($aulaResumo['sala']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="resumo-proximas">
            <div class="resumo-proximas-header">
                <span>Próximas aulas</span>
                <button type="button" class="resumo-proximas-link" onclick="showTab('calendario'); document.getElementById('tab-calendario')?.scrollIntoView({behavior: 'smooth'});">
                    Abrir calendário
                    <i class="fas fa-arrow-up-right-from-square" style="font-size: 0.8rem;"></i>
                </button>
            </div>
            <div class="resumo-proximas-empty">
                <span>Sem próximas aulas — agende pelo Calendário.</span>
                <a href="javascript:void(0);" onclick="showTab('calendario'); document.getElementById('tab-calendario')?.scrollIntoView({behavior: 'smooth'});">
                    Ir para o calendário
                </a>
            </div>
        </div>
        <?php endif; ?>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.querySelector('[data-proximas-aulas]');
        if (!container || container.dataset.enhanced === 'true') {
            return;
        }

        container.dataset.enhanced = 'true';

        requestAnimationFrame(function () {
            container.classList.remove('is-loading');
        });

        const closeMenus = function () {
            container.querySelectorAll('.resumo-proximas-actions-menu').forEach(function (menu) {
                if (!menu.hasAttribute('hidden')) {
                    menu.setAttribute('hidden', 'hidden');
                }
                menu.dataset.open = 'false';
            });
        };

        container.querySelectorAll('[data-quick-actions-trigger]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const menu = trigger.nextElementSibling;
                if (!menu) {
                    return;
                }

                const isOpen = menu.dataset.open === 'true';
                closeMenus();

                if (!isOpen) {
                    menu.dataset.open = 'true';
                    menu.hidden = false;
                }
            });
        });

        container.querySelectorAll('[data-quick-action]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const item = button.closest('.resumo-proximas-item');
                if (!item) {
                    return;
                }

                const action = button.dataset.quickAction;
                const aulaId = item.dataset.aulaId;
                const nomeAula = item.dataset.aulaNome || 'esta aula';

                if (action === 'calendar') {
                    if (typeof showTab === 'function') {
                        showTab('calendario');
                    }
                    const calendario = document.getElementById('tab-calendario');
                    if (calendario) {
                        calendario.scrollIntoView({ behavior: 'smooth' });
                    }
                } else if (action === 'edit' && aulaId) {
                    if (typeof editarAgendamento === 'function') {
                        editarAgendamento(aulaId, '', '', '', '', '', '', '', '');
                    }
                } else if (action === 'cancel' && aulaId) {
                    if (typeof cancelarAgendamento === 'function') {
                        cancelarAgendamento(aulaId, nomeAula);
                    }
                }

                closeMenus();
            });
        });

        document.addEventListener('click', closeMenus);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenus();
            }
        });
    });
    </script>
    
    <!-- Aba Disciplinas -->
    <div id="tab-disciplinas" class="tab-content">
<!-- Disciplinas -->
<div style="padding: 20px;">
    <h4 style="color: #023A8D; margin-bottom: 20px;">
        <i class="fas fa-graduation-cap me-2"></i>Disciplinas da Turma
    </h4>
    
    <!-- Disciplinas já cadastradas -->
    <?php if (!empty($disciplinasSelecionadas)): ?>
        <div class="disciplinas-cadastradas-section">
            <!-- Controles Globais -->
            <div class="disciplinas-global-controls">
                <div class="global-controls-left">
                    <button class="global-control-btn" onclick="expandirTodasDisciplinas()" aria-label="Expandir todas as disciplinas">
                        <i class="fas fa-chevron-down"></i>
                        Expandir todas
                    </button>
                    <button class="global-control-btn" onclick="recolherTodasDisciplinas()" aria-label="Recolher todas as disciplinas">
                        <i class="fas fa-chevron-up"></i>
                        Recolher todas
                    </button>
                </div>
                <div class="global-controls-right">
                    <span style="font-size: 13px; color: #6c757d;">
                        <?= count($disciplinasSelecionadas) ?> disciplina<?= count($disciplinasSelecionadas) != 1 ? 's' : '' ?>
                    </span>
                </div>
            </div>
            
            <?php foreach ($disciplinasSelecionadas as $index => $disciplina): ?>
                <?php 
                $disciplinaId = $disciplina['disciplina_id'];
                
                // Debug: Verificar o valor da disciplinaId
                echo "<!-- DEBUG: disciplinaId = " . var_export($disciplinaId, true) . " -->";
                echo "<!-- DEBUG: disciplinaId type = " . gettype($disciplinaId) . " -->";
                echo "<!-- DEBUG: disciplinaId empty = " . var_export(empty($disciplinaId), true) . " -->";
                echo "<!-- DEBUG: disciplinaId == 0 = " . var_export($disciplinaId == 0, true) . " -->";
                echo "<!-- DEBUG: disciplinaId === 0 = " . var_export($disciplinaId === 0, true) . " -->";
                
                // Pular apenas disciplinas com ID realmente inválido (0, null, vazio)
                if (empty($disciplinaId) || $disciplinaId == 0 || $disciplinaId == '0' || $disciplinaId == null) {
                    echo "<!-- Disciplina com ID inválido ignorada: " . var_export($disciplinaId, true) . " -->";
                    continue;
                }
                
                // Debug: Confirmar que a disciplina será processada
                echo "<!-- DEBUG: Processando disciplina ID: " . $disciplinaId . " -->";
                echo "<!-- DEBUG: (int)disciplinaId = " . (int)$disciplinaId . " -->";
                
                $stats = $estatisticasDisciplinas[$disciplinaId] ?? ['agendadas' => 0, 'realizadas' => 0, 'faltantes' => 0, 'obrigatorias' => 0];
                ?>
                <div class="disciplina-cadastrada-card disciplina-accordion" data-disciplina-id="<?= $disciplinaId ?>" data-turma-id="<?= $turmaId ?>">
                    <!-- Cabeçalho da Disciplina (Sempre Visível) -->
                    <div class="disciplina-header-clickable" 
                         role="button" 
                         tabindex="0"
                         aria-expanded="false"
                         aria-controls="detalhes-disciplina-<?= $disciplinaId ?>"
                         onclick="console.log('🖱️ [ONCLICK] ===== CLIQUE DETECTADO ====='); console.log('🖱️ [ONCLICK] Disciplina clicada:', '<?= htmlspecialchars($disciplinaId) ?>'); console.log('🖱️ [ONCLICK] Chamando toggleSimples...'); toggleSimples('<?= htmlspecialchars($disciplinaId) ?>'); console.log('🖱️ [ONCLICK] ===== FIM DO CLIQUE =====');">
                        <div class="disciplina-info-display">
                            <div class="disciplina-nome-display">
                                <h6>
                                    <i class="fas fa-graduation-cap icon-mono"></i>
                                    <?= htmlspecialchars($disciplina['nome_disciplina'] ?? $disciplina['nome_original'] ?? 'Disciplina não especificada') ?>
                                    <i class="fas fa-chevron-down disciplina-chevron"></i>
                                </h6>
                                
                                <!-- Estatísticas de Aulas - KPIs em Chips -->
                                <div class="aulas-stats-container">
                                    <div class="stat-item stat-agendadas">
                                        <span class="stat-label">Agendadas</span>
                                        <span class="stat-value"><?= $stats['agendadas'] ?></span>
                                    </div>
                                    <div class="stat-item stat-realizadas">
                                        <span class="stat-label">Realizadas</span>
                                        <span class="stat-value"><?= $stats['realizadas'] ?></span>
                                    </div>
                                    <div class="stat-item stat-faltantes">
                                        <span class="stat-label">Faltantes</span>
                                        <span class="stat-value"><?= $stats['faltantes'] ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="disciplina-actions-menu">
                                <button class="disciplina-menu-trigger" 
                                        aria-label="Ações de <?= htmlspecialchars($disciplina['nome_disciplina'] ?? $disciplina['nome_original'] ?? 'disciplina') ?>"
                                        title="Ações de <?= htmlspecialchars($disciplina['nome_disciplina'] ?? $disciplina['nome_original'] ?? 'disciplina') ?>"
                                        onclick="event.stopPropagation(); toggleDisciplinaMenu(this);">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="disciplina-menu-dropdown">
                                    <button class="disciplina-menu-item" onclick="event.stopPropagation(); duplicarDisciplina(<?= $disciplinaId ?>);">
                                        <i class="fas fa-clone"></i>
                                        Agendar semelhante
                                    </button>
                                    <button class="disciplina-menu-item danger" onclick="event.stopPropagation(); removerDisciplina(<?= $disciplinaId ?>);">
                                        <i class="fas fa-trash-alt"></i>
                                        Remover disciplina
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conteúdo Detalhado (Sanfona) -->
                    <div class="disciplina-detalhes-content" id="detalhes-disciplina-<?= $disciplinaId ?>" style="display: none;">
                        <div class="disciplina-detalhes-data" id="data-disciplina-<?= $disciplinaId ?>">
                            <?php 
                            $agendamentos = $historicoAgendamentos[$disciplinaId] ?? [];
                            ?>
                            
                            <!-- Seção: Histórico de Agendamentos -->
                            <?php if (!empty($agendamentos)): ?>
                                <div class="historico-agendamentos">
                                    <!-- Cabeçalho do Painel Expandido -->
                                    <div class="disciplina-panel-header">
                                        <h6 class="disciplina-panel-title">
                                            <i class="fas fa-calendar-alt"></i>
                                            Histórico de Agendamentos
                                        </h6>
                                        <div class="disciplina-panel-actions">
                                            <div class="disciplina-quick-filters">
                                                <select class="quick-filter-select" 
                                                        id="filter-sala-<?= $disciplinaId ?>" 
                                                        aria-label="Filtrar por sala"
                                                        onchange="filtrarAgendamentos(<?= $disciplinaId ?>, 'sala', this.value)">
                                                    <option value="">Todas as salas</option>
                                                    <!-- Opções serão preenchidas dinamicamente -->
                                                </select>
                                                <select class="quick-filter-select" 
                                                        id="filter-instrutor-<?= $disciplinaId ?>"
                                                        aria-label="Filtrar por instrutor"
                                                        onchange="filtrarAgendamentos(<?= $disciplinaId ?>, 'instrutor', this.value)">
                                                    <option value="">Todos os instrutores</option>
                                                    <!-- Opções serão preenchidas dinamicamente -->
                                                </select>
                                                <select class="quick-filter-select" 
                                                        id="filter-status-<?= $disciplinaId ?>"
                                                        aria-label="Filtrar por situação"
                                                        onchange="filtrarAgendamentos(<?= $disciplinaId ?>, 'status', this.value)">
                                                    <option value="">Todas as situações</option>
                                                    <option value="agendada">Agendada</option>
                                                    <option value="realizada">Realizada</option>
                                                    <option value="cancelada">Cancelada</option>
                                                    <option value="reagendada">Reagendada</option>
                                                </select>
                                            </div>
                                            <button type="button" 
                                                    class="btn btn-primary btn-sm" 
                                                    onclick="abrirModalAgendarAula('<?= $disciplinaId ?>', '<?= htmlspecialchars($disciplina['nome_disciplina'], ENT_QUOTES) ?>', '<?= htmlspecialchars($turma['data_inicio']) ?>', '<?= htmlspecialchars($turma['data_fim']) ?>')"
                                                    aria-label="Agendar nova aula para <?= htmlspecialchars($disciplina['nome_disciplina']) ?>"
                                                    style="display: inline-flex; align-items: center; gap: 6px;">
                                                <i class="fas fa-plus"></i>
                                                Agendar Nova Aula
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover" data-disciplina-id="<?= $disciplinaId ?>">
                                            <thead class="table-primary">
                                                <tr>
                                                    <th>Aula</th>
                                                    <th data-sortable="true" data-sort-key="data">Data</th>
                                                    <th data-sortable="true" data-sort-key="horario">Horário</th>
                                                    <th>Instrutor</th>
                                                    <th>Sala</th>
                                                    <th>Duração</th>
                                                    <th>Status</th>
                                                    <th width="100">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                // Ordenar agendamentos por data e hora (crescente)
                                                usort($agendamentos, function($a, $b) {
                                                    $dateTimeA = strtotime($a['data_aula'] . ' ' . $a['hora_inicio']);
                                                    $dateTimeB = strtotime($b['data_aula'] . ' ' . $b['hora_inicio']);
                                                    return $dateTimeA - $dateTimeB;
                                                });
                                                
                                                foreach ($agendamentos as $agendamento): 
                                                    $dataObj = new DateTime($agendamento['data_aula']);
                                                    $diaSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][$dataObj->format('w')];
                                                    $mesAbrev = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'][(int)$dataObj->format('m') - 1];
                                                    $dataFormatada = sprintf('%s, %d %s %s', $diaSemana, $dataObj->format('d'), $mesAbrev, $dataObj->format('Y'));
                                                    $horarioFormatado = date('H:i', strtotime($agendamento['hora_inicio'])) . '–' . date('H:i', strtotime($agendamento['hora_fim']));
                                                ?>
                                                    <tr data-agendamento-id="<?= $agendamento['id'] ?>" data-sala="<?= htmlspecialchars($agendamento['sala_nome'] ?? '') ?>" data-instrutor="<?= htmlspecialchars($agendamento['instrutor_nome'] ?? '') ?>" data-status="<?= $agendamento['status'] ?>">
                                                        <td data-label="Aula">
                                                            <?php
                                                            // Remover " - Aula X" do nome e mostrar apenas a disciplina
                                                            $nomeAulaDisplay = $agendamento['nome_aula'];
                                                            $nomeAulaDisplay = preg_replace('/ - Aula \d+$/i', '', $nomeAulaDisplay);
                                                            ?>
                                                            <strong><?= htmlspecialchars($nomeAulaDisplay) ?></strong>
                                                        </td>
                                                        <td data-label="Data">
                                                            <?= $dataFormatada ?>
                                                        </td>
                                                        <td data-label="Horário">
                                                            <?= $horarioFormatado ?>
                                                        </td>
                                                        <td data-label="Instrutor">
                                                            <?= htmlspecialchars($agendamento['instrutor_nome'] ?? 'Não informado') ?>
                                                        </td>
                                                        <td data-label="Sala">
                                                            <?= htmlspecialchars($agendamento['sala_nome'] ?? 'Não informada') ?>
                                                        </td>
                                                        <td data-label="Duração">
                                                            <?= $agendamento['duracao_minutos'] ?> min
                                                        </td>
                                                        <td data-label="Status">
                                                            <?php
                                                            $statusClass = '';
                                                            $statusText = '';
                                                            switch ($agendamento['status']) {
                                                                case 'agendada':
                                                                    $statusClass = 'badge bg-warning';
                                                                    $statusText = 'Agendada';
                                                                    break;
                                                                case 'realizada':
                                                                    $statusClass = 'badge bg-success';
                                                                    $statusText = 'Realizada';
                                                                    break;
                                                                case 'cancelada':
                                                                    $statusClass = 'badge bg-danger';
                                                                    $statusText = 'Cancelada';
                                                                    break;
                                                                case 'reagendada':
                                                                    $statusClass = 'badge bg-info';
                                                                    $statusText = 'Reagendada';
                                                                    break;
                                                                default:
                                                                    $statusClass = 'badge bg-secondary';
                                                                    $statusText = ucfirst($agendamento['status']);
                                                            }
                                                            ?>
                                                            <span class="<?= $statusClass ?>"><?= $statusText ?></span>
                                                        </td>
                                                        <td data-label="Ações">
                                                            <div class="btn-group" role="group" aria-label="Ações do agendamento">
                                                                <?php if ($agendamento['status'] === 'agendada'): 
                                                                    $labelEditar = sprintf('Editar agendamento de %s às %s', 
                                                                        date('d/m', strtotime($agendamento['data_aula'])), 
                                                                        date('H:i', strtotime($agendamento['hora_inicio'])));
                                                                    // Remover " - Aula X" do label de cancelar
                                                                    $nomeParaLabel = preg_replace('/ - Aula \d+$/', '', $agendamento['nome_aula']);
                                                                    $labelCancelar = sprintf('Cancelar agendamento de %s', $nomeParaLabel);
                                                                ?>
                                                                    <button type="button" 
                                                                            class="btn btn-sm btn-outline-primary" 
                                                                            onclick="editarAgendamento(<?= $agendamento['id'] ?>, '<?= htmlspecialchars($agendamento['nome_aula']) ?>', '<?= $agendamento['data_aula'] ?>', '<?= $agendamento['hora_inicio'] ?>', '<?= $agendamento['hora_fim'] ?>', '<?= $agendamento['instrutor_id'] ?>', '<?= $agendamento['sala_id'] ?? '' ?>', '<?= $agendamento['duracao_minutos'] ?>', '<?= htmlspecialchars($agendamento['observacoes'] ?? '') ?>')"
                                                                            title="<?= htmlspecialchars($labelEditar) ?>"
                                                                            aria-label="<?= htmlspecialchars($labelEditar) ?>"
                                                                            style="min-width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-width: 2px; font-weight: 500; background: white; border-color: #0d6efd; color: #0d6efd;">
                                                                        <i class="fas fa-pen" style="font-size: 12px;"></i>
                                                                    </button>
                                                                    <button type="button" 
                                                                            class="btn btn-sm btn-outline-secondary" 
                                                                            onclick="duplicarAgendamento(<?= $agendamento['id'] ?>)"
                                                                            title="Agendar semelhante – preenche automaticamente com os dados desta aula"
                                                                            aria-label="Agendar aula semelhante a partir desta"
                                                                            style="min-width: 32px; height: 32px;">
                                                                        <i class="fas fa-clone" style="font-size: 12px;"></i>
                                                                    </button>
                                                                    <button type="button" 
                                                                            class="btn btn-sm btn-outline-danger" 
                                                                            onclick="if(confirm('Tem certeza que deseja cancelar este agendamento? Esta ação não pode ser desfeita.')) cancelarAgendamento(<?= $agendamento['id'] ?>, '<?= htmlspecialchars($agendamento['nome_aula']) ?>')"
                                                                            title="<?= htmlspecialchars($labelCancelar) ?>"
                                                                            aria-label="<?= htmlspecialchars($labelCancelar) ?>">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <span class="text-muted small">Não editável</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="historico-agendamentos">
                                    <!-- Cabeçalho do Painel Expandido -->
                                    <div class="disciplina-panel-header">
                                        <h6 class="disciplina-panel-title">
                                            <i class="fas fa-calendar-alt"></i>
                                            Histórico de Agendamentos
                                        </h6>
                                        <div class="disciplina-panel-actions">
                                            <button type="button" 
                                                    class="btn btn-primary btn-sm" 
                                                    onclick="abrirModalAgendarAula('<?= $disciplinaId ?>', '<?= htmlspecialchars($disciplina['nome_disciplina'], ENT_QUOTES) ?>', '<?= htmlspecialchars($turma['data_inicio']) ?>', '<?= htmlspecialchars($turma['data_fim']) ?>')"
                                                    aria-label="Agendar primeira aula para <?= htmlspecialchars($disciplina['nome_disciplina']) ?>"
                                                    style="display: inline-flex; align-items: center; gap: 6px;">
                                                <i class="fas fa-plus"></i>
                                                Agendar Nova Aula
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Empty State -->
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-calendar-plus"></i>
                                        </div>
                                        <h5 class="empty-state-title">Sem agendamentos ainda</h5>
                                        <p class="empty-state-description">
                                            Esta disciplina ainda não possui aulas agendadas.<br>
                                            Clique no botão acima para agendar a primeira aula.
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Informação sobre disciplinas automáticas - Callout discreto -->
    <div style="background: #f8f9fa; border-left: 3px solid #6c757d; border-radius: 6px; padding: 14px 18px; margin-top: 30px; font-size: 13px; color: #495057;">
        <div style="display: flex; align-items: flex-start; gap: 10px;">
            <i class="fas fa-info-circle" style="color: #6c757d; margin-top: 2px; font-size: 14px;"></i>
            <div>
                <strong style="font-weight: 600; color: #343a40;">Sobre as disciplinas:</strong>
                <span style="color: #6c757d;">
                    As disciplinas são definidas automaticamente pelo tipo de curso selecionado. Os KPIs mostrados referem-se ao agendamento das aulas, não à presença dos alunos.
                </span>
            </div>
        </div>
    </div>
</div>

    </div>

    <!-- Aba Alunos -->
    <div id="tab-alunos" class="tab-content">
        <!-- Alunos Matriculados -->
        <div style="padding: 20px;" id="alunosMatriculadosWrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h4 style="color: #023A8D; margin: 0;">
            <i class="fas fa-users me-2"></i>Alunos Matriculados
        </h4>
        <div style="display: flex; gap: 10px; align-items: center;">
            <span id="total-alunos-matriculados-badge" style="background: #e3f2fd; color: #1976d2; padding: 6px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: 500;" data-total-alunos-label data-quantidade="<?= is_countable($alunosMatriculados ?? null) ? count($alunosMatriculados) : 0 ?>">
                <i class="fas fa-user-check me-1"></i>
                <?= is_countable($alunosMatriculados ?? null) ? count($alunosMatriculados) : 0 ?> aluno(s)
            </span>
            <button onclick="abrirModalInserirAlunos()" class="btn-primary" style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 6px; font-weight: 500; transition: all 0.3s; font-size: 0.9rem;">
                <i class="fas fa-user-plus"></i>
                Matricular Aluno
            </button>
        </div>
    </div>
    
    <div id="lista-alunos-matriculados">
    <?php if (!empty($alunosMatriculados)): ?>
        <div style="overflow-x: auto;">
            <table class="alunos-table" id="tabela-alunos-matriculados">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>Categoria</th>
                        <th>CFC</th>
                        <th style="text-align: center;">Status</th>
                        <th>Data Matrícula</th>
                        <th style="text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody id="tabela-alunos-matriculados-body">
                    <?php foreach ((array) $alunosMatriculados as $aluno): ?>
                        <tr data-aluno-id="<?= (int) ($aluno['aluno_id'] ?? 0) ?>">
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div class="aluno-avatar">
                                        <?= strtoupper(substr($aluno['nome'] ?? '', 0, 2)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: #2c3e50; margin-bottom: 2px;"><?= htmlspecialchars($aluno['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if (!empty($aluno['email'])): ?>
                                            <div style="font-size: 0.8rem; color: #6c757d;">
                                                <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($aluno['email'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($aluno['telefone'])): ?>
                                            <div style="font-size: 0.8rem; color: #6c757d;">
                                                <i class="fas fa-phone me-1"></i><?= htmlspecialchars($aluno['telefone'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="font-family: monospace; font-size: 0.9rem;">
                                <?= htmlspecialchars($aluno['cpf'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?php 
                                // Obter categoria priorizando matrícula ativa (usando helper centralizado)
                                $categoriaExibicao = obterCategoriaExibicao($aluno);
                                
                                // Se houver matrícula ativa, usar badge primário; caso contrário, secundário
                                $badgeClass = !empty($aluno['categoria_cnh_matricula']) ? 'bg-primary' : 'bg-secondary';
                                ?>
                                <span class="badge <?= $badgeClass ?>" style="padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;" title="Categoria CNH">
                                    <?= htmlspecialchars($categoriaExibicao, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: #495057; font-size: 0.9rem;">
                                    <?= htmlspecialchars($aluno['cfc_nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php
                                $statusClass = '';
                                $statusIcon = '';
                                $statusText = '';
                                
                                switch ($aluno['status']) {
                                    case 'matriculado':
                                        $statusClass = 'status-matriculado';
                                        $statusIcon = 'fas fa-user-check';
                                        $statusText = 'Matriculado';
                                        break;
                                    case 'cursando':
                                        $statusClass = 'status-cursando';
                                        $statusIcon = 'fas fa-graduation-cap';
                                        $statusText = 'Cursando';
                                        break;
                                    case 'evadido':
                                        $statusClass = 'status-matriculado';
                                        $statusIcon = 'fas fa-user-check';
                                        $statusText = 'Matriculado';
                                        break;
                                    case 'transferido':
                                        $statusClass = 'status-transferido';
                                        $statusIcon = 'fas fa-exchange-alt';
                                        $statusText = 'Transferido';
                                        break;
                                    case 'concluido':
                                        $statusClass = 'status-concluido';
                                        $statusIcon = 'fas fa-check-circle';
                                        $statusText = 'Concluído';
                                        break;
                                    default:
                                        $statusClass = 'status-badge';
                                        $statusIcon = 'fas fa-question-circle';
                                        $statusText = ucfirst($aluno['status']);
                                }
                                ?>
                                <span class="status-badge <?= $statusClass ?>">
                                    <i class="<?= $statusIcon ?>"></i>
                                    <?= $statusText ?>
                                </span>
                            </td>
                            <td style="font-size: 0.9rem; color: #6c757d;">
                                <i class="fas fa-calendar-alt me-1"></i>
                                <?= date('d/m/Y', strtotime($aluno['data_matricula'])) ?>
                                <div style="font-size: 0.8rem; color: #adb5bd;">
                                    <?= date('H:i', strtotime($aluno['data_matricula'])) ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <div class="action-buttons">
                                    <button
                                        data-role="remover-matricula"
                                        onclick="removerMatricula(<?= (int) ($aluno['aluno_id'] ?? 0) ?>, '<?= htmlspecialchars($aluno['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>')"
                                        class="action-btn action-btn-outline-danger"
                                        title="Remover da Turma">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px 20px; color: #6c757d;" data-empty-state-alunos>
            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
            <h5 style="margin-bottom: 10px; color: #495057;">Nenhum aluno matriculado</h5>
            <p style="margin-bottom: 20px;">Esta turma ainda não possui alunos matriculados.</p>
            <button onclick="abrirModalInserirAlunos()" class="btn-primary" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-weight: 500; transition: all 0.3s;">
                <i class="fas fa-user-plus"></i>
                Matricular Primeiro Aluno
            </button>
        </div>
    <?php endif; ?>
    </div>
</div>
    </div>

    <!-- Aba Calendário -->
    <div id="tab-calendario" class="tab-content">
        <?php
        // Calcular período da turma PRIMEIRO (antes de buscar aulas)
        $dataInicio = new DateTime($turma['data_inicio']);
        $dataFim = new DateTime($turma['data_fim']);
        
        // Obter primeiro domingo da semana que contém data_inicio
        // Isso garante que a semana completa seja exibida (incluindo dias antes de data_inicio)
        $primeiroDomingo = clone $dataInicio;
        $diaSemanaAtual = (int)$primeiroDomingo->format('w'); // 0 = domingo, 1 = segunda, etc.
        
        // Se não é domingo, voltar para o domingo anterior
        if ($diaSemanaAtual > 0) {
            $primeiroDomingo->modify('-' . $diaSemanaAtual . ' days');
        }
        
        // Garantir que não vá antes demais (só precisa da semana que contém data_inicio)
        if ($primeiroDomingo < $dataInicio && ($dataInicio->diff($primeiroDomingo)->days > 7)) {
            $primeiroDomingo = clone $dataInicio;
            $diaSemanaAtual = (int)$primeiroDomingo->format('w');
            if ($diaSemanaAtual > 0) {
                $primeiroDomingo->modify('-' . $diaSemanaAtual . ' days');
            }
        }
        
        // Calcular semanas disponíveis - INCLUIR TODOS OS DIAS DA SEMANA (mesmo fora do período)
        // Dias fora do período serão visíveis mas não clicáveis
        $todasSemanasTmp = [];
        $semanaAtual = clone $primeiroDomingo;
        
        // Calcular até a semana que contém data_fim
        $ultimaSemana = clone $dataFim;
        $diaSemanaUltima = (int)$ultimaSemana->format('w');
        if ($diaSemanaUltima > 0) {
            $ultimaSemana->modify('-' . $diaSemanaUltima . ' days');
        }
        // Garantir que vamos até pelo menos data_fim
        $dataFimParaLoop = clone $ultimaSemana;
        $dataFimParaLoop->modify('+6 days');
        
        while ($semanaAtual <= $dataFimParaLoop) {
            $semana = [];
            for ($dia = 0; $dia < 7; $dia++) {
                $diaSemana = clone $semanaAtual;
                $diaSemana->modify("+$dia days");
                // SEMPRE incluir o dia, mesmo se fora do período
                $semana[] = $diaSemana->format('Y-m-d');
            }
            $todasSemanasTmp[] = [
                'dias' => $semana,
                'inicio' => $semana[0] ? new DateTime($semana[0]) : null,
                'indice' => count($todasSemanasTmp)
            ];
            $semanaAtual->modify('+7 days');
        }
        
        // Semana selecionada - SEMPRE começar na semana que contém a data_inicio da turma
        $semanaSelecionada = isset($_GET['semana_calendario']) && $_GET['semana_calendario'] !== '' ? (int)$_GET['semana_calendario'] : null;
        
        // Se não foi especificada na URL, encontrar a semana que contém a data_inicio
        if ($semanaSelecionada === null) {
            $semanaSelecionada = 0;
            foreach ($todasSemanasTmp as $idx => $semana) {
                // Verificar se algum dia da semana contém a data_inicio
                foreach ($semana['dias'] as $dia) {
                    if ($dia && $dia === $dataInicio->format('Y-m-d')) {
                        $semanaSelecionada = $idx;
                        break 2; // Sair de ambos os loops
                    }
                }
                // Se não encontrou na semana atual, verificar se a data_inicio está entre o início e fim desta semana
                if ($semana['inicio']) {
                    $inicioSemana = $semana['inicio'];
                    $fimSemana = clone $inicioSemana;
                    $fimSemana->modify('+6 days');
                    
                    if ($dataInicio >= $inicioSemana && $dataInicio <= $fimSemana) {
                        $semanaSelecionada = $idx;
                        break;
                    }
                }
            }
        }
        
        if ($semanaSelecionada < 0 || $semanaSelecionada >= count($todasSemanasTmp)) {
            $semanaSelecionada = 0;
        }
        
        $semanaDisplay = $todasSemanasTmp[$semanaSelecionada] ?? $todasSemanasTmp[0];
        
        // Buscar TODAS as aulas do período da turma (não apenas da semana selecionada)
        // Isso permite visualizar e navegar por todo o período disponível
        try {
            // Primeiro, buscar todas as aulas da turma (sem filtro de período para debug)
            $todasAulasSemFiltro = $db->fetchAll(
                "SELECT COUNT(*) as total FROM turma_aulas_agendadas WHERE turma_id = ?",
                [$turmaId]
            );
            $totalAulasSemFiltro = $todasAulasSemFiltro[0]['total'] ?? 0;
            
            // Buscar aulas com filtros
            $todasAulasCalendario = $db->fetchAll(
                "SELECT 
                    taa.*,
                    taa.disciplina as disciplina_id,
                    COALESCE(u.nome, i.nome, 'Não informado') as instrutor_nome,
                    COALESCE(s.nome, 'Sala não definida') as sala_nome
                 FROM turma_aulas_agendadas taa
                 LEFT JOIN instrutores i ON taa.instrutor_id = i.id
                 LEFT JOIN usuarios u ON i.usuario_id = u.id
                 LEFT JOIN salas s ON taa.sala_id = s.id
                 WHERE taa.turma_id = ? 
                 AND (taa.status IS NULL OR taa.status = '' OR taa.status = 'agendada' OR taa.status != 'cancelada')
                 AND taa.data_aula >= ?
                 AND taa.data_aula <= ?
                 ORDER BY taa.data_aula ASC, taa.hora_inicio ASC",
                [$turmaId, $turma['data_inicio'], $turma['data_fim']]
            );
            
            // Log para debug
            error_log("Calendário Turma $turmaId: Total de aulas sem filtro: $totalAulasSemFiltro, Com filtros: " . count($todasAulasCalendario));
            
            // Se não encontrou aulas com filtros mas há aulas sem filtro, buscar todas (pode estar fora do período)
            if (count($todasAulasCalendario) == 0 && $totalAulasSemFiltro > 0) {
                error_log("Calendário Turma $turmaId: Nenhuma aula encontrada com filtros, buscando todas as aulas...");
                $todasAulasCalendario = $db->fetchAll(
                    "SELECT 
                        taa.*,
                        taa.disciplina as disciplina_id,
                        COALESCE(u.nome, i.nome, 'Não informado') as instrutor_nome,
                        COALESCE(s.nome, 'Sala não definida') as sala_nome
                     FROM turma_aulas_agendadas taa
                     LEFT JOIN instrutores i ON taa.instrutor_id = i.id
                     LEFT JOIN usuarios u ON i.usuario_id = u.id
                     LEFT JOIN salas s ON taa.sala_id = s.id
                     WHERE taa.turma_id = ? 
                     AND (taa.status IS NULL OR taa.status = '' OR taa.status = 'agendada' OR taa.status != 'cancelada')
                     ORDER BY taa.data_aula ASC, taa.hora_inicio ASC",
                    [$turmaId]
                );
                error_log("Calendário Turma $turmaId: Encontradas " . count($todasAulasCalendario) . " aulas sem filtro de período");
            }
            
            // Definir totalAulas aqui para uso posterior
            $totalAulas = count($todasAulasCalendario);
        } catch (Exception $e) {
            error_log("Erro ao buscar aulas para calendário: " . $e->getMessage());
            $todasAulasCalendario = [];
            $totalAulas = 0;
        }
        
        // Adicionar nome da disciplina baseado nas disciplinas selecionadas
        $disciplinasMap = [];
        foreach ($disciplinasSelecionadas as $disc) {
            $disciplinasMap[$disc['disciplina_id']] = $disc['nome_disciplina'] ?? $disc['nome_original'] ?? 'Disciplina';
        }
        
        // IMPORTANTE: Não usar referência (&$aula) para evitar modificar o array original
        // Criar uma cópia do array para modificações
        $todasAulasCalendarioModificadas = [];
        foreach ($todasAulasCalendario as $aula) {
            $aula['nome_disciplina'] = $disciplinasMap[$aula['disciplina_id']] ?? 'Disciplina';
            $todasAulasCalendarioModificadas[] = $aula;
        }
        $todasAulasCalendario = $todasAulasCalendarioModificadas;
        
        // Organizar aulas por data e disciplina
        $aulasPorData = [];
        $ultimaAulaPorDisciplina = [];
        
        // Debug: verificar todas as aulas antes de organizar
        error_log("Calendário Turma $turmaId: Total de aulas para organizar: " . count($todasAulasCalendario));
        foreach ($todasAulasCalendario as $idx => $aulaDebug) {
            error_log("  Aula " . ($idx + 1) . ": ID=" . ($aulaDebug['id'] ?? 'N/A') . ", Data=" . ($aulaDebug['data_aula'] ?? 'N/A') . ", Inicio=" . ($aulaDebug['hora_inicio'] ?? 'N/A') . ", Fim=" . ($aulaDebug['hora_fim'] ?? 'N/A'));
        }
        
        foreach ($todasAulasCalendario as $aula) {
            $data = $aula['data_aula'];
            
            // Verificar se data está vazia ou nula
            if (empty($data)) {
                error_log("Aula sem data encontrada: ID " . ($aula['id'] ?? 'N/A'));
                continue;
            }
            
            // Normalizar formato de data (pode ser Y-m-d ou Y/m/d)
            if (strpos($data, '/') !== false) {
                $dataParts = explode('/', $data);
                if (count($dataParts) == 3) {
                    // Assumir formato d/m/Y ou Y/m/d baseado no tamanho do primeiro segmento
                    if (strlen($dataParts[2]) == 4) {
                        // Formato d/m/Y ou Y/m/d
                        if (strlen($dataParts[0]) == 4) {
                            // Y/m/d
                            $data = $dataParts[0] . '-' . str_pad($dataParts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($dataParts[2], 2, '0', STR_PAD_LEFT);
                        } else {
                            // d/m/Y
                            $data = $dataParts[2] . '-' . str_pad($dataParts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($dataParts[0], 2, '0', STR_PAD_LEFT);
                        }
                    } else {
                        // Formato desconhecido, tentar converter como d/m/Y
                        $data = $dataParts[2] . '-' . str_pad($dataParts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($dataParts[0], 2, '0', STR_PAD_LEFT);
                    }
                }
            } else {
                // Já está em formato Y-m-d, garantir que está correto
                $dataParts = explode('-', $data);
                if (count($dataParts) == 3) {
                    $data = $dataParts[0] . '-' . str_pad($dataParts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($dataParts[2], 2, '0', STR_PAD_LEFT);
                }
            }
            
            $disciplinaId = $aula['disciplina_id'] ?? null;
            
            if ($disciplinaId === null) {
                error_log("Aula sem disciplina_id: ID " . ($aula['id'] ?? 'N/A'));
                continue;
            }
            
            if (!isset($aulasPorData[$data])) {
                $aulasPorData[$data] = [];
            }
            if (!isset($aulasPorData[$data][$disciplinaId])) {
                $aulasPorData[$data][$disciplinaId] = [];
            }
            $aulasPorData[$data][$disciplinaId][] = $aula;
            
            // Identificar última aula por disciplina (para destacar)
            if (!isset($ultimaAulaPorDisciplina[$disciplinaId]) || 
                strtotime($aula['data_aula'] . ' ' . $aula['hora_fim']) > strtotime($ultimaAulaPorDisciplina[$disciplinaId]['data_aula'] . ' ' . $ultimaAulaPorDisciplina[$disciplinaId]['hora_fim'])) {
                $ultimaAulaPorDisciplina[$disciplinaId] = $aula;
            }
        }
        
        // Definir cores por disciplina utilizando a paleta centralizada
        $coresDisciplinas = [];
        $coresFundoDisciplinas = [];
        foreach ($paletaCoresDisciplinas as $slugDisciplina => $coresDisciplina) {
            $coresDisciplinas[$slugDisciplina] = $coresDisciplina['base'];
            $coresFundoDisciplinas[$slugDisciplina] = $coresDisciplina['fundo'];
        }
        
        // Calcular horários dinamicamente baseado nas aulas agendadas
        // Encontrar a menor hora e maior hora das aulas para criar timeline
        $horaMinima = null;
        $horaMaxima = null;
        
        foreach ($todasAulasCalendario as $aula) {
            // Normalizar formato de hora (pode ser HH:MM ou HH:MM:SS)
            $horaInicioStr = $aula['hora_inicio'];
            $horaFimStr = $aula['hora_fim'];
            
            if (strlen($horaInicioStr) == 8) {
                $horaInicioStr = substr($horaInicioStr, 0, 5);
            }
            if (strlen($horaFimStr) == 8) {
                $horaFimStr = substr($horaFimStr, 0, 5);
            }
            
            // Converter para minutos
            list($horaInicio, $minInicio) = explode(':', $horaInicioStr);
            list($horaFim, $minFim) = explode(':', $horaFimStr);
            
            $horaMinMinutos = (int)$horaInicio * 60 + (int)$minInicio;
            $horaMaxMinutos = (int)$horaFim * 60 + (int)$minFim;
            
            if ($horaMinima === null || $horaMinMinutos < $horaMinima) {
                $horaMinima = $horaMinMinutos;
            }
            if ($horaMaxima === null || $horaMaxMinutos > $horaMaxima) {
                $horaMaxima = $horaMaxMinutos;
            }
        }
        
        // Sempre mostrar timeline completa (Manhã, Tarde, Noite) para facilitar agendamento
        // Independente de ter aulas, mostrar todo o período útil
        if ($horaMinima === null || count($todasAulasCalendario) == 0) {
            // Sem aulas: timeline completa mas compacta
            $horaMinima = 6 * 60; // Sempre começar às 06:00 (Manhã)
            $horaMaxima = 23 * 60; // Sempre terminar às 23:00 (Noite)
        } else {
            // Com aulas: sempre mostrar timeline completa, mas ajustar range se necessário
            // Sempre incluir todos os períodos (Manhã, Tarde, Noite)
            $horaMinima = 6 * 60; // Sempre começar às 06:00 para mostrar Manhã
            $horaMaxima = 23 * 60; // Sempre terminar às 23:00 para mostrar Noite completa
            
            // Mas garantir que se há aulas fora deste range, expandir
            foreach ($todasAulasCalendario as $aula) {
                $horaInicioStr = $aula['hora_inicio'];
                $horaFimStr = $aula['hora_fim'];
                
                if (strlen($horaInicioStr) == 8) {
                    $horaInicioStr = substr($horaInicioStr, 0, 5);
                }
                if (strlen($horaFimStr) == 8) {
                    $horaFimStr = substr($horaFimStr, 0, 5);
                }
                
                list($horaInicio, $minInicio) = explode(':', $horaInicioStr);
                list($horaFim, $minFim) = explode(':', $horaFimStr);
                
                $horaMinMinutos = (int)$horaInicio * 60 + (int)$minInicio;
                $horaMaxMinutos = (int)$horaFim * 60 + (int)$minFim;
                
                // Não reduzir $horaMinima abaixo de 6:00 nem aumentar $horaMaxima acima de 23:00
                // Mas garantir que todas as aulas estejam visíveis
                if ($horaMinMinutos < 6 * 60) {
                    $horaMinima = min(6 * 60, $horaMinMinutos - 30);
                }
                if ($horaMaxMinutos > 23 * 60) {
                    $horaMaxima = max(23 * 60, $horaMaxMinutos); // Sem margem extra - terminar exatamente quando necessário
                }
            }
        }
        
        // Definir períodos (Manhã, Tarde, Noite)
        // IMPORTANTE: O período "Noite" será ajustado dinamicamente para incluir todas as aulas
        $periodos = [
            'Manhã' => ['inicio' => 6 * 60, 'fim' => 12 * 60, 'colapsado' => false],
            'Tarde' => ['inicio' => 12 * 60, 'fim' => 18 * 60, 'colapsado' => false],
            'Noite' => ['inicio' => 18 * 60, 'fim' => 24 * 60, 'colapsado' => false] // Estender até 24:00 para incluir aulas até 23:00+
        ];
        // Ajustar período "Noite" dinamicamente se houver aulas após 23:00
        if (!empty($todasAulasCalendario)) {
            $ultimaHoraFimPeriodo = 0;
            foreach ($todasAulasCalendario as $aula) {
                $horaFimStr = $aula['hora_fim'];
                if (strlen($horaFimStr) == 8) {
                    $horaFimStr = substr($horaFimStr, 0, 5);
                }
                list($horaFim, $minFim) = explode(':', $horaFimStr);
                $horaFimMinutos = (int)$horaFim * 60 + (int)$minFim;
                if ($horaFimMinutos > $ultimaHoraFimPeriodo) {
                    $ultimaHoraFimPeriodo = $horaFimMinutos;
                }
            }
            // Ajustar fim do período Noite para incluir todas as aulas + margem
            if ($ultimaHoraFimPeriodo > 18 * 60) {
                $periodos['Noite']['fim'] = max(23 * 60, $ultimaHoraFimPeriodo + 60);
            }
        }
        
        // SIMPLIFICADO: NUNCA colapsar períodos automaticamente
        // Isso estava causando problemas de renderização
        // Períodos sempre expandidos por padrão - usuário pode colapsar manualmente se quiser
        foreach ($periodos as $nomePeriodo => &$periodo) {
            $periodo['colapsado'] = false;
        }
        
        // Gerar timeline com intervalos de 30 minutos (mais granular que 50min fixo)
        $horariosTimeline = [];
        $horaAtual = $horaMinima;
        while ($horaAtual <= $horaMaxima) {
            $horas = floor($horaAtual / 60);
            $minutos = $horaAtual % 60;
            $horariosTimeline[] = sprintf('%02d:%02d', $horas, $minutos);
            $horaAtual += 30; // Intervalos de 30 minutos
        }
        
        // Manter array de horários para compatibilidade (será usado apenas como referência)
        $horarios = $horariosTimeline;
        
        // Usar as semanas já calculadas acima
        $todasSemanas = $todasSemanasTmp;
        
        // JavaScript para armazenar semanas disponíveis
        $semanasJson = json_encode(array_map(function($s) {
            return [
                'dias' => $s['dias'],
                'inicio' => $s['inicio'] ? $s['inicio']->format('Y-m-d') : null,
                'indice' => $s['indice']
            ];
        }, $todasSemanas));
        ?>
        <div style="padding: 4px 8px;">
            <!-- Cabeçalho compacto estilo Google Calendar -->
            <!-- CRÍTICO: Container flex que agrupa TODOS os 5 itens lado a lado -->
            <!-- FALLBACK: Espaçamento aplicado diretamente nos filhos para garantir respiro mesmo se gap for anulado -->
            <div class="google-calendar-header" style="display: flex !important; align-items: center; margin-bottom: 8px; flex-wrap: wrap; column-gap: 14px; row-gap: 6px; padding: 6px 0;">
                <!-- CRÍTICO: Todos os 5 itens no mesmo nível do container flex -->
                <!-- FALLBACK: margin-right para garantir espaçamento mesmo se gap for anulado -->
                <h3 style="margin: 0 14px 0 0 !important; color: #202124; font-size: 18px; font-weight: 400; font-family: 'Google Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.2; flex-shrink: 0;">
                    Calendário
                </h3>
                <!-- Badge compacto inline -->
                <div style="background: #d1ecf1; border-left: 3px solid #17a2b8; padding: 4px 10px; border-radius: 4px; display: inline-flex; align-items: center; height: 24px; flex-shrink: 0; margin-right: 14px !important;">
                    <strong style="color: #0c5460; font-size: 0.8rem; font-weight: 500; line-height: 1;">
                        ✅ <span id="total-aulas-semana"><?= $totalAulas ?></span> aulas
                    </strong>
                </div>
                <!-- Botão Hoje compacto -->
                <button id="btn-hoje" onclick="irParaHoje()" style="background: #1a73e8; color: white; border: none; border-radius: 16px; padding: 6px 16px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 2px rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15); height: 28px; line-height: 1; flex-shrink: 0; margin-right: 14px !important;" onmouseover="this.style.background='#1765cc'; this.style.boxShadow='0 1px 3px rgba(60,64,67,0.3), 0 4px 8px 3px rgba(60,64,67,0.15)'" onmouseout="this.style.background='#1a73e8'; this.style.boxShadow='0 1px 2px rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15)'">
                    Hoje
                </button>
                <!-- Navegação compacta -->
                <div style="display: flex; align-items: center; gap: 0; border: 1px solid #dadce0; border-radius: 4px; overflow: hidden; flex-shrink: 0; margin-right: 14px !important;">
                    <button id="btn-semana-anterior" onclick="mudarSemana(-1)" style="background: white; border: none; padding: 6px 10px; cursor: pointer; transition: background 0.15s; color: #5f6368; height: 28px; display: flex; align-items: center;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">
                        <i class="fas fa-chevron-left" style="font-size: 11px;"></i>
                    </button>
                    <div style="border-left: 1px solid #dadce0; border-right: 1px solid #dadce0; padding: 0 10px; height: 28px; display: flex; align-items: center;">
                        <span id="info-semana-atual" style="font-size: 13px; font-weight: 400; color: #202124; white-space: nowrap; line-height: 1;">
                            <?php 
                            $semanaDisplay = $todasSemanas[$semanaSelecionada] ?? $todasSemanas[0];
                            $totalSemanas = count($todasSemanas);
                            if ($semanaDisplay['inicio']):
                                $inicioSemana = $semanaDisplay['inicio'];
                                $fimSemana = clone $inicioSemana;
                                $fimSemana->modify('+6 days');
                                $meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
                                echo $inicioSemana->format('j') . ' - ' . $fimSemana->format('j') . ' de ' . $meses[$fimSemana->format('n') - 1] . ', ' . $fimSemana->format('Y');
                            else:
                                echo 'Carregando...';
                            endif;
                            ?>
                        </span>
                    </div>
                    <button id="btn-semana-proxima" onclick="mudarSemana(1)" style="background: white; border: none; padding: 6px 10px; cursor: pointer; transition: background 0.15s; color: #5f6368; height: 28px; display: flex; align-items: center;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">
                        <i class="fas fa-chevron-right" style="font-size: 11px;"></i>
                    </button>
                </div>
                <!-- Filtro compacto -->
                <select id="filtro-disciplina-calendario" style="padding: 6px 28px 6px 10px; border: 1px solid #dadce0; border-radius: 4px; font-size: 13px; background: white; color: #202124; cursor: pointer; appearance: none; background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%235f6368' d='M6 9L1 4h10z'/%3E%3C/svg%3E\"); background-repeat: no-repeat; background-position: right 10px center; height: 28px; line-height: 1; flex-shrink: 0; margin-left: auto !important; margin-right: 0 !important;" onchange="filtrarCalendario()">
                    <option value="all">Todas as disciplinas</option>
                    <?php foreach ($disciplinasSelecionadas as $disc): ?>
                        <option value="<?= $disc['disciplina_id'] ?>"><?= htmlspecialchars($disc['nome_disciplina'] ?? $disc['nome_original'] ?? 'Disciplina') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <input type="hidden" id="semana-atual-indice" value="<?= $semanaSelecionada ?>">
            <input type="hidden" id="semanas-disponiveis" value='<?= htmlspecialchars($semanasJson) ?>'>
            <input type="hidden" id="turma-data-inicio" value="<?= $turma['data_inicio'] ?>">
            <input type="hidden" id="turma-data-fim" value="<?= $turma['data_fim'] ?>">
            <script>
            // Garantir que a URL reflita a semana correta calculada pelo servidor
            (function() {
                const url = new URL(window.location);
                const semanaNaUrl = url.searchParams.get('semana_calendario');
                const semanaCalculada = <?= $semanaSelecionada ?>;
                
                // Se não há parâmetro na URL ou está diferente do calculado, atualizar
                if (!semanaNaUrl || parseInt(semanaNaUrl) !== semanaCalculada) {
                    url.searchParams.set('semana_calendario', semanaCalculada.toString());
                    window.history.replaceState({}, '', url);
                }
            })();
            </script>
            
            <!-- Dados para JavaScript (todas as aulas para atualização dinâmica) -->
            <script type="application/json" id="dados-calendario">
            <?= json_encode([
                'aulasPorData' => $aulasPorData,
                'ultimaAulaPorDisciplina' => array_map(function($a) {
                    return ['id' => $a['id'], 'disciplina_id' => $a['disciplina_id']];
                }, $ultimaAulaPorDisciplina),
                'coresDisciplinas' => $coresDisciplinas,
                'disciplinasMap' => $disciplinasMap,
                'horarios' => $horarios,
                'horaMinima' => $horaMinima,
                'horaMaxima' => $horaMaxima
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            </script>
            
            <!-- Debug Info (Temporário) -->
            <?php 
            // $totalAulas já foi definido acima, mas garantir que existe
            if (!isset($totalAulas)) {
                $totalAulas = count($todasAulasCalendario ?? []);
            }
            
            // Buscar total de aulas no período completo (para estatísticas)
            try {
                $totalAulasPeriodo = $db->fetch(
                    "SELECT COUNT(*) as total FROM turma_aulas_agendadas 
                     WHERE turma_id = ? 
                     AND (status IS NULL OR status != 'cancelada')
                     AND data_aula >= ? AND data_aula <= ?",
                    [$turmaId, $turma['data_inicio'], $turma['data_fim']]
                );
                $totalGeral = $totalAulasPeriodo['total'] ?? 0;
            } catch (Exception $e) {
                $totalGeral = $totalAulas;
            }
            
            // Coletar informações de debug sobre as datas
            $debugDatas = [];
            $debugDatasDisponiveis = array_keys($aulasPorData ?? []);
            foreach ($semanaDisplay['dias'] as $idx => $data) {
                if ($data) {
                    $aulasDia = 0;
                    // Buscar aulas deste dia (tentar formatos diferentes)
                    $formatosData = [$data];
                    if (strpos($data, '-') !== false) {
                        $parts = explode('-', $data);
                        $formatosData[] = $parts[2] . '/' . $parts[1] . '/' . $parts[0]; // d/m/Y
                        $formatosData[] = $parts[0] . '/' . $parts[1] . '/' . $parts[2]; // Y/m/d
                    }
                    
                    foreach ($formatosData as $dataBusca) {
                        if (isset($aulasPorData[$dataBusca])) {
                            foreach ($aulasPorData[$dataBusca] as $discId => $aulas) {
                                $aulasDia += count($aulas);
                            }
                        }
                    }
                    
                    // Se ainda não encontrou, buscar em todas as datas e comparar
                    if ($aulasDia == 0 && !empty($aulasPorData)) {
                        foreach ($aulasPorData as $dataKey => $disciplinas) {
                            // Normalizar ambas as datas para comparação
                            $dataNormalizada = $data;
                            $dataKeyNormalizada = $dataKey;
                            
                            // Normalizar data da semana
                            if (strpos($dataNormalizada, '-') !== false) {
                                $parts = explode('-', $dataNormalizada);
                                $dataNormalizada = $parts[0] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[2], 2, '0', STR_PAD_LEFT);
                            }
                            
                            // Normalizar data key
                            if (strpos($dataKey, '/') !== false) {
                                $parts = explode('/', $dataKey);
                                if (count($parts) == 3) {
                                    if (strlen($parts[2]) == 4) {
                                        if (strlen($parts[0]) == 4) {
                                            $dataKeyNormalizada = $parts[0] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[2], 2, '0', STR_PAD_LEFT);
                                        } else {
                                            $dataKeyNormalizada = $parts[2] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                                        }
                                    }
                                }
                            } else {
                                $parts = explode('-', $dataKey);
                                if (count($parts) == 3) {
                                    $dataKeyNormalizada = $parts[0] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[2], 2, '0', STR_PAD_LEFT);
                                }
                            }
                            
                            if ($dataNormalizada == $dataKeyNormalizada) {
                                foreach ($disciplinas as $discId => $aulas) {
                                    $aulasDia += count($aulas);
                                }
                            }
                        }
                    }
                    
                    $debugDatas[] = "$data: $aulasDia aulas";
                }
            }
            
            ?>
            
            <!-- Calendário Estilo Google Calendar -->
            <style>
            /* Container principal - estilo Google Calendar - altura máxima da viewport */
            .timeline-calendar {
                position: relative;
                background: #ffffff;
                border-radius: 8px;
                overflow-x: hidden;
                overflow-y: auto;
                /* CRÍTICO: Reservar espaço do scrollbar sempre para alinhar header e body */
                /* O scrollbar aparece neste container, então scrollbar-gutter deve estar aqui */
                scrollbar-gutter: stable both-edges;
                box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
                font-family: 'Google Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                display: flex;
                flex-direction: column;
                /* CRÍTICO: Calcular altura máxima baseada na viewport menos elementos superiores */
                height: calc(100vh - 180px);
                min-height: calc(100vh - 180px);
                max-height: calc(100vh - 180px);
                padding-bottom: 0 !important;
                margin-bottom: 0 !important;
                border-bottom: none !important;
                /* CRÍTICO: Padding zero para não afetar alinhamento */
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            
            /* CRÍTICO: Zerar padding-bottom e min-height do wrapper externo */
            #calendario-container {
                padding-bottom: 0 !important;
                margin-bottom: 0 !important;
                min-height: auto !important;
            }
            
            /* CRÍTICO: Remover pseudo-elementos do wrapper externo que podem criar faixa */
            #calendario-container::after,
            #calendario-container::before,
            .timeline-calendar::after,
            .timeline-calendar::before {
                display: none !important;
                content: none !important;
                height: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            /* Remover borda inferior do corpo do calendário e elementos relacionados */
            .timeline-body,
            .timeline-body *,
            .timeline-day-column,
            .timeline-hour-marker,
            .timeline-slot {
                border-bottom: none !important;
                padding-bottom: 0 !important;
                margin-bottom: 0 !important;
            }
            
            /* Remover borda inferior do último elemento */
            .timeline-calendar > *:last-child {
                border-bottom: none !important;
                padding-bottom: 0 !important;
                margin-bottom: 0 !important;
            }
            
            .timeline-body > *:last-child {
                border-bottom: none !important;
                padding-bottom: 0 !important;
                margin-bottom: 0 !important;
            }
            
            /* Garantir que não há faixa branca na parte inferior bloqueando horários */
            #calendario-container,
            #calendario-container > * {
                padding-bottom: 0 !important;
                margin-bottom: 0 !important;
            }
            
            .timeline-day-column:last-child {
                border-bottom: none !important;
            }
            
            .timeline-hour-marker:last-child {
                border-bottom: none !important;
            }
            
            
            /* Cabeçalho estilo Google Calendar - mais limpo e moderno */
            /* CRÍTICO: Grid unificado com body para alinhamento perfeito */
            .timeline-header {
                display: grid !important;
                grid-template-columns: 80px repeat(7, minmax(0, 1fr)) !important;
                column-gap: 0 !important;
                box-sizing: border-box !important;
                background: #ffffff;
                color: #3c4043;
                border-bottom: 1px solid #dadce0;
                /* CRÍTICO: Sem bordas verticais no header - bordas ficam apenas no body */
                border-left: none !important;
                border-right: none !important;
                border-top: none !important;
                min-height: 36px;
                height: 36px;
                position: sticky;
                top: 0;
                z-index: 50;
                box-shadow: 0 1px 2px rgba(0,0,0,0.08);
                /* CRÍTICO: Padding zero para evitar desalinhamento */
                padding: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                padding-top: 0 !important;
                padding-bottom: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            
            .timeline-time-column {
                background: #ffffff;
                color: #3c4043;
                border-right: 1px solid #dadce0;
                position: sticky;
                left: 0;
                z-index: 51;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 500;
                font-size: 12px;
                box-sizing: border-box !important;
                width: 80px !important;
                min-width: 80px !important;
                max-width: 80px !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            
            .timeline-day-header {
                /* CRÍTICO: Padding ZERO para evitar desalinhamento - era padding: 2px que causava o problema */
                padding: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                padding-top: 0 !important;
                padding-bottom: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                text-align: center;
                font-weight: 500;
                font-size: 10px;
                color: #5f6368;
                /* CRÍTICO: Bordas apenas do 2º ao 7º item para evitar duplicidade */
                border-left: none !important;
                border-right: none !important;
                border-top: none !important;
                border-bottom: none !important;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 36px;
                height: 36px;
                box-sizing: border-box !important;
            }
            
            /* CRÍTICO: Header NÃO tem bordas verticais - bordas ficam apenas no body para evitar desalinhamento */
            /* As linhas verticais são desenhadas apenas no .timeline-day-column do body */
            
            .timeline-day-header .dia-nome {
                display: block;
                font-size: 8px;
                font-weight: 500;
                color: #5f6368;
                margin-bottom: 1px;
                text-transform: uppercase;
                letter-spacing: 0.3px;
                line-height: 1;
            }
            
            .timeline-day-header .dia-data {
                display: block;
                font-size: 16px;
                font-weight: 400;
                color: #3c4043;
                line-height: 1;
                margin-top: 0;
            }
            
            /* Destacar dia atual */
            .timeline-day-header.hoje .dia-data {
                color: #1a73e8;
                font-weight: 500;
            }
            
            .timeline-day-header.hoje {
                background: #f1f3f4;
            }
            
            /* Indicar visualmente datas fora do período */
            .timeline-day-header.dia-fora-periodo {
                opacity: 0.5;
            }
            
            .timeline-day-header.dia-fora-periodo .dia-data {
                opacity: 0.4;
            }
            
            /* Container externo do calendário */
            #calendario-container {
                padding-bottom: 0;
                margin-bottom: 0;
                overflow: visible;
                border-bottom: none !important;
            }
            
            /* Remover borda inferior do container pai */
            #tab-calendario > div,
            #tab-calendario > div > div {
                border-bottom: none !important;
            }
            
            /* Remover qualquer borda inferior do container com padding */
            #tab-calendario div[style*="padding: 20px"],
            #tab-calendario div[style*="padding:20px"] {
                border-bottom: none !important;
            }
            
            /* Corpo do calendário - altura automática baseada no conteúdo */
            /* CRÍTICO: Grid unificado com header para alinhamento perfeito */
            .timeline-body {
                display: grid !important;
                grid-template-columns: 80px repeat(7, minmax(0, 1fr)) !important;
                column-gap: 0 !important;
                box-sizing: border-box !important;
                position: relative;
                align-items: start;
                background: #ffffff;
                flex: 1;
                overflow-x: hidden;
                overflow-y: visible;
                padding-bottom: 0 !important;
                margin-bottom: 0 !important;
                border-bottom: none !important;
                /* CRÍTICO: Padding zero para evitar desalinhamento */
                padding-left: 0 !important;
                padding-right: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                /* CRÍTICO: Remover min-height padrão - será definido dinamicamente */
                min-height: auto !important;
                /* CRÍTICO: Garantir que body não tenha scrollbar próprio - o scroll está no .timeline-calendar */
            }
            
            /* CRÍTICO: Não sobrescrever altura definida dinamicamente via JavaScript */
            /* Remover estas regras que podem conflitar com o ajuste dinâmico */
            /* .timeline-body[style*="height"] {
                height: auto !important;
            }
            
            .timeline-body[style*="min-height"] {
                min-height: fit-content !important;
            } */
            /* CRÍTICO: Limitar altura dos pseudo-elementos ao conteúdo real renderizado */
            /* Garantir que ::after e ::before não criem faixa extra abaixo de 23:00 */
            .timeline-hours::after,
            .timeline-day-column::before {
                /* A altura será limitada pelo container pai que não deve ultrapassar o último slot */
                /* Se o container tem min-height, os pseudo-elementos devem respeitar isso */
                /* Mas não devem criar espaço extra além do último slot renderizado */
            }
            
            /* Garantir que o último slot (23:00) seja o último elemento visível */
            .timeline-hour-marker:last-child {
                margin-bottom: 0 !important;
                padding-bottom: 0 !important;
            }
            
            /* Garantir que não há espaço extra após o último slot */
            .timeline-hours:has(.timeline-hour-marker:last-child)::after {
                /* Limitar altura do ::after ao último marcador de hora */
                height: calc(100% - 0px) !important;
                max-height: calc(100% - 0px) !important;
            }
            
            .timeline-day-column:has(.timeline-slot:last-child)::before {
                /* Limitar altura do ::before ao último slot */
                height: calc(100% - 0px) !important;
                max-height: calc(100% - 0px) !important;
            }
            
            /* Coluna de horários estilo Google Calendar */
            .timeline-hours {
                position: sticky;
                left: 0;
                z-index: 5;
                background: #ffffff;
                border-right: 1px solid #dadce0;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                box-sizing: border-box !important;
                width: 80px !important;
                min-width: 80px !important;
                max-width: 80px !important;
                /* CRÍTICO: Zerar padding e margin para evitar espaço extra */
                padding-bottom: 0 !important;
                margin-bottom: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                min-height: auto !important;
            }
            
            /* CRÍTICO: Não sobrescrever altura definida dinamicamente via JavaScript */
            /* Remover esta regra que pode conflitar com o ajuste dinâmico */
            /* .timeline-hours[style*="height"] {
                height: auto !important;
            } */
            /* Linhas horizontais na coluna de horários - estilo Google Calendar */
            /* CRÍTICO: CSS permanente - aplicado sempre, não depende de JS */
            .timeline-hours::after {
                content: '' !important;
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                /* CRÍTICO: Não usar bottom: 0 - isso cria faixa extra */
                /* A altura será ajustada dinamicamente via JavaScript baseada no último slot renderizado */
                /* Usar height: 100% inicialmente, mas será sobrescrito pelo JS */
                height: 100% !important;
                width: 100% !important;
                /* Linhas muito sutis estilo Google Calendar - removendo última linha */
                background-image: 
                    repeating-linear-gradient(
                        to bottom,
                        transparent,
                        transparent 49px,
                        #dadce0 49px,
                        #dadce0 50px
                    ) !important;
                /* Ajustar background-size para não ultrapassar o conteúdo real */
                background-size: 100% calc(100% - 1px) !important;
                background-position: top !important;
                background-repeat: no-repeat !important;
                pointer-events: none !important;
                z-index: 1 !important;
                border-bottom: none !important;
                /* Garantir que não ultrapasse o último slot (23:00) */
                overflow: hidden !important;
                display: block !important;
            }
            
            .timeline-hour-marker {
                position: relative;
                height: 50px;
                display: flex;
                align-items: flex-start;
                box-sizing: border-box;
                z-index: 2;
            }
            
            .timeline-hour-label {
                position: absolute;
                top: -5px;
                left: 6px;
                font-size: 9px;
                color: #5f6368;
                font-weight: 400;
                background: #ffffff;
                padding: 0 3px;
            }
            
            .timeline-period-label {
                display: none; /* Removido para design mais limpo */
            }
            
            /* Colunas dos dias - estilo Google Calendar */
            .timeline-day-column {
                position: relative;
                /* CRÍTICO: Bordas apenas do 2º ao 7º item para evitar duplicidade e alinhar com header */
                border-left: none !important;
                border-right: none !important;
                border-top: none !important;
                border-bottom: none !important;
                background: #ffffff;
                overflow: visible;
                box-sizing: border-box !important;
                /* CRÍTICO: Zerar padding e margin para evitar espaço extra */
                padding-bottom: 0 !important;
                margin-bottom: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                min-height: auto !important;
            }
            
            /* CRÍTICO: Aplicar borda esquerda apenas do 2º ao 7º item (primeira coluna sem borda) */
            /* CSS permanente - não depende de JS */
            .timeline-day-column:not(:first-child) {
                border-left: 1px solid #dadce0 !important;
            }
            
            .timeline-day-column:last-child {
                border-right: none !important;
            }
            
            /* CRÍTICO: Linhas horizontais permanentes nos slots (CSS puro) */
            .timeline-slot {
                border-top: 1px solid #dadce0 !important;
            }
            
            .timeline-slot:first-child {
                border-top: none !important;
            }
            
            /* CRÍTICO: Zerar margin-bottom do último filho da grade */
            .timeline-hour-marker:last-child,
            .timeline-day-column:last-child,
            .timeline-body > *:last-child {
                margin-bottom: 0 !important;
                padding-bottom: 0 !important;
            }
            
            /* Garantir que os slots dentro das colunas sejam visíveis */
            .timeline-day-column .timeline-slot {
                position: absolute;
            }
            
            /* CRÍTICO: Não sobrescrever altura definida dinamicamente via JavaScript */
            /* Remover esta regra que pode conflitar com o ajuste dinâmico */
            /* .timeline-day-column[style*="height"] {
                height: auto !important;
            } */
            
            .timeline-day-column:last-child {
                border-right: none;
            }
            /* Grade de linhas horizontais estilo Google Calendar */
            .timeline-day-column::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                /* CRÍTICO: Não usar bottom: 0 - isso cria faixa extra */
                /* A altura será ajustada dinamicamente via JavaScript baseada no último slot renderizado */
                /* Usar height: 100% inicialmente, mas será sobrescrito pelo JS */
                height: 100%;
                width: 100%;
                /* Linhas muito sutis - estilo Google Calendar - removendo última linha */
                    background-image: 
                    repeating-linear-gradient(
                        to bottom,
                        transparent,
                        transparent 49px,
                        #dadce0 49px,
                        #dadce0 50px
                    );
                /* Ajustar background-size para não ultrapassar o conteúdo real */
                background-size: 100% calc(100% - 1px);
                background-position: top;
                background-repeat: no-repeat;
                pointer-events: none;
                z-index: 0;
                border-bottom: none !important;
                /* Garantir que não ultrapasse o último slot (23:00) */
                overflow: hidden;
            }
            /* Slots vazios - estilo Google Calendar */
            .timeline-slot {
                position: absolute;
                left: 0;
                right: 0;
                cursor: pointer;
                transition: all 0.15s ease;
                border-radius: 4px;
                padding: 4px 8px;
                font-size: 12px;
                z-index: 10;
                box-sizing: border-box;
                margin: 0 2px;
            }
            
            .timeline-slot.vazio {
                background: transparent;
                border: none;
                transition: all 0.15s ease;
                pointer-events: auto;
            }
            
            .timeline-slot.vazio:hover {
                background: #e8f0fe;
                border: 1px solid #1a73e8;
                border-radius: 4px;
            }
            
            .timeline-slot.vazio::before {
                content: '+';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                color: #1a73e8;
                font-size: 18px;
                font-weight: 300;
                opacity: 0.3;
                transition: opacity 0.15s ease;
                pointer-events: none;
                z-index: 11;
            }
            
            .timeline-slot.vazio:hover::before {
                opacity: 0.8;
            }
            
            /* Slots grandes de período não devem mostrar conteúdo visível */
            .timeline-slot.periodo-slot::before {
                display: none;
            }
            
            /* Slots grandes de intervalo não devem capturar eventos quando há slots menores */
            .timeline-slot.slot-intervalo {
                pointer-events: none;
            }
            
            /* Slots individuais sempre capturam eventos */
            .timeline-slot.slot-individual {
                pointer-events: auto !important;
                z-index: 15 !important;
            }
            
            .timeline-slot.slot-individual::before {
                opacity: 0.25 !important;
            }
            
            .timeline-slot.slot-individual:hover::before {
                opacity: 0.8 !important;
            }
            
            /* Slots desabilitados (fora do período) não são clicáveis */
            .timeline-slot.slot-desabilitado {
                pointer-events: none !important;
            }
            
            /* Eventos/Aulas - estilo Google Calendar */
            .timeline-slot.aula {
                color: #202124;
                font-weight: 400;
                box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: normal;
                word-wrap: break-word;
                display: block;
                min-height: 24px;
                border-left: 3px solid;
                border-radius: 0 4px 4px 0;
                padding: 6px 10px;
                line-height: 1.4;
            }
            
            .calendario-aula {
                position: relative;
                transition: box-shadow 0.25s ease, transform 0.25s ease;
            }
            
            .calendario-aula.calendario-aula--destaque {
                outline: 3px solid rgba(255,255,255,0.9);
                box-shadow: 0 0 0 3px rgba(2,58,141,0.35), 0 12px 20px rgba(2,58,141,0.25);
                transform: translateY(-2px);
                animation: calendarioAulaPulse 1.2s ease-in-out 0s 2;
            }
            
            @keyframes calendarioAulaPulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.02); }
                100% { transform: scale(1); }
            }
            
            .calendario-slot-vazio.calendario-slot--destaque {
                border-color: #023A8D !important;
                background: rgba(2,58,141,0.08) !important;
                box-shadow: inset 0 0 0 2px rgba(2,58,141,0.25);
            }
            
            /* Hover nos eventos - estilo Google Calendar */
            .timeline-slot.aula:hover {
                box-shadow: 0 2px 6px rgba(0,0,0,0.16), 0 2px 4px rgba(0,0,0,0.23);
                z-index: 15;
            }
            
            .timeline-periodo-colapsado {
                height: 40px !important;
                overflow: hidden;
                position: relative;
            }
            
            .timeline-periodo-colapsado::after {
                content: '...';
                position: absolute;
                bottom: 5px;
                left: 50%;
                transform: translateX(-50%);
                color: #6c757d;
                font-size: 0.8rem;
                background: white;
                padding: 0 10px;
            }
            
            .timeline-toggle-periodo {
                position: sticky;
                left: 0;
                z-index: 15;
                background: #f8f9fa;
                border-bottom: 2px solid #dee2e6;
                padding: 8px 12px;
                cursor: pointer;
                transition: background 0.2s;
                font-size: 0.85rem;
                font-weight: 600;
                color: #023A8D;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            
            .timeline-toggle-periodo:hover {
                background: #e9ecef;
            }
            
            .timeline-toggle-periodo .periodo-info {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .timeline-toggle-periodo .periodo-badge {
                background: #023A8D;
                color: white;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 0.7rem;
            }
            
            /* Classe para colapsar período sem quebrar layout */
            .periodo-colapsado {
                max-height: 0 !important;
                overflow: hidden !important;
                opacity: 0;
                transition: max-height 0.3s ease, opacity 0.3s ease;
                pointer-events: none;
                /* Garantir que display: none não sobrescreva */
            }
            
            .periodo-expandido {
                max-height: none !important;
                opacity: 1 !important;
                transition: max-height 0.3s ease, opacity 0.3s ease;
                pointer-events: auto;
                /* Quando expandido, garantir que está visível - usar revert para manter display original */
                visibility: visible !important;
            }
            
            /* Timeline hour markers devem sempre ser visíveis quando expandidos */
            .timeline-hour-marker.periodo-expandido {
                display: flex !important;
                visibility: visible !important;
            }
            
            /* Timeline slots vazios devem ser visíveis quando expandidos */
            .timeline-slot.vazio.periodo-expandido {
                display: block !important;
                visibility: visible !important;
            }
            
            /* Remover display: none quando período está expandido */
            .periodo-expandido[style*="display: none"] {
                display: revert !important;
            }
        </style>
            <div style="padding-bottom: 0; margin-bottom: 0;" id="calendario-container">
                <div class="timeline-calendar" id="timeline-calendario">
                    <?php
                    $semanaDisplay = $todasSemanas[$semanaSelecionada] ?? $todasSemanas[0];
                    $diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                    
                    // Calcular altura total da timeline (baseado em minutos)
                    // IMPORTANTE: Verificar TODAS as aulas para encontrar o range real
                    // E garantir que usamos SEMPRE o mesmo range para coluna de horários e posicionamento das aulas
                    
                    // Debug: verificar quantas aulas temos neste ponto
                    error_log("Calendário Turma $turmaId: [CÁLCULO ALTURA] Total de aulas em todasAulasCalendario: " . count($todasAulasCalendario));
                    foreach ($todasAulasCalendario as $idx => $aulaDebug) {
                        error_log("  [CÁLCULO ALTURA] Aula " . ($idx + 1) . ": ID=" . ($aulaDebug['id'] ?? 'N/A') . ", Fim=" . ($aulaDebug['hora_fim'] ?? 'N/A'));
                    }
                    
                    $horaMinimaReal = $horaMinima; // Começar com o mínimo definido (6:00 ou menor das aulas)
                    $horaMaximaReal = $horaMaxima; // Começar com o máximo definido (23:00 ou maior das aulas)
                    
                    foreach ($todasAulasCalendario as $aula) {
                        // Normalizar hora início
                        $horaInicioStr = $aula['hora_inicio'];
                        $horaFimStr = $aula['hora_fim'];
                        if (strlen($horaInicioStr) == 8) {
                            $horaInicioStr = substr($horaInicioStr, 0, 5);
                        }
                        if (strlen($horaFimStr) == 8) {
                            $horaFimStr = substr($horaFimStr, 0, 5);
                        }
                        
                        list($horaInicio, $minInicio) = explode(':', $horaInicioStr);
                        list($horaFim, $minFim) = explode(':', $horaFimStr);
                        
                        $horaInicioMinutos = (int)$horaInicio * 60 + (int)$minInicio;
                        $horaFimMinutos = (int)$horaFim * 60 + (int)$minFim;
                        
                        // Atualizar range real
                        if ($horaInicioMinutos < $horaMinimaReal) {
                            $horaMinimaReal = max(6 * 60, $horaInicioMinutos - 30); // Não ir antes de 6:00
                        }
                        if ($horaFimMinutos > $horaMaximaReal) {
                            $horaMaximaReal = $horaFimMinutos; // Sem margem - terminar exatamente quando a última aula termina
                        }
                    }
                    
                    // Garantir que a altura máxima inclua TODAS as aulas, especialmente a última
                    // Se há aulas, sempre incluir pelo menos até o fim da última aula + margem de 60 minutos
                    if (empty($todasAulasCalendario)) {
                        // Sem aulas: mostrar de 6:00 a 23:00
                        $horaMinimaFinal = 6 * 60;
                        $horaMaximaFinal = 23 * 60;
                    } else {
                        // Com aulas: garantir que TODAS as aulas estejam completamente visíveis
                        // Encontrar a última hora de término de todas as aulas
                        $ultimaHoraFim = 0;
                        $ultimaAulaId = null;
                        
                        // Debug: verificar se a Aula 111 está no array
                        $aula111Encontrada = false;
                        foreach ($todasAulasCalendario as $aulaCheck) {
                            if (($aulaCheck['id'] ?? null) == 111) {
                                $aula111Encontrada = true;
                                error_log("Calendário Turma $turmaId: [VERIFICAÇÃO] Aula 111 encontrada no array! hora_fim='" . ($aulaCheck['hora_fim'] ?? 'N/A') . "'");
                                break;
                            }
                        }
                        if (!$aula111Encontrada) {
                            error_log("Calendário Turma $turmaId: [ERRO] Aula 111 NÃO encontrada no array todasAulasCalendario! Total de aulas: " . count($todasAulasCalendario));
                            foreach ($todasAulasCalendario as $idx => $aulaDebug) {
                                error_log("  Aula no array: ID=" . ($aulaDebug['id'] ?? 'N/A') . ", Fim=" . ($aulaDebug['hora_fim'] ?? 'N/A'));
                            }
                        }
                        
                        foreach ($todasAulasCalendario as $aula) {
                            $aulaId = $aula['id'] ?? 'N/A';
                            $horaFimStr = $aula['hora_fim'];
                            
                            // Normalizar formato (pode ser HH:MM:SS ou HH:MM)
                            if (strlen($horaFimStr) == 8) {
                                $horaFimStr = substr($horaFimStr, 0, 5);
                            }
                            
                            // Garantir que explode funciona corretamente
                            $horaFimParts = explode(':', $horaFimStr);
                            if (count($horaFimParts) >= 2) {
                                $horaFim = (int)($horaFimParts[0] ?? 0);
                                $minFim = (int)($horaFimParts[1] ?? 0);
                                $horaFimMinutos = $horaFim * 60 + $minFim;
                                
                                if ($horaFimMinutos > $ultimaHoraFim) {
                                    $ultimaHoraFim = $horaFimMinutos;
                                    $ultimaAulaId = $aulaId;
                                }
                                
                                // Debug específico para cada aula (especialmente Aula 111)
                                if ($aulaId == 111) {
                                    error_log("Calendário Turma $turmaId: [AULA 111 PROCESSADA] hora_fim original='" . ($aula['hora_fim'] ?? 'N/A') . "', hora_fim normalizada='$horaFimStr', horaFimMinutos=$horaFimMinutos (" . sprintf('%02d:%02d', $horaFim, $minFim) . ")");
                                }
                                error_log("Calendário Turma $turmaId: Aula ID=$aulaId, hora_fim original='" . ($aula['hora_fim'] ?? 'N/A') . "', hora_fim normalizada='$horaFimStr', horaFimMinutos=$horaFimMinutos (" . sprintf('%02d:%02d', $horaFim, $minFim) . ")");
                            } else {
                                error_log("Calendário Turma $turmaId: ERRO ao processar hora_fim da Aula ID=$aulaId, formato inválido: '$horaFimStr'");
                            }
                        }
                        
                        // Garantir que a última aula esteja completamente visível + margem de 60 minutos
                        // Sempre mostrar pelo menos até 23:00, mas estender se necessário
                        $horaMaximaFinal = max(23 * 60, $ultimaHoraFim + 60); // Última aula + 1 hora de margem
                        $horaMinimaFinal = min($horaMinima, $horaMinimaReal);
                        
                        // Log para debug
                        error_log("Calendário Turma $turmaId: Última hora fim encontrada: " . sprintf('%02d:%02d', floor($ultimaHoraFim/60), $ultimaHoraFim%60) . " (Aula ID=$ultimaAulaId) | horaMaximaFinal: " . sprintf('%02d:%02d', floor($horaMaximaFinal/60), $horaMaximaFinal%60));
                    }
                    
                    // CRÍTICO: Atualizar as variáveis GLOBAIS para serem usadas em TODOS os cálculos
                    // Isso garante que coluna de horários e posicionamento das aulas usem o mesmo range
                    $horaMinimaUsar = $horaMinimaFinal;
                    $horaMaximaUsar = $horaMaximaFinal;
                    
                    // SOBRESCREVER $horaMinima e $horaMaxima para garantir consistência
                    $horaMinima = $horaMinimaFinal;
                    $horaMaxima = $horaMaximaFinal;
                    
                    // Calcular altura total baseada no conteúdo real
                    // Densidade compacta: 50px por slot de 30min = ~1.67px por minuto
                    $alturaTotalMinutos = $horaMaximaFinal - $horaMinimaFinal;
                    $alturaTotalPx = $alturaTotalMinutos * (50 / 30); // Densidade compacta: ~1.67px por minuto
                    
                    // Atualizar variáveis globais para garantir consistência
                    $horaMinima = $horaMinimaFinal;
                    $horaMaxima = $horaMaximaFinal;
                    
                    // Debug detalhado
                    echo "<!-- DEBUG Timeline: horaMinima=$horaMinimaFinal (" . sprintf('%02d:%02d', floor($horaMinimaFinal/60), $horaMinimaFinal%60) . "), horaMaxima=$horaMaximaFinal (" . sprintf('%02d:%02d', floor($horaMaximaFinal/60), $horaMaximaFinal%60) . "), alturaTotal=$alturaTotalPx px, totalAulas=" . count($todasAulasCalendario) . " -->";
                    ?>
                    
                    <!-- Cabeçalho -->
                    <div class="timeline-header">
                        <div class="timeline-time-column">
                            <!-- Espaço vazio para alinhamento -->
                        </div>
                        <?php 
                        $hoje = new DateTime();
                        $hojeStr = $hoje->format('Y-m-d');
                        foreach ($semanaDisplay['dias'] as $idx => $data): 
                            $ehHoje = ($data === $hojeStr);
                            $dataForaPeriodo = false;
                            if ($data) {
                                $dataDia = new DateTime($data);
                                $dataForaPeriodo = ($dataDia < $dataInicio || $dataDia > $dataFim);
                            }
                        ?>
                            <div class="timeline-day-header <?= $ehHoje ? 'hoje' : '' ?> <?= $dataForaPeriodo ? 'dia-fora-periodo' : '' ?>" data-dia-semana="<?= $idx ?>">
                                <?php if ($data): 
                                    $diaFormatado = new DateTime($data);
                                ?>
                                    <span class="dia-nome"><?= $diasSemana[$idx] ?></span>
                                    <span class="dia-data data-display" style="<?= $dataForaPeriodo ? 'opacity: 0.4;' : '' ?>"><?= $diaFormatado->format('d') ?></span>
                                <?php else: ?>
                                    <span class="dia-nome" style="opacity: 0.5;"><?= $diasSemana[$idx] ?></span>
                                    <span class="dia-data data-display">-</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Corpo da Timeline -->
                    <div class="timeline-body" style="min-height: <?= $alturaTotalPx ?>px;" data-altura-original="<?= $alturaTotalPx ?>">
                        <!-- Coluna de Horários -->
                        <div class="timeline-hours" id="timeline-hours-column" style="min-height: <?= $alturaTotalPx ?>px;" data-altura-original="<?= $alturaTotalPx ?>">
                            <?php
                            // Renderizar períodos com possibilidade de colapsar
                            $periodoRenderizado = '';
                            // Usar $horaMinima e $horaMaxima que já foram atualizados acima
                            $horaAtual = $horaMinima;
                            // Renderizar até a hora máxima calculada (inclui margem para última aula)
                            // Usar $horaMaxima que já foi calculado incluindo todas as aulas + margem
                            $horaMaximaLimite = $horaMaxima; // Usar o máximo calculado, não limitar a 23:00
                            
                            // Renderizar todos os slots até a hora máxima (garante que última aula seja visível)
                            while ($horaAtual < $horaMaximaLimite):
                                $horas = floor($horaAtual / 60);
                                $minutos = $horaAtual % 60;
                                
                                // Não renderizar se passou do limite (usar < em vez de <= para evitar linha extra)
                                if ($horaAtual >= $horaMaximaLimite) {
                                    break;
                                }
                                
                                $horaTexto = sprintf('%02d:%02d', $horas, $minutos);
                                $ehHoraInteira = ($minutos == 0);
                                
                                // Determinar período
                                $periodo = '';
                                if ($horas < 12) {
                                    $periodo = 'Manhã';
                                } elseif ($horas < 18) {
                                    $periodo = 'Tarde';
                                } else {
                                    $periodo = 'Noite';
                                }
                                
                                // Verificar se é início de um novo período
                                $ehInicioPeriodo = ($periodo != $periodoRenderizado && $ehHoraInteira);
                                
                                // Renderizar toggle de período no início de cada período
                                $periodoInfo = isset($periodos[$periodo]) && is_array($periodos[$periodo]) ? $periodos[$periodo] : null;
                                
                                if ($ehInicioPeriodo && $periodoInfo) {
                                    // Contar aulas neste período
                                    $periodoInicioMin = isset($periodoInfo['inicio']) ? $periodoInfo['inicio'] : 0;
                                    $periodoFimMin = isset($periodoInfo['fim']) ? $periodoInfo['fim'] : 0;
                                    $aulasNoPeriodo = 0;
                                    
                                    foreach ($todasAulasCalendario as $aula) {
                                        $horaInicioStr = $aula['hora_inicio'];
                                        if (strlen($horaInicioStr) == 8) {
                                            $horaInicioStr = substr($horaInicioStr, 0, 5);
                                        }
                                        list($horaInicio, $minInicio) = explode(':', $horaInicioStr);
                                        $horaMinAula = (int)$horaInicio * 60 + (int)$minInicio;
                                        
                                        if ($horaMinAula >= $periodoInicioMin && $horaMinAula < $periodoFimMin) {
                                            $aulasNoPeriodo++;
                                        }
                                    }
                                    
                                    // REMOVIDO: Toggle de período para períodos vazios
                                    // Isso estava causando linhas extras sem horário correspondente
                                    // Períodos vazios agora são renderizados normalmente com slots de período
                                }
                                
                                // Renderizar marcador de hora normal
                                // Cada slot de 30 minutos = 50px (densidade compacta: ~1.67px por minuto)
                                $altura = 50;
                            ?>
                            <div class="timeline-hour-marker <?= $ehHoraInteira ? 'hora-inteira' : '' ?>" 
                                 style="height: <?= $altura ?>px; flex-shrink: 0;"
                                 data-hora-minutos="<?= $horaAtual ?>"
                                 data-hora-texto="<?= $horaTexto ?>"
                                 data-periodo="<?= strtolower($periodo) ?>">
                                <?php if ($ehHoraInteira): ?>
                                    <span class="timeline-hour-label"><?= $horaTexto ?></span>
                                    <?php if ($ehInicioPeriodo): ?>
                                        <span class="timeline-period-label"><?= $periodo ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php
                                if ($ehInicioPeriodo) {
                                    $periodoRenderizado = $periodo;
                                }
                                
                                // Avançar 30 minutos
                                $horaAtual += 30;
                                
                                // Continuar até atingir horaMaximaLimite (não limitar a 23:00)
                                // O loop já para quando $horaAtual >= $horaMaximaLimite
                            endwhile;
                            
                            // Renderizar última linha se necessário (quando horaMaximaLimite ultrapassa o último slot de 30min)
                            // Verificar se precisamos renderizar um marcador final
                            if ($horaAtual < $horaMaximaLimite):
                                $horasFinais = floor($horaMaximaLimite / 60);
                                $minutosFinais = $horaMaximaLimite % 60;
                                $horaTextoFinal = sprintf('%02d:%02d', $horasFinais, $minutosFinais);
                                $alturaFinal = ($horaMaximaLimite - $horaAtual) * (50 / 30);
                            ?>
                            <div class="timeline-hour-marker hora-inteira" 
                                 style="height: <?= $alturaFinal ?>px; flex-shrink: 0;"
                                 data-hora-minutos="<?= $horaMaximaLimite ?>"
                                 data-hora-texto="<?= $horaTextoFinal ?>"
                                 data-periodo="noite">
                                <?php if ($minutosFinais == 0): ?>
                                    <span class="timeline-hour-label"><?= $horaTextoFinal ?></span>
                                <?php endif; ?>
                            </div>
                            <?php
                            endif;
                            ?>
                        </div>
                        
                        <!-- Colunas dos Dias -->
                        <?php foreach ($semanaDisplay['dias'] as $diaIdx => $data): ?>
                            <div class="timeline-day-column" data-dia-semana="<?= $diaIdx ?>" data-data="<?= $data ?>" style="position: relative; min-height: <?= $alturaTotalPx ?>px;" data-altura-original="<?= $alturaTotalPx ?>" id="dia-col-<?= $diaIdx ?>">
                                <?php if ($data): 
                                    // Normalizar formato de data para busca (garantir Y-m-d)
                                    $dataBusca = $data;
                                    
                                    // Buscar aulas deste dia - tentar todas as variações de data
                                    $aulasDoDia = [];
                                    
                                    // Normalizar data da semana para comparação (garantir formato Y-m-d)
                                    $dataNormalizadaBusca = $dataBusca;
                                    if (strpos($dataNormalizadaBusca, '-') !== false) {
                                        $parts = explode('-', $dataNormalizadaBusca);
                                        if (count($parts) == 3) {
                                            $dataNormalizadaBusca = $parts[0] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[2], 2, '0', STR_PAD_LEFT);
                                        }
                                    } else {
                                        // Se não está em formato Y-m-d, converter
                                        $dt = DateTime::createFromFormat('d/m/Y', $dataBusca);
                                        if ($dt) {
                                            $dataNormalizadaBusca = $dt->format('Y-m-d');
                                        }
                                    }
                                    
                                    // MÉTODO ALTERNATIVO: Buscar diretamente de $todasAulasCalendario se aulasPorData falhar
                                    $aulasEncontradasPorData = false;
                                    
                                    // Função auxiliar para normalizar qualquer formato de data
                                    $normalizarData = function($data) {
                                        if (empty($data)) return null;
                                        
                                        // Se já está em formato Y-m-d normalizado
                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
                                            return $data;
                                        }
                                        
                                        // Se está em formato Y-m-d não normalizado
                                        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $data)) {
                                            $parts = explode('-', $data);
                                            return $parts[0] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[2], 2, '0', STR_PAD_LEFT);
                                        }
                                        
                                        // Se está em formato com /
                                        if (strpos($data, '/') !== false) {
                                            $parts = explode('/', $data);
                                            if (count($parts) == 3) {
                                                // Determinar formato: Y/m/d ou d/m/Y
                                                if (strlen($parts[2]) == 4) {
                                                    // Formato d/m/Y
                                                    return $parts[2] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                                                } elseif (strlen($parts[0]) == 4) {
                                                    // Formato Y/m/d
                                                    return $parts[0] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[2], 2, '0', STR_PAD_LEFT);
                                                }
                                            }
                                        }
                                        
                                        return null;
                                    };
                                    
                                    // Buscar em todas as chaves de aulasPorData
                                    foreach ($aulasPorData as $dataKey => $disciplinas) {
                                        $dataKeyNormalizada = $normalizarData($dataKey);
                                        
                                        if ($dataKeyNormalizada && $dataKeyNormalizada == $dataNormalizadaBusca) {
                                            $aulasEncontradasPorData = true;
                                            foreach ($disciplinas as $discId => $aulas) {
                                                foreach ($aulas as $aula) {
                                                    // Verificar se a aula realmente pertence a este dia
                                                    $aulaDataNormalizada = $normalizarData($aula['data_aula'] ?? '');
                                                    if ($aulaDataNormalizada && $aulaDataNormalizada == $dataNormalizadaBusca) {
                                                        $aulasDoDia[] = $aula;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    
                                    // SE não encontrou em aulasPorData, buscar diretamente de $todasAulasCalendario
                                    if (!$aulasEncontradasPorData && !empty($todasAulasCalendario)) {
                                        foreach ($todasAulasCalendario as $aula) {
                                            $aulaDataNormalizada = $normalizarData($aula['data_aula'] ?? '');
                                            if ($aulaDataNormalizada && $aulaDataNormalizada == $dataNormalizadaBusca) {
                                                $aulasDoDia[] = $aula;
                                            }
                                        }
                                    }
                                    
                                    // SEMPRE buscar diretamente de $todasAulasCalendario como fallback adicional
                                    // Isso garante que todas as aulas sejam encontradas mesmo se houver problema na organização
                                    if (!empty($todasAulasCalendario)) {
                                        $aulasEncontradasDiretamente = [];
                                        foreach ($todasAulasCalendario as $aula) {
                                            $aulaDataNormalizada = $normalizarData($aula['data_aula'] ?? '');
                                            if ($aulaDataNormalizada && $aulaDataNormalizada == $dataNormalizadaBusca) {
                                                // Verificar se já está em $aulasDoDia
                                                $jaExiste = false;
                                                foreach ($aulasDoDia as $aulaExistente) {
                                                    if (($aulaExistente['id'] ?? null) == ($aula['id'] ?? null)) {
                                                        $jaExiste = true;
                                                        break;
                                                    }
                                                }
                                                if (!$jaExiste) {
                                                    $aulasDoDia[] = $aula;
                                                    $aulasEncontradasDiretamente[] = $aula['id'] ?? 'N/A';
                                                }
                                            }
                                        }
                                        if (!empty($aulasEncontradasDiretamente)) {
                                            error_log("Calendário Turma $turmaId: Aulas adicionadas diretamente para $dataNormalizadaBusca: " . implode(', ', $aulasEncontradasDiretamente));
                                        }
                                    }
                                    
                                    // Debug detalhado - sempre exibir para diagnóstico
                                    $debugDia = "<!-- Debug Dia $dataBusca (normalizada: $dataNormalizadaBusca): " . count($aulasDoDia) . " aulas encontradas. ";
                                    if (count($aulasDoDia) > 0) {
                                        $debugDia .= "Primeira: ID " . ($aulasDoDia[0]['id'] ?? 'N/A') . " às " . ($aulasDoDia[0]['hora_inicio'] ?? 'N/A') . " - " . ($aulasDoDia[0]['nome_aula'] ?? 'N/A');
                                        $debugDia .= " | Data da primeira: " . ($aulasDoDia[0]['data_aula'] ?? 'N/A');
                                        // Verificar cálculos de posição
                                        if (!empty($aulasDoDia[0]['hora_inicio'])) {
                                            $horaInicioTeste = $aulasDoDia[0]['hora_inicio'];
                                            if (strlen($horaInicioTeste) == 8) {
                                                $horaInicioTeste = substr($horaInicioTeste, 0, 5);
                                            }
                                            $parts = explode(':', $horaInicioTeste);
                                            $inicioMinutos = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
                                            $topCalculado = ($inicioMinutos - $horaMinima) * (50 / 30);
                                            $debugDia .= " | Calculo top: ($inicioMinutos - $horaMinima) * (50/30) = $topCalculado px";
                                        }
                                    } else {
                                        $debugDia .= " NENHUMA AULA ENCONTRADA! Verificando aulasPorData...";
                                        $debugDia .= " Chaves disponíveis (" . count($aulasPorData ?? []) . "): " . implode(', ', array_slice(array_keys($aulasPorData ?? []), 0, 10));
                                        // Tentar buscar diretamente
                                        $debugDia .= " | Buscando diretamente em todasAulasCalendario...";
                                        $encontradasDiretas = 0;
                                        foreach ($todasAulasCalendario as $aulaTeste) {
                                            $aulaDataTeste = $normalizarData($aulaTeste['data_aula'] ?? '');
                                            if ($aulaDataTeste && $aulaDataTeste == $dataNormalizadaBusca) {
                                                $encontradasDiretas++;
                                            }
                                        }
                                        $debugDia .= " Encontradas diretamente: $encontradasDiretas";
                                    }
                                    $debugDia .= " -->";
                                    // Sempre exibir debug
                                    echo $debugDia;
                                    
                                    // Ordenar aulas por horário
                                    usort($aulasDoDia, function($a, $b) {
                                        return strcmp($a['hora_inicio'], $b['hora_inicio']);
                                    });
                                    
                                    // Debug específico para verificar todas as aulas do dia
                                    echo "<!-- DEBUG AULAS DO DIA $dataBusca: Total encontrado: " . count($aulasDoDia) . " -->";
                                    foreach ($aulasDoDia as $idx => $aulaDebug) {
                                        echo "<!-- Aula " . ($idx + 1) . ": ID=" . ($aulaDebug['id'] ?? 'N/A') . ", Nome=" . htmlspecialchars($aulaDebug['nome_aula'] ?? 'N/A') . ", Inicio=" . ($aulaDebug['hora_inicio'] ?? 'N/A') . ", Fim=" . ($aulaDebug['hora_fim'] ?? 'N/A') . " -->";
                                    }
                                    
                                    // Calcular posições das aulas e slots vazios
                                    $eventos = [];
                                    
                                    // Debug: verificar se a Aula 111 está em aulasDoDia
                                    $aula111NoDia = false;
                                    foreach ($aulasDoDia as $aulaCheck) {
                                        if (($aulaCheck['id'] ?? null) == 111) {
                                            $aula111NoDia = true;
                                            error_log("Calendário Turma $turmaId: [EVENTOS] Aula 111 encontrada em aulasDoDia para $dataBusca! hora_fim='" . ($aulaCheck['hora_fim'] ?? 'N/A') . "'");
                                            break;
                                        }
                                    }
                                    if (!$aula111NoDia && $dataBusca == '2025-11-12') {
                                        error_log("Calendário Turma $turmaId: [ERRO EVENTOS] Aula 111 NÃO encontrada em aulasDoDia para $dataBusca! Total de aulas: " . count($aulasDoDia));
                                        foreach ($aulasDoDia as $idx => $aulaDebug) {
                                            error_log("  Aula em aulasDoDia: ID=" . ($aulaDebug['id'] ?? 'N/A') . ", Fim=" . ($aulaDebug['hora_fim'] ?? 'N/A'));
                                        }
                                    }
                                    
                                    foreach ($aulasDoDia as $aula) {
                                        // Converter hora_inicio e hora_fim para minutos (formato pode ser HH:MM ou HH:MM:SS)
                                        $horaInicioStr = $aula['hora_inicio'];
                                        $horaFimStr = $aula['hora_fim'];
                                        
                                        // Normalizar formato (remover segundos se presente)
                                        if (strlen($horaInicioStr) == 8) {
                                            $horaInicioStr = substr($horaInicioStr, 0, 5);
                                        }
                                        if (strlen($horaFimStr) == 8) {
                                            $horaFimStr = substr($horaFimStr, 0, 5);
                                        }
                                        
                                        // Converter para minutos desde meia-noite
                                        // Garantir que explode funciona mesmo se houver segundos
                                        $horaInicioParts = explode(':', $horaInicioStr);
                                        $horaFimParts = explode(':', $horaFimStr);
                                        
                                        $horaInicio = (int)($horaInicioParts[0] ?? 0);
                                        $minInicio = (int)($horaInicioParts[1] ?? 0);
                                        $horaFim = (int)($horaFimParts[0] ?? 0);
                                        $minFim = (int)($horaFimParts[1] ?? 0);
                                        
                                        $inicioMinutos = $horaInicio * 60 + $minInicio;
                                        $fimMinutos = $horaFim * 60 + $minFim;
                                        
                                        // Validar horários
                                        if ($inicioMinutos < 0 || $inicioMinutos > 1440 || $fimMinutos < 0 || $fimMinutos > 1440) {
                                            error_log("Horário inválido para aula ID {$aula['id']}: $horaInicioStr - $horaFimStr");
                                            continue;
                                        }
                                        
                                        $eventos[] = [
                                            'tipo' => 'aula',
                                            'inicio' => $inicioMinutos,
                                            'fim' => $fimMinutos,
                                            'aula' => $aula
                                        ];
                                    }
                                    // Ordenar eventos por início
                                    usort($eventos, function($a, $b) {
                                        return $a['inicio'] - $b['inicio'];
                                    });
                                    
                                    // Debug específico para verificar eventos antes da renderização
                                    echo "<!-- DEBUG EVENTOS DO DIA $dataBusca: Total de eventos: " . count($eventos) . " -->";
                                    error_log("Calendário Turma $turmaId: [RENDERIZAÇÃO] Dia $dataBusca - Total de eventos: " . count($eventos));
                                    foreach ($eventos as $idx => $eventoDebug) {
                                        $aulaDebug = $eventoDebug['aula'];
                                        $debugMsg = "Evento " . ($idx + 1) . ": ID=" . ($aulaDebug['id'] ?? 'N/A') . ", Nome=" . htmlspecialchars($aulaDebug['nome_aula'] ?? 'N/A') . ", Inicio=" . ($eventoDebug['inicio'] ?? 'N/A') . "min (" . sprintf('%02d:%02d', floor($eventoDebug['inicio']/60), $eventoDebug['inicio']%60) . "), Fim=" . ($eventoDebug['fim'] ?? 'N/A') . "min (" . sprintf('%02d:%02d', floor($eventoDebug['fim']/60), $eventoDebug['fim']%60) . ")";
                                        echo "<!-- $debugMsg -->";
                                        error_log("Calendário Turma $turmaId: [RENDERIZAÇÃO] $debugMsg");
                                    }
                                    
                                    // Renderizar aulas e slots vazios
                                    // Usar $horaMinima que já foi atualizado acima para garantir consistência
                                    $ultimoFim = $horaMinima;
                                    
                                    // Se não há eventos, criar slots vazios por período (mais organizados)
                                    if (empty($eventos)) {
                                        // Criar slots vazios para cada período (manhã, tarde, noite)
                                        foreach ($periodos as $nomePeriodo => $periodoInfo) {
                                            // Garantir que $periodoInfo é um array
                                            if (!is_array($periodoInfo)) {
                                                continue;
                                            }
                                            $periodoInicio = isset($periodoInfo['inicio']) ? $periodoInfo['inicio'] : 0;
                                            $periodoFim = isset($periodoInfo['fim']) ? $periodoInfo['fim'] : 0;
                                            
                                            // Ajustar para o range visível
                                            if ($periodoInicio < $horaMinima) $periodoInicio = $horaMinima;
                                            if ($periodoFim > $horaMaxima) $periodoFim = $horaMaxima;
                                            if ($periodoInicio >= $periodoFim) continue;
                                            
                                            $top = ($periodoInicio - $horaMinima) * (50 / 30);
                                            $altura = ($periodoFim - $periodoInicio) * (50 / 30);
                                            $horaSlot = sprintf('%02d:%02d', floor($periodoInicio / 60), $periodoInicio % 60);
                                            
                                            // REMOVIDO: displayStyle baseado em colapso - períodos sempre visíveis inicialmente
                                            // O JavaScript controlará o colapso visualmente se o usuário clicar
                                    ?>
                                    <div class="timeline-slot vazio periodo-slot calendario-slot-vazio" 
                                         data-periodo="<?= strtolower($nomePeriodo) ?>" 
                                         data-periodo-inicio="<?= $periodoInicio ?>"
                                         data-periodo-fim="<?= $periodoFim ?>"
                                         style="top: <?= $top ?>px; height: <?= $altura ?>px; position: absolute; left: 2px; right: 2px; z-index: 1; pointer-events: none;"
                                         onclick="agendarNoSlot('<?= $data ?>', '<?= $horaSlot ?>')"
                                         data-data="<?= $data ?>"
                                         data-hora-inicio="<?= $horaSlot ?>"
                                         title="Clique para agendar aula em <?= $nomePeriodo ?>">
                                    </div>
                                    <?php
                                        }
                                        
                                        // CRÍTICO: Criar slots individuais de 30 minutos mesmo quando não há eventos
                                        // Isso garante comportamento igual ao Google Calendar: cada slot é clicável independentemente
                                        // IMPORTANTE: Desabilitar slots para datas fora do período da turma
                                        $dataDateTime = new DateTime($data);
                                        $dataForaPeriodo = ($dataDateTime < $dataInicio || $dataDateTime > $dataFim);
                                        
                                        $horaAtualSlot = $horaMinima;
                                        // Usar $horaMaxima que já foi calculado acima (inclui margem para última aula)
                                        // Garantir que todos os slots até a última aula sejam renderizados
                                        $horaMaximaSlot = $horaMaxima; // Usar o máximo calculado, não limitar a 23:00
                                        while ($horaAtualSlot < $horaMaximaSlot) {
                                            $top = ($horaAtualSlot - $horaMinima) * (50 / 30);
                                            $altura = 50; // 30 minutos = 50px (densidade compacta: ~1.67px por minuto)
                                            $horaSlot = sprintf('%02d:%02d', floor($horaAtualSlot / 60), $horaAtualSlot % 60);
                                            
                                            // Determinar período
                                            $periodoSlot = '';
                                            if ($horaAtualSlot < 12 * 60) {
                                                $periodoSlot = 'manhã';
                                            } elseif ($horaAtualSlot < 18 * 60) {
                                                $periodoSlot = 'tarde';
                                            } else {
                                                $periodoSlot = 'noite';
                                            }
                                            
                                            // Se data está fora do período, desabilitar slot (visível mas não clicável)
                                            $classeSlot = $dataForaPeriodo ? 'slot-individual slot-desabilitado' : 'slot-individual';
                                            $estiloSlot = $dataForaPeriodo ? 'opacity: 0.3; cursor: not-allowed;' : '';
                                            $onclickSlot = $dataForaPeriodo ? '' : "onclick=\"agendarNoSlot('{$data}', '{$horaSlot}')\"";
                                            $titleSlot = $dataForaPeriodo ? 'Data fora do período da turma' : "Clique para agendar aula às {$horaSlot}";
                                    ?>
                                    <div class="timeline-slot vazio <?= $classeSlot ?> calendario-slot-vazio" 
                                         data-periodo="<?= $periodoSlot ?>"
                                         data-data="<?= $data ?>"
                                         data-hora-inicio="<?= $horaSlot ?>"
                                         data-fora-periodo="<?= $dataForaPeriodo ? '1' : '0' ?>"
                                         style="top: <?= $top ?>px; height: <?= $altura ?>px; position: absolute; left: 2px; right: 2px; z-index: 15; <?= $estiloSlot ?>"
                                         <?= $onclickSlot ?>
                                         title="<?= $titleSlot ?>">
                                    </div>
                                    <?php
                                            // Avançar 30 minutos
                                            $horaAtualSlot += 30;
                                            
                                            // Parar exatamente em 23:00 (não ultrapassar para evitar slot extra)
                                            if ($horaAtualSlot > 23 * 60) {
                                                break;
                                            }
                                        }
                                    } else {
                                        // Renderizar cada evento
                                        $eventosRenderizados = 0;
                                        foreach ($eventos as $evento) {
                                            $eventosRenderizados++;
                                            $aulaAtual = $evento['aula'];
                                            
                                            // Debug específico para cada evento sendo renderizado
                                            echo "<!-- RENDERIZANDO EVENTO $eventosRenderizados de " . count($eventos) . ": Aula ID=" . ($aulaAtual['id'] ?? 'N/A') . ", Nome=" . htmlspecialchars($aulaAtual['nome_aula'] ?? 'N/A') . " -->";
                                            
                                            // Determinar período desta aula
                                            $periodoAula = '';
                                            if ($evento['inicio'] < 12 * 60) {
                                                $periodoAula = 'manhã';
                                            } elseif ($evento['inicio'] < 18 * 60) {
                                                $periodoAula = 'tarde';
                                            } else {
                                                $periodoAula = 'noite';
                                            }
                                            $periodoAulaInfo = isset($periodos[ucfirst($periodoAula)]) && is_array($periodos[ucfirst($periodoAula)]) ? $periodos[ucfirst($periodoAula)] : null;
                                            // CRÍTICO: Períodos com aulas NUNCA devem ser escondidos, mesmo que marcados como colapsados
                                            // Se há aulas no período, ele não pode estar colapsado
                                            $periodoColapsado = false; // Sempre false para períodos com aulas
                                            
                                            // Slot vazio antes da aula (se houver)
                                            if ($evento['inicio'] > $ultimoFim && $evento['inicio'] - $ultimoFim >= 30) {
                                            $slotInicio = $ultimoFim;
                                            $slotFim = $evento['inicio'];
                                            $top = ($slotInicio - $horaMinima) * (50 / 30);
                                            $altura = ($slotFim - $slotInicio) * (50 / 30);
                                            $horaSlot = sprintf('%02d:%02d', floor($slotInicio / 60), $slotInicio % 60);
                                            
                                            // Determinar período do slot vazio
                                            $periodoSlot = '';
                                            if ($slotInicio < 12 * 60) {
                                                $periodoSlot = 'manhã';
                                            } elseif ($slotInicio < 18 * 60) {
                                                $periodoSlot = 'tarde';
                                            } else {
                                                $periodoSlot = 'noite';
                                            }
                                            // REMOVIDO: Verificação de colapso para slots vazios
                                            // Slots sempre visíveis
                                    ?>
                                    <div class="timeline-slot vazio slot-intervalo calendario-slot-vazio" 
                                         data-periodo="<?= $periodoSlot ?>"
                                         style="top: <?= $top ?>px; height: <?= $altura ?>px; position: absolute; left: 2px; right: 2px; z-index: 5; pointer-events: none;"
                                         onclick="agendarNoSlot('<?= $data ?>', '<?= $horaSlot ?>')"
                                         data-data="<?= $data ?>"
                                         data-hora-inicio="<?= $horaSlot ?>"
                                         title="Clique para agendar aula a partir de <?= $horaSlot ?>">
                                    </div>
                                    <?php
                                        }
                                        
                                        // Aula
                                        $aula = $evento['aula'];
                                        $ehUltima = isset($ultimaAulaPorDisciplina[$aula['disciplina_id']]) && 
                                                   $ultimaAulaPorDisciplina[$aula['disciplina_id']]['id'] == $aula['id'];
                                        $corDisciplina = $coresDisciplinas[$aula['disciplina_id']] ?? '#023A8D';
                                        $nomeDisciplina = $disciplinasMap[$aula['disciplina_id']] ?? 'Disciplina';
                                        
                                        // Calcular posição e altura (densidade compacta: ~1.67px por minuto)
                                        // CRÍTICO: Usar $horaMinima que já foi atualizado acima para garantir alinhamento
                                        // Calcular top baseado no início do evento
                                        $top = ($evento['inicio'] - $horaMinima) * (50 / 30);
                                        
                                        // Calcular altura baseado na duração (densidade compacta: ~1.67px por minuto)
                                        $duracaoMinutos = $evento['fim'] - $evento['inicio'];
                                        $altura = max(35, $duracaoMinutos * (50 / 30)); // Mínimo de 35px para visibilidade
                                        
                                        // Validar que top não seja negativo (aula antes do início da timeline)
                                        if ($top < 0) {
                                            echo "<!-- AVISO: Aula antes de horaMinima! top={$top}px, inicio={$evento['inicio']}min (" . sprintf('%02d:%02d', floor($evento['inicio']/60), $evento['inicio']%60) . "), horaMinima={$horaMinima}min (" . sprintf('%02d:%02d', floor($horaMinima/60), $horaMinima%60) . "). Ajustando para 0. -->";
                                            $top = 0;
                                        }
                                        
                                        // Garantir que a altura não ultrapasse o limite da timeline
                                        // IMPORTANTE: Se a aula ultrapassar o limite, ajustar $horaMaxima em vez de cortar a aula
                                        $maxTopAtual = ($horaMaxima - $horaMinima) * (50 / 30);
                                        $alturaNecessaria = $top + $altura;
                                        
                                        if ($alturaNecessaria > $maxTopAtual) {
                                            // A aula ultrapassa o limite atual - ajustar horaMaxima para incluir a aula completa
                                            $minutosAdicionais = ceil(($alturaNecessaria - $maxTopAtual) / (50 / 30));
                                            $horaMaxima = $horaMaxima + $minutosAdicionais;
                                            
                                            // Recalcular altura total
                                            $alturaTotalMinutos = $horaMaxima - $horaMinima;
                                            $alturaTotalPx = $alturaTotalMinutos * (50 / 30);
                                            
                                            echo "<!-- AJUSTE: Aula ID {$aula['id']} ultrapassava limite. horaMaxima ajustada de " . sprintf('%02d:%02d', floor(($horaMaxima - $minutosAdicionais)/60), ($horaMaxima - $minutosAdicionais)%60) . " para " . sprintf('%02d:%02d', floor($horaMaxima/60), $horaMaxima%60) . " -->";
                                        }
                                        
                                        // Debug da posição para diagnóstico
                                        echo "<!-- AULA ID {$aula['id']}: top={$top}px, altura={$altura}px, inicio={$evento['inicio']}min (" . sprintf('%02d:%02d', floor($evento['inicio']/60), $evento['inicio']%60) . "), fim={$evento['fim']}min (" . sprintf('%02d:%02d', floor($evento['fim']/60), $evento['fim']%60) . "), duracao={$duracaoMinutos}min, horaMinima={$horaMinima}min (" . sprintf('%02d:%02d', floor($horaMinima/60), $horaMinima%60) . ") -->";
                                        
                                        $horaInicioStr = $aula['hora_inicio'];
                                        $horaFimStr = $aula['hora_fim'];
                                        if (strlen($horaInicioStr) == 8) {
                                            $horaInicioStr = substr($horaInicioStr, 0, 5);
                                        }
                                        if (strlen($horaFimStr) == 8) {
                                            $horaFimStr = substr($horaFimStr, 0, 5);
                                        }
                                    ?>
                                    <?php
                                    // Usar cor de fundo clara para melhor legibilidade
                                    $corFundo = $coresFundoDisciplinas[$aula['disciplina_id']] ?? '#f1f3f4';
                                    $corBorda = $corDisciplina;
                                    ?>
                                    <?php
                                        $statsTooltip = $estatisticasDisciplinas[$aula['disciplina_id']] ?? ['agendadas' => 0, 'obrigatorias' => 0];
                                        $tooltipAgendadas = (int)($statsTooltip['agendadas'] ?? 0);
                                        $tooltipObrigatorias = (int)($statsTooltip['obrigatorias'] ?? 0);
                                        $tooltipLabel = htmlspecialchars($nomeDisciplina) . ' ' . $tooltipAgendadas . '/' . $tooltipObrigatorias . ' (' . $horaInicioStr . ' - ' . $horaFimStr . ')';
                                    ?>
                                    <div class="timeline-slot aula <?= $ehUltima ? 'ultima' : '' ?> calendario-aula"
                                         data-periodo="<?= $periodoAula ?>"
                                         style="top: <?= $top ?>px; height: <?= $altura ?>px; background: <?= $corFundo ?>; border-left-color: <?= $corBorda ?>; position: absolute; left: 2px; right: 2px; z-index: 10; overflow: hidden; box-sizing: border-box; display: block !important;"
                                         onclick="verDetalhesAula(<?= $aula['id'] ?>)"
                                         data-aula-id="<?= $aula['id'] ?>"
                                         data-disciplina-id="<?= $aula['disciplina_id'] ?>"
                                         data-inicio-minutos="<?= $evento['inicio'] ?>"
                                         data-fim-minutos="<?= $evento['fim'] ?>"
                                         data-top-original="<?= $top ?>"
                                         data-data="<?= $data ?>"
                                         data-hora-inicio="<?= $horaInicioStr ?>"
                                         data-hora-fim="<?= $horaFimStr ?>"
                                         title="<?= $tooltipLabel ?>">
                                        <div style="font-weight: 500; margin-bottom: 2px; font-size: 12px; color: #202124;">
                                            <?= htmlspecialchars($nomeDisciplina) ?>
                                        </div>
                                        <div style="font-size: 10px; color: #5f6368; margin-top: 2px;">
                                            <?= $horaInicioStr ?> - <?= $horaFimStr ?>
                                        </div>
                                    </div>
                                    <?php
                                            $ultimoFim = $evento['fim'];
                                        }
                                    }
                                    
                                    // Slot vazio final (se houver espaço significativo após última aula)
                                    // Só criar se houver pelo menos 1 hora de espaço
                                    if ($ultimoFim < $horaMaxima && $horaMaxima - $ultimoFim >= 60) {
                                        $top = ($ultimoFim - $horaMinima) * (50 / 30);
                                        $altura = ($horaMaxima - $ultimoFim) * (50 / 30);
                                        $horaSlot = sprintf('%02d:%02d', floor($ultimoFim / 60), $ultimoFim % 60);
                                        
                                        // Determinar período do slot final
                                        $periodoSlotFinal = '';
                                        if ($ultimoFim < 12 * 60) {
                                            $periodoSlotFinal = 'manhã';
                                        } elseif ($ultimoFim < 18 * 60) {
                                            $periodoSlotFinal = 'tarde';
                                        } else {
                                            $periodoSlotFinal = 'noite';
                                        }
                                        // REMOVIDO: Verificação de colapso para slot final
                                    ?>
                                    <div class="timeline-slot vazio slot-intervalo calendario-slot-vazio" 
                                         data-periodo="<?= $periodoSlotFinal ?>"
                                         style="top: <?= $top ?>px; height: <?= $altura ?>px; position: absolute; left: 2px; right: 2px; z-index: 5; pointer-events: none;"
                                         onclick="agendarNoSlot('<?= $data ?>', '<?= $horaSlot ?>')"
                                         data-data="<?= $data ?>"
                                         data-hora-inicio="<?= $horaSlot ?>"
                                         title="Clique para agendar aula a partir de <?= $horaSlot ?>">
                                    </div>
                                    <?php
                                    }
                                    // CRÍTICO: Criar slots individuais de 30 minutos para TODOS os horários disponíveis
                                    // Isso garante comportamento igual ao Google Calendar: cada slot é clicável independentemente
                                    // IMPORTANTE: Desabilitar slots para datas fora do período da turma
                                    $dataDateTime = new DateTime($data);
                                    $dataForaPeriodo = ($dataDateTime < $dataInicio || $dataDateTime > $dataFim);
                                    $horaAtualSlot = $horaMinima;
                                    // Limite máximo: 23:00 (1380 minutos) - garantir exatamente 24 slots (00-23)
                                    $horaMaximaSlot = min($horaMaxima, 23 * 60);
                                    while ($horaAtualSlot < $horaMaximaSlot) {
                                        // Verificar se este horário já está coberto por uma aula
                                        $estaCobertoPorAula = false;
                                        foreach ($eventos as $evento) {
                                            if ($evento['inicio'] <= $horaAtualSlot && $evento['fim'] > $horaAtualSlot) {
                                                $estaCobertoPorAula = true;
                                                break;
                                            }
                                        }
                                        
                                        // Se não está coberto por aula, criar slot clicável de 30 minutos
                                        if (!$estaCobertoPorAula) {
                                            $top = ($horaAtualSlot - $horaMinima) * (50 / 30);
                                            $altura = 50; // 30 minutos = 50px (densidade compacta: ~1.67px por minuto)
                                            $horaSlot = sprintf('%02d:%02d', floor($horaAtualSlot / 60), $horaAtualSlot % 60);
                                            
                                            // Determinar período
                                            $periodoSlot = '';
                                            if ($horaAtualSlot < 12 * 60) {
                                                $periodoSlot = 'manhã';
                                            } elseif ($horaAtualSlot < 18 * 60) {
                                                $periodoSlot = 'tarde';
                                            } else {
                                                $periodoSlot = 'noite';
                                            }
                                            
                                            // Se data está fora do período, desabilitar slot (visível mas não clicável)
                                            $classeSlot = $dataForaPeriodo ? 'slot-individual slot-desabilitado' : 'slot-individual';
                                            $estiloSlot = $dataForaPeriodo ? 'opacity: 0.3; cursor: not-allowed;' : '';
                                            $onclickSlot = $dataForaPeriodo ? '' : "onclick=\"agendarNoSlot('{$data}', '{$horaSlot}')\"";
                                            $titleSlot = $dataForaPeriodo ? 'Data fora do período da turma' : "Clique para agendar aula às {$horaSlot}";
                                    ?>
                                    <div class="timeline-slot vazio <?= $classeSlot ?> calendario-slot-vazio" 
                                         data-periodo="<?= $periodoSlot ?>"
                                         data-data="<?= $data ?>"
                                         data-hora-inicio="<?= $horaSlot ?>"
                                         data-fora-periodo="<?= $dataForaPeriodo ? '1' : '0' ?>"
                                         style="top: <?= $top ?>px; height: <?= $altura ?>px; position: absolute; left: 2px; right: 2px; z-index: 15; <?= $estiloSlot ?>"
                                         <?= $onclickSlot ?>
                                         title="<?= $titleSlot ?>">
                                    </div>
                                    <?php
                                        }
                                        
                                        // Avançar 30 minutos
                                        $horaAtualSlot += 30;
                                        
                                        // Parar exatamente em 23:00 (não ultrapassar para evitar slot extra)
                                        if ($horaAtualSlot > 23 * 60) {
                                            break;
                                        }
                                    }
                                    ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- CRÍTICO: Garantir que não há espaçamento extra após o calendário -->
        <style>
            #tab-calendario > div:last-child {
                margin-bottom: 0 !important;
                padding-bottom: 0 !important;
            }
            #tab-calendario > div:last-child > div:last-child {
                margin-bottom: 0 !important;
                padding-bottom: 0 !important;
            }
        </style>
    </div>

    <!-- Aba Estatísticas -->
    <div id="tab-estatisticas" class="tab-content">
        <!-- Estatísticas Gerais da Turma -->
        <div class="estatisticas-wrapper">
            <div class="estatisticas-container">
            <?php
                $totalAulasObrigatorias = 0;
                $totalAulasAgendadasSoma = 0;
                $totalAulasRealizadasSoma = 0;
                
                foreach ($estatisticasDisciplinas as $stats) {
                    $totalAulasObrigatorias += $stats['obrigatorias'] ?? 0;
                    $totalAulasAgendadasSoma += $stats['agendadas'] ?? 0;
                    $totalAulasRealizadasSoma += $stats['realizadas'] ?? 0;
                }
                
                $totalAulasFaltantesSoma = max($totalAulasObrigatorias - $totalAulasAgendadasSoma, 0);
                
                $totalCursoAulas = $totalMinutosAgendados > 0 ? ($totalMinutosAgendados / 50) : 0;
                $totalCursoAulasDisplay = fmod($totalCursoAulas, 1) === 0.0
                    ? number_format($totalCursoAulas, 0, ',', '.')
                    : number_format($totalCursoAulas, 1, ',', '.');
                
                $totalMetaAulas = $totalAulasObrigatorias ?: ($totalMinutosCurso > 0 ? round($totalMinutosCurso / 50) : 0);
                $totalMetaAulasDisplay = number_format($totalMetaAulas, 0, ',', '.');
                
                $statCards = [
                    [
                        'value' => $totalAulasDetalhes,
                        'display' => number_format($totalAulasDetalhes, 0, ',', '.'),
                        'label' => 'Aulas Agendadas',
                        'icon' => 'fas fa-calendar-alt',
                        'tooltip' => 'Nenhuma aula foi agendada ainda.',
                    ],
                    [
                        'value' => $totalCursoAulas,
                        'display' => $totalCursoAulasDisplay,
                        'label' => 'Total do Curso (aulas)',
                        'icon' => 'fas fa-layer-group',
                        'tooltip' => 'Nenhuma aula cadastrada para o curso.',
                    ],
                    [
                        'value' => $totalMetaAulas,
                        'display' => $totalMetaAulasDisplay,
                        'label' => 'Obrigatória (meta em aulas)',
                        'icon' => 'fas fa-bullseye',
                        'tooltip' => 'Configure as disciplinas da turma para definir a meta obrigatória.',
                    ],
                    [
                        'value' => $totalAlunos,
                        'display' => number_format($totalAlunos, 0, ',', '.'),
                        'label' => 'Alunos Matriculados',
                        'icon' => 'fas fa-users',
                        'tooltip' => 'Nenhum aluno matriculado nesta turma ainda.',
                    ],
                ];
            ?>
            
            <?php foreach ($statCards as $card): 
                $hasTooltip = floatval($card['value']) <= 0;
                $titleAttr = $hasTooltip ? ' title="' . htmlspecialchars($card['tooltip']) . '"' : '';
                $tooltipAttr = $hasTooltip ? ' data-has-tooltip="true"' : '';
                $labelUpper = function_exists('mb_strtoupper')
                    ? mb_strtoupper($card['label'], 'UTF-8')
                    : strtoupper($card['label']);
            ?>
                        <div class="stat-card"<?= $titleAttr . $tooltipAttr; ?>>
                            <div class="stat-header">
                                <span class="stat-icon">
                                    <i class="<?= htmlspecialchars($card['icon']); ?>"></i>
                                </span>
                                <span class="stat-number"><?= htmlspecialchars($card['display']); ?></span>
                            </div>
                            <span class="stat-label"><?= htmlspecialchars($labelUpper); ?></span>
                        </div>
            <?php endforeach; ?>
        </div>
        </div>

        <!-- Progresso das Disciplinas -->
        <?php
        $percentualGeral = $totalAulasObrigatorias > 0 ? round(($totalAulasAgendadasSoma / $totalAulasObrigatorias) * 100, 1) : 0;
        $percentualDisplay = number_format($percentualGeral, 1, '.', '');
        ?>

        <div class="estatisticas-progress">
            <div class="estatisticas-progress-header">
                <h4 class="estatisticas-progress-title">
                    <i class="fas fa-chart-line"></i>
                    Progresso da Turma
                </h4>
                <div class="estatisticas-progress-meta">
                    <span id="total-aulas-texto" class="progress-badge">
                        <?= $totalAulasAgendadasSoma ?>/<?= $totalAulasObrigatorias ?> aulas agendadas
                    </span>
                    <span class="progress-chip">
                        <i class="fas fa-percentage"></i>
                        <span id="percentual-geral"><?= $percentualDisplay ?>%</span>
                    </span>
                    <span class="progress-chip">
                        Realizadas:
                        <strong id="total-realizadas"><?= $totalAulasRealizadasSoma ?></strong>
                    </span>
                    <span class="progress-chip">
                        Faltantes:
                        <strong id="total-faltantes"><?= $totalAulasFaltantesSoma ?></strong>
                    </span>
                </div>
            </div>
            <div class="progress-bar" aria-hidden="true">
                <div id="barra-progresso-geral" class="progress-bar-fill" style="width: <?= min($percentualGeral, 100) ?>%;"></div>
            </div>
        </div>
        
        <!-- Lista de Progresso por Disciplina (Otimizada) -->
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($disciplinasSelecionadas as $disciplina): 
                    $disciplinaId = $disciplina['disciplina_id'];
                    $stats = $estatisticasDisciplinas[$disciplinaId] ?? ['agendadas' => 0, 'realizadas' => 0, 'faltantes' => 0, 'obrigatorias' => 0];
                    
                    $percentualDisciplina = $stats['obrigatorias'] > 0 ? round(($stats['agendadas'] / $stats['obrigatorias']) * 100, 1) : 0;
                    
                    $nomeDisciplina = htmlspecialchars($disciplina['nome_disciplina'] ?? $disciplina['nome_original'] ?? 'Disciplina');
                ?>
                <?php
                    $statusColor = '#6c757d';
                    $statusBackground = '#f7f8fa';
                    $statusIconClass = 'fa-minus-circle';
                    $statusLabel = 'Não iniciado';

                    if ($percentualDisciplina >= 100) {
                        $statusColor = '#28a745';
                        $statusIconClass = 'fa-check-circle';
                        $statusLabel = 'Completo';
                    } elseif ($percentualDisciplina >= 75) {
                        $statusColor = '#0d6efd';
                        $statusIconClass = 'fa-flag-checkered';
                        $statusLabel = 'Quase completo';
                    } elseif (($stats['agendadas'] ?? 0) > 0) {
                        $statusColor = '#f2994a';
                        $statusIconClass = 'fa-clock';
                        $statusLabel = 'Em progresso';
                    }
                ?>
                <div id="stats-card-<?= htmlspecialchars($disciplinaId) ?>"
                     class="disciplina-progress-card"
                     data-disciplina-id="<?= htmlspecialchars($disciplinaId) ?>"
                     style="background: <?= $statusBackground ?>; border: 1px solid rgba(0,0,0,0.06); border-left: 4px solid <?= $statusColor ?>; padding: 16px; border-radius: 12px; margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; gap: 16px; align-items: flex-start;">
                        <div style="flex: 1; min-width: 0;">
                <div class="stat-status" style="display: inline-flex; align-items: center; gap: 6px; color: <?= $statusColor ?>; font-weight: 600; margin-bottom: 6px;">
                                <i class="fas <?= $statusIconClass ?>"></i>
                                <span><?= $statusLabel ?></span>
                            </div>
                            <div style="font-weight: 600; color: #023A8D; font-size: 0.95rem;"><?= $nomeDisciplina ?></div>
                        </div>
                        <div style="text-align: right;">
                            <div style="color: #023A8D; font-weight: 600; font-size: 0.85rem;">
                                <?= $stats['agendadas'] ?>/<?= $stats['obrigatorias'] ?> aulas
                            </div>
                            <div class="stat-percentual-valor" style="color: <?= $statusColor ?>; font-weight: 700; font-size: 1.1rem;">
                                <?= $percentualDisciplina ?>%
                            </div>
                        </div>
                    </div>
                    <div class="stat-resumo" style="margin-top: 6px; font-size: 0.8rem; color: #555;">
                        <?= $stats['agendadas'] ?>/<?= $stats['obrigatorias'] ?> aulas (faltam <?= $stats['faltantes'] ?>)
                    </div>
                    <div class="stat-progresso" style="background: #e9ecef; height: 6px; border-radius: 999px; overflow: hidden; margin-top: 8px;">
                        <div class="stat-progresso-barra" style="background: <?= $statusColor ?>; height: 100%; width: <?= min($percentualDisciplina, 100) ?>%; transition: width 0.3s ease;"></div>
                    </div>
                    <div style="display: flex; justify-content: flex-start; gap: 16px; margin-top: 8px; font-size: 0.75rem; color: #555; flex-wrap: wrap;">
                        <span>Agendadas: <strong class="stat-agendadas-valor" style="color: #023A8D;"><?= $stats['agendadas'] ?></strong></span>
                        <span>Realizadas: <strong class="stat-realizadas-valor" style="color: #28a745;"><?= $stats['realizadas'] ?></strong></span>
                        <span>Faltantes: <strong class="stat-faltantes-valor" style="color: #dc3545;"><?= $stats['faltantes'] ?></strong></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<!-- JavaScript para Calendário -->
<script>
function filtrarCalendario() {
    const filtro = document.getElementById('filtro-disciplina-calendario').value;
    const aulas = document.querySelectorAll('.timeline-slot.aula');
    const slots = document.querySelectorAll('.timeline-slot.vazio');
    
    // Se não há filtro, mostrar tudo
    if (!filtro || filtro === 'all') {
        aulas.forEach(aula => aula.style.display = '');
        slots.forEach(slot => slot.style.display = '');
        return;
    }
    
    // Filtrar aulas
    aulas.forEach(aula => {
        const disciplinaId = aula.getAttribute('data-disciplina-id');
        if (disciplinaId === filtro) {
            aula.style.display = '';
        } else {
            aula.style.display = 'none';
        }
    });
    
    // Para slots vazios, manter sempre visíveis para poder agendar
}

function irParaHoje() {
    // Encontrar a semana que contém hoje
    const semanasJson = document.getElementById('semanas-disponiveis').value;
    if (!semanasJson) return;
    
    const semanas = JSON.parse(semanasJson);
    const hoje = new Date();
    const hojeStr = hoje.toISOString().split('T')[0]; // YYYY-MM-DD
    
    // Encontrar índice da semana que contém hoje
    let indiceSemanaHoje = -1;
    for (let i = 0; i < semanas.length; i++) {
        const semana = semanas[i];
        if (semana.dias && semana.dias.includes(hojeStr)) {
            indiceSemanaHoje = i;
            break;
        }
    }
    
    // Se encontrou, navegar para essa semana
    if (indiceSemanaHoje >= 0) {
        const indiceAtual = parseInt(document.getElementById('semana-atual-indice').value);
        const diferenca = indiceSemanaHoje - indiceAtual;
        if (diferenca !== 0) {
            // mudarSemana já chama reexecutarPosRender() após o AJAX
            mudarSemana(diferenca);
        } else {
            // Se já está na semana de hoje, garantir que pós-render está aplicado
            if (typeof window.reexecutarPosRender === 'function') {
                setTimeout(() => {
                    window.reexecutarPosRender();
                }, 33);
            }
        }
    } else {
        // Se não encontrou, mostrar mensagem
        alert('Semana atual não está disponível neste período.');
    }
}

function agendarNoSlot(data, hora) {
    // Quick Event Creation - estilo Google Calendar
    // Ao clicar em um slot vazio, abre modal com data e hora já preenchidos
    
    // Buscar disciplinas disponíveis para seleção
    const disciplinas = <?= json_encode(array_map(function($d) use ($estatisticasDisciplinas) {
        $id = $d['disciplina_id'];
        $stats = $estatisticasDisciplinas[$id] ?? [];
        return [
            'id' => $id,
            'nome' => $d['nome_disciplina'] ?? $d['nome_original'] ?? 'Disciplina',
            'faltantes' => $stats['faltantes'] ?? 0
        ];
    }, $disciplinasSelecionadas)) ?>;
    
    // Verificar se há disciplinas com aulas faltantes
    const disciplinasComFaltantes = disciplinas.filter(d => d.faltantes > 0);
    
    if (disciplinasComFaltantes.length === 0) {
        alert('Todas as disciplinas já têm todas as aulas agendadas!');
        return;
    }
    
    // Selecione a primeira disciplina com mais faltantes como padrão
    disciplinasComFaltantes.sort((a, b) => b.faltantes - a.faltantes);
    const disciplinaPadrao = disciplinasComFaltantes[0];
    
    // Abrir modal com dados pré-preenchidos (Quick Event Creation)
    abrirModalAgendarAulaComDataHora(
        disciplinaPadrao.id,
        disciplinaPadrao.nome,
        '<?= $turma['data_inicio'] ?>',
        '<?= $turma['data_fim'] ?>',
        data,  // Data do slot clicado
        hora   // Hora do slot clicado
    );
}

// Nova função para abrir modal com data e hora pré-preenchidas (Quick Event Creation)
function abrirModalAgendarAulaComDataHora(disciplinaId, disciplinaNome, dataInicio, dataFim, dataPreenchida, horaPreenchida) {
    // Primeiro abre o modal normalmente
    abrirModalAgendarAula(disciplinaId, disciplinaNome, dataInicio, dataFim);
    
    // Usar requestAnimationFrame para garantir que o DOM está pronto
    requestAnimationFrame(() => {
        // Preencher data e hora imediatamente
        const dataInput = document.getElementById('modal_data_aula');
        const horaInput = document.getElementById('modal_hora_inicio');
        const horaFimInput = document.getElementById('modal_hora_fim');
        
        if (dataInput && dataPreenchida) {
            // Garantir formato YYYY-MM-DD
            let dataFormatada = dataPreenchida;
            if (!dataFormatada.includes('-')) {
                // Se veio em formato brasileiro (DD/MM/YYYY), converter
                const partes = dataFormatada.split('/');
                if (partes.length === 3) {
                    dataFormatada = partes[2] + '-' + partes[1].padStart(2, '0') + '-' + partes[0].padStart(2, '0');
                }
            }
            dataInput.value = dataFormatada;
            
            // Disparar evento para atualizar preview se houver
            dataInput.dispatchEvent(new Event('change', { bubbles: true }));
            dataInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        
        if (horaInput && horaPreenchida) {
            // Garantir formato HH:MM
            let horaFormatada = horaPreenchida;
            if (!horaFormatada.includes(':')) {
                // Se veio sem formato, assumir HH:MM
                horaFormatada = horaFormatada.length === 4 ? 
                    horaFormatada.substring(0, 2) + ':' + horaFormatada.substring(2) : 
                    horaFormatada;
            }
            // Garantir formato HH:MM (com 2 dígitos)
            if (horaFormatada.match(/^\d{1,2}:\d{1,2}$/)) {
                const [h, m] = horaFormatada.split(':');
                horaFormatada = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
            }
            
            horaInput.value = horaFormatada;
            
            // Calcular hora fim automaticamente (padrão: 50 minutos)
            if (horaFimInput && horaFormatada) {
                const [hora, minuto] = horaFormatada.split(':').map(Number);
                const dataHoraInicio = new Date();
                dataHoraInicio.setHours(hora, minuto, 0, 0);
                dataHoraInicio.setMinutes(dataHoraInicio.getMinutes() + 50); // Duração padrão: 50 minutos
                
                const horaFimFormatada = String(dataHoraInicio.getHours()).padStart(2, '0') + ':' + 
                                        String(dataHoraInicio.getMinutes()).padStart(2, '0');
                horaFimInput.value = horaFimFormatada;
            }
            
            // Disparar evento para atualizar preview se houver
            horaInput.dispatchEvent(new Event('change', { bubbles: true }));
            horaInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        
        // Atualizar preview após preencher campos
        setTimeout(() => {
            if (typeof atualizarPreviewModal === 'function') {
                atualizarPreviewModal();
            }
            // Fallback para função antiga se existir
            if (typeof atualizarPreviewHorario === 'function') {
                atualizarPreviewHorario();
            }
        }, 150);
        
        // Focar no campo de instrutor para facilitar continuação do preenchimento
        setTimeout(() => {
            const instrutorInput = document.getElementById('modal_instrutor_id');
            if (instrutorInput) {
                instrutorInput.focus();
            }
        }, 200);
    });
}

function verDetalhesAula(aulaId) {
    // Buscar detalhes da aula e abrir modal de edição
    fetch(`api/agendamento-detalhes.php?id=${aulaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.agendamento) {
                const agendamento = data.agendamento;
                // Chamar função de edição com os dados carregados
                editarAgendamento(
                    agendamento.id,
                    agendamento.nome_aula || '',
                    agendamento.data_aula || '',
                    agendamento.hora_inicio || '',
                    agendamento.hora_fim || '',
                    agendamento.instrutor_id || null,
                    agendamento.sala_id || null,
                    agendamento.duracao_minutos || 50,
                    agendamento.observacoes || ''
                );
            } else {
                alert('Erro ao carregar detalhes da aula. Tente novamente.');
            }
        })
        .catch(error => {
            console.error('Erro ao buscar detalhes da aula:', error);
            alert('Erro ao carregar detalhes da aula. Tente novamente.');
        });
}

/**
 * Excluir aula a partir do modal de edição
 */
function excluirAulaDoModal() {
    const aulaIdInput = document.getElementById('modal_aula_id');
    const aulaId = aulaIdInput ? aulaIdInput.value : null;
    
    if (!aulaId) {
        alert('❌ ID da aula não encontrado. Feche e abra o modal novamente.');
        return;
    }
    
    if (!confirm('⚠️ Tem certeza que deseja excluir esta aula?\n\nEsta ação não pode ser desfeita.')) {
        return;
    }
    
    console.log('🗑️ [DEBUG] Excluindo aula do modal:', aulaId);
    
    // Criar FormData para enviar via POST
    const formData = new FormData();
    formData.append('acao', 'cancelar_aula');
    formData.append('aula_id', aulaId);
    
    // Desabilitar botão durante a requisição
    const btnExcluir = document.getElementById('btn_excluir_modal');
    if (btnExcluir) {
        btnExcluir.disabled = true;
        btnExcluir.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Excluindo...';
    }
    
    // Enviar requisição para cancelar a aula via POST
    const url = getBasePath() + '/admin/api/turmas-teoricas.php';
    console.log('🔍 [DEBUG] URL da requisição:', url);
    
    fetch(url, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('🔍 [DEBUG] Status da resposta:', response.status);
        console.log('🔍 [DEBUG] Content-Type:', response.headers.get('content-type'));
        
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('❌ [ERROR] Resposta não é JSON! Content-Type:', contentType);
            return response.text().then(text => {
                console.error('❌ [ERROR] Conteúdo da resposta:', text.substring(0, 500));
                throw new Error('Servidor retornou ' + contentType + ' ao invés de JSON');
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log('🔍 [DEBUG] Dados recebidos:', data);
        if (data.sucesso) {
            // Fechar modal primeiro
            fecharModalAgendarAula();
            
            // Mostrar mensagem e recarregar
            alert('✅ ' + (data.mensagem || 'Aula excluída com sucesso!'));
            location.reload();
        } else {
            alert('❌ Erro: ' + (data.mensagem || 'Não foi possível excluir a aula.'));
            
            // Reabilitar botão
            if (btnExcluir) {
                btnExcluir.disabled = false;
                btnExcluir.innerHTML = '<i class="fas fa-trash"></i> <span style="margin-left: 5px;">Excluir</span>';
            }
        }
    })
    .catch(error => {
        console.error('❌ [ERROR] Erro ao excluir aula:', error);
        alert('❌ Erro ao excluir a aula. Verifique o console para mais detalhes.');
        
        // Reabilitar botão
        if (btnExcluir) {
            btnExcluir.disabled = false;
            btnExcluir.innerHTML = '<i class="fas fa-trash"></i> <span style="margin-left: 5px;">Excluir</span>';
        }
    });
}
function carregarSemanaPorIndice(indice, opcoes = {}) {
    const config = {
        forcarRecarregamento: false,
        apenasSeVazio: false,
        ...opcoes
    };

    const semanasInput = document.getElementById('semanas-disponiveis');
    if (!semanasInput || !semanasInput.value) {
        return;
    }

    let semanas;
    try {
        semanas = JSON.parse(semanasInput.value);
    } catch (error) {
        console.error('Erro ao interpretar semanas disponíveis:', error);
        return;
    }

    if (!Array.isArray(semanas) || indice < 0 || indice >= semanas.length) {
        return;
    }

    const indiceAtualInput = document.getElementById('semana-atual-indice');
    if (indiceAtualInput) {
        indiceAtualInput.value = indice;
    }

    const semana = semanas[indice];
    if (!semana) {
        return;
    }

    // Atualizar cabeçalho da semana
    if (semana.inicio) {
        const inicio = new Date(semana.inicio + 'T00:00:00');
        const fimExclusivo = new Date(semana.inicio + 'T00:00:00');
        fimExclusivo.setDate(fimExclusivo.getDate() + 7);

        const fimInclusivo = new Date(fimExclusivo);
        fimInclusivo.setDate(fimInclusivo.getDate() - 1);

        const meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
        const formatarDataGoogle = (data) => {
            const dia = data.getDate();
            const mes = meses[data.getMonth()];
            const ano = data.getFullYear();
            return dia + ' de ' + mes + ', ' + ano;
        };

        const textoData = inicio.getDate() + ' - ' + formatarDataGoogle(fimInclusivo);
        const infoSemanaEl = document.getElementById('info-semana-atual');
        if (infoSemanaEl) {
            infoSemanaEl.textContent = textoData;
        }

        const dataInicioTurma = document.getElementById('turma-data-inicio')?.value;
        const dataFimTurma = document.getElementById('turma-data-fim')?.value;

        semana.dias.forEach((data, idx) => {
            const dayHeader = document.querySelector(`.timeline-day-header[data-dia-semana="${idx}"]`);
            if (dayHeader && data) {
                const dataObj = new Date(data + 'T00:00:00');
                const dataDisplay = dayHeader.querySelector('.data-display');
                if (dataDisplay) {
                    dataDisplay.textContent = dataObj.getDate();
                }

                const hojeStr = new Date().toISOString().split('T')[0];
                if (data === hojeStr) {
                    dayHeader.classList.add('hoje');
                } else {
                    dayHeader.classList.remove('hoje');
                }

                if (dataInicioTurma && dataFimTurma) {
                    const dataInicio = new Date(dataInicioTurma + 'T00:00:00');
                    const dataFim = new Date(dataFimTurma + 'T00:00:00');
                    const dataForaPeriodo = (dataObj < dataInicio || dataObj > dataFim);

                    if (dataForaPeriodo) {
                        dayHeader.classList.add('dia-fora-periodo');
                    } else {
                        dayHeader.classList.remove('dia-fora-periodo');
                    }
                }
            } else if (dayHeader) {
                const dataDisplay = dayHeader.querySelector('.data-display');
                if (dataDisplay) {
                    dataDisplay.textContent = '-';
                }
                dayHeader.classList.remove('hoje');
                dayHeader.classList.remove('dia-fora-periodo');
            }
        });

        // CRÍTICO: Sempre buscar eventos no primeiro carregamento ou quando forçado
        const calendarioVazio = !document.querySelector('#tab-calendario .timeline-slot.aula');
        const deveBuscarEventos = config.forcarRecarregamento || calendarioVazio || (!config.apenasSeVazio);

        if (deveBuscarEventos) {
            const url = new URL(window.location);
            url.searchParams.set('semana_calendario', indice);
            url.searchParams.set('ajax', '1');
            url.searchParams.set('acao', 'detalhes');

            const calendarioContainer = document.querySelector('.timeline-calendar');
            let loader = document.getElementById('calendario-loader');
            if (!loader && calendarioContainer) {
                loader = document.createElement('div');
                loader.id = 'calendario-loader';
                calendarioContainer.style.position = 'relative';
                calendarioContainer.appendChild(loader);

                if (!document.querySelector('#spinner-style')) {
                    const style = document.createElement('style');
                    style.id = 'spinner-style';
                    style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
                    document.head.appendChild(style);
                }
            }

            if (loader) {
                loader.innerHTML = '<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.9); display: flex; align-items: center; justify-content: center; z-index: 1000;"><div style="text-align: center;"><div style="border: 4px solid #f3f3f3; border-top: 4px solid #023A8D; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto;"></div><p style="margin-top: 10px; color: #023A8D; font-weight: 600;">Carregando...</p></div></div>';
            }

            fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                if (loader) {
                    loader.remove();
                }

                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;

                const novoCalendario = tempDiv.querySelector('#tab-calendario');

                if (novoCalendario) {
                    const calendarioAtual = document.querySelector('#tab-calendario');
                    if (calendarioAtual) {
                        calendarioAtual.innerHTML = novoCalendario.innerHTML;

                        setTimeout(() => {
                            if (typeof window.reexecutarPosRender === 'function') {
                                window.reexecutarPosRender();
                            }
                        }, 33);

                        const metaScroll = config.scrollMeta || window.__calendarioScrollMeta;
                        if (metaScroll) {
                            setTimeout(() => window.focarCalendarioNoAgendamento(metaScroll), 160);
                        }
                    }
                } else {
                    window.location.href = url.toString().replace('&ajax=1', '');
                }
            })
            .catch(() => {
                if (loader) {
                    loader.remove();
                }
                window.location.href = url.toString().replace('&ajax=1', '');
            });
        } else {
            if (typeof window.reexecutarPosRender === 'function') {
                setTimeout(() => window.reexecutarPosRender(), 33);
            }

            const metaScroll = config.scrollMeta || window.__calendarioScrollMeta;
            if (metaScroll) {
                setTimeout(() => window.focarCalendarioNoAgendamento(metaScroll), 160);
            }
        }
    }

    const btnAnterior = document.getElementById('btn-semana-anterior');
    const btnProximo = document.getElementById('btn-semana-proxima');
    if (btnAnterior) {
        btnAnterior.disabled = indice === 0;
    }
    if (btnProximo) {
        btnProximo.disabled = indice === semanas.length - 1;
    }
}

function mudarSemana(direcao) {
    const indiceAtualInput = document.getElementById('semana-atual-indice');
    const indiceAtual = parseInt(indiceAtualInput ? indiceAtualInput.value : '0', 10) || 0;
    const novoIndice = indiceAtual + direcao;
    carregarSemanaPorIndice(novoIndice, { forcarRecarregamento: true });
}

// Flag global para controlar se o calendário já foi inicializado
if (typeof window.calendarioSemanaInicializado === 'undefined') {
    window.calendarioSemanaInicializado = false;
}

if (typeof window.__calendarioScrollMeta === 'undefined') {
    window.__calendarioScrollMeta = null;
}

function normalizarDataParaISOCalendario(valor) {
    if (!valor) {
        return '';
    }

    if (valor instanceof Date) {
        return valor.toISOString().split('T')[0];
    }

    let texto = String(valor).trim();
    if (!texto) {
        return '';
    }

    if (texto.includes('T')) {
        texto = texto.split('T')[0];
    }

    if (texto.includes('-')) {
        const partes = texto.split('-');
        if (partes.length >= 3) {
            const ano = partes[0].padStart(4, '0');
            const mes = partes[1].padStart(2, '0');
            const dia = partes[2].padStart(2, '0');
            return `${ano}-${mes}-${dia}`;
        }
    }

    if (texto.includes('/')) {
        const partes = texto.split('/');
        if (partes.length === 3) {
            const [dia, mes, ano] = partes;
            if (ano && ano.length === 4) {
                return `${ano.padStart(4, '0')}-${mes.padStart(2, '0')}-${dia.padStart(2, '0')}`;
            }
        }
    }

    return texto;
}

function normalizarHoraCalendario(valor) {
    if (!valor) {
        return '';
    }

    let texto = String(valor).trim();
    if (!texto) {
        return '';
    }

    if (texto.includes('T')) {
        const partes = texto.split('T')[1] || '';
        texto = partes;
    }

    if (texto.includes(':')) {
        const partes = texto.split(':');
        const hora = (partes[0] || '00').padStart(2, '0').substring(0, 2);
        const minuto = (partes[1] || '00').padStart(2, '0').substring(0, 2);
        return `${hora}:${minuto}`;
    }

    if (texto.length === 4) {
        return `${texto.substring(0, 2)}:${texto.substring(2, 4)}`;
    }

    if (texto.length === 3) {
        return `0${texto.substring(0, 1)}:${texto.substring(1, 3)}`;
    }

    if (texto.length >= 2) {
        const hora = texto.substring(0, 2).padStart(2, '0');
        const minutos = texto.substring(2, 4) || '00';
        return `${hora}:${minutos.padEnd(2, '0').substring(0, 2)}`;
    }

    return texto;
}

function executarScrollCalendario(meta, tentativa = 0) {
    if (!meta) {
        return;
    }

    if (!meta.__id) {
        meta.__id = `meta-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }
    window.__calendarioScrollMeta = meta;

    const MAX_TENTATIVAS = 8;
    const tentativaAtual = tentativa + 1;
    console.debug('[Calendario][Scroll] Tentativa', tentativaAtual, 'meta:', meta);
    const container = document.querySelector('.timeline-calendar');

    if (!container) {
        console.debug('[Calendario][Scroll] Container .timeline-calendar não encontrado.');
        if (tentativa < MAX_TENTATIVAS) {
            setTimeout(() => executarScrollCalendario(meta, tentativa + 1), 200);
        }
        return;
    }

    const escapeSelector = (typeof CSS !== 'undefined' && typeof CSS.escape === 'function')
        ? CSS.escape
        : function(str) {
            return String(str).replace(/([ #;?%&,.+*~':"!^$[\]()=>|\/@])/g, '\\$1');
        };

    const aulaIds = Array.isArray(meta.aulaIds) ? meta.aulaIds.map(id => String(id)).filter(Boolean) : [];
    const aulasDestacadas = [];

    aulaIds.forEach(id => {
        const elemento = container.querySelector(`.calendario-aula[data-aula-id="${escapeSelector(id)}"]`);
        if (elemento) {
            console.debug('[Calendario][Scroll] Aula encontrada por ID:', id, elemento);
            aulasDestacadas.push(elemento);
        }
    });

    let alvo = null;

    if (aulasDestacadas.length) {
        aulasDestacadas.forEach(elemento => elemento.classList.add('calendario-aula--destaque'));
        alvo = aulasDestacadas.reduce((menor, elemento) => {
            if (!menor) {
                console.debug('[Calendario][Scroll] Utilizando primeira aula destacada como alvo', elemento);
                return elemento;
            }
            return elemento.offsetTop < menor.offsetTop ? elemento : menor;
        }, null);
    }

    const dataISO = normalizarDataParaISOCalendario(meta.data);
    const horaPadronizada = normalizarHoraCalendario(meta.horaInicio || meta.hora);
    console.debug('[Calendario][Scroll] Dados normalizados -> data:', dataISO, 'hora:', horaPadronizada);

    let slotDestacado = null;

    if (!alvo && dataISO && horaPadronizada) {
        const seletorAula = `.calendario-aula[data-data="${dataISO}"][data-hora-inicio="${horaPadronizada}"]`;
        const aulaDireta = container.querySelector(seletorAula);
        if (aulaDireta) {
            console.debug('[Calendario][Scroll] Aula encontrada via data/hora selector:', seletorAula, aulaDireta);
            aulaDireta.classList.add('calendario-aula--destaque');
            aulasDestacadas.push(aulaDireta);
            alvo = aulaDireta;
        } else {
            const seletorSlot = `.calendario-slot-vazio[data-data="${dataISO}"][data-hora-inicio="${horaPadronizada}"], .calendario-slot-vazio[data-data="${dataISO}"][data-hora="${horaPadronizada}"]`;
            slotDestacado = container.querySelector(seletorSlot);
            if (slotDestacado) {
                console.debug('[Calendario][Scroll] Slot encontrado via selector:', seletorSlot, slotDestacado);
                slotDestacado.classList.add('calendario-slot--destaque');
                alvo = slotDestacado;
            }
        }
    }

    if (!alvo) {
        console.debug('[Calendario][Scroll] Nenhum alvo encontrado na tentativa', tentativaAtual);
        if (tentativa < MAX_TENTATIVAS) {
            setTimeout(() => executarScrollCalendario(meta, tentativa + 1), 200);
        } else {
            console.warn('[Calendario][Scroll] Falha ao localizar alvo após', MAX_TENTATIVAS, 'tentativas.', meta);
            window.__calendarioScrollMeta = null;
        }
        return;
    }

    const containerRect = container.getBoundingClientRect();
    const alvoRect = alvo.getBoundingClientRect();
    const offsetTop = (alvoRect.top - containerRect.top) + container.scrollTop;
    const targetScrollTop = Math.max(offsetTop - (container.clientHeight / 2) + (alvoRect.height / 2), 0);
    meta.__targetScrollTop = targetScrollTop;
    console.debug('[Calendario][Scroll] Calculando scroll -> offsetTop:', offsetTop, 'scrollTop desejado:', targetScrollTop);

    function aplicarScroll(forceSmooth = true) {
        if (!window.__calendarioScrollMeta || window.__calendarioScrollMeta.__id !== meta.__id) {
            return;
        }
        const behavior = forceSmooth ? 'smooth' : 'auto';
        if (typeof container.scrollTo === 'function') {
            container.scrollTo({ top: targetScrollTop, behavior });
        } else {
            container.scrollTop = targetScrollTop;
        }
    }

    aplicarScroll(true);

    const enforceAttempts = 20;
    const enforceDelay = 200;
    let enforceCount = 0;

    function reforcarScroll() {
        if (!window.__calendarioScrollMeta || window.__calendarioScrollMeta.__id !== meta.__id) {
            return;
        }
        const difference = Math.abs(container.scrollTop - targetScrollTop);
        if (difference > 8) {
            console.debug('[Calendario][Scroll] Reforçando posicionamento. Atual:', container.scrollTop, 'Alvo:', targetScrollTop);
            aplicarScroll(false);
        }
        if (enforceCount < enforceAttempts) {
            enforceCount += 1;
            setTimeout(reforcarScroll, enforceDelay);
        }
    }

    setTimeout(reforcarScroll, enforceDelay);

    setTimeout(() => {
        aulasDestacadas.forEach(elemento => elemento.classList.remove('calendario-aula--destaque'));
        if (slotDestacado) {
            slotDestacado.classList.remove('calendario-slot--destaque');
        }
    }, 4000);

    if (window.__calendarioScrollReleaseTimer) {
        clearTimeout(window.__calendarioScrollReleaseTimer);
    }
    window.__calendarioScrollReleaseTimer = setTimeout(() => {
        if (window.__calendarioScrollMeta && window.__calendarioScrollMeta.__id === meta.__id) {
            window.__calendarioScrollMeta = null;
        }
    }, enforceAttempts * enforceDelay + 400);
}

window.focarCalendarioNoAgendamento = function(meta) {
    const metaAplicavel = meta || window.__calendarioScrollMeta;
    if (metaAplicavel) {
        executarScrollCalendario(metaAplicavel);
    }
};

window.inicializarCalendarioSemana = function inicializarCalendarioSemana(opcoes = {}) {
    const { forceFetch = false, remeasure = false } = opcoes;
    const indiceAtualInput = document.getElementById('semana-atual-indice');
    const indiceInicial = parseInt(indiceAtualInput ? indiceAtualInput.value : '0', 10) || 0;

    // CRÍTICO: Sempre forçar fetch no primeiro carregamento ou quando solicitado
    const deveForcarFetch = forceFetch || !window.calendarioSemanaInicializado;
    const parametrosCarregamento = deveForcarFetch
        ? { forcarRecarregamento: true }
        : { apenasSeVazio: true };

    carregarSemanaPorIndice(indiceInicial, parametrosCarregamento);

    // Marcar como inicializado após a primeira chamada
    if (!window.calendarioSemanaInicializado) {
        window.calendarioSemanaInicializado = true;
    }

    // Re-medir e re-renderizar se solicitado (importante quando aba estava oculta)
    if (remeasure && typeof window.reexecutarPosRender === 'function') {
        setTimeout(() => window.reexecutarPosRender(), 120);
    }
};

/**
 * Recarrega a semana atual do calendário via AJAX após um novo agendamento.
 * Mantém a semana selecionada e reaplica correções de layout.
 */
window.recarregarCalendario = function recarregarCalendario(opcoes = {}) {
    const indiceAtualInput = document.getElementById('semana-atual-indice');

    if (!indiceAtualInput) {
        // Fallback: inicializar calendário completo caso elementos não estejam no DOM.
        window.inicializarCalendarioSemana({ forceFetch: true, remeasure: true });
        return;
    }

    const indiceAtual = parseInt(indiceAtualInput.value, 10);
    const indiceSeguro = Number.isNaN(indiceAtual) ? 0 : indiceAtual;

    const metaScroll = opcoes.scrollMeta || window.__calendarioScrollMeta || null;
    if (metaScroll) {
        window.__calendarioScrollMeta = metaScroll;
    }

    const configuracoes = {
        forcarRecarregamento: true,
        ...opcoes,
        scrollMeta: metaScroll
    };

    carregarSemanaPorIndice(indiceSeguro, configuracoes);

    if (opcoes.remeasure !== false && typeof window.reexecutarPosRender === 'function') {
        setTimeout(() => window.reexecutarPosRender(), 220);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const url = new URL(window.location);
    const tabParam = url.searchParams.get('tab');
    const abaInicial = tabParam || localStorage.getItem('turmaDetalhesAbaAtiva') || localStorage.getItem('turma-tab-active');

    if (tabParam && tabParam !== localStorage.getItem('turmaDetalhesAbaAtiva')) {
        localStorage.setItem('turmaDetalhesAbaAtiva', tabParam);
        localStorage.setItem('turma-tab-active', tabParam);
    }

    if (abaInicial === 'calendario') {
        requestAnimationFrame(() => {
            window.inicializarCalendarioSemana({ forceFetch: true, remeasure: true });
        });
    }
});
function atualizarCalendarioSemana(novoIndice) {
    // Mostrar indicador de carregamento
    const calendarioContainer = document.querySelector('.timeline-calendar');
    if (calendarioContainer) {
        const loader = document.createElement('div');
        loader.id = 'calendario-loader';
        loader.innerHTML = '<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.9); display: flex; align-items: center; justify-content: center; z-index: 1000;"><div style="text-align: center;"><div style="border: 4px solid #f3f3f3; border-top: 4px solid #023A8D; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto;"></div><p style="margin-top: 10px; color: #023A8D; font-weight: 600;">Carregando...</p></div></div>';
        calendarioContainer.style.position = 'relative';
        calendarioContainer.appendChild(loader);
        
        // Adicionar animação CSS se não existir
        if (!document.querySelector('#spinner-style')) {
            const style = document.createElement('style');
            style.id = 'spinner-style';
            style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
            document.head.appendChild(style);
        }
    }
    
    // Recarregar a página com o novo índice para buscar apenas aulas da semana selecionada
    const url = new URL(window.location);
    url.searchParams.set('semana_calendario', novoIndice);
    window.location.href = url.toString();
    return;
    
    // Código abaixo não será executado (recarrega a página)
    /*
    const semanasJson = document.getElementById('semanas-disponiveis').value;
    const semanas = JSON.parse(semanasJson);
    const semana = semanas[novoIndice];
    
    if (!semana) return;
    
    // Obter dados das aulas já carregados (passados do PHP via script JSON)
    const dadosScript = document.getElementById('dados-calendario');
    if (!dadosScript) {
        console.error('Dados do calendário não encontrados');
        return;
    }
    
    const dados = JSON.parse(dadosScript.textContent);
    const aulasPorData = dados.aulasPorData || {};
    const ultimaAulaPorDisciplina = dados.ultimaAulaPorDisciplina || {};
    const coresDisciplinas = dados.coresDisciplinas || {};
    const disciplinasMap = dados.disciplinasMap || {};
    const horarios = dados.horarios || [];
    */
    
    // Atualizar cabeçalhos das colunas
    const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    semana.dias.forEach((data, idx) => {
        const th = document.querySelector(`th[data-dia-semana="${idx}"]`);
        if (th) {
            if (data) {
                const dataObj = new Date(data + 'T00:00:00');
                const formatarData = (d) => {
                    const dia = String(d.getDate()).padStart(2, '0');
                    const mes = String(d.getMonth() + 1).padStart(2, '0');
                    return dia + '/' + mes;
                };
                th.style.background = '#023A8D';
                th.style.color = 'white';
                const small = th.querySelector('.data-display');
                if (small) small.textContent = formatarData(dataObj);
            } else {
                th.style.background = '#e9ecef';
                th.style.color = '#6c757d';
                const small = th.querySelector('.data-display');
                if (small) small.textContent = '-';
            }
        }
    });
    
    // Atualizar corpo da tabela
    const tbody = document.querySelector('#tab-calendario tbody');
    if (!tbody) return;
    
    // Limpar e reconstruir
    tbody.innerHTML = '';
    
    let periodoAtual = '';
    horarios.forEach(hora => {
        const horaObj = new Date('2000-01-01T' + hora + ':00');
        const periodo = horaObj.getHours() < 12 ? 'Manhã' : (horaObj.getHours() < 18 ? 'Tarde' : 'Noite');
        const mostrarPeriodo = periodo !== periodoAtual;
        periodoAtual = periodo;
        
        const horaFimObj = new Date(horaObj);
        horaFimObj.setMinutes(horaFimObj.getMinutes() + 50);
        const horaFim = String(horaFimObj.getHours()).padStart(2, '0') + ':' + String(horaFimObj.getMinutes()).padStart(2, '0');
        
        const tr = document.createElement('tr');
        
        // Coluna de horário
        const tdHorario = document.createElement('td');
        tdHorario.style.cssText = 'background: #f8f9fa; padding: 8px; text-align: center; font-weight: 600; font-size: 0.85rem; position: sticky; left: 0; z-index: 5; border-right: 2px solid #dee2e6;';
        if (mostrarPeriodo) {
            tdHorario.innerHTML = `<div style="font-size: 0.7rem; color: #6c757d; margin-bottom: 4px;">${periodo}</div><div>${hora} - ${horaFim}</div>`;
        } else {
            tdHorario.innerHTML = `<div>${hora} - ${horaFim}</div>`;
        }
        tr.appendChild(tdHorario);
        
        // Colunas dos dias
        semana.dias.forEach((data, diaIdx) => {
            const td = document.createElement('td');
            td.style.cssText = 'padding: 4px; vertical-align: top;';
            
            if (!data) {
                tr.appendChild(td);
                return;
            }
            
            let temAula = false;
            let aulasNoSlot = [];
            
            if (aulasPorData[data]) {
                Object.keys(aulasPorData[data]).forEach(discId => {
                    aulasPorData[data][discId].forEach(aula => {
                        // Comparar apenas horários (usar base de referência para comparar tempo)
                        const horaSlotMin = hora.split(':').map(Number);
                        const horaSlotMinutos = horaSlotMin[0] * 60 + horaSlotMin[1];
                        
                        const aulaInicioMin = aula.hora_inicio.split(':').map(Number);
                        const aulaInicioMinutos = aulaInicioMin[0] * 60 + aulaInicioMin[1];
                        
                        const aulaFimMin = aula.hora_fim.split(':').map(Number);
                        const aulaFimMinutos = aulaFimMin[0] * 60 + aulaFimMin[1];
                        
                        const horaSlotFimMinutos = horaSlotMinutos + 50;
                        
                        // Verificar sobreposição: slot conflita se começa durante a aula ou a aula começa durante o slot
                        if ((horaSlotMinutos >= aulaInicioMinutos && horaSlotMinutos < aulaFimMinutos) ||
                            (horaSlotMinutos < aulaInicioMinutos && horaSlotFimMinutos > aulaInicioMinutos)) {
                            temAula = true;
                            aulasNoSlot.push(aula);
                        }
                    });
                });
            }
            
            if (temAula && aulasNoSlot.length > 0) {
                const aula = aulasNoSlot[0];
                const ehUltima = ultimaAulaPorDisciplina[aula.disciplina_id]?.id === aula.id;
                const corDisciplina = coresDisciplinas[aula.disciplina_id] || '#023A8D';
                const nomeDisciplina = disciplinasMap[aula.disciplina_id] || 'Disciplina';
                
                const statsTooltip = estatisticasDisciplinasMap?.[aula.disciplina_id] || estatisticasDisciplinasMap?.[String(aula.disciplina_id)] || {};
                const agendadasTooltip = statsTooltip?.agendadas ?? 0;
                const obrigatoriasTooltip = statsTooltip?.obrigatorias ?? 0;
                
                const divAula = document.createElement('div');
                divAula.className = 'calendario-aula';
                divAula.setAttribute('data-aula-id', aula.id);
                divAula.setAttribute('data-disciplina-id', aula.disciplina_id);
                divAula.setAttribute('data-data', data);
                divAula.setAttribute('data-hora-inicio', (aula.hora_inicio || '').substring(0, 5));
                divAula.setAttribute('data-hora-fim', (aula.hora_fim || '').substring(0, 5));
                divAula.style.cssText = `background: ${corDisciplina}; color: white; padding: 6px; border-radius: 4px; font-size: 0.75rem; cursor: pointer; ${ehUltima ? 'border: 2px solid #fff; box-shadow: 0 0 0 2px ' + corDisciplina + ';' : ''}`;
                divAula.onclick = () => verDetalhesAula(aula.id);
                divAula.title = `${nomeDisciplina} ${agendadasTooltip}/${obrigatoriasTooltip} (${aula.hora_inicio} - ${aula.hora_fim})`;
                
                divAula.innerHTML = `
                    <div style="font-weight: 600; margin-bottom: 2px;">${nomeDisciplina.substring(0, 15)}</div>
                    <div style="font-size: 0.7rem;">${aula.hora_inicio} - ${aula.hora_fim}</div>
                `;
                
                td.appendChild(divAula);
            } else {
                const divSlot = document.createElement('div');
                divSlot.className = 'calendario-slot-vazio';
                divSlot.setAttribute('data-data', data);
                divSlot.setAttribute('data-hora', hora);
                divSlot.style.cssText = 'background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 4px; padding: 20px 8px; text-align: center; cursor: pointer; transition: all 0.2s; min-height: 60px; display: flex; align-items: center; justify-content: center;';
                divSlot.onmouseover = function() {
                    this.style.background = '#e9ecef';
                    this.style.borderColor = '#023A8D';
                };
                divSlot.onmouseout = function() {
                    this.style.background = '#f8f9fa';
                    this.style.borderColor = '#dee2e6';
                };
                divSlot.onclick = () => agendarNoSlot(data, hora);
                divSlot.title = 'Clique para agendar aula neste horário';
                divSlot.innerHTML = '<span style="color: #adb5bd; font-size: 0.7rem;">Disponível</span>';
                
                td.appendChild(divSlot);
            }
            
            tr.appendChild(td);
        });
        
        tbody.appendChild(tr);
    });
}

// Event delegation para slots vazios (Quick Event Creation)
// Garante que funciona mesmo quando slots são criados dinamicamente via AJAX
document.addEventListener('click', function(e) {
    // Verificar se o clique foi em um slot vazio
    const slot = e.target.closest('.timeline-slot.vazio');
    if (slot && slot.hasAttribute('data-data') && slot.hasAttribute('data-hora-inicio')) {
        // Verificar se o slot está desabilitado (fora do período)
        const foraPeriodo = slot.getAttribute('data-fora-periodo') === '1';
        if (foraPeriodo) {
            e.preventDefault();
            e.stopPropagation();
            return; // Não fazer nada se estiver fora do período
        }
        
        // Prevenir duplo clique (caso tenha onclick inline também)
        e.preventDefault();
        e.stopPropagation();
        
        // Capturar dados do slot
        const data = slot.getAttribute('data-data');
        const hora = slot.getAttribute('data-hora-inicio');
        
        // Chamar função de Quick Event Creation
        if (data && hora) {
            agendarNoSlot(data, hora);
        }
    }
});
// Inicializar ao carregar
document.addEventListener('DOMContentLoaded', function() {
    // CRÍTICO: Remover faixa em branco abaixo de 23:00 após carregamento completo
    function removerFaixaBranca() {
        const timelineHours = document.querySelector('.timeline-hours');
        const timelineDayColumns = document.querySelectorAll('.timeline-day-column');
        const timelineBody = document.querySelector('.timeline-body');
        const calendarioContainer = document.getElementById('calendario-container');
        const timelineCalendar = document.querySelector('.timeline-calendar');
        
        if (timelineHours) {
            // Encontrar o último marcador de hora (deve ser 23:00)
            const ultimoMarcador = timelineHours.querySelector('.timeline-hour-marker:last-child');
            if (ultimoMarcador) {
                const ultimoMarcadorTop = ultimoMarcador.offsetTop;
                const ultimoMarcadorHeight = ultimoMarcador.offsetHeight;
                // CRÍTICO: Calcular altura exata sem arredondamento - usar Math.floor para evitar subpixel
                const alturaReal = Math.floor(ultimoMarcadorTop + ultimoMarcadorHeight);
                
                // CRÍTICO: Zerar padding-bottom e min-height de TODOS os wrappers
                const wrappers = [calendarioContainer, timelineCalendar, timelineBody, timelineHours];
                wrappers.forEach(wrapper => {
                    if (wrapper) {
                        wrapper.style.paddingBottom = '0px';
                        wrapper.style.marginBottom = '0px';
                    }
                });
                
                // CRÍTICO: Aplicar mesma altura exata (sem variação) para TODOS os containers
                const containers = ['.timeline-body', '.timeline-hours', '.timeline-day-column'];
                containers.forEach(selector => {
                    const elements = document.querySelectorAll(selector);
                    elements.forEach(el => {
                        // Usar altura exata arredondada para evitar diferenças por subpixel
                        el.style.minHeight = alturaReal + 'px';
                        el.style.height = alturaReal + 'px';
                        el.style.maxHeight = alturaReal + 'px';
                        el.style.paddingBottom = '0px';
                        el.style.marginBottom = '0px';
                    });
                });
                
                // CRÍTICO: Zerar margin-bottom do último filho
                const ultimoMarcadorEl = timelineHours.querySelector('.timeline-hour-marker:last-child');
                if (ultimoMarcadorEl) {
                    ultimoMarcadorEl.style.marginBottom = '0px';
                    ultimoMarcadorEl.style.paddingBottom = '0px';
                }
                
                // Limitar pseudo-elemento ::after da coluna de horários
                const styleAfter = document.createElement('style');
                styleAfter.id = 'fix-faixa-branca-after';
                styleAfter.textContent = `
                    .timeline-hours::after {
                        height: ${alturaReal}px !important;
                        max-height: ${alturaReal}px !important;
                        bottom: auto !important;
                    }
                `;
                // Remover estilo anterior se existir
                const estiloAnterior = document.getElementById('fix-faixa-branca-after');
                if (estiloAnterior) estiloAnterior.remove();
                document.head.appendChild(styleAfter);
            }
        }
        
        // Limitar pseudo-elementos ::before das colunas dos dias
        timelineDayColumns.forEach(column => {
            const slots = column.querySelectorAll('.timeline-slot');
            if (slots.length > 0) {
                const ultimoSlot = slots[slots.length - 1];
                const ultimoSlotTop = ultimoSlot.offsetTop;
                const ultimoSlotHeight = ultimoSlot.offsetHeight;
                // CRÍTICO: Usar mesma altura exata (arredondada) para manter sincronização
                const alturaRealColuna = Math.floor(ultimoSlotTop + ultimoSlotHeight);
                
                // Ajustar altura da coluna
                column.style.minHeight = alturaRealColuna + 'px';
                column.style.height = alturaRealColuna + 'px';
                column.style.maxHeight = alturaRealColuna + 'px';
                column.style.paddingBottom = '0px';
                column.style.marginBottom = '0px';
                
                // Zerar margin-bottom do último slot
                ultimoSlot.style.marginBottom = '0px';
                ultimoSlot.style.paddingBottom = '0px';
                
                // Limitar pseudo-elemento ::before desta coluna
                const styleBefore = document.createElement('style');
                styleBefore.id = `fix-faixa-branca-before-${column.id}`;
                styleBefore.textContent = `
                    .timeline-day-column[id="${column.id}"]::before {
                        height: ${alturaRealColuna}px !important;
                        max-height: ${alturaRealColuna}px !important;
                        bottom: auto !important;
                    }
                `;
                // Remover estilo anterior se existir
                const estiloAnterior = document.getElementById(`fix-faixa-branca-before-${column.id}`);
                if (estiloAnterior) estiloAnterior.remove();
                document.head.appendChild(styleBefore);
            }
        });
        
        // CRÍTICO: Garantir que eixo de horas e grade tenham exatamente a mesma altura
        if (timelineHours && timelineBody) {
            const alturaEixo = Math.floor(timelineHours.scrollHeight);
            const alturaGrade = Math.floor(timelineBody.scrollHeight);
            const alturaFinal = Math.max(alturaEixo, alturaGrade);
            
            // Aplicar altura final idêntica para ambos
            timelineHours.style.height = alturaFinal + 'px';
            timelineHours.style.minHeight = alturaFinal + 'px';
            timelineHours.style.maxHeight = alturaFinal + 'px';
            timelineBody.style.height = alturaFinal + 'px';
            timelineBody.style.minHeight = alturaFinal + 'px';
            timelineBody.style.maxHeight = alturaFinal + 'px';
            
            // Aplicar também nas colunas dos dias
            timelineDayColumns.forEach(column => {
                column.style.height = alturaFinal + 'px';
                column.style.minHeight = alturaFinal + 'px';
                column.style.maxHeight = alturaFinal + 'px';
            });
        }
    }
    
    // CRÍTICO: Remover espaçamento extra do wrapper abaixo do calendário
    function removerEspacamentoWrapperCalendario() {
        const tabCalendario = document.getElementById('tab-calendario');
        if (!tabCalendario) return;
        
        // Zerar padding-bottom e margin-bottom de todos os ancestrais diretos
        let elemento = tabCalendario;
        let nivel = 0;
        while (elemento && nivel < 10) { // Verificar até 10 níveis acima para pegar todos os wrappers
            elemento.style.paddingBottom = '0px';
            elemento.style.marginBottom = '0px';
            
            // Verificar se é Grid/Flex e zerar gap
            const display = window.getComputedStyle(elemento).display;
            if (display === 'grid' || display === 'flex') {
                elemento.style.gap = '0px';
                elemento.style.rowGap = '0px';
            }
            
            elemento = elemento.parentElement;
            nivel++;
        }
        
        // CRÍTICO: Zerar padding-bottom de wrappers específicos conhecidos
        const wrappersConhecidos = [
            '.admin-main',
            '.wizard-content',
            '.turma-wizard',
            '.admin-container',
            '.main-content',
            '.content-wrapper',
            '.page-wrapper'
        ];
        
        wrappersConhecidos.forEach(selector => {
            const elementos = document.querySelectorAll(selector);
            elementos.forEach(el => {
                el.style.paddingBottom = '0px';
                el.style.marginBottom = '0px';
                
                // Verificar gap em Grid/Flex
                const display = window.getComputedStyle(el).display;
                if (display === 'grid' || display === 'flex') {
                    el.style.gap = '0px';
                    el.style.rowGap = '0px';
                }
                
                // Zerar margin-bottom do último filho
                const ultimoFilho = el.lastElementChild;
                if (ultimoFilho) {
                    ultimoFilho.style.marginBottom = '0px';
                    ultimoFilho.style.paddingBottom = '0px';
                }
            });
        });
        
        // Zerar margin-bottom do último filho dentro da aba calendário
        const ultimoFilho = tabCalendario.lastElementChild;
        if (ultimoFilho) {
            ultimoFilho.style.marginBottom = '0px';
            ultimoFilho.style.paddingBottom = '0px';
        }
        
        // Remover pseudo-elementos que possam criar espaço
        const style = document.createElement('style');
        style.id = 'fix-wrapper-calendario';
        style.textContent = `
            #tab-calendario::after,
            #tab-calendario::before,
            .tab-content::after,
            .tab-content::before,
            .admin-main::after,
            .admin-main::before,
            .wizard-content::after,
            .wizard-content::before,
            .turma-wizard::after,
            .turma-wizard::before {
                display: none !important;
                content: none !important;
                height: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            #tab-calendario > *:last-child,
            .admin-main > *:last-child,
            .wizard-content > *:last-child,
            .turma-wizard > *:last-child {
                margin-bottom: 0 !important;
                padding-bottom: 0 !important;
            }
        `;
        const estiloAnterior = document.getElementById('fix-wrapper-calendario');
        if (estiloAnterior) estiloAnterior.remove();
        document.head.appendChild(style);
    }
    // CRÍTICO: Garantir alinhamento perfeito entre header e body do calendário
    function garantirAlinhamentoHeaderBody() {
        const timelineHeader = document.querySelector('.timeline-header');
        const timelineBody = document.querySelector('.timeline-body');
        const timelineCalendar = document.querySelector('.timeline-calendar');
        
        if (!timelineHeader || !timelineBody || !timelineCalendar) return;
        
        // CRÍTICO: O scrollbar está no .timeline-calendar, então medir a largura do scrollbar dele
        const scrollbarWidth = timelineCalendar.offsetWidth - timelineCalendar.clientWidth;
        
        // Verificar se scrollbar-gutter é suportado e está funcionando
        const scrollbarGutterSupported = CSS.supports('scrollbar-gutter', 'stable');
        const computedStyle = window.getComputedStyle(timelineCalendar);
        const scrollbarGutterValue = computedStyle.scrollbarGutter;
        
        // Se scrollbar-gutter não está funcionando ou não é suportado, aplicar fallback
        if (!scrollbarGutterSupported || scrollbarGutterValue === 'none' || scrollbarGutterValue === '') {
            if (scrollbarWidth > 0) {
                // Aplicar padding-right no header para compensar o scrollbar
                timelineHeader.style.paddingRight = scrollbarWidth + 'px';
            } else {
                timelineHeader.style.paddingRight = '0px';
            }
        } else {
            // Se scrollbar-gutter está funcionando, garantir que não há padding extra
            timelineHeader.style.paddingRight = '0px';
        }
        
        // CRÍTICO: Garantir que header e body tenham exatamente a mesma largura
        // Medir a largura do body (que está dentro do container com scrollbar)
        const bodyWidth = timelineBody.offsetWidth;
        const headerWidth = timelineHeader.offsetWidth;
        
        // Se houver diferença, ajustar o header para corresponder ao body
        if (Math.abs(bodyWidth - headerWidth) > 1) {
            // Forçar mesma largura do body no header
            timelineHeader.style.width = bodyWidth + 'px';
            timelineHeader.style.maxWidth = bodyWidth + 'px';
            timelineHeader.style.minWidth = bodyWidth + 'px';
        }
        
        // CRÍTICO: Sincronizar larguras das colunas individuais
        const headerColumns = timelineHeader.querySelectorAll('.timeline-day-header');
        const bodyColumns = timelineBody.querySelectorAll('.timeline-day-column');
        
        if (headerColumns.length === bodyColumns.length && headerColumns.length === 7) {
            bodyColumns.forEach((bodyCol, index) => {
                const headerCol = headerColumns[index];
                if (headerCol && bodyCol) {
                    const bodyColWidth = bodyCol.offsetWidth;
                    const headerColWidth = headerCol.offsetWidth;
                    
                    // Se houver diferença, ajustar o header para corresponder ao body
                    if (Math.abs(bodyColWidth - headerColWidth) > 1) {
                        headerCol.style.width = bodyColWidth + 'px';
                        headerCol.style.minWidth = bodyColWidth + 'px';
                        headerCol.style.maxWidth = bodyColWidth + 'px';
                    }
                }
            });
        }
        
        // CRÍTICO: Garantir que a coluna de horários também está alinhada
        const timeColumnHeader = timelineHeader.querySelector('.timeline-time-column');
        const timeColumnBody = timelineBody.querySelector('.timeline-hours');
        
        if (timeColumnHeader && timeColumnBody) {
            const timeColBodyWidth = timeColumnBody.offsetWidth;
            const timeColHeaderWidth = timeColumnHeader.offsetWidth;
            
            if (Math.abs(timeColBodyWidth - timeColHeaderWidth) > 1) {
                timeColumnHeader.style.width = timeColBodyWidth + 'px';
                timeColumnHeader.style.minWidth = timeColBodyWidth + 'px';
                timeColumnHeader.style.maxWidth = timeColBodyWidth + 'px';
            }
        }
    }
    
    // CRÍTICO: Ajustar altura do calendário para ocupar máximo da viewport
    function ajustarAlturaCalendario() {
        const timelineCalendar = document.querySelector('.timeline-calendar');
        if (!timelineCalendar) return;
        
        // Calcular altura dos elementos superiores
        const header = document.querySelector('.google-calendar-header');
        const tabCalendario = document.getElementById('tab-calendario');
        const wrapper = tabCalendario ? tabCalendario.parentElement : null;
        
        let alturaSuperiores = 0;
        if (header) alturaSuperiores += header.offsetHeight;
        
        // Altura do topbar/admin-main (aproximada)
        const adminMain = document.querySelector('.admin-main');
        const paddingTop = adminMain ? parseInt(window.getComputedStyle(adminMain).paddingTop) : 24;
        const topbarHeight = 64; // Altura fixa do topbar
        
        // Calcular altura disponível
        const alturaViewport = window.innerHeight;
        const alturaDisponivel = alturaViewport - topbarHeight - paddingTop - alturaSuperiores - 40; // 40px de margem de segurança
        
        // Aplicar altura ao calendário
        timelineCalendar.style.height = alturaDisponivel + 'px';
        timelineCalendar.style.minHeight = alturaDisponivel + 'px';
        timelineCalendar.style.maxHeight = alturaDisponivel + 'px';
    }
    
    // CRÍTICO: Função para reexecutar todas as funções de pós-render (idempotente)
    // Definir ANTES de ser chamada para estar disponível globalmente
    // Tornar global para poder ser chamada de qualquer lugar (mudarSemana, irParaHoje, etc.)
    window.reexecutarPosRender = function reexecutarPosRender() {
        // Remover observadores antigos se existirem
        if (window.timelineResizeObserver) {
            window.timelineResizeObserver.disconnect();
            window.timelineResizeObserver = null;
        }
        
        // Reexecutar todas as funções de pós-render
        removerFaixaBranca();
        removerEspacamentoWrapperCalendario();
        ajustarAlturaCalendario();
        garantirAlinhamentoHeaderBody();
        
        // Reanexar ResizeObserver no novo body
        const timelineBody = document.querySelector('.timeline-body');
        if (timelineBody && window.ResizeObserver) {
            window.timelineResizeObserver = new ResizeObserver(() => {
                garantirAlinhamentoHeaderBody();
            });
            window.timelineResizeObserver.observe(timelineBody);
        }

        if (window.__calendarioScrollMeta) {
            setTimeout(() => window.focarCalendarioNoAgendamento(window.__calendarioScrollMeta), 150);
        }
    };
    
    // CRÍTICO: MutationObserver para detectar quando o calendário é re-renderizado
    const calendarioContainer = document.querySelector('#tab-calendario');
    if (calendarioContainer && window.MutationObserver) {
        const mutationObserver = new MutationObserver((mutations) => {
            let shouldReexecutar = false;
            
            mutations.forEach((mutation) => {
                // Verificar se houve mudanças no childList (novos elementos adicionados)
                if (mutation.type === 'childList') {
                    // Verificar se um novo .timeline-body foi adicionado
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === 1) { // Element node
                            if (node.classList && node.classList.contains('timeline-body')) {
                                shouldReexecutar = true;
                            }
                            // Verificar filhos também
                            if (node.querySelector && node.querySelector('.timeline-body')) {
                                shouldReexecutar = true;
                            }
                        }
                    });
                }
                
                // Verificar se houve mudanças no subtree (mudanças profundas)
                if (mutation.type === 'childList' && mutation.target.querySelector('.timeline-body')) {
                    shouldReexecutar = true;
                }
            });
            
            // Reexecutar com debounce se necessário
            if (shouldReexecutar) {
                clearTimeout(window.posRenderTimeout);
                window.posRenderTimeout = setTimeout(() => {
                    if (typeof window.reexecutarPosRender === 'function') {
                        window.reexecutarPosRender();
                    }
                }, 33);
            }
        });
        
        // Observar mudanças no childList e subtree
        mutationObserver.observe(calendarioContainer, {
            childList: true,
            subtree: true
        });
        
        // Guardar referência para poder desconectar se necessário
        window.calendarioMutationObserver = mutationObserver;
    }
    
    // CRÍTICO: Usar ResizeObserver para sincronizar larguras em tempo real
    const timelineBody = document.querySelector('.timeline-body');
    if (timelineBody && window.ResizeObserver) {
        window.timelineResizeObserver = new ResizeObserver(() => {
            garantirAlinhamentoHeaderBody();
        });
        window.timelineResizeObserver.observe(timelineBody);
    }
    
    // CRÍTICO: Executar pós-render na carga inicial
    setTimeout(() => {
        if (typeof window.reexecutarPosRender === 'function') {
            window.reexecutarPosRender();
        }
    }, 100);
    
    // Também executar após eventos de redimensionamento
    window.addEventListener('resize', () => {
        if (typeof window.reexecutarPosRender === 'function') {
            window.reexecutarPosRender();
        }
    });
    // O servidor já calcula a semana correta baseada na data_inicio da turma
    // Não precisamos forçar semana 0 aqui
    const semanasJsonElement = document.getElementById('semanas-disponiveis');
    if (!semanasJsonElement) return;
    const semanasJson = semanasJsonElement.value;
    if (!semanasJson) return;
    
    const semanas = JSON.parse(semanasJson);
    const indiceAtual = parseInt(document.getElementById('semana-atual-indice').value) || 0;
    
    // Atualizar estado dos botões
    const btnAnterior = document.getElementById('btn-semana-anterior');
    const btnProximo = document.getElementById('btn-semana-proxima');
    
    if (btnAnterior) {
        btnAnterior.disabled = indiceAtual === 0;
        if (indiceAtual === 0) {
            btnAnterior.style.opacity = '0.5';
            btnAnterior.style.cursor = 'not-allowed';
        } else {
            btnAnterior.style.opacity = '1';
            btnAnterior.style.cursor = 'pointer';
        }
    }
    if (btnProximo) {
        btnProximo.disabled = indiceAtual === semanas.length - 1;
        if (indiceAtual === semanas.length - 1) {
            btnProximo.style.opacity = '0.5';
            btnProximo.style.cursor = 'not-allowed';
        } else {
            btnProximo.style.opacity = '1';
            btnProximo.style.cursor = 'pointer';
        }
    }
});
// Função para expandir/colapsar períodos
window.togglePeriodo = function(periodoNome) {
    const periodo = periodoNome.toLowerCase();
    console.log('togglePeriodo chamado para:', periodo);
    
    // Buscar o toggle - pode estar em qualquer lugar
    const toggleDivs = document.querySelectorAll(`.timeline-toggle-periodo[data-periodo="${periodo}"]`);
    const toggleDiv = toggleDivs[0];
    const icon = document.getElementById('icon-' + periodo);
    
    if (!toggleDiv) {
        console.warn('Toggle não encontrado para período:', periodo);
        console.log('Toggles disponíveis:', document.querySelectorAll('.timeline-toggle-periodo'));
        return;
    }
    
    // Verificar estado atual pelo ícone - se está apontando para direita (0deg ou vazio), está colapsado
    const iconTransform = icon ? icon.style.transform : '';
    const estaColapsado = !iconTransform || iconTransform === '' || iconTransform === 'rotate(0deg)' || iconTransform === 'none';
    
    console.log('Toggle período:', periodo, 'Estado atual (colapsado):', estaColapsado);
    
    // Atualizar ícone
    if (icon) {
        icon.style.transform = estaColapsado ? 'rotate(90deg)' : 'rotate(0deg)';
    }
    
    // Encontrar todos os elementos do período usando o atributo data-periodo
    // IMPORTANTE: Buscar em todas as colunas (horas e dias) e também nos toggles de período
    const markers = document.querySelectorAll(`.timeline-hour-marker[data-periodo="${periodo}"]`);
    const slotsVazios = document.querySelectorAll(`.timeline-slot[data-periodo="${periodo}"], .periodo-slot[data-periodo="${periodo}"]`);
    const aulas = document.querySelectorAll(`.timeline-slot.aula[data-periodo="${periodo}"]`);
    const periodoColapsadoDiv = document.querySelectorAll(`.timeline-periodo-colapsado[data-periodo="${periodo}"]`);
    
    console.log('Elementos encontrados:', {
        markers: markers.length,
        slotsVazios: slotsVazios.length,
        aulas: aulas.length,
        periodoColapsado: periodoColapsadoDiv.length
    });
    
    // IMPORTANTE: Separar aulas dos outros elementos
    // Aulas SEMPRE devem ser visíveis, mesmo se período está colapsado
    const elementosParaColapsar = [...markers, ...slotsVazios];
    
    // Função auxiliar para calcular offset de períodos colapsados
    const calcularOffsetPeriodosColapsados = function() {
        let offsetTotal = 0;
        const periodosOrdem = ['manhã', 'tarde', 'noite'];
        const duracaoPeriodos = {
            'manhã': 6 * 60 * 2, // 6 horas * 60 min * 2px = 720px
            'tarde': 6 * 60 * 2, // 6 horas * 60 min * 2px = 720px
            'noite': 5 * 60 * 2  // 5 horas * 60 min * 2px = 600px (até 23:00)
        };
        
        for (let i = 0; i < periodosOrdem.length; i++) {
            const p = periodosOrdem[i];
            const toggle = document.querySelector(`.timeline-toggle-periodo[data-periodo="${p}"]`);
            const icon = document.getElementById('icon-' + p);
            const iconTransform = icon ? icon.style.transform : '';
            const estaColapsado = !iconTransform || iconTransform === '' || iconTransform === 'rotate(0deg)' || iconTransform === 'none';
            
            if (toggle && estaColapsado && p !== periodo) {
                // Este período está colapsado, adicionar ao offset
                offsetTotal += duracaoPeriodos[p] - 40; // 40px é a altura do toggle quando colapsado
            }
        }
        
        return offsetTotal;
    };
    
    // Função para ajustar posição de todas as aulas baseado em períodos colapsados
    const ajustarPosicaoAulas = function() {
        const todasAulas = document.querySelectorAll('.timeline-slot.aula');
        const periodosOrdem = ['manhã', 'tarde', 'noite'];
        const inicioPeriodos = {
            'manhã': 6 * 60,    // 06:00
            'tarde': 12 * 60,   // 12:00
            'noite': 18 * 60    // 18:00
        };
        
        const fimPeriodos = {
            'manhã': 12 * 60,   // 12:00
            'tarde': 18 * 60,   // 18:00
            'noite': 23 * 60    // 23:00
        };
        
        todasAulas.forEach(aula => {
            const periodoAula = aula.getAttribute('data-periodo');
            // Obter top original do atributo data-top-original, senão do style atual
            const topOriginal = parseFloat(aula.getAttribute('data-top-original')) || parseFloat(aula.style.top) || 0;
            
            // Salvar top original se ainda não estiver salvo
            if (!aula.getAttribute('data-top-original')) {
                aula.setAttribute('data-top-original', topOriginal);
            }
            
            // Calcular offset baseado em períodos anteriores colapsados
            let offset = 0;
            for (let i = 0; i < periodosOrdem.length; i++) {
                const p = periodosOrdem[i];
                if (p === periodoAula) break; // Parar quando chegar no período da aula
                
                const toggle = document.querySelector(`.timeline-toggle-periodo[data-periodo="${p}"]`);
                const icon = document.getElementById('icon-' + p);
                const iconTransform = icon ? icon.style.transform : '';
                const estaColapsado = !iconTransform || iconTransform === '' || iconTransform === 'rotate(0deg)' || iconTransform === 'none';
                
                if (toggle && estaColapsado) {
                    // Este período anterior está colapsado
                    // Calcular altura do período quando expandido
                    const duracaoPeriodo = fimPeriodos[p] - inicioPeriodos[p];
                    const alturaPeriodoExpandido = duracaoPeriodo * (50 / 30); // Densidade compacta: ~1.67px por minuto
                    const alturaPeriodoColapsado = 35; // altura do toggle quando colapsado (reduzida)
                    const alturaEconomizada = alturaPeriodoExpandido - alturaPeriodoColapsado;
                    
                    offset += alturaEconomizada;
                }
            }
            
            // Aplicar offset ao top original
            const topAjustado = Math.max(0, topOriginal - offset);
            aula.style.top = topAjustado + 'px';
            
            console.log(`Aula ${aula.getAttribute('data-aula-id')} (${periodoAula}): topOriginal=${topOriginal}px, offset=${offset}px, topAjustado=${topAjustado}px`);
        });
    };
    // Função para ajustar altura da timeline baseado em períodos colapsados
    const ajustarAlturaTimeline = function() {
        const periodosOrdem = ['manhã', 'tarde', 'noite'];
        const inicioPeriodos = {
            'manhã': 6 * 60,    // 06:00
            'tarde': 12 * 60,   // 12:00
            'noite': 18 * 60    // 18:00
        };
        
        const fimPeriodos = {
            'manhã': 12 * 60,   // 12:00
            'tarde': 18 * 60,   // 18:00
            'noite': 23 * 60    // 23:00
        };
        
        // Obter altura total original (do PHP)
        const timelineBody = document.querySelector('.timeline-body');
        if (!timelineBody) return;
        
        // SEMPRE usar o atributo data-altura-original como fonte da verdade
        let alturaOriginalStr = timelineBody.getAttribute('data-altura-original');
        
        // Se não houver atributo, tentar obter do estilo inline inicial ou usar um valor padrão
        if (!alturaOriginalStr || alturaOriginalStr === '0' || alturaOriginalStr === '') {
            // Tentar obter do primeiro elemento que tenha a altura definida
            const primeiroElementoComAltura = document.querySelector('[data-altura-original]');
            if (primeiroElementoComAltura) {
                alturaOriginalStr = primeiroElementoComAltura.getAttribute('data-altura-original');
            }
            
            // Se ainda não encontrou, calcular baseado no range 06:00-23:00
            if (!alturaOriginalStr || alturaOriginalStr === '0' || alturaOriginalStr === '') {
                alturaOriginalStr = ((23 * 60 - 6 * 60) * (50 / 30)).toString(); // (23:00 - 06:00) * densidade compacta
                // Salvar no atributo para próximas chamadas
                timelineBody.setAttribute('data-altura-original', alturaOriginalStr);
            }
        }
        
        const alturaOriginal = parseFloat(alturaOriginalStr);
        
        // Calcular altura economizada por períodos colapsados
        let alturaEconomizadaTotal = 0;
        periodosOrdem.forEach(p => {
            const toggle = document.querySelector(`.timeline-toggle-periodo[data-periodo="${p}"]`);
            const icon = document.getElementById('icon-' + p);
            const iconTransform = icon ? icon.style.transform : '';
            const estaColapsado = !iconTransform || iconTransform === '' || iconTransform === 'rotate(0deg)' || iconTransform === 'none';
            
            if (toggle && estaColapsado) {
                // Este período está colapsado
                const duracaoPeriodo = fimPeriodos[p] - inicioPeriodos[p];
                const alturaPeriodoExpandido = duracaoPeriodo * 2; // 2px por minuto
                const alturaPeriodoColapsado = 40; // altura do toggle quando colapsado
                const alturaEconomizada = alturaPeriodoExpandido - alturaPeriodoColapsado;
                
                alturaEconomizadaTotal += alturaEconomizada;
            }
        });
        
        // Calcular nova altura
        // Se nenhum período está colapsado, usar altura original completa
        const novaAltura = alturaEconomizadaTotal > 0 
            ? Math.max(0, alturaOriginal - alturaEconomizadaTotal)
            : alturaOriginal;
        
        // Aplicar nova altura aos containers
        const containers = [
            '.timeline-body',
            '.timeline-hours',
            '.timeline-day-column'
        ];
        
        containers.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(el => {
                el.style.minHeight = novaAltura + 'px';
                el.style.height = novaAltura + 'px';
            });
        });
        
        // CRÍTICO: Limitar altura dos pseudo-elementos ao conteúdo real renderizado
        // Garantir que ::after e ::before não criem faixa extra abaixo de 23:00
        const timelineHours = document.querySelector('.timeline-hours');
        const timelineDayColumns = document.querySelectorAll('.timeline-day-column');
        
        if (timelineHours) {
            // Encontrar o último marcador de hora renderizado
            const ultimoMarcador = timelineHours.querySelector('.timeline-hour-marker:last-child');
            if (ultimoMarcador) {
                const ultimoMarcadorTop = ultimoMarcador.offsetTop;
                const ultimoMarcadorHeight = ultimoMarcador.offsetHeight;
                const alturaReal = ultimoMarcadorTop + ultimoMarcadorHeight;
                
                // Ajustar altura dos containers para altura real (sem espaço extra)
                containers.forEach(selector => {
                    const elements = document.querySelectorAll(selector);
                    elements.forEach(el => {
                        // Usar altura real do último elemento, não o min-height calculado
                        el.style.minHeight = alturaReal + 'px';
                        el.style.height = alturaReal + 'px';
                    });
                });
                
                // Limitar pseudo-elemento ::after da coluna de horários
                const style = document.createElement('style');
                style.textContent = `
                    .timeline-hours::after {
                        height: ${alturaReal}px !important;
                        max-height: ${alturaReal}px !important;
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        // Limitar pseudo-elementos ::before das colunas dos dias
        timelineDayColumns.forEach(column => {
            // Encontrar o último slot renderizado nesta coluna
            const slots = column.querySelectorAll('.timeline-slot');
            if (slots.length > 0) {
                const ultimoSlot = slots[slots.length - 1];
                const ultimoSlotTop = ultimoSlot.offsetTop;
                const ultimoSlotHeight = ultimoSlot.offsetHeight;
                const alturaRealColuna = ultimoSlotTop + ultimoSlotHeight;
                
                // Ajustar altura da coluna
                column.style.minHeight = alturaRealColuna + 'px';
                column.style.height = alturaRealColuna + 'px';
                
                // Limitar pseudo-elemento ::before desta coluna
                const style = document.createElement('style');
                style.textContent = `
                    .timeline-day-column[id="${column.id}"]::before {
                        height: ${alturaRealColuna}px !important;
                        max-height: ${alturaRealColuna}px !important;
                    }
                `;
                document.head.appendChild(style);
            }
        });
        
        console.log(`Altura ajustada: original=${alturaOriginal}px, economizada=${alturaEconomizadaTotal}px, nova=${novaAltura}px`);
    };
    
    if (estaColapsado) {
        // Expandir: mostrar todos os elementos do período
        console.log('Expandindo período:', periodo);
        
        // Primeiro, remover display: none explicitamente de TODOS os elementos do período
        elementosParaColapsar.forEach(el => {
            // Remover classe colapsado e adicionar expandido
            el.classList.remove('periodo-colapsado');
            el.classList.add('periodo-expandido');
            
            // CRÍTICO: Remover explicitamente display: none e outros estilos de colapso
            if (el.style) {
                el.style.removeProperty('display');
                el.style.removeProperty('visibility');
                el.style.removeProperty('max-height');
                el.style.removeProperty('opacity');
                el.style.removeProperty('height');
            }
        });
        
        // Garantir que marcadores de hora sejam exibidos como flex
        markers.forEach(el => {
            el.classList.remove('periodo-colapsado');
            el.classList.add('periodo-expandido');
            if (el.style) {
                el.style.removeProperty('display');
                el.style.removeProperty('visibility');
                el.style.display = 'flex';
                el.style.visibility = 'visible';
            }
        });
        
        // Garantir que slots vazios sejam exibidos como block
        slotsVazios.forEach(el => {
            el.classList.remove('periodo-colapsado');
            el.classList.add('periodo-expandido');
            if (el.style) {
                el.style.removeProperty('display');
                el.style.removeProperty('visibility');
                el.style.display = 'block';
                el.style.visibility = 'visible';
            }
        });
        
        // Garantir que aulas também estão visíveis
        aulas.forEach(el => {
            el.classList.remove('periodo-colapsado');
            el.classList.add('periodo-expandido');
            if (el.style) {
                el.style.removeProperty('display');
                el.style.removeProperty('visibility');
                el.style.display = 'block';
                el.style.visibility = 'visible';
                el.style.maxHeight = 'none';
                el.style.opacity = '1';
            }
        });
        
        // Esconder div de período colapsado
        periodoColapsadoDiv.forEach(el => {
            if (el.style) {
                el.style.display = 'none';
            }
        });
    } else {
        // Colapsar: esconder elementos vazios e marcadores, mas MANTER aulas visíveis
        console.log('Colapsando período:', periodo);
        elementosParaColapsar.forEach(el => {
            el.classList.add('periodo-colapsado');
            el.classList.remove('periodo-expandido');
            if (el.style) {
                el.style.display = 'none';
                el.style.visibility = 'hidden';
            }
        });
        
        // CRÍTICO: GARANTIR que aulas permanecem visíveis mesmo com período colapsado
        aulas.forEach(el => {
            el.classList.remove('periodo-colapsado');
            el.classList.add('periodo-expandido');
            if (el.style) {
                el.style.display = 'block';
                el.style.visibility = 'visible';
                el.style.maxHeight = 'none';
                el.style.opacity = '1';
            }
        });
        
        // Mostrar div de período colapsado
        periodoColapsadoDiv.forEach(el => {
            if (el.style) {
                el.style.display = 'block';
            }
        });
    }
    
    // IMPORTANTE: Ajustar posição de todas as aulas após colapsar/expandir
    // E também ajustar altura da timeline para evitar linhas extras
    setTimeout(() => {
        ajustarPosicaoAulas();
        ajustarAlturaTimeline();
        atualizarContadorAulasSemana(); // Atualizar contador após mudanças
    }, 100);
    
    console.log('Ação concluída para período:', periodo);
};

// Função para atualizar o contador de aulas da semana dinamicamente
function atualizarContadorAulasSemana() {
    const contadorElement = document.getElementById('total-aulas-semana');
    if (!contadorElement) return;
    
    // Contar todas as aulas visíveis no calendário
    const aulasVisiveis = document.querySelectorAll('.timeline-slot.aula');
    const total = aulasVisiveis.length;
    
    contadorElement.textContent = total;
    
    console.log('📊 Contador de aulas atualizado:', total);
}

// Inicializar contador quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        atualizarContadorAulasSemana();
    }, 500); // Aguardar um pouco para garantir que todos os elementos foram renderizados
});
</script>

<!-- JavaScript para Sistema de Abas -->
<script>
// A função showTab já foi definida antes dos botões para garantir disponibilidade global
// Se por algum motivo ainda não estiver definida, definir aqui como fallback
if (typeof window.showTab !== 'function') {
    window.showTab = function(tabName) {
        // Esconder todos os conteúdos de abas
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => {
            content.classList.remove('active');
        });
        
        // Remover classe active de todos os botões
        const tabButtons = document.querySelectorAll('.tab-button');
        tabButtons.forEach(button => {
            button.classList.remove('active');
        });
        
        // Se for a aba Calendário, garantir que está na primeira semana
        if (tabName === 'calendario') {
            const url = new URL(window.location);
            const semanaAtual = url.searchParams.get('semana_calendario');
            // Se não há parâmetro ou é inválido, forçar primeira semana (0)
            if (!semanaAtual || parseInt(semanaAtual) < 0) {
                url.searchParams.set('semana_calendario', '0');
                window.history.replaceState({}, '', url);
                // Se realmente não havia parâmetro, recarregar para garantir primeira semana
                if (!semanaAtual) {
                    window.location.href = url.toString();
                    return;
                }
            }
        }
        
        // Mostrar a aba selecionada
        const selectedTab = document.getElementById('tab-' + tabName);
        if (selectedTab) {
            selectedTab.classList.add('active');
        }
        
        if (tabName === 'calendario') {
            requestAnimationFrame(() => {
                if (typeof window.inicializarCalendarioSemana === 'function') {
                    const jaInicializado = window.calendarioSemanaInicializado === true;
                    window.inicializarCalendarioSemana({
                        forceFetch: !jaInicializado,
                        remeasure: true
                    });
                } else if (typeof window.reexecutarPosRender === 'function') {
                    setTimeout(() => window.reexecutarPosRender(), 120);
                }
            });
        }

        // Ativar o botão correspondente
        const buttons = document.querySelectorAll('.tab-button');
        buttons.forEach(button => {
            if (button.onclick && button.onclick.toString().includes("'" + tabName + "'")) {
                button.classList.add('active');
            } else {
                // Método alternativo: verificar pelo texto do botão
                const buttonText = button.textContent.toLowerCase().trim();
                if (buttonText.includes(tabName.toLowerCase())) {
                    button.classList.add('active');
                }
            }
        });
        
        // Salvar aba ativa no localStorage
        localStorage.setItem('turmaDetalhesAbaAtiva', tabName);
        localStorage.setItem('turma-tab-active', tabName);
    };
}

// Salvar a aba ativa no localStorage e garantir primeira semana do calendário
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se precisa garantir primeira semana do calendário
    const url = new URL(window.location);
    const semanaCalendario = url.searchParams.get('semana_calendario');
    const tabParam = url.searchParams.get('tab');
    const abaAtiva = tabParam || localStorage.getItem('turmaDetalhesAbaAtiva');
    
    // Se estiver acessando a aba Calendário pela primeira vez (sem parâmetro), garantir primeira semana
    if ((abaAtiva === 'calendario' || !abaAtiva) && (!semanaCalendario || semanaCalendario === '')) {
        url.searchParams.set('semana_calendario', '0');
        // Usar replaceState para não recarregar se já está na página
        window.history.replaceState({}, '', url);
    }
    
    const savedTab = tabParam || localStorage.getItem('turmaDetalhesAbaAtiva') || localStorage.getItem('turma-tab-active');
    if (savedTab) {
        requestAnimationFrame(() => showTab(savedTab));
    }
    
    // Salvar quando uma aba for clicada e garantir primeira semana para calendário
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        const originalOnclick = button.getAttribute('onclick');
        if (originalOnclick) {
            button.addEventListener('click', function() {
                // Se for a aba calendário e não há parâmetro de semana, garantir primeira semana
                if (originalOnclick.includes('calendario')) {
                    const url = new URL(window.location);
                    if (!url.searchParams.get('semana_calendario')) {
                        url.searchParams.set('semana_calendario', '0');
                        window.history.replaceState({}, '', url);
                    }
                }
                const match = originalOnclick.match(/'([^']+)'/);
                if (match && match[1]) {
                    const tabName = match[1];
                    localStorage.setItem('turma-tab-active', tabName);
                }
            });
        }
    });
});
</script>

<!-- Modal para Inserir Alunos -->
<div id="modalInserirAlunos" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh;">
        <div class="modal-header">
            <h3>
                <i class="fas fa-user-plus"></i>
                Matricular Alunos na Turma
            </h3>
            <button type="button" class="btn-close" onclick="fecharModalInserirAlunos()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Critério de Seleção:</strong> Apenas alunos com exames médico e psicotécnico aprovados serão exibidos.
            </div>
            
            <div id="loadingAlunos" style="display: none; text-align: center; padding: 20px;">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p>Carregando alunos aptos...</p>
            </div>
            
            <div id="listaAlunosAptos">
                <!-- Lista de alunos será carregada aqui -->
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModalInserirAlunos()">
                <i class="fas fa-times"></i>
                Fechar
            </button>
        </div>
    </div>
</div>
<!-- Modal para Agendar Nova Aula -->
<div id="modalAgendarAula" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 1200px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h3 id="modal_titulo">
                <i class="fas fa-calendar-plus"></i>
                Agendar Nova Aula
            </h3>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" 
                        id="btn_excluir_modal" 
                        class="btn btn-sm action-btn-outline-danger" 
                        onclick="excluirAulaDoModal()"
                        style="display: none;"
                        title="Excluir esta aula">
                    <i class="fas fa-trash"></i>
                    <span style="margin-left: 5px;">Excluir</span>
                </button>
                <button type="button" class="btn-close" onclick="fecharModalAgendarAula()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="modal-body" style="display: flex; gap: 20px; flex: 1; overflow: hidden;">
            <!-- Coluna Esquerda: Formulário de Agendamento -->
            <div style="flex: 2; min-width: 0; overflow-y: auto; padding-right: 10px;">
                <form id="formAgendarAulaModal">
                    <input type="hidden" name="acao" id="modal_acao" value="agendar_aula">
                    <input type="hidden" name="ajax" value="true">
                    <input type="hidden" name="turma_id" id="modal_turma_id" value="<?= $turmaId ?>">
                    <input type="hidden" name="disciplina" id="modal_disciplina_id">
                    <input type="hidden" name="aula_id" id="modal_aula_id" value="">
                    <input type="hidden" id="modal_modo" value="criar">
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="modal_disciplina_nome" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                            Disciplina *
                        </label>
                        <input type="text" 
                               id="modal_disciplina_nome" 
                               class="form-control" 
                               readonly 
                               style="background-color: #f8f9fa; cursor: not-allowed;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="modal_instrutor_id" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                            Instrutor *
                        </label>
                        <select id="modal_instrutor_id" name="instrutor_id" class="form-control" required>
                            <option value="">Selecione um instrutor...</option>
                            <?php if (!empty($instrutores)): ?>
                                <?php foreach ($instrutores as $instrutor): ?>
                                    <option value="<?= (int)$instrutor['id'] ?>">
                                        <?= htmlspecialchars($instrutor['nome'] ?? 'Instrutor sem nome') ?>
                                        <?php if (!empty($instrutor['categoria_habilitacao'])): ?>
                                            - <?= htmlspecialchars($instrutor['categoria_habilitacao']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>Nenhum instrutor disponível</option>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($instrutores)): ?>
                            <small class="text-danger" style="display: block; margin-top: 5px;">
                                <i class="fas fa-exclamation-triangle"></i> Nenhum instrutor ativo encontrado. Verifique o cadastro de instrutores.
                            </small>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label for="modal_data_aula" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                                Data da Aula *
                            </label>
                            <input type="date" 
                                   id="modal_data_aula" 
                                   name="data_aula" 
                                   class="form-control" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="modal_hora_inicio" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                                Horário de Início *
                            </label>
                            <input type="time"
                                   id="modal_hora_inicio"
                                   name="hora_inicio"
                                   class="form-control"
                                   placeholder="HH:MM"
                                   step="60"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="modal_quantidade_aulas" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                                Qtd Aulas *
                            </label>
                            <select id="modal_quantidade_aulas" name="quantidade_aulas" class="form-control" required>
                                <option value="1">1 aula</option>
                                <option value="2" selected>2 aulas</option>
                                <option value="3">3 aulas</option>
                                <option value="4">4 aulas</option>
                                <option value="5">5 aulas</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Preview do horário -->
                    <div id="previewHorarioModal" style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 15px; display: none;">
                        <strong style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-clock" style="color: #0d6efd;"></i>
                            Preview do Agendamento:
                        </strong>
                        <div id="previewContentModal" style="margin-top: 8px; font-family: monospace;"></div>
                    </div>
                    
                    <!-- Alerta de conflitos -->
                    <div id="alertaConflitosModal" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 15px; display: none;">
                        <strong style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-exclamation-triangle" style="color: #721c24;"></i>
                            Conflito Detectado:
                        </strong>
                        <div id="conflitosContentModal" style="margin-top: 8px;"></div>
                    </div>
                    
                    <!-- Mensagem de erro/sucesso -->
                    <div id="mensagemAgendamento" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 15px;"></div>
                    
                    <!-- Campo de observações (mostrado apenas na edição) -->
                    <div id="campoObservacoesModal" style="display: none; margin-top: 20px;">
                        <label for="modal_observacoes" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                            Observações
                        </label>
                        <textarea 
                            id="modal_observacoes" 
                            name="observacoes" 
                            class="form-control" 
                            rows="3" 
                            placeholder="Digite observações adicionais sobre o agendamento..."></textarea>
                    </div>
                </form>
            </div>
            
            <!-- Coluna Direita: Estatísticas das Disciplinas -->
            <div style="flex: 1; min-width: 300px; background: #f8f9fa; border-left: 1px solid #dee2e6; padding: 20px; overflow-y: auto; max-height: 100%;">
                <h4 style="color: #023A8D; margin-bottom: 20px; font-size: 1.1rem; font-weight: 600;">
                    Estatísticas das Disciplinas
                </h4>
                
                <div id="estatisticas-disciplinas-modal" style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($disciplinasSelecionadas as $disciplina): 
                        $disciplinaId = $disciplina['disciplina_id'];
                        $stats = $estatisticasDisciplinas[$disciplinaId] ?? ['agendadas' => 0, 'realizadas' => 0, 'faltantes' => 0, 'obrigatorias' => 0];
                        
                        $nomeDisciplina = htmlspecialchars($disciplina['nome_disciplina'] ?? $disciplina['nome_original'] ?? 'Disciplina');
                        $percentual = $stats['obrigatorias'] > 0 ? round(($stats['agendadas'] / $stats['obrigatorias']) * 100, 1) : 0;
                        
                        // Definir cor baseada no progresso
                        if ($percentual >= 100) {
                            $corBarra = '#28a745';
                        } elseif ($percentual >= 75) {
                            $corBarra = '#ffc107';
                        } elseif ($stats['agendadas'] > 0) {
                            $corBarra = '#17a2b8';
                        } else {
                            $corBarra = '#dc3545';
                        }
                    ?>
                    <div class="disciplina-stats-item-modal" 
                         data-disciplina-id="<?= $disciplinaId ?>"
                         style="background: white; padding: 12px; border-left: 3px solid <?= $corBarra ?>; border-radius: 4px; cursor: pointer; transition: all 0.2s;"
                         onclick="selecionarDisciplinaModal('<?= $disciplinaId ?>', '<?= htmlspecialchars($nomeDisciplina, ENT_QUOTES) ?>')">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <div style="width: 8px; height: 8px; border-radius: 50%; background: <?= $corBarra ?>; flex-shrink: 0;"></div>
                            <span style="font-weight: 600; color: #023A8D; font-size: 0.9rem; flex: 1;"><?= $nomeDisciplina ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <span style="color: #023A8D; font-weight: 600; font-size: 0.95rem;">
                                <?= $stats['agendadas'] ?>/<?= $stats['obrigatorias'] ?>
                            </span>
                            <span style="color: <?= $corBarra ?>; font-weight: bold; font-size: 0.9rem;">
                                <?= $percentual ?>%
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #666; margin-top: 4px;">
                            <span>Agendadas: <strong style="color: #023A8D;"><?= $stats['agendadas'] ?></strong></span>
                            <span>Faltantes: <strong style="color: #dc3545;"><?= $stats['faltantes'] ?></strong></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModalAgendarAula()">
                <i class="fas fa-times"></i>
                Cancelar
            </button>
            <button type="button" id="btnVerificarDisponibilidade" class="btn btn-outline-secondary" onclick="verificarDisponibilidadeModal()">
                <i class="fas fa-search"></i> Verificar Disponibilidade
            </button>
            <button type="button" id="btnAgendarAula" class="btn btn-primary" onclick="enviarAgendamentoModal()" disabled>
                <i class="fas fa-plus"></i>
                <span id="btnAgendarTexto">Agendar Aula(s)</span>
            </button>
        </div>
    </div>
</div>


<script>
// ==========================================
// VERIFICAÇÃO E FALLBACK PARA ÍCONES
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se FontAwesome está carregado
    const testIcon = document.createElement('i');
    testIcon.className = 'fas fa-edit';
    testIcon.style.position = 'absolute';
    testIcon.style.left = '-9999px';
    document.body.appendChild(testIcon);
    
    const computedStyle = window.getComputedStyle(testIcon);
    const fontFamily = computedStyle.getPropertyValue('font-family');
    
    // Se FontAwesome não estiver carregado, mostrar fallback
    if (!fontFamily.includes('Font Awesome')) {
        console.log('FontAwesome não detectado, usando fallback Unicode');
        const editButtons = document.querySelectorAll('.btn-outline-primary i.fa-edit');
        editButtons.forEach(function(icon) {
            const fallback = icon.nextElementSibling;
            if (fallback && fallback.tagName === 'SPAN') {
                icon.style.display = 'none';
                fallback.style.display = 'inline';
            }
        });
    }
    
    document.body.removeChild(testIcon);
});

/**
 * ==========================================
 * SISTEMA DE DISCIPLINAS - PÁGINA DE DETALHES
 * Sistema CFC Bom Conselho
 * Versão: <?= time() ?>
 * ==========================================
 */

// ==========================================
// VARIÁVEIS GLOBAIS
// ==========================================
if (typeof contadorDisciplinasDetalhes === 'undefined') { var contadorDisciplinasDetalhes = 1; }
if (typeof disciplinasDisponiveis === 'undefined') { var disciplinasDisponiveis = []; }
if (typeof originalValues === 'undefined') { var originalValues = {}; }
if (typeof autoSaveFlags === 'undefined') { var autoSaveFlags = {}; }

// ==========================================
// FUNÇÃO DE DETECÇÃO DE CAMINHO BASE
// ==========================================
function getBasePath() {
    // Detectar automaticamente o caminho base baseado na URL atual
    const currentPath = window.location.pathname;
    
    console.log('🔧 [DEBUG] getBasePath - currentPath:', currentPath);
    
    if (currentPath.includes('/cfc-bom-conselho/')) {
        console.log('🔧 [DEBUG] getBasePath - retornando /cfc-bom-conselho');
        return '/cfc-bom-conselho';
    } else if (currentPath.includes('/admin/')) {
        console.log('🔧 [DEBUG] getBasePath - retornando string vazia');
        return '';
    } else {
        // Fallback: tentar detectar baseado no host
        const host = window.location.host;
        console.log('🔧 [DEBUG] getBasePath - host:', host);
        
        if (host.includes('localhost') || host.includes('127.0.0.1')) {
            console.log('🔧 [DEBUG] getBasePath - retornando string vazia (localhost)');
            return '';
        } else {
            console.log('🔧 [DEBUG] getBasePath - retornando /cfc-bom-conselho (produção)');
            return '/cfc-bom-conselho';
        }
    }
}

// Constante para o caminho base das APIs
const API_BASE_PATH = getBasePath();
console.log('🔧 [CONFIG] Caminho base detectado:', API_BASE_PATH);

// ==========================================
// FUNÇÕES PRINCIPAIS - DISCIPLINAS
// ==========================================

// Carregar disciplinas disponíveis em todos os selects
function carregarDisciplinasDisponiveis() {
    console.log('📚 [DISCIPLINAS] Carregando disciplinas disponíveis...');
    
    // Usar a mesma API do cadastro
    return fetch(API_BASE_PATH + '/admin/api/disciplinas-clean.php?acao=listar')
        .then(response => {
            console.log('📡 [API] Resposta recebida:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(text => {
            console.log('📄 [API] Resposta bruta:', text);
            
            try {
                const data = JSON.parse(text);
                console.log('📊 [API] Dados parseados:', data);
                
                // Validação robusta - mesma estrutura do cadastro
                if (data && data.sucesso && data.disciplinas) {
                    if (Array.isArray(data.disciplinas)) {
                        console.log('✅ [API] Disciplinas é um array válido com', data.disciplinas.length, 'itens');
                        
                        // Carregar em todos os selects existentes (formulários de edição)
                        const selects = document.querySelectorAll('.disciplina-item select, .disciplina-edit-form select');
                        console.log('🔍 [SELECTS] Encontrados selects:', selects.length);
                        
                        selects.forEach((select, index) => {
                            console.log(`📝 [SELECT ${index}] Processando select...`);
                            
                            // Limpar opções existentes
                            select.innerHTML = '<option value="">Selecione a disciplina...</option>';
                            
                            // Adicionar disciplinas uma por uma - mesma estrutura do cadastro
                            data.disciplinas.forEach((disciplina, discIndex) => {
                                if (disciplina && disciplina.id && disciplina.nome) {
                                    const option = document.createElement('option');
                                    option.value = disciplina.id;
                                    option.textContent = disciplina.nome;
                                    option.dataset.aulas = disciplina.carga_horaria_padrao || 10;
                                    option.dataset.cor = '#007bff';
                                    select.appendChild(option);
                                } else {
                                    console.warn(`⚠️ [SELECT ${index}] Item inválido:`, disciplina);
                                }
                            });
                            
                            console.log(`✅ [SELECT ${index}] Disciplinas adicionadas:`, data.disciplinas.length);
                        });
                        
                        // Selecionar disciplinas já cadastradas
                        // TEMPORARIAMENTE DESABILITADO PARA TESTE
                        // selecionarDisciplinasCadastradas();
                        
                        console.log('✅ [DISCIPLINAS] Disciplinas carregadas com sucesso:', data.disciplinas.length);
                        return data.disciplinas;
                    } else {
                        console.error('❌ [API] Disciplinas não é um array:', typeof data.disciplinas, data.disciplinas);
                        throw new Error('Disciplinas não é um array');
                    }
                } else {
                    console.error('❌ [API] Estrutura de dados inválida:', data);
                    throw new Error('Estrutura de dados inválida');
                }
            } catch (parseError) {
                console.error('❌ [API] Erro ao fazer parse do JSON:', parseError);
                console.error('❌ [API] Texto recebido:', text);
                throw parseError;
            }
        })
        .catch(error => {
            console.error('❌ [DISCIPLINAS] Erro na requisição:', error);
            throw error;
        });
}

// Selecionar disciplinas já cadastradas
function selecionarDisciplinasCadastradas() {
    console.log('🎯 [DISCIPLINAS] Selecionando disciplinas cadastradas...');
    
    // Aguardar um pouco para garantir que as opções foram carregadas
    setTimeout(() => {
        const disciplinaItems = document.querySelectorAll('.disciplina-item[data-disciplina-cadastrada]');
        console.log('🔍 [DISCIPLINAS] Encontrados', disciplinaItems.length, 'itens de disciplina');
        
        disciplinaItems.forEach((item, index) => {
            const disciplinaIdCadastrada = item.getAttribute('data-disciplina-cadastrada');
            const select = item.querySelector('select');
            
            console.log(`🔍 [ITEM ${index}] Disciplina ID cadastrada:`, disciplinaIdCadastrada);
            console.log(`🔍 [ITEM ${index}] Select encontrado:`, !!select);
            console.log(`🔍 [ITEM ${index}] Opções no select:`, select ? select.options.length : 0);
            
            // Pular itens com ID inválido
            if (!disciplinaIdCadastrada || disciplinaIdCadastrada === '0' || disciplinaIdCadastrada === 'null') {
                console.log(`⚠️ [ITEM ${index}] ID inválido ignorado:`, disciplinaIdCadastrada);
                return;
            }
            
            if (select && disciplinaIdCadastrada && disciplinaIdCadastrada !== '0') {
                // Verificar se a opção existe
                const optionExists = Array.from(select.options).some(option => option.value === disciplinaIdCadastrada);
                console.log(`🔍 [ITEM ${index}] Opção existe:`, optionExists);
                
                if (optionExists) {
                    // Selecionar a disciplina cadastrada
                    select.value = disciplinaIdCadastrada;
                    console.log(`✅ [ITEM ${index}] Disciplina selecionada:`, disciplinaIdCadastrada);
                    
                    // Atualizar display da disciplina - usar o índice correto do data-disciplina-id
                    const disciplinaId = item.getAttribute('data-disciplina-id');
                    if (disciplinaId && disciplinaId !== '0' && disciplinaId !== 'null' && disciplinaId !== 'undefined') {
                        const disciplinaIdInt = parseInt(disciplinaId);
                        if (!isNaN(disciplinaIdInt) && disciplinaIdInt > 0) {
                            atualizarDisciplinaDetalhes(disciplinaIdInt);
                        }
                    }
                } else {
                    console.warn(`⚠️ [ITEM ${index}] Opção não encontrada para disciplina:`, disciplinaIdCadastrada);
                    console.warn(`⚠️ [ITEM ${index}] Opções disponíveis:`, Array.from(select.options).map(opt => opt.value));
                }
            }
        });
        
        // Atualizar contador
        atualizarContadorDisciplinasDetalhes();
    }, 500); // Aumentar o tempo para 500ms para garantir que as opções foram carregadas
}
// Atualizar disciplina (igual ao cadastro)
function atualizarDisciplinaDetalhes(disciplinaId) {
    console.log('🔄 [DISCIPLINA] Atualizando disciplina:', disciplinaId, 'tipo:', typeof disciplinaId);
    
    // Garantir que disciplinaId é um número válido
    disciplinaId = parseInt(disciplinaId);
    if (isNaN(disciplinaId) || disciplinaId <= 0) {
        console.error('❌ [DISCIPLINA] ID da disciplina inválido:', disciplinaId, 'tipo:', typeof disciplinaId);
        console.error('❌ [DISCIPLINA] Stack trace:', new Error().stack);
        return;
    }
    
    const disciplinaSelect = document.querySelector(`[data-disciplina-id="${disciplinaId}"] select`);
    if (!disciplinaSelect) return;
    
    const disciplinaItem = disciplinaSelect.closest('.disciplina-item');
    if (!disciplinaItem) {
        console.warn('⚠️ [DISCIPLINA] Item de disciplina não encontrado para:', disciplinaId);
        return;
    }
    
    const selectedOption = disciplinaSelect.options[disciplinaSelect.selectedIndex];
    const infoElement = disciplinaItem.querySelector('.disciplina-info');
    const horasInput = disciplinaItem.querySelector('.disciplina-horas');
    const horasGroup = disciplinaItem.querySelector('.input-group');
    const horasLabel = disciplinaItem.querySelector('.disciplina-info');
    
    console.log('🔍 [DISCIPLINA] Elementos encontrados:', {
        disciplinaSelect: !!disciplinaSelect,
        disciplinaItem: !!disciplinaItem,
        selectedOption: !!selectedOption,
        infoElement: !!infoElement,
        horasInput: !!horasInput,
        horasGroup: !!horasGroup,
        horasLabel: !!horasLabel
    });
    
    if (selectedOption.value) {
        const aulas = selectedOption.dataset.aulas || '0';
        const cor = selectedOption.dataset.cor || '#007bff';
        
        // Mostrar informações
        if (infoElement) {
            infoElement.style.display = 'block';
            const aulasElement = infoElement.querySelector('.aulas-obrigatorias');
            if (aulasElement) {
                aulasElement.textContent = aulas;
            }
        }
        
        // Mostrar campo de horas e configurar valor padrão
        if (horasInput && horasGroup && horasLabel) {
            horasInput.value = aulas;
            horasInput.style.display = 'block';
            horasGroup.style.display = 'flex';
            horasLabel.style.display = 'block';
        }
        
        // Mostrar botão de excluir para todas as disciplinas (não apenas ID 0)
        const deleteBtn = disciplinaItem.querySelector('.disciplina-delete-btn');
        if (deleteBtn) {
            deleteBtn.style.display = 'flex';
        }
        
        // Aplicar cor da disciplina
        disciplinaItem.style.borderLeft = '4px solid ' + cor;
        
        console.log('✅ [DISCIPLINA] Disciplina selecionada:', selectedOption.textContent, '(' + aulas + ' aulas padrão)');

        // Auto-save: salva imediatamente a seleção desta disciplina (somente em itens dinâmicos)
        try {
            if (!autoSaveFlags[disciplinaId]) {
                autoSaveFlags[disciplinaId] = true; // evita múltiplos envios
                const cargaToSave = (horasInput && horasInput.value) ? horasInput.value : aulas;
                console.log('💾 [AUTO-SAVE DISCIPLINA] Enviando add_disciplina:', { disciplinaIdSelecionada: selectedOption.value, cargaToSave });
                fetch(API_BASE_PATH + '/admin/api/turmas-teoricas-inline.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add_disciplina',
                        turma_id: <?= $turmaId ?>,
                        disciplina_id: selectedOption.value,
                        carga_horaria: cargaToSave
                    })
                })
                .then(response => {
                    if (!response.ok) { throw new Error(`HTTP ${response.status}: ${response.statusText}`); }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            showFeedback('Disciplina adicionada e salva automaticamente!', 'success');
                            // Não recarregar página - manter interface atual
                        } else {
                            autoSaveFlags[disciplinaId] = false;
                            showFeedback('Erro ao salvar disciplina: ' + (data.message || 'Tente novamente'), 'error');
                        }
                    } catch (e) {
                        autoSaveFlags[disciplinaId] = false;
                        console.error('❌ [AUTO-SAVE DISCIPLINA] Resposta inválida:', text);
                        showFeedback('Erro: Resposta inválida do servidor', 'error');
                    }
                })
                .catch(err => {
                    autoSaveFlags[disciplinaId] = false;
                    console.error('❌ [AUTO-SAVE DISCIPLINA] Erro:', err);
                    showFeedback('Erro ao salvar disciplina: ' + err.message, 'error');
                });
            }
        } catch (e) {
            autoSaveFlags[disciplinaId] = false;
            console.error('❌ [AUTO-SAVE DISCIPLINA] Exceção:', e);
        }
    } else {
        // Esconder informações
        if (infoElement) {
            infoElement.style.display = 'none';
        }
        
        // Esconder campo de horas
        if (horasInput && horasGroup && horasLabel) {
            horasInput.style.display = 'none';
            horasGroup.style.display = 'none';
            horasLabel.style.display = 'none';
            horasInput.value = '';
        }
        
        // Esconder botão de excluir para disciplina principal (ID 0) quando não há seleção
        if (disciplinaId === 0) {
            const deleteBtn = disciplinaItem.querySelector('.disciplina-delete-btn');
            if (deleteBtn) {
                deleteBtn.style.display = 'none';
            }
        }
        
        disciplinaItem.style.borderLeft = '';
    }
    
    // Atualizar contador
    atualizarContadorDisciplinasDetalhes();
}

// Adicionar disciplina adicional
function adicionarDisciplinaDetalhes() {
    console.log('➕ [DISCIPLINA] Adicionando disciplina adicional...');
    
    const container = document.getElementById('disciplinas-container-detalhes');
    if (!container) {
        console.error('❌ [DISCIPLINA] Container não encontrado!');
        return;
    }
    
    const disciplinaHtml = `
        <div class="disciplina-item border rounded p-3 mb-3" data-disciplina-id="${contadorDisciplinasDetalhes}">
            <div class="d-flex align-items-center gap-3 disciplina-row-layout">
                <div class="flex-grow-1 disciplina-field-container">
                    <select class="form-select" name="disciplina_${contadorDisciplinasDetalhes}" onchange="atualizarDisciplinaDetalhes(${contadorDisciplinasDetalhes})">
                        <option value="">Selecione a disciplina...</option>
                    </select>
                </div>
                <div class="flex-shrink-0">
                </div>
            </div>
            
            <!-- Campos ocultos para informações adicionais -->
            <div style="display: none;">
                <div class="input-group mt-2">
                    <input type="number" class="form-control disciplina-horas" 
                           name="disciplina_horas_${contadorDisciplinasDetalhes}" 
                           placeholder="Horas" 
                           min="1" 
                           max="50">
                    <span class="input-group-text">h</span>
                </div>
                <small class="text-muted disciplina-info">
                    <span class="aulas-obrigatorias"></span> aulas (padrão)
                </small>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', disciplinaHtml);
    
    // Carregar disciplinas no novo select com fallback robusto
    carregarDisciplinasNoSelect(contadorDisciplinasDetalhes);
    setTimeout(() => {
        const selectNovo = document.querySelector(`[data-disciplina-id="${contadorDisciplinasDetalhes}"] select`);
        if (selectNovo && selectNovo.options.length <= 1) {
            console.warn('⚠️ [DISCIPLINAS] Recarregando opções do select (fallback)');
            carregarDisciplinasNoSelect(contadorDisciplinasDetalhes);
        }
    }, 800);
    
    contadorDisciplinasDetalhes++;
    atualizarContadorDisciplinasDetalhes();
    
    console.log('✅ [DISCIPLINA] Disciplina adicional adicionada');
}
// Função SIMPLES e DIRETA para carregar disciplinas - SEMPRE funciona
function carregarDisciplinasSimples() {
    console.log('🚀 [SIMPLES] Carregando disciplinas de forma simples e direta...');
    
    fetch(API_BASE_PATH + '/admin/api/disciplinas-clean.php?acao=listar')
        .then(response => response.json())
        .then(data => {
            if (data && data.sucesso && data.disciplinas) {
                console.log('✅ [SIMPLES] Dados recebidos:', data.disciplinas.length, 'disciplinas');
                
                // Buscar TODOS os selects
                const selects = document.querySelectorAll('select');
                console.log('🔍 [SIMPLES] Encontrados', selects.length, 'selects');
                
                selects.forEach((select, index) => {
                    console.log(`🔍 [SIMPLES] Processando select ${index + 1}:`, select.name || select.id);
                    
                    // Limpar e adicionar disciplinas
                    select.innerHTML = '<option value="">Selecione a disciplina...</option>';
                    
                    data.disciplinas.forEach(disciplina => {
                        if (disciplina && disciplina.id && disciplina.nome && disciplina.id !== '0' && disciplina.id !== 0) {
                            const option = document.createElement('option');
                            option.value = disciplina.id;
                            option.textContent = disciplina.nome;
                            select.appendChild(option);
                        }
                    });
                    
                    console.log(`✅ [SIMPLES] Select ${index + 1} populado com ${data.disciplinas.length} disciplinas`);
                });
                
                console.log('✅ [SIMPLES] TODOS os selects foram populados!');
            } else {
                console.error('❌ [SIMPLES] Dados inválidos:', data);
            }
        })
        .catch(error => {
            console.error('❌ [SIMPLES] Erro:', error);
        });
}

// Função que FORÇA o carregamento de todas as disciplinas em TODOS os selects
function forcarCarregamentoDisciplinas() {
    console.log('🚀 [FORÇA] Carregando TODAS as disciplinas em TODOS os selects...');
    
    return fetch(API_BASE_PATH + '/admin/api/disciplinas-clean.php?acao=listar')
        .then(response => {
            console.log('📡 [FORÇA] Resposta da API:', response.status);
            return response.json();
        })
        .then(data => {
            if (data && data.sucesso && data.disciplinas) {
                console.log('✅ [FORÇA] Dados recebidos:', data.disciplinas.length, 'disciplinas');
                console.log('📋 [FORÇA] Disciplinas:', data.disciplinas.map(d => d.nome));
                
                // Buscar TODOS os selects na página
                const allSelects = document.querySelectorAll('select');
                console.log('🔍 [FORÇA] Encontrados', allSelects.length, 'selects na página');
                
                allSelects.forEach((select, index) => {
                    console.log(`🔍 [FORÇA] Processando select ${index + 1}:`, {
                        name: select.name,
                        id: select.id,
                        className: select.className,
                        currentOptions: select.options.length
                    });
                    
                    // Limpar opções existentes
                    select.innerHTML = '<option value="">Selecione a disciplina...</option>';
                    
                    // Adicionar TODAS as disciplinas
                    data.disciplinas.forEach((disciplina, discIndex) => {
                        if (disciplina && disciplina.id && disciplina.nome && disciplina.id !== '0' && disciplina.id !== 0) {
                            const option = document.createElement('option');
                            option.value = disciplina.id;
                            option.textContent = disciplina.nome;
                            option.dataset.aulas = disciplina.carga_horaria_padrao || 10;
                            option.dataset.cor = '#007bff';
                            select.appendChild(option);
                            console.log(`📝 [FORÇA] Adicionada disciplina ${discIndex + 1}: ${disciplina.nome} (ID: ${disciplina.id})`);
                        }
                    });
                    
                    console.log(`✅ [FORÇA] Select ${index + 1} populado com ${data.disciplinas.length} disciplinas`);
                });
                
                return data.disciplinas;
            } else {
                throw new Error('Dados inválidos da API: ' + JSON.stringify(data));
            }
        })
        .catch(error => {
            console.error('❌ [FORÇA] Erro ao carregar disciplinas:', error);
            throw error;
        });
}

// Função de fallback para carregar disciplinas quando a função principal falha
function carregarDisciplinasFallback(disciplinaId) {
    console.log('🔄 [FALLBACK] Usando método de força para:', disciplinaId);
    return forcarCarregamentoDisciplinas();
}

// Carregar disciplinas em um select específico
function carregarDisciplinasNoSelect(disciplinaId) {
    console.log('📚 [DISCIPLINA] Carregando disciplinas para select:', disciplinaId);
    
    // Usar a mesma API do cadastro
    return fetch(API_BASE_PATH + '/admin/api/disciplinas-clean.php?acao=listar')
        .then(response => {
            console.log('📡 [API] Resposta recebida:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(text => {
            console.log('📄 [API] Resposta bruta:', text);
            
            try {
                const data = JSON.parse(text);
                console.log('📊 [API] Dados parseados:', data);
                
                // Validação robusta - mesma estrutura do cadastro
                if (data && data.sucesso && data.disciplinas) {
                    if (Array.isArray(data.disciplinas)) {
                        console.log('✅ [API] Disciplinas é um array válido com', data.disciplinas.length, 'itens');
                        
                        let select;
                        if (disciplinaId === 'nova') {
                            // Elemento não existe nesta página - pular
                            console.log('ℹ️ [SELECT] Elemento nova_disciplina_select não existe nesta página');
                            return Promise.resolve([]);
                        } else if (disciplinaId === '0' || disciplinaId === 0 || disciplinaId === 'null' || disciplinaId === 'undefined') {
                            // ID inválido - pular
                            console.log('ℹ️ [SELECT] ID de disciplina inválido ignorado:', disciplinaId);
                            return Promise.resolve([]);
                        } else {
                            // Buscar o select de forma mais robusta com múltiplos fallbacks
                            console.log('🔍 [SELECT] Buscando select para disciplina:', disciplinaId);
                            
                            // Tentar diferentes estratégias de busca
                            const selectors = [
                                // 1. Formulário de edição específico
                                `.disciplina-edit-form select[name="disciplina_edit_${disciplinaId}"]`,
                                // 2. Qualquer select no card da disciplina
                                `[data-disciplina-id="${disciplinaId}"] select`,
                                // 3. Select dentro de qualquer formulário de edição
                                `[data-disciplina-id="${disciplinaId}"] .disciplina-edit-form select`,
                                // 4. Select com name específico
                                `select[name="disciplina_edit_${disciplinaId}"]`,
                                // 5. Qualquer select que contenha o ID da disciplina
                                `select[name*="${disciplinaId}"]`
                            ];
                            
                            for (let i = 0; i < selectors.length; i++) {
                                select = document.querySelector(selectors[i]);
                                if (select) {
                                    console.log(`✅ [SELECT] Encontrado com seletor ${i + 1}:`, selectors[i]);
                                    break;
                                } else {
                                    console.log(`❌ [SELECT] Seletor ${i + 1} falhou:`, selectors[i]);
                                }
                            }
                            
                            // Se ainda não encontrou, buscar por qualquer select visível
                            if (!select) {
                                console.log('🔍 [SELECT] Tentando busca por qualquer select visível...');
                                const allSelects = document.querySelectorAll('select');
                                for (let s of allSelects) {
                                    if (s.style.display !== 'none' && s.offsetParent !== null) {
                                        console.log('🔍 [SELECT] Select visível encontrado:', s.name, s.className);
                                        // Verificar se está relacionado à disciplina atual
                                        const parentCard = s.closest('[data-disciplina-id]');
                                        if (parentCard && parentCard.getAttribute('data-disciplina-id') === disciplinaId.toString()) {
                                            select = s;
                                            console.log('✅ [SELECT] Select relacionado encontrado!');
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        
                        if (select) {
                            console.log('✅ [SELECT] Select encontrado, adicionando disciplinas...');
                            
                            // Limpar opções existentes
                            select.innerHTML = '<option value="">Selecione a disciplina...</option>';
                            
                            // Adicionar disciplinas uma por uma - mesma estrutura do cadastro
                            data.disciplinas.forEach((disciplina, index) => {
                                if (disciplina && disciplina.id && disciplina.nome && disciplina.id !== '0' && disciplina.id !== 0) {
                                    const option = document.createElement('option');
                                    option.value = disciplina.id;
                                    option.textContent = disciplina.nome;
                                    option.dataset.aulas = disciplina.carga_horaria_padrao || 10;
                                    option.dataset.cor = '#007bff';
                                    select.appendChild(option);
                                    console.log(`📝 [DISCIPLINA ${index + 1}] Adicionada: ${disciplina.nome}`);
                                } else {
                                    console.warn('⚠️ [DISCIPLINA] Item inválido:', disciplina);
                                }
                            });
                            
                            console.log('✅ [SELECT] Disciplinas adicionadas com sucesso');
                            return Promise.resolve(data.disciplinas);
                        } else {
                            console.error('❌ [SELECT] Select não encontrado para disciplina:', disciplinaId);
                            console.log('🔄 [SELECT] Tentando método de fallback...');
                            return carregarDisciplinasFallback(disciplinaId);
                        }
                    } else {
                        console.error('❌ [API] Disciplinas não é um array:', typeof data.disciplinas, data.disciplinas);
                        return Promise.reject('Disciplinas não é um array');
                    }
                } else {
                    console.error('❌ [API] Estrutura de dados inválida:', data);
                    return Promise.reject('Estrutura de dados inválida');
                }
            } catch (parseError) {
                console.error('❌ [API] Erro ao fazer parse do JSON:', parseError);
                console.error('❌ [API] Texto recebido:', text);
                return Promise.reject(parseError);
            }
        })
        .catch(error => {
            console.error('❌ [DISCIPLINA] Erro na requisição:', error);
            return Promise.reject(error);
        });
}


// Atualizar contador de disciplinas
function atualizarContadorDisciplinasDetalhes() {
    const disciplinas = document.querySelectorAll('.disciplina-item select, .disciplina-card, .disciplina-accordion');
    let disciplinasSelecionadas = 0;
    
    disciplinas.forEach(element => {
        if (element.classList.contains('disciplina-card') || element.classList.contains('disciplina-accordion')) {
            // Para cards, verificar se tem disciplina cadastrada
            const disciplinaId = element.getAttribute('data-disciplina-cadastrada') || element.getAttribute('data-disciplina-id');
            if (disciplinaId && disciplinaId !== '0' && disciplinaId !== 'null' && disciplinaId !== 'undefined') {
                const disciplinaIdInt = parseInt(disciplinaId);
                if (!isNaN(disciplinaIdInt) && disciplinaIdInt > 0) {
                    disciplinasSelecionadas++;
                }
            }
        } else {
            // Para selects, verificar se tem valor selecionado
            if (element.value && element.value !== '0' && element.value !== 'null' && element.value !== 'undefined') {
                const valueInt = parseInt(element.value);
                if (!isNaN(valueInt) && valueInt > 0) {
                    disciplinasSelecionadas++;
                }
            }
        }
    });
    
    const contador = document.getElementById('contador-disciplinas-detalhes');
    if (contador) {
        contador.textContent = disciplinasSelecionadas;
    }
    
    console.log('📊 [CONTADOR] Disciplinas selecionadas:', disciplinasSelecionadas);
    
    // Mostrar mensagem se não há disciplinas
    if (disciplinasSelecionadas === 0) {
        console.log('ℹ️ [CONTADOR] Nenhuma disciplina selecionada');
    }
}
// Alternar entre visualização e edição de disciplina
function toggleEditDisciplina(disciplinaId) {
    console.log('🔄 [EDIT] Alternando modo de edição para disciplina:', disciplinaId);
    
    const disciplinaCard = document.querySelector(`[data-disciplina-id="${disciplinaId}"]`);
    if (!disciplinaCard) return;
    
    const editFields = disciplinaCard.querySelector('.disciplina-edit-fields');
    const editButton = disciplinaCard.querySelector('.btn-edit-disciplina');
    
    if (editFields.style.display === 'none' || !editFields.style.display) {
        // Mostrar campos de edição
        editFields.style.display = 'block';
        editButton.innerHTML = '<i class="fas fa-save"></i> Salvar';
        editButton.classList.add('btn-save-mode');
        
        // Carregar disciplinas no select se ainda não foram carregadas
        const select = editFields.querySelector('select');
        if (select && select.options.length <= 1) {
            carregarDisciplinasNoSelect(disciplinaId);
        }
        
        console.log('✅ [EDIT] Modo de edição ativado');
    } else {
        // Ocultar campos de edição
        editFields.style.display = 'none';
        editButton.innerHTML = '<i class="fas fa-edit"></i> Editar';
        editButton.classList.remove('btn-save-mode');
        
        console.log('✅ [EDIT] Modo de visualização ativado');
    }
}

// Funções de edição de disciplinas removidas - disciplinas são automáticas baseadas no tipo de curso

// Funções de edição de disciplinas removidas - disciplinas são automáticas baseadas no tipo de curso

// Funções de edição de disciplinas removidas - disciplinas são automáticas baseadas no tipo de curso

// Funções de adição de disciplinas removidas - disciplinas são automáticas baseadas no tipo de curso

// Funções de adição de disciplinas removidas - disciplinas são automáticas baseadas no tipo de curso

// Funções de adição de disciplinas removidas - disciplinas são automáticas baseadas no tipo de curso

// ==========================================
// SISTEMA DE RELATÓRIO DETALHADO DE DISCIPLINAS
// ==========================================

// Função de teste simples
function testeSimples(disciplinaId) {
    console.log('🧪 [TESTE] Função testeSimples chamada com:', disciplinaId);
    alert('Teste funcionando! ID: ' + disciplinaId);
}

// Função SIMPLES para alternar sanfona
function toggleSimples(disciplinaId) {
    console.log('🔄 [SIMPLES] ===== INÍCIO DA FUNÇÃO =====');
    console.log('🔄 [SIMPLES] Alternando disciplina:', disciplinaId);
    console.log('🔄 [SIMPLES] Tipo do ID:', typeof disciplinaId);
    
    // Verificar se o ID é válido
    if (!disciplinaId) {
        console.error('❌ [SIMPLES] ID da disciplina é vazio ou nulo');
        return;
    }
    
    console.log('🔍 [SIMPLES] Buscando elementos...');
    const disciplinaCard = document.querySelector(`[data-disciplina-id="${disciplinaId}"]`);
    const detalhesContent = document.getElementById(`detalhes-disciplina-${disciplinaId}`);
    
    console.log('🔍 [SIMPLES] Card encontrado:', !!disciplinaCard);
    console.log('🔍 [SIMPLES] Conteúdo encontrado:', !!detalhesContent);
    
    if (!disciplinaCard) {
        console.error('❌ [SIMPLES] Card da disciplina não encontrado para ID:', disciplinaId);
        console.error('❌ [SIMPLES] Tentando buscar todos os cards...');
        const todosCards = document.querySelectorAll('[data-disciplina-id]');
        console.log('❌ [SIMPLES] Total de cards encontrados:', todosCards.length);
        todosCards.forEach((card, index) => {
            console.log(`❌ [SIMPLES] Card ${index + 1}: data-disciplina-id="${card.getAttribute('data-disciplina-id')}"`);
        });
        return;
    }
    
    if (!detalhesContent) {
        console.error('❌ [SIMPLES] Conteúdo da sanfona não encontrado para ID:', disciplinaId);
        return;
    }
    
    console.log('✅ [SIMPLES] Elementos encontrados, verificando estado...');
    const isExpanded = disciplinaCard.classList.contains('expanded');
    console.log('🔍 [SIMPLES] Sanfona expandida:', isExpanded);
    
    if (isExpanded) {
        // Fechar
        console.log('🔽 [SIMPLES] Fechando sanfona...');
        disciplinaCard.classList.remove('expanded');
        detalhesContent.style.display = 'none';
        console.log('✅ [SIMPLES] Sanfona fechada');
    } else {
        // Abrir
        console.log('🔼 [SIMPLES] Abrindo sanfona...');
        disciplinaCard.classList.add('expanded');
        detalhesContent.style.display = 'block';
        
        // Mostrar conteúdo simples
        const dataElement = document.getElementById(`data-disciplina-${disciplinaId}`);
        console.log('🔍 [SIMPLES] Elemento de dados encontrado:', !!dataElement);
        
        if (dataElement) {
            console.log('✅ [SIMPLES] Dados já carregados via PHP para disciplina:', disciplinaId);
        } else {
            console.error('❌ [SIMPLES] Elemento de dados não encontrado');
        }
        
        console.log('✅ [SIMPLES] Sanfona aberta');
    }
    
    console.log('🔄 [SIMPLES] ===== FIM DA FUNÇÃO =====');
}

/**
 * Alternar exibição dos detalhes da disciplina (sanfona)
 * @param {number} disciplinaId - ID da disciplina
 */
function toggleDisciplinaDetalhes(disciplinaId) {
    console.log('🔄 [SANFONA] Alternando disciplina:', disciplinaId, 'tipo:', typeof disciplinaId);
    console.log('🔄 [SANFONA] Função chamada com sucesso!');
    
    // Garantir que disciplinaId é válido (aceita tanto números quanto strings)
    const disciplinaIdInt = parseInt(disciplinaId);
    const isNumericId = !isNaN(disciplinaIdInt) && disciplinaIdInt > 0;
    const isStringId = typeof disciplinaId === 'string' && disciplinaId.trim().length > 0 && disciplinaId !== '0';
    
    if (!isNumericId && !isStringId) {
        console.error('❌ [SANFONA] ID da disciplina inválido:', disciplinaId, 'tipo:', typeof disciplinaId);
        console.error('❌ [SANFONA] Stack trace:', new Error().stack);
        return;
    }
    
    const disciplinaCard = document.querySelector(`[data-disciplina-id="${disciplinaId}"]`);
    const detalhesContent = document.getElementById(`detalhes-disciplina-${disciplinaId}`);
    
    if (!disciplinaCard || !detalhesContent) {
        console.error('❌ [SANFONA] Elementos não encontrados para disciplina:', disciplinaId);
        console.error('❌ [SANFONA] Card encontrado:', !!disciplinaCard);
        console.error('❌ [SANFONA] Conteúdo encontrado:', !!detalhesContent);
        return;
    }
    
    const chevron = disciplinaCard.querySelector('.disciplina-chevron');
    
    const isExpanded = disciplinaCard.classList.contains('expanded');
    
    if (isExpanded) {
        // Fechar sanfona
        disciplinaCard.classList.remove('expanded');
        detalhesContent.style.display = 'none';
        console.log('✅ [SANFONA] Sanfona fechada para disciplina:', disciplinaId);
    } else {
        // Abrir sanfona
        disciplinaCard.classList.add('expanded');
        detalhesContent.style.display = 'block';
        
        // Dados já estão carregados via PHP, não precisa carregar via AJAX
        console.log('✅ [SANFONA] Dados já carregados via PHP para disciplina:', disciplinaId);
        
        console.log('✅ [SANFONA] Sanfona aberta para disciplina:', disciplinaId);
    }
}

/**
 * Carregar detalhes completos da disciplina
 * @param {number} disciplinaId - ID da disciplina
 */
function carregarDetalhesDisciplina(disciplinaId) {
    console.log('📊 [DETALHES] Carregando detalhes da disciplina:', disciplinaId, 'tipo:', typeof disciplinaId);
    console.log('📊 [DETALHES] Função carregarDetalhesDisciplina chamada!');
    
    // Garantir que disciplinaId é válido (aceita tanto números quanto strings)
    const disciplinaIdInt = parseInt(disciplinaId);
    const isNumericId = !isNaN(disciplinaIdInt) && disciplinaIdInt > 0;
    const isStringId = typeof disciplinaId === 'string' && disciplinaId.trim().length > 0 && disciplinaId !== '0';
    
    if (!isNumericId && !isStringId) {
        console.error('❌ [DETALHES] ID da disciplina inválido:', disciplinaId, 'tipo:', typeof disciplinaId);
        console.error('❌ [DETALHES] Stack trace:', new Error().stack);
        return;
    }
    
    const disciplinaCard = document.querySelector(`[data-disciplina-id="${disciplinaId}"]`);
    if (!disciplinaCard) {
        console.error('❌ [DETALHES] Card da disciplina não encontrado:', disciplinaId);
        return;
    }
    
    const turmaId = disciplinaCard.getAttribute('data-turma-id');
    const loadingElement = document.getElementById(`loading-disciplina-${disciplinaId}`);
    const dataElement = document.getElementById(`data-disciplina-${disciplinaId}`);
    
    if (!turmaId) {
        console.error('❌ [DETALHES] ID da turma não encontrado');
        return;
    }
    
    // Mostrar loading
    if (loadingElement) loadingElement.style.display = 'block';
    if (dataElement) dataElement.style.display = 'none';
    
    // Buscar dados da API
    const apiUrl = `${API_BASE_PATH}/admin/api/relatorio-disciplinas.php?acao=aulas_disciplina&turma_id=${turmaId}&disciplina_id=${disciplinaId}`;
    console.log('🌐 [API] Fazendo requisição para:', apiUrl);
    console.log('🌐 [API] Parâmetros:', { turmaId, disciplinaId, tipoDisciplinaId: typeof disciplinaId });
    
    fetch(apiUrl)
        .then(response => {
            console.log('📡 [API] Resposta recebida:', response.status, response.statusText);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text().then(text => {
                console.log('📄 [API] Resposta bruta:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('❌ [API] Erro ao fazer parse do JSON:', e);
                    console.error('❌ [API] Texto recebido:', text);
                    throw new Error('Resposta da API não é JSON válido');
                }
            });
        })
        .then(data => {
            console.log('📊 [API] Dados parseados:', data);
            
            if (data.success) {
                console.log('✅ [API] Sucesso! Renderizando detalhes...');
                renderizarDetalhesDisciplina(disciplinaId, data);
            } else {
                console.error('❌ [API] API retornou erro:', data.message || 'Erro desconhecido');
                mostrarErroDetalhes(disciplinaId, data.message || 'Erro desconhecido');
            }
        })
        .catch(error => {
            console.error('❌ [API] Erro na requisição:', error);
            console.error('❌ [API] Stack trace:', error.stack);
            mostrarErroDetalhes(disciplinaId, error.message);
        })
        .finally(() => {
            console.log('🏁 [API] Finalizando carregamento...');
            // Esconder loading
            if (loadingElement) loadingElement.style.display = 'none';
            if (dataElement) dataElement.style.display = 'block';
        });
}
/**
 * Renderizar detalhes da disciplina na interface
 * @param {number} disciplinaId - ID da disciplina
 * @param {Object} data - Dados da disciplina
 */
function renderizarDetalhesDisciplina(disciplinaId, data) {
    console.log('🎨 [RENDER] Renderizando detalhes para disciplina:', disciplinaId, 'tipo:', typeof disciplinaId);
    
    // Garantir que disciplinaId é válido (aceita tanto números quanto strings)
    const disciplinaIdInt = parseInt(disciplinaId);
    const isNumericId = !isNaN(disciplinaIdInt) && disciplinaIdInt > 0;
    const isStringId = typeof disciplinaId === 'string' && disciplinaId.trim().length > 0 && disciplinaId !== '0';
    
    if (!isNumericId && !isStringId) {
        console.error('❌ [RENDER] ID da disciplina inválido:', disciplinaId, 'tipo:', typeof disciplinaId);
        console.error('❌ [RENDER] Stack trace:', new Error().stack);
        return;
    }
    
    const dataElement = document.getElementById(`data-disciplina-${disciplinaId}`);
    if (!dataElement) {
        console.error('❌ [RENDER] Elemento de dados não encontrado para disciplina:', disciplinaId);
        return;
    }
    
    const { disciplina, aulas, estatisticas } = data;
    
    // Criar HTML dos detalhes
    let html = `
        <div class="disciplina-stats-summary">
            <h6 style="color: #023A8D; margin-bottom: 15px;">
                <i class="fas fa-chart-bar me-2"></i>Estatísticas da Disciplina
            </h6>
            
            <div class="stats-grid">
                <div class="stat-card-mini">
                    <div class="stat-number-mini">${estatisticas.total_aulas}</div>
                    <div class="stat-label-mini">Total de Aulas</div>
                </div>
                <div class="stat-card-mini">
                    <div class="stat-number-mini">${estatisticas.aulas_realizadas}</div>
                    <div class="stat-label-mini">Realizadas</div>
                </div>
                <div class="stat-card-mini">
                    <div class="stat-number-mini">${estatisticas.aulas_agendadas}</div>
                    <div class="stat-label-mini">Agendadas</div>
                </div>
                <div class="stat-card-mini">
                    <div class="stat-number-mini">${estatisticas.total_horas}h</div>
                    <div class="stat-label-mini">Horas Totais</div>
                </div>
            </div>
            
            <div class="progress-container">
                <div class="progress-label">
                    <span>Progresso da Disciplina</span>
                    <span>${estatisticas.total_horas}h / ${estatisticas.carga_obrigatoria}h</span>
                </div>
                <div class="progress-bar-custom">
                    <div class="progress-fill" style="width: ${Math.min(100, (estatisticas.total_horas / estatisticas.carga_obrigatoria) * 100)}%"></div>
                </div>
            </div>
        </div>
    `;
    
    // Adicionar tabela de aulas se houver aulas
    if (aulas && aulas.length > 0) {
        html += `
            <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                <h6 style="color: #023A8D; margin-bottom: 15px;">
                    <i class="fas fa-calendar-alt me-2"></i>Aulas Agendadas (${aulas.length})
                </h6>
                
                <div style="overflow-x: auto;">
                    <table class="aulas-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Dia</th>
                                <th>Horário</th>
                                <th>Duração</th>
                                <th>Status</th>
                                <th>Sala</th>
                                <th>Instrutor</th>
                                <th>Observações</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        aulas.forEach(aula => {
            html += `
                <tr>
                    <td style="font-weight: 600;">${aula.data_formatada}</td>
                    <td style="color: #6c757d;">${aula.dia_semana}</td>
                    <td>
                        <strong>${aula.hora_inicio}</strong><br>
                        <small style="color: #6c757d;">até ${aula.hora_fim}</small>
                    </td>
                    <td style="font-weight: 600; color: #023A8D;">${aula.duracao_horas}h</td>
                    <td>
                        <span class="status-badge-table status-${aula.status}">${aula.status_formatado}</span>
                    </td>
                    <td style="font-weight: 500;">${aula.sala_nome}</td>
                    <td class="instrutor-info">
                        <div class="instrutor-nome">${aula.instrutor_nome}</div>
                        ${aula.instrutor_telefone ? `<div class="instrutor-contato">📞 ${aula.instrutor_telefone}</div>` : ''}
                        ${aula.instrutor_email ? `<div class="instrutor-contato">✉️ ${aula.instrutor_email}</div>` : ''}
                    </td>
                    <td style="font-style: italic; color: #6c757d; max-width: 200px; word-wrap: break-word;">
                        ${aula.observacoes || '-'}
                    </td>
                </tr>
            `;
        });
        
        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    } else {
        html += `
            <div style="background: white; border-radius: 8px; padding: 40px; text-align: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                <i class="fas fa-calendar-times" style="font-size: 3rem; color: #6c757d; margin-bottom: 15px;"></i>
                <h6 style="color: #6c757d; margin-bottom: 10px;">Nenhuma aula agendada</h6>
                <p style="color: #6c757d; margin: 0;">Esta disciplina ainda não possui aulas agendadas.</p>
            </div>
        `;
    }
    
    // Inserir HTML
    dataElement.innerHTML = html;
    
    console.log('✅ [RENDER] Detalhes renderizados com sucesso');
}

/**
 * Mostrar erro ao carregar detalhes
 * @param {number} disciplinaId - ID da disciplina
 * @param {string} errorMessage - Mensagem de erro
 */
function mostrarErroDetalhes(disciplinaId, errorMessage) {
    console.error('❌ [ERRO] Mostrando erro para disciplina:', disciplinaId, 'tipo:', typeof disciplinaId, errorMessage);
    
    // Garantir que disciplinaId é válido (aceita tanto números quanto strings)
    const disciplinaIdInt = parseInt(disciplinaId);
    const isNumericId = !isNaN(disciplinaIdInt) && disciplinaIdInt > 0;
    const isStringId = typeof disciplinaId === 'string' && disciplinaId.trim().length > 0 && disciplinaId !== '0';
    
    if (!isNumericId && !isStringId) {
        console.error('❌ [ERRO] ID da disciplina inválido:', disciplinaId, 'tipo:', typeof disciplinaId);
        console.error('❌ [ERRO] Stack trace:', new Error().stack);
        return;
    }
    
    const dataElement = document.getElementById(`data-disciplina-${disciplinaId}`);
    if (!dataElement) {
        console.error('❌ [ERRO] Elemento de dados não encontrado para disciplina:', disciplinaId);
        return;
    }
    
    dataElement.innerHTML = `
        <div style="background: white; border-radius: 8px; padding: 40px; text-align: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #dc3545; margin-bottom: 15px;"></i>
            <h6 style="color: #dc3545; margin-bottom: 10px;">Erro ao carregar detalhes</h6>
            <p style="color: #6c757d; margin: 0;">${errorMessage}</p>
            <button onclick="carregarDetalhesDisciplina('${disciplinaId}')" 
                    style="margin-top: 15px; padding: 8px 16px; background: #023A8D; color: white; border: none; border-radius: 6px; cursor: pointer;">
                <i class="fas fa-redo me-2"></i>Tentar Novamente
            </button>
        </div>
    `;
}

// ==========================================
// SISTEMA DE EDIÇÃO INLINE
// ==========================================

// Função de teste específica para verificar disciplinas
function testarDisciplinasCompletas() {
    console.log('🧪 [TESTE-COMPLETO] Verificando se todas as disciplinas estão carregadas...');
    
    const selects = document.querySelectorAll('select');
    console.log('🔍 [TESTE-COMPLETO] Selects encontrados:', selects.length);
    
    selects.forEach((select, index) => {
        const opcoes = Array.from(select.options).map(opt => opt.textContent);
        console.log(`📋 [TESTE-COMPLETO] Select ${index + 1} (${select.name || select.id}):`, {
            totalOpcoes: select.options.length,
            disciplinas: opcoes
        });
        
        // Verificar se tem pelo menos 6 disciplinas (excluindo o placeholder)
        if (select.options.length < 7) {
            console.warn(`⚠️ [TESTE-COMPLETO] Select ${index + 1} tem poucas opções!`);
        } else {
            console.log(`✅ [TESTE-COMPLETO] Select ${index + 1} tem opções suficientes!`);
        }
    });
    
    // Testar API diretamente
    fetch(API_BASE_PATH + '/admin/api/disciplinas-clean.php?acao=listar')
        .then(response => response.json())
        .then(data => {
            console.log('📊 [TESTE-COMPLETO] API retornou:', data.disciplinas ? data.disciplinas.length : 0, 'disciplinas');
            if (data.disciplinas) {
                console.log('📋 [TESTE-COMPLETO] Disciplinas da API:', data.disciplinas.map(d => d.nome));
            }
        })
        .catch(error => {
            console.error('❌ [TESTE-COMPLETO] Erro na API:', error);
        });
}
// Função de teste para verificar se tudo está funcionando
function testarSistemaDisciplinas() {
    console.log('🧪 [TESTE] Iniciando teste do sistema de disciplinas...');
    
    // Testar se a função está definida
    console.log('🔍 [TESTE] Função editarDisciplinaCadastrada:', typeof editarDisciplinaCadastrada);
    console.log('🔍 [TESTE] Função carregarDisciplinasNoSelect:', typeof carregarDisciplinasNoSelect);
    console.log('🔍 [TESTE] Função carregarDisciplinasFallback:', typeof carregarDisciplinasFallback);
    console.log('🔍 [TESTE] Função forcarCarregamentoDisciplinas:', typeof forcarCarregamentoDisciplinas);
    
    // Executar teste completo após 2 segundos
    // TEMPORARIAMENTE DESABILITADO PARA TESTE
    // setTimeout(testarDisciplinasCompletas, 2000);
    
    // Testar se há selects na página
    const selects = document.querySelectorAll('select');
    console.log('🔍 [TESTE] Selects encontrados na página:', selects.length);
    
    selects.forEach((select, index) => {
        console.log(`🔍 [TESTE] Select ${index + 1}:`, {
            name: select.name,
            className: select.className,
            id: select.id,
            options: select.options.length
        });
    });
    
    // Testar API
    fetch(API_BASE_PATH + '/admin/api/disciplinas-clean.php?acao=listar')
        .then(response => response.json())
        .then(data => {
            console.log('✅ [TESTE] API funcionando:', data.disciplinas ? data.disciplinas.length : 0, 'disciplinas');
        })
        .catch(error => {
            console.error('❌ [TESTE] Erro na API:', error);
        });
}

// Inicializar sistema
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 [SISTEMA] Inicializando página de detalhes da turma...');
    console.log('🚀 [SISTEMA] DOM carregado completamente!');
    
    // Debug imediato - verificar elementos
    console.log('🔍 [SISTEMA] Verificando elementos de disciplina...');
    const disciplinaCards = document.querySelectorAll('.disciplina-accordion');
    console.log('🔍 [SISTEMA] Cards encontrados:', disciplinaCards.length);
    
    disciplinaCards.forEach((card, index) => {
        const disciplinaId = card.getAttribute('data-disciplina-id');
        const turmaId = card.getAttribute('data-turma-id');
        console.log(`📋 [SISTEMA] Card ${index + 1}: disciplinaId="${disciplinaId}", turmaId="${turmaId}"`);
        
        // Verificar onclick
        const clickableElement = card.querySelector('.disciplina-header-clickable');
        if (clickableElement) {
            console.log(`✅ [SISTEMA] Card ${index + 1}: Elemento clicável encontrado`);
            console.log(`🔗 [SISTEMA] Card ${index + 1}: onclick="${clickableElement.getAttribute('onclick')}"`);
        } else {
            console.error(`❌ [SISTEMA] Card ${index + 1}: Elemento clicável NÃO encontrado`);
        }
    });
    
    // Executar teste
    // TEMPORARIAMENTE DESABILITADO PARA TESTE
    // setTimeout(testarSistemaDisciplinas, 1000);
    
    // Carregar disciplinas usando método simples que sempre funciona
    console.log('🚀 [INIT] Usando método simples para carregar disciplinas...');
    // TEMPORARIAMENTE DESABILITADO PARA TESTE
    // carregarDisciplinasSimples();
    
    // Verificar se há selects de disciplina na página atual
    // TEMPORARIAMENTE DESABILITADO PARA TESTE
    /*
    setTimeout(() => {
        const disciplinaSelects = document.querySelectorAll('.disciplina-item select, .disciplina-edit-form select');
        console.log('🔍 [SELECTS] Verificando selects de disciplina encontrados:', disciplinaSelects.length);
        
        if (disciplinaSelects.length > 0) {
            console.log('📊 [SELECTS] Carregando disciplinas nos selects existentes');
            disciplinaSelects.forEach((select, index) => {
                if (select.options.length <= 1) {
                    console.log(`🔄 [SELECT ${index}] Carregando disciplinas`);
                    carregarDisciplinasSimples();
                }
            });
        } else {
            console.log('ℹ️ [SELECTS] Nenhum select de disciplina encontrado na página atual');
        }
    }, 1000);
    */
    
    // Configurar elementos editáveis
    const editElements = document.querySelectorAll('.inline-edit');
    editElements.forEach(element => {
        element.addEventListener('click', function(e) {
            if (e.target.classList.contains('save-btn') || e.target.classList.contains('cancel-btn')) {
                return;
            }
            startEdit(this);
        });
    });
    
    // Configurar eventos específicos para os ícones de edição
    const editIcons = document.querySelectorAll('.edit-icon');
    editIcons.forEach(icon => {
        icon.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const parentElement = this.closest('.inline-edit');
            if (parentElement) {
                startEdit(parentElement);
            }
        });
    });
    
    const basicInfoScope = document.getElementById('edit-scope-basicas');
    if (basicInfoScope) {
        const scopedInlineEdits = basicInfoScope.querySelectorAll('.inline-edit');
        scopedInlineEdits.forEach(target => {
            const clearTimer = () => {
                if (target.__hideIconTimer) {
                    clearTimeout(target.__hideIconTimer);
                    target.__hideIconTimer = null;
                }
            };

            const hideIcon = () => {
                clearTimer();
                target.classList.remove('show-icon');
            };

            const showIcon = () => {
                clearTimer();
                target.classList.add('show-icon');
            };

            target.addEventListener('mouseenter', showIcon);
            target.addEventListener('mouseleave', hideIcon);
            target.addEventListener('focusin', showIcon);
            target.addEventListener('focusout', hideIcon);
            target.addEventListener('touchstart', () => {
                showIcon();
                target.__hideIconTimer = setTimeout(hideIcon, 1500);
            }, { passive: true });
        });
    }
    
    // Atualizar contador inicial
    atualizarContadorDisciplinasDetalhes();
    
    // Adicionar eventos apenas para selects de disciplina (se existirem)
    const disciplinaSelects = document.querySelectorAll('.disciplina-item select, .disciplina-edit-form select');
    console.log('🔍 [EVENTS] Adicionando eventos para', disciplinaSelects.length, 'selects de disciplina');
    
    disciplinaSelects.forEach((select, index) => {
        console.log(`🔍 [EVENTS] Configurando select ${index + 1}:`, select.name || select.id);
        
        // Evento de clique
        select.addEventListener('click', function() {
            console.log('🖱️ [SELECT] Select clicado:', this.name || this.id);
            // Verificar se tem poucas opções e recarregar se necessário
            if (this.options.length <= 2) {
                console.log('🔄 [SELECT] Poucas opções detectadas, recarregando...');
                carregarDisciplinasSimples();
            }
        });
        
        // Evento de foco
        select.addEventListener('focus', function() {
            console.log('🎯 [SELECT] Select focado:', this.name || this.id);
            // Verificar se tem poucas opções e recarregar se necessário
            if (this.options.length <= 2) {
                console.log('🔄 [SELECT] Poucas opções detectadas no foco, recarregando...');
                carregarDisciplinasSimples();
            }
        });
        
        // Evento de mudança
        select.addEventListener('change', function() {
            console.log('🔄 [SELECT] Select alterado:', this.name || this.id, 'valor:', this.value);
        });
    });
    
    console.log('✅ [SISTEMA] Página inicializada com sucesso');
    
    // Debug: Verificar se os elementos de sanfona foram criados
    setTimeout(() => {
        const disciplinaCards = document.querySelectorAll('.disciplina-accordion');
        console.log('🔍 [DEBUG] Cards de disciplina encontrados:', disciplinaCards.length);
        
        // Monitorar mudanças nos elementos
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    mutation.removedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE && 
                            (node.classList.contains('disciplina-accordion') || 
                             node.querySelector('.disciplina-accordion'))) {
                            console.error('🚨 [MONITOR] Elemento de disciplina REMOVIDO:', node);
                            console.error('🚨 [MONITOR] Stack trace:', new Error().stack);
                        }
                    });
                }
            });
        });
        
        // Observar mudanças no container de disciplinas
        const disciplinasContainer = document.querySelector('.disciplinas-cadastradas-section');
        if (disciplinasContainer) {
            observer.observe(disciplinasContainer, { 
                childList: true, 
                subtree: true 
            });
            console.log('👁️ [MONITOR] Observador de mutações ativado');
        }
        
        disciplinaCards.forEach((card, index) => {
            const disciplinaId = card.getAttribute('data-disciplina-id');
            
            console.log(`🔍 [DEBUG] Card ${index + 1}: disciplinaId = "${disciplinaId}" (tipo: ${typeof disciplinaId})`);
            
            // Pular apenas IDs realmente inválidos (null, undefined, string vazia, '0')
            if (!disciplinaId || disciplinaId === '0' || disciplinaId === 'null' || disciplinaId === 'undefined' || disciplinaId.trim() === '') {
                console.log(`⚠️ [DEBUG] Card ${index + 1}: ID realmente inválido ignorado (${disciplinaId})`);
                // Remover elemento com ID inválido
                card.remove();
                return;
            }
            
            // Verificar se o ID é válido (aceita tanto números quanto strings não vazias)
            const disciplinaIdInt = parseInt(disciplinaId);
            const isNumericId = !isNaN(disciplinaIdInt) && disciplinaIdInt > 0;
            const isStringId = typeof disciplinaId === 'string' && disciplinaId.trim().length > 0 && disciplinaId !== '0';
            
            if (!isNumericId && !isStringId) {
                console.log(`⚠️ [DEBUG] Card ${index + 1}: ID não é válido (${disciplinaId})`);
                card.remove();
                return;
            }
            
            const detalhesContent = document.getElementById(`detalhes-disciplina-${disciplinaId}`);
            console.log(`✅ [DEBUG] Card ${index + 1}: VÁLIDO`, {
                disciplinaId: disciplinaId,
                disciplinaIdInt: disciplinaIdInt,
                temConteudo: !!detalhesContent,
                temChevron: !!card.querySelector('.disciplina-chevron')
            });
        });
    }, 2000);
    
    // Verificação periódica apenas para selects de disciplina (se existirem)
    // TEMPORARIAMENTE DESABILITADO PARA TESTE
    /*
    if (disciplinaSelects.length > 0) {
        setInterval(() => {
            let precisaRecarregar = false;
            
            disciplinaSelects.forEach(select => {
                if (select.options.length <= 2) {
                    console.log('⚠️ [PERIODIC] Select de disciplina com poucas opções detectado:', select.name || select.id);
                    precisaRecarregar = true;
                }
            });
            
            if (precisaRecarregar) {
                console.log('🔄 [PERIODIC] Recarregando disciplinas...');
                carregarDisciplinasSimples();
            }
        }, 5000); // Verificar a cada 5 segundos
    }
    */
});

/**
 * Iniciar edição inline de um campo
 * @param {HTMLElement} element - Elemento a ser editado
 */
function startEdit(element) {
    if (element.classList.contains('editing')) return;
    
    const field = element.dataset.field;
    const type = element.dataset.type;
    const value = element.dataset.value;
    
    console.log(`✏️ [EDIT] Iniciando edição do campo: ${field}`);
    
    // Salvar valor original
    originalValues[field] = value;
    
    // Adicionar classe de edição
    element.classList.add('editing');
    
    // Criar input baseado no tipo
    let input = createInputByType(type, value, field);
    
    // Aplicar estilos específicos do campo
    applyFieldStyles(input, field);
    
    // Substituir conteúdo
    element.innerHTML = '';
    element.appendChild(input);
    
    // Configurar eventos
    setupInputEvents(input, element);
    
    // Focar no input
    input.focus();
    if (type === 'text' || type === 'textarea') {
        input.select();
    }
}

/**
 * Criar input baseado no tipo de campo
 * @param {string} type - Tipo do campo (text, select, date, textarea)
 * @param {string} value - Valor atual
 * @param {string} field - Nome do campo
 * @returns {HTMLElement} Elemento input criado
 */
function createInputByType(type, value, field) {
    let input;
    
    switch (type) {
        case 'textarea':
            input = document.createElement('textarea');
            input.value = value;
            input.rows = 3;
            break;
        case 'select':
            input = document.createElement('select');
            addSelectOptions(input, field);
            input.value = value;
            break;
        case 'date':
            input = document.createElement('input');
            input.type = 'date';
            input.value = value;
            break;
        default:
            input = document.createElement('input');
            input.type = 'text';
            input.value = value;
            break;
    }
    
    return input;
}

// Funções auxiliares para melhor comportamento
function applyFieldStyles(input, field) {
    switch(field) {
        case 'nome':
            input.style.fontSize = '1.5rem';
            input.style.fontWeight = 'bold';
            input.style.color = '#023A8D';
            input.style.border = 'none';
            input.style.minWidth = '200px';
            break;
        case 'curso_nome':
            input.style.color = '#666';
            input.style.fontSize = '1rem';
            break;
        case 'data_inicio':
        case 'data_fim':
            input.style.fontFamily = 'monospace';
            input.style.background = '#f8f9fa';
            input.style.borderRadius = '4px';
            input.style.padding = '4px 8px';
            break;
        case 'status':
            input.style.padding = '4px 12px';
            input.style.borderRadius = '20px';
            input.style.fontSize = '0.9rem';
            input.style.fontWeight = '600';
            input.style.textTransform = 'uppercase';
            break;
        case 'modalidade':
        case 'sala_id':
            input.style.fontWeight = '500';
            break;
        case 'observacoes':
            input.style.fontStyle = 'italic';
            input.style.color = '#666';
            break;
    }
}

function setupInputEvents(input, element) {
    // Evento de teclado para salvamento automático
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveFieldAutomatically(element, input.value);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelEditAutomatically(element);
        }
    });
    
    // Evento de blur para salvar ao sair do campo
    input.addEventListener('blur', function() {
        setTimeout(() => {
            if (!element.contains(document.activeElement)) {
                saveFieldAutomatically(element, input.value);
            }
        }, 100);
    });
    
    // Evento de input para feedback visual
    input.addEventListener('input', function() {
        element.style.borderColor = '#28a745';
    });
}
function addSelectOptions(select, field) {
    const options = {
        'status': [
            { value: 'criando', text: 'Criando' },
            { value: 'agendando', text: 'Agendando' },
            { value: 'completa', text: 'Completa' },
            { value: 'ativa', text: 'Ativa' },
            { value: 'concluida', text: 'Concluída' }
        ],
        'modalidade': [
            { value: 'presencial', text: 'Presencial' },
            { value: 'online', text: 'Online' },
            { value: 'hibrida', text: 'Híbrida' }
        ],
        'sala_id': <?= json_encode(array_map(function($sala) {
            return ['value' => $sala['id'], 'text' => $sala['nome']];
        }, $salasCadastradas)) ?>,
        'curso_tipo': [
            { value: 'formacao_45h', text: 'Curso de Formação de Condutores - Permissão 45h' },
            { value: 'formacao_acc_20h', text: 'Curso de Formação de Condutores - ACC 20h' },
            { value: 'reciclagem_infrator', text: 'Curso de Reciclagem para Condutor Infrator' },
            { value: 'atualizacao', text: 'Curso de Atualização' }
        ]
    };
    
    if (options[field]) {
        options[field].forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.value = option.value;
            optionElement.textContent = option.text;
            select.appendChild(optionElement);
        });
    }
}
// Funções de salvamento automático (botões removidos)
function saveFieldAutomatically(element, newValue) {
    const field = element.dataset.field;
    
    console.log(`💾 [AUTO-SAVE] Salvando campo ${field} com valor: ${newValue}`);
    
    // Enviar para o servidor
    fetch(API_BASE_PATH + '/admin/api/turmas-teoricas-inline.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update_field',
            turma_id: <?= $turmaId ?>,
            field: field,
            value: newValue
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                // Atualizar valor no dataset
                element.dataset.value = newValue;
                
                // Restaurar visualização
                restoreView(element, newValue);
                
                // Mostrar feedback discreto
                showFeedback('Campo atualizado!', 'success');
            } else {
                showFeedback('Erro ao atualizar campo: ' + data.message, 'error');
            }
        } catch (e) {
            console.error('❌ [AUTO-SAVE] Resposta não é JSON válido:', text);
            showFeedback('Erro: Resposta inválida do servidor', 'error');
        }
    })
    .catch(error => {
        console.error('❌ [AUTO-SAVE] Erro:', error);
        showFeedback('Erro ao atualizar campo: ' + error.message, 'error');
    });
}
function cancelEditAutomatically(element) {
    const field = element.dataset.field;
    const originalValue = originalValues[field];
    
    console.log(`❌ [CANCEL] Cancelando edição do campo ${field}`);
    
    // Restaurar visualização
    restoreView(element, originalValue);
}

// Funções antigas removidas - agora usa salvamento automático

// Função restoreView melhorada com transição suave
function restoreView(element, value) {
    const field = element.dataset.field;
    const type = element.dataset.type;
    
    console.log(`🔄 [RESTORE] Restaurando campo ${field} com valor: ${value}`);
    
    // Remover classe de edição
    element.classList.remove('editing');
    
    // Restaurar conteúdo baseado no tipo
    let displayValue = value;
    if (type === 'date' && value) {
        displayValue = new Date(value + 'T00:00:00').toLocaleDateString('pt-BR');
    } else if (type === 'select') {
        displayValue = getSelectDisplayValue(field, value);
        console.log(`🔄 [RESTORE] Campo select ${field}: ${value} → ${displayValue}`);
    }
    
    // Restaurar HTML (sem botões - salvamento automático)
    element.innerHTML = displayValue + 
        '<i class="fas fa-edit edit-icon"></i>';
    
    // Aplicar estilos específicos do campo APÓS restaurar o HTML
    setTimeout(() => {
        applyDisplayStyles(element, field);
        console.log(`✅ [RESTORE] Campo ${field} restaurado com sucesso`);
    }, 10);
}

// Aplicar estilos específicos para exibição
function applyDisplayStyles(element, field) {
    // Remover estilos inline que podem ter sido aplicados
    element.style.borderColor = '';
    element.style.border = 'none';
    
    // Estilos gerais para evitar texto cortado
    element.style.wordWrap = 'break-word';
    element.style.overflowWrap = 'break-word';
    element.style.whiteSpace = 'normal';
    element.style.maxWidth = '100%';
    element.style.boxSizing = 'border-box';
    
    // Aplicar estilos específicos do campo
    switch(field) {
        case 'nome':
            element.style.fontSize = '1.5rem';
            element.style.fontWeight = 'bold';
            element.style.color = '#023A8D';
            element.style.border = 'none';
            element.style.minWidth = '200px';
    updateTurmaHeaderName(element.dataset.value || element.textContent);
            break;
        case 'curso_tipo':
            element.style.color = '#666';
            element.style.fontSize = '1rem';
            element.style.border = 'none';
            element.style.minWidth = '400px';
            element.style.maxWidth = 'none';
            element.style.width = 'fit-content';
            element.style.wordWrap = 'break-word';
            element.style.overflowWrap = 'break-word';
            element.style.whiteSpace = 'nowrap';
            element.style.display = 'block';
            element.style.verticalAlign = 'top';
            element.style.lineHeight = '1.4';
            element.style.overflow = 'visible';
            element.style.textOverflow = 'unset';
            element.style.textAlign = 'left';
            element.style.padding = '0';
            element.style.margin = '0';
            console.log(`🎨 [STYLES] Aplicando estilos para curso_tipo`);
            break;
        case 'data_inicio':
        case 'data_fim':
            element.style.fontFamily = 'monospace';
            element.style.background = '#f8f9fa';
            element.style.borderRadius = '4px';
            element.style.padding = '4px 8px';
            element.style.border = 'none';
            element.style.minWidth = '120px';
            element.style.maxWidth = '100%';
            break;
        case 'status':
            element.style.padding = '4px 12px';
            element.style.borderRadius = '20px';
            element.style.fontSize = '0.9rem';
            element.style.fontWeight = '600';
            element.style.textTransform = 'uppercase';
            element.style.border = 'none';
            element.style.minWidth = '100px';
            element.style.maxWidth = '100%';
            break;
        case 'modalidade':
        case 'sala_id':
            element.style.fontWeight = '500';
            element.style.border = 'none';
            element.style.minWidth = '120px';
            element.style.maxWidth = '100%';
            break;
        case 'observacoes': {
            element.style.fontStyle = 'italic';
            element.style.color = '#666';
            element.style.border = 'none';
            element.style.minWidth = '300px';
            element.style.maxWidth = '100%';
            element.style.width = '100%';
            element.style.boxSizing = 'border-box';
            element.style.whiteSpace = 'normal';
            element.style.wordWrap = 'break-word';
            element.style.overflowWrap = 'break-word';
            element.style.textAlign = 'left';
            element.style.display = 'block';
            element.style.padding = '12px';
            element.style.margin = '0';
            // Remover qualquer centralização que venha do backend
            element.querySelectorAll('*').forEach(child => {
                if (child instanceof HTMLElement) {
                    const inlineTextAlign = child.style.textAlign;
                    if (inlineTextAlign && inlineTextAlign.toLowerCase() !== 'left') {
                        child.style.textAlign = 'left';
                    }
                    const inlineDisplay = child.style.display;
                    if (inlineDisplay && inlineDisplay.toLowerCase() === 'inline') {
                        child.style.display = 'inline';
                    }
                    if (child.tagName === 'CENTER') {
                        child.style.textAlign = 'left';
                        child.style.display = 'block';
                    }
                }
            });
            break;
        }
    }
}

function getSelectDisplayValue(field, value) {
    const options = {
        'status': {
            'criando': 'Criando',
            'agendando': 'Agendando',
            'completa': 'Completa',
            'ativa': 'Ativa',
            'concluida': 'Concluída'
        },
        'modalidade': {
            'presencial': 'Presencial',
            'online': 'Online',
            'hibrida': 'Híbrida'
        },
        'curso_tipo': {
            'formacao_45h': 'Curso de Formação de Condutores - Permissão 45h',
            'formacao_acc_20h': 'Curso de Formação de Condutores - ACC 20h',
            'reciclagem_infrator': 'Curso de Reciclagem para Condutor Infrator',
            'atualizacao': 'Curso de Atualização'
        },
        'sala_id': <?= json_encode(array_column($salasCadastradas, 'nome', 'id')) ?>
    };
    
    return options[field] && options[field][value] ? options[field][value] : value;
}

function showFeedback(message, type) {
    const feedback = document.createElement('div');
    feedback.className = `alert alert-${type === 'success' ? 'success' : 'danger'}`;
    feedback.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    feedback.textContent = message;
    
    document.body.appendChild(feedback);
    
    setTimeout(() => {
        feedback.remove();
    }, 3000);
}

// Função para iniciar edição de disciplina
function startEditDisciplina(element) {
    console.log('✏️ [EDIT-DISCIPLINA] Iniciando edição de disciplina');
    
    // Adicionar classe de edição
    element.classList.add('editing');
    
    // Buscar disciplinas disponíveis
    fetch(API_BASE_PATH + '/admin/api/disciplinas-estaticas.php?action=listar')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.disciplinas) {
                showDisciplinaEditModal(element, data.disciplinas);
            } else {
                showFeedback('Erro ao carregar disciplinas disponíveis', 'error');
            }
        })
        .catch(error => {
            console.error('❌ [EDIT-DISCIPLINA] Erro:', error);
            showFeedback('Erro ao carregar disciplinas: ' + error.message, 'error');
            element.classList.remove('editing');
        });
}

// Função para mostrar modal de edição de disciplina
function showDisciplinaEditModal(element, disciplinas) {
    const disciplinaId = element.dataset.disciplinaId;
    const disciplinaAtual = element.querySelector('strong').textContent;
    
    // Criar modal
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
    `;
    
    modal.id = 'disciplina-edit-modal';
    modal.setAttribute('data-disciplina-id-atual', disciplinaId);
    
    modal.innerHTML = `
        <div style="background: white; border-radius: 12px; padding: 25px; max-width: 500px; width: 90%; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <h4 style="color: #023A8D; margin-bottom: 20px;">
                <i class="fas fa-edit me-2"></i>Editar Disciplina
            </h4>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Disciplina Atual:</label>
                <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; color: #666;">
                    ${disciplinaAtual}
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Nova Disciplina:</label>
                <select id="nova-disciplina-select" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
                    <option value="">Selecione uma disciplina...</option>
                    ${disciplinas.map(d => `<option value="${d.id}">${d.nome}</option>`).join('')}
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Carga Horária:</label>
                <input type="number" id="nova-carga-horaria" min="1" max="200" 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;"
                       placeholder="Digite a carga horária">
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="cancelEditDisciplina()" style="padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer;">
                    Cancelar
                </button>
                <button onclick="confirmEditDisciplina()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    <i class="fas fa-save me-2"></i>Salvar
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Focar no select
    setTimeout(() => {
        document.getElementById('nova-disciplina-select').focus();
    }, 100);
}

// Função para confirmar edição de disciplina
function confirmEditDisciplina() {
    const modal = document.getElementById('disciplina-edit-modal');
    if (!modal) return;
    
    const disciplinaIdAtual = modal.getAttribute('data-disciplina-id-atual');
    if (!disciplinaIdAtual) {
        showFeedback('Erro: ID da disciplina atual não encontrado', 'error');
        return;
    }
    
    saveEditDisciplina(disciplinaIdAtual);
}

// Função para salvar edição de disciplina
function saveEditDisciplina(disciplinaIdAtual) {
    const novaDisciplinaId = document.getElementById('nova-disciplina-select').value;
    const novaCargaHoraria = document.getElementById('nova-carga-horaria').value;
    
    if (!novaDisciplinaId) {
        showFeedback('Selecione uma nova disciplina', 'error');
        return;
    }
    
    if (!novaCargaHoraria || novaCargaHoraria < 1) {
        showFeedback('Digite uma carga horária válida', 'error');
        return;
    }
    
    console.log('💾 [EDIT-DISCIPLINA] Salvando:', {
        disciplinaIdAtual,
        novaDisciplinaId,
        novaCargaHoraria
    });
    
    // Enviar para API
    fetch(API_BASE_PATH + '/admin/api/turmas-teoricas-inline.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update_disciplina',
            turma_id: <?= $turmaId ?>,
            disciplina_id_atual: disciplinaIdAtual,
            disciplina_id_nova: novaDisciplinaId,
            carga_horaria: novaCargaHoraria
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showFeedback('Disciplina atualizada com sucesso!', 'success');
                cancelEditDisciplina();
                // Não recarregar página - manter interface atual
            } else {
                showFeedback('Erro ao atualizar disciplina: ' + data.message, 'error');
            }
        } catch (e) {
            console.error('❌ [EDIT-DISCIPLINA] Resposta não é JSON válido:', text);
            showFeedback('Erro: Resposta inválida do servidor', 'error');
        }
    })
    .catch(error => {
        console.error('❌ [EDIT-DISCIPLINA] Erro:', error);
        showFeedback('Erro ao atualizar disciplina: ' + error.message, 'error');
    });
}

// Função para cancelar edição de disciplina
function cancelEditDisciplina() {
    // Remover modal
    const modal = document.querySelector('div[style*="position: fixed"]');
    if (modal) {
        modal.remove();
    }
    
    // Remover classe de edição de todos os elementos
    document.querySelectorAll('.disciplina-edit-item.editing').forEach(el => {
        el.classList.remove('editing');
    });
}

// Funções para disciplinas - corrigidas
function addDisciplina() {
    console.log('➕ [DISCIPLINA] Adicionando nova disciplina...');
    
    // Verificar se há disciplinas disponíveis primeiro
    fetch(API_BASE_PATH + '/admin/api/disciplinas-estaticas.php?action=listar')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.disciplinas) {
                // Criar modal simples para seleção
                let options = '';
                data.disciplinas.forEach(disciplina => {
                    options += `<option value="${disciplina.id}">${disciplina.nome}</option>`;
                });
                
                const disciplinaId = prompt('Digite o ID da disciplina (ou veja as opções no console):');
                const cargaHoraria = prompt('Digite a carga horária:');
                
                console.log('Disciplinas disponíveis:', data.disciplinas);
                
                if (disciplinaId && cargaHoraria) {
                    addDisciplinaToTurma(disciplinaId, cargaHoraria);
                }
            } else {
                console.error('❌ [DISCIPLINA] Erro ao carregar disciplinas:', data);
                alert('Erro ao carregar disciplinas disponíveis');
            }
        })
        .catch(error => {
            console.error('❌ [DISCIPLINA] Erro na requisição:', error);
            alert('Erro ao carregar disciplinas');
        });
}

function addDisciplinaToTurma(disciplinaId, cargaHoraria) {
    console.log('➕ [DISCIPLINA] Adicionando disciplina:', disciplinaId, 'com carga:', cargaHoraria);
    
    // Enviar para API
    fetch(API_BASE_PATH + '/admin/api/turmas-teoricas-inline.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add_disciplina',
            turma_id: <?= $turmaId ?>,
            disciplina_id: disciplinaId,
            carga_horaria: cargaHoraria
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showFeedback('Disciplina adicionada com sucesso!', 'success');
                // Não recarregar página - manter interface atual
            } else {
                showFeedback('Erro ao adicionar disciplina: ' + data.message, 'error');
            }
        } catch (e) {
            console.error('❌ [DISCIPLINA] Resposta não é JSON válido:', text);
            showFeedback('Erro: Resposta inválida do servidor', 'error');
        }
    })
    .catch(error => {
        console.error('❌ [DISCIPLINA] Erro:', error);
        showFeedback('Erro ao adicionar disciplina: ' + error.message, 'error');
    });
}

function removeDisciplina(disciplinaId) {
    if (confirm('Tem certeza que deseja remover esta disciplina?')) {
        console.log('🗑️ [DISCIPLINA] Removendo disciplina:', disciplinaId);
        
        // Enviar para API
        fetch(API_BASE_PATH + '/admin/api/turmas-teoricas-inline.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'remove_disciplina',
                turma_id: <?= $turmaId ?>,
                disciplina_id: disciplinaId
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    showFeedback('Disciplina removida com sucesso!', 'success');
                    // Remover elemento do DOM
                    const disciplinaItem = document.querySelector(`[data-disciplina-id="${disciplinaId}"]`);
                    if (disciplinaItem) {
                        disciplinaItem.remove();
                    }
                } else {
                    showFeedback('Erro ao remover disciplina: ' + data.message, 'error');
                }
            } catch (e) {
                console.error('❌ [DISCIPLINA] Resposta não é JSON válido:', text);
                showFeedback('Erro: Resposta inválida do servidor', 'error');
            }
        })
        .catch(error => {
            console.error('❌ [DISCIPLINA] Erro:', error);
            showFeedback('Erro ao remover disciplina: ' + error.message, 'error');
        });
    }
}
// ===== SISTEMA DE EDIÇÃO DE AGENDAMENTOS =====
// Função auxiliar para exibir o modal de edição
function exibirModalEdicao(modal) {
    if (!modal) {
        modal = document.getElementById('modalEditarAgendamento');
    }
    
    if (!modal) {
        console.error('Modal de edição não encontrado!');
        return;
    }
    
    // Remover modais existentes antes de exibir
    const modaisExistentes = document.querySelectorAll('#modalEditarAgendamento');
    modaisExistentes.forEach(m => {
        if (m !== modal && m.parentNode) {
            m.parentNode.removeChild(m);
        }
    });
    
    // Garantir que o modal está no DOM
    if (!modal.parentNode) {
        document.body.appendChild(modal);
    }
    
    // Mostrar modal seguindo o padrão do sistema
    modal.classList.add('show');
    modal.style.cssText = `
        display: flex !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: rgba(0, 0, 0, 0.5) !important;
        z-index: 999999 !important;
        align-items: center !important;
        justify-content: center !important;
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
    `;
    
    // Bloquear scroll do body
    document.body.style.overflow = 'hidden';
    
    // Adicionar animação de fade-in
    setTimeout(() => {
        modal.classList.add('popup-fade-in');
    }, 10);
}
// Modal de edição de agendamento - usa o mesmo modal de agendamento
function editarAgendamento(id, nomeAula, dataAula, horaInicio, horaFim, instrutorId, salaId, duracao, observacoes) {
    window.currentEditAgendamentoId = id;
    
    // Usar o mesmo modal de agendamento, mas em modo de edição
    const modal = document.getElementById('modalAgendarAula');
    if (!modal) {
        console.error('Modal de agendamento não encontrado!');
        alert('Erro: Modal não encontrado. Recarregue a página.');
        return;
    }
    
    // Configurar modo de edição
    const modalModo = document.getElementById('modal_modo');
    const modalAcao = document.getElementById('modal_acao');
    const modalAulaId = document.getElementById('modal_aula_id');
    const modalTitulo = document.getElementById('modal_titulo');
    const btnAgendarTexto = document.getElementById('btnAgendarTexto');
    const campoObservacoes = document.getElementById('campoObservacoesModal');
    const modalObservacoes = document.getElementById('modal_observacoes');
    
    if (modalModo) modalModo.value = 'editar';
    if (modalAcao) modalAcao.value = 'editar_aula';
    if (modalAulaId) modalAulaId.value = id;
    if (modalTitulo) {
        modalTitulo.innerHTML = '<i class="fas fa-edit"></i> Editar Agendamento';
    }
    if (btnAgendarTexto) {
        btnAgendarTexto.textContent = 'Salvar Alterações';
    }
    if (campoObservacoes) {
        campoObservacoes.style.display = 'block';
    }
    if (modalObservacoes && observacoes) {
        modalObservacoes.value = observacoes;
    }
    
    // Mostrar botão de excluir no header (apenas em modo edição)
    const btnExcluirModal = document.getElementById('btn_excluir_modal');
    if (btnExcluirModal) {
        btnExcluirModal.style.display = 'inline-flex';
    }
    
    // Exibir modal imediatamente (dados serão preenchidos depois)
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Buscar dados completos do agendamento
    fetch(`api/agendamento-detalhes.php?id=${id}`)
        .then(response => {
            console.log('🔧 [DEBUG] Resposta agendamento-detalhes:', response.status);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const agendamento = data.agendamento;
                
                // Preencher campos do modal unificado com dados reais
                const modalDisciplinaId = document.getElementById('modal_disciplina_id');
                const modalDisciplinaNome = document.getElementById('modal_disciplina_nome');
                const modalDataAula = document.getElementById('modal_data_aula');
                const modalHoraInicio = document.getElementById('modal_hora_inicio');
                const modalInstrutorId = document.getElementById('modal_instrutor_id');
                const modalQuantidadeAulas = document.getElementById('modal_quantidade_aulas');
                const modalObservacoes = document.getElementById('modal_observacoes');
                
                // [FIX] FASE 2 - EDICAO DISCIPLINA TURMA 16: Usar exatamente o valor de disciplina retornado pela API (slug do banco)
                let disciplinaId = agendamento.disciplina || '';
                if (!disciplinaId && agendamento.nome_aula) {
                    // Tentar extrair do nome (fallback apenas se não vier do banco)
                    const partes = agendamento.nome_aula.split(' - ');
                    if (partes.length > 0) {
                        // Normalizar: remover acentos e converter para formato do banco
                        disciplinaId = partes[0].toLowerCase()
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '') // Remove acentos
                            .replace(/\s+/g, '_') // Converte espaços para underscore
                            .replace(/ç/g, 'c') // Converte ç para c
                            .replace(/ñ/g, 'n'); // Converte ñ para n
                    }
                }
                
                // Preencher campo disciplina no modal unificado (sem normalizar - usar valor do banco)
                if (modalDisciplinaId && disciplinaId) {
                    modalDisciplinaId.value = disciplinaId; // Usar valor direto do banco, sem normalização JS
                    
                    // ✅ Registrar disciplina aberta para manter accordion após salvar
                    window.ultimaDisciplinaAberta = normalizarDisciplinaJS(disciplinaId);
                    console.log('📌 Disciplina registrada ao editar:', window.ultimaDisciplinaAberta);
                }
                
                // [FIX] FASE 2 - EDICAO DISCIPLINA TURMA 16: Preencher campo disciplina no modal de edição separado
                const editDisciplinaField = document.getElementById('editDisciplina');
                if (editDisciplinaField && disciplinaId) {
                    editDisciplinaField.value = disciplinaId; // Usar valor direto do banco, sem normalização
                    console.log('✅ [FIX FASE 2] Campo editDisciplina preenchido com:', disciplinaId);
                }
                if (modalDisciplinaNome && agendamento.nome_aula) {
                    // Extrair nome da disciplina (sem " - Aula X")
                    const nomeDisciplina = agendamento.nome_aula.split(' - ')[0];
                    modalDisciplinaNome.value = nomeDisciplina;
                }
                if (modalDataAula && agendamento.data_aula) modalDataAula.value = agendamento.data_aula;
                if (modalObservacoes) modalObservacoes.value = agendamento.observacoes || '';
                
                // Calcular quantidade de aulas baseado na duração
                const duracao = agendamento.duracao_minutos || 50;
                const quantidadeAulas = Math.ceil(duracao / 50);
                if (modalQuantidadeAulas) {
                    modalQuantidadeAulas.value = quantidadeAulas.toString();
                }
                
                // Carregar horário no input incluindo o horário do agendamento
                if (agendamento.hora_inicio) {
                    const horaInicio = agendamento.hora_inicio.length === 8 ? agendamento.hora_inicio.substring(0, 5) : agendamento.hora_inicio;
                    if (modalHoraInicio && horaInicio) {
                        modalHoraInicio.value = horaInicio;
                    }
                }
                
                // Carregar selects com os valores corretos
                carregarDadosSelects(agendamento.instrutor_id, agendamento.sala_id).then(() => {
                    if (modalInstrutorId && agendamento.instrutor_id) {
                        modalInstrutorId.value = agendamento.instrutor_id;
                    }
                    
                    // Atualizar preview após todos os campos serem preenchidos
                    setTimeout(() => {
                        if (typeof atualizarPreviewModal === 'function') {
                            atualizarPreviewModal();
                        }
                    }, 150);
                });
                
                // Atualizar estatísticas no modal após carregar dados
                setTimeout(() => {
                    if (disciplinaId && typeof buscarInfoDisciplina === 'function') {
                        buscarInfoDisciplina(disciplinaId).catch(err => {
                            // Ignorar erros silenciosamente
                        });
                    }
                    if (typeof atualizarEstatisticasModal === 'function') {
                        atualizarEstatisticasModal();
                    }
                    // Destacar disciplina selecionada na sidebar
                    if (typeof destacarDisciplinaSelecionada === 'function') {
                        destacarDisciplinaSelecionada(disciplinaId);
                    }
                }, 100);
                
                console.log('✅ [DEBUG] Dados do agendamento carregados no modal unificado:', agendamento);
            } else {
                console.error('❌ [DEBUG] Erro ao carregar dados do agendamento:', data.message);
                // Tentar API de fallback
                return fetch(`api/agendamento-detalhes-fallback.php?id=${id}`);
            }
        })
        .then(response => {
            if (response) {
                return response.json();
            }
        })
        .then(data => {
            if (data && data.success) {
                const agendamento = data.agendamento;
                
                // Preencher campos do modal unificado com dados de fallback
                const modalDisciplinaId = document.getElementById('modal_disciplina_id');
                const modalDisciplinaNome = document.getElementById('modal_disciplina_nome');
                const modalDataAula = document.getElementById('modal_data_aula');
                const modalHoraInicio = document.getElementById('modal_hora_inicio');
                const modalInstrutorId = document.getElementById('modal_instrutor_id');
                const modalQuantidadeAulas = document.getElementById('modal_quantidade_aulas');
                const modalObservacoes = document.getElementById('modal_observacoes');
                
                // [FIX] FASE 2 - EDICAO DISCIPLINA TURMA 16: Usar exatamente o valor de disciplina retornado pela API (slug do banco)
                let disciplinaId = agendamento.disciplina || '';
                if (!disciplinaId && agendamento.nome_aula) {
                    const partes = agendamento.nome_aula.split(' - ');
                    if (partes.length > 0) {
                        // Normalizar disciplina extraída do nome da aula (fallback apenas)
                        disciplinaId = normalizarDisciplinaJS(partes[0]);
                    }
                }
                
                // Preencher campo disciplina no modal unificado (sem normalizar - usar valor do banco)
                if (modalDisciplinaId && disciplinaId) {
                    modalDisciplinaId.value = disciplinaId; // Usar valor direto do banco, sem normalização JS
                }
                
                // [FIX] FASE 2 - EDICAO DISCIPLINA TURMA 16: Preencher campo disciplina no modal de edição separado
                const editDisciplinaField = document.getElementById('editDisciplina');
                if (editDisciplinaField && disciplinaId) {
                    editDisciplinaField.value = disciplinaId; // Usar valor direto do banco, sem normalização
                    console.log('✅ [FIX FASE 2] Campo editDisciplina preenchido (fallback) com:', disciplinaId);
                }
                if (modalDisciplinaNome && agendamento.nome_aula) {
                    const nomeDisciplina = agendamento.nome_aula.split(' - ')[0];
                    modalDisciplinaNome.value = nomeDisciplina;
                }
                if (modalDataAula && agendamento.data_aula) modalDataAula.value = agendamento.data_aula;
                if (modalObservacoes) modalObservacoes.value = agendamento.observacoes || '';
                
                const duracao = agendamento.duracao_minutos || 50;
                const quantidadeAulas = Math.ceil(duracao / 50);
                if (modalQuantidadeAulas) {
                    modalQuantidadeAulas.value = quantidadeAulas.toString();
                }
                
                if (agendamento.hora_inicio) {
                    const horaInicio = agendamento.hora_inicio.length === 8 ? agendamento.hora_inicio.substring(0, 5) : agendamento.hora_inicio;
                    if (modalHoraInicio && horaInicio) {
                        modalHoraInicio.value = horaInicio;
                    }
                }
                
                // Carregar selects e depois definir valores
                carregarDadosSelects(agendamento.instrutor_id, agendamento.sala_id).then(() => {
                    if (modalInstrutorId && agendamento.instrutor_id) {
                        modalInstrutorId.value = agendamento.instrutor_id;
                    }
                    
                    // Atualizar preview após todos os campos serem preenchidos
                    setTimeout(() => {
                        if (typeof atualizarPreviewModal === 'function') {
                            atualizarPreviewModal();
                        }
                    }, 150);
                });
                
                // Mostrar modal
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                
                console.log('✅ [DEBUG] Dados de fallback carregados no modal unificado');
            } else {
                // Usar dados passados como parâmetro como último fallback
                const modalDisciplinaId = document.getElementById('modal_disciplina_id');
                const modalDisciplinaNome = document.getElementById('modal_disciplina_nome');
                const modalDataAula = document.getElementById('modal_data_aula');
                const modalHoraInicio = document.getElementById('modal_hora_inicio');
                const modalInstrutorId = document.getElementById('modal_instrutor_id');
                const modalQuantidadeAulas = document.getElementById('modal_quantidade_aulas');
                const modalObservacoes = document.getElementById('modal_observacoes');
                
                // Extrair disciplina do nome da aula
                let disciplinaId = '';
                if (nomeAula) {
                    const partes = nomeAula.split(' - ');
                    if (partes.length > 0) {
                        disciplinaId = partes[0].toLowerCase().replace(/\s+/g, '_');
                        if (modalDisciplinaNome) modalDisciplinaNome.value = partes[0];
                    }
                }
                if (modalDisciplinaId && disciplinaId) modalDisciplinaId.value = disciplinaId;
                if (modalDataAula && dataAula) modalDataAula.value = dataAula;
                if (modalObservacoes) modalObservacoes.value = observacoes || '';
                
                const quantidadeAulas = Math.ceil((duracao || 50) / 50);
                if (modalQuantidadeAulas) {
                    modalQuantidadeAulas.value = quantidadeAulas.toString();
                }
                
                if (horaInicio) {
                    const horaNormalizada = horaInicio.length === 8 ? horaInicio.substring(0, 5) : horaInicio;
                    if (modalHoraInicio && horaNormalizada) {
                        modalHoraInicio.value = horaNormalizada;
                    }
                }
                
                carregarDadosSelects(instrutorId, salaId).then(() => {
                    if (modalInstrutorId && instrutorId) {
                        modalInstrutorId.value = instrutorId;
                    }
                });
                
                // Mostrar modal
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                
                console.log('⚠️ [DEBUG] Usando dados passados como parâmetro no modal unificado');
            }
        })
        .catch(error => {
            console.error('❌ [DEBUG] Erro ao buscar dados do agendamento:', error);
            // Usar dados passados como parâmetro como último fallback
            const modalDisciplinaId = document.getElementById('modal_disciplina_id');
            const modalDisciplinaNome = document.getElementById('modal_disciplina_nome');
            const modalDataAula = document.getElementById('modal_data_aula');
            const modalHoraInicio = document.getElementById('modal_hora_inicio');
            const modalInstrutorId = document.getElementById('modal_instrutor_id');
            const modalQuantidadeAulas = document.getElementById('modal_quantidade_aulas');
            const modalObservacoes = document.getElementById('modal_observacoes');
            
            // Extrair disciplina do nome da aula
            let disciplinaId = '';
            if (nomeAula) {
                const partes = nomeAula.split(' - ');
                if (partes.length > 0) {
                    disciplinaId = partes[0].toLowerCase().replace(/\s+/g, '_');
                    if (modalDisciplinaNome) modalDisciplinaNome.value = partes[0];
                }
            }
            if (modalDisciplinaId && disciplinaId) modalDisciplinaId.value = disciplinaId;
            if (modalDataAula && dataAula) modalDataAula.value = dataAula;
            if (modalObservacoes) modalObservacoes.value = observacoes || '';
            
            const quantidadeAulas = Math.ceil((duracao || 50) / 50);
            if (modalQuantidadeAulas) {
                modalQuantidadeAulas.value = quantidadeAulas.toString();
            }
            
            if (horaInicio) {
                const horaNormalizada = horaInicio.length === 8 ? horaInicio.substring(0, 5) : horaInicio;
                if (modalHoraInicio && horaNormalizada) {
                    modalHoraInicio.value = horaNormalizada;
                }
            }
            
            carregarDadosSelects(instrutorId, salaId).then(() => {
                if (modalInstrutorId && instrutorId) {
                    modalInstrutorId.value = instrutorId;
                }
            });
            
            // Mostrar modal
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            console.log('⚠️ [DEBUG] Usando dados passados como parâmetro (catch) no modal unificado');
        });
    
    // Modal já é exibido dentro dos handlers acima
}

// Cancelar agendamento
function cancelarAgendamento(id, nomeAula) {
    console.log('🔧 [DEBUG] Iniciando cancelamento:', { id, nomeAula });
    
    if (confirm(`Tem certeza que deseja cancelar o agendamento "${nomeAula}"?`)) {
        const url = getBasePath() + '/admin/api/turmas-teoricas.php';
        const data = {
            acao: 'cancelar_aula',
            aula_id: id
        };
        
        console.log('🔧 [DEBUG] URL:', url);
        console.log('🔧 [DEBUG] Dados:', data);
        
        // Encontrar a linha da tabela antes de fazer a requisição
        const linhaAgendamento = document.querySelector(`tr[data-agendamento-id="${id}"]`);
        
        fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            console.log('🔧 [DEBUG] Status da resposta:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('🔧 [DEBUG] Resposta bruta:', text);
            try {
                const data = JSON.parse(text);
                console.log('🔧 [DEBUG] Dados parseados:', data);
                if (data.sucesso) {
                    // Remover a linha da tabela sem recarregar a página
                    if (linhaAgendamento) {
                        // Salvar referências antes de remover
                        const tbody = linhaAgendamento.parentElement;
                        const container = tbody?.closest('.historico-agendamentos');
                        
                        // Adicionar animação de fade out antes de remover
                        linhaAgendamento.style.transition = 'opacity 0.3s ease-out';
                        linhaAgendamento.style.opacity = '0';
                        
                        setTimeout(() => {
                            linhaAgendamento.remove();
                            console.log('✅ [DEBUG] Linha removida da tabela');
                            
                            // Verificar se a tabela ficou vazia e mostrar mensagem
                            if (tbody && tbody.querySelectorAll('tr').length === 0) {
                                if (container) {
                                    const table = container.querySelector('.table-responsive');
                                    if (table) {
                                        table.remove();
                                        container.insertAdjacentHTML('beforeend', `
                                            <div class="text-center py-4">
                                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                                <h6 class="text-muted">Nenhum agendamento encontrado</h6>
                                                <p class="text-muted small">Não há aulas agendadas para esta disciplina ainda.</p>
                                            </div>
                                        `);
                                    }
                                }
                            }
                        }, 300);
                    }
                    
                    // Atualizar estatísticas após cancelamento
                    setTimeout(() => {
                        atualizarEstatisticasTurma();
                    }, 400);
                    
                    showFeedback('✅ Agendamento cancelado com sucesso!', 'success');
                } else {
                    showFeedback('❌ Erro ao cancelar agendamento: ' + (data.mensagem || data.message || 'Erro desconhecido'), 'error');
                }
            } catch (e) {
                console.error('❌ [AGENDAMENTO] Resposta não é JSON válido:', text);
                showFeedback('❌ Erro: Resposta inválida do servidor', 'error');
            }
        })
        .catch(error => {
            console.error('❌ [AGENDAMENTO] Erro:', error);
            showFeedback('❌ Erro ao cancelar agendamento: ' + error.message, 'error');
        });
    }
}
// Salvar edição de agendamento
function salvarEdicaoAgendamento() {
    const form = document.getElementById('formEditarAgendamento');
    const formData = new FormData(form);
    
    // Garantir cálculo automático da hora fim antes de coletar dados
    if (typeof calcularHoraFimAuto === 'function') {
        calcularHoraFimAuto();
    }

    // Validar campos obrigatórios (hora_fim é calculada automaticamente)
    const camposObrigatorios = ['nome_aula', 'data_aula', 'hora_inicio', 'instrutor_id'];
    for (let campo of camposObrigatorios) {
        if (!formData.get(campo)) {
            showFeedback(`Campo obrigatório: ${campo.replace('_', ' ')}`, 'error');
            return;
        }
    }
    
    // Validar data não pode ser no passado
    const dataAula = new Date(formData.get('data_aula'));
    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);
    
    if (dataAula < hoje) {
        showFeedback('A data da aula não pode ser no passado', 'error');
        return;
    }
    
    // Validar horários
    const horaInicio = formData.get('hora_inicio');
    const horaFim = formData.get('hora_fim');
    
    if (horaInicio && horaFim && horaFim <= horaInicio) {
        showFeedback('A hora de fim deve ser posterior à hora de início', 'error');
        return;
    }
    
    // Converter FormData para objeto
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    // Garantir que observações seja incluído
    const observacoes = document.getElementById('editObservacoes');
    if (observacoes) {
        data.observacoes = observacoes.value;
    }
    
    // [FIX] FASE 2 - EDICAO DISCIPLINA TURMA 16: Garantir que disciplina seja incluída no payload
    const editDisciplina = document.getElementById('editDisciplina');
    if (editDisciplina && editDisciplina.value) {
        data.disciplina = editDisciplina.value;
        console.log('✅ [FIX FASE 2] Disciplina incluída no payload:', editDisciplina.value);
    } else {
        console.warn('⚠️ [FIX FASE 2] Campo disciplina não encontrado ou vazio no formEditarAgendamento');
    }
    
    data.acao = 'editar_aula';
    data.aula_id = document.getElementById('editAgendamentoId').value;
    
    // Debug: mostrar dados que serão enviados
    console.log('🔧 [DEBUG] Dados a serem enviados:', data);
    
    // Mostrar loading no botão
    const btnSalvar = document.querySelector('#modalEditarAgendamento .popup-save-button');
    let restaurarBtn = null;
    if (btnSalvar) {
        restaurarBtn = mostrarLoading(btnSalvar);
    }
    
    fetch(getBasePath() + '/admin/api/turmas-teoricas.php', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.text())
    .then(text => {
        if (restaurarBtn) restaurarBtn(); // Restaurar botão
        try {
            const data = JSON.parse(text);
            if (data.sucesso || data.success) {
                showFeedback(data.mensagem || 'Agendamento editado com sucesso!', 'success');
                // Atualizar linha da tabela (sem recarregar)
                try {
                    const row = document.querySelector(`[data-agendamento-id="${window.currentEditAgendamentoId}"]`);
                    if (row) {
                        const nome = document.getElementById('editNomeAula')?.value || '';
                        const dataAula = document.getElementById('editDataAula')?.value || '';
                        const horaInicio = document.getElementById('editHoraInicio')?.value || '';
                        const horaFim = document.getElementById('editHoraFim')?.value || '';
                        const instrutorSel = document.getElementById('editInstrutor');
                        const instrutorNome = instrutorSel && instrutorSel.options[instrutorSel.selectedIndex] ? instrutorSel.options[instrutorSel.selectedIndex].text : '';
                        const salaSel = document.getElementById('editSala');
                        const salaNome = salaSel && salaSel.options[salaSel.selectedIndex] ? salaSel.options[salaSel.selectedIndex].text : '';
                        
                        const cells = row.querySelectorAll('td');
                        if (cells.length >= 7) {
                            cells[0].innerHTML = `<strong>${nome}</strong>`;
                            if (dataAula) {
                                const dataBR = new Date(dataAula + 'T00:00:00').toLocaleDateString('pt-BR');
                                cells[1].textContent = dataBR;
                            }
                            cells[2].textContent = `${horaInicio?.slice(0,5)} - ${horaFim?.slice(0,5)}`;
                            cells[3].textContent = instrutorNome || cells[3].textContent;
                            cells[4].textContent = salaNome || cells[4].textContent;
                            cells[5].textContent = '50 min';
                        }
                    }
                } catch (e) { console.error('Erro ao atualizar linha editada:', e); }
                // Fechar modal sem recarregar
                fecharModalEdicao();
            } else {
                showFeedback('Erro ao editar agendamento: ' + (data.mensagem || data.message || data.error || 'Erro desconhecido'), 'error');
            }
        } catch (e) {
            console.error('❌ [AGENDAMENTO] Resposta não é JSON válido:', text);
            showFeedback('Erro: Resposta inválida do servidor', 'error');
        }
    })
    .catch(error => {
        if (restaurarBtn) restaurarBtn(); // Restaurar botão em caso de erro
        console.error('❌ [AGENDAMENTO] Erro:', error);
        showFeedback('Erro ao editar agendamento: ' + (error?.message || 'Erro desconhecido'), 'error');
    });
}

// Criar modal de edição dinamicamente seguindo o padrão do sistema
function criarModalEdicao() {
    const modal = document.createElement('div');
    modal.id = 'modalEditarAgendamento';
    modal.className = 'popup-modal';
    modal.innerHTML = `
        <div class="popup-modal-wrapper">
            <div class="popup-modal-header">
                <div class="header-content">
                    <div class="header-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="header-text">
                        <h5>Editar Agendamento</h5>
                        <small>Modifique os detalhes do agendamento selecionado</small>
                    </div>
                </div>
                <button type="button" class="popup-modal-close" onclick="fecharModalEdicao()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="popup-modal-content">
                <form id="formEditarAgendamento">
                    <input type="hidden" id="editAgendamentoId" name="aula_id">
                    <!-- [FIX] FASE 2 - EDICAO DISCIPLINA TURMA 16: Campo disciplina para garantir envio no FormData -->
                    <input type="hidden" name="disciplina" id="editDisciplina">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="editNomeAula" class="form-label fw-semibold">
                                Nome da Aula <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="editNomeAula" name="nome_aula" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editDataAula" class="form-label fw-semibold">
                                Data da Aula <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="editDataAula" name="data_aula" required>
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-3">
                        <div class="col-md-4">
                            <label for="editHoraInicio" class="form-label fw-semibold">
                                Hora Início <span class="text-danger">*</span>
                            </label>
                            <input type="time" 
                                   class="form-control" 
                                   id="editHoraInicio" 
                                   name="hora_inicio" 
                                   placeholder="HH:MM"
                                   step="60"
                                   required>
                        </div>
                        <div class="col-md-4">
                            <label for="editHoraFim" class="form-label fw-semibold">
                                Hora Fim
                            </label>
                            <div class="form-control-plaintext bg-light border rounded p-2">
                                <i class="fas fa-clock me-2 text-primary"></i>
                                <strong id="editHoraFimDisplay">--:--</strong>
                                <small class="text-muted ms-2">(calculada automaticamente)</small>
                            </div>
                            <input type="hidden" id="editHoraFim" name="hora_fim">
                        </div>
                        <div class="col-md-4">
                            <label for="editDuracaoDisplay" class="form-label fw-semibold">
                                Duração (min)
                            </label>
                            <div class="form-control-plaintext bg-light border rounded p-2">
                                <i class="fas fa-hourglass-half me-2 text-primary"></i>
                                <strong id="editDuracaoDisplay">50 min</strong>
                            </div>
                            <input type="hidden" id="editDuracao" name="duracao" value="50">
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label for="editInstrutor" class="form-label fw-semibold">
                                Instrutor <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="editInstrutor" name="instrutor_id" required>
                                <option value="">Selecione um instrutor</option>
                                <!-- Será preenchido via AJAX -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editSala" class="form-label fw-semibold">Sala</label>
                            <select class="form-select" id="editSala" name="sala_id">
                                <option value="">Selecione uma sala</option>
                                <!-- Será preenchido via AJAX -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label for="editObservacoes" class="form-label fw-semibold">Observações</label>
                        <textarea class="form-control" id="editObservacoes" name="observacoes" rows="3" placeholder="Digite observações adicionais sobre o agendamento..."></textarea>
                    </div>
                </form>
            </div>
            
            <div class="popup-modal-footer">
                <div class="popup-footer-info">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        Campos marcados com * são obrigatórios
                    </small>
                </div>
                <div class="popup-footer-actions">
                    <button type="button" class="popup-secondary-button" onclick="fecharModalEdicao()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="button" class="popup-save-button" onclick="salvarEdicaoAgendamento()">
                        <i class="fas fa-save"></i>
                        Salvar Alterações
                    </button>
                </div>
            </div>
        </div>
    `;
    
    return modal;
}
// Função para fechar o modal de edição
function fecharModalEdicao() {
    const modais = document.querySelectorAll('#modalEditarAgendamento');
    modais.forEach(modal => {
        if (modal) {
            modal.classList.remove('show');
            modal.style.cssText = 'display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important;';
            setTimeout(() => {
                if (modal.parentNode) {
                    modal.parentNode.removeChild(modal);
                }
            }, 300);
        }
    });
    
    // Restaurar body
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    document.body.classList.remove('modal-open', 'modal-unlocked-view');
    
    // Remover backdrops
    document.querySelectorAll('.modal-backdrop, .modal-overlay').forEach(b => {
        if (b.id !== 'modalAgendarAula' && b.parentNode) {
            b.parentNode.removeChild(b);
        }
    });
}

// Função para carregar dados dos selects
function carregarDadosSelects(instrutorId = null, salaId = null) {
    // Retornar Promise para permitir .then() nas chamadas
    return new Promise((resolve) => {
        console.log('🔧 [DEBUG] Carregando dados dos selects...');
        
        // CORREÇÃO (12/12/2025): Passar turma_id para filtrar por CFC da turma
        const turmaIdParam = TURMA_ID_DETALHES || (new URLSearchParams(window.location.search).get('turma_id'));
        
        // #region agent log
        fetch('http://127.0.0.1:7242/ingest/8f119121-d835-49f7-9624-6efdbdcd4639',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'turmas-teoricas-detalhes-inline.php:12499',message:'Carregando instrutores - antes do fetch',data:{turmaIdParam: turmaIdParam, TURMA_ID_DETALHES: typeof TURMA_ID_DETALHES !== 'undefined' ? TURMA_ID_DETALHES : 'undefined', urlParams: new URLSearchParams(window.location.search).get('turma_id')},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'B'})}).catch(()=>{});
        // #endregion
        
        // Carregar instrutores - usar o select do modal unificado
        fetch('api/instrutores-real.php' + (turmaIdParam ? `?turma_id=${turmaIdParam}` : ''))
            .then(response => {
                console.log('🔧 [DEBUG] Resposta instrutores:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('🔧 [DEBUG] Dados instrutores:', data);
                
                // #region agent log
                fetch('http://127.0.0.1:7242/ingest/8f119121-d835-49f7-9624-6efdbdcd4639',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'turmas-teoricas-detalhes-inline.php:12507',message:'Resposta da API recebida',data:{success: data.success, total: data.total, instrutor_ids: data.instrutores ? data.instrutores.map(i => i.id) : [], instrutor_nomes: data.instrutores ? data.instrutores.map(i => i.nome) : [], tem_carlos: data.instrutores ? data.instrutores.some(i => i.nome && i.nome.toLowerCase().includes('carlos')) : false},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A,C,D,E'})}).catch(()=>{});
                // #endregion
                
                if (data.success) {
                    // Usar o select do modal unificado
                    const selectInstrutor = document.getElementById('modal_instrutor_id');
                    if (selectInstrutor) {
                        // Limpar opções existentes, mas manter a primeira opção se existir
                        const primeiraOpcao = selectInstrutor.querySelector('option[value=""]');
                        selectInstrutor.innerHTML = primeiraOpcao ? primeiraOpcao.outerHTML : '<option value="">Selecione um instrutor...</option>';
                        
                        data.instrutores.forEach(instrutor => {
                            const option = document.createElement('option');
                            option.value = instrutor.id;
                            option.textContent = instrutor.nome || 'Instrutor sem nome';
                            if (instrutor.categoria_habilitacao) {
                                option.textContent += ' - ' + instrutor.categoria_habilitacao;
                            }
                            selectInstrutor.appendChild(option);
                        });
                        
                        // #region agent log
                        fetch('http://127.0.0.1:7242/ingest/8f119121-d835-49f7-9624-6efdbdcd4639',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'turmas-teoricas-detalhes-inline.php:12525',message:'Opções adicionadas ao select',data:{total_opcoes: selectInstrutor.options.length, opcoes_texto: Array.from(selectInstrutor.options).map(o => o.textContent)},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'E'})}).catch(()=>{});
                        // #endregion
                        
                        // Definir valor selecionado se fornecido
                        if (instrutorId) {
                            selectInstrutor.value = instrutorId;
                            console.log('✅ [DEBUG] Instrutor selecionado:', instrutorId);
                        }
                        
                        console.log('✅ [DEBUG] Instrutores carregados:', data.instrutores.length);
                    } else {
                        console.log('❌ [DEBUG] Select instrutor não encontrado (modal_instrutor_id)');
                    }
                } else {
                    console.log('❌ [DEBUG] Erro ao carregar instrutores:', data.message);
                }
                
                // Resolver promise após carregar instrutores
                resolve();
            })
            .catch(error => {
                console.error('❌ [DEBUG] Erro ao carregar instrutores:', error);
                // Resolver mesmo em caso de erro
                resolve();
            });
    });
}

// Carregar horários disponíveis (simplificado para input time)
function carregarHorariosDisponiveis(horarioCustomizado = null) {
    return new Promise((resolve, reject) => {
        // Normalizar horário customizado para formato HH:MM (remover segundos se houver)
        let horarioCustomizadoNormalizado = null;
        if (horarioCustomizado) {
            // Se o horário tem segundos (HH:MM:SS), converter para HH:MM
            if (horarioCustomizado.length === 8 && horarioCustomizado.includes(':')) {
                horarioCustomizadoNormalizado = horarioCustomizado.substring(0, 5);
            } else {
                horarioCustomizadoNormalizado = horarioCustomizado;
            }
        }
        
        const inputHoraInicio = document.getElementById('editHoraInicio');
        if (inputHoraInicio) {
            // Se houver horário customizado, definir o valor
            if (horarioCustomizadoNormalizado) {
                inputHoraInicio.value = horarioCustomizadoNormalizado;
            }
            
            // Adicionar listener para calcular hora de fim automaticamente
            if (!inputHoraInicio.hasAttribute('data-listener-added')) {
                inputHoraInicio.addEventListener('change', calcularHoraFimAuto);
                inputHoraInicio.addEventListener('input', calcularHoraFimAuto);
                inputHoraInicio.setAttribute('data-listener-added', 'true');
            }
            
            // Calcular hora de fim automaticamente se houver valor
            if (horarioCustomizadoNormalizado) {
                setTimeout(() => calcularHoraFimAuto(), 100);
            }
            
            // Retornar o horário customizado normalizado (se houver)
            resolve(horarioCustomizadoNormalizado || null);
        } else {
            reject('Input hora_inicio não encontrado');
        }
    });
}

// Calcular hora de fim automaticamente (hora início + duração)
function calcularHoraFimAuto() {
    const inputHoraInicio = document.getElementById('editHoraInicio');
    const inputHoraFim = document.getElementById('editHoraFim');
    const displayHoraFim = document.getElementById('editHoraFimDisplay');
    const editDuracao = document.getElementById('editDuracao');
    
    if (inputHoraInicio && inputHoraFim && displayHoraFim) {
        const horaInicio = inputHoraInicio.value;
        
        if (horaInicio) {
            // Obter duração do campo ou usar 50 como padrão
            const duracaoMinutos = editDuracao && editDuracao.value ? parseInt(editDuracao.value) : 50;
            
            // Calcular hora de fim (hora início + duração)
            const [horas, minutos] = horaInicio.split(':');
            const dataInicio = new Date();
            dataInicio.setHours(parseInt(horas), parseInt(minutos), 0, 0);
            
            const dataFim = new Date(dataInicio.getTime() + duracaoMinutos * 60000);
            const horaFim = dataFim.toTimeString().slice(0, 5); // Formato HH:MM
            
            // Atualizar input hidden e display
            inputHoraFim.value = horaFim;
            displayHoraFim.textContent = horaFim;
            
            console.log('✅ [HORÁRIO] Hora fim calculada:', horaFim, '- Duração:', duracaoMinutos, 'minutos');
        } else {
            inputHoraFim.value = '';
            displayHoraFim.textContent = '--:--';
        }
    }
}

// Anexar listeners para calcular a hora de fim automaticamente
function anexarAutoCalculoHoraFim() {
    const campoInicio = document.getElementById('editHoraInicio');
    if (!campoInicio) return;
    
    const handler = () => {
        console.log('🔄 [HORÁRIO] Evento disparado, calculando hora fim...');
        calcularHoraFimAuto();
    };
    
    // Remover listeners antigos
    campoInicio.removeEventListener('change', handler);
    campoInicio.removeEventListener('blur', handler);
    campoInicio.removeEventListener('input', handler);
    
    // Adicionar novos listeners
    campoInicio.addEventListener('change', handler);
    campoInicio.addEventListener('blur', handler);
    campoInicio.addEventListener('input', handler);
    
    console.log('✅ [HORÁRIO] Listeners anexados para cálculo automático');
}

// Validação automática de horários
function validarHorarios() {
    const horaInicio = document.getElementById('editHoraInicio');
    const inputHoraFim = document.getElementById('editHoraFim');
    
    if (horaInicio && inputHoraFim) {
        // Calcular hora de fim automaticamente quando hora início mudar
        horaInicio.addEventListener('change', calcularHoraFimAuto);
    }
}

// Funções antigas removidas - usando calcularHoraFimAuto() agora

// Melhorar feedback visual
function mostrarLoading(button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processando...';
    button.disabled = true;
    
    return function() {
        button.innerHTML = originalText;
        button.disabled = false;
    };
}
// Carregar dados para os selects quando o modal for aberto
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 [DEBUG] Iniciando carregamento de dados...');
    
    // Carregar instrutores
    console.log('🔧 [DEBUG] Carregando instrutores...');
    // CORREÇÃO (12/12/2025): Passar turma_id para filtrar por CFC da turma
    const turmaIdParam = TURMA_ID_DETALHES || (new URLSearchParams(window.location.search).get('turma_id'));
    fetch('api/instrutores-real.php' + (turmaIdParam ? `?turma_id=${turmaIdParam}` : ''))
        .then(response => {
            console.log('🔧 [DEBUG] Resposta instrutores:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('🔧 [DEBUG] Dados instrutores:', data);
            if (data.success) {
                const selectInstrutor = document.getElementById('editInstrutor');
                if (selectInstrutor) {
                    selectInstrutor.innerHTML = '<option value="">Selecione um instrutor</option>';
                    data.instrutores.forEach(instrutor => {
                        const option = document.createElement('option');
                        option.value = instrutor.id;
                        option.textContent = instrutor.nome;
                        selectInstrutor.appendChild(option);
                    });
                    console.log('✅ [DEBUG] Instrutores carregados:', data.instrutores.length);
                } else {
                    console.log('❌ [DEBUG] Select instrutor não encontrado');
                }
            } else {
                console.log('❌ [DEBUG] Erro ao carregar instrutores:', data.message);
            }
        })
        .catch(error => {
            console.error('❌ [DEBUG] Erro ao carregar instrutores:', error);
        });
    
    // Carregar salas
    console.log('🔧 [DEBUG] Carregando salas...');
    fetch('api/salas-real.php')
        .then(response => {
            console.log('🔧 [DEBUG] Resposta salas:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('🔧 [DEBUG] Dados salas:', data);
            if (data.success) {
                const selectSala = document.getElementById('editSala');
                if (selectSala) {
                    selectSala.innerHTML = '<option value="">Selecione uma sala</option>';
                    data.salas.forEach(sala => {
                        const option = document.createElement('option');
                        option.value = sala.id;
                        option.textContent = sala.nome;
                        selectSala.appendChild(option);
                    });
                    console.log('✅ [DEBUG] Salas carregadas:', data.salas.length);
                } else {
                    console.log('❌ [DEBUG] Select sala não encontrado');
                }
            } else {
                console.log('❌ [DEBUG] Erro ao carregar salas:', data.message);
            }
        })
        .catch(error => {
            console.error('❌ [DEBUG] Erro ao carregar salas:', error);
        });
    
    // Configurar validações
    validarHorarios();
});

// Função para abrir modal de inserir alunos
function abrirModalInserirAlunos() {
    const turmaId = <?= $turmaId ?>;
    
    // Verificar se o modal existe antes de tentar acessá-lo
    const modal = document.getElementById('modalInserirAlunos');
    if (!modal) {
        console.error('Modal modalInserirAlunos não encontrado');
        mostrarMensagem('Erro: Modal não encontrado', 'error');
        return;
    }
    
    // Mostrar modal
    modal.style.display = 'flex';
    
    // Carregar alunos aptos
    carregarAlunosAptos(turmaId);
}

// Função para fechar modal
function fecharModalInserirAlunos() {
    const modal = document.getElementById('modalInserirAlunos');
    if (modal) {
        modal.style.display = 'none';
    }
}

const TURMA_ID_DETALHES = <?= $turmaId ?>;
const TURMA_CFC_ID = <?= (int)($turma['cfc_id'] ?? 0) ?>;
const SESSION_CFC_ID = <?= (int)($user['cfc_id'] ?? 0) ?>;

// Utilitários para manipulação de alunos matriculados e aptos
function escapeHtml(value) {
    if (value === null || value === undefined) {
        return '';
    }
    return value
        .toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function obterIniciais(nome) {
    if (!nome) return '';
    const partes = nome.trim().split(/\s+/).slice(0, 2);
    return partes.map(parte => parte[0] ? parte[0].toUpperCase() : '').join('');
}

function formatarDataHoraBrasileira(dataIso) {
    const data = dataIso ? new Date(dataIso) : new Date();
    if (Number.isNaN(data.getTime())) {
        return { data: '--/--/----', hora: '--:--' };
    }
    return {
        data: data.toLocaleDateString('pt-BR'),
        hora: data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
    };
}

function gerarStatusBadge(statusRaw) {
    const status = (statusRaw || '').toLowerCase();
    const classes = ['status-badge'];
    let statusIcon = 'fas fa-question-circle';
    let statusText = statusRaw ? statusRaw.charAt(0).toUpperCase() + statusRaw.slice(1) : 'Matriculado';

    switch (status) {
        case 'matriculado':
            classes.push('status-matriculado');
            statusIcon = 'fas fa-user-check';
            statusText = 'Matriculado';
            break;
        case 'cursando':
            classes.push('status-cursando');
            statusIcon = 'fas fa-graduation-cap';
            statusText = 'Cursando';
            break;
        case 'evadido':
            classes.push('status-matriculado');
            statusIcon = 'fas fa-user-check';
            statusText = 'Matriculado';
            break;
        case 'transferido':
            classes.push('status-transferido');
            statusIcon = 'fas fa-exchange-alt';
            statusText = 'Transferido';
            break;
        case 'concluido':
            classes.push('status-concluido');
            statusIcon = 'fas fa-check-circle';
            statusText = 'Concluído';
            break;
        default:
            break;
    }

    return `
        <span class="${classes.join(' ')}">
            <i class="${statusIcon}"></i>
            ${statusText}
        </span>
    `;
}

function gerarMarkupTabelaBase() {
    return `
        <div style="overflow-x: auto;">
            <table class="alunos-table" id="tabela-alunos-matriculados">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>Categoria</th>
                        <th>CFC</th>
                        <th style="text-align: center;">Status</th>
                        <th>Data Matrícula</th>
                        <th style="text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody id="tabela-alunos-matriculados-body"></tbody>
            </table>
        </div>
    `;
}

function gerarMarkupEstadoVazio() {
    return `
        <div style="text-align: center; padding: 40px 20px; color: #6c757d;" data-empty-state-alunos>
            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
            <h5 style="margin-bottom: 10px; color: #495057;">Nenhum aluno matriculado</h5>
            <p style="margin-bottom: 20px;">Esta turma ainda não possui alunos matriculados.</p>
            <button onclick="abrirModalInserirAlunos()" class="btn-primary" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-weight: 500; transition: all 0.3s;">
                <i class="fas fa-user-plus"></i>
                Matricular Primeiro Aluno
            </button>
        </div>
    `;
}

function garantirEstruturaTabelaAlunos() {
    const wrapper = document.getElementById('lista-alunos-matriculados');
    if (!wrapper) {
        return null;
    }

    let tabela = document.getElementById('tabela-alunos-matriculados');
    let tbody = document.getElementById('tabela-alunos-matriculados-body');

    if (!tabela || !tbody) {
        wrapper.innerHTML = gerarMarkupTabelaBase();
        tabela = document.getElementById('tabela-alunos-matriculados');
        tbody = document.getElementById('tabela-alunos-matriculados-body');
    }

    return { wrapper, tabela, tbody };
}

// Função helper para obter categoria priorizando matrícula ativa (reutilizada do módulo de alunos)
function obterCategoriaExibicao(aluno) {
    // Prioridade 1: Categoria da matrícula ativa
    if (aluno.categoria_cnh_matricula) {
        return aluno.categoria_cnh_matricula;
    }
    // Prioridade 2: Categoria do aluno (fallback)
    if (aluno.categoria_cnh) {
        return aluno.categoria_cnh;
    }
    // Prioridade 3: Tentar extrair de operações
    if (aluno.operacoes) {
        try {
            const operacoes = typeof aluno.operacoes === 'string' ? JSON.parse(aluno.operacoes) : aluno.operacoes;
            if (Array.isArray(operacoes) && operacoes.length > 0) {
                const primeiraOp = operacoes[0];
                return primeiraOp.categoria || primeiraOp.categoria_cnh || 'N/A';
            }
        } catch (e) {
            // Ignorar erro de parse
        }
    }
    return 'N/A';
}

function gerarLinhaAlunoMatriculado(alunoInfo, matriculaInfo) {
    const iniciais = escapeHtml(obterIniciais(alunoInfo.nome));
    const dataFormatada = formatarDataHoraBrasileira(matriculaInfo?.data_matricula);
    
    // Obter categoria priorizando matrícula ativa
    const categoriaExibicao = obterCategoriaExibicao(alunoInfo);
    const badgeClass = alunoInfo.categoria_cnh_matricula ? 'bg-primary' : 'bg-secondary';

    return `
        <tr data-aluno-id="${alunoInfo.id}">
            <td>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div class="aluno-avatar">
                        ${iniciais}
                    </div>
                    <div>
                        <div style="font-weight: 600; color: #2c3e50; margin-bottom: 2px;">${escapeHtml(alunoInfo.nome)}</div>
                        ${alunoInfo.email ? `<div style="font-size: 0.8rem; color: #6c757d;"><i class="fas fa-envelope me-1"></i>${escapeHtml(alunoInfo.email)}</div>` : ''}
                        ${alunoInfo.telefone ? `<div style="font-size: 0.8rem; color: #6c757d;"><i class="fas fa-phone me-1"></i>${escapeHtml(alunoInfo.telefone)}</div>` : ''}
                    </div>
                </div>
            </td>
            <td style="font-family: monospace; font-size: 0.9rem;">
                ${escapeHtml(alunoInfo.cpf || '')}
            </td>
            <td>
                <span class="badge ${badgeClass}" style="padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;" title="Categoria CNH">
                    ${escapeHtml(categoriaExibicao || '--')}
                </span>
            </td>
            <td>
                <span style="color: #495057; font-size: 0.9rem;">
                    ${escapeHtml(alunoInfo.cfc_nome || '--')}
                </span>
            </td>
            <td style="text-align: center;">
                ${gerarStatusBadge(matriculaInfo?.status || alunoInfo.status || 'matriculado')}
            </td>
            <td style="font-size: 0.9rem; color: #6c757d;">
                <i class="fas fa-calendar-alt me-1"></i>
                ${dataFormatada.data}
                <div style="font-size: 0.8rem; color: #adb5bd;">
                    ${dataFormatada.hora}
                </div>
            </td>
            <td style="text-align: center;">
                <div class="action-buttons">
                    <button class="action-btn action-btn-outline-danger" data-role="remover-matricula" title="Remover da Turma">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `;
}

function adicionarAlunoMatriculadoNaTabela(alunoInfo, matriculaInfo) {
    const estrutura = garantirEstruturaTabelaAlunos();
    if (!estrutura || !estrutura.tbody) {
        return;
    }

    const emptyState = estrutura.wrapper.querySelector('[data-empty-state-alunos]');
    if (emptyState) {
        emptyState.remove();
        estrutura.wrapper.innerHTML = gerarMarkupTabelaBase();
        estrutura.tabela = document.getElementById('tabela-alunos-matriculados');
        estrutura.tbody = document.getElementById('tabela-alunos-matriculados-body');
    }

    estrutura.tbody.insertAdjacentHTML('afterbegin', gerarLinhaAlunoMatriculado(alunoInfo, matriculaInfo));

    const novaLinha = estrutura.tbody.querySelector(`tr[data-aluno-id="${alunoInfo.id}"]`);
    if (novaLinha) {
        const removerBtn = novaLinha.querySelector('[data-role="remover-matricula"]');
        if (removerBtn) {
            removerBtn.addEventListener('click', () => removerMatricula(alunoInfo.id, alunoInfo.nome));
        }
    }
}

function renderizarEstadoAlunosVazio() {
    const wrapper = document.getElementById('lista-alunos-matriculados');
    if (!wrapper) {
        return;
    }
    wrapper.innerHTML = gerarMarkupEstadoVazio();
}

// Função para carregar alunos aptos
function carregarAlunosAptos(turmaId) {
    const container = document.getElementById('listaAlunosAptos');
    const loading = document.getElementById('loadingAlunos');
    
    // Mostrar loading
    loading.style.display = 'block';
    container.innerHTML = '';
    
    // Fazer requisição para buscar alunos aptos
    fetch('api/alunos-aptos-turma-simples.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin', // Incluir cookies de sessão
        body: JSON.stringify({
            turma_id: turmaId
        })
    })
    .then(response => {
        console.log('Resposta da API:', response.status, response.statusText);
        return response.json();
    })
    .then(data => {
        loading.style.display = 'none';
        console.log('Dados recebidos:', data);
        console.log('[TURMAS TEORICAS FRONTEND] Debug Info recebido:', data.debug_info);
        
        if (data.sucesso) {
            // Garantir que debug_info tenha os valores de CFC
            const debugInfo = data.debug_info || {};
            if (!debugInfo.turma_cfc_id && TURMA_CFC_ID) {
                debugInfo.turma_cfc_id = TURMA_CFC_ID;
            }
            if (!debugInfo.session_cfc_id && SESSION_CFC_ID) {
                debugInfo.session_cfc_id = SESSION_CFC_ID;
            }
            if (debugInfo.turma_cfc_id && debugInfo.session_cfc_id) {
                debugInfo.cfc_ids_match = (debugInfo.turma_cfc_id === debugInfo.session_cfc_id);
            }
            
            exibirAlunosAptos(data.alunos, turmaId, debugInfo);
        } else {
            container.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${data.mensagem || 'Erro ao carregar alunos aptos'}
                    ${data.debug ? '<br><small>Debug: ' + JSON.stringify(data.debug) + '</small>' : ''}
                </div>
            `;
        }
    })
    .catch(error => {
        loading.style.display = 'none';
        console.error('Erro na requisição:', error);
        container.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                Erro ao carregar alunos: ${error.message}
                <br><small>Verifique o console para mais detalhes</small>
            </div>
        `;
    });
}

// Função para exibir alunos aptos
function exibirAlunosAptos(alunos, turmaId, debugInfo = null) {
    const container = document.getElementById('listaAlunosAptos');
    
        if (alunos.length === 0) {
        let debugHtml = '';
        if (debugInfo) {
            const sessionLabel = debugInfo.session_cfc_label || (debugInfo.session_cfc_id === 0 ? 'admin_global' : 'cfc_especifico');
            const isAdminGlobal = debugInfo.is_admin_global || (debugInfo.session_cfc_id === 0);
            const cfcMatchText = isAdminGlobal ? 'N/A (Admin Global)' : (debugInfo.cfc_ids_match ? 'Sim' : 'Não');
            
            debugHtml = `
                <div class="alert alert-warning mt-2">
                    <i class="fas fa-bug"></i>
                    <strong>Debug Info:</strong><br>
                    CFC da Turma: ${debugInfo.turma_cfc_id || 'N/A'}<br>
                    CFC da Sessão: ${debugInfo.session_cfc_id || 0} (${sessionLabel})<br>
                    CFCs coincidem: ${cfcMatchText}<br>
                    ${debugInfo.total_candidatos !== undefined ? `Total candidatos: ${debugInfo.total_candidatos}<br>` : ''}
                    ${debugInfo.total_aptos !== undefined ? `Total aptos: ${debugInfo.total_aptos}<br>` : ''}
                </div>
            `;
        }
        
        container.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Nenhum aluno encontrado com exames médico e psicotécnico aprovados.
                ${debugHtml}
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="alunos-grid">
            ${alunos.map(aluno => {
                // Obter categoria priorizando matrícula ativa
                const categoriaExibicao = obterCategoriaExibicao(aluno);
                return `
                <div class="aluno-card" data-aluno-id="${aluno.id}">
                    <div class="aluno-info">
                        <h4>${escapeHtml(aluno.nome)}</h4>
                        <p><strong>CPF:</strong> ${escapeHtml(aluno.cpf || '')}</p>
                        <p><strong>Categoria:</strong> ${escapeHtml(categoriaExibicao || '')}</p>
                        <p><strong>CFC:</strong> ${escapeHtml(aluno.cfc_nome || '')}</p>
                    </div>
                    <div class="exames-status">
                        ${aluno.exame_medico_resultado ? `
                            <div class="exame-status ${aluno.exame_medico_resultado === 'apto' || aluno.exame_medico_resultado === 'aprovado' ? 'apto' : 'inapto'}">
                                <i class="fas fa-user-md"></i>
                                <span>Médico: ${aluno.exame_medico_resultado === 'apto' || aluno.exame_medico_resultado === 'aprovado' ? 'Apto' : (aluno.exame_medico_resultado === 'inapto' || aluno.exame_medico_resultado === 'reprovado' ? 'Inapto' : aluno.exame_medico_resultado)}</span>
                            </div>
                        ` : ''}
                        ${aluno.exame_psicotecnico_resultado ? `
                            <div class="exame-status ${aluno.exame_psicotecnico_resultado === 'apto' || aluno.exame_psicotecnico_resultado === 'aprovado' ? 'apto' : 'inapto'}">
                                <i class="fas fa-brain"></i>
                                <span>Psicotécnico: ${aluno.exame_psicotecnico_resultado === 'apto' || aluno.exame_psicotecnico_resultado === 'aprovado' ? 'Apto' : (aluno.exame_psicotecnico_resultado === 'inapto' || aluno.exame_psicotecnico_resultado === 'reprovado' ? 'Inapto' : aluno.exame_psicotecnico_resultado)}</span>
                            </div>
                        ` : ''}
                    </div>
                    <div class="aluno-actions">
                        <button class="btn btn-success btn-sm" data-role="matricular-aluno" onclick="matricularAluno(${aluno.id}, ${turmaId}, this)">
                            <i class="fas fa-user-plus"></i>
                            Matricular
                        </button>
                    </div>
                </div>
            `;
            }).join('')}
        </div>
    `;
    
    container.innerHTML = html;

    // Persistir dados do aluno no botão para uso após matrícula
    container.querySelectorAll('.aluno-card').forEach(card => {
        const id = Number(card.dataset.alunoId);
        const aluno = alunos.find(item => Number(item.id) === id);
        if (!aluno) {
            return;
        }
        const button = card.querySelector('[data-role="matricular-aluno"]');
        if (button) {
            button.dataset.nome = aluno.nome || '';
            button.dataset.cpf = aluno.cpf || '';
            // Usar categoria priorizando matrícula ativa
            const categoriaExibicao = obterCategoriaExibicao(aluno);
            button.dataset.categoria = categoriaExibicao || '';
            button.dataset.cfc = aluno.cfc_nome || '';
            button.dataset.email = aluno.email || '';
            button.dataset.telefone = aluno.telefone || '';
            button.dataset.status = aluno.status_matricula || 'matriculado';
        }
    });
}

// Função para matricular aluno
function matricularAluno(alunoId, turmaId, buttonEl) {
    if (!confirm('Deseja realmente matricular este aluno na turma?')) {
        return;
    }
    
    const button = buttonEl || (typeof event !== 'undefined' ? (event.currentTarget || event.target) : null);
    
    if (!button) {
        console.warn('Botão de matrícula não encontrado no evento.');
    }
    
    const originalText = button ? button.innerHTML : null;
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Matriculando...';
        button.disabled = true;
    }
    
    const payload = {
        aluno_id: alunoId,
        turma_id: turmaId
    };
    
    // Log detalhado para debug
    console.log('[MATRICULAR_ALUNO] Enviando requisição', {
        url: 'api/matricular-aluno-turma.php',
        turmaId: turmaId,
        alunoId: alunoId,
        payload: payload
    });
    
    fetch('api/matricular-aluno-turma.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        credentials: 'same-origin', // Incluir cookies de sessão
        body: JSON.stringify(payload)
    })
    .then(async response => {
        // Tentar parsear JSON mesmo se status não for 200
        let data = null;
        try {
            const text = await response.text();
            data = text ? JSON.parse(text) : null;
        } catch (e) {
            console.error('[MATRICULAR_ALUNO] Erro ao parsear resposta JSON:', e);
            throw { tipo: 'parse', mensagem: 'Erro ao processar resposta do servidor' };
        }
        
        // Log da resposta
        console.log('[MATRICULAR_ALUNO] Resposta recebida', {
            status: response.status,
            ok: response.ok,
            data: data
        });
        
        // Se não tiver sucesso no JSON, tratar como erro
        if (!data || !data.sucesso) {
            const mensagemErro = data?.mensagem || 'Não foi possível matricular o aluno.';
            throw { tipo: 'api', mensagem: mensagemErro };
        }
        
        return data;
    })
    .then(data => {
        console.log('[MATRICULAR_ALUNO] Matrícula realizada com sucesso', data);

        const listaAptos = document.getElementById('listaAlunosAptos');
        const alunoCard = listaAptos ? listaAptos.querySelector(`[data-aluno-id="${alunoId}"]`) : null;
        const alunoDataset = (button || alunoCard)?.dataset || {};

        // Remover aluno da lista de aptos
        if (alunoCard) {
            alunoCard.remove();
        }

        // Preparar dados para tabela
        // Construir objeto aluno completo para usar obterCategoriaExibicao
        const alunoCompleto = {
            categoria_cnh: alunoDataset.categoria || data?.dados?.aluno?.categoria_cnh || '',
            categoria_cnh_matricula: data?.dados?.aluno?.categoria_cnh_matricula || null,
            operacoes: data?.dados?.aluno?.operacoes || null
        };
        
        const alunoInfo = {
            id: data?.dados?.aluno?.id ?? alunoId,
            nome: alunoDataset.nome || data?.dados?.aluno?.nome || '',
            cpf: alunoDataset.cpf || data?.dados?.aluno?.cpf || '',
            categoria_cnh: alunoDataset.categoria || data?.dados?.aluno?.categoria_cnh || '',
            categoria_cnh_matricula: data?.dados?.aluno?.categoria_cnh_matricula || null,
            cfc_nome: alunoDataset.cfc || data?.dados?.aluno?.cfc_nome || '',
            email: alunoDataset.email || data?.dados?.aluno?.email || '',
            telefone: alunoDataset.telefone || data?.dados?.aluno?.telefone || '',
            status: alunoDataset.status || data?.dados?.matricula?.status || 'matriculado',
            operacoes: data?.dados?.aluno?.operacoes || null
        };

        const matriculaInfo = {
            id: data?.dados?.matricula?.id,
            status: data?.dados?.matricula?.status || 'matriculado',
            data_matricula: data?.dados?.matricula?.data_matricula || new Date().toISOString()
        };

        adicionarAlunoMatriculadoNaTabela(alunoInfo, matriculaInfo);

        const totalMatriculados = data?.dados?.turma?.alunos_matriculados;
        if (typeof totalMatriculados === 'number') {
            atualizarContadorAlunos(totalMatriculados);
        } else {
            atualizarContadorAlunos();
        }

        mostrarMensagem('success', data.mensagem);
    })
    .catch(error => {
        console.error('[MATRICULAR_ALUNO] Erro inesperado', error);
        
        if (error?.tipo === 'api') {
            mostrarMensagem('error', error.mensagem);
            return;
        }
        
        if (error?.tipo === 'parse') {
            mostrarMensagem('error', error.mensagem);
            return;
        }
        
        const mensagem = error?.message || error?.mensagem || 'Erro desconhecido ao matricular aluno.';
        mostrarMensagem('error', 'Erro ao matricular aluno: ' + mensagem);
    })
    .finally(() => {
        if (button) {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    });
}

// Função para mostrar mensagens
function mostrarMensagem(param1, param2) {
    const tiposValidos = ['success', 'error', 'info', 'warning'];
    
    let tipo;
    let mensagem;
    
    if (tiposValidos.includes((param1 || '').toLowerCase())) {
        tipo = param1.toLowerCase();
        mensagem = param2;
    } else if (tiposValidos.includes((param2 || '').toLowerCase())) {
        tipo = param2.toLowerCase();
        mensagem = param1;
    } else {
        tipo = 'info';
        mensagem = param1 || param2 || 'Operação realizada.';
    }
    
    const alertClass = tipo === 'success' ? 'alert-success' : 
                     tipo === 'error' ? 'alert-danger' : 
                     tipo === 'info' ? 'alert-info' : 'alert-warning';
    const icon = tipo === 'success' ? 'fa-check-circle' : 
                tipo === 'error' ? 'fa-exclamation-circle' : 
                tipo === 'info' ? 'fa-info-circle' : 'fa-exclamation-triangle';
    
    const mensagemDiv = document.createElement('div');
    mensagemDiv.className = `alert ${alertClass} alert-dismissible fade show`;
    mensagemDiv.innerHTML = `
        <i class="fas ${icon}"></i>
        ${mensagem}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Inserir no topo da página principal
    const mainContent = document.querySelector('.main-content') || document.querySelector('.container-fluid') || document.body;
    mainContent.insertBefore(mensagemDiv, mainContent.firstChild);
    
    // Remover após 5 segundos
    setTimeout(() => {
        if (mensagemDiv.parentNode) {
            mensagemDiv.remove();
        }
    }, 5000);
}

// Função para atualizar contador de alunos na página principal
function atualizarContadorAlunos(total) {
    let quantidade = typeof total === 'number' && !Number.isNaN(total) ? total : null;

    if (quantidade === null) {
        const tbody = document.getElementById('tabela-alunos-matriculados-body');
        if (tbody) {
            quantidade = tbody.querySelectorAll('tr').length;
        }
    }

    if (quantidade === null) {
        return;
    }

    const badge = document.getElementById('total-alunos-matriculados-badge');
    if (badge) {
        badge.innerHTML = `<i class="fas fa-user-check me-1"></i>${quantidade} aluno(s)`;
        badge.dataset.quantidade = quantidade;
    }

    document.querySelectorAll('[data-total-alunos-label]').forEach(element => {
        if (element !== badge) {
            element.textContent = `${quantidade} aluno(s)`;
        }
    });
}

// Funções para ações dos alunos matriculados
function visualizarAluno(alunoId) {
    // Redirecionar para página de detalhes do aluno
    window.open(`?page=alunos&acao=detalhes&id=${alunoId}`, '_blank');
}

function editarMatricula(matriculaId) {
    // Implementar modal de edição de matrícula
    mostrarMensagem('Funcionalidade de edição de matrícula será implementada em breve.', 'info');
}

function removerMatricula(matriculaId, nomeAluno) {
    if (confirm(`Tem certeza que deseja remover a matrícula de ${nomeAluno} desta turma?\n\nEsta ação irá desvincular completamente o aluno da turma e ele ficará disponível para matrícula em outras turmas.`)) {
        // Mostrar indicador de carregamento
        mostrarMensagem('Removendo aluno da turma...', 'info');
        
        // Criar dados para enviar
        const payload = {
            turma_id: typeof TURMA_ID_DETALHES !== 'undefined' ? Number(TURMA_ID_DETALHES) : <?= $turmaId ?>,
            aluno_id: Number(matriculaId)
        };

        fetch('api/remover-matricula-turma.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
        .then(async response => {
            const contentType = response.headers.get('Content-Type') || '';
            if (contentType.includes('application/json')) {
                return response.json();
            }
            const text = await response.text();
            throw new Error(text.trim().substring(0, 200) || 'Resposta inválida do servidor');
        })
        .then(data => {
            if (data.sucesso) {
                // Mostrar mensagem de sucesso
                mostrarMensagem(data.mensagem, 'success');
                
                // Remover a linha da tabela
                const linha = document.querySelector(`tr[data-aluno-id="${matriculaId}"]`);
                if (linha) {
                    linha.remove();
                }

                const totalLinhas = typeof data?.dados?.alunos_matriculados === 'number'
                    ? data.dados.alunos_matriculados
                    : (() => {
                        const tbody = document.getElementById('tabela-alunos-matriculados-body');
                        return tbody ? tbody.querySelectorAll('tr').length : 0;
                    })();

                if (totalLinhas === 0) {
                    renderizarEstadoAlunosVazio();
                }

                atualizarContadorAlunos(totalLinhas);
            } else {
                // Mostrar mensagem de erro
                mostrarMensagem(data.mensagem, 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarMensagem('Erro ao remover aluno. Tente novamente.', 'error');
        });
    }
}

// ❌ FUNÇÃO ANTIGA REMOVIDA - usar a função enviarAgendamentoModal() mais completa definida abaixo

</script>

<style>
/* Estilos para o modal de inserir alunos */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
.modal-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8f9fa;
}

.modal-header h3 {
    margin: 0;
    color: #333;
    font-size: 18px;
    font-weight: 600;
}

#btn_excluir_modal {
    display: none;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
}

#btn_excluir_modal:hover {
    background-color: #c82333;
    transform: scale(1.05);
}

.btn-close {
    background: none;
    border: none;
    font-size: 18px;
    color: #6c757d;
    cursor: pointer;
    padding: 5px;
    border-radius: 4px;
    transition: all 0.3s;
}

.btn-close:hover {
    background: #e9ecef;
    color: #495057;
}
.modal-body {
    padding: 20px;
    flex: 1;
    overflow-y: auto;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #e5e7eb;
    background: #f8f9fa;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

/* Responsividade para modal de agendamento */
@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        max-width: 95%;
    }
    
    .modal-footer {
        flex-direction: column-reverse;
    }
    
    .modal-footer .btn {
        width: 100%;
    }
    
    #modalAgendarAula .modal-body > div[style*="grid"] {
        grid-template-columns: 1fr !important;
    }
    
    /* Modal com layout de duas colunas: empilhar em mobile */
    #modalAgendarAula .modal-body {
        flex-direction: column;
    }
    
    #modalAgendarAula .modal-body > div[style*="flex: 1"] {
        flex: none;
        min-width: 100%;
        border-left: none;
        border-top: 1px solid #dee2e6;
        padding-top: 20px;
        margin-top: 20px;
    }
}

/* Estilos para itens de estatística no modal */
.disciplina-stats-item-modal:hover {
    background-color: #f0f7ff !important;
    transform: translateX(2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
}

.disciplina-stats-item-modal {
    transition: all 0.2s ease;
}

/* Grid de alunos */
.alunos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.aluno-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 15px;
    background: white;
    transition: all 0.3s;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.aluno-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.aluno-info h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 16px;
    font-weight: 600;
}

.aluno-info p {
    margin: 5px 0;
    color: #666;
    font-size: 14px;
}

.exames-status {
    margin: 15px 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.exame-status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
}

.exame-status.apto {
    background: #d4edda;
    color: #155724;
}

.exame-status.inapto {
    background: #f8d7da;
    color: #721c24;
}

.aluno-actions {
    margin-top: 15px;
    text-align: right;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Responsividade */
@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        margin: 10px;
    }
    
    .alunos-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 15px;
    }
}

/* Alertas */
.alert {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

/* Melhorar visibilidade dos botões de ação */
.btn-group .btn-outline-primary {
    border-width: 2px !important;
    font-weight: 500 !important;
    min-width: 32px !important;
    height: 32px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: all 0.2s ease !important;
    background: white !important;
    border-color: #0d6efd !important;
    color: #0d6efd !important;
}

.btn-group .btn-outline-primary:hover {
    background-color: #0d6efd !important;
    border-color: #0d6efd !important;
    color: white !important;
    transform: scale(1.05) !important;
}

.btn-group .btn-outline-primary:hover span {
    color: white !important;
}

/* Melhorar contraste do botão de cancelar */
.btn-group .btn-outline-danger {
    border-width: 2px;
    font-weight: 500;
    min-width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-group .btn-outline-danger:hover {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
    transform: scale(1.05);
}

/* Adicionar tooltip melhorado */
.btn-group .btn[title] {
    position: relative;
}

.btn-group .btn[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: -35px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
    pointer-events: none;
}
</style>

<!-- Script simples para garantir visibilidade do ícone -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 [DEBUG] Página carregada, verificando botões de edição...');
    
    // Garantir que todos os botões de edição tenham ícone visível
    const editButtons = document.querySelectorAll('.btn-outline-primary');
    console.log('🔧 [DEBUG] Encontrados', editButtons.length, 'botões de edição');
    
    editButtons.forEach((button, index) => {
        const span = button.querySelector('span');
        if (span) {
            console.log('✅ [DEBUG] Botão', index + 1, 'tem ícone:', span.textContent);
        } else {
            console.log('❌ [DEBUG] Botão', index + 1, 'NÃO tem ícone');
        }
    });
});
</script>

<!-- JavaScript para Modal de Agendamento -->
<script>
// Variáveis globais para o modal
let turmaIdModal = <?= $turmaId ?>;
let dataInicioModal = '';
let dataFimModal = '';

// Função para normalizar disciplina (remover acentos) - escopo global
function normalizarDisciplinaJS(disciplina) {
    if (!disciplina) return '';
    
    console.log('🔧 [normalizarDisciplinaJS] Entrada:', disciplina);
    
    let normalizado = disciplina;
    
    // Primeiro, remover acentos e converter para minúsculas (independente do formato)
    normalizado = normalizado
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Remove acentos
        .replace(/ç/g, 'c') // Converte ç para c
        .replace(/ñ/g, 'n'); // Converte ñ para n
    
    console.log('🔧 [normalizarDisciplinaJS] Após remover acentos:', normalizado);
    
    // [FIX] FASE 2 - EDICAO DISCIPLINA TURMA 16: Se já tiver underscores, remover "de", "da", "do", "e" que estão entre underscores
    if (normalizado.includes('_')) {
        // Remover "_de_", "_da_", "_do_", "_e_", etc. e também no início/fim
        normalizado = normalizado
            .replace(/_(de|da|do|das|dos|e|a|o|as|os)_/gi, '_') // Remove palavras comuns entre underscores, incluindo 'e'
            .replace(/^(de|da|do|das|dos|e|a|o|as|os)_/gi, '') // Remove no início
            .replace(/_(de|da|do|das|dos|e|a|o|as|os)$/gi, '') // Remove no fim
            .replace(/_+/g, '_') // Remover underscores duplos
            .replace(/^_+|_+$/g, ''); // Remover underscores no início/fim
        console.log('🔧 [normalizarDisciplinaJS] Após remover palavras comuns (underscore):', normalizado);
        return normalizado;
    }
    
    // Se tiver espaços, remover palavras comuns primeiro
    if (normalizado.includes(' ')) {
        // Remover palavras comuns com word boundaries
        normalizado = normalizado.replace(/\s+(de|da|do|das|dos|e|a|o|as|os)(\s+|$)/gi, ' ');
        normalizado = normalizado.replace(/^(de|da|do|das|dos|e|a|o|as|os)\s+/gi, '');
        normalizado = normalizado.trim();
        console.log('🔧 [normalizarDisciplinaJS] Após remover palavras comuns (espaço):', normalizado);
    }
    
    // Converter espaços para underscores
    normalizado = normalizado
        .replace(/\s+/g, '_') // Converte espaços para underscore
        .replace(/_+/g, '_') // Remover underscores duplos
        .replace(/^_+|_+$/g, ''); // Remover underscores no início/fim
    
    console.log('🔧 [normalizarDisciplinaJS] Resultado final:', normalizado);
    return normalizado;
}

// Função para abrir o modal de agendamento
function abrirModalAgendarAula(disciplinaId, disciplinaNome, dataInicio, dataFim) {
    try {
        turmaIdModal = <?= $turmaId ?>;
        dataInicioModal = dataInicio;
        dataFimModal = dataFim;
        
        // ✅ Registrar disciplina aberta para manter accordion após salvar
        window.ultimaDisciplinaAberta = normalizarDisciplinaJS(disciplinaId);
        console.log('📌 Disciplina registrada para manter aberta:', window.ultimaDisciplinaAberta);
        
        // Preencher os campos do modal com verificação de existência
        const modalDisciplinaId = document.getElementById('modal_disciplina_id');
        const modalDisciplinaNome = document.getElementById('modal_disciplina_nome');
        const modalDataAula = document.getElementById('modal_data_aula');
        const modalInstrutorId = document.getElementById('modal_instrutor_id');
        const modalHoraInicio = document.getElementById('modal_hora_inicio');
        const modalQuantidadeAulas = document.getElementById('modal_quantidade_aulas');
        const previewHorario = document.getElementById('previewHorarioModal');
        const alertaConflitos = document.getElementById('alertaConflitosModal');
        const mensagemAgendamento = document.getElementById('mensagemAgendamento');
        const btnAgendar = document.getElementById('btnAgendarAula');
        const modal = document.getElementById('modalAgendarAula');
        const infoDisciplina = document.getElementById('infoDisciplinaModal');
        
        if (!modal) {
            console.error('Modal não encontrado!');
            alert('Erro: Modal de agendamento não encontrado. Recarregue a página.');
            return;
        }
        
        // IMPORTANTE: Esconder botão de excluir (só aparece em modo edição)
        const btnExcluirModal = document.getElementById('btn_excluir_modal');
        if (btnExcluirModal) {
            btnExcluirModal.style.display = 'none';
        }
        
        // Preencher campos se existirem (normalizar disciplina)
        if (modalDisciplinaId) modalDisciplinaId.value = normalizarDisciplinaJS(disciplinaId);
        if (modalDisciplinaNome) modalDisciplinaNome.value = disciplinaNome;
        if (modalDataAula) {
            // Definir limites de data baseado no período da turma
            modalDataAula.min = dataInicio;
            modalDataAula.max = dataFim;
            modalDataAula.value = '';
            
            // Log para debug
            console.log('🔧 [DEBUG] Limites do calendário configurados:', {
                min: dataInicio,
                max: dataFim,
                dataInicioFormatada: new Date(dataInicio + 'T00:00:00').toLocaleDateString('pt-BR'),
                dataFimFormatada: new Date(dataFim + 'T00:00:00').toLocaleDateString('pt-BR')
            });
            
            // Aviso se a data fim está muito próxima
            const hoje = new Date();
            const dataFimDate = new Date(dataFim + 'T00:00:00');
            const diasRestantes = Math.ceil((dataFimDate - hoje) / (1000 * 60 * 60 * 24));
            
            if (diasRestantes <= 7 && diasRestantes > 0) {
                console.warn('⚠️ [AVISO] Restam apenas', diasRestantes, 'dias no período da turma');
            }
        }
        
        // Configurar modo de criação
        const modalModo = document.getElementById('modal_modo');
        const modalAcao = document.getElementById('modal_acao');
        const modalAulaId = document.getElementById('modal_aula_id');
        const modalTitulo = document.getElementById('modal_titulo');
        const btnAgendarTexto = document.getElementById('btnAgendarTexto');
        const campoObservacoes = document.getElementById('campoObservacoesModal');
        
        if (modalModo) modalModo.value = 'criar';
        if (modalAcao) modalAcao.value = 'agendar_aula';
        if (modalAulaId) modalAulaId.value = '';
        if (modalTitulo) {
            modalTitulo.innerHTML = '<i class="fas fa-calendar-plus"></i> Agendar Nova Aula';
        }
        if (btnAgendarTexto) {
            btnAgendarTexto.textContent = 'Agendar Aula(s)';
        }
        if (campoObservacoes) {
            campoObservacoes.style.display = 'none';
        }
        
        // Limpar campos e mensagens
        if (modalInstrutorId) modalInstrutorId.value = '';
        if (modalHoraInicio) modalHoraInicio.value = '';
        if (modalQuantidadeAulas) modalQuantidadeAulas.value = '2';
        const modalObservacoes = document.getElementById('modal_observacoes');
        if (modalObservacoes) modalObservacoes.value = '';
        if (previewHorario) previewHorario.style.display = 'none';
        if (alertaConflitos) alertaConflitos.style.display = 'none';
        if (mensagemAgendamento) mensagemAgendamento.style.display = 'none';
        if (btnAgendar) btnAgendar.disabled = true;
        
        // Mostrar modal
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Buscar informações sobre a disciplina após um pequeno delay para garantir que o DOM está pronto
        // Tornar opcional - não bloquear o modal se falhar
        setTimeout(() => {
            // Tentar buscar informações, mas não bloquear se falhar
            if (disciplinaId && typeof buscarInfoDisciplina === 'function') {
                buscarInfoDisciplina(disciplinaId).catch(err => {
                    // Ignorar erros silenciosamente
                });
            }
            atualizarEstatisticasModal();
        }, 100);
        
        // Destacar a disciplina selecionada na sidebar
        destacarDisciplinaSelecionada(disciplinaId);
        
    } catch (error) {
        console.error('Erro ao abrir modal:', error);
        alert('Erro ao abrir modal de agendamento. Verifique o console para mais detalhes.');
    }
}

// Função para selecionar disciplina ao clicar na sidebar
function selecionarDisciplinaModal(disciplinaId, disciplinaNome) {
    // Atualizar campos do formulário
    const modalDisciplinaId = document.getElementById('modal_disciplina_id');
    const modalDisciplinaNome = document.getElementById('modal_disciplina_nome');
    
    // Normalizar disciplina antes de salvar no campo
    const disciplinaNormalizada = normalizarDisciplinaJS(disciplinaId);
    
    if (modalDisciplinaId) modalDisciplinaId.value = disciplinaNormalizada;
    if (modalDisciplinaNome) modalDisciplinaNome.value = disciplinaNome;
    
    // Destacar disciplina selecionada
    destacarDisciplinaSelecionada(disciplinaId);
    
    // Buscar informações atualizadas da disciplina (opcional - não bloquear se falhar)
    if (disciplinaId && typeof buscarInfoDisciplina === 'function') {
        buscarInfoDisciplina(disciplinaId).catch(err => {
            // Ignorar erros silenciosamente
        });
    }
}
// Função para destacar disciplina selecionada na sidebar
function destacarDisciplinaSelecionada(disciplinaId) {
    // Remover destaque de todas as disciplinas
    const items = document.querySelectorAll('.disciplina-stats-item-modal');
    items.forEach(item => {
        item.style.backgroundColor = 'white';
        item.style.boxShadow = 'none';
    });
    
    // Destacar disciplina selecionada
    const itemSelecionado = document.querySelector(`.disciplina-stats-item-modal[data-disciplina-id="${disciplinaId}"]`);
    if (itemSelecionado) {
        itemSelecionado.style.backgroundColor = '#e7f3ff';
        itemSelecionado.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
    }
}

// Função para atualizar estatísticas no modal
async function atualizarEstatisticasModal() {
    try {
        const turmaId = <?= $turmaId ?>;
        const basePath = getBasePath();
        const url = `${basePath}/admin/api/estatisticas-turma.php?turma_id=${turmaId}`;
        
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.disciplinas) {
            // Atualizar cada item de estatística no modal
            Object.keys(data.disciplinas).forEach(disciplinaId => {
                const stats = data.disciplinas[disciplinaId];
                atualizarItemEstatisticaModal(disciplinaId, stats);
            });
        }
    } catch (error) {
        console.error('❌ [DEBUG] Erro ao atualizar estatísticas do modal:', error);
    }
}
// Função para atualizar um item de estatística no modal
function atualizarItemEstatisticaModal(disciplinaId, stats) {
    const item = document.querySelector(`.disciplina-stats-item-modal[data-disciplina-id="${disciplinaId}"]`);
    if (!item) return;
    
    const percentual = stats.obrigatorias > 0 ? Math.round((stats.agendadas / stats.obrigatorias) * 100 * 10) / 10 : 0;
    
    // Atualizar cor baseada no progresso
    let corBarra = '#dc3545';
    if (percentual >= 100) {
        corBarra = '#28a745';
    } else if (percentual >= 75) {
        corBarra = '#ffc107';
    } else if (stats.agendadas > 0) {
        corBarra = '#17a2b8';
    }
    
    // Atualizar borda esquerda
    item.style.borderLeftColor = corBarra;
    
    // Atualizar círculo indicador
    const circulo = item.querySelector('div[style*="border-radius: 50%"]');
    if (circulo) {
        circulo.style.backgroundColor = corBarra;
    }
    
    // Atualizar valores usando estrutura específica do HTML
    // Buscar fração (agendadas/obrigatorias)
    const linhaFracao = item.querySelectorAll('div[style*="justify-content: space-between"]')[0];
    if (linhaFracao) {
        const spans = linhaFracao.querySelectorAll('span');
        // Primeiro span é a fração
        if (spans[0]) {
            spans[0].textContent = `${stats.agendadas}/${stats.obrigatorias}`;
        }
        // Segundo span é o percentual
        if (spans[1]) {
            spans[1].textContent = `${percentual}%`;
            spans[1].style.color = corBarra;
        }
    }
    
    // Atualizar linha de detalhes (Agendadas/Faltantes)
    const linhaDetalhes = item.querySelectorAll('div[style*="justify-content: space-between"]')[1];
    if (linhaDetalhes) {
        const spans = linhaDetalhes.querySelectorAll('span');
        spans.forEach(span => {
            const texto = span.textContent.trim();
            
            // Atualizar "Agendadas: X"
            if (texto.startsWith('Agendadas:')) {
                const strong = span.querySelector('strong');
                if (strong) {
                    strong.textContent = stats.agendadas;
                } else {
                    // Se não tiver strong, atualizar o próprio span
                    span.innerHTML = `Agendadas: <strong style="color: #023A8D;">${stats.agendadas}</strong>`;
                }
            }
            
            // Atualizar "Faltantes: X"
            if (texto.startsWith('Faltantes:')) {
                const strong = span.querySelector('strong');
                if (strong) {
                    strong.textContent = stats.faltantes;
                } else {
                    // Se não tiver strong, atualizar o próprio span
                    span.innerHTML = `Faltantes: <strong style="color: #dc3545;">${stats.faltantes}</strong>`;
                }
            }
        });
    }
}

// Função para buscar informações da disciplina
// Esta função é opcional - não bloqueia o modal se falhar
async function buscarInfoDisciplina(disciplinaId) {
    // Validar parâmetro
    if (!disciplinaId || disciplinaId === '' || disciplinaId === 'undefined' || disciplinaId === 'null') {
        // Retornar Promise resolvida para não quebrar chamadas com .catch()
        return Promise.resolve();
    }
    
    try {
        console.log('🔍 [INFO] Buscando informações da disciplina:', disciplinaId);
        
        const turmaId = <?= $turmaId ?>;
        const basePath = getBasePath();
        const url = `${basePath}/admin/api/info-disciplina-turma.php?turma_id=${turmaId}&disciplina=${encodeURIComponent(disciplinaId)}`;
        
        console.log('🔍 [INFO] URL da API:', url);
        
        const response = await fetch(url);
        console.log('🔍 [INFO] Status da resposta:', response.status);
        
        // Ler a resposta como texto primeiro para verificar se é JSON válido
        const textResponse = await response.text();
        
        // Se não for 200, tentar parsear JSON de erro, mas não quebrar a aplicação
        if (!response.ok) {
            console.warn('⚠️ [INFO] Erro ao buscar informações da disciplina. Status:', response.status);
            // Tentar parsear resposta de erro
            try {
                const errorData = JSON.parse(textResponse);
                console.warn('⚠️ [INFO] Mensagem de erro:', errorData.mensagem || errorData.message || 'Erro desconhecido');
            } catch (e) {
                console.warn('⚠️ [INFO] Resposta não é JSON válido:', textResponse.substring(0, 200));
            }
            // Não lançar erro, apenas retornar silenciosamente
            return Promise.resolve();
        }
        
        // Verificar se a resposta é realmente JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.warn('⚠️ [INFO] Content-Type não é JSON:', contentType);
            console.warn('⚠️ [INFO] Resposta recebida:', textResponse.substring(0, 500));
            // Não lançar erro, apenas retornar silenciosamente
            return Promise.resolve();
        }
        
        let data;
        try {
            data = JSON.parse(textResponse);
        } catch (parseError) {
            console.warn('⚠️ [INFO] Erro ao fazer parse do JSON, ignorando:', parseError);
            // Não quebrar a aplicação, apenas retornar silenciosamente
            return Promise.resolve();
        }
        
        if (data && data.sucesso && data.dados) {
            const info = data.dados;
            const infoDisciplina = document.getElementById('infoDisciplinaModal');
            const infoTotalObrigatorias = document.getElementById('infoTotalObrigatorias');
            const infoTotalAgendadas = document.getElementById('infoTotalAgendadas');
            const infoTotalFaltantes = document.getElementById('infoTotalFaltantes');
            
            if (infoDisciplina && infoTotalObrigatorias && infoTotalAgendadas && infoTotalFaltantes) {
                infoTotalObrigatorias.textContent = info.total_obrigatorias || 0;
                infoTotalAgendadas.textContent = info.total_agendadas || 0;
                infoTotalFaltantes.textContent = info.total_faltantes || 0;
                
                // Mostrar o card de informações com estilo inline para garantir visibilidade
                infoDisciplina.style.display = 'block';
                infoDisciplina.style.visibility = 'visible';
                infoDisciplina.style.opacity = '1';
            }
        } else {
            // Ocultar o card se houver erro ou resposta inválida
            const infoDisciplina = document.getElementById('infoDisciplinaModal');
            if (infoDisciplina) {
                infoDisciplina.style.display = 'none';
            }
        }
        
        // Retornar Promise resolvida para permitir .catch() nas chamadas
        return Promise.resolve();
    } catch (error) {
        // Não quebrar a aplicação, apenas logar o erro silenciosamente
        console.warn('⚠️ [INFO] Erro ao buscar informações da disciplina (ignorado):', error.message);
        // Ocultar o card se houver erro
        const infoDisciplina = document.getElementById('infoDisciplinaModal');
        if (infoDisciplina) {
            infoDisciplina.style.display = 'none';
        }
        // Retornar Promise resolvida mesmo em caso de erro
        return Promise.resolve();
    }
}

// Função para fechar o modal
function fecharModalAgendarAula() {
    const modal = document.getElementById('modalAgendarAula');
    if (modal) {
        modal.style.display = 'none';
    }
    
    // Esconder botão de excluir ao fechar
    const btnExcluirModal = document.getElementById('btn_excluir_modal');
    if (btnExcluirModal) {
        btnExcluirModal.style.display = 'none';
    }
    
    // Resetar modo para 'criar'
    const modalModo = document.getElementById('modal_modo');
    if (modalModo) {
        modalModo.value = 'criar';
    }
    
    document.body.style.overflow = '';
}

// Fechar modal ao clicar fora dele
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modalAgendarAula');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalAgendarAula();
            }
        });
    }
});

// Atualizar preview quando campos mudarem
let previewUpdateTimeout = null;
function atualizarPreviewModal() {
    // Proteção contra chamadas excessivas - debounce
    if (previewUpdateTimeout) {
        clearTimeout(previewUpdateTimeout);
    }
    
    previewUpdateTimeout = setTimeout(() => {
        try {
            // CRÍTICO: Ler valores diretamente dos campos do formulário, sem transformações
            const disciplinaNomeEl = document.getElementById('modal_disciplina_nome');
            const dataAulaEl = document.getElementById('modal_data_aula');
            const horaInicioEl = document.getElementById('modal_hora_inicio');
            const qtdAulasEl = document.getElementById('modal_quantidade_aulas');
            
            // Verificar se os elementos existem
            if (!disciplinaNomeEl || !dataAulaEl || !horaInicioEl || !qtdAulasEl) {
                return;
            }
            
            // Obter valores exatos dos campos
            const disciplinaNome = disciplinaNomeEl.value || '';
            const dataAula = dataAulaEl.value || '';
            const horaInicio = horaInicioEl.value || '';
            const qtdAulas = parseInt(qtdAulasEl.value) || 1;
            
            // Validar se todos os campos necessários estão preenchidos
            if (dataAula && horaInicio && qtdAulas > 0) {
                // Formatar data exatamente como está no campo (formato brasileiro)
                let dataFormatada = dataAula; // Valor padrão
                const dataObj = new Date(dataAula + 'T00:00:00');
                if (!isNaN(dataObj.getTime())) {
                    // Se data válida, formatar para brasileiro
                    dataFormatada = dataObj.toLocaleDateString('pt-BR');
                }
                
                // Calcular horários das aulas baseado EXATAMENTE no horário de início e quantidade informados
                let horariosPreview = [];
                for (let i = 0; i < qtdAulas; i++) {
                    const [horas, minutos] = horaInicio.split(':').map(Number);
                    if (isNaN(horas) || isNaN(minutos)) {
                        continue; // Pular se horário inválido
                    }
                    
                    const inicioMinutos = (horas * 60) + minutos + (i * 50);
                    const fimMinutos = inicioMinutos + 50;
                    
                    const horaInicioAula = String(Math.floor(inicioMinutos / 60)).padStart(2, '0') + ':' + 
                                         String(inicioMinutos % 60).padStart(2, '0');
                    const horaFimAula = String(Math.floor(fimMinutos / 60)).padStart(2, '0') + ':' + 
                                       String(fimMinutos % 60).padStart(2, '0');
                    
                    horariosPreview.push(`${horaInicioAula} - ${horaFimAula}`);
                }
                
                // Atualizar preview com valores EXATOS dos campos
                const previewContent = document.getElementById('previewContentModal');
                const previewDiv = document.getElementById('previewHorarioModal');
                
                if (previewContent && previewDiv) {
                    previewContent.innerHTML = `
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            <div style="font-weight: 600; margin-bottom: 4px;">${disciplinaNome || 'Disciplina não selecionada'}</div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-calendar-day" style="color: #6c757d; width: 16px;"></i>
                                <span>${dataFormatada}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-clock" style="color: #6c757d; width: 16px;"></i>
                                <span>${horariosPreview.length > 0 ? horariosPreview.join(', ') : horaInicio}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-graduation-cap" style="color: #6c757d; width: 16px;"></i>
                                <span>${qtdAulas} aula(s) de 50 minutos cada</span>
                            </div>
                        </div>
                    `;
                    previewDiv.style.display = 'block';
                    
                    // Scroll suave até o preview após atualizar
                    setTimeout(() => {
                        previewDiv.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'nearest',
                            inline: 'nearest'
                        });
                    }, 150);
                }
            } else {
                // Ocultar preview se campos não estiverem completos
                const previewDiv = document.getElementById('previewHorarioModal');
                if (previewDiv) {
                    previewDiv.style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Erro ao atualizar preview:', error);
        }
    }, 100); // Debounce de 100ms
}

// Event listeners para atualizar preview
document.addEventListener('DOMContentLoaded', function() {
    const dataAula = document.getElementById('modal_data_aula');
    const horaInicio = document.getElementById('modal_hora_inicio');
    const qtdAulas = document.getElementById('modal_quantidade_aulas');
    const instrutorId = document.getElementById('modal_instrutor_id');
    
    if (dataAula) {
        dataAula.addEventListener('change', function() {
            atualizarPreviewModal();
            verificarDisponibilidadeAuto();
        });
    }
    
    if (horaInicio) {
        horaInicio.addEventListener('change', function() {
            atualizarPreviewModal();
            verificarDisponibilidadeAuto();
        });
    }
    
    if (qtdAulas) {
        qtdAulas.addEventListener('change', function() {
            atualizarPreviewModal();
            verificarDisponibilidadeAuto();
        });
    }
    
    // Também atualizar quando disciplina mudar
    const disciplinaNome = document.getElementById('modal_disciplina_nome');
    if (disciplinaNome) {
        disciplinaNome.addEventListener('change', function() {
            atualizarPreviewModal();
        });
    }
    
    if (instrutorId) {
        instrutorId.addEventListener('change', function() {
            verificarDisponibilidadeAuto();
        });
    }
});

// Verificação automática de disponibilidade (sem mostrar mensagem, apenas habilitar/desabilitar botão)
let timeoutVerificacao = null;
function verificarDisponibilidadeAuto() {
    // Cancelar verificação anterior se ainda estiver aguardando
    if (timeoutVerificacao) {
        clearTimeout(timeoutVerificacao);
    }
    
    // Aguardar 500ms após o usuário parar de digitar
    timeoutVerificacao = setTimeout(() => {
        const instrutor = document.getElementById('modal_instrutor_id')?.value;
        const dataAula = document.getElementById('modal_data_aula')?.value;
        const horaInicio = document.getElementById('modal_hora_inicio')?.value;
        const disciplinaId = document.getElementById('modal_disciplina_id')?.value;
        const quantidadeAulas = document.getElementById('modal_quantidade_aulas')?.value || 1;
        
        const btnAgendar = document.getElementById('btnAgendarAula');
        
        // Se todos os campos obrigatórios estão preenchidos, verificar
        if (instrutor && dataAula && horaInicio && disciplinaId) {
            const params = new URLSearchParams({
                acao: 'verificar_conflitos',
                turma_id: turmaIdModal,
                disciplina: disciplinaId,
                instrutor_id: instrutor,
                data_aula: dataAula,
                hora_inicio: horaInicio,
                quantidade_aulas: quantidadeAulas
            });
            
            fetch(getBasePath() + '/admin/api/turmas-teoricas.php?' + params.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (btnAgendar) {
                    if (data.sucesso && data.disponivel) {
                        btnAgendar.disabled = false;
                    } else {
                        btnAgendar.disabled = true;
                    }
                }
            })
            .catch(error => {
                console.error('Erro na verificação automática:', error);
                if (btnAgendar) btnAgendar.disabled = true;
            });
        } else {
            // Se campos não estão completos, desabilitar botão
            if (btnAgendar) btnAgendar.disabled = true;
        }
    }, 500);
}
// Função para verificar disponibilidade
function verificarDisponibilidadeModal() {
    const instrutor = document.getElementById('modal_instrutor_id').value;
    const dataAula = document.getElementById('modal_data_aula').value;
    const horaInicio = document.getElementById('modal_hora_inicio').value;
    let disciplinaId = document.getElementById('modal_disciplina_id').value;
    const quantidadeAulas = document.getElementById('modal_quantidade_aulas').value || 1;
    
    console.log('🔍 [DEBUG FRONTEND] Verificando disponibilidade - Valores iniciais:', {
        instrutor,
        dataAula,
        horaInicio,
        disciplinaId_original: disciplinaId,
        disciplinaId_tipo: typeof disciplinaId,
        disciplinaId_length: disciplinaId ? disciplinaId.length : 0,
        quantidadeAulas
    });
    
    // Normalizar disciplina antes de enviar
    const disciplinaOriginal = disciplinaId;
    if (disciplinaId) {
        console.log('🔍 [DEBUG FRONTEND] ANTES da normalização:', {
            valor: disciplinaId,
            tem_acentos: /[àáâãäèéêëìíîïòóôõöùúûüçñ]/i.test(disciplinaId),
            tem_espacos: disciplinaId.includes(' '),
            tem_underscores: disciplinaId.includes('_'),
            tem_de: disciplinaId.toLowerCase().includes('de')
        });
        
        disciplinaId = normalizarDisciplinaJS(disciplinaId);
        
        console.log('🔍 [DEBUG FRONTEND] DEPOIS da normalização:', {
            valor_original: disciplinaOriginal,
            valor_normalizado: disciplinaId,
            tem_acentos: /[àáâãäèéêëìíîïòóôõöùúûüçñ]/i.test(disciplinaId),
            tem_espacos: disciplinaId.includes(' '),
            tem_underscores: disciplinaId.includes('_'),
            tem_de: disciplinaId.includes('de')
        });
    }
    
    if (!instrutor || !dataAula || !horaInicio || !disciplinaId) {
        console.error('❌ [DEBUG FRONTEND] Campos obrigatórios faltando:', {
            instrutor: !!instrutor,
            dataAula: !!dataAula,
            horaInicio: !!horaInicio,
            disciplinaId: !!disciplinaId
        });
        mostrarMensagemModal('❌ Preencha todos os campos obrigatórios antes de verificar conflitos.', 'error');
        return;
    }
    
    const btnVerificar = document.getElementById('btnVerificarDisponibilidade');
    const btnAgendar = document.getElementById('btnAgendarAula');
    const alertaConflitos = document.getElementById('alertaConflitosModal');
    
    btnVerificar.disabled = true;
    btnVerificar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
    btnAgendar.disabled = true;
    
    // Limpar mensagens anteriores
    if (alertaConflitos) alertaConflitos.style.display = 'none';
    mostrarMensagemModal('', '');
    
    // Obter aula_id se estiver editando
    const modalAulaId = document.getElementById('modal_aula_id');
    const aulaId = modalAulaId ? modalAulaId.value : null;
    
    // Construir URL com parâmetros
    const params = new URLSearchParams({
        acao: 'verificar_conflitos',
        turma_id: turmaIdModal,
        disciplina: disciplinaId,
        instrutor_id: instrutor,
        data_aula: dataAula,
        hora_inicio: horaInicio,
        quantidade_aulas: quantidadeAulas
    });
    
    // Adicionar aula_id se estiver editando (para buscar disciplina da aula existente se necessário)
    if (aulaId) {
        params.set('aula_id', aulaId);
        console.log('🔍 [DEBUG FRONTEND] Modo edição detectado - aula_id:', aulaId);
    }
    
    console.log('🔍 [DEBUG FRONTEND] Parâmetros finais enviados:', {
        url: params.toString(),
        turma_id: turmaIdModal,
        disciplina: disciplinaId,
        instrutor_id: instrutor,
        data_aula: dataAula,
        hora_inicio: horaInicio,
        quantidade_aulas: quantidadeAulas,
        aula_id: aulaId || null
    });
    
    // Chamar API real de verificação
    fetch(getBasePath() + '/admin/api/turmas-teoricas.php?' + params.toString(), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('🔧 [DEBUG] Status da resposta:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('🔧 [DEBUG] Resultado completo da verificação:', data);
        
        // Se houver informações de debug, logar detalhadamente
        if (data.debug_info) {
            console.error('🔍 [DEBUG FRONTEND] Informações de Debug Detalhadas:', {
                disciplina_original: data.debug_info.disciplina_original,
                disciplina_normalizada: data.debug_info.disciplina_normalizada,
                curso_tipo: data.debug_info.curso_tipo,
                turma_id: data.debug_info.turma_id,
                total_disciplinas: data.debug_info.total_disciplinas_configuradas,
                busca_case_insensitive: data.debug_info.busca_case_insensitive
            });
            console.error('🔍 [DEBUG FRONTEND] Todas as disciplinas configuradas:', JSON.stringify(data.debug_info.disciplinas_configuradas, null, 2));
            console.error('🔍 [DEBUG FRONTEND] Nomes das disciplinas no banco:', 
                JSON.stringify(data.debug_info.disciplinas_configuradas.map(d => ({ 
                    disciplina: d.disciplina, 
                    nome: d.nome_disciplina 
                })), null, 2)
            );
            console.error('🔍 [DEBUG FRONTEND] Comparação:', JSON.stringify({
                'Buscando': data.debug_info.disciplina_normalizada,
                'Disciplinas no banco': data.debug_info.disciplinas_configuradas.map(d => d.disciplina),
                'Match exato?': data.debug_info.disciplinas_configuradas.some(d => d.disciplina === data.debug_info.disciplina_normalizada),
                'Match case-insensitive?': data.debug_info.disciplinas_configuradas.some(d => d.disciplina.toLowerCase() === data.debug_info.disciplina_normalizada.toLowerCase())
            }, null, 2));
            
            // Mostrar diferença de caracteres
            const buscando = data.debug_info.disciplina_normalizada;
            const noBanco = data.debug_info.disciplinas_configuradas.map(d => d.disciplina);
            console.error('🔍 [DEBUG FRONTEND] Análise detalhada:', {
                'Buscando (chars)': buscando.split(''),
                'Buscando (length)': buscando.length,
                'Primeira disciplina no banco': noBanco[0] || null,
                'Primeira disciplina (chars)': noBanco[0] ? noBanco[0].split('') : null,
                'Primeira disciplina (length)': noBanco[0] ? noBanco[0].length : null,
                'São iguais?': buscando === noBanco[0]
            });
        }
        
        btnVerificar.disabled = false;
        btnVerificar.innerHTML = '<i class="fas fa-search"></i> Verificar Disponibilidade';
        
        if (data.sucesso && data.disponivel) {
            // Horário disponível
            if (alertaConflitos) alertaConflitos.style.display = 'none';
            btnAgendar.disabled = false;
            // Remover ícones duplicados da mensagem (se já tiver ✅, não adicionar outro)
            let mensagem = data.mensagem || 'Horário disponível! Você pode agendar as aulas.';
            if (!mensagem.startsWith('✅') && !mensagem.startsWith('✓')) {
                mensagem = '✅ ' + mensagem;
            }
            mostrarMensagemModal(mensagem, 'success');
            
            // Scroll até a mensagem de sucesso
            setTimeout(() => {
                const mensagemEl = document.getElementById('mensagemAgendamento');
                if (mensagemEl && mensagemEl.style.display !== 'none') {
                    mensagemEl.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'nearest' 
                    });
                }
            }, 150);
        } else {
            // Há conflitos
            if (alertaConflitos) {
                let mensagemConflitos = '<strong>⚠️ Conflitos detectados:</strong><ul style="margin: 10px 0; padding-left: 20px;">';
                
                if (data.detalhes && data.detalhes.length > 0) {
                    data.detalhes.forEach(conflito => {
                        mensagemConflitos += '<li>' + conflito + '</li>';
                    });
                } else if (data.conflitos && data.conflitos.length > 0) {
                    data.conflitos.forEach(conflito => {
                        mensagemConflitos += '<li>' + conflito.mensagem + '</li>';
                    });
                } else {
                    mensagemConflitos += '<li>' + (data.mensagem || 'Conflito detectado') + '</li>';
                }
                
                mensagemConflitos += '</ul>';
                alertaConflitos.innerHTML = mensagemConflitos;
                alertaConflitos.style.display = 'block';
                
                // Scroll até o alerta de conflitos
                setTimeout(() => {
                    alertaConflitos.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'nearest' 
                    });
                }, 150);
            }
            
            btnAgendar.disabled = true;
            mostrarMensagemModal('❌ ' + (data.mensagem || 'Conflito de horário detectado. Verifique os detalhes acima.'), 'error');
        }
    })
    .catch(error => {
        console.error('❌ [DEBUG] Erro ao verificar disponibilidade:', error);
        btnVerificar.disabled = false;
        btnVerificar.innerHTML = '<i class="fas fa-search"></i> Verificar Disponibilidade';
        btnAgendar.disabled = true;
        mostrarMensagemModal('❌ Erro ao verificar disponibilidade. Tente novamente.', 'error');
        
        // Scroll até a mensagem de erro
        setTimeout(() => {
            const mensagemEl = document.getElementById('mensagemAgendamento');
            if (mensagemEl && mensagemEl.style.display !== 'none') {
                mensagemEl.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'nearest' 
                });
            }
        }, 150);
    });
}
// Função para enviar agendamento (criação ou edição)
function enviarAgendamentoModal() {
    const form = document.getElementById('formAgendarAulaModal');
    if (!form) {
        console.error('Formulário não encontrado!');
        return;
    }
    
    const formData = new FormData(form);
    
    // Detectar se é criação ou edição
    const modalModo = document.getElementById('modal_modo');
    const modalAcao = document.getElementById('modal_acao');
    const modalAulaId = document.getElementById('modal_aula_id');
    const isEdicao = modalModo && modalModo.value === 'editar';
    const aulaId = modalAulaId ? modalAulaId.value : null;
    
    // Ajustar ação baseado no modo
    if (isEdicao && aulaId) {
        formData.set('acao', 'editar_aula');
        formData.set('aula_id', aulaId);
    } else {
        formData.set('acao', 'agendar_aula');
    }
    
    // Validar campos
    if (!formData.get('instrutor_id') || !formData.get('data_aula') || !formData.get('hora_inicio')) {
        mostrarMensagemModal('❌ Preencha todos os campos obrigatórios.', 'error');
        return;
    }
    
    // Verificar disponibilidade antes de agendar (última verificação) - apenas para criação
    const instrutor = formData.get('instrutor_id');
    const dataAula = formData.get('data_aula');
    const horaInicio = formData.get('hora_inicio');
    let disciplinaId = formData.get('disciplina');
    
    // Normalizar disciplina antes de enviar
    if (disciplinaId) {
        disciplinaId = normalizarDisciplinaJS(disciplinaId);
        formData.set('disciplina', disciplinaId); // Atualizar no formData
    }
    
    const quantidadeAulas = formData.get('quantidade_aulas') || 1;
    
    const btnAgendar = document.getElementById('btnAgendarAula');
    const btnAgendarTexto = document.getElementById('btnAgendarTexto');
    btnAgendar.disabled = true;
    
    // Para edição, enviar diretamente sem verificar conflitos
    if (isEdicao) {
        btnAgendar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
        enviarDadosAgendamento(formData, btnAgendar, btnAgendarTexto, isEdicao);
        return;
    }
    
    // Para criação, verificar conflitos primeiro
    btnAgendar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
    
    // Primeiro verificar conflitos antes de agendar
    const params = new URLSearchParams({
        acao: 'verificar_conflitos',
        turma_id: turmaIdModal,
        disciplina: disciplinaId,
        instrutor_id: instrutor,
        data_aula: dataAula,
        hora_inicio: horaInicio,
        quantidade_aulas: quantidadeAulas
    });
    
    // Verificar disponibilidade antes de agendar
    fetch(getBasePath() + '/admin/api/turmas-teoricas.php?' + params.toString(), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(verificacao => {
        console.log('🔧 [DEBUG] Verificação antes de agendar:', verificacao);
        
        if (!verificacao.sucesso || !verificacao.disponivel) {
            // Há conflitos, não permitir agendamento
            btnAgendar.disabled = true;
            btnAgendar.innerHTML = '<i class="fas fa-plus"></i> Agendar Aula(s)';
            
            const alertaConflitos = document.getElementById('alertaConflitosModal');
            if (alertaConflitos) {
                let mensagemConflitos = '<strong>⚠️ Conflitos detectados:</strong><ul style="margin: 10px 0; padding-left: 20px;">';
                
                if (verificacao.detalhes && verificacao.detalhes.length > 0) {
                    verificacao.detalhes.forEach(conflito => {
                        mensagemConflitos += '<li>' + conflito + '</li>';
                    });
                } else if (verificacao.conflitos && verificacao.conflitos.length > 0) {
                    verificacao.conflitos.forEach(conflito => {
                        mensagemConflitos += '<li>' + conflito.mensagem + '</li>';
                    });
                } else {
                    mensagemConflitos += '<li>' + (verificacao.mensagem || 'Conflito detectado') + '</li>';
                }
                
                mensagemConflitos += '</ul>';
                alertaConflitos.innerHTML = mensagemConflitos;
                alertaConflitos.style.display = 'block';
            }
            
            mostrarMensagemModal('❌ ' + (verificacao.mensagem || 'Não é possível agendar devido a conflitos de horário. Verifique os detalhes acima.'), 'error');
            return;
        }
        
        // Se passou na verificação, proceder com o agendamento
        enviarDadosAgendamento(formData, btnAgendar, btnAgendarTexto, isEdicao);
    })
    .catch(error => {
        console.error('Erro ao verificar disponibilidade:', error);
        btnAgendar.disabled = false;
        if (btnAgendarTexto) {
            btnAgendar.innerHTML = '<i class="fas fa-plus"></i> ' + btnAgendarTexto.textContent;
        } else {
            btnAgendar.innerHTML = '<i class="fas fa-plus"></i> Agendar Aula(s)';
        }
        mostrarMensagemModal('❌ Erro ao verificar disponibilidade. Tente novamente.', 'error');
    });
}

// Função auxiliar para enviar dados do agendamento
function enviarDadosAgendamento(formData, btnAgendar, btnAgendarTexto, isEdicao) {
    const modalModo = document.getElementById('modal_modo');
    const isEdicaoConfirmada = modalModo && modalModo.value === 'editar';
    
    if (isEdicaoConfirmada) {
        btnAgendar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    } else {
        btnAgendar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agendando...';
    }
    
    // Enviar via AJAX
    fetch(getBasePath() + '/admin/api/turmas-teoricas.php', {
        method: 'POST',
        body: new URLSearchParams(Object.fromEntries(formData)),
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('🔧 [DEBUG] Status da resposta:', response.status);
        return response.json();
    })
    .then(async data => {
        console.log('🔧 [DEBUG] Dados recebidos do servidor:', data);
        
        if (data.sucesso) {
            // Remover ícones duplicados da mensagem (se já tiver ✅, não adicionar outro)
            let mensagem = data.mensagem || 'Operação realizada com sucesso!';
            if (!mensagem.startsWith('✅') && !mensagem.startsWith('✓')) {
                mensagem = '✅ ' + mensagem;
            }
            mostrarMensagemModal(mensagem, 'success');
            
            const aulasAgendadasResposta = Array.isArray(data.aulas_agendadas)
                ? data.aulas_agendadas.map(id => String(id))
                : [];
            const quantidadeAulasForm = parseInt(formData.get('quantidade_aulas') || '1', 10) || 1;
            const metaScroll = {
                aulaIds: aulasAgendadasResposta,
                data: formData.get('data_aula'),
                horaInicio: formData.get('hora_inicio'),
                quantidadeAulas: quantidadeAulasForm
            };

            if (!metaScroll.aulaIds.length) {
                const aulaIdForm = formData.get('aula_id') || formData.get('modal_aula_id') || formData.get('id_aula');
                if (aulaIdForm) {
                    metaScroll.aulaIds = [String(aulaIdForm)];
                }
            }

            window.__calendarioScrollMeta = metaScroll;
            
            const disciplinaId = formData.get('disciplina');
            
            console.log('📋 [DEBUG] Pós-salvamento - disciplinaId:', disciplinaId, 'ultimaDisciplinaAberta:', window.ultimaDisciplinaAberta);
            
            // ✅ Atualizar apenas a disciplina específica (sem reload)
            if (disciplinaId && window.ultimaDisciplinaAberta) {
                console.log('🔄 Atualizando disciplina após salvamento:', disciplinaId);
                
                // Atualizar dados da disciplina sem recarregar página
                setTimeout(() => {
                    atualizarDisciplinaAposAgendamento(disciplinaId)
                        .then(() => {
                            console.log('✅ Disciplina atualizada com sucesso - accordion permanece aberto');
                        })
                        .catch(error => {
                            console.error('❌ Erro ao atualizar disciplina:', error);
                            alert('Erro ao atualizar dados. A página será recarregada.');
                            // Em caso de erro, fazer reload interceptado
                            window.location.reload();
                        });
                }, 300);
                
                // Atualizar calendário se a função existir
                if (typeof recarregarCalendario === 'function') {
                    recarregarCalendario({ scrollMeta: window.__calendarioScrollMeta, remeasure: true });
                }
            } else {
                // Fallback: usar interceptação de reload
                console.warn('⚠️ Nenhuma disciplina registrada - fallback para reload');
                console.log('   disciplinaId:', disciplinaId);
                console.log('   window.ultimaDisciplinaAberta:', window.ultimaDisciplinaAberta);
                window.location.reload();
            }
            
            // Fechar modal e resetar botão (aguardar um pouco mais)
            setTimeout(() => {
                fecharModalAgendarAula();
            }, isEdicaoConfirmada ? 1200 : 2000);
            
            btnAgendar.disabled = false;
            if (btnAgendarTexto) {
                btnAgendar.innerHTML = '<i class="fas fa-plus"></i> ' + btnAgendarTexto.textContent;
            } else {
                btnAgendar.innerHTML = '<i class="fas fa-plus"></i> Agendar Aula(s)';
            }
        } else {
            mostrarMensagemModal('❌ ' + (data.mensagem || 'Erro ao ' + (isEdicaoConfirmada ? 'salvar' : 'agendar') + ' aula. Tente novamente.'), 'error');
            btnAgendar.disabled = false;
            if (btnAgendarTexto) {
                btnAgendar.innerHTML = '<i class="fas fa-plus"></i> ' + btnAgendarTexto.textContent;
            } else {
                btnAgendar.innerHTML = '<i class="fas fa-plus"></i> Agendar Aula(s)';
            }
        }
    })
    .catch(error => {
        console.error('❌ [DEBUG] Erro ao enviar agendamento:', error);
        mostrarMensagemModal('❌ Erro ao processar agendamento. Tente novamente.', 'error');
        btnAgendar.disabled = false;
        if (btnAgendarTexto) {
            btnAgendar.innerHTML = '<i class="fas fa-plus"></i> ' + btnAgendarTexto.textContent;
        } else {
            btnAgendar.innerHTML = '<i class="fas fa-plus"></i> Agendar Aula(s)';
        }
    });
}
// Inserir novas linhas na tabela da disciplina sem recarregar
function inserirAgendamentosNaTabela(disciplinaId, agendamentos) {
    console.log('🔧 [DEBUG] Inserindo agendamentos na tabela:', { disciplinaId, agendamentos });
    
    // Tentar encontrar o container da disciplina de múltiplas formas
    let container = document.getElementById('detalhes-disciplina-' + disciplinaId);
    if (!container) {
        container = document.getElementById('data-disciplina-' + disciplinaId);
    }
    if (!container) {
        console.error('❌ [DEBUG] Container da disciplina não encontrado:', disciplinaId);
        // Como fallback, recarregar do servidor
        recarregarAgendamentosDisciplina(disciplinaId);
        return;
    }
    
    let tbody = container.querySelector('table tbody');
    
    // Se não houver tbody, pode ser que não haja agendamentos ainda
    if (!tbody) {
        // Verificar se há uma seção de "nenhum agendamento" e substituir
        const historicoSection = container.querySelector('.historico-agendamentos');
        if (historicoSection) {
            // Criar estrutura da tabela
            const tableHTML = `
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th>Aula</th>
                                <th>Data</th>
                                <th>Horário</th>
                                <th>Instrutor</th>
                                <th>Sala</th>
                                <th>Duração</th>
                                <th>Status</th>
                                <th width="100">Ações</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            `;
            // Substituir a seção vazia pela tabela
            const emptySection = historicoSection.querySelector('.text-center');
            if (emptySection) {
                emptySection.remove();
            }
            historicoSection.insertAdjacentHTML('beforeend', tableHTML);
            tbody = historicoSection.querySelector('table tbody');
        } else {
            console.error('❌ [DEBUG] Não foi possível encontrar ou criar tbody');
            recarregarAgendamentosDisciplina(disciplinaId);
            return;
        }
    }
    
    if (!tbody) {
        console.error('❌ [DEBUG] Tbody ainda não encontrado após tentativas');
        recarregarAgendamentosDisciplina(disciplinaId);
        return;
    }
    
    // Verificar agendamentos existentes para evitar duplicatas
    const agendamentosExistentes = Array.from(tbody.querySelectorAll('tr')).map(tr => 
        tr.getAttribute('data-agendamento-id')
    );
    
    // Filtrar apenas agendamentos que ainda não existem
    const novosAgendamentos = agendamentos.filter(aula => 
        !agendamentosExistentes.includes(String(aula.id))
    );
    
    console.log('🔧 [DEBUG] Agendamentos novos a inserir:', novosAgendamentos.length);
    
    if (novosAgendamentos.length === 0) {
        console.log('⚠️ [DEBUG] Todos os agendamentos já existem na tabela, recarregando do servidor para garantir ordem correta');
        recarregarAgendamentosDisciplina(disciplinaId);
        return;
    }
    
    // Ordenar agendamentos para inserir na ordem correta (ordem_disciplina ASC, data_aula ASC, hora_inicio ASC)
    novosAgendamentos.sort((a, b) => {
        // Ordenar por ordem_disciplina
        const ordemA = a.ordem_disciplina || 0;
        const ordemB = b.ordem_disciplina || 0;
        if (ordemA !== ordemB) return ordemA - ordemB;
        
        // Se ordem igual, ordenar por data_aula
        const dataA = new Date(a.data_aula + 'T00:00:00').getTime();
        const dataB = new Date(b.data_aula + 'T00:00:00').getTime();
        if (dataA !== dataB) return dataA - dataB;
        
        // Se data igual, ordenar por hora_inicio
        const horaA = a.hora_inicio || '00:00:00';
        const horaB = b.hora_inicio || '00:00:00';
        return horaA.localeCompare(horaB);
    });
    
    // Inserir cada agendamento na posição correta
    novosAgendamentos.forEach(aula => {
        // Verificar se já existe na tabela antes de inserir
        if (tbody.querySelector(`tr[data-agendamento-id="${aula.id}"]`)) {
            console.log('⚠️ [DEBUG] Agendamento já existe, pulando:', aula.id);
            return;
        }
        
        const tr = document.createElement('tr');
        tr.setAttribute('data-agendamento-id', aula.id);
        
        // Formatar data
        const dataBR = aula.data_aula ? new Date(aula.data_aula + 'T00:00:00').toLocaleDateString('pt-BR') : 'N/A';
        
        // Formatar horário
        const horaInicio = aula.hora_inicio ? (aula.hora_inicio.length >= 5 ? aula.hora_inicio.substring(0, 5) : aula.hora_inicio) : '--:--';
        const horaFim = aula.hora_fim ? (aula.hora_fim.length >= 5 ? aula.hora_fim.substring(0, 5) : aula.hora_fim) : '--:--';
        
        tr.innerHTML = `
            <td><strong>${aula.nome_aula || 'Aula'}</strong></td>
            <td>${dataBR}</td>
            <td>${horaInicio} - ${horaFim}</td>
            <td>${aula.instrutor_nome || 'Não informado'}</td>
            <td>${aula.sala_nome || 'Não informada'}</td>
            <td>${aula.duracao_minutos || 50} min</td>
            <td><span class="badge bg-warning">Agendada</span></td>
            <td>
                <div class="btn-group" role="group">
                    <button type="button" 
                            class="btn btn-sm action-btn-tonal" 
                            onclick="editarAgendamento(${aula.id}, '${(aula.nome_aula || '').replace(/'/g, "\\'")}', '${aula.data_aula}', '${horaInicio}', '${horaFim}', '${aula.instrutor_id || ''}', '${aula.sala_id || ''}', '${aula.duracao_minutos || 50}', '${(aula.observacoes || '').replace(/'/g, "\\'")}')"
                            title="Editar agendamento">
                        <span style="font-size: 14px; font-weight: bold;">✏</span>
                    </button>
                    <button type="button" 
                            class="btn btn-sm action-btn-outline-danger" 
                            onclick="cancelarAgendamento(${aula.id}, '${(aula.nome_aula || '').replace(/'/g, "\\'")}')"
                            title="Cancelar agendamento">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </td>
        `;
        
        // Encontrar posição correta para inserir (ordem crescente: ordem_disciplina, data_aula, hora_inicio)
        const rows = Array.from(tbody.querySelectorAll('tr'));
        let posicaoInsercao = null;
        
        const ordemAula = aula.ordem_disciplina || 0;
        const dataAula = new Date(aula.data_aula + 'T00:00:00').getTime();
        const horaAula = aula.hora_inicio || '00:00:00';
        
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const ordemRow = parseInt(row.querySelector('td')?.dataset?.ordem || row.getAttribute('data-ordem') || '0');
            const dataRowStr = row.querySelector('td:nth-child(2)')?.textContent;
            const horaRowStr = row.querySelector('td:nth-child(3)')?.textContent?.split(' - ')[0] || '00:00';
            
            // Se não conseguir obter dados da linha existente, usar atributos data
            const dataRow = dataRowStr ? new Date(dataRowStr.split('/').reverse().join('-') + 'T00:00:00').getTime() : 0;
            
            // Comparar
            if (ordemAula < ordemRow || 
                (ordemAula === ordemRow && dataAula < dataRow) ||
                (ordemAula === ordemRow && dataAula === dataRow && horaAula < horaRowStr)) {
                posicaoInsercao = row;
                break;
            }
        }
        
        // Inserir na posição correta ou no final se não encontrou posição
        if (posicaoInsercao) {
            tbody.insertBefore(tr, posicaoInsercao);
        } else {
            tbody.appendChild(tr);
        }
        
        console.log('✅ [DEBUG] Agendamento inserido na tabela:', aula.id, '- Posição:', posicaoInsercao ? 'antes de ' + posicaoInsercao.getAttribute('data-agendamento-id') : 'final');
    });
    
    console.log('✅ [DEBUG] Total de agendamentos inseridos:', novosAgendamentos.length);
}

// Função para mostrar mensagens no modal
function mostrarMensagemModal(mensagem, tipo) {
    const divMensagem = document.getElementById('mensagemAgendamento');
    
    // Substituir emojis por ícones FontAwesome monocromáticos
    let mensagemFormatada = mensagem;
    if (tipo === 'success') {
        // Remover emojis de sucesso existentes
        mensagemFormatada = mensagem.replace(/✅|✓|☑️/g, '').trim();
        // Adicionar ícone FontAwesome
        divMensagem.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-check-circle" style="color: #155724; font-size: 18px; flex-shrink: 0;"></i>
                <span>${mensagemFormatada}</span>
            </div>
        `;
        divMensagem.style.backgroundColor = '#d4edda';
        divMensagem.style.color = '#155724';
        divMensagem.style.border = '1px solid #c3e6cb';
    } else if (tipo === 'error') {
        // Remover emojis de erro existentes
        mensagemFormatada = mensagem.replace(/❌|✗|⚠️/g, '').trim();
        // Adicionar ícone FontAwesome
        divMensagem.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-circle" style="color: #721c24; font-size: 18px; flex-shrink: 0;"></i>
                <span>${mensagemFormatada}</span>
            </div>
        `;
        divMensagem.style.backgroundColor = '#f8d7da';
        divMensagem.style.color = '#721c24';
        divMensagem.style.border = '1px solid #f5c6cb';
    } else {
        // Mensagem sem tipo definido
        divMensagem.textContent = mensagem;
    }
    
    divMensagem.style.display = 'block';
    
    // Ocultar após 5 segundos
    setTimeout(() => {
        divMensagem.style.display = 'none';
    }, 5000);
}

// Função para recarregar agendamentos da disciplina do servidor
function recarregarAgendamentosDisciplina(disciplinaId) {
    console.log('🔄 [DEBUG] Recarregando agendamentos da disciplina do servidor:', disciplinaId);
    
    const urlParams = new URLSearchParams(window.location.search);
    const turmaId = urlParams.get('turma_id') || <?= $turmaId ?>;
    
    fetch(getBasePath() + '/admin/api/listar-agendamentos-turma.php?turma_id=' + turmaId + '&disciplina=' + encodeURIComponent(disciplinaId))
        .then(response => response.json())
        .then(data => {
            console.log('🔧 [DEBUG] Agendamentos carregados do servidor:', data);
            if (data.success && data.agendamentos) {
                console.log('✅ [DEBUG] Total de agendamentos encontrados:', data.agendamentos.length);
                // Recriar toda a seção de histórico
                atualizarSecaoHistorico(disciplinaId, data.agendamentos);
                
                // Atualizar estatísticas após atualizar histórico (para garantir sincronização)
                setTimeout(() => {
                    atualizarEstatisticasTurma();
                }, 300);
            } else {
                console.error('❌ [DEBUG] Erro ao carregar agendamentos:', data);
            }
        })
        .catch(error => {
            console.error('❌ [DEBUG] Erro ao buscar agendamentos:', error);
        });
}

// Função para fazer scroll até uma disciplina específica e expandir
function scrollParaDisciplina(disciplinaId) {
    console.log('📍 [DEBUG] Navegando para disciplina:', disciplinaId);
    
    if (!disciplinaId) {
        console.error('❌ [DEBUG] ID da disciplina não fornecido');
        return;
    }
    
    // Primeiro, encontrar o card da disciplina na seção "Disciplinas da Turma" (não o card de estatísticas)
    // O card da disciplina tem a classe "disciplina-cadastrada-card" e também tem data-disciplina-id
    let disciplinaCard = document.querySelector(`.disciplina-cadastrada-card[data-disciplina-id="${disciplinaId}"]`);
    
    // Se não encontrou, tentar busca alternativa (pode estar em uma estrutura diferente)
    if (!disciplinaCard) {
        console.warn('⚠️ [DEBUG] Card da disciplina não encontrado com seletor padrão, tentando alternativas...');
        
        // Tentar buscar pelo ID do container de detalhes
        const detalhesContent = document.getElementById(`detalhes-disciplina-${disciplinaId}`);
        if (detalhesContent) {
            disciplinaCard = detalhesContent.closest('.disciplina-cadastrada-card');
        }
        
        // Se ainda não encontrou, buscar por qualquer elemento com data-disciplina-id que não seja o card de estatísticas
        if (!disciplinaCard) {
            const todosCards = document.querySelectorAll(`[data-disciplina-id="${disciplinaId}"]`);
            for (let card of todosCards) {
                // Se não é o card de estatísticas (stats-card), usar este
                if (!card.id || !card.id.startsWith('stats-card-')) {
                    disciplinaCard = card;
                    console.log('✅ [DEBUG] Card encontrado via busca alternativa');
                    break;
                }
            }
        }
    }
    
    if (!disciplinaCard) {
        console.error('❌ [DEBUG] Disciplina não encontrada após todas as tentativas:', disciplinaId);
        // Mostrar mensagem amigável ao usuário
        alert('Disciplina não encontrada na página. Por favor, recarregue a página.');
        return;
    }
    
    console.log('✅ [DEBUG] Card da disciplina encontrado:', disciplinaCard);
    fazerScrollParaCard(disciplinaCard, disciplinaId);
}

// Função auxiliar para fazer scroll até um card específico
function fazerScrollParaCard(card, disciplinaId) {
    // Calcular posição com offset para melhor visualização
    const cardPosition = card.getBoundingClientRect().top + window.pageYOffset;
    const offsetPosition = cardPosition - 100; // 100px do topo para melhor visualização
    
    // Scroll suave até o card da disciplina
    window.scrollTo({
        top: offsetPosition,
        behavior: 'smooth'
    });
    
    // Aguardar um pouco e expandir a disciplina
    setTimeout(() => {
        // Verificar se já está expandida
        const detalhesContent = document.getElementById(`detalhes-disciplina-${disciplinaId}`);
        if (detalhesContent) {
            if (detalhesContent.style.display === 'none' || getComputedStyle(detalhesContent).display === 'none') {
                // Expandir se estiver fechada
                console.log('📂 [DEBUG] Expandindo disciplina:', disciplinaId);
                toggleSimples(disciplinaId);
            }
        }
        
        // Adicionar destaque visual temporário no card da disciplina
        const originalTransition = card.style.transition;
        const originalBoxShadow = card.style.boxShadow;
        const originalBorder = card.style.border;
        
        card.style.transition = 'all 0.3s ease';
        card.style.boxShadow = '0 4px 20px rgba(2, 58, 141, 0.4)';
        card.style.border = '3px solid #023A8D';
        card.style.borderRadius = '12px';
        
        setTimeout(() => {
            card.style.boxShadow = originalBoxShadow || '';
            card.style.border = originalBorder || '';
            card.style.transition = originalTransition || '';
            card.style.borderRadius = '';
        }, 2500);
        
        // Também adicionar destaque breve no card de estatísticas correspondente (feedback visual)
        const statsCard = document.getElementById(`stats-card-${disciplinaId}`);
        if (statsCard) {
            const originalStatsTransition = statsCard.style.transition;
            const originalStatsBoxShadow = statsCard.style.boxShadow;
            const originalStatsTransform = statsCard.style.transform;
            
            statsCard.style.transition = 'all 0.3s ease';
            statsCard.style.boxShadow = '0 4px 12px rgba(2, 58, 141, 0.4)';
            statsCard.style.transform = 'scale(1.03)';
            
            setTimeout(() => {
                statsCard.style.boxShadow = originalStatsBoxShadow || '';
                statsCard.style.transform = originalStatsTransform || '';
                statsCard.style.transition = originalStatsTransition || '';
            }, 1000);
        }
    }, 400);
}

// Função para encontrar e fazer scroll até a primeira disciplina incompleta
function scrollParaPrimeiraDisciplinaIncompleta() {
    // Procurar por cards de estatísticas que indicam disciplina incompleta (não verde)
    const cardsStats = document.querySelectorAll('.disciplina-stats-card');
    
    for (let card of cardsStats) {
        // Verificar se o card não está completo (verde)
        const style = window.getComputedStyle(card);
        const bgColor = style.backgroundColor;
        
        // Se não for verde (#d4edda), é uma disciplina incompleta
        if (!bgColor.includes('rgb(212, 237, 218)')) {
            // Extrair disciplina ID do onclick
            const onclick = card.getAttribute('onclick');
            const match = onclick?.match(/scrollParaDisciplina\('([^']+)'\)/);
            if (match && match[1]) {
                const disciplinaId = match[1];
                console.log('🎯 Encontrada primeira disciplina incompleta:', disciplinaId);
                
                // Aguardar um pouco para garantir que a página carregou
                setTimeout(() => {
                    scrollParaDisciplina(disciplinaId);
                }, 500);
                return;
            }
        }
    }
    
    // Se não encontrou disciplina incompleta, scroll até a primeira disciplina
    if (cardsStats.length > 0) {
        const primeiroCard = cardsStats[0];
        const onclick = primeiroCard.getAttribute('onclick');
        const match = onclick?.match(/scrollParaDisciplina\('([^']+)'\)/);
        if (match && match[1]) {
            setTimeout(() => {
                scrollParaDisciplina(match[1]);
            }, 500);
        }
    }
}

// Função para atualizar estatísticas da turma dinamicamente
function atualizarEstatisticasTurma() {
    const urlParams = new URLSearchParams(window.location.search);
    const turmaId = urlParams.get('turma_id') || <?= $turmaId ?>;
    
    console.log('📊 [DEBUG] Atualizando estatísticas da turma:', turmaId);
    
    fetch(getBasePath() + '/admin/api/estatisticas-turma.php?turma_id=' + turmaId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ [DEBUG] Estatísticas recebidas:', data);
                
                // Atualizar progresso geral
                atualizarProgressoGeral(data.estatisticas_gerais);
                
                // Atualizar cada disciplina
                if (data.disciplinas) {
                    Object.keys(data.disciplinas).forEach(disciplinaId => {
                        atualizarCardDisciplina(disciplinaId, data.disciplinas[disciplinaId]);
                    });
                }
            } else {
                console.error('❌ [DEBUG] Erro ao buscar estatísticas:', data.message);
            }
        })
        .catch(error => {
            console.error('❌ [DEBUG] Erro ao atualizar estatísticas:', error);
        });
}
// Função para atualizar o card de progresso geral
function atualizarProgressoGeral(stats) {
    // Atualizar percentual
    const percentualEl = document.getElementById('percentual-geral');
    if (percentualEl) {
        percentualEl.textContent = stats.percentual_geral + '%';
    }
    
    // Atualizar texto de total de aulas
    const totalAulasTexto = document.getElementById('total-aulas-texto');
    if (totalAulasTexto) {
        totalAulasTexto.textContent = `${stats.total_aulas_agendadas} de ${stats.total_aulas_obrigatorias} aulas agendadas`;
    }
    
    // Atualizar total realizadas
    const totalRealizadas = document.getElementById('total-realizadas');
    if (totalRealizadas) {
        totalRealizadas.textContent = stats.total_aulas_realizadas;
    }
    
    // Atualizar total faltantes
    const totalFaltantes = document.getElementById('total-faltantes');
    if (totalFaltantes) {
        totalFaltantes.textContent = stats.total_faltantes;
    }
    
    // Atualizar barra de progresso
    const barraProgresso = document.getElementById('barra-progresso-geral');
    if (barraProgresso) {
        barraProgresso.style.width = stats.percentual_geral + '%';
    }
}

// Função para atualizar card de uma disciplina específica
function atualizarCardDisciplina(disciplinaId, stats) {
    const card = document.getElementById('stats-card-' + disciplinaId);
    if (!card) {
        console.warn('⚠️ [DEBUG] Card não encontrado para disciplina:', disciplinaId);
        return;
    }
    
    console.log('📊 [DEBUG] Atualizando card da disciplina:', disciplinaId, stats);
    
    // Calcular percentual
    const percentual = stats.percentual || 0;
    
    // Atualizar valores dentro do card
    const agendadasEl = card.querySelector('.stat-agendadas-valor');
    const realizadasEl = card.querySelector('.stat-realizadas-valor');
    const faltantesEl = card.querySelector('.stat-faltantes-valor');
    const percentualEl = card.querySelector('.stat-percentual-valor');
    const barraProgresso = card.querySelector('.stat-progresso-barra');
    const resumoEl = card.querySelector('.stat-resumo');
    
    if (agendadasEl) agendadasEl.textContent = stats.agendadas;
    if (realizadasEl) realizadasEl.textContent = stats.realizadas;
    if (faltantesEl) faltantesEl.textContent = stats.faltantes;
    if (percentualEl) {
        percentualEl.textContent = percentual + '%';
        // Atualizar cor baseada no novo percentual
        atualizarCoresCardDisciplina(card, percentual, stats);
    }
    if (barraProgresso) {
        barraProgresso.style.width = Math.min(percentual, 100) + '%';
    }
    if (resumoEl) {
        resumoEl.textContent = `${stats.agendadas}/${stats.obrigatorias} aulas (faltam ${stats.faltantes})`;
    }
}
// Função para atualizar cores do card baseado no progresso
function atualizarCoresCardDisciplina(card, percentual, stats) {
    let corCard, bgCard, icon, status;
    
    const neutralBackground = '#f7f8fa';
    if (percentual >= 100) {
        corCard = '#28a745';
        bgCard = neutralBackground;
        icon = 'fa-check-circle';
        status = 'Completo';
    } else if (percentual >= 75) {
        corCard = '#0d6efd';
        bgCard = neutralBackground;
        icon = 'fa-flag-checkered';
        status = 'Quase completo';
    } else if (stats.agendadas > 0) {
        corCard = '#f2994a';
        bgCard = neutralBackground;
        icon = 'fa-clock';
        status = 'Em progresso';
    } else {
        corCard = '#6c757d';
        bgCard = neutralBackground;
        icon = 'fa-minus-circle';
        status = 'Não iniciado';
    }
    
    // Atualizar estilos do card
    card.style.background = bgCard;
    card.style.borderColor = 'rgba(0,0,0,0.06)';
    card.style.borderLeftColor = corCard;
    
    // Atualizar ícone e status
    const statusDiv = card.querySelector('.stat-status');
    if (statusDiv) {
        statusDiv.innerHTML = `<i class="fas ${icon}"></i> <span style="font-weight: 500;">${status}</span>`;
        statusDiv.style.color = corCard;
    }
    
    // Atualizar cor do percentual
    const percentualEl = card.querySelector('.stat-percentual-valor');
    if (percentualEl) {
        percentualEl.style.color = corCard;
    }
    
    // Atualizar cor da barra de progresso
    const barraProgresso = card.querySelector('.stat-progresso-barra');
    if (barraProgresso) {
        barraProgresso.style.background = corCard;
    }
}

// Verificar se veio do redirecionamento de "Continuar Agendamento"
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const sucesso = urlParams.get('sucesso');
    
    // Se veio com sucesso=1 (criação de turma), fazer scroll até disciplinas
    if (sucesso === '1') {
        console.log('✅ Detectado redirecionamento de criação/agendamento, fazendo scroll automático...');
        setTimeout(() => {
            scrollParaPrimeiraDisciplinaIncompleta();
        }, 1000);
    }
});

// Atualizar seção completa do histórico
function atualizarSecaoHistorico(disciplinaId, agendamentos) {
    const container = document.getElementById('detalhes-disciplina-' + disciplinaId) || 
                     document.getElementById('data-disciplina-' + disciplinaId);
    if (!container) {
        console.error('❌ [DEBUG] Container não encontrado para disciplina:', disciplinaId);
        return;
    }
    
    const historicoSection = container.querySelector('.historico-agendamentos');
    if (!historicoSection) {
        console.error('❌ [DEBUG] Seção de histórico não encontrada');
        return;
    }
    
    // Remover tabela existente
    const oldTable = historicoSection.querySelector('.table-responsive');
    if (oldTable) oldTable.remove();
    
    // Remover empty states existentes
    const emptyStates = historicoSection.querySelectorAll('.text-center, .empty-state');
    emptyStates.forEach(el => el.remove());
    
    // Criar nova tabela com todos os agendamentos
    if (agendamentos.length > 0) {
        let tbodyHTML = '';
        agendamentos.forEach(aula => {
            const dataBR = aula.data_aula ? new Date(aula.data_aula + 'T00:00:00').toLocaleDateString('pt-BR') : 'N/A';
            const horaInicio = aula.hora_inicio ? (aula.hora_inicio.length >= 5 ? aula.hora_inicio.substring(0, 5) : aula.hora_inicio) : '--:--';
            const horaFim = aula.hora_fim ? (aula.hora_fim.length >= 5 ? aula.hora_fim.substring(0, 5) : aula.hora_fim) : '--:--';
            
            tbodyHTML += `
                <tr data-agendamento-id="${aula.id}">
                    <td><strong>${aula.nome_aula || 'Aula'}</strong></td>
                    <td>${dataBR}</td>
                    <td>${horaInicio} - ${horaFim}</td>
                    <td>${aula.instrutor_nome || 'Não informado'}</td>
                    <td>${aula.sala_nome || 'Não informada'}</td>
                    <td>${aula.duracao_minutos || 50} min</td>
                    <td><span class="badge bg-warning">Agendada</span></td>
                    <td>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm action-btn-tonal" onclick="editarAgendamento(${aula.id}, '${(aula.nome_aula || '').replace(/'/g, "\\'")}', '${aula.data_aula}', '${horaInicio}', '${horaFim}', '${aula.instrutor_id || ''}', '${aula.sala_id || ''}', '${aula.duracao_minutos || 50}', '')" title="Editar agendamento">
                                <span style="font-size: 14px; font-weight: bold;">✏</span>
                            </button>
                            <button type="button" class="btn btn-sm action-btn-outline-danger" onclick="cancelarAgendamento(${aula.id}, '${(aula.nome_aula || '').replace(/'/g, "\\'")}')" title="Cancelar agendamento">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        const tableHTML = `
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-primary">
                        <tr>
                            <th>Aula</th>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Instrutor</th>
                            <th>Sala</th>
                            <th>Duração</th>
                            <th>Status</th>
                            <th width="100">Ações</th>
                        </tr>
                    </thead>
                    <tbody>${tbodyHTML}</tbody>
                </table>
            </div>
        `;
        historicoSection.insertAdjacentHTML('beforeend', tableHTML);
        console.log('✅ [DEBUG] Tabela atualizada com', agendamentos.length, 'agendamentos');
    } else {
        // Se não há agendamentos, mostrar mensagem vazia
        historicoSection.insertAdjacentHTML('beforeend', `
            <div class="text-center py-4">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">Nenhum agendamento encontrado</h6>
                <p class="text-muted small">Não há aulas agendadas para esta disciplina ainda.</p>
            </div>
        `);
    }
}

// CRÍTICO: Garantir que estilos inline sejam removidos e variável CSS seja aplicada
(function() {
    function removerEstilosInlineDuplicados() {
        // Remover padding-top inline do body (se existir)
        if (document.body.style.paddingTop) {
            document.body.style.paddingTop = '';
        }
        
        // Remover margin-top inline do .admin-main e .admin-container (se existir)
        const adminMain = document.querySelector('.admin-main');
        const adminContainer = document.querySelector('.admin-container');
        
        if (adminMain && adminMain.style.marginTop) {
            adminMain.style.marginTop = '';
        }
        
        if (adminContainer && adminContainer.style.marginTop) {
            adminContainer.style.marginTop = '';
        }
        
        // Garantir que a variável CSS --navbar-h esteja definida
        const topbar = document.querySelector('.topbar');
        if (topbar) {
            const navbarHeight = topbar.offsetHeight || 64;
            document.documentElement.style.setProperty('--navbar-h', `${navbarHeight}px`);
        }
    }
    
    // Executar imediatamente
    removerEstilosInlineDuplicados();
    
    // Executar após DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', removerEstilosInlineDuplicados);
    }
    
    // Executar após um pequeno delay para garantir que outros scripts já executaram
    setTimeout(removerEstilosInlineDuplicados, 100);
    
    // Executar em resize para atualizar variável CSS se necessário
    window.addEventListener('resize', removerEstilosInlineDuplicados, { passive: true });
})();

/**
 * Ajustar altura do calendário para incluir todas as aulas
 * Garante que a última aula seja completamente visível
 */
function ajustarAlturaCalendario() {
    const timelineBody = document.querySelector('.timeline-body');
    const timelineHours = document.querySelector('.timeline-hours');
    const dayColumns = document.querySelectorAll('.timeline-day-column');
    const timelineCalendar = document.querySelector('.timeline-calendar');
    
    if (!timelineBody || !timelineHours) {
        console.log('⚠️ Elementos do calendário não encontrados');
        return;
    }
    
    // Encontrar a última aula renderizada
    const todasAulas = document.querySelectorAll('.timeline-slot.aula');
    let ultimaAulaBottom = 0;
    let ultimaAulaId = null;
    
    todasAulas.forEach(aula => {
        const top = parseFloat(aula.style.top) || 0;
        const height = parseFloat(aula.style.height) || 0;
        const bottom = top + height;
        if (bottom > ultimaAulaBottom) {
            ultimaAulaBottom = bottom;
            ultimaAulaId = aula.getAttribute('data-aula-id') || 'N/A';
        }
    });
    
    console.log('🔍 Debug calendário:', {
        totalAulas: todasAulas.length,
        ultimaAulaBottom: ultimaAulaBottom + 'px',
        ultimaAulaId: ultimaAulaId,
        alturaAtualBody: timelineBody.offsetHeight + 'px',
        alturaAtualHours: timelineHours.offsetHeight + 'px'
    });
    
    // Calcular altura necessária (última aula + margem de 100px)
    const alturaNecessaria = ultimaAulaBottom + 100;
    
    // Obter altura atual (do style ou offsetHeight)
    const alturaAtualBody = parseFloat(timelineBody.style.minHeight) || timelineBody.offsetHeight;
    const alturaAtualHours = parseFloat(timelineHours.style.minHeight) || timelineHours.offsetHeight;
    
    // Sempre ajustar para garantir que todas as aulas sejam visíveis
    if (alturaNecessaria > alturaAtualBody || todasAulas.length > 0) {
        timelineBody.style.minHeight = alturaNecessaria + 'px';
        timelineHours.style.minHeight = alturaNecessaria + 'px';
        dayColumns.forEach(col => {
            col.style.minHeight = alturaNecessaria + 'px';
        });
        
        // Garantir que o container pai permita scroll
        if (timelineCalendar) {
            timelineCalendar.style.overflowY = 'auto';
            timelineCalendar.style.overflowX = 'hidden';
        }
        
        console.log('✅ Altura do calendário ajustada:', {
            anterior: Math.max(alturaAtualBody, alturaAtualHours) + 'px',
            nova: alturaNecessaria + 'px',
            ultimaAula: ultimaAulaId
        });
        
        // Scroll automático para a última aula após ajuste
        if (!window.__calendarioScrollMeta) {
            setTimeout(() => {
                const ultimaAula = document.querySelector(`.timeline-slot.aula[data-aula-id="${ultimaAulaId}"]`);
                if (ultimaAula && ultimaAulaId !== 'N/A') {
                    ultimaAula.scrollIntoView({ behavior: 'smooth', block: 'end' });
                    console.log('📍 Scroll automático para última aula:', ultimaAulaId);
                }
            }, 500);
        } else {
            console.debug('📍 Scroll automático para última aula ignorado (há meta de foco ativa).');
            setTimeout(() => {
                if (window.__calendarioScrollMeta) {
                    window.focarCalendarioNoAgendamento(window.__calendarioScrollMeta);
                }
            }, 60);
        }
    }
}

// Executar após carregamento completo
document.addEventListener('DOMContentLoaded', function() {
    // Verificar quantas aulas foram renderizadas
    setTimeout(function() {
        const todasAulas = document.querySelectorAll('.timeline-slot.aula');
        console.log('📚 Total de aulas renderizadas no DOM:', todasAulas.length);
        
        todasAulas.forEach((aula, idx) => {
            const aulaId = aula.getAttribute('data-aula-id');
            const nomeAula = aula.querySelector('div')?.textContent || 'N/A';
            const top = aula.style.top;
            const height = aula.style.height;
            console.log(`  Aula ${idx + 1}: ID=${aulaId}, Nome="${nomeAula}", Top=${top}, Height=${height}`);
        });
        
        ajustarAlturaCalendario();
    }, 500);
    
    setTimeout(ajustarAlturaCalendario, 1000);
    setTimeout(ajustarAlturaCalendario, 2000); // Executar novamente após 2 segundos
});

// Executar após qualquer atualização dinâmica
if (typeof MutationObserver !== 'undefined') {
    const observer = new MutationObserver(function(mutations) {
        let shouldAdjust = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && (node.classList.contains('timeline-slot') || node.querySelector('.timeline-slot'))) {
                        shouldAdjust = true;
                    }
                });
            }
        });
        if (shouldAdjust) {
            setTimeout(ajustarAlturaCalendario, 100);
        }
    });
    
    const timelineBody = document.querySelector('.timeline-body');
    if (timelineBody) {
        observer.observe(timelineBody, { childList: true, subtree: true });
    }
}

/**
 * Excluir turma completamente (apenas para administradores)
 * Exclui a turma e todos os dados relacionados (agendamentos, alunos, etc.)
 */
function excluirTurmaCompleta(turmaId, nomeTurma) {
    // Confirmação com detalhes
    const mensagem = `⚠️ ATENÇÃO: Esta ação é IRREVERSÍVEL!\n\n` +
                     `Você está prestes a excluir COMPLETAMENTE a turma:\n` +
                     `"${nomeTurma}"\n\n` +
                     `Isso irá excluir:\n` +
                     `• A turma em si\n` +
                     `• Todas as aulas agendadas\n` +
                     `• Todas as matrículas de alunos\n` +
                     `• Todos os registros relacionados\n\n` +
                     `Tem certeza que deseja continuar?`;
    
    if (!confirm(mensagem)) {
        return;
    }
    
    // Segunda confirmação para garantir
    if (!confirm('⚠️ ÚLTIMA CONFIRMAÇÃO!\n\nEsta ação não pode ser desfeita. Deseja realmente excluir esta turma?')) {
        return;
    }
    
    // Mostrar loading
    const btnExcluir = event.target.closest('button');
    const textoOriginal = btnExcluir.innerHTML;
    btnExcluir.disabled = true;
    btnExcluir.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Excluindo...';
    
    // Fazer requisição para API
    fetch('api/turmas-teoricas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            acao: 'excluir',
            turma_id: turmaId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            // Sucesso - mostrar mensagem e redirecionar
            alert('✅ ' + data.mensagem);
            window.location.href = '?page=turmas-teoricas';
        } else {
            // Erro - restaurar botão e mostrar mensagem
            btnExcluir.disabled = false;
            btnExcluir.innerHTML = textoOriginal;
            alert('❌ Erro: ' + data.mensagem);
        }
    })
    .catch(error => {
        // Erro de rede - restaurar botão e mostrar mensagem
        btnExcluir.disabled = false;
        btnExcluir.innerHTML = textoOriginal;
        console.error('Erro ao excluir turma:', error);
        alert('❌ Erro ao excluir turma. Verifique sua conexão e tente novamente.');
    });
}

// ==========================================
// FUNÇÕES OTIMIZADAS PARA UX
// ==========================================

/**
 * Toggle do menu de ações da disciplina
 */
function toggleDisciplinaMenu(button) {
    const dropdown = button.nextElementSibling;
    if (!dropdown) {
        return;
    }
    
    const disciplinaCard = button.closest('.disciplina-accordion');
    if (disciplinaCard) {
        const disciplinaId = disciplinaCard.getAttribute('data-disciplina-id');
        if (disciplinaId && !disciplinaCard.classList.contains('expanded')) {
            toggleSimples(disciplinaId);
        }
    }
    
    const isOpen = dropdown.classList.contains('show');
    
    // Fechar todos os outros menus
    document.querySelectorAll('.disciplina-menu-dropdown.show').forEach(menu => {
        menu.classList.remove('show');
    });
    
    // Alternar o menu atual
    if (!isOpen) {
        dropdown.classList.add('show');
        
        // Fechar ao clicar fora
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 10);
    }
}

/**
 * Expandir todas as disciplinas
 */
function expandirTodasDisciplinas() {
    console.log('📂 Expandindo todas as disciplinas...');
    const disciplinas = document.querySelectorAll('.disciplina-accordion');
    
    disciplinas.forEach(card => {
        const disciplinaId = card.getAttribute('data-disciplina-id');
        const content = card.querySelector('.disciplina-detalhes-content');
        const header = card.querySelector('.disciplina-header-clickable');
        
        if (content && content.style.display === 'none') {
            content.style.display = 'block';
            card.classList.add('expanded');
            
            if (header) {
                header.setAttribute('aria-expanded', 'true');
            }
            
            console.log(`  ✅ Expandida: ${disciplinaId}`);
        }
    });
}

/**
 * Recolher todas as disciplinas
 */
function recolherTodasDisciplinas() {
    console.log('📁 Recolhendo todas as disciplinas...');
    const disciplinas = document.querySelectorAll('.disciplina-accordion');
    
    disciplinas.forEach(card => {
        const disciplinaId = card.getAttribute('data-disciplina-id');
        const content = card.querySelector('.disciplina-detalhes-content');
        const header = card.querySelector('.disciplina-header-clickable');
        
        if (content && content.style.display !== 'none') {
            content.style.display = 'none';
            card.classList.remove('expanded');
            
            if (header) {
                header.setAttribute('aria-expanded', 'false');
            }
            
            console.log(`  ✅ Recolhida: ${disciplinaId}`);
        }
    });
}

/**
 * Filtrar agendamentos por sala, instrutor ou status
 */
function filtrarAgendamentos(disciplinaId, tipo, valor) {
    console.log(`🔍 Filtrando agendamentos: disciplina=${disciplinaId}, tipo=${tipo}, valor=${valor}`);
    
    const tabela = document.querySelector(`table[data-disciplina-id="${disciplinaId}"]`);
    if (!tabela) {
        console.error('❌ Tabela não encontrada');
        return;
    }
    
    const linhas = tabela.querySelectorAll('tbody tr');
    let visiveisCount = 0;
    
    linhas.forEach(linha => {
        let mostrar = true;
        
        // Aplicar filtros
        if (valor) {
            const valorLinha = linha.getAttribute(`data-${tipo}`);
            if (tipo === 'status') {
                mostrar = valorLinha === valor;
            } else {
                mostrar = valorLinha && valorLinha.toLowerCase().includes(valor.toLowerCase());
            }
        }
        
        if (mostrar) {
            linha.style.display = '';
            visiveisCount++;
        } else {
            linha.style.display = 'none';
        }
    });
    
    console.log(`  ✅ ${visiveisCount} agendamentos visíveis de ${linhas.length}`);
}

/**
 * Agendar aula semelhante (duplicar com pré-preenchimento)
 * Abre o modal em modo CRIAR com dados pré-preenchidos, exceto data, horário e quantidade
 */
function duplicarAgendamento(agendamentoId) {
    console.log('📋 Agendando aula semelhante ao agendamento:', agendamentoId);
    
    const modal = document.getElementById('modalAgendarAula');
    if (!modal) {
        console.error('Modal de agendamento não encontrado!');
        return;
    }
    
    // Configurar modo CRIAR (não editar)
    const modalModo = document.getElementById('modal_modo');
    const modalAcao = document.getElementById('modal_acao');
    const modalAulaId = document.getElementById('modal_aula_id');
    const modalTitulo = document.getElementById('modal_titulo');
    const btnAgendarTexto = document.getElementById('btnAgendarTexto');
    const campoObservacoes = document.getElementById('campoObservacoesModal');
    const modalObservacoes = document.getElementById('modal_observacoes');
    const btnExcluirModal = document.getElementById('btn_excluir_modal');
    
    // Garantir modo CRIAR
    if (modalModo) modalModo.value = 'criar';
    if (modalAcao) modalAcao.value = 'agendar_aula';
    if (modalAulaId) modalAulaId.value = ''; // Limpar ID para criar novo
    if (modalTitulo) {
        modalTitulo.innerHTML = '<i class="fas fa-plus"></i> Agendar Aula Semelhante';
    }
    if (btnAgendarTexto) {
        btnAgendarTexto.textContent = 'Agendar Aula';
    }
    if (campoObservacoes) {
        campoObservacoes.style.display = 'none'; // Ocultar observações em novo agendamento
    }
    if (modalObservacoes) {
        modalObservacoes.value = ''; // Limpar observações
    }
    if (btnExcluirModal) {
        btnExcluirModal.style.display = 'none'; // Ocultar botão excluir
    }
    
    // Exibir modal imediatamente
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Buscar dados do agendamento original para pré-preencher
    fetch(`api/agendamento-detalhes.php?id=${agendamentoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const agendamento = data.agendamento;
                
                // Preencher APENAS os campos que devem ser copiados
                const modalDisciplinaId = document.getElementById('modal_disciplina_id');
                const modalDisciplinaNome = document.getElementById('modal_disciplina_nome');
                const modalInstrutorId = document.getElementById('modal_instrutor_id');
                const modalHoraInicio = document.getElementById('modal_hora_inicio');
                
                // NÃO preencher: data, quantidade de aulas, observações
                const modalDataAula = document.getElementById('modal_data_aula');
                const modalQuantidadeAulas = document.getElementById('modal_quantidade_aulas');
                
                // Pré-preencher disciplina
                let disciplinaIdNormalizado = null;
                if (modalDisciplinaId && agendamento.disciplina) {
                    disciplinaIdNormalizado = normalizarDisciplinaJS(agendamento.disciplina);
                    modalDisciplinaId.value = disciplinaIdNormalizado;
                } else if (modalDisciplinaId && agendamento.nome_aula) {
                    // Extrair disciplina do nome
                    const nomeDisciplina = agendamento.nome_aula.split(' - ')[0];
                    const disciplinaId = nomeDisciplina.toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/\s+/g, '_')
                        .replace(/ç/g, 'c')
                        .replace(/ñ/g, 'n');
                    disciplinaIdNormalizado = normalizarDisciplinaJS(disciplinaId);
                    modalDisciplinaId.value = disciplinaIdNormalizado;
                }
                
                // ✅ Registrar disciplina aberta para manter accordion após salvar
                if (disciplinaIdNormalizado) {
                    window.ultimaDisciplinaAberta = disciplinaIdNormalizado;
                    console.log('📌 Disciplina registrada ao duplicar:', window.ultimaDisciplinaAberta);
                }
                
                if (modalDisciplinaNome && agendamento.nome_aula) {
                    const nomeDisciplina = agendamento.nome_aula.split(' - ')[0];
                    modalDisciplinaNome.value = nomeDisciplina;
                }
                
                // Pré-preencher instrutor
                if (modalInstrutorId && agendamento.instrutor_id) {
                    modalInstrutorId.value = agendamento.instrutor_id;
                }
                
                // LIMPAR data, horário e quantidade (usuário deve escolher)
                if (modalDataAula) {
                    modalDataAula.value = '';
                    modalDataAula.focus(); // Focar no campo de data
                }
                if (modalHoraInicio) {
                    modalHoraInicio.value = ''; // Deixar horário em branco
                }
                if (modalQuantidadeAulas) {
                    modalQuantidadeAulas.value = '1'; // Resetar para 1
                }
                
                console.log('✅ Modal pré-preenchido para agendar aula semelhante');
            } else {
                console.error('Erro ao buscar dados do agendamento:', data.message);
                alert('Erro ao carregar dados do agendamento.');
            }
        })
        .catch(error => {
            console.error('Erro ao buscar agendamento:', error);
            alert('Erro ao carregar dados. Tente novamente.');
        });
}

/**
 * Ações das disciplinas (menu de ⋮)
 */
function duplicarDisciplina(disciplinaId) {
    console.log('📋 Agendando aula semelhante para disciplina:', disciplinaId);
    // TODO: Abrir modal pré-preenchido com a disciplina selecionada
    alert('Esta funcionalidade abrirá o modal de agendamento com a disciplina pré-selecionada.');
}

function exportarDisciplina(disciplinaId) {
    console.log('📤 Exportando agendamentos da disciplina:', disciplinaId);
    // TODO: Gerar CSV/PDF com todos os agendamentos da disciplina
    alert('Esta funcionalidade exportará todos os agendamentos desta disciplina em formato CSV ou PDF.');
}

function removerDisciplina(disciplinaId) {
    console.log('🗑️ Removendo disciplina:', disciplinaId);
    
    if (confirm('Tem certeza que deseja remover esta disciplina e todos os seus agendamentos? Esta ação não pode ser desfeita.')) {
        // TODO: Implementar remoção via API
        alert('Esta funcionalidade removerá a disciplina e todos os agendamentos associados.');
    }
}

// ==========================================
// INICIALIZAÇÃO
// ==========================================

// Adicionar suporte a teclado nos accordions
document.addEventListener('DOMContentLoaded', function() {
    const accordionHeaders = document.querySelectorAll('.disciplina-header-clickable');
    
    accordionHeaders.forEach(header => {
        header.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                header.click();
            }
        });
    });
    
    console.log('✅ Funções de otimização UX carregadas');
    
    // Interceptar reload após agendamento bem-sucedido
    interceptarReloadAgendamento();
});

/**
 * Interceptar reload e atualizar apenas os dados necessários
 * Mantém o accordion aberto após agendar/editar aula
 */
function interceptarReloadAgendamento() {
    // Sobrescrever window.location.reload temporariamente
    const originalReload = window.location.reload;
    
    // Inicializar variável global se não existir
    if (!window.ultimaDisciplinaAberta) {
        window.ultimaDisciplinaAberta = null;
    }
    
    // Guardar qual disciplina está aberta quando clicar no accordion
    window.addEventListener('click', function(e) {
        const disciplinaCard = e.target.closest('.disciplina-accordion');
        if (disciplinaCard) {
            const disciplinaId = disciplinaCard.getAttribute('data-disciplina-id');
            if (disciplinaId) {
                window.ultimaDisciplinaAberta = disciplinaId;
                console.log('📌 Disciplina clicada registrada:', window.ultimaDisciplinaAberta);
            }
        }
    });
    
    // Sobrescrever reload
    window.location.reload = function(forceReload) {
        // Verificar se temos uma disciplina aberta (definida ao abrir modal ou clicar no accordion)
        const ultimaDisciplina = window.ultimaDisciplinaAberta;
        
        if (ultimaDisciplina) {
            console.log('🔄 Interceptando reload - atualizando disciplina:', ultimaDisciplina);
            
            // Em vez de recarregar, atualizar apenas os dados da disciplina
            atualizarDisciplinaAposAgendamento(ultimaDisciplina)
                .then(() => {
                    console.log('✅ Dados atualizados com sucesso - accordion mantido aberto');
                    // Limpar flags
                    window.currentEditAgendamentoId = null;
                })
                .catch(error => {
                    console.error('❌ Erro ao atualizar dados:', error);
                    // Em caso de erro, fazer reload tradicional
                    originalReload.call(window.location, forceReload);
                });
        } else {
            // Sem disciplina registrada - fazer reload normal
            console.log('ℹ️ Nenhuma disciplina registrada - reload normal');
            originalReload.call(window.location, forceReload);
        }
    };
}

/**
 * Atualizar dados da disciplina após agendamento
 * @param {string} disciplinaId - ID da disciplina a ser atualizada
 */
async function atualizarDisciplinaAposAgendamento(disciplinaId) {
    try {
        console.log('🔄 Atualizando disciplina:', disciplinaId);
        
        // Buscar turma_id da página atual
        const urlParams = new URLSearchParams(window.location.search);
        const turmaId = urlParams.get('turma_id');
        
        if (!turmaId) {
            throw new Error('turma_id não encontrado na URL');
        }
        
        // Buscar dados atualizados da disciplina
        const url = `${getBasePath()}/admin/api/disciplina-agendamentos.php?turma_id=${turmaId}&disciplina_id=${disciplinaId}`;
        console.log('🌐 Buscando dados em:', url);
        
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('📦 Dados recebidos:', data);
        
        if (!data.success) {
            throw new Error(data.mensagem || 'Erro ao buscar dados');
        }
        
        const statsResposta = data.stats || {};
        const agendadasTotais = (statsResposta.agendadas || 0) + (statsResposta.realizadas || 0);
        const faltantesCalculado = Number.isFinite(statsResposta.faltantes)
            ? statsResposta.faltantes
            : Math.max((statsResposta.obrigatorias || 0) - agendadasTotais, 0);
        const statsParaCards = {
            agendadas: agendadasTotais,
            realizadas: statsResposta.realizadas || 0,
            faltantes: faltantesCalculado,
            obrigatorias: statsResposta.obrigatorias || 0,
            percentual: (statsResposta.obrigatorias || 0) > 0
                ? Math.round((agendadasTotais / statsResposta.obrigatorias) * 100)
                : 0
        };
        
        // Atualizar KPIs (chips de estatísticas)
        const statsContainer = document.querySelector(`[data-disciplina-id="${disciplinaId}"] .aulas-stats-container`);
        if (statsContainer) {
            const agendadasEl = statsContainer.querySelector('.stat-agendadas .stat-value');
            if (agendadasEl) agendadasEl.textContent = statsParaCards.agendadas || 0;
            
            const realizadasEl = statsContainer.querySelector('.stat-realizadas .stat-value');
            if (realizadasEl) realizadasEl.textContent = statsParaCards.realizadas || 0;
            
            const faltantesEl = statsContainer.querySelector('.stat-faltantes .stat-value');
            if (faltantesEl) faltantesEl.textContent = statsParaCards.faltantes || 0;
        }
        
        // Atualizar cartão consolidado da disciplina
        if (typeof atualizarCardDisciplina === 'function') {
            atualizarCardDisciplina(disciplinaId, statsParaCards);
        }
        
        // Atualizar histórico completo (cria tabela se necessário)
        if (Array.isArray(data.agendamentos)) {
            atualizarSecaoHistorico(disciplinaId, data.agendamentos);
        }
        
        // Garantir que o accordion permaneça aberto
        const detalhesContent = document.getElementById(`detalhes-disciplina-${disciplinaId}`);
        if (detalhesContent) {
            detalhesContent.style.display = 'block';
            console.log('✅ Detalhes da disciplina mantidos visíveis');
        } else {
            console.warn('⚠️ Elemento detalhes-disciplina não encontrado:', `detalhes-disciplina-${disciplinaId}`);
        }
        
        const card = document.querySelector(`[data-disciplina-id="${disciplinaId}"]`);
        if (typeof atualizarEstatisticasTurma === 'function') {
            atualizarEstatisticasTurma();
        }
        
        if (card) {
            card.classList.add('expanded');
            
            // Scroll suave até a disciplina atualizada
            setTimeout(() => {
                card.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'nearest' 
                });
            }, 100);
            
            console.log('✅ Card da disciplina expandido');
        } else {
            console.warn('⚠️ Card da disciplina não encontrado:', `[data-disciplina-id="${disciplinaId}"]`);
        }
        
        console.log('✅ Disciplina atualizada e mantida aberta');
        
    } catch (error) {
        console.error('❌ Erro ao atualizar disciplina:', error);
        throw error;
    }
}

/**
 * Gerar HTML de uma linha de agendamento
 */
function gerarLinhaAgendamento(agendamento) {
    // Formatar data
    const data = new Date(agendamento.data_aula);
    const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    const meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    const dataFormatada = `${diasSemana[data.getDay()]}, ${data.getDate()} ${meses[data.getMonth()]} ${data.getFullYear()}`;
    
    // Formatar horário
    const horarioFormatado = `${agendamento.hora_inicio.substring(0,5)}–${agendamento.hora_fim.substring(0,5)}`;
    
    // Nome da aula sem " - Aula X"
    const nomeAulaDisplay = agendamento.nome_aula.replace(/ - Aula \d+$/i, '');
    
    // Status badge
    const statusMap = {
        'agendada': { class: 'bg-warning', text: 'AGENDADA' },
        'realizada': { class: 'bg-success', text: 'REALIZADA' },
        'cancelada': { class: 'bg-danger', text: 'CANCELADA' },
        'reagendada': { class: 'bg-info', text: 'REAGENDADA' }
    };
    const status = statusMap[agendamento.status] || { class: 'bg-secondary', text: agendamento.status.toUpperCase() };
    
    return `
        <tr data-agendamento-id="${agendamento.id}" data-sala="${agendamento.sala_nome || ''}" data-instrutor="${agendamento.instrutor_nome || ''}" data-status="${agendamento.status}">
            <td data-label="Aula"><strong>${nomeAulaDisplay}</strong></td>
            <td data-label="Data">${dataFormatada}</td>
            <td data-label="Horário">${horarioFormatado}</td>
            <td data-label="Instrutor">${agendamento.instrutor_nome || 'Não informado'}</td>
            <td data-label="Sala">${agendamento.sala_nome || 'Não informada'}</td>
            <td data-label="Duração">${agendamento.duracao_minutos} min</td>
            <td data-label="Status">
                <span class="badge ${status.class}">${status.text}</span>
            </td>
            <td data-label="Ações">
                <div class="btn-group" role="group">
                    ${agendamento.status === 'agendada' ? `
                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                onclick="editarAgendamento(${agendamento.id}, '${agendamento.nome_aula}', '${agendamento.data_aula}', '${agendamento.hora_inicio}', '${agendamento.hora_fim}', '${agendamento.instrutor_id}', '${agendamento.sala_id || ''}', '${agendamento.duracao_minutos}', '${agendamento.observacoes || ''}')"
                                title="Editar agendamento"
                                style="min-width: 32px; height: 32px;">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                onclick="duplicarAgendamento(${agendamento.id})"
                                title="Agendar semelhante"
                                style="min-width: 32px; height: 32px;">
                            <i class="fas fa-clone"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                onclick="if(confirm('Tem certeza que deseja cancelar este agendamento?')) cancelarAgendamento(${agendamento.id}, '${agendamento.nome_aula}')"
                                title="Cancelar agendamento">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : '<span class="text-muted small">Não editável</span>'}
                </div>
            </td>
        </tr>
    `;
}
</script>
