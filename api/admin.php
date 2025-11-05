<?php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/google_calendar.php';

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        if ($action === 'aprovar') {
            $id = $_POST['id'] ?? $_GET['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID do agendamento é obrigatório']);
                exit;
            }
            
            $stmt = $db->prepare("SELECT * FROM agendamentos WHERE id = :id");
            $stmt->bindValue(':id', $id);
            $stmt->execute();
            $agendamento = $stmt->fetch();
            
            if (!$agendamento) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado']);
                exit;
            }
            
            $conflito = verificarConflitos(
                $agendamento['data_utilizacao'],
                $agendamento['horario_entrada'],
                $agendamento['horario_saida'],
                $agendamento['espaco'],
                $id
            );
            
            if ($conflito['conflito']) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => $conflito['motivo']]);
                exit;
            }
            
            $eventId = adicionarEventoGoogleCalendar($agendamento);
            
            $stmt = $db->prepare("
                UPDATE agendamentos 
                SET status = 'aprovado', 
                    google_calendar_event_id = :event_id,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':event_id', $eventId);
            $stmt->execute();
            
            $stmt = $db->prepare("SELECT * FROM agendamentos WHERE id = :id");
            $stmt->bindValue(':id', $id);
            $stmt->execute();
            $agendamentoAtualizado = $stmt->fetch();
            
            enviarEmailAgendamento($agendamentoAtualizado, 'aprovado');
            
            echo json_encode(['success' => true, 'message' => 'Agendamento aprovado com sucesso', 'data' => $agendamentoAtualizado]);
            
        } elseif ($action === 'rejeitar') {
            $id = $_POST['id'] ?? $_GET['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID do agendamento é obrigatório']);
                exit;
            }
            
            $stmt = $db->prepare("
                UPDATE agendamentos 
                SET status = 'rejeitado', 
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->bindValue(':id', $id);
            $stmt->execute();
            
            $stmt = $db->prepare("SELECT * FROM agendamentos WHERE id = :id");
            $stmt->bindValue(':id', $id);
            $stmt->execute();
            $agendamento = $stmt->fetch();
            
            enviarEmailAgendamento($agendamento, 'rejeitado');
            
            echo json_encode(['success' => true, 'message' => 'Agendamento rejeitado', 'data' => $agendamento]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
