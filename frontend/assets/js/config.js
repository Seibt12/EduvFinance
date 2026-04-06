// URL base da API — detecta automaticamente onde o app está rodando
const BASE_URL = window.location.origin || 'http://localhost:8080';
const API_BASE = `${BASE_URL}/backend/api`;

// Rotas centralizadas — altere aqui se mover os arquivos HTML
// Não repita estas strings em auth.js, student.js, admin.js
const ROUTES = {
  login:   `${BASE_URL}/frontend/login.html`,
  admin:   `${BASE_URL}/frontend/dashboard/admin.html`,
  student: `${BASE_URL}/frontend/dashboard/student.html`,
};


// ------------------------------------------------------------
// apiFetch — a função central para chamar o backend
//
// Uso: const { ok, data } = await apiFetch('/auth/login.php', {
//        method: 'POST',
//        body: JSON.stringify({ email, senha })
//      });
//
// Sempre retorna: { ok: true/false, data: { success, message, ...} }
// ------------------------------------------------------------
async function apiFetch(endpoint, options = {}) {
  // Monta a configuração da requisição com defaults seguros
  const config = {
    credentials: 'include',  // envia o cookie de sessão automaticamente
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'Accept':       'application/json',
      ...(options.headers || {}),
    },
  };

  const response = await fetch(`${API_BASE}${endpoint}`, config);
  const texto = await response.text();

  if (!texto) throw new Error(`Resposta vazia do servidor (HTTP ${response.status}).`);

  let data;
  try {
    data = JSON.parse(texto);
  } catch {
    throw new Error(`Servidor retornou algo que não é JSON (HTTP ${response.status}).`);
  }

  return { ok: response.ok, status: response.status, data };
}


// ------------------------------------------------------------
// NOTIFICAÇÕES — exibe mensagens para o usuário
// ------------------------------------------------------------

// Toast: mensagem flutuante no canto da tela (desaparece sozinha)
function exibirToast(mensagem, tipo = 'info') {
  const toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = mensagem;
  toast.className = `toast ${tipo} show`;
  setTimeout(() => toast.classList.remove('show'), 3500);
}

// Alerta: mensagem vermelha/verde dentro do formulário
function exibirAlerta(elementId, mensagem, tipo = 'error') {
  const el = document.getElementById(elementId);
  if (!el) return;
  el.textContent = mensagem;
  el.className = `alert alert-${tipo} show`;
  setTimeout(() => el.className = 'alert', 5000);
}


// ------------------------------------------------------------
// SESSÃO DO USUÁRIO — salva os dados do usuário logado
// (localStorage persiste entre páginas e recarregamentos)
// ------------------------------------------------------------

function obterUsuarioAtual() {
  try { return JSON.parse(localStorage.getItem('edufinance_user')) || null; }
  catch { return null; }
}

function definirUsuarioAtual(usuario) {
  localStorage.setItem('edufinance_user', JSON.stringify(usuario));
}

function limparUsuarioAtual() {
  localStorage.removeItem('edufinance_user');
}


// ------------------------------------------------------------
// MODAIS — janelas popup (adicionar, editar, confirmar)
// ------------------------------------------------------------

function abrirModal(id) {
  document.getElementById(id)?.classList.add('open');
}

function fecharModal(id) {
  document.getElementById(id)?.classList.remove('open');
}

// Fecha qualquer modal aberto ao apertar ESC
document.addEventListener('keydown', e => {
  if (e.key === 'Escape')
    document.querySelectorAll('.modal.open').forEach(m => m.classList.remove('open'));
});


// ------------------------------------------------------------
// UTILITÁRIOS
// ------------------------------------------------------------

// Usa ROUTES para garantir consistência com todas as outras navegações
function redirecionarLogin() {
  window.location.href = ROUTES.login;
}

// Redireciona para o dashboard correto com base no tipo do usuário
function redirecionarDashboard(tipo) {
  window.location.href = tipo === 'admin' ? ROUTES.admin : ROUTES.student;
}

// Formata datas ISO para pt-BR: "2024-01-15" → "15/01/2024"
function formatarData(dataISO) {
  if (!dataISO) return '-';
  return new Date(dataISO).toLocaleDateString('pt-BR');
}
