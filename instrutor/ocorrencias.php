<?php
/**
 * Página de Ocorrências do Instrutor
 * 
 * FASE 2 - Implementação: 2024
 * Arquivo: instrutor/ocorrencias.php
 * 
 * Funcionalidades:
 * - Formulário para registrar ocorrências
 * - Listagem das ocorrências registradas pelo instrutor
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// FASE 2 - Verificação de autenticação (padrão do portal)
// Arquivo: instrutor/ocorrencias.php (linha ~13)
$user = getCurrentUser();
if (!$user || $user['tipo'] !== 'instrutor') {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/login.php');
    exit();
}

$db = db();

// FASE 2 - Verificação de precisa_trocar_senha (padrão do portal)
// Arquivo: instrutor/ocorrencias.php (linha ~20)
try {
    $checkColumn = $db->fetch("SHOW COLUMNS FROM usuarios LIKE 'precisa_trocar_senha'");
    if ($checkColumn) {
        $usuarioCompleto = $db->fetch("SELECT precisa_trocar_senha FROM usuarios WHERE id = ?", [$user['id']]);
        if ($usuarioCompleto && isset($usuarioCompleto['precisa_trocar_senha']) && $usuarioCompleto['precisa_trocar_senha'] == 1) {
            $currentPage = basename($_SERVER['PHP_SELF']);
            if ($currentPage !== 'trocar-senha.php') {
                $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                header('Location: ' . $basePath . '/instrutor/trocar-senha.php?forcado=1');
                exit();
            }
        }
    }
} catch (Exception $e) {
    // Continuar normalmente
}

// FASE 2 - Correção: Buscar dados do instrutor usando função centralizada
// Arquivo: instrutor/ocorrencias.php (linha ~35)
// Usa getCurrentInstrutorId() (mesmo padrão da Fase 1)
$instrutorId = getCurrentInstrutorId($user['id']);

// Buscar dados completos do instrutor para exibição (se existir)
$instrutor = null;
if ($instrutorId) {
    $instrutor = $db->fetch("
        SELECT i.*, u.nome as nome_usuario, u.email as email_usuario 
        FROM instrutores i 
        LEFT JOIN usuarios u ON i.usuario_id = u.id 
        WHERE i.id = ?
    ", [$instrutorId]);
}

// Se não encontrar, criar estrutura básica para exibição
if (!$instrutor) {
    $instrutor = [
        'id' => $instrutorId,
        'usuario_id' => $user['id'],
        'nome' => $user['nome'] ?? 'Instrutor',
        'nome_usuario' => $user['nome'] ?? 'Instrutor',
        'email_usuario' => $user['email'] ?? '',
        'credencial' => null,
        'cfc_id' => null
    ];
}

$instrutor['nome'] = $instrutor['nome'] ?? $instrutor['nome_usuario'] ?? $user['nome'] ?? 'Instrutor';

$success = '';
$error = '';

// FASE 2 - Correção: Processamento movido para API (admin/api/ocorrencias-instrutor.php)
// Arquivo: instrutor/ocorrencias.php (linha ~60)
// O formulário agora envia via AJAX para a API
// Este bloco foi mantido apenas para compatibilidade, mas não será usado
if (false && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar_ocorrencia') {
    $tipo = $_POST['tipo'] ?? '';
    $dataOcorrencia = $_POST['data_ocorrencia'] ?? date('Y-m-d');
    $aulaId = !empty($_POST['aula_id']) ? (int)$_POST['aula_id'] : null;
    $descricao = trim($_POST['descricao'] ?? '');
    
    // Validações
    if (empty($tipo)) {
        $error = 'Tipo da ocorrência é obrigatório.';
    } elseif (!in_array($tipo, ['atraso_aluno', 'problema_veiculo', 'infraestrutura', 'comportamento_aluno', 'outro'])) {
        $error = 'Tipo de ocorrência inválido.';
    } elseif (empty($dataOcorrencia)) {
        $error = 'Data da ocorrência é obrigatória.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataOcorrencia)) {
        $error = 'Formato de data inválido.';
    } elseif (empty($descricao)) {
        $error = 'Descrição é obrigatória.';
    } elseif (strlen($descricao) < 10) {
        $error = 'Descrição deve ter no mínimo 10 caracteres.';
    } else {
        // Validar se aula pertence ao instrutor (se fornecida)
        if ($aulaId && $instrutorId) {
            $aulaValida = $db->fetch("SELECT id FROM aulas WHERE id = ? AND instrutor_id = ?", [$aulaId, $instrutorId]);
            if (!$aulaValida) {
                $error = 'Aula não encontrada ou não pertence a você.';
            }
        }
        
        if (empty($error)) {
            // Verificar se tabela existe, se não existir, criar
            try {
                $tableExists = $db->fetch("SHOW TABLES LIKE 'ocorrencias_instrutor'");
                if (!$tableExists) {
                    // Executar migração
                    $migrationSql = file_get_contents(__DIR__ . '/../docs/scripts/migration_ocorrencias_instrutor.sql');
                    // Extrair apenas o CREATE TABLE (simplificado)
                    if (preg_match('/CREATE TABLE IF NOT EXISTS ocorrencias_instrutor[^;]+;/s', $migrationSql, $matches)) {
                        $db->query($matches[0]);
                    }
                }
            } catch (Exception $e) {
                // Tabela pode já existir ou erro na criação
                error_log('Erro ao verificar/criar tabela ocorrencias_instrutor: ' . $e->getMessage());
            }
            
            // Inserir ocorrência
            try {
                if (!$instrutorId) {
                    throw new Exception('Instrutor não encontrado. Verifique seu cadastro.');
                }
                
                $sql = "INSERT INTO ocorrencias_instrutor 
                        (instrutor_id, usuario_id, tipo, data_ocorrencia, aula_id, descricao, status, criado_em)
                        VALUES (?, ?, ?, ?, ?, ?, 'aberta', NOW())";
                
                $params = [$instrutorId, $user['id'], $tipo, $dataOcorrencia, $aulaId, $descricao];
                
                $result = $db->query($sql, $params);
                
                if ($result) {
                    $success = 'Ocorrência registrada com sucesso!';
                    
                    // Limpar formulário (redirecionar para evitar reenvio)
                    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                    header('Location: ' . $basePath . '/instrutor/ocorrencias.php?success=1');
                    exit();
                } else {
                    $error = 'Erro ao registrar ocorrência. Tente novamente.';
                }
            } catch (Exception $e) {
                $error = 'Erro ao registrar ocorrência: ' . $e->getMessage();
                if (defined('LOG_ENABLED') && LOG_ENABLED) {
                    error_log('Erro ao registrar ocorrência do instrutor: ' . $e->getMessage());
                }
            }
        }
    }
}

// FASE 2 - Verificar mensagem de sucesso via GET
// Arquivo: instrutor/ocorrencias.php (linha ~130)
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = 'Ocorrência registrada com sucesso!';
}

// FASE 2 - Buscar aulas recentes/futuras do instrutor para o select
// Arquivo: instrutor/ocorrencias.php (linha ~135)
$aulasParaSelect = [];
if ($instrutorId) {
    $aulasParaSelect = $db->fetchAll("
        SELECT a.id, a.data_aula, a.hora_inicio, al.nome as aluno_nome
        FROM aulas a
        JOIN alunos al ON a.aluno_id = al.id
        WHERE a.instrutor_id = ?
          AND a.data_aula >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND a.status != 'cancelada'
        ORDER BY a.data_aula DESC, a.hora_inicio DESC
        LIMIT 50
    ", [$instrutorId]);
}

// FASE 2 - Correção: Buscar ocorrências do instrutor usando instrutor_id correto
// Arquivo: instrutor/ocorrencias.php (linha ~150)
// Agora usa getCurrentInstrutorId() para garantir que temos o ID correto
$ocorrencias = [];
if ($instrutorId) {
    try {
        $tableExists = $db->fetch("SHOW TABLES LIKE 'ocorrencias_instrutor'");
        if ($tableExists) {
            $ocorrencias = $db->fetchAll("
                SELECT o.*, 
                       a.data_aula as aula_data, a.hora_inicio as aula_hora,
                       al.nome as aluno_nome
                FROM ocorrencias_instrutor o
                LEFT JOIN aulas a ON o.aula_id = a.id
                LEFT JOIN alunos al ON a.aluno_id = al.id
                WHERE o.instrutor_id = ?
                ORDER BY o.criado_em DESC
            ", [$instrutorId]);
        }
    } catch (Exception $e) {
        // Tabela não existe ainda ou erro na query
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            error_log('[OCORRENCIAS_PAGE] Erro ao buscar ocorrências: ' . $e->getMessage());
        }
    }
} else {
    // FASE 2 - Correção: Log detalhado se instrutor_id não foi encontrado
    // Arquivo: instrutor/ocorrencias.php (linha ~170)
    if (defined('LOG_ENABLED') && LOG_ENABLED) {
        error_log(sprintf(
            '[OCORRENCIAS_PAGE] Instrutor não encontrado - usuario_id=%d, tipo=%s, email=%s, timestamp=%s',
            $user['id'],
            $user['tipo'] ?? 'não definido',
            $user['email'] ?? 'não definido',
            date('Y-m-d H:i:s')
        ));
    }
}

// FASE 2 - Mapear tipos de ocorrência para exibição
// Arquivo: instrutor/ocorrencias.php (linha ~170)
$tiposOcorrencia = [
    'atraso_aluno' => 'Atraso do Aluno',
    'problema_veiculo' => 'Problema com Veículo',
    'infraestrutura' => 'Infraestrutura',
    'comportamento_aluno' => 'Comportamento do Aluno',
    'outro' => 'Outro'
];

$statusOcorrencia = [
    'aberta' => 'Aberta',
    'em_analise' => 'Em Análise',
    'resolvida' => 'Resolvida',
    'arquivada' => 'Arquivada'
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#10b981" id="theme-color-meta">
    <title>Registrar Ocorrência - <?php echo htmlspecialchars($instrutor['nome']); ?></title>
    <link rel="stylesheet" href="../assets/css/theme-tokens.css">
    <link rel="stylesheet" href="../assets/css/mobile-first.css">
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        (function(){var m=document.getElementById('theme-color-meta');if(!m)return;function u(){var d=window.matchMedia('(prefers-color-scheme: dark)').matches;m.setAttribute('content',d?'#1e293b':'#10b981');}u();window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',u);})();
    </script>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>Registrar Ocorrência</h1>
                <div class="subtitle">Registre ocorrências durante suas aulas</div>
            </div>
            <a href="dashboard.php" style="color: white; text-decoration: none; padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 8px;">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="container" style="max-width: 1000px; margin: 0 auto; padding: 20px 16px;">
        <!-- Mensagens -->
        <?php if ($success): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Formulário -->
            <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 24px;">
                <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 20px; color: #1e293b;">
                    <i class="fas fa-plus-circle"></i> Nova Ocorrência
                </h2>
                
                <form method="POST" action="" id="formOcorrencia">
                    <input type="hidden" name="action" value="registrar_ocorrencia">
                    
                    <!-- Tipo -->
                    <div style="margin-bottom: 16px;">
                        <label for="tipo" style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px; color: #333;">
                            Tipo da Ocorrência <span style="color: #e74c3c;">*</span>
                        </label>
                        <select 
                            id="tipo" 
                            name="tipo" 
                            required
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;"
                        >
                            <option value="">Selecione o tipo</option>
                            <?php foreach ($tiposOcorrencia as $valor => $label): ?>
                            <option value="<?php echo htmlspecialchars($valor); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Data -->
                    <div style="margin-bottom: 16px;">
                        <label for="data_ocorrencia" style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px; color: #333;">
                            Data da Ocorrência <span style="color: #e74c3c;">*</span>
                        </label>
                        <input 
                            type="date" 
                            id="data_ocorrencia" 
                            name="data_ocorrencia" 
                            value="<?php echo htmlspecialchars(date('Y-m-d')); ?>"
                            required
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;"
                        >
                    </div>

                    <!-- Aula Relacionada (Opcional) -->
                    <div style="margin-bottom: 16px;">
                        <label for="aula_id" style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px; color: #333;">
                            Aula Relacionada (Opcional)
                        </label>
                        <select 
                            id="aula_id" 
                            name="aula_id" 
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;"
                        >
                            <option value="">Nenhuma aula específica</option>
                            <?php foreach ($aulasParaSelect as $aula): ?>
                            <option value="<?php echo $aula['id']; ?>">
                                <?php echo htmlspecialchars($aula['aluno_nome']); ?> - 
                                <?php echo date('d/m/Y', strtotime($aula['data_aula'])); ?> 
                                <?php echo date('H:i', strtotime($aula['hora_inicio'])); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Descrição -->
                    <div style="margin-bottom: 20px;">
                        <label for="descricao" style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px; color: #333;">
                            Descrição Detalhada <span style="color: #e74c3c;">*</span>
                        </label>
                        <textarea 
                            id="descricao" 
                            name="descricao" 
                            required
                            minlength="10"
                            rows="6"
                            placeholder="Descreva detalhadamente a ocorrência..."
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; resize: vertical;"
                        ></textarea>
                        <small style="color: #666; font-size: 12px; margin-top: 4px; display: block;">
                            Mínimo de 10 caracteres
                        </small>
                    </div>

                    <!-- Botão -->
                    <button 
                        type="submit" 
                        style="width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer;"
                    >
                        <i class="fas fa-save"></i> Registrar Ocorrência
                    </button>
                </form>
            </div>

            <!-- Listagem -->
            <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 24px;">
                <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 20px; color: #1e293b;">
                    <i class="fas fa-list"></i> Ocorrências Registradas
                </h2>
                
                <?php if (empty($ocorrencias)): ?>
                <div style="text-align: center; padding: 40px 20px;">
                    <i class="fas fa-inbox" style="font-size: 48px; color: #cbd5e1; margin-bottom: 16px;"></i>
                    <h3 style="color: #64748b; margin-bottom: 8px;">Nenhuma ocorrência registrada</h3>
                    <p style="color: #94a3b8;">Suas ocorrências aparecerão aqui.</p>
                </div>
                <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px; max-height: 600px; overflow-y: auto;">
                    <?php foreach ($ocorrencias as $ocorrencia): ?>
                    <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; background: #f8fafc;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <span style="padding: 4px 8px; background: #3b82f6; color: white; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                        <?php echo htmlspecialchars($tiposOcorrencia[$ocorrencia['tipo']] ?? $ocorrencia['tipo']); ?>
                                    </span>
                                    <span style="padding: 4px 8px; background: <?php 
                                        echo $ocorrencia['status'] === 'aberta' ? '#f59e0b' : 
                                            ($ocorrencia['status'] === 'resolvida' ? '#10b981' : '#64748b'); 
                                    ?>; color: white; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                        <?php echo htmlspecialchars($statusOcorrencia[$ocorrencia['status']] ?? $ocorrencia['status']); ?>
                                    </span>
                                </div>
                                <div style="font-size: 14px; color: #64748b; margin-bottom: 4px;">
                                    <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($ocorrencia['data_ocorrencia'])); ?>
                                    <?php if ($ocorrencia['aula_data']): ?>
                                    <span style="margin-left: 12px;">
                                        <i class="fas fa-book"></i> Aula: <?php echo date('d/m/Y H:i', strtotime($ocorrencia['aula_data'] . ' ' . $ocorrencia['aula_hora'])); ?>
                                        <?php if ($ocorrencia['aluno_nome']): ?>
                                        - <?php echo htmlspecialchars($ocorrencia['aluno_nome']); ?>
                                        <?php endif; ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="font-size: 12px; color: #94a3b8;">
                                <?php echo date('d/m/Y H:i', strtotime($ocorrencia['criado_em'])); ?>
                            </div>
                        </div>
                        <div style="font-size: 14px; color: #1e293b; margin-top: 8px;">
                            <?php 
                            $descricaoResumo = htmlspecialchars($ocorrencia['descricao']);
                            if (strlen($descricaoResumo) > 150) {
                                echo substr($descricaoResumo, 0, 150) . '...';
                            } else {
                                echo $descricaoResumo;
                            }
                            ?>
                        </div>
                        <?php if ($ocorrencia['resolucao']): ?>
                        <div style="margin-top: 12px; padding: 12px; background: #f0fdf4; border-left: 3px solid #10b981; border-radius: 4px;">
                            <div style="font-size: 12px; font-weight: 600; color: #059669; margin-bottom: 4px;">
                                <i class="fas fa-check-circle"></i> Resolução:
                            </div>
                            <div style="font-size: 14px; color: #065f46;">
                                <?php echo htmlspecialchars($ocorrencia['resolucao']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // FASE 2 - Correção: Envio via API e atualização dinâmica da listagem
        // Arquivo: instrutor/ocorrencias.php (linha ~431)
        
        // Função para mostrar mensagem de erro
        function showError(message) {
            const errorDiv = document.querySelector('.alert-danger');
            const successDiv = document.querySelector('.alert-success');
            
            if (successDiv) successDiv.style.display = 'none';
            
            if (errorDiv) {
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
                errorDiv.style.display = 'block';
            } else {
                // Criar div de erro se não existir
                const container = document.querySelector('.container');
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger';
                alertDiv.style.cssText = 'background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
                container.insertBefore(alertDiv, container.firstChild);
            }
        }
        
        // Função para mostrar mensagem de sucesso
        function showSuccess(message) {
            const errorDiv = document.querySelector('.alert-danger');
            const successDiv = document.querySelector('.alert-success');
            
            if (errorDiv) errorDiv.style.display = 'none';
            
            if (successDiv) {
                successDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
                successDiv.style.display = 'block';
            } else {
                // Criar div de sucesso se não existir
                const container = document.querySelector('.container');
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success';
                alertDiv.style.cssText = 'background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;';
                alertDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
                container.insertBefore(alertDiv, container.firstChild);
            }
        }
        
        // FASE 2 - Correção: Usar caminho absoluto robusto baseado na URL atual
        // Arquivo: instrutor/ocorrencias.php (linha ~492)
        // Descobrir /cfc-bom-conselho independente da página atual
        const BASE_PATH = window.location.pathname.split('/instrutor/')[0];
        
        // Caminho absoluto para a API, baseado na raiz do projeto
        const API_OCORRENCIAS_URL = `${BASE_PATH}/admin/api/ocorrencias-instrutor.php`;
        
        // Debug: log do caminho final
        console.log('[Ocorrências] URL da API final:', API_OCORRENCIAS_URL);
        
        // Função para recarregar a listagem de ocorrências
        async function reloadOcorrencias() {
            try {
                const response = await fetch(API_OCORRENCIAS_URL, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    // Recarregar página para atualizar listagem (pode melhorar depois com atualização dinâmica)
                    window.location.reload();
                }
            } catch (error) {
                console.error('Erro ao recarregar ocorrências:', error);
            }
        }
        
        // Interceptar submit do formulário e enviar via API
        document.getElementById('formOcorrencia').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const form = this;
            const formData = new FormData(form);
            const descricao = formData.get('descricao').trim();
            
            // Validação frontend
            if (descricao.length < 10) {
                showError('A descrição deve ter no mínimo 10 caracteres.');
                return false;
            }
            
            // Desabilitar botão durante envio
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
            
            try {
                // FASE 2 - Correção: Enviar para API usando constante padronizada
                // Arquivo: instrutor/ocorrencias.php (linha ~540)
                const response = await fetch(API_OCORRENCIAS_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        tipo: formData.get('tipo'),
                        data_ocorrencia: formData.get('data_ocorrencia'),
                        aula_id: formData.get('aula_id') || null,
                        descricao: descricao
                    })
                });
                
                // Verificar se a resposta é válida
                if (!response.ok) {
                    // Se for 404, o arquivo não foi encontrado
                    if (response.status === 404) {
                        console.error('[Ocorrências] API não encontrada. Caminho:', API_OCORRENCIAS_URL, 'Status:', response.status);
                        showError('Erro: API não encontrada. Verifique o caminho do servidor.');
                        return false;
                    }
                    
                    // Tentar ler como texto primeiro para debug
                    const text = await response.text();
                    console.error('Resposta de erro:', text);
                    
                    // Tentar parsear como JSON se possível
                    try {
                        const errorResult = JSON.parse(text);
                        showError(errorResult.message || 'Erro ao registrar ocorrência.');
                    } catch (e) {
                        // Se não for JSON, mostrar erro genérico
                        showError('Erro ao comunicar com o servidor. Status: ' + response.status);
                    }
                    return false;
                }
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess(result.message || 'Ocorrência registrada com sucesso!');
                    form.reset();
                    
                    // Recarregar listagem após breve delay
                    setTimeout(() => {
                        reloadOcorrencias();
                    }, 500);
                } else {
                    showError(result.message || 'Erro ao registrar ocorrência.');
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                console.error('[Ocorrências] Erro na requisição:', error);
                console.error('[Ocorrências] Caminho da API usado:', API_OCORRENCIAS_URL);
                showError('Erro de conexão. Verifique sua conexão e tente novamente.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
            
            return false;
        });
    </script>
</body>
</html>

