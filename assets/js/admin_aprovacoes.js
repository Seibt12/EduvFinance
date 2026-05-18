const labelNivel = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };
const labelStatus = { pendente: 'Pendente', aprovado: 'Aprovado', rejeitado: 'Rejeitado' };

function badgeStatus(status) {
    const cls = status === 'aprovado' ? 'badge-success' : (status === 'rejeitado' ? 'badge-danger' : 'badge-pendente');
    return `<span class="badge ${cls}">${labelStatus[status] || status}</span>`;
}

async function carregarOfertasCursosAdmin() {
    const filtro = document.getElementById('filtroStatusCursos');
    const status = filtro ? filtro.value : 'pendente';
    const resp = await fetch('../php/admin_professor_courses_listar.php?status=' + encodeURIComponent(status), { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const contador = document.getElementById('contadorOfertasCursos');
    if (contador) contador.textContent = data.courses.length;

    const tbody = document.getElementById('ofertasCursosBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (data.courses.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Nenhuma oferta encontrada.</td></tr>';
        return;
    }

    for (const c of data.courses) {
        tbody.innerHTML += `<tr>
            <td>${escHtml(c.nome)}</td>
            <td>${escHtml(c.professor_nome)}</td>
            <td><span class="badge badge-${c.nivel}">${labelNivel[c.nivel] || c.nivel}</span></td>
            <td>${badgeStatus(c.status)}</td>
            <td>${formatarData(c.created_at)}</td>
            <td class="actions">
                <button class="btn btn-sm btn-secondary" onclick="analisarOfertaCurso(${c.id})">Analisar</button>
            </td>
        </tr>`;
    }
}

async function carregarOfertasAulasAdmin() {
    const filtro = document.getElementById('filtroStatusAulas');
    const status = filtro ? filtro.value : 'pendente';
    const resp = await fetch('../php/admin_professor_lessons_listar.php?status=' + encodeURIComponent(status), { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const contador = document.getElementById('contadorOfertasAulas');
    if (contador) contador.textContent = data.lessons.length;

    const tbody = document.getElementById('ofertasAulasBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (data.lessons.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Nenhuma oferta encontrada.</td></tr>';
        return;
    }

    for (const a of data.lessons) {
        tbody.innerHTML += `<tr>
            <td>${escHtml(a.titulo)}</td>
            <td>${escHtml(a.professor_nome)}</td>
            <td><span class="badge badge-${a.nivel}">${labelNivel[a.nivel] || a.nivel}</span></td>
            <td>${badgeStatus(a.status)}</td>
            <td>${formatarData(a.created_at)}</td>
            <td class="actions">
                <button class="btn btn-sm btn-secondary" onclick="analisarOfertaAula(${a.id})">Analisar</button>
            </td>
        </tr>`;
    }
}

async function analisarOfertaCurso(id) {
    const resp = await fetch('../php/admin_professor_courses_buscar.php?id=' + id, { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const c = data.course;
    const card = document.getElementById('cardAnaliseCurso');
    if (!card) return;

    let aulasHtml = '<p class="empty-state">Nenhuma aula vinculada.</p>';
    if (c.lessons && c.lessons.length > 0) {
        aulasHtml = '<ul class="analise-lista">';
        for (const l of c.lessons) {
            aulasHtml += `<li>${escHtml(l.titulo)} — ${badgeStatus(l.status)}</li>`;
        }
        aulasHtml += '</ul>';
    }

    document.getElementById('analiseCursoId').value = c.id;
    document.getElementById('analiseCursoConteudo').innerHTML = `
        <p><strong>Professor:</strong> ${escHtml(c.professor_nome)} (${escHtml(c.professor_email)})</p>
        <p><strong>Nome:</strong> ${escHtml(c.nome)}</p>
        <p><strong>Nível:</strong> ${labelNivel[c.nivel] || c.nivel}</p>
        <p><strong>Status:</strong> ${badgeStatus(c.status)}</p>
        <p><strong>Descrição:</strong></p>
        <p>${escHtml(c.descricao)}</p>
        <p><strong>Aulas vinculadas:</strong></p>
        ${aulasHtml}
        ${c.review_comment ? `<p><strong>Último parecer:</strong> ${escHtml(c.review_comment)}</p>` : ''}
    `;

    const btnAprovar = document.getElementById('btnAprovarCurso');
    const formRejeitar = document.getElementById('formRejeitarCurso');
    const podeAnalisar = c.status === 'pendente';
    if (btnAprovar) btnAprovar.style.display = podeAnalisar ? 'inline-block' : 'none';
    if (formRejeitar) formRejeitar.style.display = podeAnalisar ? 'block' : 'none';

    card.style.display = 'block';
    card.scrollIntoView({ behavior: 'smooth' });
}

async function analisarOfertaAula(id) {
    const resp = await fetch('../php/admin_professor_lessons_buscar.php?id=' + id, { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const a = data.lesson;
    const card = document.getElementById('cardAnaliseAula');
    if (!card) return;

    let midiaHtml = '';
    if (a.video_link) {
        midiaHtml += `<p><strong>Vídeo:</strong> <a href="${escHtml(a.video_link)}" target="_blank" rel="noopener">${escHtml(a.video_link)}</a></p>`;
    }
    if (a.attachment_path) {
        midiaHtml += `<p><strong>Material:</strong> <a href="../${escHtml(a.attachment_path)}" target="_blank" rel="noopener">${escHtml(a.attachment_name || 'Download')}</a></p>`;
    }
    if (!midiaHtml) {
        midiaHtml = '<p><em>Sem vídeo ou anexo informado.</em></p>';
    }

    document.getElementById('analiseAulaId').value = a.id;
    document.getElementById('analiseAulaConteudo').innerHTML = `
        <p><strong>Professor:</strong> ${escHtml(a.professor_nome)} (${escHtml(a.professor_email)})</p>
        <p><strong>Título:</strong> ${escHtml(a.titulo)}</p>
        <p><strong>Nível:</strong> ${labelNivel[a.nivel] || a.nivel}</p>
        <p><strong>Status:</strong> ${badgeStatus(a.status)}</p>
        <p><strong>Descrição:</strong></p>
        <p>${escHtml(a.descricao)}</p>
        ${midiaHtml}
        ${a.review_comment ? `<p><strong>Último parecer:</strong> ${escHtml(a.review_comment)}</p>` : ''}
    `;

    const btnAprovar = document.getElementById('btnAprovarAula');
    const formRejeitar = document.getElementById('formRejeitarAula');
    const podeAnalisar = a.status === 'pendente';
    if (btnAprovar) btnAprovar.style.display = podeAnalisar ? 'inline-block' : 'none';
    if (formRejeitar) formRejeitar.style.display = podeAnalisar ? 'block' : 'none';

    card.style.display = 'block';
    card.scrollIntoView({ behavior: 'smooth' });
}

function fecharAnaliseCurso() {
    const card = document.getElementById('cardAnaliseCurso');
    if (card) card.style.display = 'none';
}

function fecharAnaliseAula() {
    const card = document.getElementById('cardAnaliseAula');
    if (card) card.style.display = 'none';
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
