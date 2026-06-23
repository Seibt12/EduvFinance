// professor.js — Teacher dashboard logic

const labelNivelProf = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };
const labelStatusProf = {
    draft:     'Rascunho',
    pendente:  'Em Análise',
    aprovado:  'Publicado',
    rejeitado: 'Rejeitado',
};

async function carregarDashboardProfessor() {
    try {
        const resp = await fetch('../php/professor_courses_listar.php', { credentials: 'include' });
        const data = await resp.json();
        if (!data.success) { console.error('Falha ao carregar cursos'); return; }
        renderizarDashboard(data.courses);
    } catch (err) {
        console.error('Erro ao carregar dashboard:', err);
        exibirToast('Erro ao carregar seus cursos. Verifique a conexão.', 'error');
    }
}

function renderizarDashboard(courses) {
    const groups = { draft: [], pendente: [], aprovado: [], rejeitado: [] };
    for (const c of courses) {
        const s = c.status || 'draft';
        if (groups[s]) groups[s].push(c);
    }

    // Stats
    document.getElementById('statDraft').textContent    = groups.draft.length;
    document.getElementById('statPendente').textContent = groups.pendente.length;
    document.getElementById('statAprovado').textContent = groups.aprovado.length;
    document.getElementById('statRejeitado').textContent= groups.rejeitado.length;

    // Sections
    renderizarSecao('Rejeitado', groups.rejeitado, 'gridRejeitado', 'sectionRejeitado', 'countRejeitado');
    renderizarSecao('Draft',     groups.draft,     'gridDraft',     'sectionDraft',     'countDraft');
    renderizarSecao('Pendente',  groups.pendente,  'gridPendente',  'sectionPendente',  'countPendente');
    renderizarSecao('Aprovado',  groups.aprovado,  'gridAprovado',  'sectionAprovado',  'countAprovado');

    // Show/hide "empty all" notice
    const total = courses.length;
    document.getElementById('emptyAll').style.display = total === 0 ? 'block' : 'none';
}

function renderizarSecao(statusLabel, courses, gridId, sectionId, countId) {
    const section = document.getElementById(sectionId);
    const grid    = document.getElementById(gridId);
    const counter = document.getElementById(countId);

    if (!section || !grid) return;

    if (courses.length === 0) { section.style.display = 'none'; return; }

    section.style.display = 'block';
    if (counter) counter.textContent = courses.length;

    grid.innerHTML = '';
    for (const c of courses) {
        grid.innerHTML += construirCardCurso(c);
    }
}

function construirCardCurso(c) {
    const status = c.status || 'draft';
    const nivel  = c.nivel  || '';
    const badgeStatus = `<span class="badge badge-${status}">${labelStatusProf[status] || status}</span>`;
    const badgeNivel  = `<span class="badge badge-${nivel}">${labelNivelProf[nivel] || nivel}</span>`;

    const thumbHtml = c.thumbnail_path
        ? `<img src="../${escHtml(c.thumbnail_path)}" alt="thumbnail">`
        : `<div class="course-card-thumb-placeholder"><i data-lucide="book-open"></i></div>`;

    const rejectionHtml = (status === 'rejeitado' && c.review_comment)
        ? `<div class="rejection-notice"><strong>Feedback do Admin:</strong>${escHtml(c.review_comment)}</div>`
        : '';

    const canEdit   = status === 'draft' || status === 'rejeitado';
    const canDelete = status === 'draft' || status === 'rejeitado' || status === 'aprovado';

    let editBtn = '';
    if (canEdit) {
        // draft / rejeitado → builder (wizard completo)
        editBtn = `<a href="professor_builder.html?id=${c.id}" class="btn-sm btn-sm-primary"><i data-lucide="edit-2"></i> Editar</a>`;
    } else {
        // pendente / aprovado → página de visualização/edição inline
        editBtn = `<a href="professor_curso.html?id=${c.id}" class="btn-sm btn-sm-success"><i data-lucide="eye"></i> Ver</a>`;
    }

    const deleteBtn = canDelete
        ? `<button class="btn-sm btn-sm-danger" onclick="excluirCurso(${c.id}, '${escJs(c.nome)}', '${status}')"><i data-lucide="trash-2"></i></button>`
        : '';

    const totalAulas = c.total_lessons || 0;

    return `
    <div class="course-card">
      <div class="course-card-thumb">
        ${thumbHtml}
        <div class="status-ribbon">${badgeStatus}</div>
      </div>
      <div class="course-card-body">
        <div class="course-card-title">${escHtml(c.nome)}</div>
        <div class="course-card-meta">
          ${badgeNivel}
          ${c.categoria ? `<span style="font-size:var(--text-xs);color:var(--text-muted)">${escHtml(c.categoria)}</span>` : ''}
        </div>
        ${c.descricao ? `<div class="course-card-desc">${escHtml(c.descricao)}</div>` : ''}
      </div>
      ${rejectionHtml}
      <div class="course-card-footer">
        <div class="course-card-lessons">
          <i data-lucide="video" style="width:12px;height:12px"></i>
          ${totalAulas} aula${totalAulas !== 1 ? 's' : ''}
        </div>
        <div class="course-card-actions">
          ${editBtn}
          ${deleteBtn}
        </div>
      </div>
    </div>`;
}

async function excluirCurso(id, nome, status) {
    const msg = status === 'aprovado'
        ? `Excluir o curso publicado "${nome}"?\n\nO curso e todas as suas aulas serão removidos para os alunos, junto com as matrículas e o progresso deles neste curso. Esta ação não pode ser desfeita.`
        : `Excluir o curso "${nome}"? Todas as aulas serão removidas. Esta ação não pode ser desfeita.`;
    if (!confirm(msg)) return;
    try {
        const fd = new FormData();
        fd.append('id', id);
        const resp = await fetch('../php/professor_course_excluir.php', { method: 'POST', credentials: 'include', body: fd });
        const data = await resp.json();
        if (!data.success) { exibirToast(data.error || 'Erro ao excluir curso', 'error'); return; }
        exibirToast('Curso excluído.', 'success');
        carregarDashboardProfessor().then(() => lucide.createIcons());
    } catch (e) {
        exibirToast('Erro de conexão', 'error');
    }
}
