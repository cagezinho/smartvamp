const API_BASE = 'api/';
let usuario = null;
let conversaAtual = null;
let intervalos = [];

// Verificar autenticação ao carregar
if (window.location.pathname.includes('app.html')) {
    verificarAuth();
}

// ========== AUTENTICAÇÃO ==========
async function verificarAuth() {
    try {
        const res = await fetch(`${API_BASE}auth.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'verificar' })
        });
        const data = await res.json();
        
        if (data.autenticado) {
            usuario = data.usuario;
            aplicarTema(usuario.tema);
            if (usuario.is_admin) {
                document.getElementById('admin-link').style.display = 'block';
            }
            atualizarStatusBar();
            carregarHome();
        } else {
            window.location.href = 'index.html';
        }
    } catch (error) {
        console.error('Erro ao verificar auth:', error);
        window.location.href = 'index.html';
    }
}

async function fazerLogin() {
    const senha = document.getElementById('login-senha').value;
    const erroDiv = document.getElementById('login-erro');
    
    if (!senha) {
        erroDiv.textContent = 'Digite a senha';
        return;
    }
    
    try {
        const res = await fetch(`${API_BASE}auth.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'login', senha })
        });
        const data = await res.json();
        
        if (data.sucesso) {
            window.location.href = 'app.html';
        } else {
            erroDiv.textContent = data.erro || 'Senha incorreta';
            // Limpar campo após erro
            setTimeout(() => {
                document.getElementById('login-senha').value = '';
                document.getElementById('login-senha').focus();
            }, 1000);
        }
    } catch (error) {
        erroDiv.textContent = 'Erro de conexão';
    }
}

function logout() {
    fetch(`${API_BASE}auth.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ acao: 'logout' })
    }).then(() => {
        window.location.href = 'index.html';
    });
}

// ========== NAVEGAÇÃO ==========
function toggleMenu() {
    const menu = document.getElementById('menu-lateral');
    menu.classList.toggle('aberto');
}

function abrirApp(app) {
    document.querySelectorAll('.app-tela').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
    
    const tela = document.getElementById(`app-${app}`);
    if (tela) {
        tela.classList.add('active');
        document.querySelector(`[onclick="abrirApp('${app}')"]`).classList.add('active');
    }
    
    toggleMenu();
    document.getElementById('app-titulo').textContent = tela.querySelector('h2')?.textContent || 'SMARTVAMP';
    
    // Carregar dados específicos
    if (app === 'home') carregarHome();
    if (app === 'banco') carregarBanco();
    if (app === 'contatos') carregarContatos();
    if (app === 'chat') carregarChat();
    if (app === 'inventario') carregarInventario();
    if (app === 'noticias') carregarNoticias();
    if (app === 'relogio') carregarRelogio();
}

// ========== HOME ==========
async function carregarHome() {
    try {
        // Carregar saldo
        const resBanco = await fetch(`${API_BASE}banco.php`);
        const dataBanco = await resBanco.json();
        document.getElementById('home-saldo').textContent = 
            `R$ ${dataBanco.saldo.toFixed(2).replace('.', ',')}`;
        
        // Carregar relógio
        const resRelogio = await fetch(`${API_BASE}relogio.php`);
        const dataRelogio = await resRelogio.json();
        atualizarRelogioHome(dataRelogio);
        
        // Carregar notificações (mensagens não lidas)
        const resChat = await fetch(`${API_BASE}chat.php`);
        const dataChat = await resChat.json();
        const nao_lidas = dataChat.conversas.filter(c => c.nao_lidas > 0).length;
        
        const notifDiv = document.getElementById('home-notificacoes');
        if (nao_lidas > 0) {
            notifDiv.innerHTML = `<p style="color: #4ecdc4; font-weight: 700;">${nao_lidas} mensagem(ns) não lida(s)</p>`;
        } else {
            notifDiv.innerHTML = '<p style="color: rgba(255,255,255,0.5);">Nenhuma notificação</p>';
        }
    } catch (error) {
        console.error('Erro ao carregar home:', error);
    }
}

function atualizarRelogioHome(data) {
    const display = document.getElementById('home-relogio');
    if (data.modo === 'normal') {
        const agora = new Date();
        display.textContent = agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    } else {
        const dataHora = new Date(data.data + ' ' + data.hora);
        display.textContent = dataHora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }
}

// ========== BANCO ==========
async function carregarBanco() {
    try {
        const res = await fetch(`${API_BASE}banco.php`);
        const data = await res.json();
        
        document.getElementById('banco-saldo-valor').textContent = 
            `R$ ${data.saldo.toFixed(2).replace('.', ',')}`;
        
        const container = document.getElementById('transacoes-lista');
        if (data.transacoes.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 20px;">Nenhuma transação</p>';
        } else {
            container.innerHTML = data.transacoes.map(t => `
                <div class="transacao-item">
                    <div class="transacao-info">
                        <h4>${t.descricao || 'Transação'}</h4>
                        <p>${t.pix_nome ? t.pix_nome + ' | ' : ''}${new Date(t.criado_em).toLocaleString('pt-BR')}</p>
                    </div>
                    <div class="transacao-valor ${t.tipo}">
                        ${t.tipo === 'entrada' ? '+' : '-'} R$ ${parseFloat(t.valor).toFixed(2).replace('.', ',')}
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Erro ao carregar banco:', error);
    }
}

