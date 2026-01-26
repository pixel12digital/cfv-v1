<?php
/**
 * Dashboard do Instrutor - Mobile First
 * Interface focada em usabilidade móvel
 * IMPORTANTE: Instrutor NÃO pode criar agendamentos, apenas cancelar/transferir
 * 
 * FASE INSTRUTOR - AULAS TEORICAS - Correção completa
 * 
 * ANTES:
 * - Dashboard mostrava apenas aulas práticas da tabela `aulas`
 * - Estatísticas do dia não incluíam aulas teóricas
 * - "Aulas de Hoje" e "Próximas Aulas" não mostravam aulas teóricas
 * 
 * DEPOIS:
 * - Dashboard agora mostra aulas práticas E teóricas
 * - Estatísticas do dia combinam dados de ambas as fontes
 * - "Aulas de Hoje" inclui aulas teóricas do instrutor
 * - "Próximas Aulas (7 dias)" inclui aulas teóricas do instrutor
 * - Aulas teóricas mostram: turma, disciplina, sala
 * - Botões de ação diferenciados para teóricas (Chamada/Diário) vs práticas (Transferir/Cancelar)
 * 
 * ARQUIVOS AFETADOS:
 * - instrutor/dashboard.php (queries e exibição)
 * - instrutor/aulas.php (queries de listagem completa)
 * 
 * LÓGICA:
 * - Aulas práticas: tabela `aulas` com `instrutor_id`
 * - Aulas teóricas: tabela `turma_aulas_agendadas` com `instrutor_id`
 * - Ambas são combinadas e ordenadas por data/hora
 * 
 * REFACTOR DASHBOARD INSTRUTOR - Reorganização de Layout/UX (2025-11)
 * 
 * MUDANÇAS REALIZADAS (REFINAMENTO FINAL):
 * 
 * 1. BLOCO SUPERIOR - Grid 2 colunas (desktop):
 *    - Coluna esquerda: Card "Próxima Aula" compacto com badge, horário, tipo, status de chamada e botões
 *    - Coluna direita: Card "Resumo de Hoje" (3 indicadores) + Card "Pendências"
 *    - Grid responsivo: 2 colunas no desktop (>= 992px), empilhado no mobile
 * 
 * 2. AÇÕES RÁPIDAS:
 *    - Movidas para logo abaixo do grid superior
 *    - Card horizontal com botões em linha (desktop) ou empilhados (mobile)
 *    - Botões menores e mais compactos
 * 
 * 3. AULAS DE HOJE:
 *    - Tabela refinada com tipografia melhorada
 *    - Hora em linha única (18:00 – 18:50)
 *    - Badges pequenos para Tipo (TEOR/PRAT) e Status (PENDENTE/CONCLUÍDA)
 *    - Disciplina/Turma em duas linhas (disciplina forte, turma menor)
 *    - Botões de ação menores com ícones e tooltips
 *    - Linhas com chamada concluída destacadas (fundo verde claro)
 * 
 * 4. AVISOS:
 *    - Card separado com menos destaque visual
 *    - Lista compacta das últimas 3 notificações
 * 
 * 5. PRÓXIMAS AULAS (7 dias):
 *    - Compactada para mostrar apenas 2-3 primeiros dias
 *    - Lista agrupada por data com resumo (primeiras 2 aulas por dia)
 *    - Link "Ver todas as aulas" no cabeçalho
 * 
 * ARQUIVOS AFETADOS:
 * - instrutor/dashboard.php (apenas HTML/CSS reorganizado, lógica PHP mantida)
 * 
 * OBSERVAÇÕES:
 * - Todas as rotas e URLs foram preservadas (origem=instrutor, etc.)
 * - Queries SQL e regras de negócio não foram alteradas
 * - Verificação de chamada registrada adicionada para aulas teóricas (consulta turma_presencas)
 * - CSS Grid usado para layout 2 colunas no desktop (grid-template-columns: 2fr 1.2fr)
 * - Responsividade mantida: mobile empilha tudo em coluna única
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/services/SistemaNotificacoes.php';

// DEBUG: dashboard instrutor carregado (instrutor/dashboard.php)

// Inicializar variáveis antes de qualquer verificação
$aulasHoje = [];
$proximasAulas = [];
$statsHoje = [
    'total_aulas' => 0,
    'pendentes' => 0,
    'concluidas' => 0
];

// Verificar autenticação
$user = getCurrentUser();
if (!$user || $user['tipo'] !== 'instrutor') {
    // FASE 2 - Correção: Usar BASE_PATH dinamicamente
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/login.php');
    exit();
}

$db = db();
try {
    $notificacoes = new SistemaNotificacoes();
} catch (Exception $e) {
    error_log('[DASHBOARD] Erro ao inicializar SistemaNotificacoes: ' . $e->getMessage());
    // Continuar sem notificações se houver erro
    $notificacoes = null;
}
$proximaAula = null;

// Verificar se precisa trocar senha - se sim, forçar redirecionamento
// Esta verificação deve estar em TODAS as páginas do instrutor
try {
    $checkColumn = $db->fetch("SHOW COLUMNS FROM usuarios LIKE 'precisa_trocar_senha'");
    if ($checkColumn) {
        $usuarioCompleto = $db->fetch("SELECT precisa_trocar_senha FROM usuarios WHERE id = ?", [$user['id']]);
        if ($usuarioCompleto && isset($usuarioCompleto['precisa_trocar_senha']) && $usuarioCompleto['precisa_trocar_senha'] == 1) {
            // Verificar se não está já na página de trocar senha
            $currentPage = basename($_SERVER['PHP_SELF']);
            if ($currentPage !== 'trocar-senha.php') {
                $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                header('Location: ' . $basePath . '/instrutor/trocar-senha.php?forcado=1');
                exit();
            }
        }
    }
} catch (Exception $e) {
    // Se houver erro, continuar normalmente
    if (defined('LOG_ENABLED') && LOG_ENABLED) {
        error_log('Erro ao verificar precisa_trocar_senha: ' . $e->getMessage());
    }
}

// Buscar dados do instrutor (incluindo foto)
// A tabela instrutores tem usuario_id que referencia usuarios.id
try {
    $instrutor = $db->fetch("
        SELECT i.*, u.nome as nome_usuario, u.email as email_usuario 
        FROM instrutores i 
        LEFT JOIN usuarios u ON i.usuario_id = u.id 
        WHERE i.usuario_id = ?
    ", [$user['id']]);
} catch (Exception $e) {
    error_log('[DASHBOARD] Erro ao buscar dados do instrutor: ' . $e->getMessage());
    $instrutor = null;
}

// Se não encontrar na tabela instrutores, usar dados do usuário diretamente
if (!$instrutor) {
    $instrutor = [
        'id' => null,
        'usuario_id' => $user['id'],
        'nome' => $user['nome'] ?? 'Instrutor',
        'nome_usuario' => $user['nome'] ?? 'Instrutor',
        'email_usuario' => $user['email'] ?? '',
        'credencial' => null,
        'cfc_id' => null
    ];
}

// Garantir que temos um nome para exibir
$instrutor['nome'] = $instrutor['nome'] ?? $instrutor['nome_usuario'] ?? $user['nome'] ?? 'Instrutor';

// Obter o ID do instrutor (da tabela instrutores) para usar nas queries de aulas
// Se não tiver registro na tabela instrutores, não terá aulas
$instrutorId = getCurrentInstrutorId($user['id']);
if (!$instrutorId) {
    error_log("[WARN] Dashboard instrutor: instrutor_id não encontrado para usuario_id={$user['id']}");
    $instrutorId = null;
}

// FASE INSTRUTOR - AULAS TEORICAS - Buscar aulas do dia (práticas + teóricas)
$hoje = date('Y-m-d');
$aulasHoje = [];
$aulasPraticasHoje = [];
$aulasTeoricasHoje = [];

if ($instrutorId) {
    try {
        // Buscar aulas práticas do dia
        $aulasPraticasHoje = $db->fetchAll("
            SELECT a.*, 
                   a.aluno_id,
                   al.nome as aluno_nome, 
                   al.telefone as aluno_telefone,
                   al.cpf as aluno_cpf,
                   al.foto as aluno_foto,
                   al.categoria_cnh as aluno_categoria_cnh,
                   al.email as aluno_email,
                   v.modelo as veiculo_modelo, v.placa as veiculo_placa,
                   'pratica' as tipo_aula
            FROM aulas a
            JOIN alunos al ON a.aluno_id = al.id
            LEFT JOIN veiculos v ON a.veiculo_id = v.id
            WHERE a.instrutor_id = ? 
              AND a.data_aula = ?
              AND a.status != 'cancelada'
            ORDER BY a.hora_inicio ASC
        ", [$instrutorId, $hoje]);
    } catch (Exception $e) {
        error_log('[DASHBOARD] Erro ao buscar aulas práticas: ' . $e->getMessage());
        $aulasPraticasHoje = [];
    }
    
    try {
        // FASE INSTRUTOR - AULAS TEORICAS - Buscar aulas teóricas do dia
        $aulasTeoricasHoje = $db->fetchAll("
            SELECT 
                taa.id,
                taa.turma_id,
                taa.disciplina,
                taa.nome_aula,
                taa.data_aula,
                taa.hora_inicio,
                taa.hora_fim,
                taa.status,
                taa.observacoes,
                taa.sala_id,
                tt.nome as turma_nome,
                s.nome as sala_nome,
                'teorica' as tipo_aula,
                NULL as aluno_nome,
                NULL as aluno_telefone,
                NULL as veiculo_modelo,
                NULL as veiculo_placa
            FROM turma_aulas_agendadas taa
            JOIN turmas_teoricas tt ON taa.turma_id = tt.id
            LEFT JOIN salas s ON taa.sala_id = s.id
            WHERE taa.instrutor_id = ?
              AND taa.data_aula = ?
              AND taa.status != 'cancelada'
            ORDER BY taa.hora_inicio ASC
        ", [$instrutorId, $hoje]);
    } catch (Exception $e) {
        error_log('[DASHBOARD] Erro ao buscar aulas teóricas: ' . $e->getMessage());
        $aulasTeoricasHoje = [];
    }
    
    // Combinar aulas práticas e teóricas
    $aulasHoje = array_merge($aulasPraticasHoje, $aulasTeoricasHoje);
    
    // Ordenar por horário
    usort($aulasHoje, function($a, $b) {
        $horaA = $a['hora_inicio'] ?? '00:00:00';
        $horaB = $b['hora_inicio'] ?? '00:00:00';
        return strcmp($horaA, $horaB);
    });
    
    // Verificar chamada registrada para todas as aulas teóricas
    // E buscar categoria CNH da matrícula ativa para aulas práticas
    foreach ($aulasHoje as &$aula) {
        if ($aula['tipo_aula'] === 'teorica' && isset($aula['id'])) {
            try {
                $presencasCount = $db->fetch("
                    SELECT COUNT(*) as total
                    FROM turma_presencas
                    WHERE turma_aula_id = ? AND turma_id = ?
                ", [$aula['id'], $aula['turma_id'] ?? 0]);
                $aula['chamada_registrada'] = ($presencasCount['total'] ?? 0) > 0;
            } catch (Exception $e) {
                $aula['chamada_registrada'] = false;
            }
        } else {
            $aula['chamada_registrada'] = false; // Aulas práticas não têm chamada teórica
            
            // Buscar categoria CNH da matrícula ativa (prioridade)
            if (isset($aula['aluno_id'])) {
                try {
                    $matriculaAtiva = $db->fetch("
                        SELECT categoria_cnh, tipo_servico
                        FROM matriculas
                        WHERE aluno_id = ? AND status = 'ativa'
                        ORDER BY data_inicio DESC
                        LIMIT 1
                    ", [$aula['aluno_id']]);
                    
                    if ($matriculaAtiva && !empty($matriculaAtiva['categoria_cnh'])) {
                        $aula['aluno_categoria_cnh'] = $matriculaAtiva['categoria_cnh'];
                    }
                } catch (Exception $e) {
                    // Ignorar erro, usar categoria do aluno
                }
            }
        }
    }
    unset($aula); // Remover referência do último elemento
    
    // CORREÇÃO: Selecionar primeira aula pendente (status IN ('agendada','em_andamento')) ordenada por horário
    // Se não houver pendente, pode mostrar a última concluída SEM ações
    $aulasPendentes = array_filter($aulasHoje, function($aula) {
        if ($aula['tipo_aula'] === 'pratica') {
            return in_array($aula['status'] ?? '', ['agendada', 'em_andamento']);
        } else {
            // Teórica: pendente se não tem chamada registrada E status não é 'realizada'
            return !($aula['chamada_registrada'] ?? false) && ($aula['status'] ?? '') !== 'realizada';
        }
    });
    
    if (!empty($aulasPendentes)) {
        // Primeira pendente ordenada por horário
        $proximaAula = reset($aulasPendentes);
    } else {
        // Se não há pendentes, pode mostrar a última concluída apenas para visualização (sem ações)
        $aulasConcluidas = array_filter($aulasHoje, function($aula) {
            if ($aula['tipo_aula'] === 'pratica') {
                return ($aula['status'] ?? '') === 'concluida';
            } else {
                return ($aula['chamada_registrada'] ?? false) || ($aula['status'] ?? '') === 'realizada';
            }
        });
        $proximaAula = !empty($aulasConcluidas) ? end($aulasConcluidas) : null;
    }
}

// Buscar dados completos do aluno para a próxima aula (se for prática)
if ($proximaAula && $proximaAula['tipo_aula'] === 'pratica' && isset($proximaAula['aluno_id'])) {
    try {
        // Buscar categoria CNH da matrícula ativa (prioridade)
        $matriculaAtiva = $db->fetch("
            SELECT categoria_cnh, tipo_servico
            FROM matriculas
            WHERE aluno_id = ? AND status = 'ativa'
            ORDER BY data_inicio DESC
            LIMIT 1
        ", [$proximaAula['aluno_id']]);
        
        if ($matriculaAtiva && !empty($matriculaAtiva['categoria_cnh'])) {
            $proximaAula['aluno_categoria_cnh'] = $matriculaAtiva['categoria_cnh'];
        }
    } catch (Exception $e) {
        error_log('[DASHBOARD] Erro ao buscar matrícula ativa: ' . $e->getMessage());
    }
}

// Funções helper para formatação
function formatarCPF($cpf) {
    if (empty($cpf)) return 'Não informado';
    $cpfLimpo = preg_replace('/\D/', '', $cpf);
    if (strlen($cpfLimpo) === 11) {
        return substr($cpfLimpo, 0, 3) . '.' . substr($cpfLimpo, 3, 3) . '.' . substr($cpfLimpo, 6, 3) . '-' . substr($cpfLimpo, 9, 2);
    }
    return $cpf;
}

// Função para mascarar CPF (mostrar só últimos dígitos)
function mascararCPF($cpf) {
    if (empty($cpf)) return 'Não informado';
    $cpfLimpo = preg_replace('/\D/', '', $cpf);
    if (strlen($cpfLimpo) === 11) {
        return '***.***.***-' . substr($cpfLimpo, 9, 2);
    }
    return $cpf;
}

// Função para gerar iniciais do nome
function gerarIniciais($nome) {
    if (empty($nome)) return '?';
    $palavras = explode(' ', trim($nome));
    $iniciais = '';
    foreach ($palavras as $palavra) {
        if (!empty($palavra)) {
            $iniciais .= strtoupper(substr($palavra, 0, 1));
            if (strlen($iniciais) >= 2) break; // Máximo 2 iniciais
        }
    }
    return $iniciais ?: '?';
}

function formatarTelefone($telefone) {
    if (empty($telefone)) return 'Não informado';
    $telLimpo = preg_replace('/\D/', '', $telefone);
    if (strlen($telLimpo) === 11) {
        return '(' . substr($telLimpo, 0, 2) . ') ' . substr($telLimpo, 2, 5) . '-' . substr($telLimpo, 7, 4);
    } elseif (strlen($telLimpo) === 10) {
        return '(' . substr($telLimpo, 0, 2) . ') ' . substr($telLimpo, 2, 4) . '-' . substr($telLimpo, 6, 4);
    }
    return $telefone;
}

// FASE INSTRUTOR - AULAS TEORICAS - Buscar próximas aulas (próximos 7 dias) - práticas + teóricas
$proximasAulas = [];
$aulasPraticasProximas = [];
$aulasTeoricasProximas = [];

if ($instrutorId) {
    try {
        // Buscar aulas práticas dos próximos 7 dias
        $aulasPraticasProximas = $db->fetchAll("
            SELECT a.*, 
                   a.aluno_id,
                   al.nome as aluno_nome, 
                   al.telefone as aluno_telefone,
                   al.cpf as aluno_cpf,
                   al.foto as aluno_foto,
                   al.categoria_cnh as aluno_categoria_cnh,
                   al.email as aluno_email,
                   v.modelo as veiculo_modelo, v.placa as veiculo_placa,
                   'pratica' as tipo_aula
            FROM aulas a
            JOIN alunos al ON a.aluno_id = al.id
            LEFT JOIN veiculos v ON a.veiculo_id = v.id
            WHERE a.instrutor_id = ? 
              AND a.data_aula > ?
              AND a.data_aula <= DATE_ADD(?, INTERVAL 7 DAY)
              AND a.status != 'cancelada'
            ORDER BY a.data_aula ASC, a.hora_inicio ASC
            LIMIT 10
        ", [$instrutorId, $hoje, $hoje]);
    } catch (Exception $e) {
        error_log('[DASHBOARD] Erro ao buscar próximas aulas práticas: ' . $e->getMessage());
        $aulasPraticasProximas = [];
    }
    
    try {
        // FASE INSTRUTOR - AULAS TEORICAS - Buscar aulas teóricas dos próximos 7 dias
        $aulasTeoricasProximas = $db->fetchAll("
            SELECT 
                taa.id,
                taa.turma_id,
                taa.disciplina,
                taa.nome_aula,
                taa.data_aula,
                taa.hora_inicio,
                taa.hora_fim,
                taa.status,
                taa.observacoes,
                taa.sala_id,
                tt.nome as turma_nome,
                s.nome as sala_nome,
                'teorica' as tipo_aula,
                NULL as aluno_nome,
                NULL as aluno_telefone,
                NULL as veiculo_modelo,
                NULL as veiculo_placa
            FROM turma_aulas_agendadas taa
            JOIN turmas_teoricas tt ON taa.turma_id = tt.id
            LEFT JOIN salas s ON taa.sala_id = s.id
            WHERE taa.instrutor_id = ?
              AND taa.data_aula > ?
              AND taa.data_aula <= DATE_ADD(?, INTERVAL 7 DAY)
              AND taa.status != 'cancelada'
            ORDER BY taa.data_aula ASC, taa.hora_inicio ASC
            LIMIT 10
        ", [$instrutorId, $hoje, $hoje]);
    } catch (Exception $e) {
        error_log('[DASHBOARD] Erro ao buscar próximas aulas teóricas: ' . $e->getMessage());
        $aulasTeoricasProximas = [];
    }
    
    // Combinar aulas práticas e teóricas
    $proximasAulas = array_merge($aulasPraticasProximas, $aulasTeoricasProximas);
    
    // Ordenar por data e horário
    usort($proximasAulas, function($a, $b) {
        $dataA = $a['data_aula'] . ' ' . ($a['hora_inicio'] ?? '00:00:00');
        $dataB = $b['data_aula'] . ' ' . ($b['hora_inicio'] ?? '00:00:00');
        return strtotime($dataA) - strtotime($dataB);
    });
    
    // Limitar a 10 no total
    $proximasAulas = array_slice($proximasAulas, 0, 10);
}

// Buscar notificações não lidas
try {
    $notificacoesNaoLidas = $notificacoes->buscarNotificacoesNaoLidas($user['id'], 'instrutor');
} catch (Exception $e) {
    error_log('[DASHBOARD] Erro ao buscar notificações: ' . $e->getMessage());
    $notificacoesNaoLidas = [];
}

// FASE 1 - PRESENCA TEORICA - Buscar turmas teóricas do instrutor
// Arquivo: instrutor/dashboard.php (linha ~117)
// CORREÇÃO: turmas_teoricas não tem instrutor_id diretamente - o instrutor está em turma_aulas_agendadas
$turmasTeoricas = [];
$turmasTeoricasInstrutor = [];
if ($instrutorId) {
    try {
        // Buscar todas as turmas teóricas do instrutor (não apenas do dia)
        // O instrutor está associado às aulas agendadas, não diretamente à turma
        $turmasTeoricasInstrutor = $db->fetchAll("
            SELECT 
                tt.id,
                tt.nome,
                tt.curso_tipo,
                tt.data_inicio,
                tt.data_fim,
                tt.status,
                COUNT(DISTINCT tm.id) as total_alunos,
                COUNT(DISTINCT CASE WHEN taa.data_aula >= CURDATE() AND taa.status = 'agendada' THEN taa.id END) as proximas_aulas
            FROM turmas_teoricas tt
            INNER JOIN turma_aulas_agendadas taa_instrutor ON tt.id = taa_instrutor.turma_id 
                AND taa_instrutor.instrutor_id = ?
            LEFT JOIN turma_matriculas tm ON tt.id = tm.turma_id 
                AND tm.status IN ('matriculado', 'cursando', 'concluido')
            LEFT JOIN turma_aulas_agendadas taa ON tt.id = taa.turma_id
            WHERE tt.status IN ('ativa', 'completa', 'cursando', 'concluida')
            GROUP BY tt.id
            ORDER BY tt.data_inicio DESC, tt.nome ASC
            LIMIT 10
        ", [$instrutorId]);
        
        // Buscar próxima aula teórica de cada turma (para link rápido)
        foreach ($turmasTeoricasInstrutor as &$turma) {
            try {
                $proximaAulaTurma = $db->fetch("
                    SELECT id, data_aula, hora_inicio
                    FROM turma_aulas_agendadas
                    WHERE turma_id = ? 
                      AND data_aula >= CURDATE()
                      AND status = 'agendada'
                    ORDER BY data_aula ASC, hora_inicio ASC
                    LIMIT 1
                ", [$turma['id']]);
                $turma['proxima_aula'] = $proximaAulaTurma;
            } catch (Exception $e) {
                error_log('[DASHBOARD] Erro ao buscar próxima aula da turma ' . $turma['id'] . ': ' . $e->getMessage());
                $turma['proxima_aula'] = null;
            }
        }
        unset($turma);
    } catch (Exception $e) {
        error_log('[DASHBOARD] Erro ao buscar turmas teóricas: ' . $e->getMessage());
        $turmasTeoricasInstrutor = [];
    }
}

// FASE INSTRUTOR - AULAS TEORICAS - Estatísticas do dia (práticas + teóricas)
// CORREÇÃO: Calcular contadores baseado no array $aulasHoje para garantir consistência
$statsHoje = [
    'total_aulas' => 0,
    'pendentes' => 0,
    'concluidas' => 0
];

// CORREÇÃO: Contadores baseados APENAS nas aulas do array $aulasHoje (mesmo dataset da tabela)
if ($instrutorId && !empty($aulasHoje)) {
    $totalAulas = count($aulasHoje);
    $concluidas = 0;
    $pendentes = 0;
    
    foreach ($aulasHoje as $aula) {
        if ($aula['tipo_aula'] === 'pratica') {
            // Aula prática: regras simples baseadas apenas em status
            $status = $aula['status'] ?? '';
            if ($status === 'concluida') {
                $concluidas++;
            } elseif (in_array($status, ['agendada', 'em_andamento'])) {
                // NUNCA contar 'concluida' nem 'cancelada' como pendente
                $pendentes++;
            }
            // 'cancelada' não conta em nenhum dos dois
        } else {
            // Aula teórica: considerar status do banco
            $status = $aula['status'] ?? '';
            // Concluída se status = 'realizada' (independente de chamada_registrada)
            if ($status === 'realizada') {
                $concluidas++;
            } elseif ($status !== 'cancelada') {
                // Pendente: qualquer outro status que não seja 'realizada' nem 'cancelada'
                // (inclui 'agendada' e outros estados possíveis)
                $pendentes++;
            }
            // 'cancelada' não conta em nenhum dos dois
        }
    }
    
    $statsHoje = [
        'total_aulas' => $totalAulas,
        'pendentes' => $pendentes,
        'concluidas' => $concluidas
    ];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($instrutor['nome'] ?? 'Instrutor'); ?></title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/pwa/manifest.json">
    
    <!-- Meta tags PWA -->
    <meta name="theme-color" content="#10b981" id="theme-color-meta">
    <meta name="color-scheme" content="light dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default" id="apple-status-bar">
    <meta name="apple-mobile-web-app-title" content="CFC Instrutor">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="/pwa/icons/icon-192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/pwa/icons/icon-152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/pwa/icons/icon-192.png">
    
    <!-- Bootstrap 4 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    
    <!-- Theme Tokens (deve vir antes de mobile-first.css) -->
    <link rel="stylesheet" href="../assets/css/theme-tokens.css">
    
    <link rel="stylesheet" href="../assets/css/mobile-first.css">
    
    <!-- Theme Overrides Global (dark mode fixes) -->
    <link rel="stylesheet" href="../assets/css/theme-overrides.css?v=1.0.10">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Script para atualizar theme-color dinamicamente -->
    <script>
        (function() {
            function updateThemeColor() {
                const metaThemeColor = document.getElementById('theme-color-meta');
                const appleStatusBar = document.getElementById('apple-status-bar');
                
                if (!metaThemeColor) return;
                
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                
                if (prefersDark) {
                    metaThemeColor.setAttribute('content', '#1e293b');
                    if (appleStatusBar) {
                        appleStatusBar.setAttribute('content', 'black-translucent');
                    }
                } else {
                    metaThemeColor.setAttribute('content', '#10b981');
                    if (appleStatusBar) {
                        appleStatusBar.setAttribute('content', 'default');
                    }
                }
            }
            
            updateThemeColor();
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateThemeColor);
        })();
    </script>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>Minhas aulas</h1>
                <div class="subtitle">Gerencie suas aulas e turmas</div>
            </div>
            <!-- Dropdown de Usuário -->
            <div class="instrutor-profile-menu" style="position: relative;">
                <button class="instrutor-profile-button" id="instrutor-profile-button" style="background: transparent; border: none; padding: 4px 8px; color: white; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background-color 0.2s ease;">
                    <div class="instrutor-profile-avatar" style="width: 32px; height: 32px; border-radius: 50%; overflow: hidden; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 12px; position: relative; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.15);">
                        <?php if (!empty($instrutor['foto'])): ?>
                            <img src="../<?php echo htmlspecialchars($instrutor['foto']); ?>" 
                                 alt="Foto de <?php echo htmlspecialchars($instrutor['nome'] ?? ''); ?>" 
                                 style="width: 100%; height: 100%; object-fit: cover;"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; background: rgba(255,255,255,0.2); color: white;">
                                <?php 
                                $iniciais = strtoupper(substr($instrutor['nome'], 0, 1));
                                if (strpos($instrutor['nome'], ' ') !== false) {
                                    $nomes = explode(' ', $instrutor['nome']);
                                    $iniciais = strtoupper(substr($nomes[0], 0, 1) . substr(end($nomes), 0, 1));
                                }
                                echo htmlspecialchars($iniciais ?? 'IN');
                                ?>
                            </div>
                        <?php else: ?>
                            <?php 
                            $iniciais = strtoupper(substr($instrutor['nome'], 0, 1));
                            if (strpos($instrutor['nome'], ' ') !== false) {
                                $nomes = explode(' ', $instrutor['nome']);
                                $iniciais = strtoupper(substr($nomes[0], 0, 1) . substr(end($nomes), 0, 1));
                            }
                            echo htmlspecialchars($iniciais ?? 'IN');
                            ?>
                        <?php endif; ?>
                    </div>
                    <div class="instrutor-profile-info" style="display: flex; flex-direction: column; align-items: flex-start; text-align: left; min-width: 0;">
                        <span class="instrutor-profile-name" style="font-size: 14px; font-weight: 600; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100px; color: white;"><?php echo htmlspecialchars($instrutor['nome'] ?? 'Instrutor'); ?></span>
                        <span class="instrutor-profile-role" style="font-size: 12px; opacity: 0.9; line-height: 1.2; color: white;">Instrutor</span>
                    </div>
                    <i class="fas fa-chevron-down" style="font-size: 11px; margin-left: 4px; color: white; opacity: 0.9;"></i>
                </button>
                <div class="instrutor-profile-dropdown bg-theme-surface" id="instrutor-profile-dropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 8px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 200px; z-index: 1000; border: 1px solid var(--theme-border, #e2e8f0);">
                    <!-- Informações do usuário no dropdown -->
                    <div style="padding: 12px 16px; border-bottom: 1px solid #f0f0f0;">
                        <div style="font-weight: 600; color: #333; font-size: 14px; margin-bottom: 2px;"><?php echo htmlspecialchars($instrutor['nome'] ?? 'Instrutor'); ?></div>
                        <div style="font-size: 12px; color: #666;">Instrutor</div>
                    </div>
                    <a href="perfil.php" class="instrutor-dropdown-item" style="display: flex; align-items: center; padding: 12px 16px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0;">
                        <i class="fas fa-user" style="width: 20px; margin-right: 12px; color: #666;"></i>
                        Meu Perfil
                    </a>
                    <a href="trocar-senha.php" class="instrutor-dropdown-item" style="display: flex; align-items: center; padding: 12px 16px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0;">
                        <i class="fas fa-key" style="width: 20px; margin-right: 12px; color: #666;"></i>
                        Trocar senha
                    </a>
                    <a href="../admin/logout.php" class="instrutor-dropdown-item" style="display: flex; align-items: center; padding: 12px 16px; color: #e74c3c; text-decoration: none;">
                        <i class="fas fa-sign-out-alt" style="width: 20px; margin-right: 12px;"></i>
                        Sair
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Layout refinado: Container principal centralizado -->
    <div class="container my-4 instrutor-dashboard">
        <!-- PRIMEIRA SEÇÃO: Visão geral do dia (2 colunas) -->
        <div class="row mb-4">
            <!-- Coluna Esquerda: Próxima Aula -->
            <div class="col-12 col-lg-7 col-xl-6 mb-4 mb-lg-0">
                <?php if ($proximaAula): ?>
                <!-- Ajuste visual: Card Próxima Aula - hierarquia e espaçamentos -->
                <div class="card border-primary shadow-sm h-100">
                    <div class="card-header bg-primary text-white py-2 d-flex justify-content-between align-items-center">
                        <div class="flex-grow-1">
                            <div class="small mb-1" style="font-size: 0.65rem; opacity: 0.9;">PRÓXIMA AULA</div>
                            <strong style="font-size: 1.3rem; font-weight: 700; line-height: 1.2;"><?php echo date('H:i', strtotime($proximaAula['hora_inicio'])); ?>–<?php echo date('H:i', strtotime($proximaAula['hora_fim'])); ?></strong>
                        </div>
                        <span class="badge bg-<?php echo $proximaAula['tipo_aula'] === 'teorica' ? 'info' : 'success'; ?>" style="font-size: 0.75rem; opacity: 0.9; font-weight: 500;">
                            <?php echo strtoupper($proximaAula['tipo_aula']); ?>
                        </span>
                    </div>
                    <div class="card-body py-3">
                        <?php if ($proximaAula['tipo_aula'] === 'teorica'): ?>
                            <div class="mb-2">
                                <strong><?php echo htmlspecialchars((string)($proximaAula['turma_nome'] ?? '')); ?></strong>
                            </div>
                            <?php 
                            $disciplinas = [
                                'legislacao_transito' => 'Legislação de Trânsito',
                                'direcao_defensiva' => 'Direção Defensiva',
                                'primeiros_socorros' => 'Primeiros Socorros',
                                'meio_ambiente_cidadania' => 'Meio Ambiente e Cidadania',
                                'mecanica_basica' => 'Mecânica Básica'
                            ];
                            if (!empty($proximaAula['disciplina'])): ?>
                            <div class="mb-1 text-muted small">
                                <?php echo htmlspecialchars((string)($disciplinas[$proximaAula['disciplina'] ?? ''] ?? ucfirst(str_replace('_', ' ', (string)($proximaAula['disciplina'] ?? ''))))); ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($proximaAula['sala_nome'])): ?>
                            <!-- Ajuste visual: Espaçamento correto entre ícone e texto da Sala -->
                            <div class="mb-2 text-muted small">
                                <i class="fas fa-door-open mr-2"></i><?php echo htmlspecialchars((string)($proximaAula['sala_nome'] ?? '')); ?>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Informações do Aluno - Layout Otimizado Mobile -->
                            <div class="card-aluno-info mb-3">
                                <!-- Linha 1: Avatar + Nome (mesma linha, compacto) -->
                                <div class="aluno-linha-1 d-flex align-items-center mb-2">
                                    <!-- Avatar pequeno -->
                                    <div class="aluno-foto-wrapper">
                                        <?php if (!empty($proximaAula['aluno_foto'])): ?>
                                        <img src="../<?php echo htmlspecialchars($proximaAula['aluno_foto']); ?>" 
                                             alt="Foto de <?php echo htmlspecialchars($proximaAula['aluno_nome'] ?? ''); ?>" 
                                             class="aluno-foto" 
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="aluno-foto-placeholder" style="display: none;" data-iniciais="<?php echo htmlspecialchars(gerarIniciais($proximaAula['aluno_nome'] ?? '')); ?>">
                                            <span class="aluno-iniciais"><?php echo htmlspecialchars(gerarIniciais($proximaAula['aluno_nome'] ?? '')); ?></span>
                                        </div>
                                        <?php else: ?>
                                        <div class="aluno-foto-placeholder" data-iniciais="<?php echo htmlspecialchars(gerarIniciais($proximaAula['aluno_nome'] ?? '')); ?>">
                                            <span class="aluno-iniciais"><?php echo htmlspecialchars(gerarIniciais($proximaAula['aluno_nome'] ?? '')); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Nome -->
                                    <div class="aluno-nome-wrapper flex-grow-1">
                                        <strong class="aluno-nome-texto"><?php echo htmlspecialchars($proximaAula['aluno_nome'] ?? 'Aluno não informado'); ?></strong>
                                    </div>
                                </div>
                                
                                <!-- Linha 2: Comunicação (botões apenas, sem título/seletor) -->
                                <div class="aluno-linha-comunicacao mb-2">
                                    <!-- Botão principal: Mensagem (chat interno) -->
                                    <div class="comunicacao-acao-principal mb-1">
                                        <button type="button" 
                                                class="btn-comunicacao-mensagem" 
                                                onclick="alert('Chat interno em breve');"
                                                title="Abrir chat interno">
                                            <i class="fas fa-comment-dots"></i>
                                            <span>Mensagem</span>
                                        </button>
                                    </div>
                                    
                                    <!-- Botões secundários: WhatsApp e Ligar -->
                                    <?php if (!empty($proximaAula['aluno_telefone'])): ?>
                                    <div class="comunicacao-acoes-secundarias">
                                        <a href="https://wa.me/55<?php echo preg_replace('/\D/', '', $proximaAula['aluno_telefone']); ?>" 
                                           target="_blank" 
                                           class="btn-comunicacao-secundaria btn-comunicacao-whatsapp" 
                                           title="Abrir WhatsApp">
                                            <i class="fab fa-whatsapp"></i>
                                            <span>WhatsApp</span>
                                        </a>
                                        <a href="tel:<?php echo preg_replace('/\D/', '', $proximaAula['aluno_telefone']); ?>" 
                                           class="btn-comunicacao-secundaria btn-comunicacao-tel" 
                                           title="Ligar para <?php echo formatarTelefone($proximaAula['aluno_telefone']); ?>">
                                            <i class="fas fa-phone"></i>
                                            <span>Ligar</span>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Linha 3: Telefone (linha de dado, discreto) -->
                                <?php if (!empty($proximaAula['aluno_telefone'])): ?>
                                <div class="aluno-linha-3 mb-2">
                                    <i class="fas fa-phone text-muted"></i>
                                    <span class="aluno-label">Telefone:</span>
                                    <span class="aluno-valor-telefone"><?php echo formatarTelefone($proximaAula['aluno_telefone']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Linha 4: CNH -->
                                <?php if (!empty($proximaAula['aluno_categoria_cnh'])): ?>
                                <div class="aluno-linha-4 mb-2">
                                    <i class="fas fa-id-badge text-muted"></i>
                                    <span class="aluno-label">CNH:</span>
                                    <span class="badge bg-info aluno-badge-cnh"><?php echo htmlspecialchars($proximaAula['aluno_categoria_cnh']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Linha 5: CPF Mascarado (pequeno, opcional) -->
                                <?php if (!empty($proximaAula['aluno_cpf'])): ?>
                                <div class="aluno-linha-5 mb-2">
                                    <i class="fas fa-id-card text-muted"></i>
                                    <span class="aluno-label">CPF:</span>
                                    <span class="aluno-valor-cpf"><?php echo mascararCPF($proximaAula['aluno_cpf']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Linha 6: Dados da Aula/Veículo (separado) -->
                                <?php if (!empty($proximaAula['veiculo_modelo'])): ?>
                                <div class="aluno-linha-6 mt-3 pt-2 border-top">
                                    <i class="fas fa-car text-muted"></i>
                                    <span class="text-muted small"><?php echo htmlspecialchars($proximaAula['veiculo_modelo'] ?? ''); ?> - <?php echo htmlspecialchars($proximaAula['veiculo_placa'] ?? ''); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Estado de chamada -->
                        <div class="mb-3">
                            <?php if ($proximaAula['tipo_aula'] === 'teorica'): ?>
                                <?php if ($proximaAula['chamada_registrada'] ?? false): ?>
                                    <span class="badge bg-success-subtle text-success border border-success">
                                        <i class="fas fa-check-circle mr-1"></i>Chamada concluída
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning">
                                        <i class="fas fa-exclamation-circle mr-1"></i>Chamada pendente para esta aula
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo ucfirst($proximaAula['status'] ?? 'Agendada'); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Ajuste visual: Botões com largura mínima confortável e espaçamento -->
                        <?php 
                        // CORREÇÃO: Só mostrar botões se a aula não estiver concluída ou cancelada
                        $mostrarBotoes = true;
                        if ($proximaAula['tipo_aula'] === 'pratica') {
                            $mostrarBotoes = !in_array($proximaAula['status'] ?? '', ['concluida', 'cancelada']);
                        } else {
                            // Teórica: não mostrar botões se tem chamada registrada OU status = 'realizada'
                            $mostrarBotoes = !($proximaAula['chamada_registrada'] ?? false) && ($proximaAula['status'] ?? '') !== 'realizada';
                        }
                        ?>
                        <?php if ($mostrarBotoes): ?>
                        <div class="d-flex" style="gap: 0.5rem;">
                            <?php if ($proximaAula['tipo_aula'] === 'teorica'): ?>
                            <?php 
                            // Usar caminho relativo para evitar 404 em ambientes sem BASE_PATH
                            // Base do admin sem prefixo /instrutor
                            $baseAdmin = preg_replace('#/instrutor$#', '', (defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'))) . '/admin/index.php';
                            $turmaIdAula = (int)($proximaAula['turma_id'] ?? 0);
                            $aulaIdAula = (int)($proximaAula['id'] ?? 0);
                            $urlChamada = $baseAdmin . '?page=turma-chamada&turma_id=' . $turmaIdAula . '&aula_id=' . $aulaIdAula . '&origem=instrutor';
                            $urlDiario = $baseAdmin . '?page=turma-diario&turma_id=' . $turmaIdAula . '&aula_id=' . $aulaIdAula . '&origem=instrutor';
                            ?>
                            <a href="<?php echo htmlspecialchars($urlChamada); ?>" class="btn btn-primary flex-fill" style="min-height: 44px;">
                                <i class="fas fa-clipboard-list mr-2"></i>Chamada
                            </a>
                            <a href="<?php echo htmlspecialchars($urlDiario); ?>" class="btn btn-secondary flex-fill" style="min-height: 44px;">
                                <i class="fas fa-book mr-2"></i>Diário
                            </a>
                            <?php else: ?>
                            <button class="btn btn-warning flex-fill solicitar-transferencia" 
                                    data-aula-id="<?php echo $proximaAula['id']; ?>"
                                    data-data="<?php echo $proximaAula['data_aula']; ?>"
                                    data-hora="<?php echo $proximaAula['hora_inicio']; ?>">
                                <i class="fas fa-exchange-alt mr-2"></i>Transferir
                            </button>
                            <button class="btn btn-danger flex-fill cancelar-aula" 
                                    data-aula-id="<?php echo $proximaAula['id']; ?>"
                                    data-data="<?php echo $proximaAula['data_aula']; ?>"
                                    data-hora="<?php echo $proximaAula['hora_inicio']; ?>">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <!-- Status já aparece no badge acima, não precisa de caixa redundante -->
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="card h-100">
                    <div class="card-body text-center py-4">
                        <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                        <p class="text-muted mb-2">Nenhuma aula pendente hoje</p>
                        <a href="aulas.php" class="btn btn-sm btn-outline-primary">Ver todas as aulas</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Coluna Direita: Resumo + Pendências -->
            <div class="col-12 col-lg-5 col-xl-6">
                <!-- RESUMO DE HOJE - NOVO LAYOUT (Bootstrap 4) -->
                <div class="card mb-4 instrutor-resumo-hoje">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-chart-bar mr-2"></i>Resumo de Hoje</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Aulas hoje -->
                            <div class="col-12 col-lg-4 mb-3 mb-lg-0">
                                <div class="resumo-card card h-100 text-center py-3">
                                    <i class="fas fa-calendar-alt text-primary mb-2 resumo-icon"></i>
                                    <div class="resumo-valor text-primary">
                                        <?php echo $statsHoje['total_aulas']; ?>
                                    </div>
                                    <div class="resumo-label">Aulas hoje</div>
                                </div>
                            </div>
                            <!-- Pendentes -->
                            <div class="col-12 col-lg-4 mb-3 mb-lg-0">
                                <div class="resumo-card card h-100 text-center py-3">
                                    <i class="fas fa-exclamation-circle text-warning mb-2 resumo-icon"></i>
                                    <div class="resumo-valor text-warning">
                                        <?php echo $statsHoje['pendentes']; ?>
                                    </div>
                                    <div class="resumo-label">Pendentes</div>
                                </div>
                            </div>
                            <!-- Concluídas -->
                            <div class="col-12 col-lg-4">
                                <div class="resumo-card card h-100 text-center py-3">
                                    <i class="fas fa-check-circle text-success mb-2 resumo-icon"></i>
                                    <div class="resumo-valor text-success">
                                        <?php echo $statsHoje['concluidas']; ?>
                                    </div>
                                    <div class="resumo-label">Concluídas</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pendências: linha compacta dentro do Resumo (só se não houver pendências) -->
                        <?php 
                        // TODO: Implementar lógica para contar aulas anteriores sem chamada
                        $aulasSemChamada = 0; // Placeholder
                        ?>
                        <?php if ($aulasSemChamada === 0 && $statsHoje['pendentes'] === 0): ?>
                        <div class="resumo-pendencias-ok" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                <i class="fas fa-check-circle text-success" style="font-size: 0.85rem;"></i>
                                <span class="text-muted small mb-0" style="font-size: 0.8rem;">Todas as chamadas em dia</span>
                            </div>
                        </div>
                        <?php elseif ($statsHoje['pendentes'] > 0): ?>
                        <div class="resumo-pendencias-alert" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
                            <div class="alert alert-warning py-2 mb-0" style="font-size: 0.85rem;">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <strong><?php echo $statsHoje['pendentes']; ?></strong> aula(s) pendente(s) hoje
                                <a href="#aulas-hoje" class="ml-2 small">Ver lista</a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- FIM RESUMO DE HOJE -->
            </div>
        </div>
        
        <!-- ACOES RAPIDAS - COMPACTO (Mobile-first) -->
        <div class="card mb-4 instrutor-acoes-rapidas">
            <div class="card-header d-flex justify-content-center align-items-center text-center py-2">
                <i class="fas fa-bolt mr-2"></i>
                <span style="font-size: 0.9rem;">Ações Rápidas</span>
            </div>
            <div class="card-body py-2">
                <!-- Grid 2 colunas no mobile, 3 colunas no desktop - 3 botões padronizados -->
                <div class="acoes-rapidas-grid-padronizado">
                    <button class="btn btn-secondary btn-acao-rapida-padronizado" onclick="verNotificacoes()">
                        <i class="fas fa-bell"></i>
                        <span>Avisos</span>
                    </button>
                    <button class="btn btn-secondary btn-acao-rapida-padronizado" onclick="registrarOcorrencia()">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Registrar Ocorrência</span>
                    </button>
                    <button class="btn btn-secondary btn-acao-rapida-padronizado" onclick="contatarSecretaria()">
                        <i class="fas fa-phone"></i>
                        <span>Contatar Secretaria</span>
                    </button>
                </div>
            </div>
        </div>
        <!-- FIM ACOES RAPIDAS -->

        <!-- AULAS DE HOJE - NOVO LAYOUT -->
        <div class="card mb-4 dashboard-aulas-hoje">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-calendar-day mr-2"></i>Aulas de Hoje
                </h5>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="text-muted small">
                        <?php echo count($aulasHoje); ?> aula(s) agendada(s) hoje
                    </span>
                    <a href="aulas.php?periodo=hoje" class="btn btn-sm btn-outline-primary" style="padding: 4px 12px; font-size: 12px;">
                        Ver todas
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($aulasHoje)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                    <p class="text-muted mb-0">Você não possui aulas agendadas para hoje.</p>
                </div>
                <?php else: ?>
                <!-- Lista Mobile (esconder no desktop) -->
                <div class="aulas-hoje-list-mobile">
                    <?php 
                    $disciplinas = [
                        'legislacao_transito' => 'Legislação de Trânsito',
                        'direcao_defensiva' => 'Direção Defensiva',
                        'primeiros_socorros' => 'Primeiros Socorros',
                        'meio_ambiente_cidadania' => 'Meio Ambiente e Cidadania',
                        'mecanica_basica' => 'Mecânica Básica'
                    ];
                    foreach ($aulasHoje as $aula):
                        // Usar chamada_registrada já calculada no array (evita query duplicada)
                        $chamadaRegistrada = $aula['chamada_registrada'] ?? false;
                        
                        $baseAdmin = preg_replace('#/instrutor$#', '', (defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'))) . '/admin/index.php';
                        $turmaIdAula = (int)($aula['turma_id'] ?? 0);
                        $aulaIdAula = (int)($aula['id'] ?? 0);
                        $urlChamada = $baseAdmin . '?page=turma-chamada&turma_id=' . $turmaIdAula . '&aula_id=' . $aulaIdAula . '&origem=instrutor';
                        $urlDiario = $baseAdmin . '?page=turma-diario&turma_id=' . $turmaIdAula . '&aula_id=' . $aulaIdAula . '&origem=instrutor';
                        $statusAula = $aula['status'] ?? 'agendada';
                    ?>
                    <!-- Card padronizado de aula (hierarquia fixa - nunca estoura) -->
                    <div class="aula-item-mobile aula-card-padronizado bg-theme-card <?php echo $chamadaRegistrada ? 'aula-concluida' : ''; ?>" style="border: 1px solid var(--theme-border, #e2e8f0); border-radius: 8px; padding: 12px; margin-bottom: 12px; width: 100%; max-width: 100%; overflow: hidden; box-sizing: border-box;">
                        <!-- Linha 1: Hora + pill TEOR/PRAT + Status (flex-wrap para nunca estourar) -->
                        <div class="aula-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 6px;">
                            <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0;">
                                <strong class="text-theme" style="font-size: 15px; font-weight: 600; white-space: nowrap; flex-shrink: 0;"><?php echo date('H:i', strtotime($aula['hora_inicio'])); ?>–<?php echo date('H:i', strtotime($aula['hora_fim'])); ?></strong>
                                <span class="badge" style="background: <?php echo $aula['tipo_aula'] === 'teorica' ? '#3b82f6' : '#10b981'; ?>; color: white; font-size: 10px; padding: 4px 8px; font-weight: 600; white-space: nowrap; flex-shrink: 0;">
                                    <?php echo $aula['tipo_aula'] === 'teorica' ? 'TEOR' : 'PRAT'; ?>
                                </span>
                            </div>
                            <?php if ($aula['tipo_aula'] === 'teorica'): ?>
                                <span class="badge" style="font-size: 10px; padding: 4px 8px; font-weight: 500; background: <?php echo $chamadaRegistrada ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $chamadaRegistrada ? '#065f46' : '#92400e'; ?>; white-space: nowrap; flex-shrink: 0;">
                                    <?php echo $chamadaRegistrada ? 'CONCLUÍDA' : 'PENDENTE'; ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary" style="font-size: 10px; padding: 4px 8px; font-weight: 500; white-space: nowrap; flex-shrink: 0;">
                                    <?php echo strtoupper($statusAula); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Linha 2: Título principal (Disciplina ou Nome do aluno) -->
                        <div style="margin-bottom: 8px;">
                            <?php if ($aula['tipo_aula'] === 'teorica'): ?>
                                <div class="text-theme" style="font-weight: 600; font-size: 15px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars((string)($disciplinas[$aula['disciplina'] ?? ''] ?? ucfirst(str_replace('_', ' ', (string)($aula['disciplina'] ?? '')) ?? 'Disciplina'))); ?>
                                </div>
                            <?php else: ?>
                                <div class="text-theme" style="font-weight: 600; font-size: 15px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars($aula['aluno_nome'] ?? 'Aluno não informado'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Linha 3: Subinfo curta (Turma/Categoria/Veículo) -->
                        <div style="margin-bottom: 10px;">
                            <?php if ($aula['tipo_aula'] === 'teorica'): ?>
                                <div class="text-theme-muted" style="font-size: 12px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars((string)($aula['turma_nome'] ?? '')); ?>
                                    <?php if (!empty($aula['sala_nome'])): ?>
                                        · <?php echo htmlspecialchars((string)$aula['sala_nome']); ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <?php 
                                $subinfo = [];
                                if (!empty($aula['aluno_categoria_cnh'])) {
                                    $subinfo[] = 'CNH ' . htmlspecialchars($aula['aluno_categoria_cnh']);
                                }
                                if (!empty($aula['veiculo_modelo'])) {
                                    $subinfo[] = htmlspecialchars($aula['veiculo_modelo']);
                                }
                                ?>
                                <?php if (!empty($subinfo)): ?>
                                <div class="text-theme-muted" style="font-size: 12px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo implode(' · ', $subinfo); ?>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Linha 4: Ações (botões compactos e consistentes - PADRÃO ÚNICO) -->
                        <div style="display: flex; gap: 6px; flex-wrap: nowrap;">
                            <?php if ($aula['tipo_aula'] === 'teorica'): ?>
                            <!-- Ações para aula teórica: sempre Chamada + Diário -->
                            <a href="<?php echo htmlspecialchars($urlChamada); ?>" 
                               class="btn btn-primary btn-sm aula-acao-btn" 
                               style="padding: 8px 12px; font-size: 12px; flex: 1; min-width: 0; white-space: nowrap; height: 36px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-clipboard-list mr-1"></i> Chamada
                            </a>
                            <a href="<?php echo htmlspecialchars($urlDiario); ?>" 
                               class="btn btn-secondary btn-sm aula-acao-btn" 
                               style="padding: 8px 12px; font-size: 12px; flex: 1; min-width: 0; white-space: nowrap; height: 36px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-book mr-1"></i> Diário
                            </a>
                            <?php else: ?>
                            <!-- Ações para aula prática: padronizar estrutura (sempre 2 botões principais) -->
                            <?php if ($statusAula === 'agendada'): ?>
                            <button class="btn btn-success btn-sm iniciar-aula aula-acao-btn" 
                                    style="padding: 8px 12px; font-size: 12px; flex: 1; min-width: 0; white-space: nowrap; height: 36px; display: flex; align-items: center; justify-content: center;"
                                    data-aula-id="<?php echo $aula['id']; ?>">
                                <i class="fas fa-play mr-1"></i> Iniciar
                            </button>
                            <!-- Segundo botão: Transferir (sempre presente para manter consistência) -->
                            <button class="btn btn-warning btn-sm solicitar-transferencia aula-acao-btn" 
                                    style="padding: 8px 12px; font-size: 12px; flex: 1; min-width: 0; white-space: nowrap; height: 36px; display: flex; align-items: center; justify-content: center;"
                                    data-aula-id="<?php echo $aula['id']; ?>"
                                    data-data="<?php echo $aula['data_aula']; ?>"
                                    data-hora="<?php echo $aula['hora_inicio']; ?>">
                                <i class="fas fa-exchange-alt mr-1"></i> Transferir
                            </button>
                            <?php elseif ($statusAula === 'em_andamento'): ?>
                            <button class="btn btn-primary btn-sm finalizar-aula aula-acao-btn" 
                                    style="padding: 8px 12px; font-size: 12px; flex: 1; min-width: 0; white-space: nowrap; height: 36px; display: flex; align-items: center; justify-content: center;"
                                    data-aula-id="<?php echo $aula['id']; ?>">
                                <i class="fas fa-stop mr-1"></i> Finalizar
                            </button>
                            <!-- Segundo botão: placeholder invisível para manter consistência visual -->
                            <div style="flex: 1; min-width: 0;"></div>
                            <?php else: ?>
                            <!-- Status concluída/cancelada: manter estrutura mas sem ações -->
                            <div style="flex: 1; min-width: 0;"></div>
                            <div style="flex: 1; min-width: 0;"></div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Tabela Desktop (esconder no mobile) -->
                <div class="table-responsive aulas-hoje-table-desktop">
                    <table class="table table-hover align-middle mb-0 instructor-aulas-table">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap" style="width: 100px;">Hora</th>
                                <th class="text-nowrap" style="width: 70px;">Tipo</th>
                                <th style="width: 50%;">Disciplina / Turma</th>
                                <th class="text-nowrap" style="width: 100px;">Sala</th>
                                <th class="text-nowrap" style="width: 100px;">Status</th>
                                <th style="width: 100px;" class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                                <?php 
                                foreach ($aulasHoje as $aula): 
                                // Usar chamada_registrada já calculada no array (evita query duplicada)
                                $chamadaRegistrada = $aula['chamada_registrada'] ?? false;
                                
                                $baseAdmin = preg_replace('#/instrutor$#', '', (defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'))) . '/admin/index.php';
                                $turmaIdAula = (int)($aula['turma_id'] ?? 0);
                                $aulaIdAula = (int)($aula['id'] ?? 0);
                                $urlChamada = $baseAdmin . '?page=turma-chamada&turma_id=' . $turmaIdAula . '&aula_id=' . $aulaIdAula . '&origem=instrutor';
                                $urlDiario = $baseAdmin . '?page=turma-diario&turma_id=' . $turmaIdAula . '&aula_id=' . $aulaIdAula . '&origem=instrutor';
                                ?>
                                <!-- Ajuste visual: Hierarquia tipográfica da tabela de aulas de hoje -->
                                <tr class="<?php echo $chamadaRegistrada ? 'table-success' : ''; ?>">
                                    <td class="text-nowrap py-3">
                                        <strong style="font-size: 0.95rem; font-weight: 600;"><?php echo date('H:i', strtotime($aula['hora_inicio'])); ?> – <?php echo date('H:i', strtotime($aula['hora_fim'])); ?></strong>
                                    </td>
                                    <td class="text-nowrap py-3">
                                        <span class="badge bg-light text-dark badge-pill">
                                            <?php echo $aula['tipo_aula'] === 'teorica' ? 'TEOR' : 'PRAT'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($aula['tipo_aula'] === 'teorica'): ?>
                                            <div class="fw-bold" style="font-size: 0.875rem; line-height: 1.3;">
                                                <?php echo htmlspecialchars((string)($disciplinas[$aula['disciplina'] ?? ''] ?? ucfirst(str_replace('_', ' ', (string)($aula['disciplina'] ?? '')) ?? 'Disciplina'))); ?>
                                            </div>
                                            <div class="text-muted small" style="font-size: 0.75rem; line-height: 1.2;">
                                                <?php echo htmlspecialchars((string)($aula['turma_nome'] ?? '')); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex align-items-center">
                                                <!-- Foto pequena -->
                                                <div class="mr-2 flex-shrink-0">
                                                    <?php if (!empty($aula['aluno_foto'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($aula['aluno_foto']); ?>" 
                                                         alt="Foto de <?php echo htmlspecialchars($aula['aluno_nome'] ?? ''); ?>" 
                                                         class="rounded-circle aluno-foto-tabela" 
                                                         style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #dee2e6;"
                                                         onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center aluno-foto-placeholder-tabela" 
                                                         style="width: 40px; height: 40px; border: 2px solid #dee2e6; display: none;">
                                                        <i class="fas fa-user text-white"></i>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" 
                                                         style="width: 40px; height: 40px; border: 2px solid #dee2e6;">
                                                        <i class="fas fa-user text-white"></i>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Nome e informações -->
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold" style="font-size: 0.875rem; line-height: 1.3;">
                                                        <?php echo htmlspecialchars($aula['aluno_nome'] ?? 'Aluno não informado'); ?>
                                                    </div>
                                                    <?php if (!empty($aula['aluno_telefone'])): ?>
                                                    <div class="text-muted" style="font-size: 0.7rem;">
                                                        <i class="fas fa-phone mr-1"></i>
                                                        <a href="tel:<?php echo preg_replace('/\D/', '', $aula['aluno_telefone']); ?>" 
                                                           class="text-muted text-decoration-none">
                                                            <?php echo formatarTelefone($aula['aluno_telefone']); ?>
                                                        </a>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($aula['veiculo_modelo'])): ?>
                                                    <div class="text-muted small" style="font-size: 0.7rem; line-height: 1.2;">
                                                        <i class="fas fa-car mr-1"></i><?php echo htmlspecialchars($aula['veiculo_modelo'] ?? ''); ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($aula['tipo_aula'] === 'pratica'): ?>
                                                <?php if ($aula['status'] === 'em_andamento' && isset($aula['km_inicial']) && $aula['km_inicial'] !== null): ?>
                                                <div class="text-muted small" style="font-size: 0.7rem; line-height: 1.2; margin-top: 2px;">
                                                    KM inicial: <?php echo number_format($aula['km_inicial'], 0, ',', '.'); ?>
                                                </div>
                                                <?php elseif ($aula['status'] === 'concluida' && isset($aula['km_inicial']) && $aula['km_inicial'] !== null && isset($aula['km_final']) && $aula['km_final'] !== null): ?>
                                                <?php $kmRodados = $aula['km_final'] - $aula['km_inicial']; ?>
                                                <div class="text-muted small" style="font-size: 0.7rem; line-height: 1.2; margin-top: 2px;">
                                                    KM: <?php echo number_format($aula['km_inicial'], 0, ',', '.'); ?> → <?php echo number_format($aula['km_final'], 0, ',', '.'); ?> (<?php echo $kmRodados >= 0 ? '+' : ''; ?><?php echo number_format($kmRodados, 0, ',', '.'); ?>)
                                                </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($aula['tipo_aula'] === 'teorica'): ?>
                                            <span class="small" style="font-size: 0.8rem;"><?php echo htmlspecialchars((string)($aula['sala_nome'] ?? '-')); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small" style="font-size: 0.8rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($aula['tipo_aula'] === 'teorica'): ?>
                                            <span class="badge <?php echo $chamadaRegistrada ? 'bg-success-subtle text-success border border-success' : 'bg-warning-subtle text-warning border border-warning'; ?>" style="font-size: 0.7rem; padding: 0.3rem 0.5rem; font-weight: 500;">
                                                <?php echo $chamadaRegistrada ? 'CONCLUÍDA' : 'PENDENTE'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.7rem; padding: 0.3rem 0.5rem; font-weight: 500;">
                                                <?php echo strtoupper($aula['status'] ?? 'AGENDADA'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($aula['tipo_aula'] === 'teorica'): ?>
                                            <a href="<?php echo htmlspecialchars($urlChamada); ?>" 
                                               class="btn btn-primary btn-sm" 
                                               style="padding: 0.35rem 0.6rem; min-width: 38px;"
                                               title="Abrir chamada"
                                               data-bs-toggle="tooltip">
                                                <i class="fas fa-clipboard-list"></i>
                                            </a>
                                            <a href="<?php echo htmlspecialchars($urlDiario); ?>" 
                                               class="btn btn-secondary btn-sm" 
                                               style="padding: 0.35rem 0.6rem; min-width: 38px;"
                                               title="Abrir diário"
                                               data-bs-toggle="tooltip">
                                                <i class="fas fa-book"></i>
                                            </a>
                                            <?php else: ?>
                                            <?php 
                                            // TAREFA 2.2 - Adicionar botões de iniciar/finalizar aula
                                            $statusAula = $aula['status'] ?? 'agendada';
                                            ?>
                                            <?php if ($statusAula === 'agendada'): ?>
                                            <button class="btn btn-success btn-sm iniciar-aula" 
                                                    style="padding: 0.35rem 0.6rem; min-width: 38px;"
                                                    data-aula-id="<?php echo $aula['id']; ?>"
                                                    title="Iniciar aula"
                                                    data-bs-toggle="tooltip">
                                                <i class="fas fa-play"></i>
                                            </button>
                                            <?php elseif ($statusAula === 'em_andamento'): ?>
                                            <button class="btn btn-primary btn-sm finalizar-aula" 
                                                    style="padding: 0.35rem 0.6rem; min-width: 38px;"
                                                    data-aula-id="<?php echo $aula['id']; ?>"
                                                    title="Finalizar aula"
                                                    data-bs-toggle="tooltip">
                                                <i class="fas fa-stop"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($statusAula === 'agendada'): ?>
                                            <button class="btn btn-warning btn-sm solicitar-transferencia" 
                                                    style="padding: 0.35rem 0.6rem; min-width: 38px;"
                                                    data-aula-id="<?php echo $aula['id']; ?>"
                                                    data-data="<?php echo $aula['data_aula']; ?>"
                                                    data-hora="<?php echo $aula['hora_inicio']; ?>"
                                                    title="Transferir"
                                                    data-bs-toggle="tooltip">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm cancelar-aula" 
                                                    style="padding: 0.35rem 0.6rem; min-width: 38px;"
                                                    data-aula-id="<?php echo $aula['id']; ?>"
                                                    data-data="<?php echo $aula['data_aula']; ?>"
                                                    data-hora="<?php echo $aula['hora_inicio']; ?>"
                                                    title="Cancelar"
                                                    data-bs-toggle="tooltip">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                    </div>
                </div>
        
        <!-- QUARTA SEÇÃO: Avisos (condicional - só mostrar se houver avisos) -->
        <?php if (!empty($notificacoesNaoLidas)): ?>
        <div class="card mb-4">
            <div class="card-header py-2">
                <h6 class="card-title mb-0" style="font-size: 0.9rem; font-weight: 600;">
                    <i class="fas fa-bell mr-2"></i>Avisos
                </h6>
            </div>
            <div class="card-body py-2">
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($notificacoesNaoLidas, 0, 3) as $notificacao): ?>
                    <div class="list-group-item px-0 py-2 border-0 border-bottom">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1" style="font-size: 0.9rem;">
                                <?php echo htmlspecialchars((string)($notificacao['titulo'] ?? '')); ?>
                            </h6>
                        </div>
                        <p class="mb-1 small text-muted" style="font-size: 0.8rem;">
                            <?php echo htmlspecialchars((string)($notificacao['mensagem'] ?? '')); ?>
                        </p>
                        <small class="text-muted">
                            <?php echo date('d/m/Y H:i', strtotime($notificacao['criado_em'])); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- FASE 1 - PRESENCA TEORICA - Minhas Turmas Teóricas -->
        <?php if (!empty($turmasTeoricasInstrutor)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-users-class"></i>
                    Minhas Turmas Teóricas
                </h2>
            </div>
            <div class="turma-list">
                <?php foreach ($turmasTeoricasInstrutor as $turma): ?>
                    <?php 
                    $statusLabel = [
                        'ativa' => 'Ativa',
                        'completa' => 'Completa',
                        'cursando' => 'Cursando',
                        'concluida' => 'Concluída',
                        'cancelada' => 'Cancelada'
                    ][$turma['status']] ?? ucfirst($turma['status']);
                    
                    $statusClass = [
                        'ativa' => 'success',
                        'completa' => 'info',
                        'cursando' => 'primary',
                        'concluida' => 'secondary',
                        'cancelada' => 'danger'
                    ][$turma['status']] ?? 'secondary';
                    
                    $nomesCursos = [
                        'formacao_45h' => 'Formação 45h',
                        'formacao_acc_20h' => 'Formação ACC 20h',
                        'reciclagem_infrator' => 'Reciclagem Infrator',
                        'atualizacao' => 'Atualização'
                    ];
                    ?>
                    <div class="turma-item" style="padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <h6 style="margin: 0 0 8px 0; font-weight: 600; color: #1e293b;">
                                    <?php echo htmlspecialchars((string)($turma['nome'] ?? '')); ?>
                                </h6>
                                <div class="text-theme-muted" style="font-size: 13px; margin-bottom: 4px;">
                                    <i class="fas fa-graduation-cap mr-1"></i>
                                    <?php echo htmlspecialchars((string)($nomesCursos[$turma['curso_tipo'] ?? ''] ?? $turma['curso_tipo'] ?? '')); ?>
                                </div>
                                <div class="text-theme-muted" style="font-size: 13px; margin-bottom: 4px;">
                                    <i class="fas fa-calendar mr-1"></i>
                                    <?php echo date('d/m/Y', strtotime($turma['data_inicio'])); ?> - 
                                    <?php echo date('d/m/Y', strtotime($turma['data_fim'])); ?>
                                </div>
                                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                                    <i class="fas fa-users mr-1"></i>
                                    <?php echo $turma['total_alunos']; ?> aluno(s)
                                </div>
                            </div>
                            <div style="text-align: right; margin-left: 16px;">
                                <span class="badge bg-<?php echo $statusClass; ?>" style="margin-bottom: 8px; display: block;">
                                    <?php echo $statusLabel; ?>
                                </span>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <a href="../admin/index.php?page=turmas-teoricas&acao=detalhes&turma_id=<?php echo $turma['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       style="text-decoration: none; font-size: 11px; padding: 4px 8px;">
                                        <i class="fas fa-eye mr-1"></i>
                                        Detalhes
                                    </a>
                                    <?php if ($turma['proxima_aula']): ?>
                                    <?php 
                                    // AJUSTE INSTRUTOR - FLUXO CHAMADA/DIARIO - Link para chamada da próxima aula
                                    $baseAdmin = preg_replace('#/instrutor$#', '', (defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'))) . '/admin/index.php';
                                    $turmaIdTurma = (int)($turma['id'] ?? 0);
                                    $aulaIdTurma = (int)($turma['proxima_aula']['id'] ?? 0);
                                    $urlChamadaTurma = $baseAdmin . '?page=turma-chamada&turma_id=' . $turmaIdTurma . '&aula_id=' . $aulaIdTurma . '&origem=instrutor';
                                    ?>
                                    <a href="<?php echo htmlspecialchars($urlChamadaTurma); ?>" 
                                       class="btn btn-sm btn-primary" 
                                       style="text-decoration: none; font-size: 11px; padding: 4px 8px;">
                                        <i class="fas fa-clipboard-check mr-1"></i>
                                        Chamada
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- QUINTA SEÇÃO: Próximas Aulas (7 dias) - Condicional (só renderizar se houver aulas) -->
        <?php if (!empty($proximasAulas)): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <h6 class="card-title mb-0" style="font-size: 0.9rem; font-weight: 600;">
                    <i class="fas fa-calendar-alt mr-2"></i>Próximas Aulas (7 dias)
                </h6>
                <a href="aulas.php?periodo=proximos_7_dias" class="btn btn-sm btn-outline-primary">
                    Ver todas
                </a>
            </div>
            <div class="card-body py-2">
                <?php 
                // REFACTOR DASHBOARD INSTRUTOR - Agrupar próximas aulas por data (limitar a 2-3 dias)
                $aulasPorData = [];
                foreach ($proximasAulas as $aula) {
                    $data = $aula['data_aula'];
                    if (!isset($aulasPorData[$data])) {
                        $aulasPorData[$data] = [];
                    }
                    $aulasPorData[$data][] = $aula;
                }
                // Limitar a 2-3 primeiras datas
                $aulasPorData = array_slice($aulasPorData, 0, 3, true);
                ?>
                <!-- Ajuste visual: Próximas Aulas - cada dia em card com borda leve e espaçamento melhorado -->
                <div class="d-flex flex-column proximas-aulas-list">
                    <?php foreach ($aulasPorData as $data => $aulasDoDia): ?>
                    <div class="card border proximas-aulas-dia-card">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong style="font-size: 0.9rem; font-weight: 600;"><?php echo date('d/m/Y', strtotime($data)); ?></strong>
                                <span class="text-muted small" style="font-size: 0.75rem;">· <?php echo count($aulasDoDia); ?> aula(s)</span>
                            </div>
                            <div class="ml-2">
                                <?php foreach (array_slice($aulasDoDia, 0, 2) as $aula): ?>
                                <div class="d-flex align-items-center mb-2 proximas-aulas-item" style="gap: 0.5rem;">
                                    <span class="badge bg-<?php echo $aula['tipo_aula'] === 'teorica' ? 'info' : 'success'; ?>" style="font-size: 0.7rem; padding: 0.2rem 0.4rem;">
                                        <?php echo $aula['tipo_aula'] === 'teorica' ? 'TEOR' : 'PRAT'; ?>
                                    </span>
                                    <small style="font-size: 0.85rem;">
                                        <strong><?php echo date('H:i', strtotime($aula['hora_inicio'])); ?></strong>
                                        <?php if ($aula['tipo_aula'] === 'teorica'): ?>
                                            - <?php echo htmlspecialchars((string)($aula['turma_nome'] ?? '')); ?>
                                        <?php else: ?>
                                            - <?php echo htmlspecialchars($aula['aluno_nome'] ?? 'Aluno'); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <?php endforeach; ?>
                                <?php if (count($aulasDoDia) > 2): ?>
                                <div class="mt-2 proximas-aulas-extra">
                                    <small class="text-muted fst-italic" style="font-size: 0.75rem;">+<?php echo count($aulasDoDia) - 2; ?> aula(s) neste dia</small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Cancelamento/Transferência -->
    <div id="modalAcao" class="modal-overlay hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitulo">Cancelar Aula</h3>
            </div>
            <div class="modal-body">
                <form id="formAcao">
                    <input type="hidden" id="aulaId" name="aula_id">
                    <input type="hidden" id="tipoAcao" name="tipo_acao">
                    
                    <div class="form-group">
                        <label class="form-label">Data da Aula</label>
                        <input type="text" id="dataAula" class="form-input" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Horário</label>
                        <input type="text" id="horaAula" class="form-input" readonly>
                    </div>
                    
                    <div class="form-group" id="novaDataGroup" style="display: none;">
                        <label class="form-label">Nova Data</label>
                        <input type="date" id="novaData" name="nova_data" class="form-input">
                    </div>
                    
                    <div class="form-group" id="novaHoraGroup" style="display: none;">
                        <label class="form-label">Novo Horário</label>
                        <input type="time" id="novaHora" name="nova_hora" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Motivo</label>
                        <select id="motivo" name="motivo" class="form-select">
                            <option value="">Selecione um motivo</option>
                            <option value="problema_saude">Problema de saúde</option>
                            <option value="imprevisto_pessoal">Imprevisto pessoal</option>
                            <option value="problema_veiculo">Problema com veículo</option>
                            <option value="ausencia_aluno">Ausência do aluno</option>
                            <option value="condicoes_climaticas">Condições climáticas</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Justificativa *</label>
                        <textarea id="justificativa" name="justificativa" class="form-textarea" 
                                  placeholder="Descreva o motivo da ação..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Política:</strong> Ações devem ser feitas com no mínimo 24 horas de antecedência.
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="enviarAcao()">Confirmar</button>
            </div>
        </div>
    </div>

    <!-- Modal de Chat Interno (Seleção de Destino) -->
    <div id="modalChat" class="modal-overlay hidden">
        <div class="modal modal-chat" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-comment-dots mr-2"></i>Enviar Mensagem
                </h3>
                <button type="button" class="modal-close" onclick="fecharModalChat()" aria-label="Fechar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="form-label mb-2">Destino:</label>
                    <div class="chat-destino-toggle">
                        <button type="button" 
                                class="chat-destino-btn active" 
                                data-destino="aluno"
                                onclick="selecionarDestinoChat('aluno')">
                            <i class="fas fa-user-graduate mr-1"></i>Aluno
                        </button>
                        <button type="button" 
                                class="chat-destino-btn" 
                                data-destino="secretaria"
                                onclick="selecionarDestinoChat('secretaria')">
                            <i class="fas fa-building mr-1"></i>Secretaria
                        </button>
                    </div>
                </div>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle mr-2"></i>
                    <small>Chat interno em desenvolvimento. Esta funcionalidade será implementada em breve.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModalChat()">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnAbrirChat" onclick="confirmarAbrirChat()">
                    <i class="fas fa-comment-dots mr-1"></i>Abrir Chat
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <script>
        // Variáveis globais
        let modalAberto = false;

        // Funções de navegação
        function verTodasAulas() {
            window.location.href = 'aulas.php';
        }

        function verNotificacoes() {
            window.location.href = 'notificacoes.php';
        }

        function registrarOcorrencia() {
            window.location.href = 'ocorrencias.php';
        }

        function contatarSecretaria() {
            window.location.href = 'contato.php';
        }

        // Funções do modal
        // FASE 1 - Ajuste: Normalizar valores de tipo_acao para corresponder à API
        // Arquivo: instrutor/dashboard.php (linha ~561)
        function abrirModal(tipo, aulaId, data, hora) {
            // Normalizar tipo: 'cancelamento' ou 'transferencia' (API espera estes valores)
            const tipoNormalizado = tipo === 'cancelamento' ? 'cancelamento' : 
                                   tipo === 'transferencia' ? 'transferencia' : 
                                   tipo === 'cancelar' ? 'cancelamento' : 
                                   tipo === 'transferir' ? 'transferencia' : tipo;
            
            document.getElementById('tipoAcao').value = tipoNormalizado;
            document.getElementById('aulaId').value = aulaId;
            document.getElementById('dataAula').value = data;
            document.getElementById('horaAula').value = hora;
            
            const modal = document.getElementById('modalAcao');
            const titulo = document.getElementById('modalTitulo');
            const novaDataGroup = document.getElementById('novaDataGroup');
            const novaHoraGroup = document.getElementById('novaHoraGroup');
            
            if (tipoNormalizado === 'transferencia') {
                titulo.textContent = 'Solicitar Transferência';
                novaDataGroup.style.display = 'block';
                novaHoraGroup.style.display = 'block';
            } else {
                titulo.textContent = 'Cancelar Aula';
                novaDataGroup.style.display = 'none';
                novaHoraGroup.style.display = 'none';
            }
            
            modal.classList.remove('hidden');
            modalAberto = true;
        }

        function fecharModal() {
            document.getElementById('modalAcao').classList.add('hidden');
            document.getElementById('formAcao').reset();
            modalAberto = false;
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Botões de cancelamento
            // FASE 1 - Ajuste: Normalizar tipo para 'cancelamento' (API espera este valor)
            // Arquivo: instrutor/dashboard.php (linha ~595)
            document.querySelectorAll('.cancelar-aula').forEach(btn => {
                btn.addEventListener('click', function() {
                    const aulaId = this.dataset.aulaId;
                    const data = this.dataset.data;
                    const hora = this.dataset.hora;
                    abrirModal('cancelamento', aulaId, data, hora);
                });
            });

            // Botões de transferência
            // FASE 1 - Ajuste: Normalizar tipo para 'transferencia' (API espera este valor)
            // Arquivo: instrutor/dashboard.php (linha ~604)
            document.querySelectorAll('.solicitar-transferencia').forEach(btn => {
                btn.addEventListener('click', function() {
                    const aulaId = this.dataset.aulaId;
                    const data = this.dataset.data;
                    const hora = this.dataset.hora;
                    abrirModal('transferencia', aulaId, data, hora);
                });
            });

            // TAREFA 2.2 - Botões de iniciar aula
            document.querySelectorAll('.iniciar-aula').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const aulaId = this.dataset.aulaId;
                    
                    // Coletar KM inicial via prompt
                    const kmInicialStr = prompt('Informe o KM inicial do veículo:');
                    
                    // Se cancelou ou vazio, abortar
                    if (kmInicialStr === null || kmInicialStr.trim() === '') {
                        return;
                    }
                    
                    // Validar numérico
                    const kmInicial = Number(kmInicialStr.trim());
                    if (isNaN(kmInicial)) {
                        alert('KM inicial deve ser um número válido.');
                        return;
                    }
                    
                    // Validar >= 0
                    if (kmInicial < 0) {
                        alert('KM inicial deve ser maior ou igual a zero.');
                        return;
                    }
                    
                    try {
                        const response = await fetch('../admin/api/instrutor-aulas.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                aula_id: aulaId,
                                tipo_acao: 'iniciar',
                                km_inicial: kmInicial
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            mostrarToast('Aula iniciada com sucesso!', 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            mostrarToast(result.message || 'Erro ao iniciar aula.', 'error');
                        }
                    } catch (error) {
                        console.error('Erro:', error);
                        mostrarToast('Erro de conexão. Tente novamente.', 'error');
                    }
                });
            });

            // TAREFA 2.2 - Botões de finalizar aula
            document.querySelectorAll('.finalizar-aula').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const aulaId = this.dataset.aulaId;
                    
                    // Coletar KM final via prompt
                    const kmFinalStr = prompt('Informe o KM final do veículo:');
                    
                    // Se cancelou ou vazio, abortar
                    if (kmFinalStr === null || kmFinalStr.trim() === '') {
                        return;
                    }
                    
                    // Validar numérico
                    const kmFinal = Number(kmFinalStr.trim());
                    if (isNaN(kmFinal)) {
                        alert('KM final deve ser um número válido.');
                        return;
                    }
                    
                    // Validar >= 0
                    if (kmFinal < 0) {
                        alert('KM final deve ser maior ou igual a zero.');
                        return;
                    }
                    
                    try {
                        const response = await fetch('../admin/api/instrutor-aulas.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                aula_id: aulaId,
                                tipo_acao: 'finalizar',
                                km_final: kmFinal
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            mostrarToast('Aula finalizada com sucesso!', 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            mostrarToast(result.message || 'Erro ao finalizar aula.', 'error');
                        }
                    } catch (error) {
                        console.error('Erro:', error);
                        mostrarToast('Erro de conexão. Tente novamente.', 'error');
                    }
                });
            });

            // Botões de chamada e diário
            document.querySelectorAll('.fazer-chamada').forEach(btn => {
                btn.addEventListener('click', function() {
                    const target = this.dataset.url || this.getAttribute('href');
                    if (target) {
                        window.location.href = target;
                    }
                });
            });

            document.querySelectorAll('.fazer-diario').forEach(btn => {
                btn.addEventListener('click', function() {
                    const target = this.dataset.url || this.getAttribute('href');
                    if (target) {
                        window.location.href = target;
                    }
                });
            });

            // Botões de marcar notificação como lida
            document.querySelectorAll('.marcar-lida').forEach(btn => {
                btn.addEventListener('click', function() {
                    const notificacaoId = this.dataset.id;
                    marcarNotificacaoComoLida(notificacaoId);
                });
            });

            // Fechar modal ao clicar fora
            document.getElementById('modalAcao').addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModal();
                }
            });
            
            // Fechar modal de chat ao clicar fora
            const modalChat = document.getElementById('modalChat');
            if (modalChat) {
                modalChat.addEventListener('click', function(e) {
                    if (e.target === this) {
                        fecharModalChat();
                    }
                });
            }
        });

        // Função para enviar ação
        async function enviarAcao() {
            const form = document.getElementById('formAcao');
            const formData = new FormData(form);
            
            // Validação básica
            if (!formData.get('justificativa').trim()) {
                mostrarToast('Por favor, preencha a justificativa.', 'error');
                return;
            }

            try {
                // FASE 1 - Alteração: Usar nova API específica para instrutores
                // Arquivo: instrutor/dashboard.php (linha ~657)
                // Antes: admin/api/solicitacoes.php (bloqueava instrutores)
                // Agora: admin/api/instrutor-aulas.php (específica para instrutores com validação de segurança)
                const response = await fetch('../admin/api/instrutor-aulas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        aula_id: formData.get('aula_id'),
                        tipo_acao: formData.get('tipo_acao'), // Mudou de tipo_solicitacao para tipo_acao
                        nova_data: formData.get('nova_data'),
                        nova_hora: formData.get('nova_hora'),
                        motivo: formData.get('motivo'),
                        justificativa: formData.get('justificativa')
                    })
                });

                const result = await response.json();

                if (result.success) {
                    mostrarToast('Solicitação enviada com sucesso!', 'success');
                    fecharModal();
                    // Recarregar a página após um breve delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    mostrarToast(result.message || 'Erro ao enviar solicitação.', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarToast('Erro de conexão. Tente novamente.', 'error');
            }
        }

        // Função para marcar notificação como lida
        async function marcarNotificacaoComoLida(notificacaoId) {
            try {
                const response = await fetch('../admin/api/notificacoes.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        notificacao_id: notificacaoId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Remover a notificação da interface
                    const notificacaoItem = document.querySelector(`[data-id="${notificacaoId}"]`);
                    if (notificacaoItem) {
                        notificacaoItem.remove();
                    }
                    
                    // Atualizar contador de notificações
                    const badge = document.querySelector('.badge');
                    if (badge) {
                        const count = parseInt(badge.textContent) - 1;
                        if (count > 0) {
                            badge.textContent = count;
                        } else {
                            badge.parentElement.parentElement.remove();
                        }
                    }
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }

        // Função para mostrar toast
        // Função placeholder para chat interno (a ser implementada)
        function abrirModalChat(alunoId, aulaId) {
            alert('Chat interno será implementado em breve. Aluno ID: ' + alunoId);
        }
        
        function mostrarToast(mensagem, tipo = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${tipo}`;
            
            toast.innerHTML = `
                <div class="toast-header">
                    <i class="fas fa-${tipo === 'success' ? 'check-circle' : tipo === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span class="toast-title">${tipo === 'success' ? 'Sucesso' : tipo === 'error' ? 'Erro' : 'Informação'}</span>
                </div>
                <div class="toast-message">${mensagem}</div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Remover toast após 5 segundos
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }

        // Prevenir envio do formulário com Enter
        document.getElementById('formAcao').addEventListener('submit', function(e) {
            e.preventDefault();
        });
    </script>

    <style>
        /* ============================================
           ESTILOS ESPECÍFICOS - DASHBOARD INSTRUTOR
           ============================================ */
        
        /* Container principal */
        .instrutor-dashboard .card {
            border-radius: 0.75rem;
        }
        
        .instrutor-dashboard .card-title {
            font-size: 1rem;
            font-weight: 600;
        }
        
        /* Wrapper geral do dashboard do instrutor */
        .instrutor-dashboard {
            padding-bottom: 2rem;
        }
        
        /* RESUMO DE HOJE - LAYOUT DOS CARDS (Bootstrap 4) */
        /* CORREÇÃO: Removidas classes row-cols-* (Bootstrap 5) que não existem no Bootstrap 4.
           Agora usando grid clássico: row + col-12 col-md-4 */
        /* IMPORTANTE: Garantir que nenhuma regra CSS quebre o grid do Bootstrap 4 */
        .instrutor-dashboard .instrutor-resumo-hoje .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        
        .instrutor-dashboard .instrutor-resumo-hoje .row > [class*="col-"] {
            padding-right: 15px;
            padding-left: 15px;
        }
        
        .instrutor-dashboard .resumo-card {
            border-radius: 0.5rem;
            border: 1px solid #f0f0f0;
            background: #f8f9fa;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .instrutor-dashboard .resumo-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.08);
        }
        
        .instrutor-dashboard .resumo-card .resumo-icon {
            font-size: 1.4rem;
        }
        
        .instrutor-dashboard .resumo-card .resumo-valor {
            font-size: 1.6rem;
            font-weight: 600;
            line-height: 1.1;
        }
        
        .instrutor-dashboard .resumo-card .resumo-label {
            font-size: 0.8rem;
            color: #666;
            white-space: nowrap;
        }
        
        /* ACOES RAPIDAS - Botões estilo card clicável */
        /* IMPORTANTE: Garantir que o grid do Bootstrap 4 funcione corretamente */
        .instrutor-dashboard .instrutor-acoes-rapidas .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        
        .instrutor-dashboard .instrutor-acoes-rapidas .row > [class*="col-"] {
            padding-right: 15px;
            padding-left: 15px;
            display: flex;
        }
        
        .instrutor-dashboard .btn-acao-rapida {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.85rem 1rem;
            min-height: 60px;
            border-radius: 0.5rem;
            font-weight: 500;
            text-align: center;
            white-space: normal;
            line-height: 1.2;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
        }
        
        .instrutor-dashboard .btn-acao-rapida i {
            margin-right: 0.5rem;
        }
        
        .instrutor-dashboard .btn-acao-rapida:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.12);
        }
        
        /* Card Próxima Aula - horário destacado */
        .instructor-next-class-time {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .instructor-next-class-status {
            font-size: 0.85rem;
        }
        
        /* ============================================
           ESTILOS - CARD DO ALUNO (Hierarquia Fixa)
           ============================================ */
        .card-aluno-info {
            padding: 0;
        }
        
        /* Container principal - flexbox */
        .card-aluno-info {
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        
        
        /* Linha 1: Avatar + Nome (compacto) */
        .aluno-linha-1 {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .aluno-foto-wrapper {
            flex-shrink: 0;
            margin-right: 10px;
        }
        
        .aluno-foto {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dee2e6;
            display: block;
        }
        
        .aluno-foto-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: 2px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .aluno-iniciais {
            font-size: 0.85rem;
            line-height: 1;
        }
        
        .aluno-nome-wrapper {
            flex: 1;
            min-width: 0;
        }
        
        .aluno-nome-texto {
            font-size: 0.95rem;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100%;
            text-align: left;
        }
        
        /* Linha 2: Comunicação (botões apenas, otimizado) */
        .aluno-linha-comunicacao {
            margin-bottom: 6px;
        }
        
        /* Botão principal: Mensagem (chat interno) - reduzido */
        .comunicacao-acao-principal {
            width: 100%;
        }
        
        .btn-comunicacao-mensagem {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 14px;
            border-radius: 8px;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            min-height: 44px;
            cursor: pointer;
        }
        
        .btn-comunicacao-mensagem:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }
        
        .btn-comunicacao-mensagem:active {
            transform: translateY(0);
        }
        
        .btn-comunicacao-mensagem i {
            font-size: 1rem;
        }
        
        /* Botões secundários: WhatsApp e Ligar - reduzidos */
        .comunicacao-acoes-secundarias {
            display: flex;
            gap: 6px;
            width: 100%;
        }
        
        .btn-comunicacao-secundaria {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 8px 10px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.2s;
            min-height: 40px;
        }
        
        .btn-comunicacao-whatsapp {
            background-color: #25d366;
            color: white;
            border: none;
        }
        
        .btn-comunicacao-whatsapp:hover {
            background-color: #20ba5a;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(37, 211, 102, 0.3);
        }
        
        .btn-comunicacao-tel {
            background-color: #0d6efd;
            color: white;
            border: none;
        }
        
        .btn-comunicacao-tel:hover {
            background-color: #0b5ed7;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(13, 110, 253, 0.3);
        }
        
        .btn-comunicacao-secundaria i {
            font-size: 0.85rem;
        }
        
        /* Modal de Chat - Toggle de Destino */
        .chat-destino-toggle {
            display: flex;
            gap: 8px;
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 4px;
        }
        
        .chat-destino-btn {
            flex: 1;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            background-color: transparent;
            color: #6c757d;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .chat-destino-btn:hover {
            background-color: #e9ecef;
            color: #495057;
        }
        
        .chat-destino-btn.active {
            background-color: #0d6efd;
            color: white;
            box-shadow: 0 2px 4px rgba(13, 110, 253, 0.2);
        }
        
        .chat-destino-btn.active:hover {
            background-color: #0b5ed7;
        }
        
        .modal-chat .modal-body {
            padding: 20px;
        }
        
        /* Bloco de Dados Padronizado (Telefone, CNH, CPF, Veículo) - NOVO */
        .aluno-dados-container {
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        
        .aluno-linha-dado {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .aluno-linha-dado:last-child {
            margin-bottom: 0;
        }
        
        .aluno-linha-dado i {
            width: 18px;
            text-align: center;
            color: #94a3b8;
            flex-shrink: 0;
        }
        
        .aluno-linha-dado .aluno-label {
            color: #6c757d;
            font-size: 0.85rem;
            margin-right: 6px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .aluno-linha-dado .aluno-valor-cpf,
        .aluno-linha-dado .aluno-valor-telefone,
        .aluno-linha-dado .aluno-valor-veiculo {
            font-weight: 400;
            color: #6c757d;
            font-size: 0.8rem;
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Linhas 3, 4, 5: Informações secundárias (compatibilidade) */
        .aluno-linha-3,
        .aluno-linha-4,
        .aluno-linha-5 {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        .aluno-linha-3 i,
        .aluno-linha-4 i,
        .aluno-linha-5 i {
            width: 18px;
            text-align: center;
            margin-right: 8px;
            flex-shrink: 0;
        }
        
        .aluno-label {
            color: #6c757d;
            font-size: 0.85rem;
            margin-right: 6px;
            white-space: nowrap;
        }
        
        .aluno-valor-cpf,
        .aluno-valor-telefone {
            font-weight: 400;
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        .aluno-badge-cnh {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Linha 6: Dados do veículo (separado) */
        .aluno-linha-6 {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .aluno-linha-6 i {
            margin-right: 8px;
        }
        
        /* Responsivo - Mobile */
        @media (max-width: 767.98px) {
            .card-aluno-info {
                flex-direction: column;
                margin-bottom: 12px !important; /* Reduzido de mb-3 (16px) */
            }
            
            /* Linha 1: Avatar + Nome (compacto) */
            .aluno-linha-1 {
                margin-bottom: 8px !important; /* Reduzido de 12px */
            }
            
            /* Reduzir espaçamentos verticais */
            .aluno-linha-comunicacao {
                margin-bottom: 8px !important; /* Reduzido de mb-2 (12px) */
            }
            
            .aluno-linha-3,
            .aluno-linha-4,
            .aluno-linha-5,
            .aluno-linha-6 {
                margin-bottom: 6px !important; /* Reduzido de mb-2 (12px) */
            }
            
            /* Reduzir padding do card-body da próxima aula (card com border-primary) */
            .card.border-primary .card-body {
                padding: 14px 16px !important; /* Reduzido de py-3 (16px) */
            }
            
            /* Reduzir espaçamento do estado de chamada */
            .card.border-primary .mb-3:last-of-type {
                margin-bottom: 10px !important;
            }
            
            .aluno-foto-wrapper {
                margin-right: 10px;
            }
            
            .aluno-foto,
            .aluno-foto-placeholder {
                width: 38px;
                height: 38px;
            }
            
            .aluno-iniciais {
                font-size: 0.8rem;
            }
            
            .aluno-nome-texto {
                font-size: 0.9rem;
            }
            
            /* Comunicação no mobile */
            .comunicacao-header {
                margin-bottom: 10px;
            }
            
            .comunicacao-destino-select {
                font-size: 0.7rem;
                padding: 3px 6px;
            }
            
            .btn-comunicacao-mensagem {
                min-height: 48px;
                font-size: 0.95rem;
            }
            
            .btn-comunicacao-secundaria {
                min-height: 44px;
                font-size: 0.8rem;
            }
            
            .aluno-linha-3,
            .aluno-linha-4,
            .aluno-linha-5 {
                justify-content: flex-start;
                text-align: left;
                width: 100%;
            }
            
            .aluno-linha-6 {
                text-align: left;
                width: 100%;
            }
        }
        
        /* Desktop: Layout em 2 colunas (foto esquerda, info direita) */
        @media (min-width: 768px) {
            .card-aluno-info {
                flex-direction: row;
                align-items: flex-start;
                gap: 16px;
            }
            
            .aluno-linha-1 {
                display: flex;
                align-items: center;
            }
            
            .aluno-foto-wrapper {
                margin-right: 12px;
            }
            
            /* Comunicação no desktop */
            .comunicacao-header {
                margin-bottom: 10px;
            }
            
            .btn-comunicacao-mensagem {
                min-height: 44px;
            }
            
            .btn-comunicacao-secundaria {
                min-height: 40px;
            }
        }
        
        /* Tabela de Aulas de Hoje - fonte compacta e espaçamento melhorado */
        .instructor-aulas-table {
            font-size: 0.9rem;
        }
        
        .instructor-aulas-table th {
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.75rem 0.5rem;
        }
        
        .instructor-aulas-table td {
            padding: 0.75rem 0.5rem;
        }
        
        /* AULAS DE HOJE - Padding lateral confortável */
        .instrutor-dashboard .card-body {
            padding: 1.25rem;
        }
        
        .instrutor-dashboard .dashboard-aulas-hoje .card-body {
            padding: 1.25rem 1.5rem;
        }
        
        /* Mobile: ajustar padding para consistência com outros cards */
        @media (max-width: 768px) {
            .instrutor-dashboard .dashboard-aulas-hoje .card-body {
                padding: 14px 16px !important; /* Mesmo padding do card "Próxima aula" */
            }
        }
        
        .instrutor-dashboard .dashboard-aulas-hoje table th,
        .instrutor-dashboard .dashboard-aulas-hoje table td {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
            vertical-align: middle;
        }
        
        /* Próximas Aulas - espaçamento melhorado */
        .proximas-aulas-list {
            gap: 12px;
        }
        
        .proximas-aulas-dia-card {
            border-color: #e9ecef !important;
            background: white;
        }
        
        .proximas-aulas-item {
            line-height: 1.6;
        }
        
        .proximas-aulas-extra {
            margin-top: 8px;
        }
        
        /* Responsividade mobile - ajustes de padding e espaçamento */
        @media (max-width: 767.98px) {
            .instructor-dashboard-container {
                padding: 16px 10px 24px;
            }
            
            .instructor-dashboard-container .card {
                margin-bottom: 16px;
            }
            
            .instructor-dashboard-container .card-body {
                padding: 16px;
            }
            
            /* Ações Rápidas - grid 2x2 no mobile */
            .instructor-quick-actions .btn {
                width: 100%;
            }
        }
        
        /* Estilos específicos para o dashboard do instrutor */
        .notificacao-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 8px;
            background: #f8fafc;
        }

        .notificacao-content {
            flex: 1;
        }

        .notificacao-content h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #1e293b;
        }

        .notificacao-content p {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .notificacao-content small {
            font-size: 11px;
            color: #94a3b8;
        }

        .stat-item {
            text-align: center;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 500;
        }

        .aula-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .aula-actions .btn {
            flex: 1;
            min-width: 120px;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .alert i {
            font-size: 16px;
        }

        @media (max-width: 480px) {
            .aula-actions {
                flex-direction: column;
            }
            
            .aula-actions .btn {
                width: 100%;
                min-width: auto;
            }
        }

        /* Responsividade mobile - ajustes de padding e espaçamento */
        @media (max-width: 767.98px) {
            .instructor-dashboard-container {
                padding: 16px 10px 24px;
            }
            
            .instructor-dashboard-container .card {
                margin-bottom: 16px;
            }
            
            .instructor-dashboard-container .card-body {
                padding: 16px;
            }
            
            /* Ações Rápidas - grid 2x2 no mobile */
            .instructor-quick-actions .btn {
                width: 100%;
            }
        }
        
        /* Badges com cores suaves (Bootstrap 5.3+) */
        .bg-success-subtle {
            background-color: #d1e7dd !important;
        }
        
        .bg-warning-subtle {
            background-color: #fff3cd !important;
        }
        
        .bg-secondary-subtle {
            background-color: #e2e3e5 !important;
        }
        
        /* Ajuste visual: Ações Rápidas - grid responsivo */
        .acoes-rapidas-grid {
            --bs-gutter-x: 0.5rem;
        }
        
        @media (min-width: 768px) {
            .acoes-rapidas-grid .col-md-auto {
                flex: 0 0 auto;
                width: auto;
            }
        }
        
        /* Ajuste visual: Responsividade mobile - tabela de Aulas de Hoje */
        @media (max-width: 575px) {
            .instructor-aulas-table {
                font-size: 0.8rem;
            }
            
            .instructor-aulas-table th,
            .instructor-aulas-table td {
                padding: 0.4rem 0.3rem;
            }
            
            .instructor-aulas-table th {
                font-size: 0.75rem;
            }
            
            /* Permitir quebra de linha em disciplinas longas */
            .instructor-aulas-table td:nth-child(3) {
                word-break: break-word;
                max-width: 120px;
            }
            
            /* Botões de ação menores no mobile */
            .instructor-aulas-table .btn-group-sm .btn {
                padding: 0.25rem 0.4rem;
                font-size: 0.75rem;
            }
        }
        
        /* AJUSTE DASHBOARD INSTRUTOR - otimização layout desktop */
        @media (min-width: 1200px) {
            /* Reduzir padding vertical dos cards de aula em desktop */
            .aula-item {
                padding: 12px 16px !important;
            }

            .aula-item-header {
                margin-bottom: 8px !important;
            }

            .aula-detalhes {
                margin-bottom: 8px !important;
            }

            .aula-detalhe {
                font-size: 13px !important;
                padding: 4px 0 !important;
            }

            /* Destacar primeira aula de hoje */
            .aulas-hoje-card .aula-list > .aula-item:first-child {
                border: 2px solid #2563eb;
                background: #eff6ff;
                position: relative;
            }

            .aulas-hoje-card .aula-list > .aula-item:first-child::before {
                content: "Próxima aula";
                position: absolute;
                top: 8px;
                right: 8px;
                background: #2563eb;
                color: white;
                font-size: 10px;
                font-weight: 600;
                padding: 2px 8px;
                border-radius: 4px;
                text-transform: uppercase;
            }

            /* Compactar cards de próximas aulas */
            .proximas-aulas-card .aula-item {
                padding: 10px 14px !important;
            }

            .proximas-aulas-card .aula-detalhe {
                font-size: 12px !important;
            }

            .proximas-aulas-card .aula-actions .btn {
                font-size: 12px !important;
                padding: 6px 12px !important;
            }

            /* Reduzir espaçamento entre cards */
            .aula-list .aula-item {
                margin-bottom: 8px !important;
            }
        }
        
        /* ============================================
           ESTILOS DO HEADER - MOBILE (375x667)
           ============================================ */
        @media (max-width: 768px) {
            /* Reduzir padding vertical do header */
            .header {
                padding: 12px 16px !important;
            }
            
            /* Título mais compacto */
            .header h1 {
                font-size: 18px !important;
                margin-bottom: 2px !important;
            }
            
            /* Subtítulo mais discreto */
            .header .subtitle {
                font-size: 12px !important;
                opacity: 0.85 !important;
            }
            
            /* Chip do perfil ghost - mobile */
            .instrutor-profile-button {
                padding: 4px 6px !important;
                gap: 6px !important;
                background: transparent !important;
                border: none !important;
            }
            
            /* Hover/active discreto no mobile */
            .instrutor-profile-button:hover,
            .instrutor-profile-button:active,
            .instrutor-profile-button.active {
                background: rgba(255,255,255,0.1) !important;
                border-radius: 6px !important;
            }
            
            /* Avatar menor no mobile - sem moldura pesada */
            .instrutor-profile-avatar {
                width: 30px !important;
                height: 30px !important;
                font-size: 11px !important;
                border: 1px solid rgba(255,255,255,0.2) !important;
            }
            
            /* Nome em 1 linha no mobile (truncado) */
            .instrutor-profile-info {
                min-width: 0 !important;
                flex: 1 !important;
            }
            
            .instrutor-profile-name {
                max-width: 90px !important;
                font-size: 13px !important;
                color: white !important;
            }
            
            /* Esconder "Instrutor" no mobile (só mostrar no dropdown) */
            .instrutor-profile-role {
                display: none !important;
            }
            
            /* Ajustar chevron no mobile */
            .instrutor-profile-button .fa-chevron-down {
                font-size: 10px !important;
                margin-left: 2px !important;
                opacity: 0.85 !important;
            }
        }
        
        /* Desktop: manter comportamento atual com hover discreto */
        @media (min-width: 769px) {
            .instrutor-profile-role {
                display: block !important;
            }
            
            /* Hover/active discreto no desktop */
            .instrutor-profile-button:hover,
            .instrutor-profile-button:active,
            .instrutor-profile-button.active {
                background: rgba(255,255,255,0.1) !important;
                border-radius: 6px !important;
            }
            
            /* Avatar no desktop - contorno sutil */
            .instrutor-profile-avatar {
                border: 1px solid rgba(255,255,255,0.2) !important;
            }
        }
        
        /* ============================================
           MOBILE-FIRST: AULAS DE HOJE (PRIORIDADE)
           ============================================ */
        @media (max-width: 768px) {
            /* Esconder tabela no mobile */
            .aulas-hoje-table-desktop {
                display: none !important;
            }
            
            /* Mostrar lista no mobile */
            .aulas-hoje-list-mobile {
                display: block;
            }
        }
        
        @media (min-width: 769px) {
            /* Esconder lista no desktop */
            .aulas-hoje-list-mobile {
                display: none !important;
            }
            
            /* Mostrar tabela no desktop */
            .aulas-hoje-table-desktop {
                display: block;
            }
        }
        
        /* ============================================
           MOBILE-FIRST: RESUMO DE HOJE (COMPACTO)
           ============================================ */
        @media (max-width: 768px) {
            /* Resumo de hoje: transformar cards em chips compactos */
            .instrutor-resumo-hoje .card-body {
                padding: 12px !important;
            }
            
            .instrutor-resumo-hoje .row {
                margin: 0 !important;
                display: flex !important;
                flex-wrap: nowrap !important;
                gap: 8px !important;
            }
            
            .instrutor-resumo-hoje .col-12.col-lg-4 {
                flex: 1 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .instrutor-resumo-hoje .resumo-card {
                padding: 10px 8px !important;
                margin: 0 !important;
                height: auto !important;
            }
            
            .instrutor-resumo-hoje .resumo-icon {
                font-size: 1rem !important;
                margin-bottom: 4px !important;
            }
            
            .instrutor-resumo-hoje .resumo-valor {
                font-size: 1.3rem !important;
                line-height: 1 !important;
            }
            
            .instrutor-resumo-hoje .resumo-label {
                font-size: 0.7rem !important;
                margin-top: 4px !important;
            }
        }
        
        /* ============================================
           MOBILE-FIRST: SEÇÕES VAZIAS CONDICIONAIS
           ============================================ */
        @media (max-width: 768px) {
            /* Pendências em dia: linha compacta dentro do Resumo */
            .resumo-pendencias-ok {
                display: block !important;
            }
            
            .resumo-pendencias-alert {
                display: block !important;
            }
        }
        
        /* ============================================
           MOBILE-FIRST: AÇÕES RÁPIDAS (3 BOTÕES PADRONIZADOS)
           ============================================ */
        .acoes-rapidas-grid-padronizado {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        
        .btn-acao-rapida-padronizado {
            height: 44px !important;
            min-height: 44px !important;
            padding: 8px 12px !important;
            font-size: 13px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            white-space: normal !important;
            line-height: 1.3 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            border-radius: 6px !important;
            font-weight: 500 !important;
        }
        
        .btn-acao-rapida-padronizado i {
            font-size: 14px !important;
            flex-shrink: 0 !important;
        }
        
        .btn-acao-rapida-padronizado span {
            white-space: normal !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            text-align: center !important;
            /* Permitir até 2 linhas */
            display: -webkit-box !important;
            -webkit-line-clamp: 2 !important;
            -webkit-box-orient: vertical !important;
            overflow: hidden !important;
        }
        
        /* Mobile: grid 2 colunas, terceiro botão vai para linha de baixo */
        @media (max-width: 768px) {
            .acoes-rapidas-grid-padronizado {
                grid-template-columns: repeat(2, 1fr);
            }
            
            /* Terceiro botão ocupa 100% da largura na segunda linha */
            .acoes-rapidas-grid-padronizado > button:nth-child(3) {
                grid-column: 1 / -1;
            }
        }
        
        /* Desktop: 3 botões em linha */
        @media (min-width: 769px) {
            .acoes-rapidas-grid-padronizado {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .acoes-rapidas-grid-padronizado > button:nth-child(3) {
                grid-column: auto;
            }
        }
        
        /* ============================================
           MOBILE-FIRST: CARDS DE AULA PADRONIZADOS (NUNCA ESTOURA)
           ============================================ */
        .aula-card-padronizado {
            max-width: 100% !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
        }
        
        .aula-card-padronizado * {
            box-sizing: border-box !important;
        }
        
        /* Header com flex-wrap para badges nunca empurrarem layout */
        .aula-header {
            width: 100% !important;
        }
        
        /* Garantir que nada quebre em colunas estranhas */
        @media (max-width: 768px) {
            .aula-card-padronizado {
                width: 100% !important;
                max-width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            
            /* AJUSTE PROPORCIONALIDADE: Aulas Hoje - garantir largura útil igual ao restante do card */
            /* Usar mesmo padding do card "Próxima aula" (14px 16px) para consistência visual */
            .dashboard-aulas-hoje .card-body,
            .instrutor-dashboard .dashboard-aulas-hoje .card-body {
                padding: 14px 16px !important; /* Mesmo padding do card border-primary */
            }
            
            /* Prevenir scroll horizontal e garantir largura total disponível */
            /* CORREÇÃO: Forçar largura total do container, removendo qualquer limitação */
            /* Garantir que o bloco ocupe exatamente a mesma largura útil do conteúdo do card */
            .dashboard-aulas-hoje .card-body .aulas-hoje-list-mobile,
            .aulas-hoje-list-mobile {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                overflow-x: hidden !important;
                overflow-y: visible !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                box-sizing: border-box !important;
                display: block !important;
                /* Garantir que não haja nenhuma limitação de largura de containers pais */
                flex: 1 1 100% !important;
            }
            
            /* Garantir que o card-body não tenha limitações de largura e use padding consistente */
            .dashboard-aulas-hoje .card-body {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                /* Padding já ajustado acima (14px 16px) para consistência com card "Próxima aula" */
            }
            
            /* Garantir que os cards internos também ocupem toda a largura proporcionalmente */
            .aulas-hoje-list-mobile .aula-card-padronizado,
            .aulas-hoje-list-mobile .aula-item-mobile {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                box-sizing: border-box !important;
            }
            
            /* Adicionar padding interno nos cards de aula para espaçamento visual (já que removemos do card-body) */
            .aulas-hoje-list-mobile .aula-card-padronizado {
                padding-left: 16px !important;
                padding-right: 16px !important;
            }
            
            /* Garantir que título e meta nunca estourem */
            .aula-card-padronizado > div:nth-child(2),
            .aula-card-padronizado > div:nth-child(3) {
                width: 100% !important;
                max-width: 100% !important;
                overflow: hidden !important;
            }
            
            /* Botões sempre alinhados e sem overflow */
            .aula-card-padronizado > div:last-child {
                width: 100% !important;
                max-width: 100% !important;
                overflow: hidden !important;
            }
        }
        
        /* ============================================
           MOBILE-FIRST: RESUMO SEM ALTURA EXCESSIVA
           ============================================ */
        @media (max-width: 768px) {
            .instrutor-resumo-hoje .card-header {
                padding: 8px 12px !important;
                font-size: 0.9rem !important;
            }
            
            .instrutor-resumo-hoje .card-body {
                padding: 12px !important;
            }
            
            .instrutor-resumo-hoje .resumo-card {
                padding: 10px 8px !important;
                min-height: auto !important;
            }
            
            .instrutor-resumo-hoje .resumo-icon {
                font-size: 1rem !important;
                margin-bottom: 4px !important;
            }
            
            .instrutor-resumo-hoje .resumo-valor {
                font-size: 1.3rem !important;
                line-height: 1 !important;
                margin-bottom: 2px !important;
            }
            
            .instrutor-resumo-hoje .resumo-label {
                font-size: 0.7rem !important;
                margin-top: 0 !important;
            }
        }
        
        /* ============================================
           MOBILE-FIRST: SEÇÕES VAZIAS NÃO OCUPAM ESPAÇO
           ============================================ */
        @media (max-width: 768px) {
            /* Pendências em dia: linha compacta */
            .pendencias-ok-mobile {
                display: block !important;
            }
            
            .pendencias-ok-mobile .card-body {
                padding: 8px 12px !important;
            }
        }
        
        /* ============================================
           CORREÇÕES DESKTOP (min-width: 992px) - VERSÃO CIRÚRGICA
           ============================================ */
        @media (min-width: 992px) {
            /* 1) ENXUGAR ESPAÇO BRANCO DO CARD "PRÓXIMA AULA" */
            /* Remover h-100 que força altura igual ao card ao lado - seletor mais específico */
            .instrutor-dashboard .col-lg-7 .card.border-primary.h-100,
            .instrutor-dashboard .col-xl-6 .card.border-primary.h-100 {
                height: auto !important;
                min-height: auto !important;
                max-height: none !important;
            }
            
            /* Garantir que o row pai não force altura igual */
            .instrutor-dashboard .row:first-of-type {
                align-items: flex-start !important;
            }
            
            /* Garantir que a coluna pai não force altura */
            .instrutor-dashboard .col-lg-7,
            .instrutor-dashboard .col-xl-6 {
                align-items: flex-start !important;
                display: flex !important;
                flex-direction: column !important;
            }
            
            /* Garantir que o card dentro da coluna não estique */
            .instrutor-dashboard .col-lg-7 > .card,
            .instrutor-dashboard .col-xl-6 > .card {
                flex: 0 1 auto !important;
                width: 100% !important;
            }
            
            /* Reduzir espaçamentos internos para card mais compacto */
            .card.border-primary .card-body {
                padding-bottom: 0.75rem !important;
            }
            
            .card.border-primary .mb-3:last-of-type {
                margin-bottom: 0.5rem !important;
            }
            
            /* 2) GARANTIR VISIBILIDADE DAS INFOS (TELEFONE, CPF, VEÍCULO) NO DESKTOP */
            /* Ajustar layout do card-aluno-info no desktop para não esticar */
            .card.border-primary .card-aluno-info {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0 !important;
                margin-bottom: 0.75rem !important;
            }
            
            /* Garantir que as linhas apareçam - sem quebra agressiva */
            .card.border-primary .card-aluno-info .aluno-linha-3,
            .card.border-primary .card-aluno-info .aluno-linha-5,
            .card.border-primary .card-aluno-info .aluno-linha-6 {
                display: flex !important;
                visibility: visible !important;
                opacity: 1 !important;
                width: 100% !important;
                margin-bottom: 0.5rem !important;
            }
            
            /* Permitir quebra natural de linha apenas quando necessário - SEM word-break agressivo */
            .card.border-primary .card-aluno-info .aluno-linha-3,
            .card.border-primary .card-aluno-info .aluno-linha-5 {
                flex-wrap: wrap;
                overflow-wrap: normal;
                word-break: normal;
            }
            
            .card.border-primary .card-aluno-info .aluno-linha-6 {
                overflow-wrap: normal;
                word-break: normal;
            }
            
            /* Garantir que valores não quebrem caracter por caracter */
            .card.border-primary .card-aluno-info .aluno-valor-telefone,
            .card.border-primary .card-aluno-info .aluno-valor-cpf {
                white-space: normal;
                word-break: normal;
                overflow-wrap: normal;
            }
            
            /* 3) CORRIGIR OVERFLOW DO TELEFONE NA TABELA "AULAS DE HOJE" - APENAS TABELA */
            /* Aplicar overflow e quebra APENAS nas células da tabela, não no card superior */
            .dashboard-aulas-hoje .instructor-aulas-table td {
                overflow: hidden;
                word-wrap: break-word;
                overflow-wrap: break-word;
                position: relative;
            }
            
            /* Apenas na tabela: garantir que containers flex não ultrapassem */
            .dashboard-aulas-hoje .instructor-aulas-table .d-flex {
                flex-wrap: wrap;
                max-width: 100%;
                overflow: hidden;
            }
            
            .dashboard-aulas-hoje .instructor-aulas-table .flex-grow-1 {
                min-width: 0;
                max-width: 100%;
            }
            
            /* Quebra de linha normal (não agressiva) apenas na tabela */
            .dashboard-aulas-hoje .instructor-aulas-table .text-muted,
            .dashboard-aulas-hoje .instructor-aulas-table .fw-bold,
            .dashboard-aulas-hoje .instructor-aulas-table a {
                word-break: break-word;
                overflow-wrap: break-word;
                white-space: normal;
            }
            
            /* 4) CORRIGIR AVATAR DUPLICADO NA TABELA "AULAS DE HOJE" */
            /* Regra mais robusta: se imagem tem src e está visível, placeholder deve estar oculto */
            .dashboard-aulas-hoje .instructor-aulas-table .mr-2.flex-shrink-0 img.aluno-foto-tabela[src]:not([src=""]) {
                display: block !important;
            }
            
            .dashboard-aulas-hoje .instructor-aulas-table .mr-2.flex-shrink-0 img.aluno-foto-tabela[src]:not([src=""]) ~ .aluno-foto-placeholder-tabela {
                display: none !important;
            }
            
            /* Se imagem está oculta (onerror), mostrar placeholder */
            .dashboard-aulas-hoje .instructor-aulas-table .mr-2.flex-shrink-0 img.aluno-foto-tabela[style*="display: none"] ~ .aluno-foto-placeholder-tabela {
                display: flex !important;
            }
            
            /* Placeholder só aparece se não houver imagem válida (tratado via JS) */
        }
    </style>
    
    <!-- Script para Dropdown de Perfil e correção de avatares -->
    <script>
        // Função para corrigir avatares duplicados na tabela
        function corrigirAvataresTabela() {
            const containers = document.querySelectorAll('.instructor-aulas-table .mr-2.flex-shrink-0');
            containers.forEach(function(container) {
                const img = container.querySelector('img.aluno-foto-tabela');
                const placeholder = container.querySelector('.aluno-foto-placeholder-tabela');
                
                if (!container) return;
                
                // Se não há imagem, mostrar placeholder
                if (!img) {
                    if (placeholder) {
                        placeholder.style.display = 'flex';
                    }
                    return;
                }
                
                // Se há imagem mas não há placeholder, não fazer nada
                if (!placeholder) return;
                
                // Verificar se a imagem tem src válido
                const hasValidSrc = img.src && 
                                   img.src !== window.location.href && 
                                   !img.src.endsWith('#') &&
                                   !img.src.includes('undefined') &&
                                   !img.src.includes('null');
                
                if (hasValidSrc) {
                    // Verificar estado atual da imagem
                    const computedStyle = window.getComputedStyle(img);
                    const isHidden = img.style.display === 'none' || 
                                    computedStyle.display === 'none' ||
                                    img.offsetWidth === 0 ||
                                    img.offsetHeight === 0;
                    
                    if (!isHidden && img.complete) {
                        // Imagem carregou com sucesso
                        if (img.naturalHeight !== 0 && img.naturalWidth !== 0) {
                            // Imagem válida carregada
                            placeholder.style.display = 'none';
                            img.style.display = 'block';
                            img.style.visibility = 'visible';
                        } else {
                            // Imagem inválida
                            img.style.display = 'none';
                            placeholder.style.display = 'flex';
                        }
                    } else if (!isHidden && !img.complete) {
                        // Imagem ainda está carregando
                        img.addEventListener('load', function() {
                            if (this.naturalHeight !== 0 && this.naturalWidth !== 0) {
                                placeholder.style.display = 'none';
                                this.style.display = 'block';
                                this.style.visibility = 'visible';
                            } else {
                                this.style.display = 'none';
                                placeholder.style.display = 'flex';
                            }
                        }, { once: true });
                        
                        img.addEventListener('error', function() {
                            this.style.display = 'none';
                            placeholder.style.display = 'flex';
                        }, { once: true });
                    } else {
                        // Imagem está oculta, mostrar placeholder
                        placeholder.style.display = 'flex';
                    }
                } else {
                    // Não há src válido, mostrar placeholder
                    img.style.display = 'none';
                    placeholder.style.display = 'flex';
                }
            });
        }
        
        // Garantir que avatares duplicados não apareçam na tabela
        document.addEventListener('DOMContentLoaded', function() {
            // Corrigir imediatamente
            corrigirAvataresTabela();
            
            // Corrigir após um pequeno delay (para imagens que ainda estão carregando)
            setTimeout(corrigirAvataresTabela, 100);
            setTimeout(corrigirAvataresTabela, 500);
            
            // Observar mudanças no DOM (caso a tabela seja carregada dinamicamente)
            const observer = new MutationObserver(function(mutations) {
                let shouldCheck = false;
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length > 0) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1 && 
                                (node.classList && node.classList.contains('instructor-aulas-table') ||
                                 node.querySelector && node.querySelector('.instructor-aulas-table'))) {
                                shouldCheck = true;
                            }
                        });
                    }
                });
                if (shouldCheck) {
                    setTimeout(corrigirAvataresTabela, 100);
                }
            });
            
            const tableContainer = document.querySelector('.dashboard-aulas-hoje');
            if (tableContainer) {
                observer.observe(tableContainer, { childList: true, subtree: true });
            }
            
            // Toggle do dropdown de perfil
            const profileButton = document.getElementById('instrutor-profile-button');
            const profileDropdown = document.getElementById('instrutor-profile-dropdown');
            
            if (profileButton && profileDropdown) {
                profileButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const isVisible = profileDropdown.style.display === 'block';
                    profileDropdown.style.display = isVisible ? 'none' : 'block';
                    profileButton.classList.toggle('active', !isVisible);
                });
                
                // Fechar dropdown ao clicar fora
                document.addEventListener('click', function(e) {
                    if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.style.display = 'none';
                        profileButton.classList.remove('active');
                    }
                });
            }
        });
    </script>
    
    <!-- PWA Registration Script -->
    <script src="/pwa/pwa-register.js"></script>
</body>
</html>
