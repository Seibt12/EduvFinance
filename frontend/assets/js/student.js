let usuarioAtual = null;

document.addEventListener('DOMContentLoaded', () => {
  verificarPapel('aluno', usuario => {
    usuarioAtual = usuario;
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
  if (nome === 'lessons')   carregarAulas();
  if (nome === 'courses')   carregarCursos();
  if (nome === 'investor')  carregarSecaoInvestidor();
  if (nome === 'simulator') carregarSimulador();
  if (nome === 'profile')   carregarPerfil();
}

// ============================================================
// DASHBOARD
// ============================================================

async function carregarDashboard() {
  try {
    const { ok, data } = await apiFetch('/progress/list.php');
    if (!ok || !data.success) return;
    const r = data.resumo;

    document.getElementById('dashPercent').textContent    = r.percentual + '%';
    document.getElementById('dashConcluidas').textContent = r.concluidas;
    document.getElementById('dashTotal').textContent      = r.total;

    const barra = document.getElementById('dashProgressBar');
    if (barra) barra.style.width = r.percentual + '%';

    const msg = document.getElementById('motivMessage');
    if (msg) {
      if (r.percentual === 100)    msg.textContent = 'Parabens! Voce concluiu todas as aulas!';
      else if (r.percentual >= 66) msg.textContent = 'Voce esta quase la, continue!';
      else if (r.percentual >= 33) msg.textContent = 'Bom progresso, nao pare agora!';
      else                         msg.textContent = 'Comece pelas aulas basicas e evolua!';
    }

    const proximaAula = data.lessons.find(l => !l.concluido && !l.bloqueado);
    const elProxima   = document.getElementById('nextLesson');
    if (elProxima) {
      if (proximaAula) {
        elProxima.innerHTML = `
          <span class="badge badge-${proximaAula.nivel}">${labelDoNivel(proximaAula.nivel)}</span>
          <strong style="display:block;margin:6px 0">${esc(proximaAula.titulo)}</strong>
          <button class="btn btn-primary btn-sm" onclick="exibirSecao('lessons')">Ver aulas</button>
        `;
      } else if (r.percentual === 100) {
        elProxima.innerHTML = '<span style="color:#27ae60">Todas as aulas concluidas!</span>';
      } else {
        elProxima.innerHTML = '<span style="color:#666">Complete as aulas basicas para desbloquear os proximos niveis.</span>';
      }
    }

    // Mostra o perfil de investidor no card do dashboard
    carregarPerfilNoDashboard();
  } catch {
    exibirToast('Erro ao carregar dashboard.', 'error');
  }
}

async function carregarPerfilNoDashboard() {
  const el = document.getElementById('dashInvestorPerfil');
  if (!el) return;
  try {
    const { ok, data } = await apiFetch('/investor/get.php');
    if (ok && data.success && data.profile) {
      const rotulos = { conservador: 'Conservador', moderado: 'Moderado', agressivo: 'Agressivo' };
      const cores   = { conservador: '#6fcf6f', moderado: '#e0b84a', agressivo: '#e06f6f' };
      const p = data.profile.perfil;
      el.textContent = rotulos[p] || p;
      el.style.color = cores[p] || '#7b9cff';
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

async function carregarAulas() {
  const container = document.getElementById('lessonsContainer');
  container.innerHTML = '<p style="color:#666">Carregando...</p>';
  try {
    const { ok, data } = await apiFetch('/progress/list.php');
    if (!ok || !data.success) {
      container.innerHTML = '<p style="color:#c0392b">Erro ao carregar aulas.</p>';
      return;
    }
    const porNivel = {
      basico:        data.lessons.filter(l => l.nivel === 'basico'),
      intermediario: data.lessons.filter(l => l.nivel === 'intermediario'),
      avancado:      data.lessons.filter(l => l.nivel === 'avancado'),
    };
    container.innerHTML = Object.entries(porNivel).map(([nivel, aulas]) => {
      if (!aulas.length) return '';
      return `
        <div class="level-section">
          <div class="level-title">${labelDoNivel(nivel)}</div>
          <div class="lessons-grid">
            ${aulas.map(a => renderizarCardAula(a)).join('')}
          </div>
        </div>
      `;
    }).join('');
  } catch {
    container.innerHTML = '<p style="color:#c0392b">Erro de conexao.</p>';
  }
}

function renderizarCardAula(aula) {
  const classeCard = aula.bloqueado ? 'lesson-card locked' : aula.concluido ? 'lesson-card completed' : 'lesson-card';
  let rodape;
  if (aula.bloqueado) {
    rodape = `<span style="font-size:12px;color:#666">Bloqueado</span>`;
  } else if (aula.concluido) {
    rodape = `
      <span style="color:#27ae60;font-size:13px">Concluida</span>
      <button class="btn btn-secondary btn-sm" onclick="alternarAula(${aula.id}, false)">Desfazer</button>
    `;
  } else {
    rodape = `<button class="btn btn-primary btn-sm" onclick="alternarAula(${aula.id}, true)">Marcar como concluida</button>`;
  }
  return `
    <div class="${classeCard}">
      <h4>${esc(aula.titulo)}</h4>
      <p>${esc(aula.descricao)}</p>
      <div class="lesson-card-footer">${rodape}</div>
    </div>
  `;
}

async function alternarAula(aulaId, concluido) {
  try {
    const { ok, data } = await apiFetch('/progress/mark.php', {
      method: 'POST',
      body: JSON.stringify({ lesson_id: aulaId, concluido }),
    });
    if (!ok || !data.success) { exibirToast(data.message || 'Erro.', 'error'); return; }
    exibirToast(data.message, 'success');
    await carregarAulas();
    carregarDashboard();
  } catch {
    exibirToast('Erro de conexao.', 'error');
  }
}

// ============================================================
// CURSOS
// ============================================================

async function carregarCursos() {
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

    const matriculados = data.courses.filter(c => c.matriculado);
    const disponiveis  = data.courses.filter(c => !c.matriculado);

    let html = '';

    if (matriculados.length > 0) {
      html += `<div class="level-title" style="margin-bottom:12px">Meus cursos</div>`;
      html += `<div class="courses-grid">`;
      html += matriculados.map(c => renderizarCardCurso(c)).join('');
      html += `</div>`;
    }

    if (disponiveis.length > 0) {
      html += `<div class="level-title" style="margin:24px 0 12px">Disponiveis para matricula</div>`;
      html += `<div class="courses-grid">`;
      html += disponiveis.map(c => renderizarCardCurso(c)).join('');
      html += `</div>`;
    }

    container.innerHTML = html;
  } catch {
    container.innerHTML = '<p style="color:#c0392b">Erro de conexao.</p>';
  }
}

function renderizarCardCurso(curso) {
  const badgeNivel    = `<span class="badge badge-${curso.nivel}">${labelDoNivel(curso.nivel)}</span>`;
  const progressoHtml = curso.matriculado ? `
    <div class="course-progress">
      <div class="course-progress-bar">
        <div class="course-progress-fill" style="width:${curso.percentual}%"></div>
      </div>
      <span class="course-progress-text">${curso.concluidas}/${curso.total_aulas} aulas &mdash; ${curso.percentual}%</span>
    </div>
  ` : `<div style="font-size:12px;color:#666">${curso.total_aulas} aulas</div>`;

  const botaoAcao = curso.matriculado
    ? `<button class="btn btn-primary btn-sm" onclick="abrirDetalhesCurso(${curso.id})">Acessar</button>
       <button class="btn btn-secondary btn-sm" onclick="matricularCurso(${curso.id}, 'unenroll')">Cancelar</button>`
    : `<button class="btn btn-primary btn-sm" onclick="matricularCurso(${curso.id}, 'enroll')">Matricular-se</button>`;

  return `
    <div class="course-card ${curso.matriculado ? 'enrolled' : ''}">
      <div class="course-card-header">
        ${badgeNivel}
        <h4>${esc(curso.nome)}</h4>
        <p>${esc(curso.descricao.substring(0, 100))}${curso.descricao.length > 100 ? '...' : ''}</p>
      </div>
      <div class="course-card-footer">
        ${progressoHtml}
        <div class="actions" style="margin-top:10px">${botaoAcao}</div>
      </div>
    </div>
  `;
}

async function matricularCurso(cursoId, acao) {
  try {
    const { ok, data } = await apiFetch('/courses/enroll.php', {
      method: 'POST',
      body: JSON.stringify({ course_id: cursoId, action: acao }),
    });
    if (!ok || !data.success) { exibirToast(data.message || 'Erro.', 'error'); return; }
    exibirToast(data.message, 'success');
    carregarCursos();
  } catch {
    exibirToast('Erro de conexao.', 'error');
  }
}

async function abrirDetalhesCurso(cursoId) {
  // Ativa a sub-view de detalhe sem mostrar na nav
  document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
  document.getElementById('section-course-detail').classList.add('active');

  document.getElementById('courseDetailNome').textContent = 'Carregando...';
  document.getElementById('courseDetailDesc').textContent = '';
  document.getElementById('courseDetailActions').innerHTML = '';
  document.getElementById('courseDetailProgress').innerHTML = '';
  document.getElementById('courseDetailLessons').innerHTML = '<p style="color:#666">Carregando...</p>';

  try {
    const { ok, data } = await apiFetch(`/courses/get.php?id=${cursoId}`);
    if (!ok || !data.success) { exibirToast('Erro ao carregar curso.', 'error'); return; }

    const c = data.course;
    document.getElementById('courseDetailNome').textContent = c.nome;
    document.getElementById('courseDetailDesc').textContent = c.descricao;

    document.getElementById('courseDetailActions').innerHTML = `
      <button class="btn btn-secondary btn-sm" onclick="matricularCurso(${c.id}, 'unenroll'); exibirSecao('courses')">Cancelar Matricula</button>
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

    const porNivel = {
      basico:        data.lessons.filter(l => l.nivel === 'basico'),
      intermediario: data.lessons.filter(l => l.nivel === 'intermediario'),
      avancado:      data.lessons.filter(l => l.nivel === 'avancado'),
    };

    document.getElementById('courseDetailLessons').innerHTML = Object.entries(porNivel).map(([nivel, aulas]) => {
      if (!aulas.length) return '';
      return `
        <div class="level-section">
          <div class="level-title">${labelDoNivel(nivel)}</div>
          <div class="lessons-grid">
            ${aulas.map(a => `
              <div class="lesson-card ${a.concluido ? 'completed' : ''}">
                <h4>${esc(a.titulo)}</h4>
                <p>${esc(a.descricao)}</p>
                <div class="lesson-card-footer">
                  ${a.concluido
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
    exibirToast('Erro de conexao.', 'error');
  }
}

// ============================================================
// PERFIL DE INVESTIDOR
// ============================================================

const PERGUNTAS_INVESTIDOR = [
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

const RECOMENDACOES_PERFIL = {
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

let respostasQuiz = {};
let perguntaAtual = 0;

async function carregarSecaoInvestidor() {
  const container = document.getElementById('investorContainer');
  container.innerHTML = '<p style="color:#666">Carregando...</p>';
  try {
    const { ok, data } = await apiFetch('/investor/get.php');
    if (ok && data.success && data.profile) {
      exibirResultadoInvestidor(data.profile);
    } else {
      iniciarQuizInvestidor();
    }
  } catch {
    container.innerHTML = '<p style="color:#c0392b">Erro ao carregar perfil.</p>';
  }
}

function iniciarQuizInvestidor() {
  respostasQuiz = {};
  perguntaAtual = 0;
  exibirPergunta(0);
}

function exibirPergunta(indice) {
  const container = document.getElementById('investorContainer');
  const q         = PERGUNTAS_INVESTIDOR[indice];
  const total     = PERGUNTAS_INVESTIDOR.length;

  container.innerHTML = `
    <div class="investor-quiz">
      <div class="quiz-progress">
        <span class="quiz-step">Pergunta ${indice + 1} de ${total}</span>
        <div class="quiz-progress-bar">
          <div class="quiz-progress-fill" style="width:${((indice) / total) * 100}%"></div>
        </div>
      </div>
      <div class="quiz-card">
        <h3 class="quiz-question">${esc(q.texto)}</h3>
        <div class="quiz-options">
          ${q.opcoes.map(o => `
            <button class="quiz-option" onclick="selecionarResposta('${q.id}', '${o.valor}', this)">
              <span class="option-letter">${o.valor.toUpperCase()}</span>
              ${esc(o.texto)}
            </button>
          `).join('')}
        </div>
        <div class="quiz-nav">
          ${indice > 0 ? `<button class="btn btn-secondary" onclick="voltarPergunta(${indice})">Anterior</button>` : '<span></span>'}
          <button class="btn btn-primary" id="nextBtn" disabled onclick="avancarPergunta(${indice})">
            ${indice === total - 1 ? 'Ver meu perfil' : 'Proxima'}
          </button>
        </div>
      </div>
    </div>
  `;

  // Se já respondeu esta pergunta antes, re-seleciona a opção escolhida
  if (respostasQuiz[q.id]) {
    const jaSelecionado = container.querySelector(`.quiz-option[onclick*="'${respostasQuiz[q.id]}'"]`);
    if (jaSelecionado) {
      jaSelecionado.classList.add('selected');
      document.getElementById('nextBtn').disabled = false;
    }
  }
}

function selecionarResposta(idPergunta, valor, btn) {
  // Remove seleção dos outros botões da mesma pergunta
  btn.closest('.quiz-options').querySelectorAll('.quiz-option').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  respostasQuiz[idPergunta] = valor;
  document.getElementById('nextBtn').disabled = false;
}

function voltarPergunta(indiceAtual) {
  exibirPergunta(indiceAtual - 1);
}

function avancarPergunta(indiceAtual) {
  const q = PERGUNTAS_INVESTIDOR[indiceAtual];
  if (!respostasQuiz[q.id]) return;

  if (indiceAtual < PERGUNTAS_INVESTIDOR.length - 1) {
    exibirPergunta(indiceAtual + 1);
  } else {
    enviarQuiz();
  }
}

async function enviarQuiz() {
  const container = document.getElementById('investorContainer');
  container.innerHTML = '<p style="color:#666">Calculando seu perfil...</p>';
  try {
    const { ok, data } = await apiFetch('/investor/save.php', {
      method: 'POST',
      body: JSON.stringify({ respostas: respostasQuiz }),
    });
    if (!ok || !data.success) {
      exibirToast(data.message || 'Erro ao salvar.', 'error');
      iniciarQuizInvestidor();
      return;
    }
    // Recarrega para exibir o resultado salvo
    const { ok: ok2, data: data2 } = await apiFetch('/investor/get.php');
    if (ok2 && data2.success && data2.profile) {
      exibirResultadoInvestidor(data2.profile);
    }
    carregarPerfilNoDashboard();
    exibirToast('Perfil salvo com sucesso!', 'success');
  } catch {
    exibirToast('Erro de conexao.', 'error');
    iniciarQuizInvestidor();
  }
}

function exibirResultadoInvestidor(perfil) {
  const container = document.getElementById('investorContainer');
  const rec       = RECOMENDACOES_PERFIL[perfil.perfil];
  const rotulos   = { conservador: 'Conservador', moderado: 'Moderado', agressivo: 'Agressivo' };

  container.innerHTML = `
    <div class="investor-result">
      <div class="investor-perfil-badge" style="border-color:${rec.cor}">
        <span class="investor-perfil-label" style="color:${rec.cor}">${rotulos[perfil.perfil]}</span>
        <span class="investor-perfil-sub">Seu perfil de investidor</span>
        <span class="investor-perfil-score">Pontuacao: ${perfil.pontuacao}/15</span>
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
        <button class="btn btn-secondary" onclick="iniciarQuizInvestidor()">Refazer o teste</button>
      </div>
    </div>
  `;
}

// ============================================================
// PERFIL DO USUÁRIO
// ============================================================

function carregarPerfil() {
  const usuario = obterUsuarioAtual();
  if (!usuario) return;
  document.getElementById('profileNome').value  = usuario.nome;
  document.getElementById('profileEmail').value = usuario.email;
}

function inicializarFormularioPerfil() {
  const form = document.getElementById('profileForm');
  if (!form) return;
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const nome  = document.getElementById('profileNome').value.trim();
    const email = document.getElementById('profileEmail').value.trim();
    const senha = document.getElementById('profileSenha').value.trim();
    const conf  = document.getElementById('profileConfirm').value.trim();
    const btn   = form.querySelector('[type=submit]');
    if (senha && senha !== conf) { exibirToast('As senhas nao coincidem.', 'error'); return; }
    btn.disabled = true;
    btn.textContent = 'Salvando...';
    const corpo = { id: usuarioAtual.id, nome, email };
    if (senha) corpo.senha = senha;
    try {
      const { ok, data } = await apiFetch('/users/update.php', {
        method: 'POST',
        body: JSON.stringify(corpo),
      });
      if (!ok || !data.success) { exibirToast(data.message, 'error'); return; }
      usuarioAtual.nome  = nome;
      usuarioAtual.email = email;
      definirUsuarioAtual(usuarioAtual);
      document.getElementById('userName').textContent = nome;
      document.getElementById('profileSenha').value   = '';
      document.getElementById('profileConfirm').value = '';
      exibirToast('Perfil atualizado.', 'success');
    } catch {
      exibirToast('Erro de conexao.', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Salvar';
    }
  });
}

// ============================================================
// UTILITÁRIOS
// ============================================================

function labelDoNivel(nivel) {
  return { basico: 'Basico', intermediario: 'Intermediario', avancado: 'Avancado' }[nivel] || nivel;
}

function esc(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ============================================================
// SIMULADOR DE INVESTIMENTOS
// ============================================================

// Tipos disponíveis — label e taxa de referência para exibição
const TIPOS_INVESTIMENTO = [
  { id: 'savings', label: 'Poupança',    rate: '0,5% a.m.' },
  { id: 'cdb',     label: 'CDB',         rate: '0,8% a.m.' },
  { id: 'stocks',  label: 'Ações',       rate: '1,2% a.m.' },
  { id: 'crypto',  label: 'Criptomoeda', rate: '2,0% a.m.' },
];

// Tipos recomendados por perfil de investidor
const MAPA_PERFIL = {
  conservador: ['savings', 'cdb'],
  moderado:    ['cdb', 'stocks'],
  agressivo:   ['stocks', 'crypto'],
};

// Nome e cor de destaque de cada perfil
const META_PERFIL = {
  conservador: { label: 'Conservador', color: '#6fcf6f' },
  moderado:    { label: 'Moderado',    color: '#e0b84a' },
  agressivo:   { label: 'Agressivo',   color: '#e06f6f' },
};

// IDs válidos — usados na validação antes de enviar para a API
const IDS_VALIDOS = new Set(TIPOS_INVESTIMENTO.map(t => t.id));

// Mapa de labels usado na tabela de histórico
const ROTULOS_TIPO = Object.fromEntries(
  TIPOS_INVESTIMENTO.map(t => [t.id, t.label])
);

let graficoSimulador    = null;   // instância do gráfico — destruída e recriada a cada simulação
let simuladorPronto     = false;  // garante que o submit é registrado só uma vez
let perfilEmCache       = null;   // último perfil buscado da API
let usuarioEscolheuTipo = false;  // true quando o usuário clica manualmente num card

// Ponto de entrada — chamado por exibirSecao('simulator')
async function carregarSimulador() {
  inicializarFormularioSimulador();

  // Renderiza os cards imediatamente com o perfil em cache (se existir),
  // assim o formulário já fica funcional enquanto a API responde
  const recomendadosCache = perfilEmCache
    ? (MAPA_PERFIL[perfilEmCache.perfil] ?? [])
    : [];
  renderizarCardsTipoInvestimento(recomendadosCache);

  // Busca o perfil atualizado do servidor
  let perfil = null;
  try {
    const { ok, data } = await apiFetch('/investor/get.php');
    if (ok && data.success) perfil = data.profile ?? null;
  } catch {
    // Se a rede falhar, o simulador continua funcionando sem personalização
  }

  const novoPerfil   = perfil?.perfil ?? null;
  const perfilAntigo = perfilEmCache?.perfil ?? null;

  perfilEmCache = perfil;

  if (novoPerfil !== perfilAntigo) {
    // Perfil mudou (ou é a primeira carga): aplica tudo
    aplicarPerfilSimulador(perfil);
  } else {
    // Perfil igual: só atualiza o banner, sem reconstruir os cards
    // (reconstruir os cards resetaria a escolha manual do usuário)
    const recomendados = MAPA_PERFIL[novoPerfil] ?? [];
    renderizarBannerPerfil(novoPerfil, recomendados);
  }

  carregarHistoricoSimulacoes();
}

// Atualiza o banner e a ordem dos cards com base no perfil do usuário
function aplicarPerfilSimulador(perfil) {
  const nomePerfil   = perfil?.perfil ?? null;
  const recomendados = MAPA_PERFIL[nomePerfil] ?? [];

  renderizarBannerPerfil(nomePerfil, recomendados);
  renderizarCardsTipoInvestimento(recomendados);
}

// Renderiza o banner de recomendação acima do simulador
// Sem perfil: mostra aviso + botão para fazer o quiz
// Com perfil: mostra nome do perfil e investimentos recomendados
function renderizarBannerPerfil(nomePerfil, recomendados) {
  const banner = document.getElementById('simProfileBanner');
  if (!banner) return;

  banner.style.display = '';

  if (!nomePerfil) {
    banner.className = 'sim-profile-banner sim-profile-banner--empty';
    banner.style.borderLeftColor = '';
    banner.innerHTML = `
      <span class="sim-profile-banner__msg">
        Você ainda não definiu seu Perfil de Investidor.
        Complete o quiz para receber recomendações personalizadas.
      </span>
      <button class="btn btn-secondary btn-sm" onclick="exibirSecao('investor')">
        Fazer quiz agora
      </button>
    `;
    return;
  }

  const meta = META_PERFIL[nomePerfil] ?? { label: nomePerfil, color: '#7b9cff' };

  // Monta o texto "CDB e Ações" a partir dos IDs recomendados
  const nomesRec = recomendados
    .map(id => TIPOS_INVESTIMENTO.find(t => t.id === id)?.label ?? id)
    .map(nome => `<strong>${esc(nome)}</strong>`);

  let textoRec;
  if (nomesRec.length === 0) {
    textoRec = 'nenhum definido';
  } else if (nomesRec.length === 1) {
    textoRec = nomesRec[0];
  } else {
    textoRec = nomesRec.slice(0, -1).join(', ') + ' e ' + nomesRec[nomesRec.length - 1];
  }

  banner.className = 'sim-profile-banner sim-profile-banner--active';
  banner.style.borderLeftColor = meta.color;

  banner.innerHTML = `
    <div class="sim-profile-banner__body">
      <span class="sim-profile-banner__profile">
        Seu perfil:&nbsp;<strong id="simBannerProfileLabel">${esc(meta.label)}</strong>
      </span>
      <span class="sim-profile-banner__rec">
        Recomendamos para você: ${textoRec}
      </span>
      <span class="sim-profile-banner__hint">
        Os investimentos recomendados aparecem destacados abaixo.
        Fique à vontade para simular qualquer opção.
      </span>
    </div>
    <button class="btn btn-secondary btn-sm sim-profile-banner__cta"
            onclick="exibirSecao('investor')">
      Rever perfil
    </button>
  `;

  // Aplica a cor via DOM — evita injetar style direto no innerHTML
  const labelEl = document.getElementById('simBannerProfileLabel');
  if (labelEl) labelEl.style.color = meta.color;
}

// Renderiza os cards de tipo de investimento
// Os recomendados aparecem primeiro, com badge "Recomendado"
function renderizarCardsTipoInvestimento(recomendados) {
  const container   = document.getElementById('simTypeCards');
  const inputHidden = document.getElementById('simType');
  if (!container || !inputHidden) return;

  // Recomendados primeiro, depois o restante (ordem estável dentro de cada grupo)
  const ordenados = [
    ...TIPOS_INVESTIMENTO.filter(t =>  recomendados.includes(t.id)),
    ...TIPOS_INVESTIMENTO.filter(t => !recomendados.includes(t.id)),
  ];

  // Se o usuário já escolheu um tipo manualmente, mantemos a escolha dele.
  // Caso contrário, selecionamos o primeiro recomendado (ou o primeiro da lista).
  const idAnterior = inputHidden.value;
  const idAtivo    = (usuarioEscolheuTipo && IDS_VALIDOS.has(idAnterior))
    ? idAnterior
    : (ordenados[0]?.id ?? 'savings');

  inputHidden.value = idAtivo;

  container.innerHTML = ordenados.map(t => {
    const ehRec   = recomendados.includes(t.id);
    const ehAtivo = t.id === idAtivo;
    return `
      <button type="button"
              class="sim-type-card${ehRec ? ' sim-type-card--rec' : ''}${ehAtivo ? ' sim-type-card--active' : ''}"
              data-type="${t.id}"
              title="${ehRec ? 'Recomendado para o seu perfil' : ''}"
              onclick="selecionarTipoInvestimento(this, '${t.id}')">
        <span class="sim-type-card__label">${esc(t.label)}</span>
        <span class="sim-type-card__rate">${esc(t.rate)}</span>
        ${ehRec ? '<span class="sim-type-card__badge">Recomendado</span>' : ''}
      </button>
    `;
  }).join('');
}

// Chamado quando o usuário clica num card de investimento
function selecionarTipoInvestimento(btn, idTipo) {
  usuarioEscolheuTipo = true; // respeita a escolha do usuário nos próximos renders

  const inputHidden = document.getElementById('simType');
  if (inputHidden) inputHidden.value = idTipo;

  document.querySelectorAll('.sim-type-card').forEach(c => c.classList.remove('sim-type-card--active'));
  btn.classList.add('sim-type-card--active');
}

// Registra o evento de submit no formulário — executa apenas uma vez
function inicializarFormularioSimulador() {
  if (simuladorPronto) return;
  const form = document.getElementById('simulatorForm');
  if (!form) return;
  form.addEventListener('submit', executarSimulacao);
  simuladorPronto = true;
}

async function executarSimulacao(e) {
  e.preventDefault();

  const capital = parseFloat(document.getElementById('simCapital').value) || 0;
  const tipo    = document.getElementById('simType').value;
  const periodo = parseInt(document.getElementById('simPeriod').value, 10) || 0;
  const aporte  = parseFloat(document.getElementById('simContrib').value) || 0;

  // Validações no frontend — o servidor valida novamente por segurança
  if (!IDS_VALIDOS.has(tipo)) {
    exibirToast('Selecione um tipo de investimento.', 'error');
    return;
  }
  if (capital < 0 || aporte < 0) {
    exibirToast('Valores não podem ser negativos.', 'error');
    return;
  }
  if (capital === 0 && aporte === 0) {
    exibirToast('Informe um capital inicial ou aporte mensal.', 'error');
    return;
  }
  if (!periodo || periodo < 1 || periodo > 600) {
    exibirToast('Período inválido (1–600 meses).', 'error');
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
        investment_type:      tipo,
        period_months:        periodo,
        monthly_contribution: aporte,
      }),
    });

    if (!ok || !data.success) {
      exibirToast(data.message || 'Erro ao simular.', 'error');
      return;
    }

    exibirResultados(data, periodo);
    carregarHistoricoSimulacoes();
  } catch {
    exibirToast('Erro de conexão.', 'error');
  } finally {
    btn.disabled    = false;
    btn.textContent = 'Simular';
  }
}

function exibirResultados(data, periodo) {
  document.getElementById('simulatorResults').style.display = 'block';

  document.getElementById('simResInvested').textContent = formatarMoeda(data.totalInvested);
  document.getElementById('simResProfit').textContent   = formatarMoeda(data.totalProfit);
  document.getElementById('simResFinal').textContent    = formatarMoeda(data.finalAmount);

  renderizarGrafico(data.evolution, data.investedEvol, periodo);
}

function renderizarGrafico(evolucao, evolucaoInvestido, periodo) {
  const canvas = document.getElementById('simulatorChart');
  if (!canvas) return;

  const rotulos = Array.from({ length: periodo }, (_, i) => `Mês ${i + 1}`);

  // Destrói o gráfico anterior antes de criar um novo — o Chart.js empilharia instâncias
  if (graficoSimulador) {
    graficoSimulador.destroy();
    graficoSimulador = null;
  }

  graficoSimulador = new Chart(canvas, {
    type: 'line',
    data: {
      labels: rotulos,
      datasets: [
        {
          label: 'Patrimônio Total',
          data: evolucao,
          borderColor: '#4a6cf7',
          backgroundColor: 'rgba(74, 108, 247, 0.12)',
          fill: true,
          tension: 0.4,
          pointRadius: periodo <= 60 ? 3 : 0,
          pointHoverRadius: 5,
          borderWidth: 2,
        },
        {
          label: 'Total Investido',
          data: evolucaoInvestido,
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
            label: ctx => ` ${ctx.dataset.label}: ${formatarMoeda(ctx.parsed.y)}`,
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
            callback: val => formatarMoedaAbreviada(val),
          },
          grid: { color: '#1e1e1e' },
        },
      },
    },
  });
}

async function carregarHistoricoSimulacoes() {
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
        <td>${esc(ROTULOS_TIPO[s.investment_type] || s.investment_type)}</td>
        <td>${formatarMoeda(s.initial_capital)}</td>
        <td>${formatarMoeda(s.monthly_contribution)}</td>
        <td>${s.period_months} meses</td>
        <td style="color:#7b9cff">${formatarMoeda(s.final_amount)}</td>
        <td style="color:#27ae60">+${formatarMoeda(s.total_profit)}</td>
        <td style="color:#666">${formatarData(s.created_at)}</td>
      </tr>
    `).join('');
  } catch {
    card.style.display = 'none';
  }
}

function formatarMoeda(valor) {
  return Number(valor).toLocaleString('pt-BR', {
    style:    'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
  });
}

function formatarMoedaAbreviada(valor) {
  if (valor >= 1_000_000) return 'R$ ' + (valor / 1_000_000).toFixed(1) + 'M';
  if (valor >= 1_000)     return 'R$ ' + (valor / 1_000).toFixed(1) + 'k';
  return 'R$ ' + Number(valor).toFixed(0);
}