function abrirPix(tipo) {
    const modal = document.getElementById('modal-pix');
    const form = document.getElementById('form-pix');
    const titulo = document.getElementById('modal-pix-titulo');
    
    form.reset();
    document.getElementById('pix-tipo').value = tipo;
    titulo.textContent = tipo === 'enviar' ? 'Enviar PIX' : 'Receber PIX';
    
    if (tipo === 'receber') {
        document.getElementById('pix-chave').placeholder = 'Sua chave PIX';
        document.getElementById('pix-nome').placeholder = 'Seu nome';
    }
    
    modal.classList.add('active');
}

document.getElementById('form-pix').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const tipo = document.getElementById('pix-tipo').value;
    const data = {
        acao: 'pix',
        tipo: tipo === 'enviar' ? 'enviar' : 'receber',
        valor: document.getElementById('pix-valor').value,
        chave: document.getElementById('pix-chave').value,
        nome: document.getElementById('pix-nome').value,
        descricao: document.getElementById('pix-descricao').value || 
                   (tipo === 'enviar' ? 'PIX enviado' : 'PIX recebido')
    };
    
    try {
        const res = await fetch(`${API_BASE}banco.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        
        if (result.sucesso) {
            alert(result.mensagem);
            fecharModal('modal-pix');
            carregarBanco();
            carregarHome();
        } else {
            alert('Erro: ' + result.erro);
        }
    } catch (error) {
        alert('Erro ao processar PIX');
    }
});

// ========== CONTATOS ==========
async function carregarContatos() {
    try {
        const res = await fetch(`${API_BASE}contatos.php`);
        const data = await res.json();
        
        // Carregar grupos no filtro
        const selectFiltro = document.getElementById('filtro-grupo');
        selectFiltro.innerHTML = '<option value="">Todos os grupos</option>' +
            data.grupos.map(g => `<option value="${g.id}">${g.nome}</option>`).join('');
        
        // Carregar grupos no modal
        const selectModal = document.getElementById('contato-grupo');
        selectModal.innerHTML = '<option value="">Sem grupo</option>' +
            data.grupos.map(g => `<option value="${g.id}">${g.nome}</option>`).join('');
        
        filtrarContatos(data.contatos);
    } catch (error) {
        console.error('Erro ao carregar contatos:', error);
    }
}

function filtrarContatos(contatos) {
    const grupoId = document.getElementById('filtro-grupo').value;
    const filtrados = grupoId ? contatos.filter(c => c.grupo_id == grupoId) : contatos;
    
    const container = document.getElementById('contatos-lista');
    if (filtrados.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 20px;">Nenhum contato</p>';
    } else {
        container.innerHTML = filtrados.map(c => `
            <div class="contato-item" onclick="abrirChatContato(${c.id}, '${c.nome}', '${c.avatar || ''}')">
                <div class="contato-avatar">${c.nome.charAt(0).toUpperCase()}</div>
                <div class="contato-info">
                    <h4>${c.nome}</h4>
                    <p>${c.telefone || ''} ${c.profissao ? '| ' + c.profissao : ''}</p>
                </div>
                ${c.grupo_nome ? `<span class="contato-grupo">${c.grupo_nome}</span>` : ''}
            </div>
        `).join('');
    }
}

function abrirModalContato(id = null) {
    const modal = document.getElementById('modal-contato');
    const form = document.getElementById('form-contato');
    const titulo = document.getElementById('modal-contato-titulo');
    
    form.reset();
    document.getElementById('contato-id').value = id || '';
    titulo.textContent = id ? 'Editar Contato' : 'Novo Contato';
    
    modal.classList.add('active');
}

document.getElementById('form-contato').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const id = document.getElementById('contato-id').value;
    const data = {
        acao: id ? 'editar' : 'adicionar',
        id: id || undefined,
        nome: document.getElementById('contato-nome').value,
        telefone: document.getElementById('contato-telefone').value,
        grupo_id: document.getElementById('contato-grupo').value || null,
        endereco: document.getElementById('contato-endereco').value,
        profissao: document.getElementById('contato-profissao').value,
        notas: document.getElementById('contato-notas').value
    };
    
    try {
        const res = await fetch(`${API_BASE}contatos.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        
        if (result.sucesso) {
            fecharModal('modal-contato');
            carregarContatos();
        } else {
            alert('Erro: ' + result.erro);
        }
    } catch (error) {
        alert('Erro ao salvar contato');
    }
});

