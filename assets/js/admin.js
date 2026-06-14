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
        const total = parseInt(a.total_matriculadas) || 0;
        const pct   = total > 0 ? Math.round((parseInt(a.concluidas) / total) * 100) : 0;
        tbody.innerHTML += `<tr>
            <td>${escHtml(a.nome)}</td>
            <td>${parseInt(a.concluidas)} / ${total}</td>
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
        const concluidas = parseInt(u.total_concluidas) || 0;
        const matriculadas = parseInt(u.total_matriculadas) || 0;
        const pct = matriculadas > 0 ? Math.round((concluidas / matriculadas) * 100) : 0;
        const dataFormatada = formatarData(u.created_at);
        tbody.innerHTML += `<tr>
            <td>${escHtml(u.nome)}</td>
            <td>${escHtml(u.email)}</td>
            <td>
                <div class="progress-wrap">
                    <div class="progress-bar"><div class="progress-fill" style="width:${pct}%"></div></div>
                    <span class="progress-label">${concluidas}/${matriculadas} (${pct}%)</span>
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
    const resp = await fetch('../php/users_buscar.php?id=' + id + '&tipo=aluno', { credentials: 'include' });
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
