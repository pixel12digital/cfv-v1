<?php

namespace App\Controllers;

class InstallController extends Controller
{
    /**
     * Landing pública /install — instalar app do aluno (sem auth).
     * Não registra SW; apenas facilita instalação/uso do que já existe.
     */
    public function show()
    {
        $loginUrl = base_url('login');
        $this->viewRaw('install', ['loginUrl' => $loginUrl]);
    }
}
