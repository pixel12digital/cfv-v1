<?php

use App\Controllers\AuthController;
use App\Controllers\InstallController;
use App\Controllers\StartController;
use App\Controllers\DashboardController;
use App\Controllers\ApiController;
use App\Controllers\AlunosController;
use App\Controllers\AgendaController;
use App\Controllers\InstrutoresController;
use App\Controllers\VeiculosController;
use App\Controllers\FinanceiroController;
use App\Controllers\ServicosController;
use App\Controllers\UsuariosController;
use App\Controllers\ConfiguracoesController;
use App\Controllers\NotificationsController;
use App\Controllers\BroadcastNotificationsController;
use App\Controllers\RescheduleRequestsController;
use App\Controllers\TheoryClassesController;
use App\Controllers\TheorySessionsController;
use App\Controllers\TheoryEnrollmentsController;
use App\Controllers\TheoryAttendanceController;
use App\Controllers\PaymentsController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\RoleMiddleware;

// Router é passado como variável global $router
global $router;

// Rotas públicas
$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/login/cfc-logo', [AuthController::class, 'cfcLogo']); // Logo do CFC no login (público)
$router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->get('/reset-password', [AuthController::class, 'showResetPassword']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);
$router->get('/ativar-conta', [AuthController::class, 'showActivateAccount']);
$router->post('/ativar-conta', [AuthController::class, 'activateAccount']);
$router->get('/install', [InstallController::class, 'show']); // Landing pública: instalar app do aluno (sem auth)
$router->get('/start', [StartController::class, 'show']); // Magic link primeiro acesso: valida token, onboarding, redirect define-password
$router->get('/define-password', [AuthController::class, 'showDefinePassword']); // Definir senha (onboarding, sem auth)
$router->post('/define-password', [AuthController::class, 'definePassword']);

// Rotas protegidas
$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);
$router->get('/change-password', [AuthController::class, 'showChangePassword'], [AuthMiddleware::class]);
$router->post('/change-password', [AuthController::class, 'changePassword'], [AuthMiddleware::class]);

// Módulos - Fase 1
// Serviços
$router->get('/servicos', [ServicosController::class, 'index'], [AuthMiddleware::class]);
$router->get('/servicos/novo', [ServicosController::class, 'novo'], [AuthMiddleware::class]);
$router->post('/servicos/criar', [ServicosController::class, 'criar'], [AuthMiddleware::class]);
$router->get('/servicos/{id}/editar', [ServicosController::class, 'editar'], [AuthMiddleware::class]);
$router->post('/servicos/{id}/atualizar', [ServicosController::class, 'atualizar'], [AuthMiddleware::class]);
$router->post('/servicos/{id}/toggle', [ServicosController::class, 'toggle'], [AuthMiddleware::class]);

// Alunos
$router->get('/alunos', [AlunosController::class, 'index'], [AuthMiddleware::class]);
$router->get('/alunos/novo', [AlunosController::class, 'novo'], [AuthMiddleware::class]);
$router->post('/alunos/criar', [AlunosController::class, 'criar'], [AuthMiddleware::class]);
$router->get('/alunos/{id}', [AlunosController::class, 'show'], [AuthMiddleware::class]);
$router->get('/alunos/{id}/editar', [AlunosController::class, 'editar'], [AuthMiddleware::class]);
$router->post('/alunos/{id}/atualizar', [AlunosController::class, 'atualizar'], [AuthMiddleware::class]);
$router->get('/alunos/{id}/matricular', [AlunosController::class, 'matricular'], [AuthMiddleware::class]);
$router->post('/alunos/{id}/matricular', [AlunosController::class, 'criarMatricula'], [AuthMiddleware::class]);
$router->post('/alunos/{id}/foto/upload', [AlunosController::class, 'uploadFoto'], [AuthMiddleware::class]);
$router->post('/alunos/{id}/foto/remover', [AlunosController::class, 'removerFoto'], [AuthMiddleware::class]);
$router->get('/alunos/{id}/foto', [AlunosController::class, 'foto'], [AuthMiddleware::class]);
$router->post('/alunos/{id}/historico/observacao', [AlunosController::class, 'adicionarObservacao'], [AuthMiddleware::class]);

