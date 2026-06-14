// admin_aprovacoes.js — Admin review workflow

const labelNivel = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };
const labelStatus = {
    draft:    'Rascunho',
    pendente: 'Em Análise',
    aprovado: 'Aprovado',
    rejeitado:'Rejeitado',
};

// escHtml is defined in utils.js

function badgeStatus(status) {
    const cls = status === 'aprovado' ? 'badge-aprovado'
              : status === 'rejeitado' ? 'badge-rejeitado'
              : status === 'draft'     ? 'badge-draft'
              : 'badge-pendente';
    return `<span class="badge ${cls}">${labelStatus[status] || status}</span>`;
}

// ─── Load course list ─────────────────────────────────────────────────────
async function carregarOfertasCursosAdmin() {
    const filtro = document.getElementById('filtroStatusCursos');
    const status = filtro ? filtro.value : 'pendente';
    try {
        const resp = await fetch('../php/admin_professor_courses_listar.php?status=' + encodeURIComponent(status), { credentials: 'include' });
        const data = await resp.json();
        if (!data.success) return;

        const contador = document.getElementById('contadorOfertasCursos');
        if (contador) contador.textContent = data.courses.length;

        const tbody = document.getElementById('ofertasCursosBody');
        if (!tbody) return;

        if (data.courses.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--text-muted)">Nenhum curso encontrado para este filtro.</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        for (const c of data.courses) {
            tbody.innerHTML += `<tr>
                <td>
                  <div style="font-weight:var(--weight-medium);color:var(--text-primary)">${escHtml(c.nome)}</div>
                  ${c.subtitulo ? `<div style="font-size:var(--text-xs);color:var(--text-muted);margin-top:2px">${escHtml(c.subtitulo)}</div>` : ''}
                </td>
                <td>${escHtml(c.professor_nome)}</td>
                <td><span class="badge badge-${c.nivel}">${labelNivel[c.nivel] || c.nivel}</span></td>
                <td>${c.total_aulas || 0}</td>
                <td>${badgeStatus(c.status)}</td>
                <td>${formatarData(c.created_at)}</td>
                <td>
                  <button class="btn-view" onclick="analisarOfertaCurso(${c.id})"><i data-lucide="search"></i> Analisar</button>
                </td>
            </tr>`;
        }
        lucide.createIcons();
    } catch (e) {
        console.error('Erro ao carregar cursos:', e);
    }
}

// ─── Load course detail ───────────────────────────────────────────────────
async function analisarOfertaCurso(id) {
    try {
        const resp = await fetch('../php/admin_professor_courses_buscar.php?id=' + id, { credentials: 'include' });
        const data = await resp.json();
        if (!data.success) { exibirToast('Curso não encontrado', 'error'); return; }
        preencherReviewPanel(data.course);
    } catch (e) {
        exibirToast('Erro ao carregar detalhes do curso', 'error');
    }
}

