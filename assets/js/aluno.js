// ── Dashboard do aluno ─────────────────────────────────────
async function carregarDashboardAluno(usuario) {
    const [respProgress, respCursos, respPerfil] = await Promise.all([
        fetch('../php/progress_listar.php', { credentials: 'include' }),
        fetch('../php/courses_listar.php',  { credentials: 'include' }),
        fetch('../php/sessao_check.php',    { credentials: 'include' }),
    ]);

    const dataProgress = await respProgress.json();
    const dataCursos   = await respCursos.json();

    // Estatísticas gerais
    let aulasConcluidas = 0;
    let totalAulas = 0;
    if (dataProgress.success) {
        totalAulas = dataProgress.lessons.length;
        aulasConcluidas = dataProgress.lessons.filter(l => parseInt(l.concluido) === 1).length;
    }
    const pctGeral = totalAulas > 0 ? Math.round((aulasConcluidas / totalAulas) * 100) : 0;

    document.getElementById('statConcluidas').textContent = aulasConcluidas;
    document.getElementById('statProgresso').textContent  = pctGeral + '%';

    let cursosMatriculados = [];
    if (dataCursos.success) {
        cursosMatriculados = dataCursos.courses.filter(c => parseInt(c.matriculado) === 1);
    }
    document.getElementById('statCursos').textContent = cursosMatriculados.length;

    // Perfil de investidor na dashboard
    const perfilUsuario = (dataCursos.success && dataCursos.perfil_usuario) ? dataCursos.perfil_usuario : null;
    const cardPerfil    = document.getElementById('cardPerfil');
    const perfilResult  = document.getElementById('perfilResult');
    const perfilCta     = document.getElementById('perfilCta');
    if (cardPerfil) {
        cardPerfil.style.display = 'block';
        if (perfilUsuario && profileConfig[perfilUsuario]) {
            const cfg = profileConfig[perfilUsuario];
            const badge = document.getElementById('perfilBadge');
            badge.textContent      = cfg.letter;
            badge.style.background = cfg.gradient;
            document.getElementById('perfilNome').textContent = cfg.name;
            document.getElementById('perfilDesc').textContent = cfg.desc;
            if (perfilResult) perfilResult.style.display = 'block';
            if (perfilCta)    perfilCta.style.display    = 'none';
        } else {
            if (perfilResult) perfilResult.style.display = 'none';
            if (perfilCta)    perfilCta.style.display    = 'block';
        }
    }

    // Grid de cursos matriculados
    const grid = document.getElementById('cursosGrid');
    const cardCursos = document.getElementById('cardCursos');
    const cardSemCursos = document.getElementById('cardSemCursos');

    if (cursosMatriculados.length > 0) {
        if (cardCursos) cardCursos.style.display = 'block';
        if (cardSemCursos) cardSemCursos.style.display = 'none';
        if (grid) {
            grid.innerHTML = '';
            const labelNivel = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };
            for (const c of cursosMatriculados) {
                const totalC = parseInt(c.total_aulas);
                const conclC = parseInt(c.concluidas);
                const pct = totalC > 0 ? Math.round((conclC / totalC) * 100) : 0;
                grid.innerHTML += `<div class="course-card">
                    <div class="course-nivel">
                        <span class="badge badge-${c.nivel}">${labelNivel[c.nivel] || c.nivel}</span>
                    </div>
                    <h3>${escHtml(c.nome)}</h3>
                    <div class="progress-bar"><div class="progress-fill" style="width:${pct}%"></div></div>
                    <small>${conclC}/${totalC} aulas — ${pct}%</small>
                </div>`;
            }
        }
    } else {
        if (cardCursos) cardCursos.style.display = 'none';
        if (cardSemCursos) cardSemCursos.style.display = 'block';
    }
}

// ── Tab switching ───────────────────────────────────────────
function switchTab(tabId) {
    const panelMap = { matriculados: 'tabMatriculados', explorar: 'tabExplorar' };
    const btnMap   = { matriculados: 'tabBtnMatriculados', explorar: 'tabBtnExplorar' };
    Object.keys(panelMap).forEach(t => {
        document.getElementById(panelMap[t]).style.display = 'none';
        document.getElementById(btnMap[t]).classList.remove('active');
    });
    document.getElementById(panelMap[tabId]).style.display = 'flex';
    document.getElementById(btnMap[tabId]).classList.add('active');
    lucide.createIcons();
}

