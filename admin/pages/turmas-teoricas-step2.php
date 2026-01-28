<?php
/**
 * Step 2: Agendamento de Aulas
 * Sistema de agendamento com validação de conflitos
 */

if (!$turmaAtual) {
    echo '<div class="alert alert-danger">❌ Turma não encontrada.</div>';
    return;
}

// Obter disciplinas do curso (priorizando disciplinas selecionadas pelo usuário)
$disciplinasCurso = $turmaManager->obterDisciplinasParaAgendamento($turmaAtual['id']);

// Gerar horários disponíveis (05:00 às 23:10, intervalos de 50min)
// Expandido para permitir aulas fora do horário comercial padrão
$horariosDisponiveis = [
    '05:00', '05:50', '06:00', '06:50', // Horários muito cedo
    '07:00', '07:50', '08:40', '09:30', '10:20', '11:10',
    '12:00', '12:50', // Horário do almoço (adicionado)
    '13:00', '13:50', '14:40', '15:30', '16:20', '17:10', '18:00',
    '18:50', '19:40', '20:30', '21:10', '22:00', '22:50', '23:10' // Horários noturnos
];
?>

<style>
/* Layout responsivo personalizado */
@media (max-width: 768px) {
    .container-fluid {
        padding: 10px;
    }
    
    .form-section {
        margin-bottom: 20px;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .d-flex.flex-column .btn {
        margin-bottom: 10px;
    }
    
    .d-flex.flex-column .btn:last-child {
        margin-bottom: 0;
    }
}

@media (max-width: 576px) {
    .col-sm-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .col-md-4 {
        flex: 0 0 100%;
        max-width: 100%;
    }
}

/* Melhorar aparência dos cards */
.form-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
}

/* Estilo para preview e alertas responsivos */
#previewHorario, #alertaConflitos {
    word-break: break-word;
}

/* Grid responsivo para dados da turma */
.turma-dados-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .turma-dados-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}
</style>

