let currentUser = null;

document.addEventListener('DOMContentLoaded', () => {
  requireRole('aluno', user => {
    currentUser = user;
    document.getElementById('userName').textContent = user.nome;
    initNavigation();
    loadDashboard();
  });
});

function initNavigation() {
  document.querySelectorAll('.nav-item[data-section]').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      showSection(link.dataset.section);
    });
  });
}

function showSection(name) {
  document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const section = document.getElementById(`section-${name}`);
  const navItem = document.querySelector(`.nav-item[data-section="${name}"]`);
  if (section) section.classList.add('active');
  if (navItem) navItem.classList.add('active');
  if (name === 'dashboard') loadDashboard();
  if (name === 'lessons')   loadLessons();
  if (name === 'courses')   loadCourses();
  if (name === 'investor')  loadInvestorSection();
  if (name === 'simulator') loadSimulator();
  if (name === 'profile')   loadProfile();
}

// ============================================================
// DASHBOARD
// ============================================================

async function loadDashboard() {
  try {
    const { ok, data } = await apiFetch('/progress/list.php');
    if (!ok || !data.success) return;
    const r = data.resumo;

    document.getElementById('dashPercent').textContent    = r.percentual + '%';
    document.getElementById('dashConcluidas').textContent = r.concluidas;
    document.getElementById('dashTotal').textContent      = r.total;

    const bar = document.getElementById('dashProgressBar');
    if (bar) bar.style.width = r.percentual + '%';

    const msg = document.getElementById('motivMessage');
    if (msg) {
      if (r.percentual === 100)    msg.textContent = 'Parabens! Voce concluiu todas as aulas!';
      else if (r.percentual >= 66) msg.textContent = 'Voce esta quase la, continue!';
      else if (r.percentual >= 33) msg.textContent = 'Bom progresso, nao pare agora!';
      else                         msg.textContent = 'Comece pelas aulas basicas e evolua!';
    }

    const nextLesson = data.lessons.find(l => !l.concluido && !l.bloqueado);
    const nextEl = document.getElementById('nextLesson');
    if (nextEl) {
      if (nextLesson) {
        nextEl.innerHTML = `
          <span class="badge badge-${nextLesson.nivel}">${nivelLabel(nextLesson.nivel)}</span>
          <strong style="display:block;margin:6px 0">${esc(nextLesson.titulo)}</strong>
          <button class="btn btn-primary btn-sm" onclick="showSection('lessons')">Ver aulas</button>
        `;
      } else if (r.percentual === 100) {
        nextEl.innerHTML = '<span style="color:#27ae60">Todas as aulas concluidas!</span>';
      } else {
        nextEl.innerHTML = '<span style="color:#666">Complete as aulas basicas para desbloquear os proximos niveis.</span>';
      }
    }

    // Mostra perfil de investidor no card do dashboard
    loadInvestorCardDashboard();
  } catch {
    showToast('Erro ao carregar dashboard.', 'error');
  }
}

async function loadInvestorCardDashboard() {
  const el = document.getElementById('dashInvestorPerfil');
  if (!el) return;
  try {
    const { ok, data } = await apiFetch('/investor/get.php');
    if (ok && data.success && data.profile) {
      const labels = { conservador: 'Conservador', moderado: 'Moderado', agressivo: 'Agressivo' };
      const colors = { conservador: '#6fcf6f', moderado: '#e0b84a', agressivo: '#e06f6f' };
      const p = data.profile.perfil;
      el.textContent = labels[p] || p;
      el.style.color = colors[p] || '#7b9cff';
    } else {
      el.textContent = 'Nao definido';
      el.style.color = '#666';
    }
  } catch {
    el.textContent = '-';
  }
}

// ============================================================
// AULAS
// ============================================================

async function loadLessons() {
  const container = document.getElementById('lessonsContainer');
  container.innerHTML = '<p style="color:#666">Carregando...</p>';
  try {
    const { ok, data } = await apiFetch('/progress/list.php');
    if (!ok || !data.success) {
      container.innerHTML = '<p style="color:#c0392b">Erro ao carregar aulas.</p>';
      return;
    }
    const byLevel = {
      basico:        data.lessons.filter(l => l.nivel === 'basico'),
      intermediario: data.lessons.filter(l => l.nivel === 'intermediario'),
      avancado:      data.lessons.filter(l => l.nivel === 'avancado'),
    };
    container.innerHTML = Object.entries(byLevel).map(([nivel, lessons]) => {
      if (!lessons.length) return '';
      return `
        <div class="level-section">
          <div class="level-title">${nivelLabel(nivel)}</div>
          <div class="lessons-grid">
            ${lessons.map(l => renderLessonCard(l)).join('')}
          </div>
        </div>
      `;
    }).join('');
  } catch {
    container.innerHTML = '<p style="color:#c0392b">Erro de conexao.</p>';
  }
}

