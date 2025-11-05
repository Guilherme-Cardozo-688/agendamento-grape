<?php

require_once __DIR__ . '/../config/credentials.php';
require_once __DIR__ . '/functions.php';

function obterTokenGoogle() {
    if (!file_exists(GOOGLE_TOKEN_FILE)) {
        return null;
    }
    
    $tokenData = json_decode(file_get_contents(GOOGLE_TOKEN_FILE), true);
    
    if (isset($tokenData['expires_at']) && time() >= $tokenData['expires_at']) {
        if (isset($tokenData['refresh_token'])) {
            $tokenData = renovarTokenGoogle($tokenData['refresh_token']);
        } else {
            return null;
        }
    }
    
    return $tokenData;
}

function renovarTokenGoogle($refreshToken) {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token'
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $newTokenData = json_decode($response, true);
        $newTokenData['refresh_token'] = $refreshToken;
        $newTokenData['expires_at'] = time() + $newTokenData['expires_in'];
        file_put_contents(GOOGLE_TOKEN_FILE, json_encode($newTokenData));
        return $newTokenData;
    }
    
    return null;
}

function adicionarEventoGoogleCalendar($agendamento) {
    $token = obterTokenGoogle();
    
    if (!$token) {
        return null;
    }
    
    $dataInicio = $agendamento['data_utilizacao'] . 'T' . $agendamento['horario_entrada'] . ':00';
    $dataFim = $agendamento['data_utilizacao'] . 'T' . $agendamento['horario_saida'] . ':00';
    
    $evento = [
        'summary' => 'Agendamento GrapeTech - ' . $agendamento['espaco'],
        'description' => "Agendado por: {$agendamento['nome_servidor']}\n" .
                        "Pessoas Responsáveis: {$agendamento['pessoas_responsaveis']}\n" .
                        "Equipamentos: " . ($agendamento['equipamentos'] ?? 'N/A'),
        'start' => [
            'dateTime' => $dataInicio,
            'timeZone' => 'America/Sao_Paulo',
        ],
        'end' => [
            'dateTime' => $dataFim,
            'timeZone' => 'America/Sao_Paulo',
        ],
    ];
    
    $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/' . GOOGLE_CALENDAR_ID . '/events');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token['access_token'],
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($evento));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $eventData = json_decode($response, true);
        return $eventData['id'];
    }
    
    return null;
}

function removerEventoGoogleCalendar($eventId) {
    $token = obterTokenGoogle();
    
    if (!$token || !$eventId) {
        return false;
    }
    
    $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/' . GOOGLE_CALENDAR_ID . '/events/' . $eventId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token['access_token'],
    ]);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 204;
}
