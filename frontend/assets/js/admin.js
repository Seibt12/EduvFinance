document.addEventListener('DOMContentLoaded', () => {
  verificarPapel('admin', usuario => {
    document.getElementById('userName').textContent = usuario.nome;
    inicializarNavegacao();
    carregarDashboard();
  });
});

function inicializarNavegacao() {
  document.querySelectorAll('.nav-item[data-section]').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      exibirSecao(link.dataset.section);
    });
  });
}

function exibirSecao(nome) {
  document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const secao   = document.getElementById(`section-${nome}`);
  const navItem = document.querySelector(`.nav-item[data-section="${nome}"]`);
  if (secao)   secao.classList.add('active');
  if (navItem) navItem.classList.add('active');
  if (nome === 'dashboard') carregarDashboard();
  if (nome === 'students')  carregarAlunos();
  if (nome === 'lessons')   carregarAulas();
  if (nome === 'courses')   carregarCursos();
}

// ============================================================
// DASHBOARD
// ============================================================

async function carregarDashboard() {
  try {
    const { ok, data } = await apiFetch('/progress/stats.php');
    if (!ok || !data.success) return;
    document.getElementById('totalStudents').textContent = data.totalStudents;
    document.getElementById('totalLessons').textContent  = data.totalLessons;
    document.getElementById('avgCompletion').textContent = data.avgCompletion + '%';
    document.getElementById('totalCourses').textContent  = data.totalCourses ?? '-';
  } catch {
    exibirToast('Erro ao carregar dados.', 'error');
  }
}

// ============================================================
// ALUNOS
// ============================================================

async function carregarAlunos() {
  const tbody = document.getElementById('studentsTableBody');
  tbody.innerHTML = '<tr><td colspan="6" class="loading">Carregando...</td></tr>';
  try {
    const { ok, data } = await apiFetch('/users/list.php');
    if (!ok || !data.success) {
      tbody.innerHTML = '<tr><td colspan="6" class="empty">Erro ao carregar.</td></tr>';
      return;
    }
    if (data.users.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="empty">Nenhum aluno cadastrado.</td></tr>';
      return;
    }
    tbody.innerHTML = data.users.map(u => `
      <tr>
        <td>${u.id}</td>
        <td>${esc(u.nome)}</td>
        <td>${esc(u.email)}</td>
        <td>${formatarData(u.created_at)}</td>
        <td>${u.progresso}%</td>
        <td>
          <div class="actions">
            <button class="btn btn-warning btn-sm" onclick="abrirModalEditarAluno(${u.id})">Editar</button>
            <button class="btn btn-danger btn-sm" onclick="confirmarExclusaoAluno(${u.id}, '${esc(u.nome)}')">Excluir</button>
          </div>
        </td>
      </tr>
    `).join('');
  } catch {
    tbody.innerHTML = '<tr><td colspan="6" class="empty">Erro de conexao.</td></tr>';
  }
}

async function abrirModalEditarAluno(id) {
  try {
    const { ok, data } = await apiFetch(`/users/get.php?id=${id}`);
    if (!ok || !data.success) { exibirToast('Erro ao carregar dados.', 'error'); return; }
    document.getElementById('studentId').value    = data.user.id;
    document.getElementById('studentNome').value  = data.user.nome;
    document.getElementById('studentEmail').value = data.user.email;
    abrirModal('studentModal');
  } catch {
    exibirToast('Erro de conexao.', 'error');
  }
}