function renderLessonCard(lesson) {
  const cardClass = lesson.bloqueado ? 'lesson-card locked' : lesson.concluido ? 'lesson-card completed' : 'lesson-card';
  let footer;
  if (lesson.bloqueado) {
    footer = `<span style="font-size:12px;color:#666">Bloqueado</span>`;
  } else if (lesson.concluido) {
    footer = `
      <span style="color:#27ae60;font-size:13px">Concluida</span>
      <button class="btn btn-secondary btn-sm" onclick="toggleLesson(${lesson.id}, false)">Desfazer</button>
    `;
  } else {
    footer = `<button class="btn btn-primary btn-sm" onclick="toggleLesson(${lesson.id}, true)">Marcar como concluida</button>`;
  }
  return `
    <div class="${cardClass}">
      <h4>${esc(lesson.titulo)}</h4>
      <p>${esc(lesson.descricao)}</p>
      <div class="lesson-card-footer">${footer}</div>
    </div>
  `;
}

async function toggleLesson(lessonId, concluido) {
  try {
    const { ok, data } = await apiFetch('/progress/mark.php', {
      method: 'POST',
      body: JSON.stringify({ lesson_id: lessonId, concluido }),
    });
    if (!ok || !data.success) { showToast(data.message || 'Erro.', 'error'); return; }
    showToast(data.message, 'success');
    await loadLessons();
    loadDashboard();
  } catch {
    showToast('Erro de conexao.', 'error');
  }
}

// ============================================================
// CURSOS
// ============================================================

async function loadCourses() {
  const container = document.getElementById('coursesContainer');
  container.innerHTML = '<p style="color:#666">Carregando...</p>';
  try {
    const { ok, data } = await apiFetch('/courses/list.php');
    if (!ok || !data.success) {
      container.innerHTML = '<p style="color:#c0392b">Erro ao carregar cursos.</p>';
      return;
    }
    if (data.courses.length === 0) {
      container.innerHTML = '<p style="color:#666">Nenhum curso disponivel no momento.</p>';
      return;
    }

    const enrolled   = data.courses.filter(c => c.matriculado);
    const available  = data.courses.filter(c => !c.matriculado);

    let html = '';

    if (enrolled.length > 0) {
      html += `<div class="level-title" style="margin-bottom:12px">Meus cursos</div>`;
      html += `<div class="courses-grid">`;
      html += enrolled.map(c => renderCourseCard(c)).join('');
      html += `</div>`;
    }

    if (available.length > 0) {
      html += `<div class="level-title" style="margin:24px 0 12px">Disponiveis para matricula</div>`;
      html += `<div class="courses-grid">`;
      html += available.map(c => renderCourseCard(c)).join('');
      html += `</div>`;
    }

    container.innerHTML = html;
  } catch {
    container.innerHTML = '<p style="color:#c0392b">Erro de conexao.</p>';
  }
}

function renderCourseCard(course) {
  const nivelBadge = `<span class="badge badge-${course.nivel}">${nivelLabel(course.nivel)}</span>`;
  const progressHtml = course.matriculado ? `
    <div class="course-progress">
      <div class="course-progress-bar">
        <div class="course-progress-fill" style="width:${course.percentual}%"></div>
      </div>
      <span class="course-progress-text">${course.concluidas}/${course.total_aulas} aulas &mdash; ${course.percentual}%</span>
    </div>
  ` : `<div style="font-size:12px;color:#666">${course.total_aulas} aulas</div>`;

  const actionBtn = course.matriculado
    ? `<button class="btn btn-primary btn-sm" onclick="openCourseDetail(${course.id})">Acessar</button>
       <button class="btn btn-secondary btn-sm" onclick="enrollCourse(${course.id}, 'unenroll')">Cancelar</button>`
    : `<button class="btn btn-primary btn-sm" onclick="enrollCourse(${course.id}, 'enroll')">Matricular-se</button>`;

  return `
    <div class="course-card ${course.matriculado ? 'enrolled' : ''}">
      <div class="course-card-header">
        ${nivelBadge}
        <h4>${esc(course.nome)}</h4>
        <p>${esc(course.descricao.substring(0, 100))}${course.descricao.length > 100 ? '...' : ''}</p>
      </div>
      <div class="course-card-footer">
        ${progressHtml}
        <div class="actions" style="margin-top:10px">${actionBtn}</div>
      </div>
    </div>
  `;
}

async function enrollCourse(courseId, action) {
  try {
    const { ok, data } = await apiFetch('/courses/enroll.php', {
      method: 'POST',
      body: JSON.stringify({ course_id: courseId, action }),
    });
    if (!ok || !data.success) { showToast(data.message || 'Erro.', 'error'); return; }
    showToast(data.message, 'success');
    loadCourses();
  } catch {
    showToast('Erro de conexao.', 'error');
  }
}

