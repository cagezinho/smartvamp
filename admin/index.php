<?php
require_once '../config.php';
session_start();

// Verificar se é admin
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../index.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - SmartVamp</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="admin-container">
        <header>
            <h1>🎮 Painel Administrativo</h1>
            <button onclick="logout()" class="btn-logout">Sair</button>
        </header>

        <nav class="admin-nav">
            <button class="nav-btn active" data-tab="usuarios">Usuários</button>
            <button class="nav-btn" data-tab="transacoes">Transações</button>
            <button class="nav-btn" data-tab="contatos">Contatos</button>
            <button class="nav-btn" data-tab="chat">Chat</button>
            <button class="nav-btn" data-tab="noticias">Notícias</button>
            <button class="nav-btn" data-tab="relogio">Relógio</button>
        </nav>

        <!-- Aba Usuários -->
        <div id="tab-usuarios" class="tab-content active">
            <div class="section-header">
                <h2>Gerenciar Usuários</h2>
                <button onclick="abrirModalUsuario()" class="btn-primary">+ Novo Usuário</button>
            </div>
            <div id="lista-usuarios" class="lista-container"></div>
        </div>

        <!-- Aba Transações -->
        <div id="tab-transacoes" class="tab-content">
            <div class="section-header">
                <h2>Transações de Todos os Usuários</h2>
            </div>
            <div id="lista-transacoes" class="lista-container"></div>
        </div>

        <!-- Aba Contatos -->
        <div id="tab-contatos" class="tab-content">
            <div class="section-header">
                <h2>Contatos Globais (NPCs)</h2>
                <button onclick="abrirModalContato()" class="btn-primary">+ Novo Contato</button>
            </div>
            <div id="lista-contatos" class="lista-container"></div>
        </div>

        <!-- Aba Chat -->
        <div id="tab-chat" class="tab-content">
            <div class="section-header">
                <h2>Enviar Mensagem como NPC</h2>
            </div>
            <div class="chat-admin">
                <select id="select-usuario-chat" class="select-input">
                    <option value="">Selecione um usuário...</option>
                </select>
                <select id="select-conversa-chat" class="select-input">
                    <option value="">Selecione uma conversa...</option>
                </select>
                <button onclick="criarNovaConversa()" class="btn-secondary">+ Nova Conversa</button>
                <div id="area-mensagens" class="mensagens-container"></div>
                <div class="enviar-mensagem">
                    <input type="text" id="input-mensagem" placeholder="Digite a mensagem..." class="input-text">
                    <button onclick="enviarMensagemNPC()" class="btn-primary">Enviar</button>
                </div>
            </div>
        </div>

        <!-- Aba Notícias -->
        <div id="tab-noticias" class="tab-content">
            <div class="section-header">
                <h2>Gerenciar Notícias</h2>
                <button onclick="abrirModalNoticia()" class="btn-primary">+ Nova Notícia</button>
            </div>
            <div id="lista-noticias" class="lista-container"></div>
        </div>

        <!-- Aba Relógio -->
        <div id="tab-relogio" class="tab-content">
            <div class="section-header">
                <h2>Controle do Relógio do Jogo</h2>
            </div>
            <div class="relogio-admin">
                <div class="relogio-display" id="relogio-display"></div>
                <div class="relogio-controls">
                    <label>
                        <input type="radio" name="modo-relogio" value="normal" checked> Normal (tempo real)
                    </label>
                    <label>
                        <input type="radio" name="modo-relogio" value="manual"> Manual
                    </label>
                    <div id="controles-manuais" style="display: none;">
                        <input type="date" id="data-manual" class="input-text">
                        <input type="time" id="hora-manual" class="input-text">
                        <button onclick="salvarRelogio()" class="btn-primary">Salvar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modais -->
    <div id="modal-usuario" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal('modal-usuario')">&times;</span>
            <h3 id="modal-usuario-titulo">Novo Usuário</h3>
            <form id="form-usuario">
                <input type="hidden" id="usuario-id">
                <input type="text" id="usuario-nome" placeholder="Nome" class="input-text" required>
                <input type="password" id="usuario-senha" placeholder="Senha" class="input-text">
                <input type="number" id="usuario-saldo" placeholder="Saldo inicial" class="input-text" step="0.01" value="0">
                <select id="usuario-tema" class="select-input">
                    <option value="escuro">Tema Escuro</option>
                    <option value="claro">Tema Claro</option>
                </select>
                <button type="submit" class="btn-primary">Salvar</button>
            </form>
        </div>
    </div>

    <div id="modal-contato" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal('modal-contato')">&times;</span>
            <h3 id="modal-contato-titulo">Novo Contato</h3>
            <form id="form-contato">
                <input type="hidden" id="contato-id">
                <input type="text" id="contato-nome" placeholder="Nome" class="input-text" required>
                <input type="text" id="contato-telefone" placeholder="Telefone" class="input-text">
                <select id="contato-grupo" class="select-input"></select>
                <input type="text" id="contato-endereco" placeholder="Endereço" class="input-text">
                <input type="text" id="contato-profissao" placeholder="Profissão" class="input-text">
                <textarea id="contato-notas" placeholder="Notas" class="textarea"></textarea>
                
                <label style="display: block; margin: 15px 0 10px; font-weight: 600;">Adicionar nas agendas de:</label>
                <div id="contato-usuarios-checkboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                    <!-- Checkboxes serão preenchidos via JavaScript -->
                </div>
                <small style="color: #666; display: block; margin-top: 5px;">Deixe vazio para criar como contato global (visível para todos)</small>
                
                <button type="submit" class="btn-primary">Salvar</button>
            </form>
        </div>
    </div>

    <div id="modal-noticia" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal('modal-noticia')">&times;</span>
            <h3 id="modal-noticia-titulo">Nova Notícia</h3>
            <form id="form-noticia">
                <input type="hidden" id="noticia-id">
                <input type="text" id="noticia-titulo" placeholder="Título" class="input-text" required>
                <textarea id="noticia-conteudo" placeholder="Conteúdo" class="textarea" required></textarea>
                <input type="text" id="noticia-autor" placeholder="Autor" class="input-text">
                <button type="submit" class="btn-primary">Salvar</button>
            </form>
        </div>
    </div>

    <script src="admin.js"></script>
</body>
</html>
