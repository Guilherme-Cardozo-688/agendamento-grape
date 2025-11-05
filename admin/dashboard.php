<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: login.php');
    exit;
}

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
} catch (Exception $e) {
    error_log("Erro ao carregar arquivos base: " . $e->getMessage());
    die("Erro ao carregar sistema. Verifique os logs.");
}

try {
    require_once __DIR__ . '/../includes/google_calendar.php';
} catch (Exception $e) {
    error_log("Erro ao carregar Google Calendar: " . $e->getMessage());
}

$db = Database::getInstance()->getConnection();

$agendamentosPendentes = buscarAgendamentos(null, null, 'pendente');
$agendamentosAprovados = buscarAgendamentos(null, null, 'aprovado');

$statusGoogle = ['conectado' => false, 'token_valido' => false, 'erros' => []];
$errosGoogle = [];

try {
    $extensoesOk = true;
    if (!extension_loaded('curl')) {
        $statusGoogle['erros'][] = 'Extensão cURL não está habilitada. Reinicie o servidor PHP após habilitar no php.ini';
        $extensoesOk = false;
    }
    if (!extension_loaded('openssl')) {
        $statusGoogle['erros'][] = 'Extensão OpenSSL não está habilitada. Reinicie o servidor PHP após habilitar no php.ini';
        $extensoesOk = false;
    }
    
    if ($extensoesOk && function_exists('verificarStatusGoogleCalendar')) {
        $statusGoogleTemp = verificarStatusGoogleCalendar();
        $statusGoogle['conectado'] = $statusGoogleTemp['conectado'];
        $statusGoogle['token_valido'] = $statusGoogleTemp['token_valido'];
        $statusGoogle['erros'] = array_merge($statusGoogle['erros'], $statusGoogleTemp['erros']);
        $statusGoogle['ultimo_erro'] = $statusGoogleTemp['ultimo_erro'] ?? null;
    }
    
    if (function_exists('obterErrosGoogleCalendar')) {
        $errosGoogle = obterErrosGoogleCalendar();
    }
} catch (Exception $e) {
    error_log("Erro ao verificar status do Google Calendar: " . $e->getMessage());
    $statusGoogle['erros'][] = 'Erro ao verificar status do Google Calendar: ' . $e->getMessage();
}
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
        <div class="card mb-4" style="background: #f9fafb; border: 2px solid #e5e7eb;">
            <div class="card-header">
                <h2 style="margin: 0;">Google Calendar - Status da Conexão</h2>
            </div>
            <div class="card-body">
                <div id="google-status-container">
                    <?php if ($statusGoogle['conectado'] && $statusGoogle['token_valido']): ?>
                        <div class="alert alert-success">
                            <strong>Conectado</strong> - Google Calendar está configurado e funcionando corretamente.
                        </div>
                        <?php if (!empty($statusGoogle['erros'])): ?>
                            <div class="alert alert-warning">
                                <strong>Avisos:</strong>
                                <ul style="margin: 0.5rem 0 0 1.5rem;">
                                    <?php foreach ($statusGoogle['erros'] as $erro): ?>
                                        <li><?php echo htmlspecialchars($erro); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>Não Conectado</strong> - Google Calendar não está configurado ou token inválido.
                            <?php if (!empty($statusGoogle['erros'])): ?>
                                <ul style="margin: 0.5rem 0 0 1.5rem;">
                                    <?php foreach ($statusGoogle['erros'] as $erro): ?>
                                        <li><?php echo htmlspecialchars($erro); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <button id="refresh-status" class="btn btn-secondary">Atualizar Status</button>
                        <a href="verificar-extensoes.php" class="btn btn-secondary" target="_blank">Verificar Extensões PHP</a>
                        <?php if (!$statusGoogle['conectado'] || !$statusGoogle['token_valido']): ?>
                            <p class="mt-2"><small><strong>Dica:</strong> Certifique-se de que o arquivo <code>config/google-service-account.json</code> está presente e que a Service Account tem permissão para acessar o calendário.</small></p>
                            <?php if (!empty($statusGoogle['erros'])): ?>
                                <p class="mt-2"><small><strong>IMPORTANTE:</strong> Se você acabou de habilitar as extensões no php.ini, é necessário <strong>REINICIAR o servidor PHP</strong> (pare com Ctrl+C e inicie novamente com <code>php -S localhost:8000</code>).</small></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($errosGoogle)): ?>
        <div class="card mb-4" style="background: #fef2f2; border: 2px solid #fecaca;">
            <div class="card-header" style="background: #fee2e2;">
                <h2 style="margin: 0; color: #991b1b;">Erros do Google Calendar</h2>
            </div>
            <div class="card-body">
                <ul style="margin: 0; padding-left: 1.5rem;">
                    <?php foreach ($errosGoogle as $erro): ?>
                        <li style="color: #991b1b; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($erro); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
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
                                <p><strong class="text-warning">Ocupa todo o espaço</strong></p>
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
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($agendamento['email'] ?? 'N/A'); ?></p>
                        <p><strong>Data:</strong> <?php echo formatarData($agendamento['data_utilizacao']); ?></p>
                        <p><strong>Horário:</strong> <?php echo formatarHorario($agendamento['horario_entrada']); ?> às <?php echo formatarHorario($agendamento['horario_saida']); ?></p>
                        <p><strong>Espaço:</strong> <?php echo htmlspecialchars($agendamento['espaco']); ?></p>
                        <?php if ($agendamento['google_calendar_event_id']): ?>
                            <p><small style="color: #059669;">Sincronizado com Google Calendar</small></p>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <button class="btn btn-danger btn-delete" data-id="<?php echo $agendamento['id']; ?>">
                                Excluir
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>