async function salvarAluno(e) {
  e.preventDefault();
  const id    = parseInt(document.getElementById('studentId').value);
  const nome  = document.getElementById('studentNome').value.trim();
  const email = document.getElementById('studentEmail').value.trim();
  const btn   = e.target.querySelector('[type=submit]');
  btn.disabled = true;
  btn.textContent = 'Salvando...';
  try {
    const { ok, data } = await apiFetch('/users/update.php', {
      method: 'POST',
      body: JSON.stringify({ id, nome, email }),
    });
    if (!ok || !data.success) { exibirToast(data.message, 'error'); return; }
    exibirToast('Aluno atualizado.', 'success');
    fecharModal('studentModal');
    carregarAlunos();
  } catch {
    exibirToast('Erro de conexao.', 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Salvar';
  }
}

function confirmarExclusaoAluno(id, nome) {
  document.getElementById('confirmMessage').textContent = `Excluir o aluno "${nome}"?`;
  document.getElementById('confirmBtn').onclick = () => excluirAluno(id);
  abrirModal('confirmModal');
}

async function excluirAluno(id) {
  try {
    const { ok, data } = await apiFetch('/users/delete.php', {
      method: 'POST',
      body: JSON.stringify({ id }),
    });
    if (!ok || !data.success) { exibirToast(data.message, 'error'); return; }
    exibirToast('Aluno excluido.', 'success');
    fecharModal('confirmModal');
    carregarAlunos();
    carregarDashboard();
  } catch {
    exibirToast('Erro de conexao.', 'error');
  }
}

// ============================================================
// AULAS
// ============================================================

async function carregarAulas() {
  const tbody = document.getElementById('lessonsTableBody');
  tbody.innerHTML = '<tr><td colspan="5" class="loading">Carregando...</td></tr>';
  try {
    const { ok, data } = await apiFetch('/lessons/list.php');
    if (!ok || !data.success) {
      tbody.innerHTML = '<tr><td colspan="5" class="empty">Erro ao carregar.</td></tr>';
      return;
    }
    if (data.lessons.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" class="empty">Nenhuma aula cadastrada.</td></tr>';
      return;
    }
    const rotuloNivel = { basico: 'Basico', intermediario: 'Intermediario', avancado: 'Avancado' };
    tbody.innerHTML = data.lessons.map(l => `
      <tr>
        <td>${l.id}</td>
        <td>${esc(l.titulo)}</td>
        <td><span class="badge badge-${l.nivel}">${rotuloNivel[l.nivel]}</span></td>
        <td style="color:#888">${esc(l.descricao.substring(0, 80))}${l.descricao.length > 80 ? '...' : ''}</td>
        <td>
          <div class="actions">
            <button class="btn btn-warning btn-sm" onclick="abrirModalEditarAula(${l.id})">Editar</button>
            <button class="btn btn-danger btn-sm" onclick="confirmarExclusaoAula(${l.id}, '${esc(l.titulo)}')">Excluir</button>
          </div>
        </td>
      </tr>
    `).join('');
  } catch {
    tbody.innerHTML = '<tr><td colspan="5" class="empty">Erro de conexao.</td></tr>';
  }
}

function abrirModalNovaAula() {
  document.getElementById('lessonModalTitle').textContent = 'Nova Aula';
  document.getElementById('lessonId').value        = '';
  document.getElementById('lessonTitulo').value    = '';
  document.getElementById('lessonDescricao').value = '';
  document.getElementById('lessonNivel').value     = 'basico';
  abrirModal('lessonModal');
}

async function abrirModalEditarAula(id) {
  try {
    const { ok, data } = await apiFetch(`/lessons/get.php?id=${id}`);
    if (!ok || !data.success) { exibirToast('Erro ao carregar aula.', 'error'); return; }
    document.getElementById('lessonModalTitle').textContent = 'Editar Aula';
    document.getElementById('lessonId').value        = data.lesson.id;
    document.getElementById('lessonTitulo').value    = data.lesson.titulo;
    document.getElementById('lessonDescricao').value = data.lesson.descricao;
    document.getElementById('lessonNivel').value     = data.lesson.nivel;
    abrirModal('lessonModal');
  } catch {
    exibirToast('Erro de conexao.', 'error');
  }
}

async function salvarAula(e) {
  e.preventDefault();
  const id        = document.getElementById('lessonId').value;
  const titulo    = document.getElementById('lessonTitulo').value.trim();
  const descricao = document.getElementById('lessonDescricao').value.trim();
  const nivel     = document.getElementById('lessonNivel').value;
  const btn       = e.target.querySelector('[type=submit]');
  const ehEdicao  = id !== '';

  btn.disabled = true;
  btn.textContent = 'Salvando...';

  const endpoint = ehEdicao ? '/lessons/update.php' : '/lessons/create.php';
  const corpo    = ehEdicao ? { id: parseInt(id), titulo, descricao, nivel } : { titulo, descricao, nivel };

  try {
    const { ok, data } = await apiFetch(endpoint, {
      method: 'POST',
      body: JSON.stringify(corpo),
    });
    if (!ok || !data.success) { exibirToast(data.message, 'error'); return; }
    exibirToast(ehEdicao ? 'Aula atualizada.' : 'Aula criada.', 'success');
    fecharModal('lessonModal');
    carregarAulas();
  } catch {
    exibirToast('Erro de conexao.', 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Salvar';
  }
}

function confirmarExclusaoAula(id, titulo) {
  document.getElementById('confirmMessage').textContent = `Excluir a aula "${titulo}"?`;
  document.getElementById('confirmBtn').onclick = () => excluirAula(id);
  abrirModal('confirmModal');
}

async function excluirAula(id) {
  try {
    const { ok, data } = await apiFetch('/lessons/delete.php', {
      method: 'POST',
      body: JSON.stringify({ id }),
    });
    if (!ok || !data.success) { exibirToast(data.message, 'error'); return; }
    exibirToast('Aula excluida.', 'success');
    fecharModal('confirmModal');
    carregarAulas();
  } catch {
    exibirToast('Erro de conexao.', 'error');
  }
}

// ============================================================
// CURSOS
// ============================================================

async function carregarCursos() {
  const tbody = document.getElementById('coursesTableBody');
  tbody.innerHTML = '<tr><td colspan="6" class="loading">Carregando...</td></tr>';
  try {
    const { ok, data } = await apiFetch('/courses/list.php');
    if (!ok || !data.success) {
      tbody.innerHTML = '<tr><td colspan="6" class="empty">Erro ao carregar.</td></tr>';
      return;
    }
    if (data.courses.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="empty">Nenhum curso cadastrado.</td></tr>';
      return;
    }
    const rotuloNivel = { basico: 'Basico', intermediario: 'Intermediario', avancado: 'Avancado' };
    tbody.innerHTML = data.courses.map(c => `
      <tr>
        <td>${c.id}</td>
        <td>${esc(c.nome)}</td>
        <td><span class="badge badge-${c.nivel}">${rotuloNivel[c.nivel]}</span></td>
        <td>${c.total_aulas}</td>
        <td>${c.total_matriculas}</td>
        <td>
          <div class="actions">
            <button class="btn btn-warning btn-sm" onclick="abrirModalEditarCurso(${c.id})">Editar</button>
            <button class="btn btn-danger btn-sm" onclick="confirmarExclusaoCurso(${c.id}, '${esc(c.nome)}')">Excluir</button>
          </div>
        </td>
      </tr>
    `).join('');
  } catch {
    tbody.innerHTML = '<tr><td colspan="6" class="empty">Erro de conexao.</td></tr>';
  }
}

async function abrirModalNovoCurso() {
  document.getElementById('courseModalTitle').textContent = 'Novo Curso';
  document.getElementById('courseId').value        = '';
  document.getElementById('courseNome').value      = '';
  document.getElementById('courseDescricao').value = '';
  document.getElementById('courseNivel').value     = 'basico';
  await carregarCheckboxesAulas([]);
  abrirModal('courseModal');
}

async function abrirModalEditarCurso(id) {
  try {
    const { ok, data } = await apiFetch(`/courses/get.php?id=${id}`);
    if (!ok || !data.success) { exibirToast('Erro ao carregar curso.', 'error'); return; }
    document.getElementById('courseModalTitle').textContent = 'Editar Curso';
    document.getElementById('courseId').value        = data.course.id;
    document.getElementById('courseNome').value      = data.course.nome;
    document.getElementById('courseDescricao').value = data.course.descricao;
    document.getElementById('courseNivel').value     = data.course.nivel;
    await carregarCheckboxesAulas(data.course.lesson_ids);
    abrirModal('courseModal');
  } catch {
    exibirToast('Erro de conexao.', 'error');
  }
}

async function carregarCheckboxesAulas(idsSelecionados) {
  const container = document.getElementById('courseLessonsCheckboxes');
  container.innerHTML = '<span style="color:#666;font-size:12px">Carregando aulas...</span>';
  try {
    const { ok, data } = await apiFetch('/lessons/list.php');
    if (!ok || !data.success || data.lessons.length === 0) {
      container.innerHTML = '<span style="color:#666;font-size:12px">Nenhuma aula cadastrada ainda.</span>';
      return;
    }
    const rotuloNivel = { basico: 'Basico', intermediario: 'Intermediario', avancado: 'Avancado' };
    container.innerHTML = data.lessons.map(l => `
      <label class="checkbox-item">
        <input type="checkbox" name="course_lesson" value="${l.id}" ${idsSelecionados.includes(l.id) ? 'checked' : ''}>
        <span class="badge badge-${l.nivel}" style="margin:0 6px 0 4px">${rotuloNivel[l.nivel]}</span>
        ${esc(l.titulo)}
      </label>
    `).join('');
  } catch {
    container.innerHTML = '<span style="color:#c0392b;font-size:12px">Erro ao carregar aulas.</span>';
  }
}

async function salvarCurso(e) {
  e.preventDefault();
  const id        = document.getElementById('courseId').value;
  const nome      = document.getElementById('courseNome').value.trim();
  const descricao = document.getElementById('courseDescricao').value.trim();
  const nivel     = document.getElementById('courseNivel').value;
  const ehEdicao  = id !== '';

  const marcados = document.querySelectorAll('input[name="course_lesson"]:checked');
  const idsAulas = Array.from(marcados).map(cb => parseInt(cb.value));

  const btn = e.target.querySelector('[type=submit]');
  btn.disabled = true;
  btn.textContent = 'Salvando...';

  const endpoint = ehEdicao ? '/courses/update.php' : '/courses/create.php';
  const corpo    = ehEdicao
    ? { id: parseInt(id), nome, descricao, nivel, lesson_ids: idsAulas }
    : { nome, descricao, nivel, lesson_ids: idsAulas };

  try {
    const { ok, data } = await apiFetch(endpoint, {
      method: 'POST',
      body: JSON.stringify(corpo),
    });
    if (!ok || !data.success) { exibirToast(data.message, 'error'); return; }
    exibirToast(ehEdicao ? 'Curso atualizado.' : 'Curso criado.', 'success');
    fecharModal('courseModal');
    carregarCursos();
    carregarDashboard();
  } catch {
    exibirToast('Erro de conexao.', 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Salvar';
  }
}

function confirmarExclusaoCurso(id, nome) {
  document.getElementById('confirmMessage').textContent = `Excluir o curso "${nome}"? Isso tambem remove todas as matriculas.`;
  document.getElementById('confirmBtn').onclick = () => excluirCurso(id);
  abrirModal('confirmModal');
}

async function excluirCurso(id) {
  try {
    const { ok, data } = await apiFetch('/courses/delete.php', {
      method: 'POST',
      body: JSON.stringify({ id }),
    });
    if (!ok || !data.success) { exibirToast(data.message, 'error'); return; }
    exibirToast('Curso excluido.', 'success');
    fecharModal('confirmModal');
    carregarCursos();
    carregarDashboard();
  } catch {
    exibirToast('Erro de conexao.', 'error');
  }
}

// ============================================================
// UTILITÁRIOS
// ============================================================

function esc(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