function preencherReviewPanel(c) {
    const panel = document.getElementById('reviewPanel');
    if (!panel) return;

    // Professor
    document.getElementById('rpProfNome').textContent  = c.professor_nome  || '—';
    document.getElementById('rpProfEmail').textContent = c.professor_email || '—';
    const inicial = c.professor_nome ? c.professor_nome.charAt(0).toUpperCase() : 'P';
    document.getElementById('rpProfAvatar').textContent = inicial;
    const statusBadge = document.getElementById('rpStatusBadge');
    statusBadge.className = 'badge badge-' + c.status;
    statusBadge.textContent = labelStatus[c.status] || c.status;

    // Course info
    document.getElementById('rpNome').textContent     = c.nome || '—';
    document.getElementById('rpSubtitulo').textContent= c.subtitulo || '—';
    document.getElementById('rpNivelCat').innerHTML   = `<span class="badge badge-${c.nivel}">${labelNivel[c.nivel] || c.nivel}</span>${c.categoria ? ` &nbsp; <span style="color:var(--text-muted)">${escHtml(c.categoria)}</span>` : ''}`;
    document.getElementById('rpDescricao').textContent= c.descricao || '—';

    // Thumbnail
    const thumbEl = document.getElementById('rpThumb');
    if (c.thumbnail_path) {
        thumbEl.innerHTML = `<img src="../${escHtml(c.thumbnail_path)}" alt="thumbnail">`;
    } else {
        thumbEl.innerHTML = `<div class="course-thumb-placeholder"><i data-lucide="image"></i></div>`;
    }

    // Lessons
    const lessons = c.lessons || [];
    document.getElementById('rpLessonsCount').textContent = lessons.length;
    const lessonList = document.getElementById('rpLessonsList');
    if (lessons.length === 0) {
        lessonList.innerHTML = '<p style="font-size:var(--text-sm);color:var(--text-muted);font-style:italic">Nenhuma aula vinculada a este curso.</p>';
    } else {
        lessonList.innerHTML = lessons.map((l, i) => `
          <div class="lesson-review-item">
            <div class="lesson-review-num">${i + 1}</div>
            <div class="lesson-review-info">
              <div class="lesson-review-title">${escHtml(l.titulo)}</div>
              <div class="lesson-review-meta">
                ${l.video_link   ? `<a href="${escHtml(l.video_link)}" target="_blank" rel="noopener" style="color:var(--primary)">🎬 Ver vídeo</a>` : ''}
                ${l.attachment_path ? `<a href="../${escHtml(l.attachment_path)}" target="_blank" rel="noopener" style="color:var(--primary)">📎 ${escHtml(l.attachment_name || 'Material')}</a>` : ''}
                ${l.duracao ? `<span>⏱ ${l.duracao} min</span>` : ''}
                ${l.descricao ? `<span style="font-style:italic">${escHtml(l.descricao.substring(0,80))}${l.descricao.length > 80 ? '…' : ''}</span>` : ''}
              </div>
            </div>
          </div>
        `).join('');
    }

    // Prior feedback
    const feedbackBox  = document.getElementById('rpPriorFeedback');
    const feedbackText = document.getElementById('rpPriorFeedbackText');
    if (c.review_comment) {
        feedbackBox.style.display = 'block';
        feedbackText.textContent  = c.review_comment;
    } else {
        feedbackBox.style.display = 'none';
    }

    // Actions
    const actionsEl = document.getElementById('rpActions');
    if (actionsEl) actionsEl.style.display = c.status === 'pendente' ? 'block' : 'none';
    document.getElementById('rpCourseId').value = c.id;

    // Reset reject form
    const rejectWrap = document.getElementById('rejectWrap');
    if (rejectWrap) rejectWrap.style.display = 'none';
    const rejectComment = document.getElementById('rejectComment');
    if (rejectComment) rejectComment.value = '';

    panel.style.display = 'block';
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    lucide.createIcons();
}

function fecharReview() {
    const panel = document.getElementById('reviewPanel');
    if (panel) panel.style.display = 'none';
}

function toggleRejeitar() {
    const el = document.getElementById('rejectWrap');
    if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

// ─── Approve ──────────────────────────────────────────────────────────────
async function aprovarCurso() {
    const id = document.getElementById('rpCourseId').value;
    if (!id) return;
    if (!confirm('Aprovar e publicar este curso? Ele ficará visível para todos os alunos.')) return;

    const btn = document.getElementById('btnAprovar');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader"></i> Aprovando...';
    lucide.createIcons();

    try {
        const fd = new FormData();
        fd.append('id', id);
        const resp = await fetch('../php/admin_professor_courses_aprovar.php', { method: 'POST', credentials: 'include', body: fd });
        const data = await resp.json();
        if (!data.success) { exibirToast(data.error || 'Erro ao aprovar', 'error'); return; }
        exibirToast(data.message || 'Curso aprovado e publicado!', 'success');
        fecharReview();
        carregarOfertasCursosAdmin().then(() => lucide.createIcons());
    } catch (e) {
        exibirToast('Erro de conexão', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check"></i> Aprovar e Publicar';
        lucide.createIcons();
    }
}

// ─── Reject ───────────────────────────────────────────────────────────────
async function rejeitarCurso() {
    const id      = document.getElementById('rpCourseId').value;
    const comment = document.getElementById('rejectComment').value.trim();
    if (!id) return;
    if (!comment) { exibirToast('Informe o motivo da rejeição.', 'error'); return; }

    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('review_comment', comment);
        const resp = await fetch('../php/admin_professor_courses_rejeitar.php', { method: 'POST', credentials: 'include', body: fd });
        const data = await resp.json();
        if (!data.success) { exibirToast(data.error || 'Erro ao rejeitar', 'error'); return; }
        exibirToast(data.message || 'Curso rejeitado. O professor foi notificado.', 'success');
        fecharReview();
        carregarOfertasCursosAdmin().then(() => lucide.createIcons());
    } catch (e) {
        exibirToast('Erro de conexão', 'error');
    }
}