// ── Dropdown toggle ────────────────────────────────────────
function toggleDropdown(btn) {
    const dd = btn.closest('.dropdown');
    const wasOpen = dd.classList.contains('open');
    document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
    if (!wasOpen) dd.classList.add('open');
}

// ── Investor profile config ────────────────────────────────
const profileConfig = {
    conservador: {
        letter: 'C',
        name: 'Perfil Conservador',
        desc: 'Foco em segurança e preservação de capital.',
        gradient: 'linear-gradient(135deg,#10b981,#059669)',
    },
    moderado: {
        letter: 'M',
        name: 'Perfil Moderado',
        desc: 'Equilíbrio entre crescimento e segurança.',
        gradient: 'linear-gradient(135deg,#4f7cff,#9b6dff)',
    },
    agressivo: {
        letter: 'A',
        name: 'Perfil Agressivo',
        desc: 'Foco em crescimento e altos retornos.',
        gradient: 'linear-gradient(135deg,#f59e0b,#ef4444)',
    },
};

// ── Deterministic cover gradient by course name ────────────
function coverGradient(nome) {
    const palette = [
        'linear-gradient(135deg,#4f7cff,#9b6dff)',
        'linear-gradient(135deg,#10b981,#3b82f6)',
        'linear-gradient(135deg,#f59e0b,#ef4444)',
        'linear-gradient(135deg,#6366f1,#8b5cf6)',
        'linear-gradient(135deg,#14b8a6,#06b6d4)',
        'linear-gradient(135deg,#ec4899,#8b5cf6)',
    ];
    let hash = 0;
    for (let i = 0; i < nome.length; i++) hash += nome.charCodeAt(i);
    return palette[hash % palette.length];
}

