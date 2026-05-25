async function verificarSessao(papelEsperado, callback) {
    let data;
    try {
        const resp = await fetch('../php/sessao_check.php', { credentials: 'include' });
        data = await resp.json();
    } catch (err) {
        window.location.replace('../login/index.html');
        return;
    }

    if (!data.autenticado) {
        window.location.replace('../login/index.html');
        return;
    }

    if (papelEsperado && data.tipo !== papelEsperado) {
        let destino;
        if (data.tipo === 'admin') destino = 'index.html';
        else if (data.tipo === 'professor') destino = 'professor.html';
        else destino = 'student.html';
        window.location.replace(destino);
        return;
    }

    if (callback) callback(data);
}

async function logout() {
    await fetch('../php/logout.php', { method: 'POST', credentials: 'include' }).catch(() => {});
    window.location.replace('../login/index.html');
}
