const API_BASE = '../api/';
const ADMIN_API = 'admin.php';

let usuarios = [];
let conversas = [];

// Navegação entre abas
document.querySelectorAll('.nav-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        
        btn.classList.add('active');
        const tabId = btn.dataset.tab;
        document.getElementById(`tab-${tabId}`).classList.add('active');
        
        if (tabId === 'usuarios') carregarUsuarios();
        if (tabId === 'transacoes') carregarTransacoes();
        if (tabId === 'contatos') carregarContatos();
        if (tabId === 'noticias') carregarNoticias();
        if (tabId === 'relogio') carregarRelogio();
    });
});

// Carregar dados iniciais
carregarUsuarios();

// ========== USUÁRIOS ==========
async function carregarUsuarios() {
    try {
        const res = await fetch(`${ADMIN_API}?acao=usuarios`);
        const data = await res.json();
        usuarios = data.usuarios;
        
        const container = document.getElementById('lista-usuarios');
        container.innerHTML = usuarios.map(u => `
            <div class="item-card">
                <div class="item-info">
                    <h3>${u.nome}</h3>
                    <p>Saldo: R$ ${parseFloat(u.saldo).toFixed(2)} | Tema: ${u.tema} | 
                       ${u.ativo ? 'Ativo' : 'Inativo'} | 
                       Último acesso: ${u.ultimo_acesso ? new Date(u.ultimo_acesso).toLocaleString('pt-BR') : 'Nunca'}</p>
                </div>
                <div class="item-actions">
                    <button class="btn-edit" onclick="editarUsuario(${u.id})">Editar</button>
                    <button class="btn-add" onclick="adicionarSaldo(${u.id})">+ Saldo</button>
                    <button class="btn-delete" onclick="removerUsuario(${u.id}, '${u.nome}')">Remover</button>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Erro ao carregar usuários:', error);
    }
}

function abrirModalUsuario(id = null) {
    const modal = document.getElementById('modal-usuario');
    const form = document.getElementById('form-usuario');
    const titulo = document.getElementById('modal-usuario-titulo');
    
    form.reset();
    document.getElementById('usuario-id').value = id || '';
    titulo.textContent = id ? 'Editar Usuário' : 'Novo Usuário';
    
    if (id) {
        const usuario = usuarios.find(u => u.id == id);
        if (usuario) {
            document.getElementById('usuario-nome').value = usuario.nome;
            document.getElementById('usuario-saldo').value = usuario.saldo;
            document.getElementById('usuario-tema').value = usuario.tema;
        }
    }
    
    modal.style.display = 'block';
}

document.getElementById('form-usuario').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('usuario-id').value;
    const data = {
        acao: id ? 'editar_usuario' : 'criar_usuario',
        id: id || undefined,
        nome: document.getElementById('usuario-nome').value,
        senha: document.getElementById('usuario-senha').value,
        saldo: document.getElementById('usuario-saldo').value,
        tema: document.getElementById('usuario-tema').value
    };
    
    try {
        const res = await fetch(ADMIN_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.sucesso) {
            fecharModal('modal-usuario');
            carregarUsuarios();
        } else {
            alert('Erro: ' + result.erro);
        }
    } catch (error) {
        alert('Erro ao salvar usuário');
    }
});

function editarUsuario(id) {
    abrirModalUsuario(id);
}

async function adicionarSaldo(usuario_id) {
    const valor = prompt('Digite o valor a adicionar:');
    if (!valor || isNaN(valor) || parseFloat(valor) <= 0) return;
    
    const descricao = prompt('Descrição (opcional):') || 'Adicionado pelo admin';
    
    try {
        const res = await fetch(ADMIN_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'adicionar_saldo',
                usuario_id,
                valor: parseFloat(valor),
                descricao
            })
        });
        const result = await res.json();
        if (result.sucesso) {
            alert('Saldo adicionado! Novo saldo: R$ ' + result.novo_saldo.toFixed(2));
            carregarUsuarios();
        } else {
            alert('Erro: ' + result.erro);
        }
    } catch (error) {
        alert('Erro ao adicionar saldo');
    }
}

async function removerUsuario(usuario_id, nome) {
    if (!confirm(`Tem certeza que deseja remover o usuário "${nome}"? Esta ação não pode ser desfeita.`)) {
        return;
    }
    
    try {
        const res = await fetch(ADMIN_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'remover_usuario',
                id: usuario_id
            })
        });
        const result = await res.json();
        if (result.sucesso) {
            alert('Usuário removido com sucesso');
            carregarUsuarios();
        } else {
            alert('Erro: ' + result.erro);
        }
    } catch (error) {
        alert('Erro ao remover usuário');
    }
}

// ========== TRANSAÇÕES ==========
async function carregarTransacoes() {
    try {
        const res = await fetch(`${ADMIN_API}?acao=transacoes`);
        const data = await res.json();
        
        const container = document.getElementById('lista-transacoes');
        container.innerHTML = data.transacoes.map(t => `
            <div class="item-card">
                <div class="item-info">
                    <h3>${t.usuario_nome} - ${t.tipo === 'entrada' ? 'Entrada' : 'Saída'}</h3>
                    <p>R$ ${parseFloat(t.valor).toFixed(2)} | ${t.descricao || 'Sem descrição'}</p>
                    <p style="font-size: 12px; color: #888;">${new Date(t.criado_em).toLocaleString('pt-BR')}</p>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Erro ao carregar transações:', error);
    }
}

// ========== CONTATOS ==========
async function carregarContatos() {
    try {
        const res = await fetch(`${ADMIN_API}?acao=contatos`);
        const data = await res.json();
        
        // Carregar grupos
        const gruposRes = await fetch(`${ADMIN_API}?acao=grupos`);
        const gruposData = await gruposRes.json();
        
        const selectGrupo = document.getElementById('contato-grupo');
        selectGrupo.innerHTML = '<option value="">Sem grupo</option>' + 
            gruposData.grupos.map(g => `<option value="${g.id}">${g.nome}</option>`).join('');
        
        const container = document.getElementById('lista-contatos');
        container.innerHTML = data.contatos.map(c => `
            <div class="item-card">
                <div class="item-info">
                    <h3>${c.nome} ${c.telefone ? '| ' + c.telefone : ''}</h3>
                    <p>${c.grupo_nome || 'Sem grupo'} | ${c.profissao || ''} | ${c.endereco || ''}</p>
                </div>
                <div class="item-actions">
                    <button class="btn-edit" onclick="editarContato(${c.id})">Editar</button>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Erro ao carregar contatos:', error);
    }
}

function abrirModalContato(id = null) {
    const modal = document.getElementById('modal-contato');
    const form = document.getElementById('form-contato');
    const titulo = document.getElementById('modal-contato-titulo');
    
    form.reset();
    document.getElementById('contato-id').value = id || '';
    titulo.textContent = id ? 'Editar Contato' : 'Novo Contato';
    
    // Carregar lista de usuários para checkboxes
    fetch(`${ADMIN_API}?acao=usuarios`)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('contato-usuarios-checkboxes');
            container.innerHTML = data.usuarios
                .filter(u => u.nome !== 'Admin')
                .map(u => `
                    <label style="display: block; margin: 5px 0; cursor: pointer;">
                        <input type="checkbox" value="${u.id}" class="usuario-checkbox" style="margin-right: 8px;">
                        ${u.nome}
                    </label>
                `).join('');
        });
    
    if (id) {
        // Carregar dados do contato
        fetch(`${ADMIN_API}?acao=contatos`)
            .then(r => r.json())
            .then(data => {
                const contato = data.contatos.find(c => c.id == id);
                if (contato) {
                    document.getElementById('contato-nome').value = contato.nome;
                    document.getElementById('contato-telefone').value = contato.telefone || '';
                    document.getElementById('contato-grupo').value = contato.grupo_id || '';
                    document.getElementById('contato-endereco').value = contato.endereco || '';
                    document.getElementById('contato-profissao').value = contato.profissao || '';
                    document.getElementById('contato-notas').value = contato.notas || '';
                }
            });
    }
    
    modal.style.display = 'block';
}

document.getElementById('form-contato').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('contato-id').value;
    
    // Coletar IDs de usuários selecionados
    const usuariosSelecionados = Array.from(document.querySelectorAll('.usuario-checkbox:checked'))
        .map(cb => parseInt(cb.value));
    
    const data = {
        acao: id ? 'editar_contato' : 'criar_contato',
        id: id || undefined,
        nome: document.getElementById('contato-nome').value,
        telefone: document.getElementById('contato-telefone').value,
        grupo_id: document.getElementById('contato-grupo').value || null,
        endereco: document.getElementById('contato-endereco').value,
        profissao: document.getElementById('contato-profissao').value,
        notas: document.getElementById('contato-notas').value,
        usuarios_ids: usuariosSelecionados
    };
    
    try {
        const res = await fetch(ADMIN_API, {
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

function editarContato(id) {
    abrirModalContato(id);
}

// ========== CHAT ==========
document.getElementById('select-usuario-chat').addEventListener('change', async (e) => {
    const usuario_id = e.target.value;
    if (!usuario_id) return;
    
    try {
        const res = await fetch(`${ADMIN_API}?acao=conversas&usuario_id=${usuario_id}`);
        const data = await res.json();
        conversas = data.conversas;
        
        const select = document.getElementById('select-conversa-chat');
        select.innerHTML = '<option value="">Selecione uma conversa...</option>' +
            conversas.map(c => `<option value="${c.id}">${c.nome_contato}</option>`).join('');
    } catch (error) {
        console.error('Erro ao carregar conversas:', error);
    }
});

document.getElementById('select-conversa-chat').addEventListener('change', async (e) => {
    const conversa_id = e.target.value;
    if (!conversa_id) return;
    
    await carregarMensagens(conversa_id);
});

async function carregarMensagens(conversa_id) {
    try {
        const res = await fetch(`${API_BASE}chat.php?conversa_id=${conversa_id}`);
        const data = await res.json();
        
        const container = document.getElementById('area-mensagens');
        container.innerHTML = data.mensagens.map(m => `
            <div class="mensagem ${m.remetente}">
                <strong>${m.remetente === 'npc' ? 'NPC' : 'Jogador'}:</strong> ${m.conteudo}
                <div style="font-size: 11px; margin-top: 5px;">${new Date(m.criado_em).toLocaleString('pt-BR')}</div>
            </div>
        `).join('');
        
        container.scrollTop = container.scrollHeight;
    } catch (error) {
        console.error('Erro ao carregar mensagens:', error);
    }
}

async function enviarMensagemNPC() {
    const conversa_id = document.getElementById('select-conversa-chat').value;
    const conteudo = document.getElementById('input-mensagem').value;
    
    if (!conversa_id || !conteudo) {
        alert('Selecione uma conversa e digite uma mensagem');
        return;
    }
    
    try {
        const res = await fetch(ADMIN_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'enviar_mensagem_npc',
                conversa_id,
                conteudo
            })
        });
        const result = await res.json();
        if (result.sucesso) {
            document.getElementById('input-mensagem').value = '';
            await carregarMensagens(conversa_id);
        } else {
            alert('Erro: ' + result.erro);
        }
    } catch (error) {
        alert('Erro ao enviar mensagem');
    }
}

async function criarNovaConversa() {
    const usuario_id = document.getElementById('select-usuario-chat').value;
    if (!usuario_id) {
        alert('Selecione um usuário primeiro');
        return;
    }
    
    // Buscar contatos do usuário
    try {
        const res = await fetch(`${ADMIN_API}?acao=contatos_usuario&usuario_id=${usuario_id}`);
        const data = await res.json();
        
        if (data.contatos.length === 0) {
            alert('Este usuário não possui contatos. Crie contatos primeiro na aba Contatos.');
            return;
        }
        
        // Criar lista de contatos para escolha
        let lista = 'Escolha um contato:\n\n';
        data.contatos.forEach((c, i) => {
            lista += `${i+1}. ${c.nome}${c.telefone ? ' (' + c.telefone + ')' : ''}\n`;
        });
        
        const escolha = prompt(lista + '\nDigite o número:');
        if (!escolha) return;
        
        const contatoEscolhido = data.contatos[parseInt(escolha) - 1];
        if (!contatoEscolhido) {
            alert('Contato inválido');
            return;
        }
        
        // Criar conversa
        const resCriar = await fetch(ADMIN_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'criar_conversa_npc',
                usuario_id,
                nome_contato: contatoEscolhido.nome,
                avatar_contato: contatoEscolhido.avatar || '',
                contato_id: contatoEscolhido.id
            })
        });
        const result = await resCriar.json();
        if (result.sucesso) {
            document.getElementById('select-usuario-chat').dispatchEvent(new Event('change'));
            document.getElementById('select-conversa-chat').value = result.id;
            document.getElementById('select-conversa-chat').dispatchEvent(new Event('change'));
        }
    } catch (error) {
        alert('Erro ao criar conversa');
    }
}

// Carregar lista de usuários para o chat
fetch(`${ADMIN_API}?acao=usuarios`)
    .then(r => r.json())
    .then(data => {
        const select = document.getElementById('select-usuario-chat');
        select.innerHTML = '<option value="">Selecione um usuário...</option>' +
            data.usuarios.map(u => `<option value="${u.id}">${u.nome}</option>`).join('');
    });

// ========== NOTÍCIAS ==========
async function carregarNoticias() {
    try {
        const res = await fetch(`${ADMIN_API}?acao=noticias`);
        const data = await res.json();
        
        const container = document.getElementById('lista-noticias');
        container.innerHTML = data.noticias.map(n => `
            <div class="item-card">
                <div class="item-info">
                    <h3>${n.titulo}</h3>
                    <p>${n.conteudo.substring(0, 100)}... | Autor: ${n.autor || 'Anônimo'}</p>
                    <p style="font-size: 12px; color: #888;">${new Date(n.criado_em).toLocaleString('pt-BR')} | 
                       ${n.ativo ? 'Ativa' : 'Inativa'}</p>
                </div>
                <div class="item-actions">
                    <button class="btn-edit" onclick="editarNoticia(${n.id})">Editar</button>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Erro ao carregar notícias:', error);
    }
}

function abrirModalNoticia(id = null) {
    const modal = document.getElementById('modal-noticia');
    const form = document.getElementById('form-noticia');
    const titulo = document.getElementById('modal-noticia-titulo');
    
    form.reset();
    document.getElementById('noticia-id').value = id || '';
    titulo.textContent = id ? 'Editar Notícia' : 'Nova Notícia';
    
    if (id) {
        fetch(`${ADMIN_API}?acao=noticias`)
            .then(r => r.json())
            .then(data => {
                const noticia = data.noticias.find(n => n.id == id);
                if (noticia) {
                    document.getElementById('noticia-titulo').value = noticia.titulo;
                    document.getElementById('noticia-conteudo').value = noticia.conteudo;
                    document.getElementById('noticia-autor').value = noticia.autor || '';
                }
            });
    }
    
    modal.style.display = 'block';
}

document.getElementById('form-noticia').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('noticia-id').value;
    const data = {
        acao: id ? 'editar_noticia' : 'criar_noticia',
        id: id || undefined,
        titulo: document.getElementById('noticia-titulo').value,
        conteudo: document.getElementById('noticia-conteudo').value,
        autor: document.getElementById('noticia-autor').value,
        ativo: 1
    };
    
    try {
        const res = await fetch(ADMIN_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.sucesso) {
            fecharModal('modal-noticia');
            carregarNoticias();
        } else {
            alert('Erro: ' + result.erro);
        }
    } catch (error) {
        alert('Erro ao salvar notícia');
    }
});