// ── Cursos do aluno ────────────────────────────────────────
async function carregarCursos() {
    const nivel  = (document.getElementById('filtroNivel')  || {}).value || '';
    const perfil = (document.getElementById('filtroPerfil') || {}).value || '';
    const qs = new URLSearchParams();
    if (nivel)  qs.set('nivel',  nivel);
    if (perfil) qs.set('perfil', perfil);

    const url = '../php/courses_listar.php' + (qs.toString() ? '?' + qs.toString() : '');
    const resp = await fetch(url, { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    // Investor profile card
    const perfilUsuario = data.perfil_usuario || null;
    const profileCard = document.getElementById('profileCard');
    if (profileCard && perfilUsuario && profileConfig[perfilUsuario]) {
        const cfg = profileConfig[perfilUsuario];
        const badge = document.getElementById('profileCardBadge');
        badge.textContent = cfg.letter;
        badge.style.background = cfg.gradient;
        document.getElementById('profileCardName').textContent = cfg.name;
        document.getElementById('profileCardDesc').textContent = cfg.desc;
        profileCard.style.display = 'flex';
    } else if (profileCard) {
        profileCard.style.display = 'none';
    }

    const gridCatalogo      = document.getElementById('cursosGrid');
    const gridMatriculados  = document.getElementById('gridMatriculados');
    const emptyMatriculados = document.getElementById('emptyMatriculados');
    const matLoading        = document.getElementById('matriculadosLoading');
    if (!gridCatalogo) return;

    gridCatalogo.innerHTML = '';
    if (gridMatriculados) gridMatriculados.innerHTML = '';

    const labelNivel = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };
    let totalMatriculados = 0;

    for (const c of data.courses) {
        const totalC = parseInt(c.total_aulas);
        const conclC = parseInt(c.concluidas || 0);
        const pct = totalC > 0 ? Math.round((conclC / totalC) * 100) : 0;
        const matriculado = parseInt(c.matriculado) === 1;
        const cover   = coverGradient(c.nome);
        const inicial = escHtml(c.nome.charAt(0).toUpperCase());

        const recomendado = perfilUsuario &&
            (c.perfil_recomendado === perfilUsuario || c.perfil_recomendado === 'todos');
        const badgeRec = recomendado ? '<span class="badge badge-success">Recomendado</span>' : '';

        if (matriculado) {
            totalMatriculados++;
            const proximaAula = c.proxima_aula
                ? `<div class="next-lesson"><i data-lucide="play-circle"></i><span>Próxima: ${escHtml(c.proxima_aula)}</span></div>`
                : '';
            if (gridMatriculados) gridMatriculados.innerHTML += `
<div class="course-card enrolled-card" data-curso-id="${c.id}">
  <div class="course-cover">
    <div class="course-cover-placeholder" style="background:${cover}">${inicial}</div>
  </div>
  <div class="enrolled-card-body">
    <div class="course-nivel"><span class="badge badge-${c.nivel}">${labelNivel[c.nivel] || c.nivel}</span></div>
    <h3>${escHtml(c.nome)}</h3>
    <div class="course-progress-section">
      <div class="progress-header">
        <span class="progress-label">Progresso</span>
        <span class="progress-pct">${pct}%</span>
      </div>
      <div class="progress-bar"><div class="progress-fill" style="width:${pct}%"></div></div>
      <small>${conclC} de ${totalC} aulas concluídas</small>
    </div>
    ${proximaAula}
    <div class="course-actions">
      <a href="aluno_cursos.html?curso=${c.id}" class="btn-action btn-action-primary" style="flex:1;justify-content:center">
        <i data-lucide="play"></i> Continuar
      </a>
      <div class="dropdown">
        <button class="btn-action btn-action-secondary" onclick="toggleDropdown(this)" aria-label="Mais opções">
          <i data-lucide="more-horizontal"></i>
        </button>
        <div class="dropdown-menu">
          <form method="POST" action="../php/courses_matricular.php">
            <input type="hidden" name="course_id" value="${c.id}">
            <input type="hidden" name="action" value="unenroll">
            <button type="submit" class="dropdown-item dropdown-item-danger"
                    onclick="return confirm('Cancelar matrícula em ${escJs(c.nome)}?')">
              <i data-lucide="x-circle"></i> Cancelar matrícula
            </button>
          </form>
        </div>
      </div>
    </div>
    <div class="course-rating-placeholder"></div>
  </div>
</div>`;
        } else {
            gridCatalogo.innerHTML += `
<div class="course-card explore-card" data-curso-id="${c.id}">
  <div class="course-cover">
    <div class="course-cover-placeholder" style="background:${cover}">${inicial}</div>
  </div>
  <div class="explore-card-body">
    <div class="course-nivel">
      <span class="badge badge-${c.nivel}">${labelNivel[c.nivel] || c.nivel}</span>
      ${badgeRec}
    </div>
    <h3>${escHtml(c.nome)}</h3>
    <p>${escHtml(c.descricao)}</p>
    <div class="course-rating-placeholder"></div>
    <small><i data-lucide="book" style="width:12px;height:12px;vertical-align:-2px"></i> ${totalC} aula(s)</small>
    <div class="course-actions">
      <form method="POST" action="../php/courses_matricular.php" style="width:100%">
        <input type="hidden" name="course_id" value="${c.id}">
        <input type="hidden" name="action" value="enroll">
        <button type="submit" class="btn-action btn-action-enroll btn-action-full">
          <i data-lucide="plus-circle"></i> Matricular-se
        </button>
      </form>
    </div>
  </div>
</div>`;
        }
    }

    // Tab badge
    const tabBadge = document.getElementById('tabBadgeMatriculados');
    if (tabBadge) {
        tabBadge.textContent = totalMatriculados;
        tabBadge.style.display = totalMatriculados > 0 ? 'inline-flex' : 'none';
    }

    // Enrolled tab visibility
    if (gridMatriculados) gridMatriculados.style.display  = totalMatriculados > 0 ? 'grid' : 'none';
    if (emptyMatriculados) emptyMatriculados.style.display = totalMatriculados > 0 ? 'none' : 'flex';
    if (matLoading) matLoading.style.display = 'none';

    // Catalog tab visibility
    const cursosLoading = document.getElementById('cursosLoading');
    if (cursosLoading) cursosLoading.style.display = 'none';
    gridCatalogo.style.display = 'grid';

    if (gridCatalogo.innerHTML === '') {
        gridCatalogo.innerHTML = `<div class="empty-state" style="grid-column:1/-1">
            <div class="empty-icon"><i data-lucide="search"></i></div>
            <p>Nenhum curso encontrado para os filtros selecionados.</p>
        </div>`;
    }

    if (typeof injectMediasNaGrade === 'function') injectMediasNaGrade();
}

async function aplicarFiltros() {
    const grid    = document.getElementById('cursosGrid');
    const loading = document.getElementById('cursosLoading');
    if (grid)    grid.style.display    = 'none';
    if (loading) loading.style.display = 'grid';
    await carregarCursos();
    lucide.createIcons();
}

function limparFiltros() {
    const n = document.getElementById('filtroNivel');
    const p = document.getElementById('filtroPerfil');
    if (n) n.value = '';
    if (p) p.value = '';
    aplicarFiltros();
}

async function carregarAulasDoCurso(cursoId) {
    const [respCurso, respAulas] = await Promise.all([
        fetch('../php/courses_listar.php', { credentials: 'include' }),
        fetch('../php/lessons_listar.php?curso_id=' + cursoId, { credentials: 'include' }),
    ]);
    const dataCursos = await respCurso.json();
    const dataAulas  = await respAulas.json();

    if (!dataAulas.success) return;

    const curso = dataCursos.success ? dataCursos.courses.find(c => parseInt(c.id) === parseInt(cursoId)) : null;

    const titulo = document.getElementById('cursoTitulo');
    const desc   = document.getElementById('cursoDescricao');
    if (titulo && curso) titulo.textContent = curso.nome;
    if (desc   && curso) desc.textContent   = curso.descricao;

    const tbody = document.getElementById('aulasBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (dataAulas.lessons.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="empty-state">Este curso ainda não possui aulas.</td></tr>';
        return;
    }

    const labelNivel = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };
    for (const a of dataAulas.lessons) {
        const concluido = parseInt(a.concluido) === 1;
        let conteudoHtml = `<strong>${escHtml(a.titulo)}</strong>`;
        if (a.descricao) conteudoHtml += `<br><small>${escHtml(a.descricao)}</small>`;
        if (a.video_link) {
            conteudoHtml += `<br><a href="${escHtml(a.video_link)}" target="_blank" rel="noopener" class="btn-table btn-table-video">Assistir vídeo</a>`;
        }
        if (a.attachment_path) {
            conteudoHtml += `<br><a href="../${escHtml(a.attachment_path)}" target="_blank" rel="noopener" class="btn-table btn-table-file">📎 ${escHtml(a.attachment_name || 'Material de apoio')}</a>`;
        }
        tbody.innerHTML += `<tr>
            <td>${conteudoHtml}</td>
            <td><span class="badge badge-${a.nivel}">${labelNivel[a.nivel] || a.nivel}</span></td>
            <td>${concluido
                ? '<span class="badge badge-success">Concluída</span>'
                : '<span class="badge" style="background:var(--bg-elevated);color:var(--text-muted);border:1px solid var(--border)">Pendente</span>'}</td>
            <td>
                <form method="POST" action="../php/progress_marcar.php">
                    <input type="hidden" name="lesson_id" value="${a.id}">
                    <input type="hidden" name="course_id" value="${cursoId}">
                    <input type="hidden" name="concluido" value="${concluido ? '0' : '1'}">
                    <input type="hidden" name="redirect" value="aluno_cursos.html?curso=${cursoId}">
                    <button type="submit" class="btn-table ${concluido ? 'btn-table-done' : 'btn-table-primary'}">
                        ${concluido ? 'Desmarcar' : 'Marcar concluída'}
                    </button>
                </form>
            </td>
        </tr>`;
    }

    // Load reviews section
    if (typeof carregarReviewsDoCurso === 'function') {
        await carregarReviewsDoCurso(cursoId);
        lucide.createIcons();
    }
}

// escHtml and escJs are defined in utils.js
