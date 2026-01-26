# 📹 PROPOSTA DE PLAYLIST - VÍDEOS TUTORIAIS CFC BOM CONSELHO

**Data:** Janeiro 2025  
**Objetivo:** Estruturar série completa de vídeos tutoriais para diferentes perfis de usuário  
**Baseado em:** Análise completa do código, rotas, controllers e menus atuais

---

## 📋 SUMÁRIO EXECUTIVO

### Módulos Identificados: 18 módulos principais

| Categoria | Módulos | Status Geral |
|-----------|---------|--------------|
| **Cadastros Base** | Alunos, Instrutores, Veículos, Serviços, Usuários | ✅ Pronto |
| **Acadêmico** | Turmas Teóricas, Presenças, Aulas Práticas, Agenda | ✅ Pronto |
| **Avaliações** | Exames & Provas | ✅ Pronto |
| **Financeiro** | Faturas, Pagamentos, Inadimplência | ✅ Pronto |
| **Comunicação** | Notificações, Comunicados, Reagendamentos | ✅ Pronto |
| **Configurações** | CFC, Disciplinas, Cursos, SMTP | ✅ Pronto |
| **Relatórios** | Dashboards, Relatórios diversos | ⚠️ Parcial |

### Perfis de Usuário

1. **ADMIN** - Acesso total (12 módulos visíveis)
2. **SECRETARIA** - Operação diária (7 módulos visíveis)
3. **INSTRUTOR** - Operação limitada (2 módulos visíveis)
4. **ALUNO** - Portal do aluno (3 módulos visíveis)

---

## 📊 INVENTÁRIO COMPLETO DE MÓDULOS

### 1. MÓDULO: ALUNOS

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Alunos |
| **Objetivo** | Cadastro completo de alunos, gestão de matrículas, histórico detalhado |
| **Quem usa** | ADMIN, SECRETARIA |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/alunos` (listagem), `/alunos/novo`, `/alunos/{id}` (modal completo) |
| **Fluxos Principais** | 1) Cadastrar aluno → 2) Matricular → 3) Visualizar histórico → 4) Editar dados |
| **Dependências** | Serviços (para matrícula), CFC (multi-tenant) |
| **Rotas** | `GET /alunos`, `POST /alunos/criar`, `GET /alunos/{id}`, `POST /alunos/{id}/matricular` |
| **Features Especiais** | Modal com abas (Dados/Matrícula/Histórico/Financeiro), upload de foto, busca avançada, filtros por status |

---

### 2. MÓDULO: MATRÍCULAS

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Matrículas |
| **Objetivo** | Criar e gerenciar matrículas de alunos em serviços (categorias CNH) |
| **Quem usa** | ADMIN, SECRETARIA |
| **Status** | ✅ Pronto |
| **Principais Telas** | Integrado no modal de aluno (aba "Matrícula") |
| **Fluxos Principais** | 1) Selecionar aluno → 2) Escolher serviço → 3) Definir plano de pagamento → 4) Salvar matrícula |
| **Dependências** | Alunos, Serviços |
| **Rotas** | `POST /alunos/{id}/matricular`, `GET /matriculas/{id}`, `POST /matriculas/{id}/atualizar` |
| **Features Especiais** | Cálculo automático de parcelas, controle de entrada/saldo, vinculação com turmas teóricas |

---

### 3. MÓDULO: TURMAS TEÓRICAS

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Turmas Teóricas |
| **Objetivo** | Criar turmas teóricas, agendar aulas, matricular alunos, controlar presenças |
| **Quem usa** | ADMIN, SECRETARIA |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/turmas-teoricas` (listagem), `/turmas-teoricas/novo` (wizard 4 etapas), `/turmas-teoricas/{id}` (detalhes) |
| **Fluxos Principais** | 1) Criar turma → 2) Agendar aulas → 3) Matricular alunos → 4) Registrar presenças |
| **Dependências** | Salas, Disciplinas, Cursos, Alunos (com exames OK) |
| **Rotas** | `GET /turmas-teoricas`, `POST /turmas-teoricas/criar`, `GET /turmas-teoricas/{id}/sessoes/novo` |
| **Features Especiais** | Wizard em 4 etapas, validação de exames antes de matricular, cálculo automático de frequência, controle de carga horária |

