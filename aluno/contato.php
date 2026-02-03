<?php
/**
 * Contato do Aluno com o CFC
 * 
 * FASE 4 - CONTATO ALUNO - Implementação: 2025
 * Arquivo: aluno/contato.php
 * 
 * Funcionalidades:
 * - Exibir informações de contato da secretaria
 * - Formulário para enviar mensagem para secretaria
 * - Listar mensagens enviadas pelo aluno
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// FASE 4 - CONTATO ALUNO - Verificação de autenticação
$user = getCurrentUser();
if (!$user || $user['tipo'] !== 'aluno') {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/login.php');
    exit();
}

$db = db();

// FASE 4 - CONTATO ALUNO - Obter aluno_id usando getCurrentAlunoId()
$alunoId = getCurrentAlunoId($user['id']);

if (!$alunoId) {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/aluno/dashboard.php?erro=aluno_nao_encontrado');
    exit();
}

// FASE 4 - CONTATO ALUNO - Buscar dados do aluno
$aluno = $db->fetch("SELECT * FROM alunos WHERE id = ?", [$alunoId]);
if (!$aluno) {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/aluno/dashboard.php?erro=aluno_nao_encontrado');
    exit();
}

$aluno['nome'] = $aluno['nome'] ?? 'Aluno';

// FASE 4 - CONTATO ALUNO - Informações de contato da secretaria (fixas por enquanto)
$contatoSecretaria = [
    'whatsapp' => '87981450308', // Número sem formatação para link
    'whatsapp_formatado' => '(87) 98145-0308',
    'email' => 'contato@cfcbomconselho.com.br',
    'telefone' => '(87) 98145-0308',
    'horario_atendimento' => 'Segunda a Sexta, 8h às 18h',
    'endereco' => 'R. Ângela Pessoa Lucena, 248 - Bom Conselho, PE'
];

$success = '';
$error = '';
$tabelaExiste = false;

// FASE 4 - CONTATO ALUNO - Verificar se tabela existe
try {
    $tabelaExiste = $db->fetch("SHOW TABLES LIKE 'contatos_aluno'");
} catch (Exception $e) {
    error_log('Erro ao verificar tabela contatos_aluno: ' . $e->getMessage());
}

// FASE 4 - CONTATO ALUNO - Processar envio de mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enviar_mensagem') {
    if (!$tabelaExiste) {
        $error = 'Sistema de contato ainda não está disponível. Entre em contato com a secretaria diretamente.';
    } else {
        $assunto = trim($_POST['assunto'] ?? '');
        $tipoAssunto = trim($_POST['tipo_assunto'] ?? '');
        $mensagem = trim($_POST['mensagem'] ?? '');
        $aulaId = !empty($_POST['aula_id']) ? (int)$_POST['aula_id'] : null;
        
        // Validações
        if (empty($assunto)) {
            $error = 'Assunto é obrigatório.';
        } elseif (strlen($assunto) < 5) {
            $error = 'Assunto deve ter no mínimo 5 caracteres.';
        } elseif (empty($mensagem)) {
            $error = 'Mensagem é obrigatória.';
        } elseif (strlen($mensagem) < 10) {
            $error = 'Mensagem deve ter no mínimo 10 caracteres.';
        } else {
            // FASE 4 - CONTATO ALUNO - Validar se aula pertence ao aluno (se fornecida)
            if ($aulaId) {
                $aulaValida = $db->fetch("SELECT id FROM aulas WHERE id = ? AND aluno_id = ?", [$aulaId, $alunoId]);
                if (!$aulaValida) {
                    $error = 'Aula não encontrada ou não pertence a você.';
                }
            }
            
            if (empty($error)) {
                // Inserir mensagem
                try {
                    $sql = "INSERT INTO contatos_aluno 
                            (aluno_id, usuario_id, tipo_assunto, assunto, mensagem, aula_id, status, criado_em)
                            VALUES (?, ?, ?, ?, ?, ?, 'aberto', NOW())";
                    
                    $params = [$alunoId, $user['id'], $tipoAssunto ?: null, $assunto, $mensagem, $aulaId];
                    
                    $result = $db->query($sql, $params);
                    
                    if ($result) {
                        $success = 'Mensagem enviada com sucesso! A secretaria entrará em contato em breve.';
                        
                        // Limpar formulário (redirecionar para evitar reenvio)
                        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                        header('Location: ' . $basePath . '/aluno/contato.php?success=1');
                        exit();
                    } else {
                        $error = 'Erro ao enviar mensagem. Tente novamente.';
                    }
                } catch (Exception $e) {
                    $error = 'Erro ao enviar mensagem: ' . $e->getMessage();
                    if (defined('LOG_ENABLED') && LOG_ENABLED) {
                        error_log('Erro ao enviar mensagem do aluno: ' . $e->getMessage());
                    }
                }
            }
        }
    }
}

// FASE 4 - CONTATO ALUNO - Verificar mensagem de sucesso via GET
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = 'Mensagem enviada com sucesso! A secretaria entrará em contato em breve.';
}

// FASE 4 - CONTATO ALUNO - Buscar aulas recentes/futuras do aluno para o select (opcional)
$aulasParaSelect = [];
if ($alunoId) {
    // Aulas práticas
    $aulasPraticas = $db->fetchAll("
        SELECT a.id, a.data_aula, a.hora_inicio, a.tipo_aula, 'pratica' as tipo
        FROM aulas a
        WHERE a.aluno_id = ?
          AND a.data_aula >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND a.status != 'cancelada'
        ORDER BY a.data_aula DESC, a.hora_inicio DESC
        LIMIT 20
    ", [$alunoId]);
    
    // Aulas teóricas (via turma_matriculas)
    $turmasAluno = $db->fetchAll("
        SELECT tm.turma_id
        FROM turma_matriculas tm
        WHERE tm.aluno_id = ?
          AND tm.status IN ('matriculado', 'cursando', 'concluido')
    ", [$alunoId]);
    
    $aulasTeoricas = [];
    if (!empty($turmasAluno)) {
        $turmaIds = array_column($turmasAluno, 'turma_id');
        $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
        $aulasTeoricas = $db->fetchAll("
            SELECT taa.id, taa.data_aula, taa.hora_inicio, taa.disciplina, 'teorica' as tipo, tt.nome as turma_nome
            FROM turma_aulas_agendadas taa
            JOIN turmas_teoricas tt ON taa.turma_id = tt.id
            WHERE taa.turma_id IN ($placeholders)
              AND taa.data_aula >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND taa.status != 'cancelada'
            ORDER BY taa.data_aula DESC, taa.hora_inicio DESC
            LIMIT 20
        ", $turmaIds);
    }
    
    // Combinar e formatar
    foreach ($aulasPraticas as $aula) {
        $aulasParaSelect[] = [
            'id' => $aula['id'],
            'tipo' => 'pratica',
            'data' => $aula['data_aula'],
            'hora' => $aula['hora_inicio'],
            'label' => 'Prática - ' . date('d/m/Y', strtotime($aula['data_aula'])) . ' ' . date('H:i', strtotime($aula['hora_inicio']))
        ];
    }
    
    foreach ($aulasTeoricas as $aula) {
        $aulasParaSelect[] = [
            'id' => $aula['id'],
            'tipo' => 'teorica',
            'data' => $aula['data_aula'],
            'hora' => $aula['hora_inicio'],
            'label' => 'Teórica (' . htmlspecialchars($aula['turma_nome']) . ') - ' . date('d/m/Y', strtotime($aula['data_aula'])) . ' ' . date('H:i', strtotime($aula['hora_inicio']))
        ];
    }
    
    // Ordenar por data (mais recentes primeiro)
    usort($aulasParaSelect, function($a, $b) {
        return strtotime($b['data'] . ' ' . $b['hora']) - strtotime($a['data'] . ' ' . $a['hora']);
    });
    
    // Limitar a 30
    $aulasParaSelect = array_slice($aulasParaSelect, 0, 30);
}

// FASE 4 - CONTATO ALUNO - Buscar mensagens enviadas pelo aluno
$contatosEnviados = [];
if ($tabelaExiste && $alunoId) {
    try {
        $contatosEnviados = $db->fetchAll("
            SELECT ca.*, 
                   u.nome as respondido_por_nome,
                   a.data_aula as aula_data,
                   a.hora_inicio as aula_hora,
                   a.tipo_aula as aula_tipo
            FROM contatos_aluno ca
            LEFT JOIN usuarios u ON ca.respondido_por = u.id
            LEFT JOIN aulas a ON ca.aula_id = a.id
            WHERE ca.aluno_id = ?
            ORDER BY ca.criado_em DESC
            LIMIT 50
        ", [$alunoId]);
    } catch (Exception $e) {
        error_log('Erro ao buscar contatos do aluno: ' . $e->getMessage());
    }
}

// Função auxiliar para formatar status
function formatarStatusContato($status) {
    $map = [
        'aberto' => ['label' => 'Recebido', 'class' => 'primary'],
        'em_atendimento' => ['label' => 'Em Análise', 'class' => 'warning'],
        'respondido' => ['label' => 'Respondido', 'class' => 'success'],
        'fechado' => ['label' => 'Arquivado', 'class' => 'secondary']
    ];
    
    return $map[$status] ?? ['label' => ucfirst($status), 'class' => 'secondary'];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#10b981" id="theme-color-meta">
    <title>Contato com o CFC - <?php echo htmlspecialchars($aluno['nome']); ?></title>
    <link rel="stylesheet" href="../assets/css/theme-tokens.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
    <link rel="stylesheet" href="../assets/css/aluno-dashboard.css">
    <style>
        /* Removido header-contato - agora é card dentro do conteúdo */
        .contato-info-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .contato-info-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .contato-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        /* Removido media query de header-contato */
    </style>
    <script>
        (function(){var m=document.getElementById('theme-color-meta');if(!m)return;function u(){var d=window.matchMedia('(prefers-color-scheme: dark)').matches;m.setAttribute('content',d?'#1e293b':'#10b981');}u();window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',u);})();
    </script>
</head>
<body>
    <div class="container" style="max-width: 1000px; margin: 0 auto; padding: 20px 16px;">
        <!-- Card de Título (não é header, é card dentro do conteúdo) -->
        <div class="card card-aluno-dashboard mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h1 class="h4 mb-1">
                            <i class="fas fa-headset me-2 text-primary"></i>
                            Contato com o CFC
                        </h1>
                        <p class="text-muted mb-0 small">Use este canal para falar com a secretaria sobre dúvidas, pagamentos, aulas e outros assuntos.</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-2"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
        <!-- Mensagens -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!$tabelaExiste): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Atenção:</strong> O sistema de contato ainda não está disponível. 
            Entre em contato com a secretaria diretamente através dos canais abaixo.
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Informações de Contato -->
            <div class="col-12 col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-address-card me-2"></i>Informações de Contato
                        </h5>
                        
                        <div class="d-flex flex-column gap-3">
                            <!-- WhatsApp -->
                            <div class="contato-info-card">
                                <div class="contato-info-icon" style="background: #25D366;">
                                    <i class="fab fa-whatsapp"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small text-muted mb-1">WhatsApp</div>
                                    <a href="https://wa.me/55<?php echo htmlspecialchars($contatoSecretaria['whatsapp']); ?>" 
                                       target="_blank"
                                       class="text-decoration-none fw-semibold">
                                        <?php echo htmlspecialchars($contatoSecretaria['whatsapp_formatado']); ?>
                                    </a>
                                </div>
                            </div>

                            <!-- E-mail -->
                            <div class="contato-info-card">
                                <div class="contato-info-icon" style="background: #2563eb;">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small text-muted mb-1">E-mail</div>
                                    <a href="mailto:<?php echo htmlspecialchars($contatoSecretaria['email']); ?>"
                                       class="text-decoration-none fw-semibold">
                                        <?php echo htmlspecialchars($contatoSecretaria['email']); ?>
                                    </a>
                                </div>
                            </div>

                            <!-- Telefone -->
                            <div class="contato-info-card">
                                <div class="contato-info-icon" style="background: #10b981;">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small text-muted mb-1">Telefone</div>
                                    <a href="tel:<?php echo htmlspecialchars(str_replace(['(', ')', ' ', '-'], '', $contatoSecretaria['telefone'])); ?>"
                                       class="text-decoration-none fw-semibold">
                                        <?php echo htmlspecialchars($contatoSecretaria['telefone']); ?>
                                    </a>
                                </div>
                            </div>

                            <!-- Horário -->
                            <div class="contato-info-card">
                                <div class="contato-info-icon" style="background: #f59e0b;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small text-muted mb-1">Horário de Atendimento</div>
                                    <div class="fw-semibold">
                                        <?php echo htmlspecialchars($contatoSecretaria['horario_atendimento']); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Endereço -->
                            <div class="contato-info-card">
                                <div class="contato-info-icon" style="background: #ef4444;">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small text-muted mb-1">Endereço</div>
                                    <div class="small">
                                        <?php echo htmlspecialchars($contatoSecretaria['endereco']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulário de Mensagem -->
            <div class="col-12 col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-paper-plane me-2"></i>Enviar Mensagem
                        </h5>
                        
                        <form method="POST" action="" id="formContato">
                            <input type="hidden" name="action" value="enviar_mensagem">
                            
                            <!-- Tipo de Assunto -->
                            <div class="mb-3">
                                <label for="tipo_assunto" class="form-label">Tipo de Assunto (Opcional)</label>
                                <select id="tipo_assunto" name="tipo_assunto" class="form-select">
                                    <option value="">Selecione...</option>
                                    <option value="Dúvida sobre aulas">Dúvida sobre aulas</option>
                                    <option value="Financeiro">Financeiro</option>
                                    <option value="Documentação">Documentação</option>
                                    <option value="Exames">Exames</option>
                                    <option value="Outro">Outro</option>
                                </select>
                            </div>

                            <!-- Assunto -->
                            <div class="mb-3">
                                <label for="assunto" class="form-label">
                                    Assunto <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       id="assunto" 
                                       name="assunto" 
                                       class="form-control"
                                       required
                                       minlength="5"
                                       placeholder="Ex: Dúvida sobre agendamento, Problema com pagamento...">
                                <small class="text-muted">Mínimo de 5 caracteres</small>
                            </div>

                            <!-- Aula Relacionada (Opcional) -->
                            <div class="mb-3">
                                <label for="aula_id" class="form-label">Aula Relacionada (Opcional)</label>
                                <select id="aula_id" name="aula_id" class="form-select">
                                    <option value="">Nenhuma aula específica</option>
                                    <?php foreach ($aulasParaSelect as $aula): ?>
                                    <option value="<?php echo $aula['id']; ?>">
                                        <?php echo htmlspecialchars($aula['label']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Mensagem -->
                            <div class="mb-3">
                                <label for="mensagem" class="form-label">
                                    Mensagem <span class="text-danger">*</span>
                                </label>
                                <textarea id="mensagem" 
                                          name="mensagem" 
                                          class="form-control"
                                          required
                                          minlength="10"
                                          rows="6"
                                          placeholder="Descreva sua dúvida, solicitação ou problema..."></textarea>
                                <small class="text-muted">Mínimo de 10 caracteres</small>
                            </div>

                            <!-- Botão -->
                            <button type="submit" class="btn btn-primary w-100" <?php echo !$tabelaExiste ? 'disabled' : ''; ?>>
                                <i class="fas fa-paper-plane me-2"></i>Enviar Mensagem
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Mensagens Enviadas -->
        <?php if ($tabelaExiste): ?>
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title mb-4">
                    <i class="fas fa-inbox me-2"></i>Mensagens Enviadas
                </h5>
                
                <?php if (empty($contatosEnviados)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                    <h6 class="fw-semibold mb-2">Nenhuma mensagem enviada</h6>
                    <p class="text-muted mb-0">
                        Suas mensagens enviadas para a secretaria aparecerão aqui.
                    </p>
                </div>
                <?php else: ?>
                <div class="contatos-list">
                    <?php foreach ($contatosEnviados as $contato): 
                        $statusInfo = formatarStatusContato($contato['status']);
                    ?>
                    <div class="contato-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge bg-<?php echo $statusInfo['class']; ?>">
                                        <?php echo $statusInfo['label']; ?>
                                    </span>
                                    <?php if ($contato['tipo_assunto']): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($contato['tipo_assunto']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <h6 class="mb-1 fw-semibold"><?php echo htmlspecialchars($contato['assunto']); ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($contato['criado_em'])); ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($contato['mensagem']); ?></p>
                        </div>
                        
                        <?php if ($contato['aula_data']): ?>
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                Aula relacionada: 
                                <?php echo ucfirst($contato['aula_tipo']); ?> - 
                                <?php echo date('d/m/Y', strtotime($contato['aula_data'])); ?> 
                                <?php echo date('H:i', strtotime($contato['aula_hora'])); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($contato['resposta']): ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fas fa-reply text-primary"></i>
                                <strong>Resposta da Secretaria</strong>
                                <?php if ($contato['respondido_por_nome']): ?>
                                <small class="text-muted">por <?php echo htmlspecialchars($contato['respondido_por_nome']); ?></small>
                                <?php endif; ?>
                                <?php if ($contato['respondido_em']): ?>
                                <small class="text-muted ms-auto">
                                    <?php echo date('d/m/Y H:i', strtotime($contato['respondido_em'])); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($contato['resposta']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // FASE 4 - CONTATO ALUNO - Validação frontend do formulário
        document.getElementById('formContato').addEventListener('submit', function(e) {
            const assunto = document.getElementById('assunto').value.trim();
            const mensagem = document.getElementById('mensagem').value.trim();
            
            if (assunto.length < 5) {
                e.preventDefault();
                alert('O assunto deve ter no mínimo 5 caracteres.');
                return false;
            }
            
            if (mensagem.length < 10) {
                e.preventDefault();
                alert('A mensagem deve ter no mínimo 10 caracteres.');
                return false;
            }
        });
    </script>
</body>
</html>