// Matrículas
$router->get('/matriculas/{id}', [AlunosController::class, 'showMatricula'], [AuthMiddleware::class]);
$router->post('/matriculas/{id}/atualizar', [AlunosController::class, 'atualizarMatricula'], [AuthMiddleware::class]);
$router->post('/matriculas/{id}/excluir', [AlunosController::class, 'excluirMatricula'], [AuthMiddleware::class]);
$router->post('/matriculas/{id}/excluir-definitivamente', [AlunosController::class, 'excluirDefinitivamente'], [AuthMiddleware::class]);

// Etapas
$router->post('/student-steps/{id}/toggle', [AlunosController::class, 'toggleStep'], [AuthMiddleware::class]);

// Agenda
$router->get('/agenda', [AgendaController::class, 'index'], [AuthMiddleware::class]);
$router->get('/agenda/novo', [AgendaController::class, 'novo'], [AuthMiddleware::class]);
$router->post('/agenda/criar', [AgendaController::class, 'criar'], [AuthMiddleware::class]);
$router->get('/agenda/iniciar-bloco', [AgendaController::class, 'iniciarBloco'], [AuthMiddleware::class]);
$router->post('/agenda/iniciar-bloco', [AgendaController::class, 'iniciarBloco'], [AuthMiddleware::class]);
$router->get('/agenda/finalizar-bloco', [AgendaController::class, 'finalizarBloco'], [AuthMiddleware::class]);
$router->post('/agenda/finalizar-bloco', [AgendaController::class, 'finalizarBloco'], [AuthMiddleware::class]);
$router->get('/agenda/{id}', [AgendaController::class, 'show'], [AuthMiddleware::class]);
$router->get('/agenda/{id}/editar', [AgendaController::class, 'editar'], [AuthMiddleware::class]);
$router->post('/agenda/{id}/atualizar', [AgendaController::class, 'atualizar'], [AuthMiddleware::class]);
$router->post('/agenda/{id}/cancelar', [AgendaController::class, 'cancelar'], [AuthMiddleware::class]);
$router->get('/agenda/{id}/concluir', [AgendaController::class, 'concluir'], [AuthMiddleware::class]);
$router->post('/agenda/{id}/concluir', [AgendaController::class, 'concluir'], [AuthMiddleware::class]);
$router->get('/agenda/{id}/iniciar', [AgendaController::class, 'iniciar'], [AuthMiddleware::class]);
$router->post('/agenda/{id}/iniciar', [AgendaController::class, 'iniciar'], [AuthMiddleware::class]);
$router->get('/api/agenda/calendario', [AgendaController::class, 'apiCalendario'], [AuthMiddleware::class]);
$router->post('/agenda/{id}/solicitar-reagendamento', [RescheduleRequestsController::class, 'solicitar'], [AuthMiddleware::class]);

// Instrutores
$router->get('/instrutores', [InstrutoresController::class, 'index'], [AuthMiddleware::class]);
$router->get('/instrutores/novo', [InstrutoresController::class, 'novo'], [AuthMiddleware::class]);
$router->post('/instrutores/criar', [InstrutoresController::class, 'criar'], [AuthMiddleware::class]);
$router->get('/instrutores/{id}/editar', [InstrutoresController::class, 'editar'], [AuthMiddleware::class]);
$router->post('/instrutores/{id}/atualizar', [InstrutoresController::class, 'atualizar'], [AuthMiddleware::class]);
$router->post('/instrutores/{id}/excluir', [InstrutoresController::class, 'excluir'], [AuthMiddleware::class]);
$router->post('/instrutores/{id}/foto/upload', [InstrutoresController::class, 'uploadFoto'], [AuthMiddleware::class]);
$router->post('/instrutores/{id}/foto/remover', [InstrutoresController::class, 'removerFoto'], [AuthMiddleware::class]);
$router->get('/instrutores/{id}/foto', [InstrutoresController::class, 'foto'], [AuthMiddleware::class]);