// ========== CHAT ==========
async function carregarChat() {
    try {
        const res = await fetch(`${API_BASE}chat.php`);
        const data = await res.json();
        
        const container = document.getElementById('chat-lista');
        if (data.conversas.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 20px;">Nenhuma conversa</p>';
        } else {
            container.innerHTML = data.conversas.map(c => `
                <div class="conversa-item ${c.nao_lidas > 0 ? 'nao-lidas' : ''}" 
                     onclick="abrirConversa(${c.id}, '${c.nome_contato}', '${c.avatar_contato || ''}')">
                    <div class="conversa-avatar">${c.nome_contato.charAt(0).toUpperCase()}</div>
                    <div class="conversa-info">
                        <h4>${c.nome_contato}</h4>
                        <p>${c.ultima_mensagem || 'Nenhuma mensagem'}</p>
                    </div>
                    ${c.nao_lidas > 0 ? `<div class="conversa-badge">${c.nao_lidas}</div>` : ''}
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Erro ao carregar chat:', error);
    }
}

async function abrirChatContato(contato_id, nome, avatar) {
    try {
        // Criar ou buscar conversa
        const res = await fetch(`${API_BASE}chat.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'criar_conversa',
                contato_id,
                nome_contato: nome,
                avatar_contato: avatar
            })
        });
        const data = await res.json();
        
        if (data.sucesso) {
            abrirConversa(data.id, nome, avatar);
        }
    } catch (error) {
        console.error('Erro ao criar conversa:', error);
    }
}

async function abrirConversa(conversa_id, nome, avatar) {
    conversaAtual = conversa_id;
    
    // Criar tela de conversa dinamicamente
    const content = document.getElementById('app-content');
    let chatTela = document.getElementById('chat-tela-conversa');
    
    if (!chatTela) {
        chatTela = document.createElement('div');
        chatTela.id = 'chat-tela-conversa';
        chatTela.className = 'chat-tela';
        chatTela.innerHTML = `
            <div class="chat-header">
                <button onclick="fecharConversa()" class="btn-voltar">←</button>
                <h3>${nome}</h3>
            </div>
            <div class="chat-mensagens" id="chat-mensagens"></div>
            <div class="chat-input-area">
                <input type="text" id="chat-input-text" class="chat-input" placeholder="Digite uma mensagem...">
                <button onclick="enviarMensagem()" class="btn-enviar">→</button>
            </div>
        `;
        content.appendChild(chatTela);
        
        // Enter para enviar
        document.getElementById('chat-input-text').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') enviarMensagem();
        });
    }
    
    document.querySelectorAll('.app-tela').forEach(t => t.classList.remove('active'));
    chatTela.classList.add('active');
    
    await carregarMensagens();
    
    // Auto-refresh mensagens
    if (intervalos['chat']) clearInterval(intervalos['chat']);
    intervalos['chat'] = setInterval(carregarMensagens, 3000);
}

function fecharConversa() {
    const chatTela = document.getElementById('chat-tela-conversa');
    if (chatTela) {
        chatTela.classList.remove('active');
        document.getElementById('app-chat').classList.add('active');
    }
    conversaAtual = null;
    if (intervalos['chat']) clearInterval(intervalos['chat']);
}

async function carregarMensagens() {
    if (!conversaAtual) return;
    
    try {
        const res = await fetch(`${API_BASE}chat.php?conversa_id=${conversaAtual}`);
        const data = await res.json();
        
        const container = document.getElementById('chat-mensagens');
        container.innerHTML = data.mensagens.map(m => `
            <div class="mensagem ${m.remetente}">
                ${m.conteudo}
                <div class="mensagem-hora">${new Date(m.criado_em).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</div>
            </div>
        `).join('');
        
        container.scrollTop = container.scrollHeight;
    } catch (error) {
        console.error('Erro ao carregar mensagens:', error);
    }
}

