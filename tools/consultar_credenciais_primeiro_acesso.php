<?php
/**
 * Consulta no banco (remoto ou local) se usuários/credenciais do fluxo
 * "primeiro acesso" (link /start?token=...) estão corretos.
 *
 * Uso: php tools/consultar_credenciais_primeiro_acesso.php
 * Ou:  http://localhost/cfc-v.1/tools/consultar_credenciais_primeiro_acesso.php
 */

$base = dirname(__DIR__);
require_once $base . '/app/autoload.php';

use App\Config\Database;
use App\Config\Env;

Env::load();

header('Content-Type: text/plain; charset=utf-8');

echo "========================================\n";
echo "CREDENCIAIS / USUÁRIO — PRIMEIRO ACESSO (LINK)\n";
echo "========================================\n\n";

$dbHost = $_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? 'localhost');
$dbName = $_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? 'cfc_db');
$isRemote = !in_array(strtolower((string)$dbHost), ['localhost', '127.0.0.1', '::1']);
echo "Banco: " . $dbHost . ($isRemote ? " [REMOTO]" : " [LOCAL]") . " / " . $dbName . "\n\n";

try {
    $db = Database::getInstance()->getConnection();

    // 1) Tabela first_access_tokens
    $tokensOk = false;
    try {
        $r = $db->query("SELECT 1 FROM first_access_tokens LIMIT 1");
        $tokensOk = (bool) $r;
    } catch (Throwable $e) {
        echo "⚠️  Tabela first_access_tokens: " . $e->getMessage() . "\n\n";
    }

    if ($tokensOk) {
        echo "📌 Tokens de primeiro acesso (últimos 15):\n";
        echo str_repeat('-', 70) . "\n";
        $stmt = $db->query("
            SELECT id, user_id, expires_at, used_at,
                   CASE WHEN used_at IS NOT NULL THEN 'usado' WHEN expires_at < NOW() THEN 'expirado' ELSE 'válido' END as estado
            FROM first_access_tokens
            ORDER BY id DESC
            LIMIT 15
        ");
        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tokens as $t) {
            echo "  id={$t['id']} user_id={$t['user_id']} expires={$t['expires_at']} used_at=" . ($t['used_at'] ?? 'NULL') . " [{$t['estado']}]\n";
        }
        echo "\n";
    }

    // 2) Alunos com user_id (vinculados ao fluxo de acesso) — sem coluna tipo
    echo "📌 Alunos com usuário vinculado (user_id preenchido):\n";
    echo str_repeat('-', 70) . "\n";
    $hasStudents = false;
    try {
        $stmt = $db->query("
            SELECT s.id as student_id, s.user_id, s.email as student_email, s.full_name, s.name,
                   u.id as usuario_id, u.email as usuario_email, u.status,
                   COALESCE(u.must_change_password, 0) as must_change_password,
                   CHAR_LENGTH(u.password) as pwd_len,
                   u.cpf
            FROM students s
            INNER JOIN usuarios u ON u.id = s.user_id
            ORDER BY s.id DESC
            LIMIT 20
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $hasStudents = true;
            $nome = $r['full_name'] ?? $r['name'] ?? 'N/A';
            $pwdOk = isset($r['pwd_len']) && (int)$r['pwd_len'] >= 50;
            $emailMatch = ($r['student_email'] ?? '') === ($r['usuario_email'] ?? '');
            $ana = (stripos($nome, 'ANA') !== false && stripos($nome, 'BEZERRA') !== false) ? ' ← Ana' : '';
            echo "  student_id={$r['student_id']} user_id={$r['user_id']} | usuario: email=" . ($r['usuario_email'] ?? '') . " status={$r['status']} | ";
            echo "must_change_pwd={$r['must_change_password']} | pwd_len={$r['pwd_len']} " . ($pwdOk ? '[OK]' : '[??]') . " | email_match=" . ($emailMatch ? 'sim' : 'não') . "{$ana}\n";
        }
        if (!$hasStudents) {
            echo "  (nenhum aluno com user_id)\n";
        }
        echo "\n";
    } catch (Throwable $e) {
        echo "  Erro: " . $e->getMessage() . "\n\n";
    }

    // 3) Usuários com role ALUNO (usuario_roles) ou id em students.user_id
    echo "📌 Usuários vinculados a students (últimos 15 por id):\n";
    echo str_repeat('-', 70) . "\n";
    try {
        $stmt = $db->query("
            SELECT u.id, u.email, u.nome, u.status,
                   COALESCE(u.must_change_password, 0) as must_change_password,
                   CHAR_LENGTH(u.password) as pwd_len,
                   (SELECT GROUP_CONCAT(ur.role) FROM usuario_roles ur WHERE ur.usuario_id = u.id) as roles
            FROM usuarios u
            WHERE u.id IN (SELECT user_id FROM students WHERE user_id IS NOT NULL)
            ORDER BY u.id DESC
            LIMIT 15
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $u) {
            $pwdOk = isset($u['pwd_len']) && (int)$u['pwd_len'] >= 50;
            echo "  id={$u['id']} email=" . ($u['email'] ?? '') . " nome=" . ($u['nome'] ?? '') . " status={$u['status']} must_change_pwd={$u['must_change_password']} pwd_len={$u['pwd_len']} " . ($pwdOk ? '[OK]' : '[??]') . " roles=" . ($u['roles'] ?? '') . "\n";
        }
        echo "\n";
    } catch (Throwable $e) {
        echo "  Erro: " . $e->getMessage() . "\n\n";
    }

    // 4) Ana Bezerra especificamente (student + usuario, sem coluna tipo)
    echo "📌 Busca por 'Ana Bezerra' (student + usuario):\n";
    echo str_repeat('-', 70) . "\n";
    try {
        $stmt = $db->prepare("
            SELECT s.id as student_id, s.user_id, s.email as s_email, s.full_name, s.name
            FROM students s
            WHERE (s.full_name LIKE ? OR s.name LIKE ?)
            LIMIT 5
        ");
        $stmt->execute(['%Ana%Bezerra%', '%Ana%Bezerra%']);
        $anas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($anas as $a) {
            echo "  Student: id={$a['student_id']} user_id=" . ($a['user_id'] ?? 'NULL') . " email=" . ($a['s_email'] ?? '') . " nome=" . ($a['full_name'] ?? $a['name'] ?? '') . "\n";
            if (!empty($a['user_id'])) {
                $u = $db->prepare("SELECT id, email, nome, status, COALESCE(must_change_password,0) as must_change_password, CHAR_LENGTH(password) as pwd_len FROM usuarios WHERE id = ?");
                $u->execute([$a['user_id']]);
                $usr = $u->fetch(PDO::FETCH_ASSOC);
                if ($usr) {
                    echo "  Usuario: id={$usr['id']} email={$usr['email']} nome={$usr['nome']} status={$usr['status']} must_change_pwd={$usr['must_change_password']} pwd_len={$usr['pwd_len']}\n";
                    $r = $db->prepare("SELECT GROUP_CONCAT(role) as roles FROM usuario_roles WHERE usuario_id = ?");
                    $r->execute([$usr['id']]);
                    $rr = $r->fetch(PDO::FETCH_ASSOC);
                    echo "  Roles: " . ($rr['roles'] ?? '') . "\n";
                }
            }
        }
        if (empty($anas)) {
            echo "  (nenhum aluno encontrado com nome contendo 'Ana Bezerra')\n";
        }
    } catch (Throwable $e) {
        echo "  Erro: " . $e->getMessage() . "\n";
    }

    echo "\n========================================\n";
    echo "Fim da consulta.\n";

} catch (Throwable $e) {
    echo "Erro de conexão/consulta: " . $e->getMessage() . "\n";
    exit(1);
}
