<?php
declare(strict_types=1);

// Copy this file to includes/config.php and fill the values for your hosting
const DB_HOST = 'localhost'; // or the host provided by your host
const DB_NAME = 'your_cpanel_prefix_dbname';
const DB_USER = 'your_cpanel_prefix_dbuser';
const DB_PASS = 'your_db_password';
const APP_NAME = 'Controle de Estoque';
const BASE_URL = '/';
const SESSION_NAME = 'estoque_session';
const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

if (!ini_get('date.timezone')) {
    date_default_timezone_set(DEFAULT_TIMEZONE);
}
