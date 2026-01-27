<?php

namespace App\Controllers;

use App\Models\FirstAccessToken;
use App\Models\User;

class StartController extends Controller
{
    /**
     * GET /start?token=...
     * Valida token de primeiro acesso, cria sessão de onboarding e exibe o formulário
     * "Definir senha" na mesma resposta (sem redirect) para evitar perda de cookie no in-app do WhatsApp.
     * Se token inválido/expirado/usado, exibe página de fallback com código de erro.
     */
    public function show()
    {
        $token = trim($_GET['token'] ?? '');

        if ($token === '') {
            $this->showFallback('link_invalido', 'ERR_START_INVALID');
            return;
        }

        $tokenModel = new FirstAccessToken();
        $r = $tokenModel->findWithReason($token);

        if ($r['result'] !== 'ok') {
            $this->logStartAttempt($token, $r);
            $code = $r['result'] === 'not_found' ? 'ERR_START_NOTFOUND'
                : ($r['result'] === 'expired' ? 'ERR_START_EXPIRED' : 'ERR_START_USED');
            $this->showFallback('expirado_ou_usado', $code);
            return;
        }

        $row = $r['row'];
        $userModel = new User();
        $user = $userModel->find($row['user_id']);
        if (!$user || ($user['status'] ?? '') !== 'ativo') {
            $this->showFallback('expirado_ou_usado', 'ERR_START_USED');
            return;
        }

        // NÃO marcar token como usado aqui. O WhatsApp (e outros) fazem GET no link para preview
        // e isso invalidava o token antes do aluno abrir. Só marcar quando ele definir a senha.
        $_SESSION['onboarding_user_id'] = (int) $row['user_id'];
        $_SESSION['onboarding_token_id'] = (int) $row['id'];
        $_SESSION['force_password_change'] = true;

        // Renderizar a mesma view de "definir senha" na própria resposta de /start (sem redirect),
        // para não depender do cookie sobreviver ao redirect no in-app do WhatsApp.
        $this->viewRaw('auth/define-password', ['user' => $user]);
    }

    /**
     * Loga uma linha por tentativa de uso do token (quando result !== ok).
     */
    private function logStartAttempt($plainToken, array $r)
    {
        if (!function_exists('error_log')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $line = sprintf(
            '[START] token_present=%s token_hash_prefix=%s found_token_id=%s now=%s expires_at=%s used_at=%s result=%s',
            $plainToken !== '' ? 'true' : 'false',
            $r['hash_prefix'] ?? '',
            $r['found_token_id'] ?? 'null',
            $now,
            $r['expires_at'] ?? 'null',
            $r['used_at'] ?? 'null',
            $r['result'] ?? 'unknown'
        );
        error_log($line);
    }

    /**
     * Página de fallback quando o link está inválido, expirado ou já usado.
     *
     * @param string $reason 'link_invalido' | 'expirado_ou_usado'
     * @param string|null $errorCode Ex.: ERR_START_NOTFOUND, ERR_START_EXPIRED, ERR_START_USED, ERR_START_INVALID
     */
    private function showFallback($reason, $errorCode = null)
    {
        $messages = [
            'link_invalido' => 'Este link é inválido.',
            'expirado_ou_usado' => 'Este link expirou ou já foi utilizado.',
        ];
        $msg = $messages[$reason] ?? 'Este link não é válido.';
        $this->viewRaw('start/fallback', [
            'message' => $msg,
            'errorCode' => $errorCode,
            'loginUrl' => base_url('login'),
            'forgotPasswordUrl' => base_url('forgot-password'),
        ]);
    }
}
