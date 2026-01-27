<?php

namespace App\Controllers;

use App\Models\FirstAccessToken;
use App\Models\User;

class StartController extends Controller
{
    /**
     * GET /start?token=...
     * Valida token de primeiro acesso, cria sessão de onboarding e redireciona para definir senha.
     * Se token inválido/expirado/usado, exibe página de fallback.
     */
    public function show()
    {
        $token = trim($_GET['token'] ?? '');

        if ($token === '') {
            $this->showFallback('link_invalido');
            return;
        }

        $tokenModel = new FirstAccessToken();
        $row = $tokenModel->findByPlainToken($token);

        if (!$row) {
            if (function_exists('error_log')) {
                error_log('[START] Token primeiro acesso inválido/expirado/usado. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
            }
            $this->showFallback('expirado_ou_usado');
            return;
        }

        $userModel = new User();
        $user = $userModel->find($row['user_id']);
        if (!$user || ($user['status'] ?? '') !== 'ativo') {
            $this->showFallback('expirado_ou_usado');
            return;
        }

        // NÃO marcar token como usado aqui. O WhatsApp (e outros) fazem GET no link para preview
        // e isso invalidava o token antes do aluno abrir. Só marcar quando ele definir a senha.
        $_SESSION['onboarding_user_id'] = (int) $row['user_id'];
        $_SESSION['onboarding_token_id'] = (int) $row['id'];
        $_SESSION['force_password_change'] = true;

        redirect(base_url('define-password'));
    }

    /**
     * Página de fallback quando o link está inválido, expirado ou já usado.
     *
     * @param string $reason 'link_invalido' | 'expirado_ou_usado'
     */
    private function showFallback($reason)
    {
        $messages = [
            'link_invalido' => 'Este link é inválido.',
            'expirado_ou_usado' => 'Este link expirou ou já foi utilizado.',
        ];
        $msg = $messages[$reason] ?? 'Este link não é válido.';
        $this->viewRaw('start/fallback', [
            'message' => $msg,
            'loginUrl' => base_url('login'),
            'forgotPasswordUrl' => base_url('forgot-password'),
        ]);
    }
}
