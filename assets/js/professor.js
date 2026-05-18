const labelNivelProf = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };
const labelStatusProf = { pendente: 'Pendente', aprovado: 'Aprovado', rejeitado: 'Rejeitado' };

function badgeStatusProf(status) {
    const cls = status === 'aprovado' ? 'badge-success' : (status === 'rejeitado' ? 'badge-danger' : 'badge-pendente');
    return `<span class="badge ${cls}">${labelStatusProf[status] || status}</span>`;
}

async function carregarOfertasProfessor() {
    try {
        const [respCursos, respAulas] = await Promise.all([
            fetch('../php/professor_courses_listar.php', { credentials: 'include' }),
            fetch('../php/professor_lessons_listar.php', { credentials: 'include' }),
        ]);
        const dataCursos = await respCursos.json();
        const dataAulas  = await respAulas.json();

        if (dataCursos.success) {
            montarListaCursosProfessor(dataCursos.courses);
            preencherCheckboxesAulasCurso(dataAulas.success ? dataAulas.lessons : []);
        }
        if (dataAulas.success) montarListaAulasProfessor(dataAulas.lessons);
    } catch (err) {
        console.error('Erro ao carregar ofertas do professor:', err);
        exibirToast('Erro ao carregar ofertas do professor. Faça login novamente.', 'error');
    }
}

function preencherCheckboxesAulasCurso(lessons) {
    const container = document.getElementById('checkboxAulasCursoProf');
    if (!container) return;
    container.innerHTML = '';
    if (!lessons.length) {
        container.innerHTML = '<p class="empty-state">Cadastre aulas antes de vinculá-las a um curso.</p>';
        return;
    }
    for (const a of lessons) {
        container.innerHTML += `<label class="checkbox-item">
            <input type="checkbox" name="lesson_ids[]" value="${a.id}" id="aulaProf_${a.id}">
            ${escHtml(a.titulo)} <span class="badge badge-${a.nivel}">${labelNivelProf[a.nivel] || a.nivel}</span>
            ${badgeStatusProf(a.status)}
        </label>`;
    }
}