---

### 4. MÓDULO: PRESENÇAS TEÓRICAS

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Presenças Teóricas |
| **Objetivo** | Registrar presença dos alunos nas aulas teóricas, calcular frequência |
| **Quem usa** | ADMIN, SECRETARIA, INSTRUTOR |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/turmas-teoricas/{id}/sessoes/{sessionId}/presenca` |
| **Fluxos Principais** | 1) Acessar turma → 2) Selecionar aula → 3) Marcar presenças → 4) Salvar |
| **Dependências** | Turmas Teóricas, Sessões |
| **Rotas** | `GET /turmas-teoricas/{id}/sessoes/{sessionId}/presenca`, `POST /turmas-teoricas/{id}/sessoes/{sessionId}/presenca/salvar` |
| **Features Especiais** | Marcação individual e em lote, cálculo automático de frequência percentual, validação de elegibilidade para prova teórica |

---

### 5. MÓDULO: AULAS PRÁTICAS

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Aulas Práticas |
| **Objetivo** | Agendar aulas práticas, controlar execução, registrar km e observações |
| **Quem usa** | ADMIN, SECRETARIA, INSTRUTOR |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/agenda` (calendário), `/agenda/novo`, `/agenda/{id}` (detalhes), `/agenda/{id}/iniciar`, `/agenda/{id}/concluir` |
| **Fluxos Principais** | 1) Agendar aula → 2) Instrutor inicia → 3) Instrutor conclui → 4) Registrar km/observações |
| **Dependências** | Alunos, Instrutores, Veículos, Agenda |
| **Rotas** | `GET /agenda`, `POST /agenda/criar`, `POST /agenda/{id}/iniciar`, `POST /agenda/{id}/concluir` |
| **Features Especiais** | Validação de conflitos, limites diários, bloqueio por financeiro, calendário visual, filtros por instrutor/veículo |

---

### 6. MÓDULO: AGENDA

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Agenda Geral |
| **Objetivo** | Visualizar calendário unificado de aulas teóricas e práticas |
| **Quem usa** | ADMIN, SECRETARIA, INSTRUTOR, ALUNO |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/agenda` (calendário mensal/semanal), `/agenda/{id}` (detalhes) |
| **Fluxos Principais** | 1) Visualizar calendário → 2) Filtrar por tipo/instrutor → 3) Criar/editar agendamento |
| **Dependências** | Aulas Práticas, Turmas Teóricas |
| **Rotas** | `GET /agenda`, `GET /api/agenda/calendario` |
| **Features Especiais** | Calendário visual (FullCalendar), filtros avançados, visualização por perfil (instrutor vê só suas aulas) |

---

### 7. MÓDULO: EXAMES & PROVAS

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Exames & Provas |
| **Objetivo** | Registrar exames médico/psicotécnico e provas teórica/prática |
| **Quem usa** | ADMIN, SECRETARIA |
| **Status** | ✅ Pronto |
| **Principais Telas** | `admin/index.php?page=exames` (legado), integrado no modal de aluno |
| **Fluxos Principais** | 1) Selecionar aluno → 2) Escolher tipo (médico/psico/teórico/prático) → 3) Registrar resultado → 4) Validar elegibilidade |
| **Dependências** | Alunos |
| **Rotas** | APIs legadas em `admin/api/exames.php` |
| **Features Especiais** | Validação de elegibilidade para turmas/práticas, bloqueios automáticos, histórico completo |

---

### 8. MÓDULO: FINANCEIRO - FATURAS

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Financeiro - Faturas |
| **Objetivo** | Criar e gerenciar faturas de alunos, controlar vencimentos |
| **Quem usa** | ADMIN, SECRETARIA |
| **Status** | ✅ Pronto |
| **Principais Telas** | `admin/index.php?page=financeiro-faturas` (legado), `/financeiro` (novo) |
| **Fluxos Principais** | 1) Criar fatura → 2) Definir valor/vencimento → 3) Registrar pagamento → 4) Controlar inadimplência |
| **Dependências** | Alunos, Matrículas |
| **Rotas** | APIs legadas em `admin/api/financeiro-faturas.php` |
| **Features Especiais** | Bloqueio automático por inadimplência, integração com gateway EFI (parcial), cálculo de saldo devedor |

---

### 9. MÓDULO: FINANCEIRO - PAGAMENTOS

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Financeiro - Pagamentos |
| **Objetivo** | Registrar pagamentos, gerar carnês, sincronizar com gateway |
| **Quem usa** | ADMIN, SECRETARIA |
| **Status** | ✅ Pronto |
| **Principais Telas** | `admin/index.php?page=financeiro-despesas` (legado), integrado em faturas |
| **Fluxos Principais** | 1) Selecionar fatura → 2) Registrar pagamento → 3) Gerar carnê (opcional) → 4) Sincronizar com EFI |
| **Dependências** | Faturas |
| **Rotas** | `POST /api/payments/generate`, `POST /api/payments/mark-paid`, `POST /api/payments/sync` |
| **Features Especiais** | Geração de carnê digital, integração EFI (webhook), marcação manual de pagamento |

---

### 10. MÓDULO: INSTRUTORES

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Instrutores |
| **Objetivo** | Cadastrar e gerenciar instrutores, categorias, disponibilidade |
| **Quem usa** | ADMIN, SECRETARIA |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/instrutores` (listagem), `/instrutores/novo`, `/instrutores/{id}/editar` |
| **Fluxos Principais** | 1) Cadastrar instrutor → 2) Definir categorias → 3) Vincular a aulas |
| **Dependências** | Categorias CNH |
| **Rotas** | `GET /instrutores`, `POST /instrutores/criar`, `POST /instrutores/{id}/foto/upload` |
| **Features Especiais** | Upload de foto, categorias de habilitação, histórico de aulas |

