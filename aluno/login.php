<?php
// =====================================================
// PÁGINA DE LOGIN PARA ALUNOS - SISTEMA CFC
// =====================================================

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

// Se já estiver logado como aluno, redirecionar para dashboard
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user && $user['tipo'] === 'aluno') {
        header('Location: dashboard.php');
        exit;
    }
}

$error = '';
$success = '';

// Processar formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpfOuEmail = trim($_POST['cpf'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    if (empty($cpfOuEmail) || empty($senha)) {
        $error = 'Por favor, preencha todos os campos';
    } else {
        try {
            // CPF: só números. E-mail: passar como está (Auth::getUserByLogin aceita os dois)
            $login = (strpos($cpfOuEmail, '@') !== false)
                ? strtolower($cpfOuEmail)
                : preg_replace('/[^0-9]/', '', $cpfOuEmail);
            
            $auth = new Auth();
            $result = $auth->login($login, $senha);
            
            if ($result['success']) {
                $user = getCurrentUser();
                if ($user && $user['tipo'] === 'aluno') {
                    $success = 'Login realizado com sucesso';
                    header('Refresh: 1; URL=dashboard.php');
                    exit;
                } else {
                    $error = 'Acesso negado. Esta conta não é de um aluno.';
                    $auth->logout();
                }
            } else {
                $error = $result['message'];
            }
        } catch (Exception $e) {
            $error = 'Erro interno do sistema. Tente novamente.';
            if (LOG_ENABLED) {
                error_log('Erro no login de aluno: ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Aluno | Sistema CFC</title>
    <link rel="stylesheet" href="../assets/css/login.css">
    <style>
        .login-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .login-header p {
            color: #666;
            font-size: 16px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>🎓 Login Aluno</h1>
                <p>Acesse seu painel de aulas</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="cpf" class="form-label">CPF ou e-mail</label>
                    <input type="text" id="cpf" name="cpf" class="form-control" 
                           placeholder="CPF (000.000.000-00) ou e-mail" required autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="senha" class="form-label">Senha</label>
                    <input type="password" id="senha" name="senha" class="form-control" 
                           placeholder="Digite sua senha" required>
                </div>
                
                <button type="submit" class="btn-login">
                    Entrar
                </button>
            </form>
            
            <div class="back-link">
                <a href="../index.php">← Voltar ao sistema principal</a>
            </div>
        </div>
    </div>
    
    <script>
        // Máscara para CPF; se contiver @, é e-mail — não aplicar máscara
        document.getElementById('cpf').addEventListener('input', function(e) {
            var v = e.target.value;
            if (v.indexOf('@') >= 0) return;
            var value = v.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
    </script>
</body>
</html>