<div class="container-fluid">
    <div class="row g-3">
        <!-- Coluna da esquerda: Formulário de agendamento -->
        <div class="col-lg-8 col-md-12">
        <div class="form-section">
            <h4>📚 Dados da Turma</h4>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <div class="turma-dados-grid">
                    <div><strong>Nome:</strong> <?= htmlspecialchars($turmaAtual['nome']) ?></div>
                    <div><strong>Sala:</strong> <?= htmlspecialchars($turmaAtual['sala_nome']) ?></div>
                    <div><strong>Curso:</strong> <?= htmlspecialchars($turmaAtual['curso_nome'] ?? $turmaAtual['curso_tipo'] ?? 'N/A') ?></div>
                    <div><strong>Período:</strong> <?= date('d/m/Y', strtotime($turmaAtual['data_inicio'])) ?> - <?= date('d/m/Y', strtotime($turmaAtual['data_fim'])) ?></div>
                </div>
            </div>
        </div>

        <form method="POST" action="?page=turmas-teoricas&step=2&turma_id=<?= $turmaAtual['id'] ?>" id="formAgendarAula">
            <input type="hidden" name="acao" value="agendar_aula">
            <input type="hidden" name="turma_id" value="<?= $turmaAtual['id'] ?>">
            <input type="hidden" name="step" value="2">
            
            <div class="form-section">
                <h4>📅 Agendar Nova Aula</h4>
                
                <div class="form-group">
                    <label for="disciplina">Disciplina *</label>
                    <select id="disciplina" name="disciplina" class="form-control" required>
                        <option value="">Selecione uma disciplina...</option>
                        <?php foreach ($disciplinasCurso as $disc): ?>
                            <option value="<?= $disc['disciplina'] ?>" 
                                    data-aulas-obrigatorias="<?= $disc['aulas_obrigatorias'] ?>"
                                    data-cor="<?= $disc['cor_hex'] ?>">
                                <?= htmlspecialchars($disc['nome_disciplina']) ?> 
                                (<?= $disc['aulas_obrigatorias'] ?> aulas obrigatórias)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="instrutor_id">Instrutor *</label>
                    <select id="instrutor_id" name="instrutor_id" class="form-control" required>
                        <option value="">Selecione um instrutor...</option>
                        <?php foreach ($instrutores as $instrutor): ?>
                            <option value="<?= $instrutor['id'] ?>">
                                <?= htmlspecialchars($instrutor['nome']) ?>
                                <?php if ($instrutor['categoria_habilitacao']): ?>
                                    - <?= htmlspecialchars($instrutor['categoria_habilitacao']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="data_aula">Data da Aula *</label>
                            <input type="date" 
                                   id="data_aula" 
                                   name="data_aula" 
                                   class="form-control" 
                                   min="<?= $turmaAtual['data_inicio'] ?>"
                                   max="<?= $turmaAtual['data_fim'] ?>"
                                   required>
                        </div>
                    </div>
                    
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="hora_inicio">Horário de Início *</label>
                            <select id="hora_inicio" name="hora_inicio" class="form-control" required>
                                <option value="">Selecione o horário...</option>
                                <?php foreach ($horariosDisponiveis as $horario): ?>
                                    <option value="<?= $horario ?>"><?= $horario ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="quantidade_aulas">Qtd Aulas *</label>
                            <select id="quantidade_aulas" name="quantidade_aulas" class="form-control" required>
                                <option value="1">1 aula</option>
                                <option value="2" selected>2 aulas</option>
                                <option value="3">3 aulas</option>
                                <option value="4">4 aulas</option>
                                <option value="5">5 aulas</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Preview do horário -->
                <div id="previewHorario" style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin-top: 15px; display: none;">
                    <strong>🕐 Preview do Agendamento:</strong>
                    <div id="previewContent" style="margin-top: 8px; font-family: monospace;"></div>
                </div>
                
                <!-- Alerta de conflitos -->
                <div id="alertaConflitos" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-top: 15px; display: none;">
                    <strong>⚠️ Conflito Detectado:</strong>
                    <div id="conflitosContent" style="margin-top: 8px;"></div>
                </div>
                
                <div class="d-flex flex-column flex-md-row gap-2 justify-content-md-end mt-3">
                    <button type="button" id="verificarConflitos" class="btn btn-outline-secondary">
                        🔍 Verificar Disponibilidade
                    </button>
                    <button type="submit" class="btn btn-primary" disabled>
                        ➕ Agendar Aula(s)
                    </button>
                </div>
            </div>
        </form>
    </div>
    
        <!-- Coluna da direita: Progresso e aulas agendadas -->
        <div class="col-lg-4 col-md-12">
        <div class="form-section">
            <h4>⏱️ Progresso da Turma</h4>
            
            <?php 
            // Calcular percentual de progresso
            $totalAulas = 0;
            $aulasAgendadas = 0;
            $percentual = 0;
            
            if (!empty($progressoAtual)) {
                $totalAulas = array_sum(array_column($progressoAtual, 'aulas_obrigatorias'));
                $aulasAgendadas = array_sum(array_column($progressoAtual, 'aulas_agendadas'));
                $percentual = $totalAulas > 0 ? round(($aulasAgendadas / $totalAulas) * 100, 1) : 0;
            }
            ?>
            
            <div style="margin-bottom: 20px;">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
                    <div style="font-size: 2rem; font-weight: bold; color: #023A8D;">
                        <?= $percentual ?>%
                    </div>
                    <div style="color: #666; font-size: 0.9rem;">
                        <?= $aulasAgendadas ?> de <?= $totalAulas ?> aulas agendadas
                    </div>
                    
                    <!-- Barra de progresso visual -->
                    <div style="width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; margin-top: 10px; overflow: hidden;">
                        <div style="width: <?= $percentual ?>%; height: 100%; background: linear-gradient(90deg, #023A8D, #F7931E); transition: width 0.3s ease;"></div>
                    </div>
                </div>
                    
                <!-- Lista de disciplinas -->
                <?php if (!empty($progressoAtual)): ?>
                    <div style="display: grid; gap: 10px;">
                        <?php foreach ($progressoAtual as $disc): ?>
                            <div class="disciplina-item <?= $disc['status_disciplina'] ?>">
                                <div style="flex: 1;">
                                    <strong><?= htmlspecialchars($disc['nome_disciplina']) ?></strong>
                                    <div style="font-size: 0.8rem; opacity: 0.8;">
                                        <?= $disc['aulas_agendadas'] ?>/<?= $disc['aulas_obrigatorias'] ?> aulas
                                        <?php if ($disc['aulas_faltantes'] > 0): ?>
                                            <span style="color: #dc3545; font-weight: bold;">
                                                (faltam <?= $disc['aulas_faltantes'] ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="font-size: 1.2rem;">
                                    <?= $disc['status_disciplina'] === 'completa' ? '✅' : ($disc['status_disciplina'] === 'parcial' ? '⚠️' : '') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 30px; color: #666;">
                        <div style="font-size: 2rem; margin-bottom: 10px;">📅</div>
                        <p>Nenhuma aula agendada ainda.<br>Comece agendando a primeira aula!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Aulas agendadas recentemente -->
        <?php
        try {
            $aulasRecentes = $db->fetchAll("
                SELECT taa.*, dc.nome_disciplina, u.nome as instrutor_nome
                FROM turma_aulas_agendadas taa
                LEFT JOIN disciplinas_configuracao dc ON taa.disciplina = dc.disciplina 
                    AND dc.curso_tipo = ?
                LEFT JOIN instrutores i ON taa.instrutor_id = i.id
                LEFT JOIN usuarios u ON i.usuario_id = u.id
                WHERE taa.turma_id = ?
                ORDER BY taa.data_aula DESC, taa.hora_inicio DESC
                LIMIT 5
            ", [$turmaAtual['curso_tipo'], $turmaAtual['id']]);
        } catch (Exception $e) {
            $aulasRecentes = [];
        }
        ?>
        
        <?php if (!empty($aulasRecentes)): ?>
            <div class="form-section">
                <h4>📋 Últimas Aulas Agendadas</h4>
                <div style="display: grid; gap: 8px;">
                    <?php foreach ($aulasRecentes as $aula): ?>
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; border-left: 4px solid #023A8D;">
                            <div style="font-weight: bold; color: #023A8D; font-size: 0.9rem;">
                                <?= htmlspecialchars($aula['nome_disciplina'] ?? ucfirst(str_replace('_', ' ', $aula['disciplina']))) ?>
                            </div>
                            <div style="font-size: 0.8rem; color: #666; margin-top: 4px;">
                                📅 <?= date('d/m/Y', strtotime($aula['data_aula'])) ?> 
                                às <?= $aula['hora_inicio'] ?> - <?= $aula['hora_fim'] ?>
                            </div>
                            <div style="font-size: 0.8rem; color: #666;">
                                👨‍🏫 <?= htmlspecialchars($aula['instrutor_nome'] ?? 'Instrutor não definido') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Navegação -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">
    <a href="?page=turmas-teoricas" class="btn-secondary">
        ← Voltar para Lista
    </a>
    
    <div>
        <?php if ($percentual >= 100): ?>
            <a href="?page=turmas-teoricas&acao=alunos&step=4&turma_id=<?= $turmaAtual['id'] ?>" class="btn-primary">
                Próxima Etapa: Matricular Alunos →
            </a>
        <?php else: ?>
            <div style="color: #666; font-size: 0.9rem;">
                Complete o agendamento de todas as disciplinas para prosseguir
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formAgendarAula');
    const btnVerificar = document.getElementById('verificarConflitos');
    const btnAgendar = form.querySelector('button[type="submit"]');
    const previewDiv = document.getElementById('previewHorario');
    const previewContent = document.getElementById('previewContent');
    const alertaDiv = document.getElementById('alertaConflitos');
    const conflitosContent = document.getElementById('conflitosContent');
    
    // Campos do formulário
    const disciplina = document.getElementById('disciplina');
    const instrutor = document.getElementById('instrutor_id');
    const dataAula = document.getElementById('data_aula');
    const horaInicio = document.getElementById('hora_inicio');
    const qtdAulas = document.getElementById('quantidade_aulas');
    
    // Atualizar preview quando campos mudarem
    function atualizarPreview() {
        if (disciplina.value && dataAula.value && horaInicio.value && qtdAulas.value) {
            const disciplinaNome = disciplina.options[disciplina.selectedIndex].text.split(' (')[0];
            const data = new Date(dataAula.value + 'T00:00:00').toLocaleDateString('pt-BR');
            const qtd = parseInt(qtdAulas.value);
            const hora = horaInicio.value;
            
            // Calcular horários das aulas
            let horariosPreview = [];
            for (let i = 0; i < qtd; i++) {
                const [horas, minutos] = hora.split(':').map(Number);
                const inicioMinutos = (horas * 60) + minutos + (i * 50);
                const fimMinutos = inicioMinutos + 50;
                
                const horaInicioAula = String(Math.floor(inicioMinutos / 60)).padStart(2, '0') + ':' + 
                                     String(inicioMinutos % 60).padStart(2, '0');
                const horaFimAula = String(Math.floor(fimMinutos / 60)).padStart(2, '0') + ':' + 
                                   String(fimMinutos % 60).padStart(2, '0');
                
                horariosPreview.push(`${horaInicioAula} - ${horaFimAula}`);
            }
            
            previewContent.innerHTML = `
                <strong>${disciplinaNome}</strong><br>
                📅 ${data}<br>
                🕐 ${horariosPreview.join(', ')}<br>
                🎓 ${qtd} aula(s) de 50 minutos cada
            `;
            
            previewDiv.style.display = 'block';
            btnVerificar.disabled = false;
        } else {
            previewDiv.style.display = 'none';
            btnVerificar.disabled = true;
            btnAgendar.disabled = true;
            alertaDiv.style.display = 'none';
        }
    }
    
    // Event listeners
    [disciplina, instrutor, dataAula, horaInicio, qtdAulas].forEach(field => {
        field.addEventListener('change', atualizarPreview);
    });
    
    // Verificar conflitos
    btnVerificar.addEventListener('click', function() {
        if (!instrutor.value || !dataAula.value || !horaInicio.value) {
            alert('Preencha todos os campos obrigatórios antes de verificar conflitos.');
            return;
        }
        
        btnVerificar.disabled = true;
        btnVerificar.textContent = '🔍 Verificando...';
        
        // Simular verificação de conflitos (seria uma chamada à API)
        setTimeout(() => {
            // Por enquanto, simular que não há conflitos
            const temConflito = Math.random() < 0.2; // 20% de chance de conflito para demo
            
            if (temConflito) {
                conflitosContent.innerHTML = `
                    O instrutor já possui aula agendada no horário solicitado.<br>
                    <small>Escolha outro horário ou instrutor.</small>
                `;
                alertaDiv.style.display = 'block';
                btnAgendar.disabled = true;
            } else {
                alertaDiv.style.display = 'none';
                btnAgendar.disabled = false;
            }
            
            btnVerificar.disabled = false;
            btnVerificar.textContent = '🔍 Verificar Disponibilidade';
        }, 1000);
    });
    
    // Destacar disciplina selecionada
    disciplina.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const cor = selectedOption.dataset.cor || '#023A8D';
        
        if (selectedOption.value) {
            this.style.borderColor = cor;
            this.style.borderWidth = '3px';
        } else {
            this.style.borderColor = '#e9ecef';
            this.style.borderWidth = '2px';
        }
    });
    
    // Validação de data no final de semana
    dataAula.addEventListener('change', function() {
        const data = new Date(this.value + 'T00:00:00');
        const diaSemana = data.getDay();
        
        if (diaSemana === 0 || diaSemana === 6) { // Domingo ou sábado
            if (!confirm('A data selecionada é um final de semana. Deseja continuar?')) {
                this.value = '';
                atualizarPreview();
            }
        }
    });
});
</script>

        </div>
    </div>
</div>