async function openCourseDetail(courseId) {
  // Ativa a sub-view de detalhe sem mostrar na nav
  document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
  document.getElementById('section-course-detail').classList.add('active');

  document.getElementById('courseDetailNome').textContent = 'Carregando...';
  document.getElementById('courseDetailDesc').textContent = '';
  document.getElementById('courseDetailActions').innerHTML = '';
  document.getElementById('courseDetailProgress').innerHTML = '';
  document.getElementById('courseDetailLessons').innerHTML = '<p style="color:#666">Carregando...</p>';

  try {
    const { ok, data } = await apiFetch(`/courses/get.php?id=${courseId}`);
    if (!ok || !data.success) { showToast('Erro ao carregar curso.', 'error'); return; }

    const c = data.course;
    document.getElementById('courseDetailNome').textContent = c.nome;
    document.getElementById('courseDetailDesc').textContent = c.descricao;

    document.getElementById('courseDetailActions').innerHTML = `
      <button class="btn btn-secondary btn-sm" onclick="enrollCourse(${c.id}, 'unenroll'); showSection('courses')">Cancelar Matricula</button>
    `;

    document.getElementById('courseDetailProgress').innerHTML = `
      <div class="progress-summary">
        <h3>Progresso neste curso</h3>
        <div class="progress-info">
          <div><strong>${c.concluidas}</strong> <span>de</span> <strong>${c.total_aulas}</strong> <span>aulas concluidas</span></div>
          <div><strong>${c.percentual}%</strong></div>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" style="width:${c.percentual}%"></div>
        </div>
      </div>
    `;

    if (data.lessons.length === 0) {
      document.getElementById('courseDetailLessons').innerHTML = '<p style="color:#666">Nenhuma aula vinculada a este curso ainda.</p>';
      return;
    }

    const byLevel = {
      basico:        data.lessons.filter(l => l.nivel === 'basico'),
      intermediario: data.lessons.filter(l => l.nivel === 'intermediario'),
      avancado:      data.lessons.filter(l => l.nivel === 'avancado'),
    };

    document.getElementById('courseDetailLessons').innerHTML = Object.entries(byLevel).map(([nivel, lessons]) => {
      if (!lessons.length) return '';
      return `
        <div class="level-section">
          <div class="level-title">${nivelLabel(nivel)}</div>
          <div class="lessons-grid">
            ${lessons.map(l => `
              <div class="lesson-card ${l.concluido ? 'completed' : ''}">
                <h4>${esc(l.titulo)}</h4>
                <p>${esc(l.descricao)}</p>
                <div class="lesson-card-footer">
                  ${l.concluido
                    ? `<span style="color:#27ae60;font-size:13px">Concluida</span>`
                    : `<span style="color:#666;font-size:12px">Pendente</span>`}
                </div>
              </div>
            `).join('')}
          </div>
        </div>
      `;
    }).join('');
  } catch {
    showToast('Erro de conexao.', 'error');
  }
}

// ============================================================
// PERFIL DE INVESTIDOR
// ============================================================

const INVESTOR_QUESTIONS = [
  {
    id: 'q1',
    texto: 'Qual e o seu principal objetivo financeiro?',
    opcoes: [
      { valor: 'a', texto: 'Proteger meu dinheiro — seguranca acima de tudo' },
      { valor: 'b', texto: 'Crescimento moderado com alguma seguranca' },
      { valor: 'c', texto: 'Maximizar o retorno, mesmo aceitando riscos altos' },
    ]
  },
  {
    id: 'q2',
    texto: 'Em quanto tempo voce precisa do retorno do seu investimento?',
    opcoes: [
      { valor: 'a', texto: 'Menos de 1 ano (curto prazo)' },
      { valor: 'b', texto: 'De 1 a 5 anos (medio prazo)' },
      { valor: 'c', texto: 'Mais de 5 anos (longo prazo)' },
    ]
  },
  {
    id: 'q3',
    texto: 'Se seus investimentos caissem 20% em um mes, o que voce faria?',
    opcoes: [
      { valor: 'a', texto: 'Venderia tudo para nao perder mais' },
      { valor: 'b', texto: 'Ficaria preocupado mas esperaria a recuperacao' },
      { valor: 'c', texto: 'Compraria mais — e uma otima oportunidade!' },
    ]
  },
  {
    id: 'q4',
    texto: 'Qual e a sua tolerancia a perdas?',
    opcoes: [
      { valor: 'a', texto: 'Nao aceito perder nada do capital investido' },
      { valor: 'b', texto: 'Aceito perder ate 10% se o retorno esperado compensar' },
      { valor: 'c', texto: 'Aceito perder mais de 20% em busca de retorno alto' },
    ]
  },
  {
    id: 'q5',
    texto: 'Qual e a sua experiencia com investimentos?',
    opcoes: [
      { valor: 'a', texto: 'Nenhuma — so tenho poupanca ou conta corrente' },
      { valor: 'b', texto: 'Ja investi em renda fixa ou fundos conservadores' },
      { valor: 'c', texto: 'Ja investi em acoes, fundos imobiliarios ou criptomoedas' },
    ]
  }
];

