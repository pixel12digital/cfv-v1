<?php
// SCRIPT DE TESTE: SIMULAR LOGIN DE ALUNO REAL (SEM CPF)
// Usando o sistema RBAC real

require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

echo "<h2>🧪 TESTE DE LOGIN DE ALUNO REAL (SISTEMA RBAC)</h2>";

try {
    $db = db();
    
    // 1. Buscar alunos reais via RBAC
    echo "<h3>1. Buscando alunos via RBAC</h3>";
    $alunos = $db->fetchAll("
        SELECT u.id, u.nome, u.email, u.password, u.status 
        FROM usuarios u 
        JOIN usuario_roles ur ON u.id = ur.usuario_id 
        WHERE ur.role = 'ALUNO' AND u.status = 'ativo'
        LIMIT 3
    ");
    
    if (empty($alunos)) {
        echo "❌ Nenhum aluno encontrado via RBAC<br>";
        exit;
    }
    
    echo "✅ Encontrados " . count($alunos) . " alunos via RBAC<br>";
    
    $alunoTeste = $alunos[0];
    echo "Aluno selecionado: " . $alunoTeste['nome'] . "<br>";
    echo "Email: " . $alunoTeste['email'] . "<br>";
    
    // 2. Testar login com email (como o sistema faz para não-alunos)
    echo "<h3>2. Testando login com email</h3>";
    
    // Limpar sessão
    session_destroy();
    session_start();
    
    // Criar instância Auth e testar login
    $auth = new Auth();
    $result = $auth->login($alunoTeste['email'], '123456');
    
    echo "Resultado do login:<br>";
    echo "Success: " . ($result['success'] ? "✅ SIM" : "❌ NÃO") . "<br>";
    echo "Message: " . $result['message'] . "<br>";
    
    if ($result['success']) {
        echo "✅ Login bem-sucedido!<br>";
        
        // 3. Verificar sessão criada
        echo "<h3>3. Verificando sessão criada</h3>";
        echo "user_id: " . ($_SESSION['user_id'] ?? 'NÃO EXISTE') . "<br>";
        echo "user_email: " . ($_SESSION['user_email'] ?? 'NÃO EXISTE') . "<br>";
        echo "user_tipo: " . ($_SESSION['user_tipo'] ?? 'NÃO EXISTE') . "<br>";
        echo "last_activity: " . ($_SESSION['last_activity'] ?? 'NÃO EXISTE') . "<br>";
        
        // 4. Testar getCurrentUser()
        echo "<h3>4. Testando getCurrentUser()</h3>";
        $currentUser = getCurrentUser();
        if ($currentUser) {
            echo "✅ Usuário recuperado:<br>";
            echo "ID: " . $currentUser['id'] . "<br>";
            echo "Nome: " . $currentUser['nome'] . "<br>";
            echo "Tipo: " . ($currentUser['tipo'] ?? 'NÃO DEFINIDO') . "<br>";
        } else {
            echo "❌ getCurrentUser() retornou NULL<br>";
        }
        
        // 5. Testar acesso ao dashboard
        echo "<h3>5. Testando acesso ao dashboard</h3>";
        $dashboardLogged = isLoggedIn();
        $user = getCurrentUser();
        $userTypeOk = $user && ($user['tipo'] ?? '') === 'aluno';
        
        echo "isLoggedIn(): " . ($dashboardLogged ? "✅ TRUE" : "❌ FALSE") . "<br>";
        echo "Tipo é 'aluno': " . ($userTypeOk ? "✅ TRUE" : "❌ FALSE") . "<br>";
        echo "Acesso permitido: " . ($dashboardLogged && $userTypeOk ? "✅ SIM" : "❌ NÃO") . "<br>";
        
        // 6. Simular segundo acesso após timeout
        echo "<h3>6. Simulando segundo acesso após timeout</h3>";
        
        // Salvar estado original
        $tempoOriginal = $_SESSION['last_activity'];
        
        // Simular timeout
        $_SESSION['last_activity'] = time() - (SESSION_TIMEOUT + 100);
        
        echo "Timeout simulado: " . (SESSION_TIMEOUT + 100) . " segundos<br>";
        
        $loggedInAfterTimeout = isLoggedIn();
        echo "isLoggedIn() após timeout: " . ($loggedInAfterTimeout ? "✅ TRUE" : "❌ FALSE") . "<br>";
        
        if (!$loggedInAfterTimeout) {
            echo "❌ Sessão expirou! Verificando estado:<br>";
            echo "Sessão ativa: " . (session_status() === PHP_SESSION_ACTIVE ? "✅ SIM" : "❌ NÃO") . "<br>";
            echo "user_id ainda existe: " . (isset($_SESSION['user_id']) ? "✅ SIM" : "❌ NÃO") . "<br>";
            echo "last_activity ainda existe: " . (isset($_SESSION['last_activity']) ? "✅ SIM" : "❌ NÃO") . "<br>";
        }
        
        // 7. Testar registerSession (vai falhar sem tabela sessoes)
        echo "<h3>7. Testando registerSession()</h3>";
        try {
            // Acessar método privado via reflexão
            $reflection = new ReflectionClass($auth);
            $method = $reflection->getMethod('registerSession');
            $method->setAccessible(true);
            $method->invoke($auth, $alunoTeste['id']);
            echo "✅ registerSession() funcionou<br>";
        } catch (Exception $e) {
            echo "❌ registerSession() falhou: " . $e->getMessage() . "<br>";
            
            // Verificar se é erro de tabela não existente
            if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
                echo "🔴 ERRO CONFIRMADO: Tabela 'sessoes' não existe!<br>";
            }
        }
        
        // 8. Testar validateRememberToken (vai falhar sem tabela sessoes)
        echo "<h3>8. Testando validateRememberToken()</h3>";
        try {
            $reflection = new ReflectionClass($auth);
            $method = $reflection->getMethod('validateRememberToken');
            $method->setAccessible(true);
            $result = $method->invoke($auth, 'token_teste');
            echo "✅ validateRememberToken() funcionou<br>";
        } catch (Exception $e) {
            echo "❌ validateRememberToken() falhou: " . $e->getMessage() . "<br>";
            
            if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
                echo "🔴 ERRO CONFIRMADO: Tabela 'sessoes' não existe!<br>";
            }
        }
        
    } else {
        echo "❌ Login falhou<br>";
        
        // Tentar descobrir a senha correta
        echo "<h3>3. Tentando descobrir senha</h3>";
        if ($alunoTeste['password']) {
            echo "Hash existe: " . substr($alunoTeste['password'], 0, 30) . "...<br>";
            
            // Tentar senhas comuns
            $senhasComuns = ['123456', 'password', 'admin', 'aluno', $alunoTeste['email']];
            foreach ($senhasComuns as $senha) {
                if (password_verify($senha, $alunoTeste['password'])) {
                    echo "✅ Senha encontrada: $senha<br>";
                    
                    // Testar login com senha correta
                    $result2 = $auth->login($alunoTeste['email'], $senha);
                    echo "Login com senha '$senha': " . ($result2['success'] ? "✅ SUCESSO" : "❌ FALHA") . "<br>";
                    break;
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro no teste: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

?>
