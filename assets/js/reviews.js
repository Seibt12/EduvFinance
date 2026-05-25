// ── Course Reviews — student-side logic ─────────────────────

(function () {
    'use strict';

    // ── State ───────────────────────────────────────────────
    let activeCourseId = null;
    let selectedRating = 0;

    // ── DOM refs (populated lazily) ─────────────────────────
    function dom(id) { return document.getElementById(id); }

    // ── Helpers ─────────────────────────────────────────────
    function starsHtml(rating, max) {
        max = max || 5;
        const filled = Math.round(parseFloat(rating) || 0);
        let out = '';
        for (let i = 1; i <= max; i++) out += i <= filled ? '★' : '☆';
        return out;
    }

    function fmtDate(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    // ── Render average in course-grid cards ─────────────────
    async function injectMediasNaGrade() {
        try {
            const resp = await fetch('../php/review_listar.php?medias=1', { credentials: 'include' });
            const data = await resp.json();
            if (!data.success) return;

            // Find all course cards injected by aluno.js (they carry data-curso-id)
            document.querySelectorAll('[data-curso-id]').forEach(card => {
                const id    = parseInt(card.dataset.cursoId);
                const info  = data.medias[id];
                const el    = card.querySelector('.course-rating-placeholder');
                if (!el) return;
                if (info && info.total > 0) {
                    el.innerHTML =
                        `<span class="star-display">
                            <span class="stars">${starsHtml(info.media)}</span>
                            <span class="media-val">${info.media.toFixed(1)}</span>
                            <span class="total-val">(${info.total} avaliação${info.total !== 1 ? 'ões' : ''})</span>
                        </span>`;
                } else {
                    el.innerHTML = '<span style="font-size:var(--text-xs);color:var(--text-muted)">Sem avaliações ainda</span>';
                }
            });
        } catch (_) { /* silent */ }
    }

    // ── Load reviews for a single course (lessons view) ─────
    async function carregarReviewsDoCurso(cursoId) {
        activeCourseId = cursoId;
        const actionArea = dom('reviewActionArea');
        const listEl     = dom('reviewsList');
        const headerEl   = dom('cursoRatingHeader');

        if (!actionArea || !listEl) return;

        actionArea.innerHTML = '<span style="color:var(--text-muted);font-size:var(--text-sm)">Carregando avaliações...</span>';
        listEl.innerHTML     = '';

        try {
            const resp = await fetch(`../php/review_listar.php?curso_id=${cursoId}`, { credentials: 'include' });
            const data = await resp.json();
            if (!data.success) { actionArea.innerHTML = ''; return; }

            // Update header rating display
            if (headerEl) {
                if (data.total > 0) {
                    headerEl.innerHTML =
                        `<span class="star-display">
                            <span class="stars">${starsHtml(data.media)}</span>
                            <span class="media-val">${parseFloat(data.media).toFixed(1)}</span>
                            <span class="total-val">(${data.total} avaliação${data.total !== 1 ? 'ões' : ''})</span>
                        </span>`;
                } else {
                    headerEl.innerHTML = '<span style="font-size:var(--text-xs);color:var(--text-muted)">Sem avaliações ainda</span>';
                }
            }

            // Action area: show review button or "already reviewed" state
            if (data.minha_review) {
                actionArea.innerHTML =
                    `<div style="margin-bottom:16px;padding:12px 16px;background:var(--success-subtle);border:1px solid var(--success-border);border-radius:var(--radius-md);font-size:var(--text-sm);color:var(--success)">
                        <strong>Você avaliou este curso com ${data.minha_review.rating} estrela${data.minha_review.rating !== 1 ? 's' : ''}.</strong>
                        ${data.minha_review.comment ? `<p style="margin-top:4px;color:var(--text-secondary)">"${escHtml(data.minha_review.comment)}"</p>` : ''}
                    </div>`;
            } else {
                // Check completion to decide whether to show the review button
                // The button is only shown when course is 100% — checked by the backend.
                // We show it optimistically; backend will reject if incomplete.
                actionArea.innerHTML =
                    `<button class="btn-action btn-action-review" id="btnAbrirReview" style="margin-bottom:16px">
                        <i data-lucide="star"></i>
                        Avaliar este curso
                    </button>
                    <div id="reviewCompletionHint" style="display:none;font-size:var(--text-xs);color:var(--text-muted);margin-bottom:16px">
                        Conclua todas as aulas para avaliar este curso.
                    </div>`;
                lucide.createIcons();

                dom('btnAbrirReview').addEventListener('click', () => abrirModalReview(cursoId));
            }

            // Reviews list
            if (data.reviews.length === 0) {
                listEl.innerHTML = '<p class="review-empty">Este curso ainda não possui avaliações. Seja o primeiro!</p>';
            } else {
                listEl.innerHTML = data.reviews.map(r => `
                    <div class="review-item">
                        <div class="review-item-header">
                            <span class="review-item-name">${escHtml(r.student_nome)}</span>
                            <span class="review-item-meta">
                                <span class="review-item-stars">${starsHtml(r.rating)}</span>
                                <span>${fmtDate(r.created_at)}</span>
                            </span>
                        </div>
                        ${r.comment ? `<p class="review-item-comment">${escHtml(r.comment)}</p>` : ''}
                    </div>`).join('');
            }
        } catch (_) {
            actionArea.innerHTML = '<span style="color:var(--danger);font-size:var(--text-sm)">Erro ao carregar avaliações.</span>';
        }
    }

    // ── Modal: open / close ──────────────────────────────────
    function abrirModalReview(cursoId) {
        activeCourseId = cursoId;
        selectedRating = 0;
        dom('reviewComment').value = '';
        dom('ratingError').style.display = 'none';
        dom('reviewModalSubmitBtn').disabled = false;
        dom('reviewModalSubmitBtn').innerHTML = '<i data-lucide="send"></i> Enviar avaliação';
        resetarEstrelas();
        dom('reviewModal').classList.add('open');
        lucide.createIcons();
    }

    function fecharModalReview() {
        dom('reviewModal').classList.remove('open');
    }

    // ── Star selector logic ──────────────────────────────────
    function resetarEstrelas() {
        document.querySelectorAll('#starSelector button').forEach(btn => {
            btn.classList.remove('active', 'hovered');
        });
        selectedRating = 0;
    }

    function destacarEstrelas(upTo) {
        document.querySelectorAll('#starSelector button').forEach(btn => {
            const val = parseInt(btn.dataset.value);
            btn.classList.toggle('hovered', val <= upTo);
        });
    }

    function selecionarEstrela(val) {
        selectedRating = val;
        document.querySelectorAll('#starSelector button').forEach(btn => {
            const v = parseInt(btn.dataset.value);
            btn.classList.toggle('active', v <= val);
            btn.classList.remove('hovered');
        });
        dom('ratingError').style.display = 'none';
    }

    // ── Submit review ────────────────────────────────────────
    async function enviarReview() {
        if (selectedRating < 1) {
            dom('ratingError').style.display = 'block';
            return;
        }

        const comment = dom('reviewComment').value.trim();
        const btn     = dom('reviewModalSubmitBtn');
        btn.disabled  = true;
        btn.innerHTML = '<i data-lucide="loader-2" style="animation:spin 1s linear infinite"></i> Enviando...';
        lucide.createIcons();

        try {
            const resp = await fetch('../php/review_inserir.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    course_id: activeCourseId,
                    rating: selectedRating,
                    comment,
                }),
            });
            const data = await resp.json();

            if (data.success) {
                fecharModalReview();
                exibirToast(data.message, 'success');
                // Reload reviews section to show the new review
                await carregarReviewsDoCurso(activeCourseId);
                lucide.createIcons();
            } else {
                btn.disabled  = false;
                btn.innerHTML = 'Enviar avaliação';
                exibirToast(data.message || 'Erro ao enviar avaliação.', 'error');
            }
        } catch (_) {
            btn.disabled  = false;
            btn.innerHTML = 'Enviar avaliação';
            exibirToast('Erro de conexão. Tente novamente.', 'error');
        }
    }

    // ── Wire up events after DOM ready ───────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        // Close modal
        dom('reviewModalClose')?.addEventListener('click', fecharModalReview);
        dom('reviewModalCancelBtn')?.addEventListener('click', fecharModalReview);
        dom('reviewModal')?.addEventListener('click', e => {
            if (e.target === dom('reviewModal')) fecharModalReview();
        });

        // Escape key
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') fecharModalReview();
        });

        // Submit
        dom('reviewModalSubmitBtn')?.addEventListener('click', enviarReview);

        // Star interactions
        document.querySelectorAll('#starSelector button').forEach(btn => {
            btn.addEventListener('mouseenter', () => destacarEstrelas(parseInt(btn.dataset.value)));
            btn.addEventListener('mouseleave', () => {
                document.querySelectorAll('#starSelector button').forEach(b => b.classList.remove('hovered'));
                // Restore active state
                if (selectedRating > 0) selecionarEstrela(selectedRating);
            });
            btn.addEventListener('click', () => selecionarEstrela(parseInt(btn.dataset.value)));
        });
    });

    // ── Expose functions used by aluno.js ───────────────────
    window.carregarReviewsDoCurso = carregarReviewsDoCurso;
    window.injectMediasNaGrade    = injectMediasNaGrade;
    window.starsHtml              = starsHtml;
})();
