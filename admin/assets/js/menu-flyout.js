/**
 * JavaScript Flyout/Hover Menu
 * Sidebar sempre compacta com flyouts que aparecem no hover
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Menu flyout carregado');
    
    // =====================================================
    // CONFIGURAÇÃO DOS FLYOUTS
    // =====================================================
    
    const flyoutConfig = {
        'alunos': {
            title: 'Alunos',
            items: [
                { icon: 'fas fa-list', text: 'Todos os Alunos', href: 'index.php?page=alunos' },
                { icon: 'fas fa-user-check', text: 'Alunos Ativos', href: 'index.php?page=alunos&status=em_formacao' },
                { icon: 'fas fa-clipboard-check', text: 'Alunos em Exame', href: 'index.php?page=alunos&status=em_exame' },
                { icon: 'fas fa-check-circle', text: 'Alunos Concluídos', href: 'index.php?page=alunos&status=concluido' }
            ]
        },
        'academico': {
            title: 'Acadêmico',
            items: [
                { icon: 'fas fa-chalkboard-teacher', text: 'Turmas Teóricas', href: 'index.php?page=turmas-teoricas' },
                // AJUSTE MENU PRESENCA TEORICA - REMOVIDO ITEM TEMPORÁRIO (FLYOUT)
                // O fluxo oficial de presença TEÓRICA é via:
                // Acadêmico → Turmas Teóricas → Detalhes da Turma → Seleção da Aula → Chamada/Frequência
                // O antigo menu "Presenças Teóricas (Temporário)" foi removido para evitar duplicidade de caminhos.
                { icon: 'fas fa-car-side', text: 'Aulas Práticas', href: 'pages/listar-aulas.php' },
                { icon: 'fas fa-calendar-alt', text: 'Agenda Geral', href: 'index.php?page=agendamento' },
                { icon: 'fas fa-chalkboard-teacher', text: 'Instrutores', href: 'index.php?page=instrutores' },
                { icon: 'fas fa-car', text: 'Veículos', href: 'index.php?page=veiculos' },
                { icon: 'fas fa-door-open', text: 'Salas', href: 'index.php?page=configuracoes-salas' }
            ]
        },
        'provas-exames': {
            title: 'Provas & Exames',
            items: [
                { icon: 'fas fa-stethoscope', text: 'Exame Médico', href: 'index.php?page=exames&tipo=medico' },
                { icon: 'fas fa-brain', text: 'Exame Psicotécnico', href: 'index.php?page=exames&tipo=psicotecnico' },
                { icon: 'fas fa-file-alt', text: 'Prova Teórica', href: 'index.php?page=exames&tipo=teorico' },
                { icon: 'fas fa-car', text: 'Prova Prática', href: 'index.php?page=exames&tipo=pratico' }
            ]
        },
        'financeiro': {
            title: 'Financeiro',
            items: [
                { icon: 'fas fa-file-invoice', text: 'Faturas', href: 'index.php?page=financeiro-faturas' },
                { icon: 'fas fa-receipt', text: 'Pagamentos', href: 'index.php?page=financeiro-despesas' },
                { icon: 'fas fa-chart-line', text: 'Relatórios Financeiros', href: 'index.php?page=financeiro-relatorios', adminOnly: true },
                { icon: 'fas fa-cog', text: 'Configurações Financeiras', href: '#', onclick: 'alert("Página em desenvolvimento"); return false;' }
            ]
        },
        'relatorios': {
            title: 'Relatórios',
            items: [
                { icon: 'fas fa-chart-bar', text: 'Frequência Teórica', href: 'pages/relatorio-frequencia.php' },
                { icon: 'fas fa-check-circle', text: 'Conclusão Prática', href: '#', onclick: 'alert("Relatório em desenvolvimento"); return false;' },
                { icon: 'fas fa-clipboard-check', text: 'Provas (Taxa de Aprovação)', href: '#', onclick: 'alert("Relatório em desenvolvimento"); return false;' },
                { icon: 'fas fa-exclamation-triangle', text: 'Inadimplência', href: 'index.php?page=financeiro-relatorios&tipo=inadimplencia', adminOnly: true }
            ]
        },
        'configuracoes': {
            title: 'Configurações',
            items: [
                { icon: 'fas fa-building', text: 'Dados do CFC', href: 'index.php?page=configuracoes&action=dados-cfc' },
                { icon: 'fas fa-layer-group', text: 'Cursos / Categorias', href: 'index.php?page=configuracoes-categorias' },
                { icon: 'fas fa-book', text: 'Disciplinas', href: 'index.php?page=configuracoes-disciplinas' },
                { icon: 'fas fa-database', text: 'Otimizar Banco (Índices)', href: 'index.php?page=aplicar-indices' },
                { icon: 'fas fa-search', text: 'Diagnóstico de Queries', href: 'index.php?page=diagnostico-queries' },
                { icon: 'fas fa-envelope', text: 'E-mail (SMTP)', href: 'index.php?page=configuracoes-smtp' }
            ]
        },
        'ferramentas': {
            title: 'Ferramentas',
            items: [
                { icon: 'fas fa-tools', text: 'Ferramentas Gerais', href: '?page=ferramentas' },
                { icon: 'fas fa-download', text: 'Exportar Dados', href: '?page=exportar' },
                { icon: 'fas fa-upload', text: 'Importar Dados', href: '?page=importar' }
            ]
        }
    };
    
    // =====================================================
    // CRIAÇÃO DOS FLYOUTS
    // =====================================================
    
    function createFlyouts() {
        console.log('Criando flyouts...');
        
        // Criar flyouts para grupos com submenus
        Object.keys(flyoutConfig).forEach(groupId => {
            const config = flyoutConfig[groupId];
            const toggle = document.querySelector(`[data-group="${groupId}"]`);
            
            if (toggle) {
                console.log('Criando flyout para:', groupId);
                
                // Criar elemento flyout
                const flyout = document.createElement('div');
                flyout.className = 'nav-flyout';
                const isAdmin = typeof window.ADMIN_IS_ADMIN !== 'undefined' && window.ADMIN_IS_ADMIN;
                const visibleItems = config.items.filter(item => !item.adminOnly || isAdmin);
                flyout.innerHTML = `
                    <div class="flyout-title">${config.title}</div>
                    ${visibleItems.map(item => `
                        <a href="${item.href || '#'}" class="flyout-item" ${item.onclick ? `onclick="${item.onclick}"` : ''}>
                            ${item.icon ? `<i class="${item.icon}"></i> ` : ''}${item.text}
                        </a>
                    `).join('')}
                `;
                
                // Adicionar flyout ao toggle
                toggle.parentElement.appendChild(flyout);
                console.log('Flyout criado para:', groupId);
            }
        });
        
        // Criar flyout para Dashboard (item único)
        const dashboardLink = document.querySelector('.nav-link[href="?page=dashboard"]');
        if (dashboardLink) {
            console.log('Criando flyout para Dashboard');
            const flyout = document.createElement('div');
            flyout.className = 'nav-flyout';
            flyout.innerHTML = `
                <div class="flyout-title">Dashboard</div>
                <a href="?page=dashboard" class="flyout-item">
                    Visão Geral
                </a>
            `;
            dashboardLink.parentElement.appendChild(flyout);
        }
        
        // Criar flyout para Sair (item único)
        const logoutLink = document.querySelector('.nav-link[href="logout.php"]');
        if (logoutLink) {
            console.log('Criando flyout para Sair');
            const flyout = document.createElement('div');
            flyout.className = 'nav-flyout';
            flyout.innerHTML = `
                <div class="flyout-title">Sair</div>
                <a href="logout.php" class="flyout-item">
                    Logout
                </a>
            `;
            logoutLink.parentElement.appendChild(flyout);
        }
        
        console.log('Total de flyouts criados:', document.querySelectorAll('.nav-flyout').length);
    }
    
    // =====================================================
    // CONTROLE DE HOVER DOS FLYOUTS - MELHORADO
    // =====================================================
    
    function setupFlyoutHover() {
        console.log('Configurando hover dos flyouts...');
        
        // Aguardar um pouco para garantir que os flyouts foram criados
        setTimeout(() => {
            // Configurar hover para todos os itens de navegação
            const navItems = document.querySelectorAll('.nav-item, .nav-group, .nav-link');
            console.log('Encontrados', navItems.length, 'itens de navegação');
            
            navItems.forEach((item, index) => {
                const flyout = item.querySelector('.nav-flyout');
                
                if (flyout) {
                    console.log('Configurando hover para item', index + 1, ':', item);
                    let hoverTimeout;
                    
                    // Calcular posição do flyout
                    function updateFlyoutPosition() {
                        const rect = item.getBoundingClientRect();
                        flyout.style.top = rect.top + 'px';
                        flyout.style.left = (rect.left + rect.width + 8) + 'px';
                    }
                    
                    item.addEventListener('mouseenter', function() {
                        console.log('Mouse entrou no item:', item);
                        clearTimeout(hoverTimeout);
                        updateFlyoutPosition();
                        flyout.style.opacity = '1';
                        flyout.style.visibility = 'visible';
                        flyout.classList.add('show');
                    });
                    
                    item.addEventListener('mouseleave', function() {
                        console.log('Mouse saiu do item:', item);
                        // Pequeno delay para evitar fechamento acidental
                        hoverTimeout = setTimeout(() => {
                            flyout.style.opacity = '0';
                            flyout.style.visibility = 'hidden';
                            flyout.classList.remove('show');
                        }, 150);
                    });
                    
                    // Atualizar posição quando a janela for redimensionada
                    window.addEventListener('resize', updateFlyoutPosition);
                } else {
                    console.log('Flyout não encontrado para item', index + 1, ':', item);
                }
            });
            
            // Configurar hover para flyouts também (para evitar fechamento ao mover mouse)
            const flyouts = document.querySelectorAll('.nav-flyout');
            console.log('Encontrados', flyouts.length, 'flyouts');
            
            flyouts.forEach((flyout, index) => {
                let hoverTimeout;
                
                flyout.addEventListener('mouseenter', function() {
                    console.log('Mouse entrou no flyout', index + 1);
                    clearTimeout(hoverTimeout);
                });
                
                flyout.addEventListener('mouseleave', function() {
                    console.log('Mouse saiu do flyout', index + 1);
                    hoverTimeout = setTimeout(() => {
                        flyout.style.opacity = '0';
                        flyout.style.visibility = 'hidden';
                        flyout.classList.remove('show');
                    }, 150);
                });
            });
        }, 100);
    }
    
    // =====================================================
    // CONTROLE DE RESPONSIVIDADE
    // =====================================================
    
    function handleResize() {
        console.log('Janela redimensionada:', window.innerWidth);
        
        const sidebar = document.querySelector('.admin-sidebar');
        
        if (window.innerWidth <= 1024) {
            // Em mobile, manter sempre expandido
            if (sidebar) {
                sidebar.style.width = '280px';
                sidebar.classList.add('mobile-expanded');
                console.log('Modo mobile ativado');
            }
        } else {
            // Em desktop, comportamento flyout
            if (sidebar) {
                sidebar.style.width = '70px';
                sidebar.classList.remove('mobile-expanded');
                console.log('Modo desktop ativado');
            }
        }
    }
    
    // =====================================================
    // INICIALIZAÇÃO
    // =====================================================
    
    // Criar flyouts
    createFlyouts();
    
    // Configurar hover
    setupFlyoutHover();
    
    // Configurar responsividade
    window.addEventListener('resize', handleResize);
    
    // Executar uma vez para configurar estado inicial
    handleResize();
    
    console.log('Menu flyout configurado com sucesso');
});
