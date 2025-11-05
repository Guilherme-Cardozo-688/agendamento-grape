<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
            $dataFim = $_GET['data_fim'] ?? date('Y-m-t');
            $status = $_GET['status'] ?? null;
            
            $agendamentos = buscarAgendamentos($dataInicio, $dataFim, $status);
            
            if (!is_array($agendamentos)) {
                $agendamentos = [];
            }
            
            echo json_encode(['success' => true, 'data' => $agendamentos], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar agendamentos', 'data' => []], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            $data = $_POST;
        }
        
        $camposObrigatorios = ['nome_servidor', 'pessoas_responsaveis', 'data_utilizacao', 
                              'horario_entrada', 'horario_saida', 'espaco'];
        
        foreach ($camposObrigatorios as $campo) {
            if (empty($data[$campo])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Campo {$campo} é obrigatório"]);
                exit;
            }
        }
        
        $conflito = verificarConflitos(
            $data['data_utilizacao'],
            $data['horario_entrada'],
            $data['horario_saida'],
            $data['espaco']
        );
        
        if ($conflito['conflito']) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => $conflito['motivo']]);
            exit;
        }
        
        $sql = "
            INSERT INTO agendamentos 
            (nome_servidor, email, pessoas_responsaveis, data_utilizacao, horario_entrada, 
             horario_saida, espaco, equipamentos, ocupa_todo_espaco, status)
            VALUES 
            (:nome_servidor, :email, :pessoas_responsaveis, :data_utilizacao, :horario_entrada,
             :horario_saida, :espaco, :equipamentos, :ocupa_todo_espaco, 'pendente')
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':nome_servidor', $data['nome_servidor']);
        $stmt->bindValue(':email', $data['email'] ?? null);
        $stmt->bindValue(':pessoas_responsaveis', $data['pessoas_responsaveis']);
        $stmt->bindValue(':data_utilizacao', $data['data_utilizacao']);
        $stmt->bindValue(':horario_entrada', $data['horario_entrada']);
        $stmt->bindValue(':horario_saida', $data['horario_saida']);
        $stmt->bindValue(':espaco', $data['espaco']);
        $stmt->bindValue(':equipamentos', $data['equipamentos'] ?? null);
        $stmt->bindValue(':ocupa_todo_espaco', isset($data['ocupa_todo_espaco']) ? 1 : 0);
        
        if ($stmt->execute()) {
            $id = $db->lastInsertId();
            
            $stmt = $db->prepare("SELECT * FROM agendamentos WHERE id = :id");
            $stmt->bindValue(':id', $id);
            $stmt->execute();
            $agendamento = $stmt->fetch();
            
            require_once __DIR__ . '/../includes/email.php';
            enviarEmailAgendamento($agendamento, 'criado');
            
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Agendamento criado com sucesso', 'data' => $agendamento]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao criar agendamento']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