const INVESTOR_RECOMMENDATIONS = {
  conservador: {
    cor: '#6fcf6f',
    descricao: 'Voce prioriza seguranca e estabilidade. Prefere nao correr riscos e valoriza a previsibilidade dos retornos.',
    recomendacoes: [
      { titulo: 'Poupanca', desc: 'Seguranca total, liquidez imediata, ideal para reserva de emergencia.' },
      { titulo: 'Tesouro Direto SELIC', desc: 'Titulos do governo com rendimento diario, altissima seguranca.' },
      { titulo: 'CDB com liquidez diaria', desc: 'Rendem acima da poupanca com seguranca garantida pelo FGC ate R$250k.' },
      { titulo: 'Renda Fixa (LCI/LCA)', desc: 'Isento de IR para pessoas fisicas, lastreado em credito imobiliario e do agronegocio.' },
    ]
  },
  moderado: {
    cor: '#e0b84a',
    descricao: 'Voce busca equilibrio entre seguranca e crescimento. Aceita algum risco em troca de retornos maiores no medio prazo.',
    recomendacoes: [
      { titulo: 'Fundos Multimercado', desc: 'Diversificacao automatica entre renda fixa, acoes e cambio.' },
      { titulo: 'Fundos Imobiliarios (FIIs)', desc: 'Dividendos mensais isentos de IR, exposicao ao mercado imobiliario.' },
      { titulo: 'CDB de medio/longo prazo', desc: 'Taxas mais altas para prazos maiores, ainda com protecao do FGC.' },
      { titulo: 'Tesouro IPCA+', desc: 'Protege contra inflacao com rentabilidade real garantida.' },
    ]
  },
  agressivo: {
    cor: '#e06f6f',
    descricao: 'Voce tem alto apetite por risco e busca retornos expressivos no longo prazo. Esta disposto a enfrentar volatilidade.',
    recomendacoes: [
      { titulo: 'Acoes (Bolsa de Valores)', desc: 'Participacao em empresas com potencial de valorizacao alta.' },
      { titulo: 'ETFs (Fundos de Indice)', desc: 'Diversificacao com baixo custo — IBOVESPA, S&P500, etc.' },
      { titulo: 'Fundos de Acoes', desc: 'Gestao profissional focada em renda variavel.' },
      { titulo: 'Criptomoedas', desc: 'Alta volatilidade com potencial de retorno expressivo no longo prazo.' },
    ]
  }
};

let investorAnswers = {};
let currentQuestion = 0;

async function loadInvestorSection() {
  const container = document.getElementById('investorContainer');
  container.innerHTML = '<p style="color:#666">Carregando...</p>';
  try {
    const { ok, data } = await apiFetch('/investor/get.php');
    if (ok && data.success && data.profile) {
      renderInvestorResult(data.profile);
    } else {
      renderInvestorQuiz();
    }
  } catch {
    container.innerHTML = '<p style="color:#c0392b">Erro ao carregar perfil.</p>';
  }
}

function renderInvestorQuiz() {
  investorAnswers = {};
  currentQuestion = 0;
  renderQuestion(0);
}

function renderQuestion(index) {
  const container = document.getElementById('investorContainer');
  const q         = INVESTOR_QUESTIONS[index];
  const total     = INVESTOR_QUESTIONS.length;

  container.innerHTML = `
    <div class="investor-quiz">
      <div class="quiz-progress">
        <span class="quiz-step">Pergunta ${index + 1} de ${total}</span>
        <div class="quiz-progress-bar">
          <div class="quiz-progress-fill" style="width:${((index) / total) * 100}%"></div>
        </div>
      </div>
      <div class="quiz-card">
        <h3 class="quiz-question">${esc(q.texto)}</h3>
        <div class="quiz-options">
          ${q.opcoes.map(o => `
            <button class="quiz-option" onclick="selectAnswer('${q.id}', '${o.valor}', this)">
              <span class="option-letter">${o.valor.toUpperCase()}</span>
              ${esc(o.texto)}
            </button>
          `).join('')}
        </div>
        <div class="quiz-nav">
          ${index > 0 ? `<button class="btn btn-secondary" onclick="goToPrevQuestion(${index})">Anterior</button>` : '<span></span>'}
          <button class="btn btn-primary" id="nextBtn" disabled onclick="goToNextQuestion(${index})">
            ${index === total - 1 ? 'Ver meu perfil' : 'Proxima'}
          </button>
        </div>
      </div>
    </div>
  `;

  // Se ja respondeu esta pergunta antes, re-seleciona
  if (investorAnswers[q.id]) {
    const alreadySelected = container.querySelector(`.quiz-option[onclick*="'${investorAnswers[q.id]}'"]`);
    if (alreadySelected) {
      alreadySelected.classList.add('selected');
      document.getElementById('nextBtn').disabled = false;
    }
  }
}

function selectAnswer(questionId, valor, btn) {
  // Remove seleção de outros botões da mesma pergunta
  btn.closest('.quiz-options').querySelectorAll('.quiz-option').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  investorAnswers[questionId] = valor;
  document.getElementById('nextBtn').disabled = false;
}

function goToPrevQuestion(currentIndex) {
  renderQuestion(currentIndex - 1);
}

