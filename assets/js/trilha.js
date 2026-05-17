/**
 * trilha.js
 * Lógica da Trilha de Aprendizagem
 * Carrega aulas em sequência, exibe status de progresso, permite marcar como concluída
 */

// ── Carregar lista de cursos para seleção de trilha ────────────────────────
async function carregarCursosParaTrilha() {
    const resp = await fetch('../php/courses_listar.php', { credentials: 'include' });
    const data = await resp.json();
    
    if (!data.success) {
        document.getElementById('cursosGrid').innerHTML = '<p class="empty-state">Erro ao carregar cursos.</p>';
        return;
    }

    const grid = document.getElementById('cursosGrid');
    const cursosMatriculados = data.courses.filter(c => parseInt(c.matriculado) === 1);

    if (cursosMatriculados.length === 0) {
        grid.innerHTML = '<p class="empty-state">Você ainda não está matriculado em nenhum curso. <a href="aluno_cursos.html">Explorar cursos</a></p>';
        return;
    }

    grid.innerHTML = '';
    const labelNivel = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };

    for (const curso of cursosMatriculados) {
        const totalC = parseInt(curso.total_aulas);
        const conclC = parseInt(curso.concluidas || 0);
        const pct = totalC > 0 ? Math.round((conclC / totalC) * 100) : 0;

        grid.innerHTML += `
            <div class="course-card">
                <div class="course-nivel">
                    <span class="badge badge-${curso.nivel}">${labelNivel[curso.nivel] || curso.nivel}</span>
                </div>
                <h3>${escHtml(curso.nome)}</h3>
                <p>${escHtml(curso.descricao)}</p>
                <div class="progress-bar" style="margin: 12px 0;">
                    <div class="progress-fill" style="width:${pct}%"></div>
                </div>
                <small>${conclC}/${totalC} — ${pct}%</small>
                <div style="margin-top: 12px;">
                    <a href="aluno_trilha.html?curso_id=${curso.id}" class="btn btn-sm btn-primary btn-full">
                        Ver Trilha
                    </a>
                </div>
            </div>
        `;
    }
}

// ── Carregar trilha de um curso específico ──────────────────────────────────
async function carregarTrilha(cursoId) {
    try {
        const resp = await fetch('../php/trilha_listar.php?curso_id=' + cursoId, { 
            credentials: 'include' 
        });
        const data = await resp.json();

        if (!data.success) {
            exibirToast(data.message || 'Erro ao carregar trilha', 'error');
            return;
        }

        const curso = data.course;
        const aulas = data.lessons;
        const stats = data.stats;

        // Preencher header
        document.getElementById('trilhaTitulo').textContent = curso.nome;
        document.getElementById('trilhaDescricao').textContent = curso.descricao;

        // Atualizar estatísticas
        atualizarEstatisticas(stats);

        // Renderizar trilha
        renderizarTrilha(aulas, cursoId);

        // Guardar estado global
        window.trilhaAtual = {
            cursoId: cursoId,
            aulas: aulas,
            stats: stats
        };

    } catch (erro) {
        exibirToast('Erro ao carregar trilha: ' + erro.message, 'error');
    }
}

// ── Atualizar dados de estatísticas ────────────────────────────────────────
function atualizarEstatisticas(stats) {
    document.getElementById('statPercentual').textContent = stats.percentual + '%';
    document.getElementById('statConcluidas').textContent = stats.concluidas;
    document.getElementById('statTotal').textContent = stats.total;
    document.getElementById('progressText').textContent = stats.concluidas + '/' + stats.total;

    const progressBar = document.getElementById('progressBarGrande');
    if (progressBar) {
        progressBar.style.width = stats.percentual + '%';
    }
}