// Veículos
$router->get('/veiculos', [VeiculosController::class, 'index'], [AuthMiddleware::class]);
$router->get('/veiculos/novo', [VeiculosController::class, 'novo'], [AuthMiddleware::class]);
$router->post('/veiculos/criar', [VeiculosController::class, 'criar'], [AuthMiddleware::class]);
$router->get('/veiculos/{id}/editar', [VeiculosController::class, 'editar'], [AuthMiddleware::class]);
$router->post('/veiculos/{id}/atualizar', [VeiculosController::class, 'atualizar'], [AuthMiddleware::class]);
$router->post('/veiculos/{id}/excluir', [VeiculosController::class, 'excluir'], [AuthMiddleware::class]);

// Financeiro
$router->get('/financeiro', [FinanceiroController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/financeiro/autocomplete', [FinanceiroController::class, 'autocomplete'], [AuthMiddleware::class]);

// Usuários (ADMIN)
$router->get('/usuarios', [UsuariosController::class, 'index'], [AuthMiddleware::class]);
$router->get('/usuarios/novo', [UsuariosController::class, 'novo'], [AuthMiddleware::class]);
$router->post('/usuarios/criar', [UsuariosController::class, 'criar'], [AuthMiddleware::class]);
$router->post('/usuarios/criar-acesso-aluno', [UsuariosController::class, 'criarAcessoAluno'], [AuthMiddleware::class]);
$router->post('/usuarios/criar-acesso-instrutor', [UsuariosController::class, 'criarAcessoInstrutor'], [AuthMiddleware::class]);
$router->get('/usuarios/{id}/editar', [UsuariosController::class, 'editar'], [AuthMiddleware::class]);
$router->post('/usuarios/{id}/atualizar', [UsuariosController::class, 'atualizar'], [AuthMiddleware::class]);
$router->post('/usuarios/{id}/excluir', [UsuariosController::class, 'excluir'], [AuthMiddleware::class]);
$router->post('/usuarios/{id}/gerar-senha-temporaria', [UsuariosController::class, 'gerarSenhaTemporaria'], [AuthMiddleware::class]);
$router->post('/usuarios/{id}/gerar-link-ativacao', [UsuariosController::class, 'gerarLinkAtivacao'], [AuthMiddleware::class]);
$router->post('/usuarios/{id}/enviar-link-email', [UsuariosController::class, 'enviarLinkEmail'], [AuthMiddleware::class]);
$router->post('/usuarios/{id}/access-link', [UsuariosController::class, 'accessLink'], [AuthMiddleware::class]);

// Configurações (ADMIN)
$router->get('/configuracoes/smtp', [ConfiguracoesController::class, 'smtp'], [AuthMiddleware::class]);
$router->post('/configuracoes/smtp/salvar', [ConfiguracoesController::class, 'salvarSmtp'], [AuthMiddleware::class]);
$router->post('/configuracoes/smtp/testar', [ConfiguracoesController::class, 'testarSmtp'], [AuthMiddleware::class]);

// Configurações do CFC (Logo PWA)
$router->get('/configuracoes/cfc', [ConfiguracoesController::class, 'cfc'], [AuthMiddleware::class]);
$router->post('/configuracoes/cfc/salvar', [ConfiguracoesController::class, 'salvarCfc'], [AuthMiddleware::class]);
$router->get('/configuracoes/cfc/logo', [ConfiguracoesController::class, 'logo'], [AuthMiddleware::class]);
$router->post('/configuracoes/cfc/logo/upload', [ConfiguracoesController::class, 'uploadLogo'], [AuthMiddleware::class]);
$router->post('/configuracoes/cfc/logo/remover', [ConfiguracoesController::class, 'removerLogo'], [AuthMiddleware::class]);

// Contas PIX do CFC
$router->post('/configuracoes/cfc/pix-accounts/criar', [ConfiguracoesController::class, 'pixAccountCriar'], [AuthMiddleware::class]);
$router->post('/configuracoes/cfc/pix-accounts/{id}/atualizar', [ConfiguracoesController::class, 'pixAccountAtualizar'], [AuthMiddleware::class]);
$router->post('/configuracoes/cfc/pix-accounts/{id}/excluir', [ConfiguracoesController::class, 'pixAccountExcluir'], [AuthMiddleware::class]);
$router->post('/configuracoes/cfc/pix-accounts/{id}/definir-padrao', [ConfiguracoesController::class, 'pixAccountDefinirPadrao'], [AuthMiddleware::class]);

// Curso Teórico - Configurações (ADMIN)
$router->get('/configuracoes/disciplinas', [ConfiguracoesController::class, 'disciplinas'], [AuthMiddleware::class]);
$router->get('/configuracoes/disciplinas/novo', [ConfiguracoesController::class, 'disciplinaNovo'], [AuthMiddleware::class]);
$router->post('/configuracoes/disciplinas/criar', [ConfiguracoesController::class, 'disciplinaCriar'], [AuthMiddleware::class]);
$router->get('/configuracoes/disciplinas/{id}/editar', [ConfiguracoesController::class, 'disciplinaEditar'], [AuthMiddleware::class]);
$router->post('/configuracoes/disciplinas/{id}/atualizar', [ConfiguracoesController::class, 'disciplinaAtualizar'], [AuthMiddleware::class]);
$router->post('/configuracoes/disciplinas/{id}/excluir', [ConfiguracoesController::class, 'disciplinaExcluir'], [AuthMiddleware::class]);

$router->get('/configuracoes/cursos', [ConfiguracoesController::class, 'cursos'], [AuthMiddleware::class]);
$router->get('/configuracoes/cursos/novo', [ConfiguracoesController::class, 'cursoNovo'], [AuthMiddleware::class]);
$router->post('/configuracoes/cursos/criar', [ConfiguracoesController::class, 'cursoCriar'], [AuthMiddleware::class]);
$router->get('/configuracoes/cursos/{id}/editar', [ConfiguracoesController::class, 'cursoEditar'], [AuthMiddleware::class]);
$router->post('/configuracoes/cursos/{id}/atualizar', [ConfiguracoesController::class, 'cursoAtualizar'], [AuthMiddleware::class]);
$router->post('/configuracoes/cursos/{id}/excluir', [ConfiguracoesController::class, 'cursoExcluir'], [AuthMiddleware::class]);

// Curso Teórico - Secretaria (Turmas, Sessões, Matrículas, Presença)
$router->get('/turmas-teoricas', [TheoryClassesController::class, 'index'], [AuthMiddleware::class]);
$router->get('/turmas-teoricas/novo', [TheoryClassesController::class, 'novo'], [AuthMiddleware::class]);
$router->post('/turmas-teoricas/criar', [TheoryClassesController::class, 'criar'], [AuthMiddleware::class]);
$router->get('/turmas-teoricas/{id}', [TheoryClassesController::class, 'show'], [AuthMiddleware::class]);
$router->get('/turmas-teoricas/{id}/editar', [TheoryClassesController::class, 'editar'], [AuthMiddleware::class]);
$router->post('/turmas-teoricas/{id}/atualizar', [TheoryClassesController::class, 'atualizar'], [AuthMiddleware::class]);
$router->post('/turmas-teoricas/{id}/excluir', [TheoryClassesController::class, 'excluir'], [AuthMiddleware::class]);

$router->get('/turmas-teoricas/{classId}/sessoes/novo', [TheorySessionsController::class, 'novo'], [AuthMiddleware::class]);
$router->post('/turmas-teoricas/{classId}/sessoes/criar', [TheorySessionsController::class, 'criar'], [AuthMiddleware::class]);
$router->get('/turmas-teoricas/{classId}/sessoes/{sessionId}/editar', [TheorySessionsController::class, 'editar'], [AuthMiddleware::class]);
$router->post('/turmas-teoricas/{classId}/sessoes/{sessionId}/atualizar', [TheorySessionsController::class, 'atualizar'], [AuthMiddleware::class]);
$router->post('/turmas-teoricas/{classId}/sessoes/{sessionId}/cancelar', [TheorySessionsController::class, 'cancelar'], [AuthMiddleware::class]);

$router->get('/turmas-teoricas/{classId}/matricular', [TheoryEnrollmentsController::class, 'novo'], [AuthMiddleware::class]);
$router->get('/turmas-teoricas/{classId}/matriculas/buscar', [TheoryEnrollmentsController::class, 'buscarMatriculas'], [AuthMiddleware::class]);
$router->post('/turmas-teoricas/{classId}/matriculas/criar', [TheoryEnrollmentsController::class, 'criar'], [AuthMiddleware::class]);
$router->post('/turmas-teoricas/{classId}/matriculas/{enrollmentId}/remover', [TheoryEnrollmentsController::class, 'remover'], [AuthMiddleware::class]);

$router->get('/turmas-teoricas/{classId}/sessoes/{sessionId}/presenca', [TheoryAttendanceController::class, 'sessao'], [AuthMiddleware::class]);
$router->post('/turmas-teoricas/{classId}/sessoes/{sessionId}/presenca/salvar', [TheoryAttendanceController::class, 'salvar'], [AuthMiddleware::class]);

// Notificações
$router->get('/notificacoes', [NotificationsController::class, 'index'], [AuthMiddleware::class]);
$router->post('/notificacoes/{id}/ler', [NotificationsController::class, 'markAsRead'], [AuthMiddleware::class]);
$router->post('/notificacoes/ler-todas', [NotificationsController::class, 'markAllAsRead'], [AuthMiddleware::class]);
$router->post('/notificacoes/excluir-historico', [NotificationsController::class, 'excluirHistorico'], [AuthMiddleware::class]);
$router->get('/api/notificacoes/contador', [NotificationsController::class, 'getUnreadCount'], [AuthMiddleware::class]);

// Comunicados (Broadcast de Notificações - ADMIN/SECRETARIA)
$router->get('/comunicados/novo', [BroadcastNotificationsController::class, 'create'], [AuthMiddleware::class]);
$router->post('/comunicados', [BroadcastNotificationsController::class, 'store'], [AuthMiddleware::class]);

// Solicitações de Reagendamento
$router->get('/solicitacoes-reagendamento', [RescheduleRequestsController::class, 'index'], [AuthMiddleware::class]);
$router->get('/solicitacoes-reagendamento/{id}', [RescheduleRequestsController::class, 'show'], [AuthMiddleware::class]);
$router->post('/solicitacoes-reagendamento/{id}/aprovar', [RescheduleRequestsController::class, 'aprovar'], [AuthMiddleware::class]);
$router->post('/solicitacoes-reagendamento/{id}/recusar', [RescheduleRequestsController::class, 'recusar'], [AuthMiddleware::class]);

// API
$router->post('/api/switch-role', [ApiController::class, 'switchRole'], [AuthMiddleware::class]);
$router->get('/api/geo/cidades', [ApiController::class, 'getCidades'], [AuthMiddleware::class]);
$router->get('/api/geo/cep', [ApiController::class, 'getCep'], [AuthMiddleware::class]);
$router->get('/api/students/{id}/enrollments', [ApiController::class, 'getStudentEnrollments'], [AuthMiddleware::class]);

// Payments API
$router->post('/api/payments/generate', [PaymentsController::class, 'generate'], [AuthMiddleware::class]);
$router->get('/api/payments/status', [PaymentsController::class, 'status'], [AuthMiddleware::class]);
$router->post('/api/payments/sync', [PaymentsController::class, 'sync'], [AuthMiddleware::class]);
$router->post('/api/payments/cancel', [PaymentsController::class, 'cancel'], [AuthMiddleware::class]);
$router->post('/api/payments/sync-pendings', [PaymentsController::class, 'syncPendings'], [AuthMiddleware::class]);
$router->post('/api/payments/mark-paid', [PaymentsController::class, 'markPaid'], [AuthMiddleware::class]);
$router->post('/api/payments/webhook/efi', [PaymentsController::class, 'webhookEfi']);

// Debug (APENAS LOCAL - REMOVER EM PRODUÇÃO)
use App\Controllers\DebugController;
$router->get('/debug/database', [DebugController::class, 'database']);