function goToNextQuestion(currentIndex) {
  const q = INVESTOR_QUESTIONS[currentIndex];
  if (!investorAnswers[q.id]) return;

  if (currentIndex < INVESTOR_QUESTIONS.length - 1) {
    renderQuestion(currentIndex + 1);
  } else {
    submitInvestorQuiz();
  }
}

async function submitInvestorQuiz() {
  const container = document.getElementById('investorContainer');
  container.innerHTML = '<p style="color:#666">Calculando seu perfil...</p>';
  try {
    const { ok, data } = await apiFetch('/investor/save.php', {
      method: 'POST',
      body: JSON.stringify({ respostas: investorAnswers }),
    });
    if (!ok || !data.success) {
      showToast(data.message || 'Erro ao salvar.', 'error');
      renderInvestorQuiz();
      return;
    }
    // Recarrega para exibir o resultado salvo
    const { ok: ok2, data: data2 } = await apiFetch('/investor/get.php');
    if (ok2 && data2.success && data2.profile) {
      renderInvestorResult(data2.profile);
    }
    loadInvestorCardDashboard();
    showToast('Perfil salvo com sucesso!', 'success');
  } catch {
    showToast('Erro de conexao.', 'error');
    renderInvestorQuiz();
  }
}

function renderInvestorResult(profile) {
  const container = document.getElementById('investorContainer');
  const rec       = INVESTOR_RECOMMENDATIONS[profile.perfil];
  const labels    = { conservador: 'Conservador', moderado: 'Moderado', agressivo: 'Agressivo' };

  container.innerHTML = `
    <div class="investor-result">
      <div class="investor-perfil-badge" style="border-color:${rec.cor}">
        <span class="investor-perfil-label" style="color:${rec.cor}">${labels[profile.perfil]}</span>
        <span class="investor-perfil-sub">Seu perfil de investidor</span>
        <span class="investor-perfil-score">Pontuacao: ${profile.pontuacao}/15</span>
      </div>

      <div class="card" style="margin-top:16px">
        <div class="card-header">O que isso significa?</div>
        <div class="card-body">
          <p style="color:#ccc;line-height:1.6">${esc(rec.descricao)}</p>
        </div>
      </div>

      <div class="card" style="margin-top:16px">
        <div class="card-header">Investimentos recomendados para voce</div>
        <div class="card-body">
          <div class="recommendations-grid">
            ${rec.recomendacoes.map(r => `
              <div class="recommendation-item" style="border-left-color:${rec.cor}">
                <strong>${esc(r.titulo)}</strong>
                <p>${esc(r.desc)}</p>
              </div>
            `).join('')}
          </div>
        </div>
      </div>

      <div style="margin-top:16px;text-align:right">
        <button class="btn btn-secondary" onclick="renderInvestorQuiz()">Refazer o teste</button>
      </div>
    </div>
  `;
}

// ============================================================
// PERFIL DO USUÁRIO
// ============================================================

function loadProfile() {
  const user = getCurrentUser();
  if (!user) return;
  document.getElementById('profileNome').value  = user.nome;
  document.getElementById('profileEmail').value = user.email;
}

function initProfileForm() {
  const form = document.getElementById('profileForm');
  if (!form) return;
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const nome   = document.getElementById('profileNome').value.trim();
    const email  = document.getElementById('profileEmail').value.trim();
    const senha  = document.getElementById('profileSenha').value.trim();
    const conf   = document.getElementById('profileConfirm').value.trim();
    const btn    = form.querySelector('[type=submit]');
    if (senha && senha !== conf) { showToast('As senhas nao coincidem.', 'error'); return; }
    btn.disabled = true;
    btn.textContent = 'Salvando...';
    const body = { id: currentUser.id, nome, email };
    if (senha) body.senha = senha;
    try {
      const { ok, data } = await apiFetch('/users/update.php', {
        method: 'POST',
        body: JSON.stringify(body),
      });
      if (!ok || !data.success) { showToast(data.message, 'error'); return; }
      currentUser.nome  = nome;
      currentUser.email = email;
      setCurrentUser(currentUser);
      document.getElementById('userName').textContent = nome;
      document.getElementById('profileSenha').value   = '';
      document.getElementById('profileConfirm').value = '';
      showToast('Perfil atualizado.', 'success');
    } catch {
      showToast('Erro de conexao.', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Salvar';
    }
  });
}

// ============================================================
// UTILITÁRIOS
// ============================================================

function nivelLabel(nivel) {
  return { basico: 'Basico', intermediario: 'Intermediario', avancado: 'Avancado' }[nivel] || nivel;
}

