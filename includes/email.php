<?php

require_once __DIR__ . '/../config/credentials.php';
require_once __DIR__ . '/functions.php';

function verificarPHPMailerDisponivel() {
    static $disponivel = null;
    
    if ($disponivel === null) {
        $disponivel = false;
        
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }
        
        $phpmailerPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        if (file_exists($phpmailerPath)) {
            require_once $phpmailerPath;
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
        }
        
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $disponivel = true;
        }
    }
    
    return $disponivel;
}

function enviarEmailAgendamento($agendamento, $status = 'criado') {
    if (!file_exists(__DIR__ . '/../config/credentials.php')) {
        return false;
    }
    
    try {
        $to = $agendamento['email'] ?? SMTP_FROM_EMAIL;
        
        if (empty($to)) {
            error_log("Email de destino não fornecido para agendamento ID: " . ($agendamento['id'] ?? 'desconhecido'));
            return false;
        }
        
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
                <h2>Agendamento Aprovado!</h2>
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
            $motivo = !empty($agendamento['motivo_rejeicao']) ? $agendamento['motivo_rejeicao'] : 'Não informado';
            
            $message = "
                <h2>Agendamento Rejeitado</h2>
                <p>Olá,</p>
                <p>Infelizmente seu agendamento foi <strong>rejeitado</strong>.</p>
                <h3>Detalhes do Agendamento:</h3>
                <ul>
                    <li><strong>Nome:</strong> {$agendamento['nome_servidor']}</li>
                    <li><strong>Data:</strong> " . formatarData($agendamento['data_utilizacao']) . "</li>
                    <li><strong>Horário:</strong> " . formatarHorario($agendamento['horario_entrada']) . " às " . formatarHorario($agendamento['horario_saida']) . "</li>
                    <li><strong>Espaço:</strong> {$agendamento['espaco']}</li>
                </ul>
                <p><strong>Motivo da Rejeição:</strong></p>
                <p>{$motivo}</p>
                <p>Por favor, entre em contato para mais informações ou tente agendar em outro horário.</p>
            ";
        }
        
        $phpmailerDisponivel = verificarPHPMailerDisponivel();
        if ($phpmailerDisponivel) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            try {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT;
                $mail->CharSet = 'UTF-8';
                
                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($to);
                
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $message;
                
                $mail->send();
                return true;
            } catch (\Exception $e) {
                error_log("Erro ao enviar email via PHPMailer: " . $mail->ErrorInfo);
                return false;
            }
        } else {
            error_log("PHPMailer não está disponível. Instale via Composer: composer require phpmailer/phpmailer");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Erro ao enviar email: " . $e->getMessage());
        return false;
    }
}
