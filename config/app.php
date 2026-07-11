<?php
// config/app.php

define('APP_NAME',    'Swimming Academy');
define('APP_URL',     'http://localhost:8000');   // ← change to your domain (no trailing slash)
define('APP_ENV',     'development');        // 'production' in live

// Session
define('SESSION_NAME',     'bhc_session');
define('SESSION_LIFETIME', 7200);            // 2 hours in seconds

// Email (PHPMailer settings)
define('MAIL_HOST',       'smtp.gmail.com');  // ← your SMTP host
define('MAIL_PORT',       587);
define('MAIL_USERNAME',   'osama.ramadan.esmail@gmail.com');
define('MAIL_PASSWORD',   'zvqc smes joxs fnmf'); // ← change
define('MAIL_FROM_EMAIL', 'osama.ramadan.esmail@gmail.com');
define('MAIL_FROM_NAME',  APP_NAME);
define('MAIL_ENCRYPTION', 'tls');

// Token expiry
define('VERIFY_TOKEN_HOURS', 24);
define('RESET_TOKEN_HOURS',  1);
define('ROLE_DASHBOARDS', [
    'admin'          => '/admin/dashboard',
    'customer_service'      => '/customer_service/dashboard',
    'branch_manager'     => '/branch_manager/dashboard',
    'area_manager'   => '/area_manager/dashboard',
]);