function esc(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ============================================================
// SIMULADOR DE INVESTIMENTOS — com integração ao Perfil Investidor
// ============================================================

// ------------------------------------------------------------------
// Data tables — single source of truth for labels, rates, and
// profile→investment recommendations.
// ------------------------------------------------------------------

const INVESTMENT_TYPES_META = [
  { id: 'savings', label: 'Poupança',    rate: '0,5% a.m.' },
  { id: 'cdb',     label: 'CDB',         rate: '0,8% a.m.' },
  { id: 'stocks',  label: 'Ações',       rate: '1,2% a.m.' },
  { id: 'crypto',  label: 'Criptomoeda', rate: '2,0% a.m.' },
];

// Maps each investor profile to its recommended investment types.
// Extend this object whenever new profiles or types are added.
const PROFILE_INVESTMENT_MAP = {
  conservador: ['savings', 'cdb'],
  moderado:    ['cdb', 'stocks'],
  agressivo:   ['stocks', 'crypto'],
};

// Display metadata for each profile (label + accent colour)
const PROFILE_META = {
  conservador: { label: 'Conservador', color: '#6fcf6f' },
  moderado:    { label: 'Moderado',    color: '#e0b84a' },
  agressivo:   { label: 'Agressivo',   color: '#e06f6f' },
};

// FIX #3 — pre-built set for O(1) type validation in runSimulation()
const VALID_INVESTMENT_IDS = new Set(INVESTMENT_TYPES_META.map(t => t.id));

// Kept for backward compatibility (history table labels)
const SIMULATOR_TYPE_LABELS = Object.fromEntries(
  INVESTMENT_TYPES_META.map(t => [t.id, t.label])
);

let _simulatorChart       = null;  // Chart.js instance — destroyed/rebuilt each simulation
let _simulatorReady       = false; // ensures initSimulatorForm runs only once
let _simulatorProfile     = null;  // FIX #5 — cached profile, avoids needless re-renders
let _simUserHasSelected   = false; // FIX #2 — true once the user explicitly picks a card

// ------------------------------------------------------------------
// Entry point — called by showSection('simulator')
// ------------------------------------------------------------------
async function loadSimulator() {
  initSimulatorForm();

  // FIX #1 — render cards immediately so the form is never blank.
  // Use the cached profile from the last visit (or no recommendations on
  // first load). Either way the user can start filling the form at once.
  const cachedRec = _simulatorProfile
    ? (PROFILE_INVESTMENT_MAP[_simulatorProfile.perfil] ?? [])
    : [];
  renderSimTypeCards(cachedRec);

  // Fetch fresh investor profile in the background
  let profile = null;
  try {
    const { ok, data } = await apiFetch('/investor/get.php');
    if (ok && data.success) profile = data.profile ?? null;
  } catch { /* network failure — profile stays null, UI stays functional */ }

  // FIX #5 — only re-apply when the profile actually changed.
  // This prevents unnecessary card rebuilds (which would reset the user's
  // card selection) on every re-visit to the simulator section.
  const newPerfil = profile?.perfil ?? null;
  const oldPerfil = _simulatorProfile?.perfil ?? null;

  _simulatorProfile = profile;

  if (newPerfil !== oldPerfil) {
    // Profile changed (or first load with a real profile): full personalisation
    applyProfileToSimulator(profile);
  } else {
    // Profile unchanged: only refresh the banner text (cheap, no card rebuild)
    const recommended = PROFILE_INVESTMENT_MAP[newPerfil] ?? [];
    renderSimProfileBanner(newPerfil, recommended);
  }

  loadSimulatorHistory();
}

// ------------------------------------------------------------------
// Profile integration — banner + investment type card order/labels
// ------------------------------------------------------------------

/**
 * Orchestrates all personalisation based on the fetched profile.
 * @param {Object|null} profile — profile object from investor/get.php
 */
function applyProfileToSimulator(profile) {
  const perfil      = profile?.perfil ?? null;
  const recommended = PROFILE_INVESTMENT_MAP[perfil] ?? [];

  renderSimProfileBanner(perfil, recommended);
  renderSimTypeCards(recommended);
}

/**
 * Renders the recommendation banner.
 * - No profile  → CTA to complete the quiz
 * - Has profile → show profile name + recommended types
 *
 * Uses only DOM property assignments for colour values (no raw style
 * strings injected into innerHTML) to avoid future XSS surface.
 */
function renderSimProfileBanner(perfil, recommended) {
  const banner = document.getElementById('simProfileBanner');
  if (!banner) return;

  banner.style.display = '';

  if (!perfil) {
    banner.className = 'sim-profile-banner sim-profile-banner--empty';
    banner.style.borderLeftColor = '';
    banner.innerHTML = `
      <span class="sim-profile-banner__msg">
        Você ainda não definiu seu Perfil de Investidor.
        Complete o quiz para receber recomendações personalizadas.
      </span>
      <button class="btn btn-secondary btn-sm"
              onclick="showSection('investor')">
        Fazer quiz agora
      </button>
    `;
    return;
  }

  // FIX #6 — accent colour applied via DOM property, not innerHTML injection
  const meta = PROFILE_META[perfil] ?? { label: perfil, color: '#7b9cff' };

  // FIX #4 — build "CDB e Ações" without relying on Array→string coercion
  const recNames = recommended
    .map(id => INVESTMENT_TYPES_META.find(t => t.id === id)?.label ?? id)
    .map(name => `<strong>${esc(name)}</strong>`);

  let recText;
  if (recNames.length === 0) {
    recText = 'nenhum definido';
  } else if (recNames.length === 1) {
    recText = recNames[0];
  } else {
    recText = recNames.slice(0, -1).join(', ') + ' e ' + recNames[recNames.length - 1];
  }

  banner.className = 'sim-profile-banner sim-profile-banner--active';
  banner.style.borderLeftColor = meta.color;

  // Profile label element gets its colour via DOM — not via a raw style string
  banner.innerHTML = `
    <div class="sim-profile-banner__body">
      <span class="sim-profile-banner__profile">
        Seu perfil:&nbsp;<strong id="simBannerProfileLabel">${esc(meta.label)}</strong>
      </span>
      <span class="sim-profile-banner__rec">
        Recomendamos para você: ${recText}
      </span>
      <span class="sim-profile-banner__hint">
        Os investimentos recomendados aparecem destacados abaixo.
        Fique à vontade para simular qualquer opção.
      </span>
    </div>
    <button class="btn btn-secondary btn-sm sim-profile-banner__cta"
            onclick="showSection('investor')">
      Rever perfil
    </button>
  `;

  // Apply colour via DOM property after innerHTML is set (safe, no injection risk)
  const labelEl = document.getElementById('simBannerProfileLabel');
  if (labelEl) labelEl.style.color = meta.color;
}

/**
 * Builds the investment type card grid.
 * Recommended types appear first with a badge; all types remain available.
 *
 * FIX #2 — preserves the user's current card selection across re-renders:
 *   • If the user explicitly clicked a card (_simUserHasSelected = true)
 *     that type stays active even after a profile-driven re-render.
 *   • If the user has not yet interacted, defaults to the first recommended
 *     type (or first overall when there are no recommendations).
 *
 * @param {string[]} recommended — type IDs for this profile (may be empty)
 */
function renderSimTypeCards(recommended) {
  const container   = document.getElementById('simTypeCards');
  const hiddenInput = document.getElementById('simType');
  if (!container || !hiddenInput) return;

  // Recommended types first, then the rest — stable relative order in each group
  const ordered = [
    ...INVESTMENT_TYPES_META.filter(t =>  recommended.includes(t.id)),
    ...INVESTMENT_TYPES_META.filter(t => !recommended.includes(t.id)),
  ];

  // Determine which card should be active:
  //   1. User's explicit pick (if still valid)  ← highest priority
  //   2. First recommended type                 ← profile personalisation
  //   3. First type overall                     ← safe fallback
  const prevId  = hiddenInput.value;
  const activeId = (_simUserHasSelected && VALID_INVESTMENT_IDS.has(prevId))
    ? prevId
    : (ordered[0]?.id ?? 'savings');

  hiddenInput.value = activeId;

  container.innerHTML = ordered.map(t => {
    const isRec    = recommended.includes(t.id);
    const isActive = t.id === activeId;
    return `
      <button type="button"
              class="sim-type-card${isRec ? ' sim-type-card--rec' : ''}${isActive ? ' sim-type-card--active' : ''}"
              data-type="${t.id}"
              title="${isRec ? 'Recomendado para o seu perfil' : ''}"
              onclick="selectSimType(this, '${t.id}')">
        <span class="sim-type-card__label">${esc(t.label)}</span>
        <span class="sim-type-card__rate">${esc(t.rate)}</span>
        ${isRec ? '<span class="sim-type-card__badge">Recomendado</span>' : ''}
      </button>
    `;
  }).join('');
}

/**
 * Handles a card click: flags that the user has made an explicit choice,
 * updates the hidden input, and marks the clicked card as active.
 * Called inline from each card's onclick attribute.
 */
function selectSimType(btn, typeId) {
  // FIX #2 — record explicit user intent so re-renders preserve this choice
  _simUserHasSelected = true;

  const hiddenInput = document.getElementById('simType');
  if (hiddenInput) hiddenInput.value = typeId;

  document.querySelectorAll('.sim-type-card')
    .forEach(c => c.classList.remove('sim-type-card--active'));
  btn.classList.add('sim-type-card--active');
}

// Attach submit handler once
function initSimulatorForm() {
  if (_simulatorReady) return;
  const form = document.getElementById('simulatorForm');
  if (!form) return;
  form.addEventListener('submit', runSimulation);
  _simulatorReady = true;
}

async function runSimulation(e) {
  e.preventDefault();

  const capital = parseFloat(document.getElementById('simCapital').value) || 0;
  const type    = document.getElementById('simType').value;
  const period  = parseInt(document.getElementById('simPeriod').value, 10) || 0;
  const contrib = parseFloat(document.getElementById('simContrib').value) || 0;

  // Client-side validation (mirrors server-side)
  // FIX #3 — guard against an empty or tampered hidden input value
  if (!VALID_INVESTMENT_IDS.has(type)) {
    showToast('Selecione um tipo de investimento.', 'error');
    return;
  }
  if (capital < 0 || contrib < 0) {
    showToast('Valores não podem ser negativos.', 'error');
    return;
  }
  if (capital === 0 && contrib === 0) {
    showToast('Informe um capital inicial ou aporte mensal.', 'error');
    return;
  }
  if (!period || period < 1 || period > 600) {
    showToast('Período inválido (1–600 meses).', 'error');
    return;
  }

  const btn = e.target.querySelector('[type=submit]');
  btn.disabled    = true;
  btn.textContent = 'Calculando...';

  try {
    const { ok, data } = await apiFetch('/simulator/calculate.php', {
      method: 'POST',
      body: JSON.stringify({
        initial_capital:      capital,
        investment_type:      type,
        period_months:        period,
        monthly_contribution: contrib,
      }),
    });

    if (!ok || !data.success) {
      showToast(data.message || 'Erro ao simular.', 'error');
      return;
    }

    renderSimulatorResults(data, period);
    loadSimulatorHistory();
  } catch {
    showToast('Erro de conexão.', 'error');
  } finally {
    btn.disabled    = false;
    btn.textContent = 'Simular';
  }
}

function renderSimulatorResults(data, period) {
  document.getElementById('simulatorResults').style.display = 'block';

  document.getElementById('simResInvested').textContent = formatCurrency(data.totalInvested);
  document.getElementById('simResProfit').textContent   = formatCurrency(data.totalProfit);
  document.getElementById('simResFinal').textContent    = formatCurrency(data.finalAmount);

  renderSimulatorChart(data.evolution, data.investedEvol, period);
}

function renderSimulatorChart(evolution, investedEvol, period) {
  const canvas = document.getElementById('simulatorChart');
  if (!canvas) return;

  // Build X-axis labels — show "Mês N" but limit density for long periods
  const labels = Array.from({ length: period }, (_, i) => `Mês ${i + 1}`);

  if (_simulatorChart) {
    _simulatorChart.destroy();
    _simulatorChart = null;
  }

  _simulatorChart = new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Patrimônio Total',
          data: evolution,
          borderColor: '#4a6cf7',
          backgroundColor: 'rgba(74, 108, 247, 0.12)',
          fill: true,
          tension: 0.4,
          pointRadius: period <= 60 ? 3 : 0,
          pointHoverRadius: 5,
          borderWidth: 2,
        },
        {
          label: 'Total Investido',
          data: investedEvol,
          borderColor: '#27ae60',
          backgroundColor: 'rgba(39, 174, 96, 0.05)',
          fill: true,
          tension: 0,
          borderDash: [5, 5],
          pointRadius: 0,
          pointHoverRadius: 5,
          borderWidth: 2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          labels: { color: '#aaa', font: { size: 12 }, boxWidth: 20 },
        },
        tooltip: {
          backgroundColor: '#1c1c1c',
          borderColor: '#333',
          borderWidth: 1,
          titleColor: '#e0e0e0',
          bodyColor: '#aaa',
          callbacks: {
            label: ctx => ` ${ctx.dataset.label}: ${formatCurrency(ctx.parsed.y)}`,
          },
        },
      },
      scales: {
        x: {
          ticks: {
            color: '#555',
            maxTicksLimit: 12,
            font: { size: 11 },
          },
          grid: { color: '#1e1e1e' },
        },
        y: {
          ticks: {
            color: '#555',
            font: { size: 11 },
            callback: val => formatCurrencyShort(val),
          },
          grid: { color: '#1e1e1e' },
        },
      },
    },
  });
}

