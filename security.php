<?php
// security.php - Global Security Configuration & Helper Functions

// 1. Safe Error Handling
// Prevent fatal errors from crashing the page and exposing sensitive stack traces
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] Error [$errno]: $errstr in $errfile on line $errline" . PHP_EOL;
    error_log($logMessage, 3, __DIR__ . '/error.log');
    
    // Only show friendly message to the user if it's a fatal error
    if ($errno == E_USER_ERROR || $errno == E_ERROR || $errno == E_CORE_ERROR || $errno == E_COMPILE_ERROR) {
        http_response_code(500);
        die("<div style='font-family:sans-serif; text-align:center; background:#f4f7f6; padding:50px; color:#333;'><h3>Oops! Something went wrong.</h3><p>We are experiencing technical difficulties. Please try again later.</p></div>");
    }
    return true; // Don't execute PHP internal error handler
}

function customExceptionHandler($exception) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine() . PHP_EOL;
    error_log($logMessage, 3, __DIR__ . '/error.log');
    
    http_response_code(500);
    die("<div style='font-family:sans-serif; text-align:center; background:#f4f7f6; padding:50px; color:#333;'><h3>Oops! Something went wrong.</h3><p>We are experiencing technical difficulties. Please try again later.</p></div>");
}

set_error_handler("customErrorHandler");
set_exception_handler("customExceptionHandler");


// 2. Global HTTP Security Headers (Helmet Equivalent)
header("X-Frame-Options: DENY"); // Prevents clickjacking
header("X-XSS-Protection: 1; mode=block"); // Cross-site scripting (XSS) filter
header("X-Content-Type-Options: nosniff"); // Prevents MIME-sniffing
header("Strict-Transport-Security: max-age=31536000; includeSubDomains"); // Enforce HTTPS
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// 3. .env Secret Vault Parser
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception(".env file is missing.");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Ensure .env is loaded
try {
    loadEnv(__DIR__ . '/.env');
} catch (Exception $e) {
    // If we can't load the .env, the app can't connect to the DB anyway
    die("Critical configuration error.");
}
?>
