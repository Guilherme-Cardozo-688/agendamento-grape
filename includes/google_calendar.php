<?php

require_once __DIR__ . '/../config/credentials.php';
require_once __DIR__ . '/functions.php';

$GLOBALS['google_calendar_errors'] = [];

function carregarServiceAccount() {
    try {
        if (!defined('GOOGLE_SERVICE_ACCOUNT_FILE')) {
            $GLOBALS['google_calendar_errors'][] = 'GOOGLE_SERVICE_ACCOUNT_FILE não está definido. Verifique credentials.php';
            return null;
        }
        
        if (!file_exists(GOOGLE_SERVICE_ACCOUNT_FILE)) {
            $GLOBALS['google_calendar_errors'][] = 'Arquivo de Service Account não encontrado: ' . GOOGLE_SERVICE_ACCOUNT_FILE;
            return null;
        }
        
        $jsonContent = file_get_contents(GOOGLE_SERVICE_ACCOUNT_FILE);
        if ($jsonContent === false) {
            $GLOBALS['google_calendar_errors'][] = 'Erro ao ler arquivo de Service Account.';
            return null;
        }
        
        $serviceAccount = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $GLOBALS['google_calendar_errors'][] = 'Erro ao decodificar JSON: ' . json_last_error_msg();
            return null;
        }
        
        if (!$serviceAccount || !isset($serviceAccount['private_key'])) {
            $GLOBALS['google_calendar_errors'][] = 'Arquivo de Service Account inválido ou sem chave privada.';
            return null;
        }
        
        return $serviceAccount;
    } catch (Exception $e) {
        $GLOBALS['google_calendar_errors'][] = 'Erro ao carregar Service Account: ' . $e->getMessage();
        return null;
    }
}

function verificarOpenSSL() {
    if (!function_exists('openssl_pkey_get_private')) {
        return false;
    }
    if (!extension_loaded('openssl')) {
        return false;
    }
    return true;
}

function verificarCurl() {
    if (!function_exists('curl_init')) {
        return false;
    }
    if (!extension_loaded('curl')) {
        return false;
    }
    return true;
}

function configurarSSL($ch) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $cacertPath = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($cacertPath && file_exists($cacertPath)) {
        curl_setopt($ch, CURLOPT_CAINFO, $cacertPath);
        return true;
    }
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    return false;
}

function criarJWT($serviceAccount) {
    if (!verificarOpenSSL()) {
        $GLOBALS['google_calendar_errors'][] = 'Extensão OpenSSL não está habilitada no PHP. Habilite a extensão openssl no php.ini para usar Google Calendar.';
        return null;
    }
    
    $now = time();
    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT'
    ];
    
    $claimSet = [
        'iss' => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/calendar',
        'aud' => $serviceAccount['token_uri'],
        'exp' => $now + 3600,
        'iat' => $now
    ];
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
    $base64UrlClaimSet = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($claimSet)));
    
    $signatureInput = $base64UrlHeader . '.' . $base64UrlClaimSet;
    
    $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
    if (!$privateKey) {
        $error = openssl_error_string();
        $GLOBALS['google_calendar_errors'][] = 'Erro ao carregar chave privada: ' . ($error ?: 'Erro desconhecido');
        return null;
    }
    
    $signature = '';
    if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        $error = openssl_error_string();
        $GLOBALS['google_calendar_errors'][] = 'Erro ao assinar JWT: ' . ($error ?: 'Erro desconhecido');
        return null;
    }
    
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $signatureInput . '.' . $base64UrlSignature;
}

function obterAccessTokenViaJWT() {
    limparErrosGoogleCalendar();
    
    if (!verificarCurl()) {
        $GLOBALS['google_calendar_errors'][] = 'Extensão cURL não está habilitada no PHP. Habilite a extensão curl no php.ini para usar Google Calendar.';
        return null;
    }
    
    $serviceAccount = carregarServiceAccount();
    if (!$serviceAccount) {
        return null;
    }
    
    $jwt = criarJWT($serviceAccount);
    if (!$jwt) {
        return null;
    }
    
    $ch = curl_init($serviceAccount['token_uri']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    configurarSSL($ch);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        $GLOBALS['google_calendar_errors'][] = 'Erro de conexão ao obter token: ' . $error;
        return null;
    }
    
    if ($httpCode === 200) {
        $tokenData = json_decode($response, true);
        if (isset($tokenData['access_token'])) {
            $tokenData['expires_at'] = time() + ($tokenData['expires_in'] ?? 3600);
            return $tokenData;
        }
    }
    
    $errorData = json_decode($response, true);
    $errorMsg = isset($errorData['error_description']) ? $errorData['error_description'] : 'Erro desconhecido';
    $GLOBALS['google_calendar_errors'][] = 'Erro ao obter token: ' . $errorMsg . ' (HTTP ' . $httpCode . ')';
    
    return null;
}

function obterTokenGoogle() {
    if (file_exists(GOOGLE_TOKEN_FILE)) {
        $tokenData = json_decode(file_get_contents(GOOGLE_TOKEN_FILE), true);
        
        if ($tokenData && isset($tokenData['access_token']) && isset($tokenData['expires_at'])) {
            if (time() < ($tokenData['expires_at'] - 300)) {
                return $tokenData;
            }
        }
    }
    
    $tokenData = obterAccessTokenViaJWT();
    
    if ($tokenData) {
        file_put_contents(GOOGLE_TOKEN_FILE, json_encode($tokenData));
    }
    
    return $tokenData;
}

