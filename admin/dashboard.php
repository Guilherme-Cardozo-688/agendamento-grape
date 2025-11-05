<?php
session_start();

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

$agendamentosPendentes = buscarAgendamentos(null, null, 'pendente');
$agendamentosAprovados = buscarAgendamentos(null, null, 'aprovado');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - GrapeTech</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <h1>GrapeTech - Painel Admin</h1>
            <div>
                <span>Olá, <?php echo htmlspecialchars($_SESSION['admin_nome']); ?></span>
                <a href="logout.php" class="btn btn-sm btn-secondary">Sair</a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <h2>Agendamentos Pendentes</h2>
        
        <?php if (empty($agendamentosPendentes)): ?>
            <div class="alert alert-info">Nenhum agendamento pendente</div>
        <?php else: ?>
            <div class="agendamentos-list">
                <?php foreach ($agendamentosPendentes as $agendamento): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3><?php echo htmlspecialchars($agendamento['nome_servidor']); ?></h3>
                            <span class="badge badge-<?php echo corStatus($agendamento['status']); ?>">
                                <?php echo textoStatus($agendamento['status']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <p><strong>Pessoas Responsáveis:</strong> <?php echo htmlspecialchars($agendamento['pessoas_responsaveis']); ?></p>
                            <p><strong>Data:</strong> <?php echo formatarData($agendamento['data_utilizacao']); ?></p>
                            <p><strong>Horário:</strong> <?php echo formatarHorario($agendamento['horario_entrada']); ?> às <?php echo formatarHorario($agendamento['horario_saida']); ?></p>
                            <p><strong>Espaço:</strong> <?php echo htmlspecialchars($agendamento['espaco']); ?></p>
                            <?php if ($agendamento['equipamentos']): ?>
                                <p><strong>Equipamentos:</strong> <?php echo htmlspecialchars($agendamento['equipamentos']); ?></p>
                            <?php endif; ?>
                            <?php if ($agendamento['ocupa_todo_espaco']): ?>
                                <p><strong class="text-warning">⚠️ Ocupa todo o espaço</strong></p>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <button class="btn btn-success btn-approve" data-id="<?php echo $agendamento['id']; ?>">
                                    Aprovar
                                </button>
                                <button class="btn btn-danger btn-reject" data-id="<?php echo $agendamento['id']; ?>">
                                    Rejeitar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <h2 class="mt-4">Agendamentos Aprovados</h2>
        <div class="agendamentos-list">
            <?php foreach ($agendamentosAprovados as $agendamento): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><?php echo htmlspecialchars($agendamento['nome_servidor']); ?></h3>
                        <span class="badge badge-success">Aprovado</span>
                    </div>
                    <div class="card-body">
                        <p><strong>Data:</strong> <?php echo formatarData($agendamento['data_utilizacao']); ?></p>
                        <p><strong>Horário:</strong> <?php echo formatarHorario($agendamento['horario_entrada']); ?> às <?php echo formatarHorario($agendamento['horario_saida']); ?></p>
                        <p><strong>Espaço:</strong> <?php echo htmlspecialchars($agendamento['espaco']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>

