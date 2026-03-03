<?php
/**
 * Relatório de Alunos por Status com Controle de Aulas
 * Visão gerencial: status, aulas contratadas, realizadas, agendadas e restantes
 * Acesso: apenas ADMIN e SECRETARIA
 */

if (!defined('ADMIN_ROUTING')) {
    header('Location: ../index.php');
    exit;
}

$user = getCurrentUser();
$userType = $user['tipo'] ?? null;
if (!in_array($userType, ['admin', 'secretaria'], true)) {
    $_SESSION['flash_message'] = 'Acesso negado. Apenas administradores e secretárias podem acessar este relatório.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: index.php');
    exit;
}

// Buscar lista de CFCs para filtro
$db = Database::getInstance();
$cfcs = $db->fetchAll("SELECT id, nome FROM cfcs WHERE ativo = 1 ORDER BY nome");

// Valores padrão dos filtros
$filtroStatus = $_GET['status'] ?? '';
$filtroCfc = $_GET['cfc_id'] ?? '';
$filtroDataInicio = $_GET['data_inicio'] ?? '';
$filtroDataFim = $_GET['data_fim'] ?? '';
?>

<style>
.relatorio-print .no-print { display: none !important; }
@media print {
    .no-print, .nav-sidebar, .topbar, .btn-toolbar .btn { display: none !important; }
    .relatorio-print { padding: 0; }
    .card { border: 1px solid #ddd; box-shadow: none; }
}
.stats-card {
    padding: 1.25rem;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.stats-number {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}
.stats-label {
    font-size: 0.875rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.table-relatorio { font-size: 0.9rem; }
.table-relatorio th { white-space: nowrap; }
.progress-bar-custom {
    height: 20px;
    border-radius: 4px;
    background: #e9ecef;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-status {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
}
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
.loading-overlay.active {
    display: flex;
}
</style>

<div class="relatorio-print">
    <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
        <h1 class="h2">
            <i class="fas fa-users-cog me-2"></i>Relatório de Alunos por Status
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0 no-print">
            <button type="button" class="btn btn-outline-success me-2" id="btnExportar">
                <i class="fas fa-file-csv me-1"></i>Exportar CSV
            </button>
            <button type="button" class="btn btn-primary" onclick="window.print();">
                <i class="fas fa-print me-1"></i>Imprimir
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4 no-print">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3" id="formFiltros">
                <input type="hidden" name="page" value="relatorio-alunos-status">
                
                <div class="col-md-3">
                    <label for="status" class="form-label">Status do Aluno</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="lead" <?= $filtroStatus === 'lead' ? 'selected' : '' ?>>Lead</option>
                        <option value="matriculado" <?= $filtroStatus === 'matriculado' ? 'selected' : '' ?>>Matriculado</option>
                        <option value="em_andamento" <?= $filtroStatus === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                        <option value="concluido" <?= $filtroStatus === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                        <option value="cancelado" <?= $filtroStatus === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="cfc_id" class="form-label">Unidade/CFC</label>
                    <select class="form-select" id="cfc_id" name="cfc_id">
                        <option value="">Todas</option>
                        <?php foreach ($cfcs as $cfc): ?>
                            <option value="<?= $cfc['id'] ?>" <?= $filtroCfc == $cfc['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cfc['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="data_inicio" class="form-label">Data Matrícula Início</label>
                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($filtroDataInicio) ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="data_fim" class="form-label">Data Matrícula Fim</label>
                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= htmlspecialchars($filtroDataFim) ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="row mb-4" id="statsContainer">
        <div class="col-md-2">
            <div class="stats-card" style="background: #e3f2fd;">
                <div class="stats-number text-primary" id="statTotal">0</div>
                <div class="stats-label">Total</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card" style="background: #fff3e0;">
                <div class="stats-number text-warning" id="statEmAndamento">0</div>
                <div class="stats-label">Em Andamento</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card" style="background: #e8f5e8;">
                <div class="stats-number text-success" id="statConcluido">0</div>
                <div class="stats-label">Concluídos</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card" style="background: #f3e5f5;">
                <div class="stats-number text-info" id="statMatriculado">0</div>
                <div class="stats-label">Matriculados</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card" style="background: #ffebee;">
                <div class="stats-number text-danger" id="statBloqueados">0</div>
                <div class="stats-label">Bloqueados</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card" style="background: #fce4ec;">
                <div class="stats-number text-secondary" id="statCancelado">0</div>
                <div class="stats-label">Cancelados</div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Listagem de Alunos</h5>
        </div>
        <div class="card-body p-0">
            <div id="loadingMessage" class="p-4 text-center text-muted">
                <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                <p class="mb-0">Carregando dados...</p>
            </div>
            
            <div id="emptyMessage" class="p-4 text-center text-muted" style="display: none;">
                <i class="fas fa-inbox fa-2x mb-2"></i>
                <p class="mb-0">Nenhum aluno encontrado com os filtros aplicados.</p>
            </div>
            
            <div class="table-responsive" id="tableContainer" style="display: none;">
                <table class="table table-hover table-striped table-relatorio mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Status</th>
                            <th>Status Financeiro</th>
                            <th>Serviço</th>
                            <th class="text-center">Contratadas</th>
                            <th class="text-center">Realizadas</th>
                            <th class="text-center">Agendadas</th>
                            <th class="text-center">Restantes</th>
                            <th>Progresso</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="text-center">
        <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
        <p class="text-muted">Processando...</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    carregarDados();
    
    // Exportar CSV
    document.getElementById('btnExportar').addEventListener('click', function() {
        const params = new URLSearchParams(window.location.search);
        params.delete('page');
        const url = 'api/exportar-relatorio-alunos-status.php?' + params.toString();
        window.open(url, '_blank');
    });
});

function carregarDados() {
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    
    fetch('api/relatorio-alunos-status.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                atualizarEstatisticas(data.stats);
                renderizarTabela(data.alunos);
            } else {
                mostrarErro(data.message || 'Erro ao carregar dados');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarErro('Erro ao carregar dados do relatório');
        });
}

function atualizarEstatisticas(stats) {
    document.getElementById('statTotal').textContent = stats.total_alunos || 0;
    document.getElementById('statEmAndamento').textContent = stats.em_andamento || 0;
    document.getElementById('statConcluido').textContent = stats.concluido || 0;
    document.getElementById('statMatriculado').textContent = stats.matriculado || 0;
    document.getElementById('statBloqueados').textContent = stats.bloqueados || 0;
    document.getElementById('statCancelado').textContent = stats.cancelado || 0;
}

function renderizarTabela(alunos) {
    const tbody = document.getElementById('tableBody');
    const loadingMsg = document.getElementById('loadingMessage');
    const emptyMsg = document.getElementById('emptyMessage');
    const tableContainer = document.getElementById('tableContainer');
    
    loadingMsg.style.display = 'none';
    
    if (alunos.length === 0) {
        emptyMsg.style.display = 'block';
        tableContainer.style.display = 'none';
        return;
    }
    
    emptyMsg.style.display = 'none';
    tableContainer.style.display = 'block';
    
    tbody.innerHTML = alunos.map(aluno => {
        const statusBadge = getStatusBadge(aluno.status);
        const financialBadge = getFinancialBadge(aluno.financial_status, aluno.bloqueado);
        const progressBar = getProgressBar(aluno.percentual_conclusao);
        const aulasRestantesDisplay = aluno.aulas_restantes === null ? 'Sem limite' : aluno.aulas_restantes;
        const aulasContratadasDisplay = aluno.aulas_contratadas === null ? 'Sem limite' : aluno.aulas_contratadas;
        
        return `
            <tr>
                <td>
                    <strong>${escapeHtml(aluno.nome)}</strong>
                    ${aluno.bloqueado ? '<i class="fas fa-lock text-danger ms-1" title="Bloqueado financeiramente"></i>' : ''}
                </td>
                <td>${escapeHtml(aluno.cpf || '-')}</td>
                <td>${statusBadge}</td>
                <td>${financialBadge}</td>
                <td><small>${escapeHtml(aluno.servico || '-')}</small></td>
                <td class="text-center"><strong>${aulasContratadasDisplay}</strong></td>
                <td class="text-center"><span class="badge bg-success">${aluno.aulas_realizadas}</span></td>
                <td class="text-center"><span class="badge bg-info">${aluno.aulas_agendadas}</span></td>
                <td class="text-center">
                    ${aluno.aulas_restantes !== null && aluno.aulas_restantes <= 0 
                        ? '<span class="badge bg-danger">0</span>' 
                        : `<span class="badge bg-warning text-dark">${aulasRestantesDisplay}</span>`
                    }
                </td>
                <td style="min-width: 150px;">${progressBar}</td>
            </tr>
        `;
    }).join('');
}

function getStatusBadge(status) {
    const badges = {
        'lead': '<span class="badge badge-status bg-secondary">Lead</span>',
        'matriculado': '<span class="badge badge-status bg-primary">Matriculado</span>',
        'em_andamento': '<span class="badge badge-status bg-warning text-dark">Em Andamento</span>',
        'concluido': '<span class="badge badge-status bg-success">Concluído</span>',
        'cancelado': '<span class="badge badge-status bg-danger">Cancelado</span>'
    };
    return badges[status] || '<span class="badge badge-status bg-secondary">' + status + '</span>';
}

function getFinancialBadge(financialStatus, bloqueado) {
    if (bloqueado) {
        return '<span class="badge badge-status bg-danger"><i class="fas fa-lock me-1"></i>Bloqueado</span>';
    }
    const badges = {
        'em_dia': '<span class="badge badge-status bg-success">Em Dia</span>',
        'pendente': '<span class="badge badge-status bg-warning text-dark">Pendente</span>',
        'bloqueado': '<span class="badge badge-status bg-danger">Bloqueado</span>'
    };
    return badges[financialStatus] || '<span class="badge badge-status bg-secondary">-</span>';
}

function getProgressBar(percentual) {
    if (percentual === 0) {
        return '<div class="progress-bar-custom"><div class="progress-fill" style="width: 0%;">0%</div></div>';
    }
    const width = Math.min(percentual, 100);
    return `<div class="progress-bar-custom"><div class="progress-fill" style="width: ${width}%;">${percentual}%</div></div>`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function mostrarErro(mensagem) {
    const loadingMsg = document.getElementById('loadingMessage');
    loadingMsg.innerHTML = `
        <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
        <p class="mb-0 text-danger">${escapeHtml(mensagem)}</p>
    `;
}
</script>
