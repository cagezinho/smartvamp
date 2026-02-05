/**
 * SmartVamp - Aplicativo Principal
 * Sistema completo de gerenciamento para RPG Vampiro: A Máscara
 */

// ========== CONFIGURAÇÃO ==========
const API_BASE = (() => {
    const path = window.location.pathname;
    const pathParts = path.split('/').filter(p => p);
    if (pathParts.length > 0 && pathParts[pathParts.length - 1].includes('.')) {
        pathParts.pop();
    }
    const basePath = pathParts.length > 0 ? '/' + pathParts.join('/') + '/' : '/';
    return basePath + 'api/';
})();

// ========== ESTADO GLOBAL ==========
let usuario = null;
let conversaAtual = null;
let intervalos = {};
let contatosCache = [];

// ========== INICIALIZAÇÃO ==========
if (window.location.pathname.includes('app.html')) {
    verificarAuth();
}

// ========== AUTENTICAÇÃO ==========
async function verificarAuth() {
    try {
        const res = await fetch(`${API_BASE}auth.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'verificar' }),
            credentials: 'same-origin'
        });
        
        const data = await res.json();
        
        if (data.autenticado) {
            usuario = data.usuario;
            aplicarTema(usuario.tema);
            
            if (usuario.is_admin) {
                const adminIcon = document.getElementById('admin-icon');
                if (adminIcon) adminIcon.style.display = 'flex';
            }
            
            atualizarStatusBar();
            carregarHome();
        } else {
            redirecionarLogin();
        }
    } catch (error) {
        console.error('Erro ao verificar auth:', error);
        redirecionarLogin();
    }
}

function redirecionarLogin() {
    const currentPath = window.location.pathname;
    const basePath = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
    window.location.href = basePath + 'index.html';
}

function logout() {
    fetch(`${API_BASE}auth.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ acao: 'logout' }),
        credentials: 'same-origin'
    }).then(() => {
        redirecionarLogin();
    });
}

// ========== NAVEGAÇÃO ==========
function abrirApp(app) {
    document.querySelectorAll('.app-tela').forEach(t => t.classList.remove('active'));
    
    const tela = document.getElementById(`app-${app}`);
    if (tela) {
        tela.classList.add('active');
    }
    
    // Mostrar/ocultar botão Home
    const homeButton = document.getElementById('home-button-container');
    if (app === 'home') {
        homeButton.style.display = 'none';
    } else {
        homeButton.style.display = 'block';
    }
    
    // Carregar dados específicos
    switch(app) {
        case 'home': carregarHome(); break;
        case 'banco': carregarBanco(); break;
        case 'contatos': carregarContatos(); break;
        case 'chat': carregarChat(); break;
        case 'inventario': carregarInventario(); break;
        case 'noticias': carregarNoticias(); break;
    }
}

function voltarHome() {
    abrirApp('home');
}

// ========== HOME ==========
async function carregarHome() {
    // Home só mostra os ícones
}

