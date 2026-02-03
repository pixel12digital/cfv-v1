<?php
/**
 * Página de Contato com Secretaria - Instrutor
 * 
 * FASE 2 - Implementação: 2024
 * Arquivo: instrutor/contato.php
 * 
 * Funcionalidades:
 * - Exibir informações de contato da secretaria
 * - Formulário para enviar mensagem para secretaria
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// FASE 2 - Verificação de autenticação (padrão do portal)
// Arquivo: instrutor/contato.php (linha ~13)
$user = getCurrentUser();
if (!$user || $user['tipo'] !== 'instrutor') {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/login.php');
    exit();
}

$db = db();

// FASE 2 - Verificação de precisa_trocar_senha (padrão do portal)
// Arquivo: instrutor/contato.php (linha ~20)
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

// FASE 2 - Buscar dados do instrutor (padrão do portal)
// Arquivo: instrutor/contato.php (linha ~35)
$instrutor = $db->fetch("
    SELECT i.*, u.nome as nome_usuario, u.email as email_usuario 
    FROM instrutores i 
    LEFT JOIN usuarios u ON i.usuario_id = u.id 
    WHERE i.usuario_id = ?
", [$user['id']]);

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

$instrutor['nome'] = $instrutor['nome'] ?? $instrutor['nome_usuario'] ?? $user['nome'] ?? 'Instrutor';
$instrutorId = $instrutor['id'] ?? null;

// FASE 2 - Informações de contato da secretaria (fixas por enquanto)
// Arquivo: instrutor/contato.php (linha ~60)
// Fonte: index.php (linhas 4732-4740)
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

// FASE 2 - Processar envio de mensagem
// Arquivo: instrutor/contato.php (linha ~75)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enviar_mensagem') {
    $assunto = trim($_POST['assunto'] ?? '');
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
                $tableExists = $db->fetch("SHOW TABLES LIKE 'contatos_instrutor'");
                if (!$tableExists) {
                    // Executar migração
                    $migrationSql = file_get_contents(__DIR__ . '/../docs/scripts/migration_contatos_instrutor.sql');
                    if (preg_match('/CREATE TABLE IF NOT EXISTS contatos_instrutor[^;]+;/s', $migrationSql, $matches)) {
                        $db->query($matches[0]);
                    }
                }
            } catch (Exception $e) {
                error_log('Erro ao verificar/criar tabela contatos_instrutor: ' . $e->getMessage());
            }
            
            // Inserir mensagem
            try {
                if (!$instrutorId) {
                    throw new Exception('Instrutor não encontrado. Verifique seu cadastro.');
                }
                
                $sql = "INSERT INTO contatos_instrutor 
                        (instrutor_id, usuario_id, assunto, mensagem, aula_id, status, criado_em)
                        VALUES (?, ?, ?, ?, ?, 'aberto', NOW())";
                
                $params = [$instrutorId, $user['id'], $assunto, $mensagem, $aulaId];
                
                $result = $db->query($sql, $params);
                
                if ($result) {
                    $success = 'Mensagem enviada com sucesso! A secretaria entrará em contato em breve.';
                    
                    // Limpar formulário (redirecionar para evitar reenvio)
                    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                    header('Location: ' . $basePath . '/instrutor/contato.php?success=1');
                    exit();
                } else {
                    $error = 'Erro ao enviar mensagem. Tente novamente.';
                }
            } catch (Exception $e) {
                $error = 'Erro ao enviar mensagem: ' . $e->getMessage();
                if (defined('LOG_ENABLED') && LOG_ENABLED) {
                    error_log('Erro ao enviar mensagem do instrutor: ' . $e->getMessage());
                }
            }
        }
    }
}

// FASE 2 - Verificar mensagem de sucesso via GET
// Arquivo: instrutor/contato.php (linha ~145)
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = 'Mensagem enviada com sucesso! A secretaria entrará em contato em breve.';
}

// FASE 2 - Buscar aulas recentes/futuras do instrutor para o select (opcional)
// Arquivo: instrutor/contato.php (linha ~150)
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
        LIMIT 30
    ", [$instrutorId]);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#10b981" id="theme-color-meta">
    <title>Contatar Secretaria - <?php echo htmlspecialchars($instrutor['nome']); ?></title>
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
                <h1>Contatar Secretaria</h1>
                <div class="subtitle">Entre em contato com a secretaria do CFC</div>
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
            <!-- Informações de Contato -->
            <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 24px;">
                <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 20px; color: #1e293b;">
                    <i class="fas fa-address-card"></i> Informações de Contato
                </h2>
                
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <!-- WhatsApp -->
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px;">
                        <div style="width: 40px; height: 40px; background: #25D366; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">WhatsApp</div>
                            <a 
                                href="https://wa.me/55<?php echo htmlspecialchars($contatoSecretaria['whatsapp']); ?>" 
                                target="_blank"
                                style="font-size: 16px; font-weight: 600; color: #2563eb; text-decoration: none;"
                            >
                                <?php echo htmlspecialchars($contatoSecretaria['whatsapp_formatado']); ?>
                            </a>
                        </div>
                    </div>

                    <!-- E-mail -->
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px;">
                        <div style="width: 40px; height: 40px; background: #2563eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">E-mail</div>
                            <a 
                                href="mailto:<?php echo htmlspecialchars($contatoSecretaria['email']); ?>"
                                style="font-size: 16px; font-weight: 600; color: #2563eb; text-decoration: none;"
                            >
                                <?php echo htmlspecialchars($contatoSecretaria['email']); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Telefone -->
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px;">
                        <div style="width: 40px; height: 40px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">Telefone</div>
                            <a 
                                href="tel:<?php echo htmlspecialchars(str_replace(['(', ')', ' ', '-'], '', $contatoSecretaria['telefone'])); ?>"
                                style="font-size: 16px; font-weight: 600; color: #2563eb; text-decoration: none;"
                            >
                                <?php echo htmlspecialchars($contatoSecretaria['telefone']); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Horário -->
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px;">
                        <div style="width: 40px; height: 40px; background: #f59e0b; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">Horário de Atendimento</div>
                            <div style="font-size: 16px; font-weight: 600; color: #1e293b;">
                                <?php echo htmlspecialchars($contatoSecretaria['horario_atendimento']); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Endereço -->
                    <div style="display: flex; align-items: start; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px;">
                        <div style="width: 40px; height: 40px; background: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; flex-shrink: 0;">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">Endereço</div>
                            <div style="font-size: 14px; color: #1e293b;">
                                <?php echo htmlspecialchars($contatoSecretaria['endereco']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulário de Mensagem -->
            <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 24px;">
                <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 20px; color: #1e293b;">
                    <i class="fas fa-paper-plane"></i> Enviar Mensagem
                </h2>
                
                <form method="POST" action="" id="formContato">
                    <input type="hidden" name="action" value="enviar_mensagem">
                    
                    <!-- Assunto -->
                    <div style="margin-bottom: 16px;">
                        <label for="assunto" style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px; color: #333;">
                            Assunto <span style="color: #e74c3c;">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="assunto" 
                            name="assunto" 
                            required
                            minlength="5"
                            placeholder="Ex: Dúvida sobre agendamento, Problema com veículo..."
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;"
                        >
                        <small style="color: #666; font-size: 12px; margin-top: 4px; display: block;">
                            Mínimo de 5 caracteres
                        </small>
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

                    <!-- Mensagem -->
                    <div style="margin-bottom: 20px;">
                        <label for="mensagem" style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px; color: #333;">
                            Mensagem <span style="color: #e74c3c;">*</span>
                        </label>
                        <textarea 
                            id="mensagem" 
                            name="mensagem" 
                            required
                            minlength="10"
                            rows="8"
                            placeholder="Descreva sua dúvida, solicitação ou problema..."
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
                        <i class="fas fa-paper-plane"></i> Enviar Mensagem
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // FASE 2 - Validação frontend do formulário
        // Arquivo: instrutor/contato.php (linha ~340)
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

