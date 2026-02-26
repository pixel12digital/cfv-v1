<?php
// SCRIPT DE TESTE: SIMULAR FLUXO DE LOGIN DE ALUNO
// Para reproduzir exatamente o problema relatado

require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

echo "<h2>🧪 TESTE DE FLUXO DE LOGIN DE ALUNO</h2>";

try {
    $db = db();
    
    // 1. Verificar se existem alunos para teste
    echo "<h3>1. Buscando alunos para teste</h3>";
    
    // Buscar alunos na tabela usuarios (sem coluna 'tipo')
    $alunos = $db->fetchAll("SELECT id, nome, email, cpf, status FROM usuarios WHERE status = 'ativo' LIMIT 3");
    
    if (empty($alunos)) {
        echo "❌ Nenhum aluno encontrado para teste<br>";
        exit;
    }
    
    echo "✅ Encontrados " . count($alunos) . " alunos para teste<br>";
    echo "<pre>";
    print_r($alunos);
    echo "</pre>";
    
    // 2. Simular primeiro login (como o código faz)
    $alunoTeste = $alunos[0];
    $cpfTeste = $alunoTeste['cpf'];
    $senhaPadrao = '123456'; // Senha padrão usada no sistema
    
    echo "<h3>2. Simulando PRIMEIRO login</h3>";
    echo "CPF: $cpfTeste<br>";
    echo "Senha: [PADRÃO]<br>";
    
    // Limpar sessão atual
    session_destroy();
    session_start();
    
    // Simular a lógica do login.php para alunos
    $cpfLimpo = preg_replace('/[^0-9]/', '', $cpfTeste);
    
    echo "CPF Original: $cpfTeste<br>";
    echo "CPF Limpo: $cpfLimpo<br>";
    
    // Buscar aluno (exatamente como o login.php faz)
    $aluno = $db->fetch("SELECT * FROM usuarios WHERE cpf = ? AND status = 'ativo'", [$cpfLimpo]);
    
    if (!$aluno) {
        echo "❌ Aluno não encontrado<br>";
        exit;
    }
    
    echo "✅ Aluno encontrado: " . $aluno['nome'] . "<br>";
    
    // Verificar senha
    $senhaHash = $aluno['password'] ?? null;
    if (!$senhaHash) {
        echo "❌ Senha não encontrada no banco<br>";
        exit;
    }
    
    $senhaValida = password_verify($senhaPadrao, $senhaHash);
    $senhaDefault = ($senhaPadrao === '123456');
    
    echo "Hash existe: " . ($senhaHash ? "SIM" : "NÃO") . "<br>";
    echo "Comprimento do hash: " . strlen($senhaHash) . " caracteres<br>";
    echo "password_verify: " . ($senhaValida ? "SIM" : "NÃO") . "<br>";
    echo "Senha padrão (123456): " . ($senhaDefault ? "SIM" : "NÃO") . "<br>";
    
    if ($senhaValida || $senhaDefault) {
        echo "✅ Senha válida! Criando sessão...<br>";
        
        // Criar sessão exatamente como o sistema faz
        $_SESSION['user_id'] = $aluno['id'];
        $_SESSION['user_email'] = $aluno['email'] ?? $aluno['cpf'] . '@aluno.cfc';
        $_SESSION['user_tipo'] = 'aluno'; // FORÇADO manualmente
        $_SESSION['last_activity'] = time();
        
        echo "✅ Sessão criada:<br>";
        echo "user_id: " . $_SESSION['user_id'] . "<br>";
        echo "user_email: " . $_SESSION['user_email'] . "<br>";
        echo "user_tipo: " . $_SESSION['user_tipo'] . "<br>";
        echo "last_activity: " . $_SESSION['last_activity'] . "<br>";
        
        // 3. Testar isLoggedIn() imediatamente
        echo "<h3>3. Testando isLoggedIn() imediatamente</h3>";
        $loggedIn = isLoggedIn();
        echo "isLoggedIn(): " . ($loggedIn ? "✅ TRUE" : "❌ FALSE") . "<br>";
        
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
        
        // 5. Simular acesso ao dashboard
        echo "<h3>5. Simulando acesso ao dashboard</h3>";
        
        // Verificar as mesmas condições do dashboard.php
        $dashboardLogged = isLoggedIn();
        $user = getCurrentUser();
        $userTypeOk = $user && ($user['tipo'] ?? '') === 'aluno';
        
        echo "dashboardLogged: " . ($dashboardLogged ? "✅" : "❌") . "<br>";
        echo "userTypeOk: " . ($userTypeOk ? "✅" : "❌") . "<br>";
        echo "Acesso permitido: " . ($dashboardLogged && $userTypeOk ? "✅ SIM" : "❌ NÃO") . "<br>";
        
        // 6. Simular segundo acesso (após algum tempo)
        echo "<h3>6. Simulando SEGUNDO acesso</h3>";
        
        // Modificar last_activity para simular tempo passado
        $tempoOriginal = $_SESSION['last_activity'];
        $_SESSION['last_activity'] = time() - (SESSION_TIMEOUT + 100); // Excedeu timeout
        
        echo "Tempo original: " . date('H:i:s', $tempoOriginal) . "<br>";
        echo "Tempo modificado: " . date('H:i:s', $_SESSION['last_activity']) . "<br>";
        echo "SESSION_TIMEOUT: " . SESSION_TIMEOUT . " segundos<br>";
        echo "Diferença: " . (time() - $_SESSION['last_activity']) . " segundos<br>";
        
        // Testar isLoggedIn() após timeout
        $loggedInAfterTimeout = isLoggedIn();
        echo "isLoggedIn() após timeout: " . ($loggedInAfterTimeout ? "✅ TRUE" : "❌ FALSE") . "<br>";
        
        if (!$loggedInAfterTimeout) {
            echo "❌ Sessão foi destruída pelo timeout!<br>";
            echo "Verificando se sessão ainda existe:<br>";
            echo "user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NÃO EXISTE') . "<br>";
            echo "last_activity: " . (isset($_SESSION['last_activity']) ? $_SESSION['last_activity'] : 'NÃO EXISTE') . "<br>";
        }
        
        // 7. Testar com tabela sessoes (se existisse)
        echo "<h3>7. Testando registerSession()</h3>";
        try {
            $auth = new Auth();
            // Tentar registrar sessão (vai falhar se tabela não existir)
            $auth->registerSession($aluno['id']);
            echo "✅ registerSession() funcionou<br>";
        } catch (Exception $e) {
            echo "❌ registerSession() falhou: " . $e->getMessage() . "<br>";
        }
        
    } else {
        echo "❌ Senha inválida<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro no teste: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

?>