// ========== BANCO ==========
async function carregarBanco() {
    try {
        const res = await fetch(`${API_BASE}banco.php`, {
            credentials: 'same-origin'
        });
        const data = await res.json();
        
        document.getElementById('banco-saldo-valor').textContent = 
            `R$ ${data.saldo.toFixed(2).replace('.', ',')}`;
        
        const chavePixEl = document.getElementById('chave-pix-valor');
        if (data.chave_pix) {
            chavePixEl.textContent = data.chave_pix;
        } else {
            chavePixEl.textContent = 'Não configurada';
        }
        
        const container = document.getElementById('transacoes-lista');
        if (data.transacoes.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 20px;">Nenhuma transação</p>';
        } else {
            container.innerHTML = data.transacoes.map(t => `
                <div class="transacao-item">
                    <div class="transacao-info">
                        <h4>${escapeHtml(t.descricao || 'Transação')}</h4>
                        <p>${t.pix_nome ? escapeHtml(t.pix_nome) + ' | ' : ''}${formatarData(t.criado_em)}</p>
                    </div>
                    <div class="transacao-valor ${t.tipo}">
                        ${t.tipo === 'entrada' ? '+' : '-'} R$ ${parseFloat(t.valor).toFixed(2).replace('.', ',')}
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Erro ao carregar banco:', error);
        mostrarErro('Erro ao carregar dados do banco');
    }
}

function editarChavePix() {
    const modal = document.getElementById('modal-chave-pix');
    const chaveAtual = document.getElementById('chave-pix-valor').textContent;
    if (chaveAtual !== 'Não configurada') {
        document.getElementById('nova-chave-pix').value = chaveAtual;
    }
    modal.classList.add('active');
}

function abrirPix(tipo) {
    const modal = document.getElementById('modal-pix');
    const form = document.getElementById('form-pix');
    form.reset();
    document.getElementById('pix-tipo').value = 'enviar';
    document.getElementById('modal-pix-titulo').textContent = 'Enviar PIX';
    modal.classList.add('active');
}

// Formulário PIX
const formPix = document.getElementById('form-pix');
if (formPix) {
    formPix.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const valor = parseFloat(document.getElementById('pix-valor').value);
        const chave = document.getElementById('pix-chave').value.trim();
        
        if (!chave || valor <= 0) {
            mostrarErro('Preencha todos os campos corretamente');
            return;
        }
        
        try {
            const res = await fetch(`${API_BASE}banco.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    acao: 'pix',
                    valor: valor,
                    chave: chave,
                    descricao: 'PIX enviado'
                }),
                credentials: 'same-origin'
            });
            
            const result = await res.json();
            
            if (result.sucesso) {
                fecharModal('modal-pix');
                await carregarBanco();
                mostrarSucesso('PIX enviado com sucesso');
            } else {
                mostrarErro(result.erro || 'Erro ao enviar PIX');
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarErro('Erro ao processar PIX');
        }
    });
}

// Formulário Chave PIX
const formChavePix = document.getElementById('form-chave-pix');
if (formChavePix) {
    formChavePix.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const chave = document.getElementById('nova-chave-pix').value.trim();
        
        try {
            const res = await fetch(`${API_BASE}banco.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    acao: 'salvar_chave_pix',
                    chave_pix: chave
                }),
                credentials: 'same-origin'
            });
            
            const result = await res.json();
            
            if (result.sucesso) {
                fecharModal('modal-chave-pix');
                await carregarBanco();
                mostrarSucesso('Chave PIX salva com sucesso');
            } else {
                mostrarErro(result.erro || 'Erro ao salvar chave PIX');
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarErro('Erro ao salvar chave PIX');
        }
    });
}

// ========== CONTATOS ==========
async function carregarContatos() {
    try {
        const res = await fetch(`${API_BASE}contatos.php`, {
            credentials: 'same-origin'
        });
        const data = await res.json();
        
        contatosCache = data.contatos;
        
        // Carregar grupos no filtro
        const selectFiltro = document.getElementById('filtro-grupo');
        selectFiltro.innerHTML = '<option value="">Todos os grupos</option>' +
            data.grupos.map(g => `<option value="${g.id}">${escapeHtml(g.nome)}</option>`).join('');
        
        // Carregar grupos no modal
        const selectModal = document.getElementById('contato-grupo');
        selectModal.innerHTML = '<option value="">Sem grupo</option>' +
            data.grupos.map(g => `<option value="${g.id}">${escapeHtml(g.nome)}</option>`).join('');
        
        filtrarContatos(data.contatos);
    } catch (error) {
        console.error('Erro ao carregar contatos:', error);
        mostrarErro('Erro ao carregar contatos');
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
            <div class="contato-item" onclick="abrirChatContato(${c.id}, '${escapeHtml(c.nome)}', '${escapeHtml(c.avatar || '')}')">
                <div class="contato-avatar">${c.nome.charAt(0).toUpperCase()}</div>
                <div class="contato-info">
                    <h4>${escapeHtml(c.nome)}</h4>
                    <p>${c.telefone ? escapeHtml(c.telefone) + ' ' : ''}${c.profissao ? '| ' + escapeHtml(c.profissao) : ''}</p>
                </div>
                ${c.grupo_nome ? `<span class="contato-grupo">${escapeHtml(c.grupo_nome)}</span>` : ''}
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
    
    if (id) {
        const contato = contatosCache.find(c => c.id == id);
        if (contato) {
            document.getElementById('contato-nome').value = contato.nome || '';
            document.getElementById('contato-telefone').value = contato.telefone || '';
            document.getElementById('contato-grupo').value = contato.grupo_id || '';
            document.getElementById('contato-endereco').value = contato.endereco || '';
            document.getElementById('contato-profissao').value = contato.profissao || '';
            document.getElementById('contato-notas').value = contato.notas || '';
        }
    }
    
    modal.classList.add('active');
}

const formContato = document.getElementById('form-contato');
if (formContato) {
    formContato.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const id = document.getElementById('contato-id').value;
        const data = {
            acao: id ? 'editar' : 'adicionar',
            id: id || undefined,
            nome: document.getElementById('contato-nome').value.trim(),
            telefone: document.getElementById('contato-telefone').value.trim(),
            grupo_id: document.getElementById('contato-grupo').value || null,
            endereco: document.getElementById('contato-endereco').value.trim(),
            profissao: document.getElementById('contato-profissao').value.trim(),
            notas: document.getElementById('contato-notas').value.trim()
        };
        
        try {
            const res = await fetch(`${API_BASE}contatos.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                credentials: 'same-origin'
            });
            
            const result = await res.json();
            
            if (result.sucesso) {
                fecharModal('modal-contato');
                await carregarContatos();
                mostrarSucesso('Contato salvo com sucesso');
            } else {
                mostrarErro(result.erro || 'Erro ao salvar contato');
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarErro('Erro ao salvar contato');
        }
    });
}

