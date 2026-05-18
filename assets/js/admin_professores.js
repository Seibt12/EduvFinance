async function carregarProfessores() {
    const resp = await fetch('../php/users_listar.php?tipo=professor', { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const el = document.getElementById('contadorProfessores');
    if (el) el.textContent = data.users.length;

    const tbody = document.getElementById('professoresBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (data.users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="empty-state">Nenhum professor cadastrado.</td></tr>';
        return;
    }

    for (const u of data.users) {
        tbody.innerHTML += `<tr>
            <td>${escHtml(u.nome)}</td>
            <td>${escHtml(u.email)}</td>
            <td>${formatarData(u.created_at)}</td>
            <td class="actions">
                <button class="btn btn-sm btn-secondary" onclick="mostrarFormEdicaoProfessor(${u.id})">Editar</button>
                <form method="POST" action="../php/users_excluir.php" style="display:inline"
                      onsubmit="return confirm('Excluir professor ${escJs(u.nome)}?')">
                    <input type="hidden" name="id" value="${u.id}">
                    <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                </form>
            </td>
        </tr>`;
    }
}

async function mostrarFormEdicaoProfessor(id) {
    const resp = await fetch('../php/users_buscar.php?tipo=professor&id=' + id, { credentials: 'include' });
    const data = await resp.json();
    if (!data.success) return;

    const u = data.user;
    document.getElementById('editProfessorId').value    = u.id;
    document.getElementById('editProfessorNome').value  = u.nome;
    document.getElementById('editProfessorEmail').value = u.email;
    document.getElementById('editProfessorSenha').value = '';
    document.getElementById('formEdicaoProfessor').style.display = 'block';
    document.getElementById('formEdicaoProfessor').scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicaoProfessor() {
    document.getElementById('formEdicaoProfessor').style.display = 'none';
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJs(str) {
    return String(str).replace(/\\/g,'\\\\').replace(/'/g,"\\'");
}
