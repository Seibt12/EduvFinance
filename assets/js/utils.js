function formatarMoeda(valor) {
    return 'R$ ' + Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatarData(dataStr) {
    if (!dataStr) return '-';
    const d = new Date(dataStr);
    return d.toLocaleDateString('pt-BR');
}

function exibirToast(mensagem, tipo) {
    const toast = document.createElement('div');
    toast.className = 'alert alert-' + (tipo || 'success');
    toast.textContent = mensagem;
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;max-width:400px';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}