// ========== CHAT ==========
async function carregarChat() {
    try {
        const res = await fetch(`${API_BASE}chat.php`, {
            credentials: 'same-origin'
        });
        const data = await res.json();
        
        const container = document.getElementById('chat-lista');
        if (data.conversas.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 20px;">Nenhuma conversa</p>';
        } else {
            container.innerHTML = data.conversas.map(c => `
                <div class="conversa-item ${c.nao_lidas > 0 ? 'nao-lidas' : ''}" 
                     onclick="abrirConversa(${c.id}, '${escapeHtml(c.nome_contato)}', '${escapeHtml(c.avatar_contato || '')}')">
                    <div class="conversa-avatar">${c.nome_contato.charAt(0).toUpperCase()}</div>
                    <div class="conversa-info">
                        <h4>${escapeHtml(c.nome_contato)}</h4>
                        <p>${escapeHtml(c.ultima_mensagem || 'Nenhuma mensagem')}</p>
                    </div>
                    ${c.nao_lidas > 0 ? `<div class="conversa-badge">${c.nao_lidas}</div>` : ''}
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Erro ao carregar chat:', error);
        mostrarErro('Erro ao carregar conversas');
    }
}

async function abrirChatContato(contato_id, nome, avatar) {
    try {
        const res = await fetch(`${API_BASE}chat.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'criar_conversa',
                contato_id,
                nome_contato: nome,
                avatar_contato: avatar
            }),
            credentials: 'same-origin'
        });
        
        const data = await res.json();
        
        if (data.sucesso) {
            abrirConversa(data.id, nome, avatar);
        }
    } catch (error) {
        console.error('Erro ao criar conversa:', error);
        mostrarErro('Erro ao criar conversa');
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
                <h3>${escapeHtml(nome)}</h3>
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
    } else {
        chatTela.querySelector('h3').textContent = nome;
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
        const res = await fetch(`${API_BASE}chat.php?conversa_id=${conversaAtual}`, {
            credentials: 'same-origin'
        });
        const data = await res.json();
        
        const container = document.getElementById('chat-mensagens');
        container.innerHTML = data.mensagens.map(m => `
            <div class="mensagem ${m.remetente}">
                ${escapeHtml(m.conteudo)}
                <div class="mensagem-hora">${formatarHora(m.criado_em)}</div>
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
            }),
            credentials: 'same-origin'
        });
        
        const result = await res.json();
        
        if (result.sucesso) {
            input.value = '';
            await carregarMensagens();
        } else {
            mostrarErro(result.erro || 'Erro ao enviar mensagem');
        }
    } catch (error) {
        console.error('Erro ao enviar mensagem:', error);
        mostrarErro('Erro ao enviar mensagem');
    }
}

// ========== INVENTÁRIO ==========
async function carregarInventario() {
    try {
        const res = await fetch(`${API_BASE}inventario.php`, {
            credentials: 'same-origin'
        });
        const data = await res.json();
        
        const container = document.getElementById('inventario-lista');
        const categorias = Object.keys(data.inventario);
        
        if (categorias.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 20px;">Inventário vazio</p>';
        } else {
            container.innerHTML = categorias.map(cat => `
                <div class="categoria-inventario">
                    <div class="categoria-titulo">${escapeHtml(cat || 'Outros')}</div>
                    ${data.inventario[cat].map(item => `
                        <div class="item-inventario">
                            <div class="item-info">
                                <h4>${escapeHtml(item.item_nome)}</h4>
                                <p>${escapeHtml(item.descricao || '')}</p>
                            </div>
                            <div class="item-controles">
                                <button class="btn-quantidade" onclick="alterarQuantidade(${item.id}, -1)">-</button>
                                <span class="item-quantidade">${item.quantidade}</span>
                                <button class="btn-quantidade" onclick="alterarQuantidade(${item.id}, 1)">+</button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Erro ao carregar inventário:', error);
        mostrarErro('Erro ao carregar inventário');
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

const formItem = document.getElementById('form-item');
if (formItem) {
    formItem.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const id = document.getElementById('item-id').value;
        const data = {
            acao: id ? 'editar' : 'adicionar',
            id: id || undefined,
            item_nome: document.getElementById('item-nome').value.trim(),
            categoria: document.getElementById('item-categoria').value.trim(),
            quantidade: parseInt(document.getElementById('item-quantidade').value) || 1,
            descricao: document.getElementById('item-descricao').value.trim()
        };
        
        try {
            const res = await fetch(`${API_BASE}inventario.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                credentials: 'same-origin'
            });
            
            const result = await res.json();
            
            if (result.sucesso) {
                fecharModal('modal-item');
                await carregarInventario();
                mostrarSucesso('Item salvo com sucesso');
            } else {
                mostrarErro(result.erro || 'Erro ao salvar item');
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarErro('Erro ao salvar item');
        }
    });
}

