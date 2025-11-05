<?php

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'seu-email@gmail.com');
define('SMTP_PASS', 'sua-senha-app');
define('SMTP_FROM_EMAIL', 'seu-email@gmail.com');
define('SMTP_FROM_NAME', 'GrapeTech Agendamentos');

define('GOOGLE_CLIENT_ID', 'seu-client-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'seu-client-secret');
define('GOOGLE_REDIRECT_URI', 'http://localhost/agendamento-grapetech/config/google_oauth_callback.php');
define('GOOGLE_CALENDAR_ID', 'primary');
define('GOOGLE_TOKEN_FILE', __DIR__ . '/google_token.json');
