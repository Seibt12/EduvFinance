// ============================================================
// AUTH.JS — login, logout, proteção de rotas
//
// Funções disponíveis:
//   requireSession(callback) — verifica sessão, redireciona se inválida
//   requireRole(role, callback) — verifica sessão + papel do usuário
//   logout() — encerra sessão e volta ao login
//   initLoginPage() — inicializa o formulário de login
//   initRegisterPage() — inicializa o formulário de cadastro
// ============================================================


// ------------------------------------------------------------
// PROTEÇÃO DE ROTAS — use no topo de cada página protegida
// ------------------------------------------------------------

/**
 * Verifica se existe uma sessão válida no servidor.
 * Se sim: chama callback(user) e inicializa a página.
 * Se não: limpa localStorage e redireciona para login.
 *
 * Uso em student.js / admin.js:
 *   requireSession(user => { ... inicializa página ... });
 */
async function requireSession(callback) {
  try {
    const { ok, data } = await apiFetch('/auth/check.php');

    if (!ok || !data.success) {
      // Sessão inválida ou expirada
      console.warn('[Auth] Sessão inválida:', data?.message);
      clearCurrentUser();
      redirectToLogin();
      return;
    }

    // Sessão válida — atualiza localStorage e inicializa a página
    setCurrentUser(data.user);
    if (callback) callback(data.user);

  } catch (err) {
    // Erro de rede ou servidor fora do ar
    console.error('[Auth] Erro ao verificar sessão:', err.message);
    clearCurrentUser();
    redirectToLogin();
  }
}

/**
 * Verifica sessão E garante que o usuário tem o papel correto.
 *
 * Se sessão inválida → redireciona para login.
 * Se papel errado (ex: aluno tentando acessar área admin)
 *   → redireciona para o dashboard CORRETO do usuário (não para login).
 *
 * Uso em admin.js:   requireRole('admin', user => { ... });
 * Uso em student.js: requireRole('aluno', user => { ... });
 */
async function requireRole(role, callback) {
  await requireSession(user => {
    if (user.tipo !== role) {
      // Usuário está logado mas no lugar errado — manda para o dashboard dele
      console.warn(`[Auth] Papel incorreto: esperado "${role}", encontrado "${user.tipo}". Redirecionando.`);
      redirectToDashboard(user.tipo);
      return;
    }
    if (callback) callback(user);
  });
}


// ------------------------------------------------------------
// LOGOUT
// ------------------------------------------------------------

async function logout() {
  try {
    await apiFetch('/auth/logout.php', { method: 'POST' });
  } catch (err) {
    // Mesmo que a chamada falhe, limpamos o localStorage
    console.warn('[Auth] Logout backend falhou (ignorando):', err.message);
  }
  clearCurrentUser();
  redirectToLogin();
}


// ------------------------------------------------------------
// PÁGINA DE LOGIN
// ------------------------------------------------------------

function initLoginPage() {
  // Se já há um usuário no localStorage, verifica se a sessão ainda é válida.
  // Enquanto verifica, desabilita o formulário para evitar duplo submit.
  const user = getCurrentUser();
  if (user) {
    _checkAndRedirectIfLoggedIn();
    // Não retornamos aqui: o formulário é inicializado normalmente.
    // Se a sessão ainda for válida, o redirect vai acontecer antes de qualquer submit.
  }

  const form = document.getElementById('loginForm');
  if (!form) return;

  form.addEventListener('submit', async e => {
    e.preventDefault();

    const btn   = form.querySelector('[type=submit]');
    const email = document.getElementById('loginEmail').value.trim();
    const senha = document.getElementById('loginSenha').value.trim();

    if (!email || !senha) {
      showAlert('loginAlert', 'Preencha email e senha.');
      return;
    }

    // Desabilita o botão para evitar duplo clique
    btn.disabled    = true;
    btn.textContent = 'Entrando...';

    try {
      const { ok, data } = await apiFetch('/auth/login.php', {
        method: 'POST',
        body:   JSON.stringify({ email, senha }),
      });

      if (!ok || !data.success) {
        showAlert('loginAlert', data.message || 'Email ou senha incorretos.');
        return;  // finally reabilita o botão
      }

      // Login OK — salva usuário e redireciona para o dashboard correto
      setCurrentUser(data.user);
      console.info('[Auth] Login OK, tipo:', data.user.tipo);
      redirectToDashboard(data.user.tipo);
      // Não precisamos reabilitar o botão aqui — a página vai mudar

    } catch (err) {
      console.error('[Auth] Erro no login:', err.message);
      showAlert('loginAlert', 'Erro de conexão. Tente novamente.');
    } finally {
      // Só reabilita se a página NÃO foi redirecionada
      // (se redirecionou, este código roda mas não importa)
      btn.disabled    = false;
      btn.textContent = 'Entrar';
    }
  });
}

/**
 * Verifica silenciosamente se a sessão ainda é válida.
 * Se sim, redireciona para o dashboard correto.
 * Se não, apenas limpa o localStorage (não redireciona — usuário já está no login).
 *
 * Função interna — não chame diretamente, use initLoginPage().
 */
async function _checkAndRedirectIfLoggedIn() {
  try {
    const { ok, data } = await apiFetch('/auth/check.php');
    if (ok && data.success) {
      console.info('[Auth] Sessão ainda válida, redirecionando para dashboard.');
      redirectToDashboard(data.user.tipo);
    } else {
      clearCurrentUser();
    }
  } catch {
    clearCurrentUser();
  }
}


// ------------------------------------------------------------
// PÁGINA DE CADASTRO
// ------------------------------------------------------------

function initRegisterPage() {
  const form = document.getElementById('registerForm');
  if (!form) return;

  form.addEventListener('submit', async e => {
    e.preventDefault();

    const btn   = form.querySelector('[type=submit]');
    const nome  = document.getElementById('registerNome').value.trim();
    const email = document.getElementById('registerEmail').value.trim();
    const senha = document.getElementById('registerSenha').value.trim();
    const conf  = document.getElementById('registerConfirm').value.trim();
    // EXEMPLO: campo "idade" — remova esta linha se não quiser o campo
    const idade = parseInt(document.getElementById('registerIdade')?.value) || 0;

    // Validação básica no frontend (backend valida de novo por segurança)
    if (!nome || !email || !senha) {
      showAlert('registerAlert', 'Preencha todos os campos obrigatórios.');
      return;
    }

    if (senha !== conf) {
      showAlert('registerAlert', 'As senhas não coincidem.');
      return;
    }

    btn.disabled    = true;
    btn.textContent = 'Cadastrando...';

    try {
      const { ok, data } = await apiFetch('/auth/register.php', {
        method: 'POST',
        body:   JSON.stringify({ nome, email, senha, idade }),
      });

      if (!ok || !data.success) {
        showAlert('registerAlert', data.message || 'Erro ao cadastrar.');
        return;
      }

      showAlert('registerAlert', 'Cadastro realizado! Redirecionando...', 'success');
      setTimeout(() => redirectToLogin(), 1800);

    } catch (err) {
      console.error('[Auth] Erro no cadastro:', err.message);
      showAlert('registerAlert', 'Erro de conexão. Tente novamente.');
    } finally {
      btn.disabled    = false;
      btn.textContent = 'Criar Conta';
    }
  });
}
