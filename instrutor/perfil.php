<?php
/**
 * Página de Perfil do Instrutor
 * Permite ao instrutor editar seus próprios dados básicos
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Verificar autenticação
$user = getCurrentUser();
if (!$user || $user['tipo'] !== 'instrutor') {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/login.php');
    exit();
}

$db = db();

// Verificar se precisa trocar senha
try {
    $checkColumn = $db->fetch("SHOW COLUMNS FROM usuarios LIKE 'precisa_trocar_senha'");
    if ($checkColumn) {
        $usuarioCompleto = $db->fetch("SELECT precisa_trocar_senha FROM usuarios WHERE id = ?", [$user['id']]);
        if ($usuarioCompleto && isset($usuarioCompleto['precisa_trocar_senha']) && $usuarioCompleto['precisa_trocar_senha'] == 1) {
            $basePath = defined('BASE_PATH') ? BASE_PATH : '';
            header('Location: ' . $basePath . '/instrutor/trocar-senha.php?forcado=1');
            exit();
        }
    }
} catch (Exception $e) {
    // Continuar normalmente
}

// Buscar dados completos do usuário e instrutor
$usuarioCompleto = $db->fetch("
    SELECT u.*, i.id as instrutor_id, i.cfc_id, i.credencial, i.foto as foto_instrutor, 
           i.email as email_instrutor, i.telefone as telefone_instrutor,
           c.nome as cfc_nome
    FROM usuarios u
    LEFT JOIN instrutores i ON i.usuario_id = u.id
    LEFT JOIN cfcs c ON c.id = i.cfc_id
    WHERE u.id = ?
", [$user['id']]);

if (!$usuarioCompleto) {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/instrutor/dashboard.php');
    exit();
}

// Priorizar dados da tabela instrutores, com fallback para usuarios
// Tratar strings vazias como NULL para garantir fallback correto
$fotoPerfil = !empty($usuarioCompleto['foto_instrutor']) ? $usuarioCompleto['foto_instrutor'] : null;
$emailPerfil = !empty($usuarioCompleto['email_instrutor']) ? $usuarioCompleto['email_instrutor'] : ($usuarioCompleto['email'] ?? '');
$telefonePerfil = !empty($usuarioCompleto['telefone_instrutor']) ? $usuarioCompleto['telefone_instrutor'] : ($usuarioCompleto['telefone'] ?? '');

// Nota: A atualização agora é feita via API (AJAX) para suportar upload de foto
// Mantemos apenas validação básica aqui se necessário

// Inicializar variáveis para evitar warnings
$success = '';
$error = '';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#10b981" id="theme-color-meta">
    <title>Meu Perfil - <?php echo htmlspecialchars($usuarioCompleto['nome']); ?></title>
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
                <h1>Meu Perfil</h1>
                <div class="subtitle">Edite suas informações pessoais</div>
            </div>
            <a href="dashboard.php" style="color: white; text-decoration: none; padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 8px;">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="container" style="max-width: 800px; margin: 0 auto; padding: 20px 16px;">
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

        <!-- Formulário -->
        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 24px;">
            <form id="formPerfil" enctype="multipart/form-data">
                
                <!-- Foto do Perfil -->
                <div style="margin-bottom: 24px; text-align: center;">
                    <label style="display: block; margin-bottom: 12px; font-weight: 600; color: #333;">
                        <i class="fas fa-camera"></i> Foto do Perfil
                    </label>
                    <div style="position: relative; display: inline-block;">
                        <div id="foto-preview-container" style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 3px solid #2563eb; margin: 0 auto; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                            <?php if ($fotoPerfil): ?>
                                <img id="foto-preview" src="../<?php echo htmlspecialchars($fotoPerfil); ?>" alt="Foto do perfil" style="width: 100%; height: 100%; object-fit: cover;" onerror="var img = this; var placeholder = document.getElementById('foto-placeholder'); if (placeholder) { img.style.display='none'; placeholder.style.display='flex'; }">
                                <div id="foto-placeholder" style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 2.5rem; font-weight: 600;">
                                    <?php
                                    $iniciais = strtoupper(substr($usuarioCompleto['nome'] ?? 'I', 0, 1));
                                    $nomes = explode(' ', $usuarioCompleto['nome'] ?? '');
                                    if (count($nomes) > 1) {
                                        $iniciais = strtoupper(substr($nomes[0], 0, 1) . substr(end($nomes), 0, 1));
                                    }
                                    echo htmlspecialchars($iniciais);
                                    ?>
                                </div>
                            <?php else: ?>
                                <?php
                                $iniciais = strtoupper(substr($usuarioCompleto['nome'] ?? 'I', 0, 1));
                                $nomes = explode(' ', $usuarioCompleto['nome'] ?? '');
                                if (count($nomes) > 1) {
                                    $iniciais = strtoupper(substr($nomes[0], 0, 1) . substr(end($nomes), 0, 1));
                                }
                                ?>
                                <div id="foto-placeholder" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 2.5rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($iniciais); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <label for="foto" style="position: absolute; bottom: 0; right: 0; background: #2563eb; color: white; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                            <i class="fas fa-camera" style="font-size: 14px;"></i>
                            <input type="file" id="foto" name="foto" accept="image/*" style="display: none;" onchange="previewFotoPerfil(this)">
                        </label>
                    </div>
                    <small style="display: block; margin-top: 8px; color: #666; font-size: 0.85rem;">
                        📷 JPG, PNG, GIF até 2MB
                    </small>
                </div>

                <!-- Nome -->
                <div style="margin-bottom: 20px;">
                    <label for="nome" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Nome Completo <span style="color: #e74c3c;">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="nome" 
                        name="nome" 
                        value="<?php echo htmlspecialchars($usuarioCompleto['nome'] ?? ''); ?>" 
                        required
                        style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;"
                    >
                </div>

                <!-- Email -->
                <div style="margin-bottom: 20px;">
                    <label for="email" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        E-mail <span style="color: #e74c3c;">*</span>
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?php echo htmlspecialchars($emailPerfil); ?>" 
                        required
                        style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;"
                    >
                </div>

                <!-- Telefone -->
                <div style="margin-bottom: 20px;">
                    <label for="telefone" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Telefone/Celular
                    </label>
                    <input 
                        type="tel" 
                        id="telefone" 
                        name="telefone" 
                        value="<?php echo htmlspecialchars($telefonePerfil); ?>" 
                        style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;"
                    >
                </div>

                <!-- Campos somente leitura -->
                <hr style="margin: 24px 0; border: none; border-top: 1px solid #eee;">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        CPF
                    </label>
                    <input 
                        type="text" 
                        value="<?php echo htmlspecialchars($usuarioCompleto['cpf'] ?? 'Não informado'); ?>" 
                        readonly
                        style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; background: #f5f5f5; color: #666;"
                    >
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        CFC Vinculado
                    </label>
                    <input 
                        type="text" 
                        value="<?php echo htmlspecialchars($usuarioCompleto['cfc_nome'] ?? 'Não vinculado'); ?>" 
                        readonly
                        style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; background: #f5f5f5; color: #666;"
                    >
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Tipo de Usuário
                    </label>
                    <input 
                        type="text" 
                        value="Instrutor" 
                        readonly
                        style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; background: #f5f5f5; color: #666;"
                    >
                </div>

                <!-- Botões -->
                <div style="display: flex; gap: 12px; margin-top: 32px;">
                    <button 
                        type="submit" 
                        id="btnSalvar"
                        style="flex: 1; padding: 12px 24px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer;"
                    >
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                    <a 
                        href="dashboard.php" 
                        style="padding: 12px 24px; background: #f0f0f0; color: #333; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; text-decoration: none; display: flex; align-items: center; justify-content: center;"
                    >
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Preview da foto
        function previewFotoPerfil(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validar tipo
                if (!file.type.startsWith('image/')) {
                    alert('⚠️ Por favor, selecione apenas arquivos de imagem (JPG, PNG, GIF)');
                    input.value = '';
                    return;
                }
                
                // Validar tamanho (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('⚠️ O arquivo deve ter no máximo 2MB');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('foto-preview');
                    const placeholder = document.getElementById('foto-placeholder');
                    const container = document.getElementById('foto-preview-container');
                    
                    if (!preview) {
                        // Criar elemento img se não existir
                        const img = document.createElement('img');
                        img.id = 'foto-preview';
                        img.src = e.target.result;
                        img.alt = 'Foto do perfil';
                        img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
                        img.onerror = "this.style.display='none'; this.nextElementSibling.style.display='flex';";
                        container.insertBefore(img, placeholder);
                    } else {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    
                    if (placeholder) placeholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Salvar perfil via API
        document.getElementById('formPerfil').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btnSalvar = document.getElementById('btnSalvar');
            const btnOriginalText = btnSalvar.innerHTML;
            btnSalvar.disabled = true;
            btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            
            try {
                const formData = new FormData();
                formData.append('email', document.getElementById('email').value);
                formData.append('telefone', document.getElementById('telefone').value);
                
                const fotoInput = document.getElementById('foto');
                if (fotoInput.files && fotoInput.files[0]) {
                    formData.append('foto', fotoInput.files[0]);
                }
                
                const response = await fetch('api/perfil.php', {
                    method: 'POST', // Usar POST ao invés de PUT para multipart/form-data funcionar corretamente
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Mostrar mensagem de sucesso
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success';
                    alertDiv.style.cssText = 'background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;';
                    alertDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + (result.message || 'Perfil atualizado com sucesso!');
                    
                    const container = document.querySelector('.container');
                    container.insertBefore(alertDiv, container.firstChild);
                    
                    // Atualizar preview da foto se houver
                    if (result.perfil && result.perfil.foto) {
                        const preview = document.getElementById('foto-preview');
                        const placeholder = document.getElementById('foto-placeholder');
                        if (preview) {
                            preview.src = '../' + result.perfil.foto;
                            preview.style.display = 'block';
                        }
                        if (placeholder) placeholder.style.display = 'none';
                    }
                    
                    // Atualizar campos de telefone e email com os dados retornados
                    if (result.perfil) {
                        if (result.perfil.telefone !== undefined) {
                            document.getElementById('telefone').value = result.perfil.telefone || '';
                        }
                        if (result.perfil.email !== undefined) {
                            document.getElementById('email').value = result.perfil.email || '';
                        }
                        
                        // Atualizar foto se houver
                        if (result.perfil.foto) {
                            const preview = document.getElementById('foto-preview');
                            const placeholder = document.getElementById('foto-placeholder');
                            if (preview) {
                                preview.src = '../' + result.perfil.foto;
                                preview.style.display = 'block';
                            }
                            if (placeholder) placeholder.style.display = 'none';
                        }
                    }
                    
                    // Recarregar dados do servidor para garantir sincronização
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                    
                    // Scroll para o topo
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    throw new Error(result.error || 'Erro ao atualizar perfil');
                }
            } catch (error) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger';
                alertDiv.style.cssText = 'background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + error.message;
                
                const container = document.querySelector('.container');
                container.insertBefore(alertDiv, container.firstChild);
                
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                setTimeout(() => alertDiv.remove(), 5000);
            } finally {
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = btnOriginalText;
            }
        });
    </script>
</body>
</html>

