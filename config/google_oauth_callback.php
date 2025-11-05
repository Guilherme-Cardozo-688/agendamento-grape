<?php

require_once __DIR__ . '/credentials.php';

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $tokenData = json_decode($response, true);
        $tokenData['expires_at'] = time() + $tokenData['expires_in'];
        file_put_contents(GOOGLE_TOKEN_FILE, json_encode($tokenData));
        echo '<h1>Autenticação bem-sucedida!</h1><p>Você pode fechar esta janela.</p>';
    } else {
        echo '<h1>Erro na autenticação</h1><p>Por favor, tente novamente.</p>';
    }
} else {
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/calendar',
        'access_type' => 'offline',
        'prompt' => 'consent'
    ]);
    
    header('Location: ' . $authUrl);
    exit;
}
