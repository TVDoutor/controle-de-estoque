<?php

declare(strict_types=1);

// In most shared hosting (HostGator) the DB runs on the same machine. Use
// 'localhost' unless your provider gave you a remote DB host (then restore
// the remote IP/hostname).
//const DB_HOST = '127.0.0.1';
const DB_HOST = '108.167.168.27';
const DB_NAME = 'tvdout68_controle_estoque';
const DB_USER = 'tvdout68_controle_estoque';
const DB_PASS = 'controle_estoque';
const APP_NAME = 'Controle de Estoque';
const BASE_URL = '/';
const SESSION_NAME = 'estoque_session';
const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

if (!ini_get('date.timezone')) {
    date_default_timezone_set(DEFAULT_TIMEZONE);
}

