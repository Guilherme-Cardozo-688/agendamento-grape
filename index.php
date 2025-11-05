<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento GrapeTech</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <h1>GrapeTech - Agendamento de Espaços</h1>
            <a href="admin/login.php" class="btn btn-sm btn-secondary">Admin</a>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <h2>Calendário de Agendamentos</h2>
                <div id="calendario">
                    <div style="text-align: center; padding: 2rem;">
                        <p>Carregando calendário...</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3>Novo Agendamento</h3>
                    </div>
                    <div class="card-body form-scrollable">
                        <form id="formAgendamento">
                            <div class="form-group">
                                <label for="nome_servidor">Nome Completo do(a) servidor(a) *</label>
                                <input type="text" id="nome_servidor" name="nome_servidor" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email para notificações</label>
                                <input type="email" id="email" name="email">
                            </div>
                            
                            <div class="form-group">
                                <label for="pessoas_responsaveis">Informe os nomes das pessoas que você está responsável *</label>
                                <textarea id="pessoas_responsaveis" name="pessoas_responsaveis" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="data_utilizacao">Data de utilização *</label>
                                <input type="date" id="data_utilizacao" name="data_utilizacao" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="horario_entrada">Horário de entrada *</label>
                                <input type="time" id="horario_entrada" name="horario_entrada" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="horario_saida">Horário de saída prevista *</label>
                                <input type="time" id="horario_saida" name="horario_saida" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Quais espaços serão utilizados *</label>
                                <div class="checkbox-group">
                                    <label>
                                        <input type="radio" name="espaco" value="Laboratório IFMaker" required>
                                        Laboratório IFMaker
                                    </label>
                                    <label>
                                        <input type="radio" name="espaco" value="CoWorking" required>
                                        CoWorking
                                    </label>
                                    <label>
                                        <input type="radio" name="espaco" value="Sala de Reunião" required>
                                        Sala de Reunião
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group" id="equipamentos-group" style="display: none;">
                                <label>Equipamentos (se Laboratório IFMaker)</label>
                                <div class="checkbox-group">
                                    <label><input type="checkbox" name="equipamentos[]" value="Ender 2"> Ender 2 (3 disponíveis)</label>
                                    <label><input type="checkbox" name="equipamentos[]" value="Finder 3"> Finder 3 (3 disponíveis)</label>
                                    <label><input type="checkbox" name="equipamentos[]" value="H5"> H5 (1 disponível)</label>
                                    <label><input type="checkbox" name="equipamentos[]" value="Core a1 v2"> Core a1 v2 (1 disponível)</label>
                                    <label><input type="checkbox" name="equipamentos[]" value="ScannCut Brother"> ScannCut Brother (1 disponível)</label>
                                    <label><input type="checkbox" name="equipamentos[]" value="Plotter de recorte"> Plotter de recorte (1 disponível)</label>
                                    <label><input type="checkbox" name="equipamentos[]" value="Canetas 3d"> Canetas 3d (8 disponíveis)</label>
                                    <label>
                                        <input type="text" name="equipamentos_outro" placeholder="Outros">
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="ocupa_todo_espaco" value="1">
                                    Este evento ocupa todo o espaço (bloqueia outros agendamentos no mesmo horário)
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">Enviar Agendamento</button>
                        </form>
                        
                        <div id="mensagem" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>

