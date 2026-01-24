<?php

// Backfill seguro para sincronizar vínculos instrutor/usuário e roles INSTRUTOR.
// Pode ser executado múltiplas vezes (idempotente).

use App\Config\Database;

require_once __DIR__ . '/../app/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Backfill de roles INSTRUTOR para usuários vinculados a instrutores ===\n";

try {
    $db = Database::getInstance()->getConnection();

    // 1) Para cada instrutor com user_id definido, garantir role INSTRUTOR correspondente
    $stmt = $db->query("SELECT id, user_id, email FROM instructors WHERE user_id IS NOT NULL AND user_id <> 0");
    $instructors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($instructors as $instr) {
        $userId = (int)$instr['user_id'];
        if ($userId <= 0) {
            continue;
        }

        $check = $db->prepare("SELECT 1 FROM usuario_roles WHERE usuario_id = ? AND role = 'INSTRUTOR' LIMIT 1");
        $check->execute([$userId]);
        if (!$check->fetchColumn()) {
            $insert = $db->prepare("INSERT INTO usuario_roles (usuario_id, role) VALUES (?, 'INSTRUTOR')");
            $insert->execute([$userId]);
            echo "Adicionado role INSTRUTOR para usuario_id={$userId} (instrutor_id={$instr['id']})\n";
        }
    }

    // 2) Para instrutores sem user_id, tentar resolver por e-mail de forma segura
    $stmt = $db->query("SELECT id, email, user_id FROM instructors WHERE (user_id IS NULL OR user_id = 0) AND email IS NOT NULL AND email <> ''");
    $orphanInstructors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orphanInstructors as $instr) {
        $email = trim($instr['email'] ?? '');
        if ($email === '') {
            continue;
        }

        // Buscar usuários com este e-mail
        $uStmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $uStmt->execute([$email]);
        $users = $uStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($users) === 1) {
            $userId = (int)$users[0]['id'];

            // Vincular instrutor ao usuário se ainda não estiver vinculado
            $update = $db->prepare("UPDATE instructors SET user_id = ? WHERE id = ? AND (user_id IS NULL OR user_id = 0)");
            $update->execute([$userId, $instr['id']]);

            // Garantir role INSTRUTOR
            $check = $db->prepare("SELECT 1 FROM usuario_roles WHERE usuario_id = ? AND role = 'INSTRUTOR' LIMIT 1");
            $check->execute([$userId]);
            if (!$check->fetchColumn()) {
                $insert = $db->prepare("INSERT INTO usuario_roles (usuario_id, role) VALUES (?, 'INSTRUTOR')");
                $insert->execute([$userId]);
                echo "Backfill: vinculado instrutor_id={$instr['id']} ao usuario_id={$userId} por email={$email} e adicionada role INSTRUTOR\n";
            } else {
                echo "Backfill: vinculado instrutor_id={$instr['id']} ao usuario_id={$userId} por email={$email} (role já existia)\n";
            }
        } elseif (count($users) > 1) {
            // Ambiguidade: apenas logar e pular
            echo "AVISO: email={$email} possui múltiplos usuários. Instrutor_id={$instr['id']} ignorado para evitar ambiguidade.\n";
        }
    }

    echo "Backfill concluído.\n";
} catch (Exception $e) {
    echo "ERRO no backfill: " . $e->getMessage() . "\n";
    exit(1);
}

