<?php

require_once __DIR__ . '/../config/database.php';

function verificarConflitos($data, $horarioEntrada, $horarioSaida, $espaco, $excluirId = null) {
    $db = Database::getInstance()->getConnection();
    
    $sql = "
        SELECT COUNT(*) as count 
        FROM agendamentos 
        WHERE data_utilizacao = :data
        AND status = 'aprovado'
        AND ocupa_todo_espaco = 1
        AND (
            (horario_entrada <= :entrada AND horario_saida > :entrada)
            OR (horario_entrada < :saida AND horario_saida >= :saida)
            OR (horario_entrada >= :entrada AND horario_saida <= :saida)
        )
    ";
    
    if ($excluirId) {
        $sql .= " AND id != :excluir_id";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':data', $data);
    $stmt->bindValue(':entrada', $horarioEntrada);
    $stmt->bindValue(':saida', $horarioSaida);
    if ($excluirId) {
        $stmt->bindValue(':excluir_id', $excluirId);
    }
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        return ['conflito' => true, 'motivo' => 'Existe um evento que ocupa todo o espaço neste horário'];
    }
    
    $limites = [
        'Laboratório IFMaker' => 3,
        'CoWorking' => 8,
        'Sala de Reunião' => 1
    ];
    
    $limite = $limites[$espaco] ?? 0;
    
    $sql = "
        SELECT COUNT(*) as count 
        FROM agendamentos 
        WHERE data_utilizacao = :data
        AND espaco = :espaco
        AND status = 'aprovado'
        AND (
            (horario_entrada <= :entrada AND horario_saida > :entrada)
            OR (horario_entrada < :saida AND horario_saida >= :saida)
            OR (horario_entrada >= :entrada AND horario_saida <= :saida)
        )
    ";
    
    if ($excluirId) {
        $sql .= " AND id != :excluir_id";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':data', $data);
    $stmt->bindValue(':espaco', $espaco);
    $stmt->bindValue(':entrada', $horarioEntrada);
    $stmt->bindValue(':saida', $horarioSaida);
    if ($excluirId) {
        $stmt->bindValue(':excluir_id', $excluirId);
    }
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] >= $limite) {
        return ['conflito' => true, 'motivo' => "Limite de {$limite} agendamento(s) para {$espaco} já atingido neste horário"];
    }
    
    return ['conflito' => false];
}

function buscarAgendamentos($dataInicio = null, $dataFim = null, $status = null) {
    $db = Database::getInstance()->getConnection();
    
    $sql = "SELECT * FROM agendamentos WHERE 1=1";
    $params = [];
    
    if ($dataInicio) {
        $sql .= " AND data_utilizacao >= :data_inicio";
        $params[':data_inicio'] = $dataInicio;
    }
    
    if ($dataFim) {
        $sql .= " AND data_utilizacao <= :data_fim";
        $params[':data_fim'] = $dataFim;
    }
    
    if ($status) {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }
    
    $sql .= " ORDER BY data_utilizacao, horario_entrada";
    
    try {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        $result = $stmt->fetchAll();
        return is_array($result) ? $result : [];
    } catch (PDOException $e) {
        error_log("Erro ao buscar agendamentos: " . $e->getMessage());
        return [];
    }
}

function formatarData($data) {
    if (empty($data)) {
        return '';
    }
    
    $dias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    $meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
              'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    
    $timestamp = strtotime($data);
    if ($timestamp === false) {
        return $data;
    }
    
    $diaSemana = $dias[date('w', $timestamp)] ?? '';
    $dia = date('d', $timestamp);
    $mes = $meses[date('n', $timestamp)] ?? '';
    $ano = date('Y', $timestamp);
    
    return "{$diaSemana}, {$dia} de {$mes} de {$ano}";
}

function formatarHorario($horario) {
    if (empty($horario)) {
        return '';
    }
    
    $timestamp = strtotime($horario);
    if ($timestamp === false) {
        return $horario;
    }
    
    return date('H:i', $timestamp);
}

function corStatus($status) {
    $cores = [
        'pendente' => 'warning',
        'aprovado' => 'success',
        'rejeitado' => 'danger'
    ];
    return $cores[$status] ?? 'secondary';
}

function textoStatus($status) {
    $textos = [
        'pendente' => 'Pendente',
        'aprovado' => 'Aprovado',
        'rejeitado' => 'Rejeitado'
    ];
    return $textos[$status] ?? $status;
}