async function loadSimulatorHistory() {
  const card  = document.getElementById('simulatorHistoryCard');
  const tbody = document.getElementById('simulatorHistoryBody');
  if (!card || !tbody) return;

  try {
    const { ok, data } = await apiFetch('/simulator/history.php');

    if (!ok || !data.success || !data.simulations.length) {
      card.style.display = 'none';
      return;
    }

    card.style.display = 'block';
    tbody.innerHTML = data.simulations.map(s => `
      <tr>
        <td>${esc(SIMULATOR_TYPE_LABELS[s.investment_type] || s.investment_type)}</td>
        <td>${formatCurrency(s.initial_capital)}</td>
        <td>${formatCurrency(s.monthly_contribution)}</td>
        <td>${s.period_months} meses</td>
        <td style="color:#7b9cff">${formatCurrency(s.final_amount)}</td>
        <td style="color:#27ae60">+${formatCurrency(s.total_profit)}</td>
        <td style="color:#666">${formatDate(s.created_at)}</td>
      </tr>
    `).join('');
  } catch {
    card.style.display = 'none';
  }
}

// ------ Currency helpers ------

function formatCurrency(value) {
  return Number(value).toLocaleString('pt-BR', {
    style:    'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
  });
}

function formatCurrencyShort(value) {
  if (value >= 1_000_000) return 'R$ ' + (value / 1_000_000).toFixed(1) + 'M';
  if (value >= 1_000)     return 'R$ ' + (value / 1_000).toFixed(1) + 'k';
  return 'R$ ' + Number(value).toFixed(0);
}
