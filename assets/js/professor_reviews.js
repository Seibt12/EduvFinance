// ── Professor Reviews Analytics ─────────────────────────────

async function carregarReviewsProf() {
    const statsEl  = document.getElementById('reviewsProfStats');
    const cursosEl = document.getElementById('reviewsProfCursosBody');
    const recentEl = document.getElementById('reviewsProfRecentes');
    if (!statsEl || !cursosEl || !recentEl) return;

    try {
        const resp = await fetch('../php/professor_reviews_listar.php', { credentials: 'include' });
        const data = await resp.json();
        if (!data.success) return;

        const labelNivel = { basico: 'Básico', intermediario: 'Intermediário', avancado: 'Avançado' };

        function starsStr(rating, max) {
            max = max || 5;
            const n = Math.round(parseFloat(rating) || 0);
            let s = '';
            for (let i = 1; i <= max; i++) s += i <= n ? '★' : '☆';
            return s;
        }

        function fmtDate(iso) {
            if (!iso) return '';
            return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        // ── Global stats ─────────────────────────────────────
        const mediaFormatada = data.media_geral !== null
            ? parseFloat(data.media_geral).toFixed(1)
            : '—';

        statsEl.innerHTML = `
            <div class="review-stat-mini">
                <div class="rsm-value" style="color:#f59e0b">${mediaFormatada}</div>
                <div class="rsm-label">Nota média geral</div>
            </div>
            <div class="review-stat-mini">
                <div class="rsm-value">${data.total_reviews}</div>
                <div class="rsm-label">Total de avaliações</div>
            </div>
            <div class="review-stat-mini">
                <div class="rsm-value">${data.cursos.length}</div>
                <div class="rsm-label">Cursos publicados</div>
            </div>`;

        // ── Per-course table ──────────────────────────────────
        if (data.cursos.length === 0) {
            cursosEl.innerHTML = `<tr><td colspan="4" style="padding:32px;text-align:center;color:var(--text-muted)">
                Nenhum curso aprovado ainda.</td></tr>`;
        } else {
            cursosEl.innerHTML = data.cursos.map(c => {
                const media = c.media !== null
                    ? `<span class="stars-cell">${starsStr(c.media)}</span> <strong>${parseFloat(c.media).toFixed(1)}</strong>`
                    : '<span style="color:var(--text-muted)">Sem avaliações</span>';
                return `<tr>
                    <td><strong>${escHtml(c.course_nome)}</strong></td>
                    <td><span class="badge badge-${c.nivel}">${labelNivel[c.nivel] || c.nivel}</span></td>
                    <td>${media}</td>
                    <td>${c.total_reviews}</td>
                </tr>`;
            }).join('');
        }

        // ── Recent reviews ────────────────────────────────────
        if (data.recentes.length === 0) {
            recentEl.innerHTML = `<p style="color:var(--text-muted);font-size:var(--text-sm);font-style:italic;padding:12px 0">
                Ainda não há avaliações nos seus cursos.</p>`;
        } else {
            recentEl.innerHTML = data.recentes.map(r => `
                <div class="prof-review-item">
                    <div class="prof-review-header">
                        <span class="prof-review-name">${escHtml(r.student_nome)}</span>
                        <span class="prof-review-meta">
                            <span class="prof-review-stars">${starsStr(r.rating)}</span>
                            <span class="prof-review-course">${escHtml(r.course_nome)}</span>
                            <span>${fmtDate(r.created_at)}</span>
                        </span>
                    </div>
                    ${r.comment ? `<p class="prof-review-comment">${escHtml(r.comment)}</p>` : ''}
                </div>`).join('');
        }

    } catch (_) {
        const cursosEl2 = document.getElementById('reviewsProfCursosBody');
        if (cursosEl2) {
            cursosEl2.innerHTML = `<tr><td colspan="4" style="padding:24px;text-align:center;color:var(--danger)">
                Erro ao carregar avaliações.</td></tr>`;
        }
    }
}
