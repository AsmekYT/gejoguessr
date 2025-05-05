<?php

define('LOG_FILE_PATH', __DIR__ . '/app.log');
define('ROTATED_LOG_FILE_PATH', __DIR__ . '/app.log.1');
define('MAX_LOG_SIZE_BYTES', 1 * 1024 * 1024);

function custom_log(string $message, string $level = 'INFO') {
    try {
        if (file_exists(LOG_FILE_PATH) && filesize(LOG_FILE_PATH) > MAX_LOG_SIZE_BYTES) {
            if (file_exists(ROTATED_LOG_FILE_PATH)) {
                unlink(ROTATED_LOG_FILE_PATH);
            }
            if (file_exists(LOG_FILE_PATH)) {
               rename(LOG_FILE_PATH, ROTATED_LOG_FILE_PATH);
            }
        }
    } catch (Exception $e) {
        error_log("Custom Logger Rotation Error: " . $e->getMessage());
    }

    $timestamp = date('Y-m-d H:i:s');
    $sanitized_message = str_replace(["\r", "\n"], ' ', trim($message));
    $logEntry = "[{$timestamp}] [{$level}] {$sanitized_message}" . PHP_EOL;

    file_put_contents(LOG_FILE_PATH, $logEntry, FILE_APPEND | LOCK_EX);
}

?>