---

### 11. MÓDULO: VEÍCULOS

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Veículos |
| **Objetivo** | Cadastrar veículos da frota, controlar disponibilidade |
| **Quem usa** | ADMIN, SECRETARIA |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/veiculos` (listagem), `/veiculos/novo`, `/veiculos/{id}/editar` |
| **Fluxos Principais** | 1) Cadastrar veículo → 2) Vincular a aulas práticas → 3) Controlar manutenção |
| **Dependências** | Nenhuma |
| **Rotas** | `GET /veiculos`, `POST /veiculos/criar`, `POST /veiculos/{id}/excluir` |
| **Features Especiais** | Controle de disponibilidade, histórico de uso |

---

### 12. MÓDULO: SERVIÇOS

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Serviços |
| **Objetivo** | Cadastrar serviços oferecidos (categorias CNH, pacotes) |
| **Quem usa** | ADMIN, SECRETARIA |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/servicos` (listagem), `/servicos/novo`, `/servicos/{id}/editar` |
| **Fluxos Principais** | 1) Cadastrar serviço → 2) Definir preço → 3) Usar em matrículas |
| **Dependências** | Nenhuma |
| **Rotas** | `GET /servicos`, `POST /servicos/criar`, `POST /servicos/{id}/toggle` |
| **Features Especiais** | Ativação/desativação de serviços, vinculação com matrículas |

---

### 13. MÓDULO: USUÁRIOS

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Usuários |
| **Objetivo** | Gerenciar usuários do sistema, criar acessos para alunos/instrutores |
| **Quem usa** | ADMIN, SECRETARIA |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/usuarios` (listagem), `/usuarios/novo`, `/usuarios/{id}/editar` |
| **Fluxos Principais** | 1) Criar usuário → 2) Definir perfil → 3) Gerar senha temporária → 4) Enviar link de ativação |
| **Dependências** | Alunos (para criar acesso de aluno), Instrutores (para criar acesso de instrutor) |
| **Rotas** | `GET /usuarios`, `POST /usuarios/criar-acesso-aluno`, `POST /usuarios/gerar-link-ativacao` |
| **Features Especiais** | Criação de acesso para aluno/instrutor, geração de senha temporária, link de ativação por email |

---

### 14. MÓDULO: NOTIFICAÇÕES

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Notificações |
| **Objetivo** | Central de notificações in-app para alunos e instrutores |
| **Quem usa** | ADMIN, SECRETARIA, INSTRUTOR, ALUNO |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/notificacoes` (feed), contador no header |
| **Fluxos Principais** | 1) Visualizar notificações → 2) Marcar como lida → 3) Acessar link relacionado |
| **Dependências** | Sistema de eventos (aulas, agendamentos) |
| **Rotas** | `GET /notificacoes`, `POST /notificacoes/{id}/ler`, `GET /api/notificacoes/contador` |
| **Features Especiais** | Contador em tempo real, marcação em lote, histórico |

