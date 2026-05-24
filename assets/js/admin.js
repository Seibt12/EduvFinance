// ── Dashboard ──────────────────────────────────────────────
async function carregarDashboard() {
    const resp = await fetch('../php/progress_stats.php', { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    document.getElementById('statAlunos').textContent  = data.totalStudents;
    document.getElementById('statAulas').textContent   = data.totalLessons;
    document.getElementById('statCursos').textContent  = data.totalCourses;
    document.getElementById('statMedia').textContent   = data.avgCompletion + '%';

    const tbody = document.getElementById('progressoBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    for (const a of data.progressoAlunos) {
        const pct = data.totalLessons > 0 ? Math.round((parseInt(a.concluidas) / data.totalLessons) * 100) : 0;
        tbody.innerHTML += `<tr>
            <td>${escHtml(a.nome)}</td>
            <td>${parseInt(a.concluidas)} / ${data.totalLessons}</td>
            <td>
                <div class="progress-wrap">
                    <div class="progress-bar"><div class="progress-fill" style="width:${pct}%"></div></div>
                    <span class="progress-label">${pct}%</span>
                </div>
            </td>
        </tr>`;
    }
}

// ── Alunos ─────────────────────────────────────────────────
async function carregarAlunos() {
    const resp = await fetch('../php/users_listar.php', { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const totalAulas = data.totalAulas;
    const el = document.getElementById('contadorAlunos');
    if (el) el.textContent = data.users.length;

    const tbody = document.getElementById('alunosBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (data.users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Nenhum aluno cadastrado.</td></tr>';
        return;
    }

    for (const u of data.users) {
        const concluidas = parseInt(u.total_concluidas);
        const pct = totalAulas > 0 ? Math.round((concluidas / totalAulas) * 100) : 0;
        const dataFormatada = formatarData(u.created_at);
        tbody.innerHTML += `<tr>
            <td>${escHtml(u.nome)}</td>
            <td>${escHtml(u.email)}</td>
            <td>
                <div class="progress-wrap">
                    <div class="progress-bar"><div class="progress-fill" style="width:${pct}%"></div></div>
                    <span class="progress-label">${concluidas}/${totalAulas} (${pct}%)</span>
                </div>
            </td>
            <td>${dataFormatada}</td>
            <td>
                <div class="actions">
                    <button class="btn-edit" onclick="mostrarFormEdicaoAluno(${u.id})">Editar</button>
                    <form method="POST" action="../php/users_excluir.php" style="display:inline"
                          onsubmit="return confirm('Excluir aluno ${escJs(u.nome)}?')">
                        <input type="hidden" name="id" value="${u.id}">
                        <button type="submit" class="btn-del">Excluir</button>
                    </form>
                </div>
            </td>
        </tr>`;
    }
}

async function mostrarFormEdicaoAluno(id) {
    const resp = await fetch('../php/users_buscar.php?id=' + id, { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const u = data.user;
    document.getElementById('editAlunoId').value    = u.id;
    document.getElementById('editAlunoNome').value  = u.nome;
    document.getElementById('editAlunoEmail').value = u.email;
    document.getElementById('editAlunoSenha').value = '';
    document.getElementById('formEdicaoAluno').style.display = 'block';
    document.getElementById('formEdicaoAluno').scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicaoAluno() {
    document.getElementById('formEdicaoAluno').style.display = 'none';
}

// ── Aulas ──────────────────────────────────────────────────
async function carregarAulas() {
    const resp = await fetch('../php/lessons_listar.php', { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const el = document.getElementById('contadorAulas');
    if (el) el.textContent = data.lessons.length;

    const tbody = document.getElementById('aulasBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (data.lessons.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="empty-state">Nenhuma aula cadastrada.</td></tr>';
        return;
    }

    const labelNivel = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };
    for (const l of data.lessons) {
        const dataFormatada = formatarData(l.created_at);
        tbody.innerHTML += `<tr>
            <td>${escHtml(l.titulo)}</td>
            <td><span class="badge badge-${l.nivel}">${labelNivel[l.nivel] || l.nivel}</span></td>
            <td>${dataFormatada}</td>
            <td>
                <div class="actions">
                    <button class="btn-edit" onclick="mostrarFormEdicaoAula(${l.id})">Editar</button>
                    <form method="POST" action="../php/lessons_excluir.php" style="display:inline"
                          onsubmit="return confirm('Excluir aula \'${escJs(l.titulo)}\'?')">
                        <input type="hidden" name="id" value="${l.id}">
                        <button type="submit" class="btn-del">Excluir</button>
                    </form>
                </div>
            </td>
        </tr>`;
    }
}

async function mostrarFormEdicaoAula(id) {
    const resp = await fetch('../php/lessons_buscar.php?id=' + id, { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const l = data.lesson;
    document.getElementById('formAulaId').value        = l.id;
    document.getElementById('formAulaTitulo').value    = l.titulo;
    document.getElementById('formAulaDescricao').value = l.descricao;
    document.getElementById('formAulaNivel').value     = l.nivel;
    document.getElementById('formAulaAction').value    = 'editar';
    document.getElementById('cardTituloAula').textContent = 'Editar Aula';
    document.getElementById('btnSubmitAula').textContent  = 'Salvar';
    document.getElementById('btnCancelarAula').style.display = 'inline-block';
    document.getElementById('formAulaEl').action = '../php/lessons_atualizar.php';
    document.getElementById('formAulaEl').scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicaoAula() {
    document.getElementById('formAulaId').value        = '';
    document.getElementById('formAulaTitulo').value    = '';
    document.getElementById('formAulaDescricao').value = '';
    document.getElementById('formAulaNivel').value     = '';
    document.getElementById('cardTituloAula').textContent = 'Nova Aula';
    document.getElementById('btnSubmitAula').textContent  = 'Criar Aula';
    document.getElementById('btnCancelarAula').style.display = 'none';
    document.getElementById('formAulaEl').action = '../php/lessons_inserir.php';
}

// ── Cursos ─────────────────────────────────────────────────
async function carregarCursos() {
    const [respCursos, respAulas] = await Promise.all([
        fetch('../php/courses_listar.php', { credentials: 'include' }),
        fetch('../php/lessons_listar.php', { credentials: 'include' }),
    ]);
    const dataCursos = await respCursos.json();
    const dataAulas  = await respAulas.json();

    if (!dataCursos.success) return;

    const el = document.getElementById('contadorCursos');
    if (el) el.textContent = dataCursos.courses.length;

    // Preencher lista de aulas nos checkboxes do formulário
    const checkboxList = document.getElementById('checkboxAulas');
    if (checkboxList && dataAulas.success) {
        checkboxList.innerHTML = '';
        const labelNivel = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };
        for (const a of dataAulas.lessons) {
            checkboxList.innerHTML += `<label class="checkbox-item">
                <input type="checkbox" name="lesson_ids[]" value="${a.id}" id="aula_${a.id}">
                ${escHtml(a.titulo)} <span class="badge badge-${a.nivel}">${labelNivel[a.nivel] || a.nivel}</span>
            </label>`;
        }
    }

    const tbody = document.getElementById('cursosBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (dataCursos.courses.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Nenhum curso cadastrado.</td></tr>';
        return;
    }

    const labelNivel = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };
    for (const c of dataCursos.courses) {
        tbody.innerHTML += `<tr>
            <td>${escHtml(c.nome)}</td>
            <td><span class="badge badge-${c.nivel}">${labelNivel[c.nivel] || c.nivel}</span></td>
            <td>${parseInt(c.total_aulas)}</td>
            <td>${parseInt(c.total_matriculas)}</td>
            <td>
                <div class="actions">
                    <button class="btn-edit" onclick="mostrarFormEdicaoCurso(${c.id})">Editar</button>
                    <form method="POST" action="../php/courses_excluir.php" style="display:inline"
                          onsubmit="return confirm('Excluir curso \'${escJs(c.nome)}\'?')">
                        <input type="hidden" name="id" value="${c.id}">
                        <button type="submit" class="btn-del">Excluir</button>
                    </form>
                </div>
            </td>
        </tr>`;
    }
}

async function mostrarFormEdicaoCurso(id) {
    const resp = await fetch('../php/courses_buscar.php?id=' + id, { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const c = data.course;
    document.getElementById('formCursoId').value        = c.id;
    document.getElementById('formCursoNome').value      = c.nome;
    document.getElementById('formCursoDescricao').value = c.descricao;
    document.getElementById('formCursoNivel').value     = c.nivel;

    // Marcar checkboxes das aulas vinculadas
    document.querySelectorAll('input[name="lesson_ids[]"]').forEach(cb => {
        cb.checked = c.lesson_ids.includes(parseInt(cb.value));
    });

    document.getElementById('cardTituloCurso').textContent = 'Editar Curso';
    document.getElementById('btnSubmitCurso').textContent  = 'Salvar';
    document.getElementById('btnCancelarCurso').style.display = 'inline-block';
    document.getElementById('formCursoEl').action = '../php/courses_atualizar.php';
    document.getElementById('formCursoEl').scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicaoCurso() {
    document.getElementById('formCursoId').value        = '';
    document.getElementById('formCursoNome').value      = '';
    document.getElementById('formCursoDescricao').value = '';
    document.getElementById('formCursoNivel').value     = '';
    document.querySelectorAll('input[name="lesson_ids[]"]').forEach(cb => cb.checked = false);
    document.getElementById('cardTituloCurso').textContent = 'Novo Curso';
    document.getElementById('btnSubmitCurso').textContent  = 'Criar Curso';
    document.getElementById('btnCancelarCurso').style.display = 'none';
    document.getElementById('formCursoEl').action = '../php/courses_inserir.php';
}

// ── Helpers ────────────────────────────────────────────────
function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJs(str) {
    return String(str).replace(/\\/g,'\\\\').replace(/'/g,"\\'");
}