// ── Renderizar trilha visual ───────────────────────────────────────────────
function renderizarTrilha(aulas, cursoId) {
    const container = document.getElementById('trilhaScroll');
    container.innerHTML = '';

    if (aulas.length === 0) {
        container.innerHTML = '<p class="empty-state">Este curso ainda não possui aulas.</p>';
        return;
    }

    const labelNivel = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };

    for (let i = 0; i < aulas.length; i++) {
        const aula = aulas[i];
        const isUltima = i === aulas.length - 1;

        // Card da aula
        const card = document.createElement('div');
        card.className = `trilha-card trilha-status-${aula.status}`;
        card.id = `trilha-card-${aula.id}`;

        let iconStatus = '';
        let classeStatus = '';

        if (aula.status === 'concluida') {
            iconStatus = '✓';
            classeStatus = 'trilha-concluida';
        } else if (aula.status === 'em_progresso') {
            iconStatus = '▶';
            classeStatus = 'trilha-em-progresso';
        } else if (aula.status === 'disponivel') {
            iconStatus = '◉';
            classeStatus = 'trilha-disponivel';
        } else {
            iconStatus = '◯';
            classeStatus = 'trilha-bloqueada';
        }

        card.innerHTML = `
            <div class="trilha-card-inner ${classeStatus}">
                <div class="trilha-card-numero">${aula.posicao}</div>
                <div class="trilha-card-icon">${iconStatus}</div>
                <div class="trilha-card-titulo">${escHtml(aula.titulo)}</div>
                <div class="trilha-card-nivel">
                    <span class="badge badge-sm badge-${aula.nivel}">${labelNivel[aula.nivel] || aula.nivel}</span>
                </div>
                <div class="trilha-card-acao">
                    ${aula.status !== 'futura' ? `
                        <button onclick="exibirDetalheAula(${aula.id}, '${escHtml(aula.titulo)}', ${parseInt(aula.concluido)}, ${cursoId})" class="btn btn-xs btn-secondary">
                            Detalhes
                        </button>
                    ` : `
                        <span class="badge">Bloqueada</span>
                    `}
                </div>
            </div>
        `;

        card.onclick = (e) => {
            if (aula.status !== 'futura' && e.target.closest('button') === null) {
                exibirDetalheAula(aula.id, aula.titulo, parseInt(aula.concluido), cursoId);
            }
        };

        container.appendChild(card);

        // Conector entre cards
        if (!isUltima) {
            const conector = document.createElement('div');
            conector.className = 'trilha-conector';
            
            // O conector é colorido se ambas as aulas estão completas ou a próxima está desbloqueada
            if (aula.status === 'concluida' || aula.status === 'em_progresso') {
                conector.classList.add('trilha-conector-ativo');
            }
            
            container.appendChild(conector);
        }
    }

    // Auto-scroll para a aula em progresso
    setTimeout(() => {
        const cardEmProgresso = container.querySelector('.trilha-em-progresso');
        if (cardEmProgresso) {
            cardEmProgresso.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }, 300);
}

// ── Exibir detalhes da aula ────────────────────────────────────────────────
function exibirDetalheAula(aulaId, aulaTitulo, concluido, cursoId) {
    const detalheDiv = document.getElementById('cardDetalheAula');
    const tituloDiv = document.getElementById('detalheAulaTitulo');
    const conteudoDiv = document.getElementById('detalheAulaConteudo');

    tituloDiv.textContent = aulaTitulo;

    const statusTexto = concluido ? 'Concluída' : 'Pendente';
    const botaoTexto = concluido ? 'Desmarcar Concluída' : 'Marcar Concluída';
    const botaoClasse = concluido ? 'btn-secondary' : 'btn-primary';

    conteudoDiv.innerHTML = `
        <div style="margin-bottom: 20px;">
            <p style="color: #888; margin-bottom: 12px;">Status: <strong>${statusTexto}</strong></p>
            
            <div class="form-group">
                <form method="POST" action="../php/progress_marcar.php" style="display: flex; gap: 8px;">
                    <input type="hidden" name="lesson_id" value="${aulaId}">
                    <input type="hidden" name="concluido" value="${concluido ? '0' : '1'}">
                    <input type="hidden" name="redirect" value="aluno_trilha.html?curso_id=${cursoId}">
                    <button type="submit" class="btn btn-sm ${botaoClasse}">
                        ${botaoTexto}
                    </button>
                </form>
            </div>
        </div>
    `;

    detalheDiv.style.display = 'block';

    // Scroll suave para o card de detalhes
    setTimeout(() => {
        detalheDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
}

// ── Fechar detalhes da aula ────────────────────────────────────────────────
function fecharDetalhes() {
    document.getElementById('cardDetalheAula').style.display = 'none';
}

// ── Atualizar progresso em tempo real ──────────────────────────────────────
async function atualizarProgressoTrilha(cursoId) {
    try {
        const resp = await fetch('../php/trilha_progresso.php?curso_id=' + cursoId, { 
            credentials: 'include' 
        });
        const data = await resp.json();

        if (data.success) {
            atualizarEstatisticas(data.stats);
            
            // Recarregar trilha completa após marcar como concluída
            setTimeout(() => carregarTrilha(cursoId), 1500);
        }
    } catch (erro) {
        console.error('Erro ao atualizar progresso:', erro);
    }
}

// ── Helper para escape HTML ────────────────────────────────────────────────
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