---

### 15. MÓDULO: COMUNICADOS

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Comunicados |
| **Objetivo** | Enviar comunicados em massa para alunos/instrutores |
| **Quem usa** | ADMIN, SECRETARIA |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/comunicados/novo` |
| **Fluxos Principais** | 1) Criar comunicado → 2) Selecionar destinatários → 3) Enviar |
| **Dependências** | Notificações |
| **Rotas** | `GET /comunicados/novo`, `POST /comunicados` |
| **Features Especiais** | Seleção por perfil, filtros por turma/aluno |

---

### 16. MÓDULO: SOLICITAÇÕES DE REAGENDAMENTO

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Solicitações de Reagendamento |
| **Objetivo** | Gerenciar solicitações de alunos para reagendar aulas |
| **Quem usa** | ADMIN, SECRETARIA |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/solicitacoes-reagendamento` (listagem), `/solicitacoes-reagendamento/{id}` (detalhes) |
| **Fluxos Principais** | 1) Aluno solicita → 2) Secretaria avalia → 3) Aprovar/Recusar |
| **Dependências** | Agenda |
| **Rotas** | `GET /solicitacoes-reagendamento`, `POST /solicitacoes-reagendamento/{id}/aprovar` |
| **Features Especiais** | Aprovação/recusa, notificação automática ao aluno |

---