async function enviarMensagem() {
    const input = document.getElementById('chat-input-text');
    const conteudo = input.value.trim();
    
    if (!conteudo || !conversaAtual) return;
    
    try {
        const res = await fetch(`${API_BASE}chat.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'enviar',
                conversa_id: conversaAtual,
                conteudo
            })
        });
        const result = await res.json();
        
        if (result.sucesso) {
            input.value = '';
            await carregarMensagens();
        }
    } catch (error) {
        console.error('Erro ao enviar mensagem:', error);
    }
}

// ========== INVENTÁRIO ==========
async function carregarInventario() {
    try {
        const res = await fetch(`${API_BASE}inventario.php`);
        const data = await res.json();
        
        const container = document.getElementById('inventario-lista');
        const categorias = Object.keys(data.inventario);
        
        if (categorias.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 20px;">Inventário vazio</p>';
        } else {
            container.innerHTML = categorias.map(cat => `
                <div class="categoria-inventario">
                    <div class="categoria-titulo">${cat || 'Outros'}</div>
                    ${data.inventario[cat].map(item => `
                        <div class="item-inventario">
                            <div class="item-info">
                                <h4>${item.item_nome}</h4>
                                <p>${item.descricao || ''}</p>
                            </div>
                            <div class="item-quantidade">${item.quantidade}x</div>
                        </div>
                    `).join('')}
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Erro ao carregar inventário:', error);
    }
}

function abrirModalItem(id = null) {
    const modal = document.getElementById('modal-item');
    const form = document.getElementById('form-item');
    const titulo = document.getElementById('modal-item-titulo');
    
    form.reset();
    document.getElementById('item-id').value = id || '';
    titulo.textContent = id ? 'Editar Item' : 'Novo Item';
    
    modal.classList.add('active');
}

document.getElementById('form-item').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const id = document.getElementById('item-id').value;
    const data = {
        acao: id ? 'editar' : 'adicionar',
        id: id || undefined,
        item_nome: document.getElementById('item-nome').value,
        categoria: document.getElementById('item-categoria').value,
        quantidade: parseInt(document.getElementById('item-quantidade').value),
        descricao: document.getElementById('item-descricao').value
    };
    
    try {
        const res = await fetch(`${API_BASE}inventario.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        
        if (result.sucesso) {
            fecharModal('modal-item');
            carregarInventario();
        } else {
            alert('Erro: ' + result.erro);
        }
    } catch (error) {
        alert('Erro ao salvar item');
    }
});

// ========== NOTÍCIAS ==========
async function carregarNoticias() {
    try {
        const res = await fetch(`${API_BASE}noticias.php`);
        const data = await res.json();
        
        const container = document.getElementById('noticias-lista');
        if (data.noticias.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 20px;">Nenhuma notícia</p>';
        } else {
            container.innerHTML = data.noticias.map(n => `
                <div class="noticia-item">
                    <h3>${n.titulo}</h3>
                    <p>${n.conteudo}</p>
                    <div class="noticia-meta">
                        <span>${n.autor || 'Anônimo'}</span>
                        <span>${new Date(n.criado_em).toLocaleDateString('pt-BR')}</span>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Erro ao carregar notícias:', error);
    }
}

// ========== RELÓGIO ==========
async function carregarRelogio() {
    try {
        const res = await fetch(`${API_BASE}relogio.php`);
        const data = await res.json();
        
        atualizarRelogioDisplay(data);
        
        // Atualizar a cada segundo
        if (intervalos['relogio']) clearInterval(intervalos['relogio']);
        intervalos['relogio'] = setInterval(async () => {
            const res = await fetch(`${API_BASE}relogio.php`);
            const data = await res.json();
            atualizarRelogioDisplay(data);
        }, 1000);
    } catch (error) {
        console.error('Erro ao carregar relógio:', error);
    }
}

function atualizarRelogioDisplay(data) {
    const display = document.getElementById('relogio-display-grande');
    const dataDisplay = document.getElementById('relogio-data');
    
    if (data.modo === 'normal') {
        const agora = new Date();
        display.textContent = agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        dataDisplay.textContent = agora.toLocaleDateString('pt-BR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    } else {
        const dataHora = new Date(data.data + ' ' + data.hora);
        display.textContent = dataHora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        dataDisplay.textContent = dataHora.toLocaleDateString('pt-BR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }
}

// ========== TEMA ==========
function toggleTema() {
    const novoTema = document.body.classList.contains('tema-claro') ? 'escuro' : 'claro';
    
    fetch(`${API_BASE}tema.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tema: novoTema })
    }).then(() => {
        aplicarTema(novoTema);
    });
}

function aplicarTema(tema) {
    if (tema === 'claro') {
        document.body.classList.add('tema-claro');
    } else {
        document.body.classList.remove('tema-claro');
    }
}

// ========== UTILITÁRIOS ==========
function fecharModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function atualizarStatusBar() {
    const agora = new Date();
    document.getElementById('status-hora').textContent = 
        agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    
    setInterval(() => {
        const agora = new Date();
        document.getElementById('status-hora').textContent = 
            agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }, 1000);
}

function abrirAdmin() {
    window.open('admin/index.php', '_blank');
}

// Limpar intervalos ao sair
window.addEventListener('beforeunload', () => {
    Object.values(intervalos).forEach(interval => clearInterval(interval));
});