function editarNoticia(id) {
    abrirModalNoticia(id);
}

// ========== RELÓGIO ==========
async function carregarRelogio() {
    try {
        const res = await fetch(`${API_BASE}relogio.php`);
        const data = await res.json();
        
        const modo = data.modo || 'normal';
        document.querySelector(`input[name="modo-relogio"][value="${modo}"]`).checked = true;
        
        if (modo === 'manual') {
            document.getElementById('controles-manuais').style.display = 'block';
            document.getElementById('data-manual').value = data.data;
            document.getElementById('hora-manual').value = data.hora;
        }
        
        atualizarDisplayRelogio(data);
    } catch (error) {
        console.error('Erro ao carregar relógio:', error);
    }
}

function atualizarDisplayRelogio(data) {
    const display = document.getElementById('relogio-display');
    if (data.modo === 'normal') {
        const agora = new Date();
        display.textContent = agora.toLocaleString('pt-BR');
    } else {
        display.textContent = new Date(data.data + ' ' + data.hora).toLocaleString('pt-BR');
    }
}

document.querySelectorAll('input[name="modo-relogio"]').forEach(radio => {
    radio.addEventListener('change', (e) => {
        document.getElementById('controles-manuais').style.display = 
            e.target.value === 'manual' ? 'block' : 'none';
    });
});

async function salvarRelogio() {
    const modo = document.querySelector('input[name="modo-relogio"]:checked').value;
    const data = {
        acao: 'atualizar_relogio',
        modo,
        data: modo === 'manual' ? document.getElementById('data-manual').value : null,
        hora: modo === 'manual' ? document.getElementById('hora-manual').value : null
    };
    
    try {
        const res = await fetch(ADMIN_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.sucesso) {
            alert('Relógio atualizado!');
            carregarRelogio();
        }
    } catch (error) {
        alert('Erro ao salvar relógio');
    }
}

// Atualizar relógio a cada segundo
setInterval(() => {
    if (document.getElementById('tab-relogio').classList.contains('active')) {
        fetch(`${API_BASE}relogio.php`)
            .then(r => r.json())
            .then(data => atualizarDisplayRelogio(data));
    }
}, 1000);

// ========== UTILITÁRIOS ==========
function fecharModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

function logout() {
    fetch(`${API_BASE}auth.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ acao: 'logout' })
    }).then(() => {
        window.location.href = '../index.html';
    });
}
