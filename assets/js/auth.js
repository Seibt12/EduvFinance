async function verificarSessao(papelEsperado, callback) {
    try {
        const resp = await fetch('../php/sessao_check.php', { credentials: 'include' });
        const data = await resp.json();

        if (!data.autenticado) {
            window.location.replace('../login/index.html');
            return;
        }

        if (papelEsperado && data.tipo !== papelEsperado) {
            const destino = data.tipo === 'admin' ? 'index.html' : 'student.html';
            window.location.replace(destino);
            return;
        }

        if (callback) callback(data);
    } catch (err) {
        window.location.replace('../login/index.html');
    }
}

async function logout() {
    await fetch('../php/logout.php', { method: 'POST', credentials: 'include' }).catch(() => {});
    window.location.replace('../login/index.html');
}
