<?php

require_once __DIR__ . '/../config/credentials.php';
require_once __DIR__ . '/functions.php';

function enviarEmailAgendamento($agendamento, $status = 'criado') {
    if (!file_exists(__DIR__ . '/../config/credentials.php')) {
        return false;
    }
    
    try {
        $to = $agendamento['email'] ?? SMTP_FROM_EMAIL;
        $subject = '';
        $message = '';
        
        if ($status === 'criado') {
            $subject = 'Agendamento GrapeTech - Pendente de Aprovação';
            $message = "
                <h2>Agendamento Recebido</h2>
                <p>Olá,</p>
                <p>Seu agendamento foi recebido e está <strong>pendente de aprovação</strong>.</p>
                <h3>Detalhes do Agendamento:</h3>
                <ul>
                    <li><strong>Nome:</strong> {$agendamento['nome_servidor']}</li>
                    <li><strong>Pessoas Responsáveis:</strong> {$agendamento['pessoas_responsaveis']}</li>
                    <li><strong>Data:</strong> " . formatarData($agendamento['data_utilizacao']) . "</li>
                    <li><strong>Horário:</strong> " . formatarHorario($agendamento['horario_entrada']) . " às " . formatarHorario($agendamento['horario_saida']) . "</li>
                    <li><strong>Espaço:</strong> {$agendamento['espaco']}</li>
                </ul>
                <p>Você receberá uma notificação quando o agendamento for aprovado ou rejeitado.</p>
            ";
        } elseif ($status === 'aprovado') {
            $subject = 'Agendamento GrapeTech - Aprovado';
            $message = "
                <h2>Agendamento Aprovado! ✅</h2>
                <p>Olá,</p>
                <p>Seu agendamento foi <strong>aprovado</strong> e foi adicionado ao calendário.</p>
                <h3>Detalhes do Agendamento:</h3>
                <ul>
                    <li><strong>Nome:</strong> {$agendamento['nome_servidor']}</li>
                    <li><strong>Pessoas Responsáveis:</strong> {$agendamento['pessoas_responsaveis']}</li>
                    <li><strong>Data:</strong> " . formatarData($agendamento['data_utilizacao']) . "</li>
                    <li><strong>Horário:</strong> " . formatarHorario($agendamento['horario_entrada']) . " às " . formatarHorario($agendamento['horario_saida']) . "</li>
                    <li><strong>Espaço:</strong> {$agendamento['espaco']}</li>
                </ul>
                <p>Até breve!</p>
            ";
        } elseif ($status === 'rejeitado') {
            $subject = 'Agendamento GrapeTech - Rejeitado';
            $message = "
                <h2>Agendamento Rejeitado</h2>
                <p>Olá,</p>
                <p>Infelizmente seu agendamento foi <strong>rejeitado</strong>.</p>
                <p>Por favor, entre em contato para mais informações ou tente agendar em outro horário.</p>
            ";
        }
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        
        return mail($to, $subject, $message, $headers);
        
    } catch (Exception $e) {
        error_log("Erro ao enviar email: " . $e->getMessage());
        return false;
    }
}
