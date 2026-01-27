<?php

namespace App\Controllers;

use App\Models\User;

class InstallController extends Controller
{
    /**
     * Landing pública /install — instalar app do aluno (sem auth).
     * CTA principal: "Abrir portal do aluno" se autenticado como aluno, senão "Fazer login".
     */
    public function show()
    {
        $loginUrl = base_url('login');
        $dashboardUrl = base_url('/aluno/dashboard.php');
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $userModel = new User();
            $user = $userModel->find($_SESSION['user_id']);
        }
        $isAluno = $user && (strtolower($user['tipo'] ?? '') === 'aluno');
        $primaryCtaUrl = $isAluno ? $dashboardUrl : $loginUrl;
        $primaryCtaLabel = $isAluno ? 'Abrir portal do aluno' : 'Fazer login';
        $this->viewRaw('install', [
            'loginUrl' => $loginUrl,
            'primaryCtaUrl' => $primaryCtaUrl,
            'primaryCtaLabel' => $primaryCtaLabel,
        ]);
    }
}