### 17. MÓDULO: CONFIGURAÇÕES

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Configurações |
| **Objetivo** | Configurar CFC, disciplinas, cursos teóricos, SMTP |
| **Quem usa** | ADMIN |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/configuracoes/cfc`, `/configuracoes/disciplinas`, `/configuracoes/cursos`, `/configuracoes/smtp` |
| **Fluxos Principais** | 1) Configurar dados do CFC → 2) Cadastrar disciplinas → 3) Criar cursos → 4) Configurar email |
| **Dependências** | Nenhuma (base do sistema) |
| **Rotas** | `GET /configuracoes/*`, `POST /configuracoes/*/salvar` |
| **Features Especiais** | Upload de logo, teste de SMTP, configuração de cursos teóricos |

---

### 18. MÓDULO: DASHBOARD

| Propriedade | Valor |
|-------------|-------|
| **Nome** | Dashboard |
| **Objetivo** | Visão geral com KPIs, estatísticas, resumo por perfil |
| **Quem usa** | ADMIN, SECRETARIA, INSTRUTOR, ALUNO |
| **Status** | ✅ Pronto |
| **Principais Telas** | `/dashboard` (varia por perfil) |
| **Fluxos Principais** | 1) Visualizar KPIs → 2) Acessar módulos rápidos → 3) Ver próximas ações |
| **Dependências** | Todos os módulos (agrega dados) |
| **Rotas** | `GET /dashboard` |
| **Features Especiais** | Cards específicos por perfil, gráficos, links rápidos |

---

## 🎬 PROPOSTA DE PLAYLIST ESTRUTURADA

### ESTRUTURA GERAL

A playlist será dividida em **7 trilhas principais**, priorizando o princípio 80/20 (fluxos mais usados primeiro):

1. **Trilha 0: Onboarding Geral** (2 episódios)
2. **Trilha 1: Operação Diária - Secretaria** (8 episódios)
3. **Trilha 2: Acadêmico - Turmas e Aulas** (6 episódios)
4. **Trilha 3: Financeiro** (4 episódios)
5. **Trilha 4: Administração e Configurações** (5 episódios)
6. **Trilha 5: Portal do Aluno** (3 episódios)
7. **Trilha 6: Portal do Instrutor** (2 episódios)

**Total:** 30 episódios (~10-15 horas de conteúdo)

---

## 📺 TABELA COMPLETA DE EPISÓDIOS

| Playlist/Trilha | Episódio | Objetivo | Telas/Rotas | Pré-requisitos | Persona | Duração (min) | Observações |
|-----------------|----------|----------|-------------|----------------|---------|---------------|-------------|
| **0. Onboarding Geral** | 0.1 | Visão geral do sistema e perfis | `/dashboard`, `/login` | Nenhum | Todos | 15 | Mostrar diferenças entre perfis, navegação básica |
| **0. Onboarding Geral** | 0.2 | Primeiro acesso e configuração inicial | `/login`, `/change-password`, `/configuracoes/cfc` | Nenhum | ADMIN | 12 | Trocar senha padrão, configurar logo CFC |
| **1. Operação Diária** | 1.1 | Cadastro completo de aluno | `/alunos/novo`, `/alunos/{id}` | Nenhum | SECRETARIA | 18 | Dados pessoais, documentos, foto, validações |
| **1. Operação Diária** | 1.2 | Criar matrícula e plano de pagamento | `/alunos/{id}/matricular` | Episódio 1.1 | SECRETARIA | 15 | Escolher serviço, definir entrada, parcelas |
| **1. Operação Diária** | 1.3 | Visualizar e editar histórico do aluno | `/alunos/{id}` (modal) | Episódio 1.1 | SECRETARIA | 20 | Abas do modal, adicionar observações, editar dados |
| **1. Operação Diária** | 1.4 | Cadastrar instrutores e veículos | `/instrutores/novo`, `/veiculos/novo` | Nenhum | SECRETARIA | 12 | Dados básicos, categorias, foto instrutor |
| **1. Operação Diária** | 1.5 | Cadastrar serviços oferecidos | `/servicos/novo` | Nenhum | SECRETARIA | 10 | Criar pacotes, definir preços, ativar/desativar |
| **1. Operação Diária** | 1.6 | Registrar exames médico e psicotécnico | Modal aluno → Aba Exames | Episódio 1.1 | SECRETARIA | 12 | Tipos de exame, resultados, validações |
| **1. Operação Diária** | 1.7 | Registrar provas teórica e prática | Modal aluno → Aba Exames | Episódio 1.1, 1.6 | SECRETARIA | 15 | Provas DETRAN, protocolos, aprovação/reprovação |
| **1. Operação Diária** | 1.8 | Busca avançada e filtros | `/alunos` (filtros) | Episódio 1.1 | SECRETARIA | 10 | Filtros por status, busca por CPF/nome, exportar |
| **2. Acadêmico** | 2.1 | Criar turma teórica (wizard completo) | `/turmas-teoricas/novo` | Episódio 0.2, 1.4 | SECRETARIA | 25 | 4 etapas: dados, sala, agendamento, matrículas |
| **2. Acadêmico** | 2.2 | Agendar aulas teóricas por disciplina | `/turmas-teoricas/{id}` → Agendar aulas | Episódio 2.1 | SECRETARIA | 18 | Selecionar disciplina, data/hora, carga horária |
| **2. Acadêmico** | 2.3 | Matricular alunos em turma teórica | `/turmas-teoricas/{id}/matricular` | Episódio 2.1, 1.1 | SECRETARIA | 15 | Validação de exames, vagas, elegibilidade |
| **2. Acadêmico** | 2.4 | Registrar presenças teóricas | `/turmas-teoricas/{id}/sessoes/{sessionId}/presenca` | Episódio 2.1, 2.3 | SECRETARIA, INSTRUTOR | 15 | Marcação individual/lote, cálculo de frequência |
| **2. Acadêmico** | 2.5 | Agendar aula prática | `/agenda/novo` | Episódio 1.1, 1.4 | SECRETARIA | 20 | Validações de conflito, limites, disponibilidade |
| **2. Acadêmico** | 2.6 | Visualizar agenda e calendário | `/agenda` | Episódio 2.5 | SECRETARIA, INSTRUTOR | 12 | Calendário visual, filtros, visualização por perfil |
| **3. Financeiro** | 3.1 | Criar e gerenciar faturas | `admin/index.php?page=financeiro-faturas` | Episódio 1.2 | SECRETARIA | 18 | Criar fatura, definir vencimento, vincular a aluno |
| **3. Financeiro** | 3.2 | Registrar pagamentos | Integrado em faturas | Episódio 3.1 | SECRETARIA | 15 | Marcar como pago, gerar carnê, sincronizar EFI |
| **3. Financeiro** | 3.3 | Controlar inadimplência e bloqueios | `/financeiro` (resumo) | Episódio 3.1 | SECRETARIA | 12 | Alunos em atraso, bloqueios automáticos, desbloqueio |
| **3. Financeiro** | 3.4 | Gerar carnê digital e integração EFI | `/api/payments/generate` | Episódio 3.2 | SECRETARIA | 20 | Geração de carnê, webhook EFI, sincronização |
| **4. Administração** | 4.1 | Gerenciar usuários e criar acessos | `/usuarios` | Episódio 0.2 | ADMIN | 18 | Criar usuário, gerar senha temporária, link de ativação |
| **4. Administração** | 4.2 | Configurar disciplinas e cursos teóricos | `/configuracoes/disciplinas`, `/configuracoes/cursos` | Episódio 0.2 | ADMIN | 20 | Cadastrar disciplinas, criar cursos, vincular disciplinas |
| **4. Administração** | 4.3 | Configurar SMTP e notificações por email | `/configuracoes/smtp` | Episódio 0.2 | ADMIN | 15 | Configurar servidor SMTP, testar envio |
| **4. Administração** | 4.4 | Configurar dados do CFC e logo | `/configuracoes/cfc` | Episódio 0.2 | ADMIN | 12 | Dados cadastrais, upload de logo, PWA |
| **4. Administração** | 4.5 | Dashboard e relatórios administrativos | `/dashboard` (ADMIN) | Todos os módulos | ADMIN | 15 | KPIs, estatísticas, acesso rápido |
| **5. Portal Aluno** | 5.1 | Acessar portal e visualizar progresso | `/dashboard` (ALUNO) | Episódio 1.1, 4.1 | ALUNO | 12 | Dashboard do aluno, progresso teórico/prático |
| **5. Portal Aluno** | 5.2 | Visualizar agenda e solicitar reagendamento | `/agenda` (ALUNO), `/agenda/{id}/solicitar-reagendamento` | Episódio 2.5, 5.1 | ALUNO | 15 | Minha agenda, solicitar reagendamento |
| **5. Portal Aluno** | 5.3 | Consultar situação financeira | `/financeiro` (ALUNO) | Episódio 3.1, 5.1 | ALUNO | 10 | Faturas, pagamentos, pendências |
| **6. Portal Instrutor** | 6.1 | Acessar portal e visualizar agenda | `/dashboard` (INSTRUTOR), `/agenda` (INSTRUTOR) | Episódio 1.4, 4.1 | INSTRUTOR | 12 | Dashboard instrutor, minha agenda do dia |
| **6. Portal Instrutor** | 6.2 | Registrar presenças e iniciar/concluir aulas | `/turmas-teoricas/{id}/sessoes/{sessionId}/presenca`, `/agenda/{id}/iniciar` | Episódio 2.4, 2.5, 6.1 | INSTRUTOR | 18 | Presenças teóricas, iniciar aula prática, registrar km |

---

## 🎯 RECOMENDAÇÕES PRÁTICAS DE GRAVAÇÃO

### Duração Ideal por Episódio

- **Episódios básicos:** 10-12 minutos
- **Episódios intermediários:** 15-18 minutos
- **Episódios avançados/complexos:** 20-25 minutos
- **Máximo absoluto:** 25 minutos (dividir se necessário)

### Ritmo e Narrativa

**Padrão de narrativa (problema → ação → resultado):**

1. **Contexto (30s-1min):** "Você precisa fazer X porque Y"
2. **Demonstração (80% do tempo):** Passo a passo com exemplos reais
3. **Resultado (30s):** "Agora você tem X funcionando, próximo passo é Y"
4. **Dica rápida (opcional, 30s):** "Armadilha comum: evite fazer Z porque..."

### Onde Usar Exemplos Reais

- **Sempre:** Cadastros (aluno, instrutor, veículo) - usar dados realistas mas fictícios
- **Sempre:** Fluxos completos (matrícula → turma → presença)
- **Evitar:** Dados sensíveis (CPFs reais, nomes reais de clientes)
- **Sugestão:** Criar conjunto de dados de demo padronizado

### Sequência de Gravação (Minimizar Retrabalho)

**Ordem recomendada:**

1. **Fase 1 - Base (gravar primeiro):**
   - Episódio 0.1, 0.2 (Onboarding)
   - Episódio 1.4, 1.5 (Cadastros base: instrutores, veículos, serviços)
   - Episódio 4.2, 4.3, 4.4 (Configurações - muda pouco)

2. **Fase 2 - Operação (gravar segundo):**
   - Episódio 1.1, 1.2, 1.3 (Alunos e matrículas)
   - Episódio 1.6, 1.7 (Exames e provas)
   - Episódio 2.1, 2.2, 2.3, 2.4 (Turmas teóricas)

3. **Fase 3 - Operação Avançada (gravar terceiro):**
   - Episódio 2.5, 2.6 (Aulas práticas e agenda)
   - Episódio 3.1, 3.2, 3.3, 3.4 (Financeiro)

4. **Fase 4 - Portais (gravar por último):**
   - Episódio 5.1, 5.2, 5.3 (Portal Aluno)
   - Episódio 6.1, 6.2 (Portal Instrutor)

**Razão:** Configurações mudam pouco, então gravar primeiro. Operação depende de dados criados, então seguir ordem lógica. Portais dependem de tudo funcionando.

### Checklist "Antes de Gravar"

#### Dados de Demo Necessários

- [ ] **CFC configurado:** Logo, dados cadastrais, SMTP (opcional)
- [ ] **Serviços cadastrados:** Pelo menos 3 serviços (A, B, ACC)
- [ ] **Instrutores:** 2-3 instrutores com categorias diferentes
- [ ] **Veículos:** 2-3 veículos cadastrados
- [ ] **Alunos de teste:** 5-10 alunos com diferentes status
- [ ] **Matrículas:** Algumas matrículas ativas
- [ ] **Turmas teóricas:** 1-2 turmas (uma ativa, uma concluída)
- [ ] **Aulas práticas:** Algumas agendadas para demonstração
- [ ] **Faturas:** Algumas faturas (abertas, pagas, vencidas)

#### Contas de Teste

- [ ] **Admin:** `admin@cfc.local` (senha alterada)
- [ ] **Secretaria:** Conta de teste criada
- [ ] **Instrutor:** Conta vinculada a instrutor cadastrado
- [ ] **Aluno:** Conta vinculada a aluno cadastrado

#### Permissões e Tenants

- [ ] **CFC ID:** Verificar se está usando CFC correto (ID 36 para produção)
- [ ] **Permissões:** Verificar que cada perfil tem acesso correto
- [ ] **Multi-tenant:** Se necessário, criar dados em CFC de teste

#### Ambiente de Gravação

- [ ] **Navegador:** Chrome/Firefox atualizado, modo anônimo (evitar extensões)
- [ ] **Resolução:** 1920x1080 (Full HD) ou superior
- [ ] **Zoom:** 100% (sem zoom)
- [ ] **Dados limpos:** Limpar cache antes de cada episódio
- [ ] **Microfone:** Testar qualidade de áudio
- [ ] **Tela:** Ocultar informações sensíveis (emails reais, etc.)

#### Ferramentas de Gravação

- [ ] **Software:** OBS Studio, Camtasia, ou similar
- [ ] **Áudio:** Microfone de qualidade, ambiente silencioso
- [ ] **Edição:** Software de edição preparado (se necessário)

### Armadilhas e Erros Comuns a Mencionar

#### Por Módulo

**Alunos:**
- ⚠️ Não cadastrar aluno sem CPF válido (bloqueia matrícula)
- ⚠️ Verificar se aluno já existe antes de cadastrar (buscar por CPF)
- ⚠️ Foto deve ser JPG/PNG e < 2MB

**Matrículas:**
- ⚠️ Serviço deve estar ativo para aparecer na lista
- ⚠️ Valor total não pode ser menor que entrada
- ⚠️ Matrícula cancelada não pode ser editada

**Turmas Teóricas:**
- ⚠️ Sala deve estar cadastrada antes de criar turma
- ⚠️ Carga horária agendada não pode ultrapassar total
- ⚠️ Aluno precisa ter exames médico/psico aprovados para matricular
- ⚠️ Não pode matricular aluno em turma que já começou (validação de data)

**Presenças:**
- ⚠️ Só pode marcar presença em aulas já agendadas
- ⚠️ Frequência é calculada automaticamente (não editar manualmente)
- ⚠️ Aluno precisa de 75% de frequência para fazer prova teórica

**Aulas Práticas:**
- ⚠️ Verificar conflitos antes de agendar (mesmo horário/aluno/instrutor)
- ⚠️ Limite de 3 aulas por dia por aluno
- ⚠️ Aluno inadimplente não pode agendar (bloqueio automático)
- ⚠️ Instrutor só vê suas próprias aulas no portal

**Financeiro:**
- ⚠️ Fatura vencida bloqueia aluno automaticamente
- ⚠️ Pagamento manual não sincroniza com EFI automaticamente
- ⚠️ Carnê gerado não pode ser editado (gerar novo se necessário)

**Configurações:**
- ⚠️ Alterar disciplinas/cursos afeta turmas futuras (não retroativas)
- ⚠️ SMTP deve ser testado antes de usar em produção
- ⚠️ Logo do CFC aparece no PWA (verificar tamanho/resolução)

---

## 📈 PRIORIZAÇÃO 80/20

### Episódios Críticos (Gravar Primeiro)

Estes episódios cobrem 80% do uso diário:

1. **Episódio 1.1** - Cadastro de aluno (base de tudo)
2. **Episódio 1.2** - Criar matrícula (fluxo mais comum)
3. **Episódio 2.1** - Criar turma teórica (operacional)
4. **Episódio 2.4** - Registrar presenças (diário)
5. **Episódio 2.5** - Agendar aula prática (diário)
6. **Episódio 3.1** - Criar faturas (financeiro básico)
7. **Episódio 3.2** - Registrar pagamentos (financeiro básico)

**Total:** 7 episódios (~2 horas) cobrem 80% do uso.

### Episódios Avançados (Gravar Depois)

Estes cobrem casos especiais e configurações:

- Episódios 4.x (Administração)
- Episódio 3.4 (Integração EFI)
- Episódios 5.x e 6.x (Portais)

---

## 🎨 PADRÃO VISUAL E NARRATIVA

### Abertura Padrão (10-15s)

"Olá! Bem-vindo ao tutorial do sistema CFC Bom Conselho. Neste episódio, você vai aprender [OBJETIVO]. Sou [NOME] e estou aqui para te guiar passo a passo."

### Estrutura Interna

1. **Contexto (1min):** Por que isso é importante?
2. **Pré-requisitos (30s):** O que você precisa ter feito antes?
3. **Demonstração (80%):** Passo a passo com exemplos
4. **Resumo (30s):** O que você aprendeu
5. **Próximo passo (15s):** "No próximo episódio, vamos..."

### Encerramento Padrão (10s)

"Espero que este tutorial tenha sido útil. Se tiver dúvidas, consulte a documentação ou entre em contato com o suporte. Até o próximo episódio!"

---

## 📝 NOTAS FINAIS

### Versões e Atualizações

- **Versão do sistema:** 1.0.0 (Janeiro 2025)
- **Última atualização desta proposta:** Janeiro 2025
- **Revisar quando:** Novos módulos forem adicionados ou fluxos mudarem significativamente

### Distribuição de Conteúdo

- **YouTube:** Playlist pública, organizada por trilhas
- **Documentação:** Links para vídeos na documentação do sistema
- **Onboarding:** Incluir links nos emails de boas-vindas

### Métricas de Sucesso

Acompanhar:
- Visualizações por episódio
- Taxa de conclusão (assistir até o fim)
- Comentários e dúvidas frequentes
- Tempo médio de visualização

---

**Fim da Proposta**
