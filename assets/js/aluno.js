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

// ── Cursos do aluno ────────────────────────────────────────
async function carregarCursos() {
    const resp = await fetch('../php/courses_listar.php', { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const grid = document.getElementById('cursosGrid');
    if (!grid) return;
    grid.innerHTML = '';

    const labelNivel = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };
    for (const c of data.courses) {
        const totalC = parseInt(c.total_aulas);
        const conclC = parseInt(c.concluidas || 0);
        const pct = totalC > 0 ? Math.round((conclC / totalC) * 100) : 0;
        const matriculado = parseInt(c.matriculado) === 1;

        let progressHtml = '';
        let actionsHtml  = '';
        if (matriculado) {
            progressHtml = `<div class="progress-bar" style="margin-top:8px">
                <div class="progress-fill" style="width:${pct}%"></div>
            </div>
            <small>${conclC}/${totalC} — ${pct}%</small>`;
            actionsHtml = `<div class="course-actions">
                <a href="aluno_cursos.html?curso=${c.id}" class="btn btn-sm btn-primary">Ver aulas</a>
                <form method="POST" action="../php/courses_matricular.php" style="display:inline">
                    <input type="hidden" name="course_id" value="${c.id}">
                    <input type="hidden" name="action" value="unenroll">
                    <button type="submit" class="btn btn-sm btn-danger"
                            onclick="return confirm('Cancelar matrícula?')">Cancelar matrícula</button>
                </form>
            </div>`;
        } else {
            actionsHtml = `<div class="course-actions">
                <form method="POST" action="../php/courses_matricular.php">
                    <input type="hidden" name="course_id" value="${c.id}">
                    <input type="hidden" name="action" value="enroll">
                    <button type="submit" class="btn btn-sm btn-primary">Matricular-se</button>
                </form>
            </div>`;
        }

        grid.innerHTML += `<div class="course-card">
            <div class="course-nivel">
                <span class="badge badge-${c.nivel}">${labelNivel[c.nivel] || c.nivel}</span>
                ${matriculado ? '<span class="badge badge-success">Matriculado</span>' : ''}
            </div>
            <h3>${escHtml(c.nome)}</h3>
            <p>${escHtml(c.descricao)}</p>
            <small>${totalC} aula(s)</small>
            ${progressHtml}
            ${actionsHtml}
        </div>`;
    }
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
        tbody.innerHTML += `<tr>
            <td>${escHtml(a.titulo)}</td>
            <td><span class="badge badge-${a.nivel}">${labelNivel[a.nivel] || a.nivel}</span></td>
            <td>${concluido
                ? '<span class="badge badge-success">Concluída</span>'
                : '<span class="badge">Pendente</span>'}</td>
            <td>
                <form method="POST" action="../php/progress_marcar.php">
                    <input type="hidden" name="lesson_id" value="${a.id}">
                    <input type="hidden" name="concluido" value="${concluido ? '0' : '1'}">
                    <input type="hidden" name="redirect" value="aluno_cursos.html?curso=${cursoId}">
                    <button type="submit" class="btn btn-sm ${concluido ? 'btn-secondary' : 'btn-primary'}">
                        ${concluido ? 'Desmarcar' : 'Marcar concluída'}
                    </button>
                </form>
            </td>
        </tr>`;
    }
}

// ── Helpers ────────────────────────────────────────────────
function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
