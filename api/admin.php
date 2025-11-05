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
            
            $eventId = null;
            $errosGoogle = [];
            try {
                $eventId = adicionarEventoGoogleCalendar($agendamento);
                $errosGoogle = obterErrosGoogleCalendar();
            } catch (Exception $e) {
                error_log("Erro ao adicionar evento ao Google Calendar: " . $e->getMessage());
                $errosGoogle[] = $e->getMessage();
            }
            
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
            
            $emailEnviado = false;
            $erroEmail = null;
            try {
                $emailEnviado = enviarEmailAgendamento($agendamentoAtualizado, 'aprovado');
                if (!$emailEnviado) {
                    $erroEmail = "Falha ao enviar email. Verifique os logs do servidor.";
                    error_log("Falha ao enviar email de aprovação para: " . ($agendamentoAtualizado['email'] ?? 'email não informado'));
                }
            } catch (Exception $e) {
                $erroEmail = $e->getMessage();
                error_log("Exceção ao enviar email de aprovação: " . $e->getMessage());
            }
            
            $response = [
                'success' => true, 
                'message' => 'Agendamento aprovado com sucesso',
                'data' => $agendamentoAtualizado,
                'google_calendar' => [
                    'event_id' => $eventId,
                    'sucesso' => !empty($eventId),
                    'erros' => $errosGoogle
                ],
                'email' => [
                    'enviado' => $emailEnviado,
                    'erro' => $erroEmail
                ]
            ];
            
            echo json_encode($response);
            
        } elseif ($action === 'rejeitar') {
            $id = $_POST['id'] ?? $_GET['id'] ?? null;
            $motivo = $_POST['motivo'] ?? $_GET['motivo'] ?? '';
            
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
            
            if ($agendamento['status'] === 'aprovado' && !empty($agendamento['google_calendar_event_id'])) {
                removerEventoGoogleCalendar($agendamento['google_calendar_event_id']);
            }
            
            $stmt = $db->prepare("
                UPDATE agendamentos 
                SET status = 'rejeitado', 
                    motivo_rejeicao = :motivo,
                    google_calendar_event_id = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':motivo', $motivo);
            $stmt->execute();
            
            $stmt = $db->prepare("SELECT * FROM agendamentos WHERE id = :id");
            $stmt->bindValue(':id', $id);
            $stmt->execute();
            $agendamentoAtualizado = $stmt->fetch();
            
            $emailEnviado = false;
            $erroEmail = null;
            try {
                $emailEnviado = enviarEmailAgendamento($agendamentoAtualizado, 'rejeitado');
                if (!$emailEnviado) {
                    $erroEmail = "Falha ao enviar email. Verifique os logs do servidor.";
                    error_log("Falha ao enviar email de rejeição para: " . ($agendamentoAtualizado['email'] ?? 'email não informado'));
                }
            } catch (Exception $e) {
                $erroEmail = $e->getMessage();
                error_log("Exceção ao enviar email de rejeição: " . $e->getMessage());
            }
            
            $response = [
                'success' => true, 
                'message' => 'Agendamento rejeitado',
                'data' => $agendamentoAtualizado,
                'email' => [
                    'enviado' => $emailEnviado,
                    'erro' => $erroEmail
                ]
            ];
            
            echo json_encode($response);
            
        } elseif ($action === 'excluir') {
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
            
            if (!empty($agendamento['google_calendar_event_id'])) {
                removerEventoGoogleCalendar($agendamento['google_calendar_event_id']);
            }
            
            $stmt = $db->prepare("DELETE FROM agendamentos WHERE id = :id");
            $stmt->bindValue(':id', $id);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Agendamento excluído com sucesso']);
        }
        break;
        
    case 'GET':
        if ($action === 'status-google') {
            require_once __DIR__ . '/../includes/google_calendar.php';
            $status = verificarStatusGoogleCalendar();
            $erros = obterErrosGoogleCalendar();
            echo json_encode([
                'success' => true,
                'status' => $status,
                'erros' => $erros
            ]);
            exit;
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