function verificarStatusGoogleCalendar() {
    $status = [
        'conectado' => false,
        'token_valido' => false,
        'erros' => [],
        'ultimo_erro' => null
    ];
    
    try {
        if (!verificarOpenSSL()) {
            $status['erros'][] = 'Extensão OpenSSL não está habilitada no PHP. Habilite a extensão openssl no php.ini para usar Google Calendar.';
            return $status;
        }
        
        if (!verificarCurl()) {
            $status['erros'][] = 'Extensão cURL não está habilitada no PHP. Habilite a extensão curl no php.ini para usar Google Calendar.';
            return $status;
        }
        
        if (!defined('GOOGLE_SERVICE_ACCOUNT_FILE')) {
            $status['erros'][] = 'Google Calendar não configurado. Verifique o arquivo credentials.php';
            return $status;
        }
        
        if (!file_exists(GOOGLE_SERVICE_ACCOUNT_FILE)) {
            $status['erros'][] = 'Arquivo de Service Account não encontrado: ' . GOOGLE_SERVICE_ACCOUNT_FILE;
            return $status;
        }
        
        $serviceAccount = carregarServiceAccount();
        if (!$serviceAccount) {
            $status['erros'] = array_merge($status['erros'], $GLOBALS['google_calendar_errors'] ?? []);
            return $status;
        }
        
        $status['conectado'] = true;
        
        try {
            $token = obterTokenGoogle();
            
            if ($token && isset($token['access_token'])) {
                $status['token_valido'] = true;
            } else {
                $status['token_valido'] = false;
                $status['erros'] = array_merge($status['erros'], $GLOBALS['google_calendar_errors'] ?? []);
            }
        } catch (Exception $e) {
            $status['token_valido'] = false;
            $status['erros'][] = 'Erro ao obter token: ' . $e->getMessage();
        }
        
        if (!empty($GLOBALS['google_calendar_errors'])) {
            $status['erros'] = array_merge($status['erros'], $GLOBALS['google_calendar_errors']);
            $status['ultimo_erro'] = end($GLOBALS['google_calendar_errors']);
        }
    } catch (Exception $e) {
        $status['erros'][] = 'Erro ao verificar status: ' . $e->getMessage();
        error_log("Erro em verificarStatusGoogleCalendar: " . $e->getMessage());
    }
    
    return $status;
}

function obterErrosGoogleCalendar() {
    return $GLOBALS['google_calendar_errors'] ?? [];
}

function limparErrosGoogleCalendar() {
    $GLOBALS['google_calendar_errors'] = [];
}

function adicionarEventoGoogleCalendar($agendamento) {
    limparErrosGoogleCalendar();
    $token = obterTokenGoogle();
    
    if (!$token || !isset($token['access_token'])) {
        $GLOBALS['google_calendar_errors'][] = 'Não foi possível obter token válido para adicionar evento ao Google Calendar.';
        return null;
    }
    
    if (!defined('GOOGLE_CALENDAR_ID')) {
        $GLOBALS['google_calendar_errors'][] = 'ID do calendário não configurado.';
        return null;
    }
    
    $dataInicio = $agendamento['data_utilizacao'] . 'T' . $agendamento['horario_entrada'] . ':00';
    $dataFim = $agendamento['data_utilizacao'] . 'T' . $agendamento['horario_saida'] . ':00';
    
    $evento = [
        'summary' => 'Agendamento GrapeTech - ' . $agendamento['espaco'],
        'description' => "Agendado por: {$agendamento['nome_servidor']}\n" .
                        "Email: " . ($agendamento['email'] ?? 'N/A') . "\n" .
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
    configurarSSL($ch);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token['access_token'],
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($evento));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        $GLOBALS['google_calendar_errors'][] = 'Erro de conexão ao adicionar evento: ' . $error;
        return null;
    }
    
    if ($httpCode === 200) {
        $eventData = json_decode($response, true);
        if (isset($eventData['id'])) {
            return $eventData['id'];
        }
    }
    
    $errorData = json_decode($response, true);
    $errorMsg = isset($errorData['error']['message']) ? $errorData['error']['message'] : 'Erro desconhecido';
    $GLOBALS['google_calendar_errors'][] = 'Erro ao adicionar evento ao Google Calendar: ' . $errorMsg . ' (HTTP ' . $httpCode . ')';
    
    return null;
}

function removerEventoGoogleCalendar($eventId) {
    limparErrosGoogleCalendar();
    
    if (!$eventId) {
        $GLOBALS['google_calendar_errors'][] = 'ID do evento não fornecido para remoção.';
        return false;
    }
    
    $token = obterTokenGoogle();
    
    if (!$token || !isset($token['access_token'])) {
        $GLOBALS['google_calendar_errors'][] = 'Não foi possível obter token válido para remover evento do Google Calendar.';
        return false;
    }
    
    if (!defined('GOOGLE_CALENDAR_ID')) {
        $GLOBALS['google_calendar_errors'][] = 'ID do calendário não configurado.';
        return false;
    }
    
    $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/' . GOOGLE_CALENDAR_ID . '/events/' . $eventId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    configurarSSL($ch);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token['access_token'],
    ]);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        $GLOBALS['google_calendar_errors'][] = 'Erro de conexão ao remover evento: ' . $error;
        return false;
    }
    
    if ($httpCode === 204) {
        return true;
    }
    
    if ($httpCode === 404) {
        $GLOBALS['google_calendar_errors'][] = 'Evento não encontrado no Google Calendar (já pode ter sido removido).';
        return false;
    }
    
    $GLOBALS['google_calendar_errors'][] = 'Erro ao remover evento do Google Calendar (HTTP ' . $httpCode . ').';
    return false;
}