function montarListaCursosProfessor(courses) {
    const contador = document.getElementById('contadorCursosProf');
    const statCursos = document.getElementById('statOfertasCursos');
    const tbody = document.getElementById('cursosProfBody');
    if (contador) contador.textContent = courses.length;
    if (statCursos) statCursos.textContent = courses.length;
    if (!tbody) return;

    if (courses.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Nenhuma oferta de curso cadastrada.</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    for (const c of courses) {
        const parecer = c.status === 'rejeitado' && c.review_comment
            ? `<br><small class="form-hint">${escHtml(c.review_comment)}</small>` : '';
        tbody.innerHTML += `<tr>
            <td>${escHtml(c.nome)}${parecer}</td>
            <td><span class="badge badge-${c.nivel}">${labelNivelProf[c.nivel] || c.nivel}</span></td>
            <td>${badgeStatusProf(c.status)}</td>
            <td>${formatarData(c.created_at)}</td>
            <td class="actions">
                <button class="btn btn-sm btn-secondary" onclick="mostrarFormEdicaoCursoProf(${c.id})">Editar</button>
                <form method="POST" action="../php/professor_courses_excluir.php" style="display:inline"
                      onsubmit="return confirm('Excluir oferta de curso ${escJs(c.nome)}?')">
                    <input type="hidden" name="id" value="${c.id}">
                    <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                </form>
            </td>
        </tr>`;
    }
}

function montarListaAulasProfessor(lessons) {
    const contador = document.getElementById('contadorAulasProf');
    const statAulas = document.getElementById('statOfertasAulas');
    const tbody = document.getElementById('aulasProfBody');
    if (contador) contador.textContent = lessons.length;
    if (statAulas) statAulas.textContent = lessons.length;
    if (!tbody) return;

    if (lessons.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Nenhuma oferta de aula cadastrada.</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    for (const a of lessons) {
        const parecer = a.status === 'rejeitado' && a.review_comment
            ? `<br><small class="form-hint">${escHtml(a.review_comment)}</small>` : '';
        const midia = a.video_link || a.attachment_path
            ? `<br><small>${a.video_link ? '🎬 Vídeo' : ''}${a.video_link && a.attachment_path ? ' · ' : ''}${a.attachment_path ? '📎 Anexo' : ''}</small>`
            : '';
        tbody.innerHTML += `<tr>
            <td>${escHtml(a.titulo)}${midia}${parecer}</td>
            <td><span class="badge badge-${a.nivel}">${labelNivelProf[a.nivel] || a.nivel}</span></td>
            <td>${badgeStatusProf(a.status)}</td>
            <td>${formatarData(a.created_at)}</td>
            <td class="actions">
                <button class="btn btn-sm btn-secondary" onclick="mostrarFormEdicaoAulaProf(${a.id})">Editar</button>
                <form method="POST" action="../php/professor_lessons_excluir.php" style="display:inline"
                      onsubmit="return confirm('Excluir oferta de aula ${escJs(a.titulo)}?')">
                    <input type="hidden" name="id" value="${a.id}">
                    <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                </form>
            </td>
        </tr>`;
    }
}

async function mostrarFormEdicaoCursoProf(id) {
    const resp = await fetch('../php/professor_courses_buscar.php?id=' + id, { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const c = data.course;
    document.getElementById('formCursoProfId').value        = c.id;
    document.getElementById('formCursoProfNome').value      = c.nome;
    document.getElementById('formCursoProfDescricao').value = c.descricao;
    document.getElementById('formCursoProfNivel').value     = c.nivel;
    document.querySelectorAll('input[name="lesson_ids[]"]').forEach(cb => {
        cb.checked = (c.lesson_ids || []).map(Number).includes(parseInt(cb.value, 10));
    });
    document.getElementById('cardTituloCurso').textContent  = 'Editar Oferta de Curso';
    document.getElementById('btnSubmitCursoProf').textContent = 'Salvar';
    document.getElementById('btnCancelarCursoProf').style.display = 'inline-block';
    document.getElementById('formCursoProfEl').action = '../php/professor_courses_atualizar.php';
    document.getElementById('formCursoProfEl').scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicaoCursoProf() {
    document.getElementById('formCursoProfId').value        = '';
    document.getElementById('formCursoProfNome').value      = '';
    document.getElementById('formCursoProfDescricao').value = '';
    document.getElementById('formCursoProfNivel').value     = '';
    document.querySelectorAll('input[name="lesson_ids[]"]').forEach(cb => { cb.checked = false; });
    document.getElementById('cardTituloCurso').textContent  = 'Nova Oferta de Curso';
    document.getElementById('btnSubmitCursoProf').textContent = 'Criar Oferta';
    document.getElementById('btnCancelarCursoProf').style.display = 'none';
    document.getElementById('formCursoProfEl').action = '../php/professor_courses_inserir.php';
}

async function mostrarFormEdicaoAulaProf(id) {
    const resp = await fetch('../php/professor_lessons_buscar.php?id=' + id, { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const a = data.lesson;
    document.getElementById('formAulaProfId').value        = a.id;
    document.getElementById('formAulaProfTitulo').value   = a.titulo;
    document.getElementById('formAulaProfDescricao').value = a.descricao;
    document.getElementById('formAulaProfNivel').value    = a.nivel;
    document.getElementById('formAulaProfVideo').value     = a.video_link || '';

    const hint = document.getElementById('anexoAtualProf');
    if (hint) {
        if (a.attachment_path) {
            hint.style.display = 'block';
            hint.innerHTML = `Anexo atual: <a href="../${escHtml(a.attachment_path)}" target="_blank">${escHtml(a.attachment_name || 'Download')}</a> (envie outro arquivo para substituir)`;
        } else {
            hint.style.display = 'none';
            hint.textContent = '';
        }
    }

    document.getElementById('cardTituloAula').textContent  = 'Editar Oferta de Aula';
    document.getElementById('btnSubmitAulaProf').textContent = 'Salvar';
    document.getElementById('btnCancelarAulaProf').style.display = 'inline-block';
    document.getElementById('formAulaProfEl').action = '../php/professor_lessons_atualizar.php';
    document.getElementById('formAulaProfEl').scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicaoAulaProf() {
    document.getElementById('formAulaProfId').value        = '';
    document.getElementById('formAulaProfTitulo').value   = '';
    document.getElementById('formAulaProfDescricao').value = '';
    document.getElementById('formAulaProfNivel').value    = '';
    document.getElementById('formAulaProfVideo').value    = '';
    document.getElementById('formAulaProfAnexo').value    = '';
    const hint = document.getElementById('anexoAtualProf');
    if (hint) { hint.style.display = 'none'; hint.textContent = ''; }
    document.getElementById('cardTituloAula').textContent  = 'Nova Oferta de Aula';
    document.getElementById('btnSubmitAulaProf').textContent = 'Criar Oferta';
    document.getElementById('btnCancelarAulaProf').style.display = 'none';
    document.getElementById('formAulaProfEl').action = '../php/professor_lessons_inserir.php';
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJs(str) {
    return String(str).replace(/\\/g,'\\\\').replace(/'/g,"\\'");
}
