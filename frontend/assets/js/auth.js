// ============================================================
// AUTH.JS — login, logout, proteção de rotas
//
// Funções disponíveis:
//   verificarSessao(callback)       — verifica sessão, redireciona se inválida
//   verificarPapel(papel, callback) — verifica sessão + papel do usuário
//   logout()                        — encerra sessão e volta ao login
//   inicializarLogin()              — inicializa o formulário de login
//   inicializarCadastro()           — inicializa o formulário de cadastro

// ------------------------------------------------------------
// PROTEÇÃO DE ROTAS — use no topo de cada página protegida
// ------------------------------------------------------------

/**
 * Verifica se existe uma sessão válida no servidor.
 * Se sim: chama callback(usuario) e inicializa a página.
 * Se não: limpa localStorage e redireciona para o login.
 *
 * Uso em student.js / admin.js:
 *   verificarSessao(usuario => { ... inicializa página ... });
 */
async function verificarSessao(callback) {
  try {
    const { ok, data } = await apiFetch('/auth/check.php');

    if (!ok || !data.success) {
      // Sessão inválida ou expirada
      console.warn('[Auth] Sessão inválida:', data?.message);
      limparUsuarioAtual();
      redirecionarLogin();
      return;
    }

    // Sessão válida — atualiza localStorage e inicializa a página
    definirUsuarioAtual(data.user);
    if (callback) callback(data.user);

  } catch (err) {
    // Erro de rede ou servidor fora do ar
    console.error('[Auth] Erro ao verificar sessão:', err.message);
    limparUsuarioAtual();
    redirecionarLogin();
  }
}

/**
 * Verifica sessão E garante que o usuário tem o papel correto.
 *
 * Se sessão inválida → redireciona para o login.
 * Se papel errado (ex: aluno tentando acessar área admin)
 *   → redireciona para o dashboard CORRETO do usuário (não para login).
 *
 * Uso em admin.js:   verificarPapel('admin', usuario => { ... });
 * Uso em student.js: verificarPapel('aluno', usuario => { ... });
 */
async function verificarPapel(papel, callback) {
  await verificarSessao(usuario => {
    if (usuario.tipo !== papel) {
      // Usuário está logado mas no lugar errado — manda para o dashboard dele
      console.warn(`[Auth] Papel incorreto: esperado "${papel}", encontrado "${usuario.tipo}". Redirecionando.`);
      redirecionarDashboard(usuario.tipo);
      return;
    }
    if (callback) callback(usuario);
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
  limparUsuarioAtual();
  redirecionarLogin();
}


// ------------------------------------------------------------
// PÁGINA DE LOGIN
// ------------------------------------------------------------

function inicializarLogin() {
  // Se já há um usuário no localStorage, verifica se a sessão ainda é válida.
  const usuario = obterUsuarioAtual();
  if (usuario) {
    _verificarSessaoAtiva();
    // Não retornamos aqui: o formulário é inicializado normalmente.
    // Se a sessão ainda for válida, o redirect acontece antes de qualquer submit.
  }

  const form = document.getElementById('loginForm');
  if (!form) return;

  form.addEventListener('submit', async e => {
    e.preventDefault();

    const btn   = form.querySelector('[type=submit]');
    const email = document.getElementById('loginEmail').value.trim();
    const senha = document.getElementById('loginSenha').value.trim();

    if (!email || !senha) {
      exibirAlerta('loginAlert', 'Preencha email e senha.');
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
        exibirAlerta('loginAlert', data.message || 'Email ou senha incorretos.');
        return;  // finally reabilita o botão
      }

      // Login OK — salva usuário e redireciona para o dashboard correto
      definirUsuarioAtual(data.user);
      console.info('[Auth] Login OK, tipo:', data.user.tipo);
      redirecionarDashboard(data.user.tipo);
      // Não precisamos reabilitar o botão aqui — a página vai mudar

    } catch (err) {
      console.error('[Auth] Erro no login:', err.message);
      exibirAlerta('loginAlert', 'Erro de conexão. Tente novamente.');
    } finally {
      // Só reabilita se a página NÃO foi redirecionada
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
 * Função interna — não chame diretamente, use inicializarLogin().
 */
async function _verificarSessaoAtiva() {
  try {
    const { ok, data } = await apiFetch('/auth/check.php');
    if (ok && data.success) {
      console.info('[Auth] Sessão ainda válida, redirecionando para dashboard.');
      redirecionarDashboard(data.user.tipo);
    } else {
      limparUsuarioAtual();
    }
  } catch {
    limparUsuarioAtual();
  }
}


// ------------------------------------------------------------
// PÁGINA DE CADASTRO
// ------------------------------------------------------------

function inicializarCadastro() {
  const form = document.getElementById('registerForm');
  if (!form) return;

  form.addEventListener('submit', async e => {
    e.preventDefault();

    const btn   = form.querySelector('[type=submit]');
    const nome  = document.getElementById('registerNome').value.trim();
    const email = document.getElementById('registerEmail').value.trim();
    const senha = document.getElementById('registerSenha').value.trim();
    const conf  = document.getElementById('registerConfirm').value.trim();
    const idade = parseInt(document.getElementById('registerIdade')?.value) || 0;

    // Validação básica no frontend (backend valida de novo por segurança)
    if (!nome || !email || !senha) {
      exibirAlerta('registerAlert', 'Preencha todos os campos obrigatórios.');
      return;
    }

    if (senha !== conf) {
      exibirAlerta('registerAlert', 'As senhas não coincidem.');
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
        exibirAlerta('registerAlert', data.message || 'Erro ao cadastrar.');
        return;
      }

      exibirAlerta('registerAlert', 'Cadastro realizado! Redirecionando...', 'success');
      setTimeout(() => redirecionarLogin(), 1800);

    } catch (err) {
      console.error('[Auth] Erro no cadastro:', err.message);
      exibirAlerta('registerAlert', 'Erro de conexão. Tente novamente.');
    } finally {
      btn.disabled    = false;
      btn.textContent = 'Criar Conta';
    }
  });
}