async function alterarQuantidade(itemId, delta) {
    try {
        const res = await fetch(`${API_BASE}inventario.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'alterar_quantidade',
                id: itemId,
                delta: delta
            }),
            credentials: 'same-origin'
        });
        
        const result = await res.json();
        
        if (result.sucesso) {
            await carregarInventario();
        } else {
            mostrarErro(result.erro || 'Erro ao alterar quantidade');
        }
    } catch (error) {
        console.error('Erro:', error);
        mostrarErro('Erro ao alterar quantidade');
    }
}

// ========== NOTÍCIAS ==========
async function carregarNoticias() {
    try {
        const res = await fetch(`${API_BASE}noticias.php`, {
            credentials: 'same-origin'
        });
        const data = await res.json();
        
        const container = document.getElementById('noticias-lista');
        if (data.noticias.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 20px;">Nenhuma notícia</p>';
        } else {
            container.innerHTML = data.noticias.map(n => `
                <div class="noticia-item">
                    <h3>${escapeHtml(n.titulo)}</h3>
                    <p>${escapeHtml(n.conteudo)}</p>
                    <div class="noticia-meta">
                        <span>${escapeHtml(n.autor || 'Anônimo')}</span>
                        <span>${formatarData(n.criado_em)}</span>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Erro ao carregar notícias:', error);
        mostrarErro('Erro ao carregar notícias');
    }
}

// ========== TEMA ==========
function aplicarTema(tema) {
    if (tema === 'claro') {
        document.body.classList.add('tema-claro');
    } else {
        document.body.classList.remove('tema-claro');
    }
}

// ========== UTILITÁRIOS ==========
function fecharModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

function atualizarStatusBar() {
    const agora = new Date();
    const horaEl = document.getElementById('status-hora');
    if (horaEl) {
        horaEl.textContent = agora.toLocaleTimeString('pt-BR', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }
    
    setInterval(() => {
        const agora = new Date();
        if (horaEl) {
            horaEl.textContent = agora.toLocaleTimeString('pt-BR', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
    }, 1000);
}

function abrirAdmin() {
    const currentPath = window.location.pathname;
    const basePath = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
    window.open(basePath + 'admin/index.php', '_blank');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatarData(data) {
    if (!data) return '';
    const d = new Date(data);
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

function formatarHora(data) {
    if (!data) return '';
    const d = new Date(data);
    return d.toLocaleTimeString('pt-BR', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

function mostrarErro(mensagem) {
    // Implementar toast de erro
    alert(mensagem);
}

function mostrarSucesso(mensagem) {
    // Implementar toast de sucesso
    console.log('Sucesso:', mensagem);
}

// Limpar intervalos ao sair
window.addEventListener('beforeunload', () => {
    Object.values(intervalos).forEach(interval => clearInterval(interval));
});

// Fechar modais ao clicar fora
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});