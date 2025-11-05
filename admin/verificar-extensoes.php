<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verificação de Extensões PHP</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .ok { color: green; font-weight: bold; }
        .erro { color: red; font-weight: bold; }
        .info { background: #f0f0f0; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Verificação de Extensões PHP</h1>
    
    <div class="info">
        <h2>OpenSSL</h2>
        <?php if (extension_loaded('openssl')): ?>
            <p class="ok">OpenSSL está HABILITADO</p>
            <p>Função openssl_pkey_get_private: <?php echo function_exists('openssl_pkey_get_private') ? 'Disponível' : 'Não disponível'; ?></p>
        <?php else: ?>
            <p class="erro">OpenSSL NÃO está habilitado</p>
        <?php endif; ?>
    </div>
    
    <div class="info">
        <h2>cURL</h2>
        <?php if (extension_loaded('curl')): ?>
            <p class="ok">cURL está HABILITADO</p>
            <p>Função curl_init: <?php echo function_exists('curl_init') ? 'Disponível' : 'Não disponível'; ?></p>
        <?php else: ?>
            <p class="erro">cURL NÃO está habilitado</p>
        <?php endif; ?>
    </div>
    
    <div class="info">
        <h2>Service Account</h2>
        <?php
        $serviceAccountFile = __DIR__ . '/../config/google-service-account.json';
        if (file_exists($serviceAccountFile)): ?>
            <p class="ok">Arquivo encontrado: <?php echo $serviceAccountFile; ?></p>
            <?php
            $json = json_decode(file_get_contents($serviceAccountFile), true);
            if ($json && isset($json['client_email'])): ?>
                <p>Email: <?php echo htmlspecialchars($json['client_email']); ?></p>
            <?php else: ?>
                <p class="erro">Arquivo JSON inválido</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="erro">Arquivo não encontrado: <?php echo $serviceAccountFile; ?></p>
        <?php endif; ?>
    </div>
    
    <div class="info">
        <h2>PHP Info</h2>
        <p>Versão PHP: <?php echo PHP_VERSION; ?></p>
        <p>Arquivo php.ini: <?php echo php_ini_loaded_file(); ?></p>
    </div>
    
    <p><a href="dashboard.php">Voltar ao Dashboard</a></p>
</body>
</html>

