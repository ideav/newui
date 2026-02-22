<?php
/**
 * Email Helper Functions
 *
 * Provides email sending functionality using SMTP
 */

require_once __DIR__ . '/../config.php';

/**
 * Send an email using SMTP configuration from config.php
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (plain text)
 * @return bool True on success, false on failure
 */
function mysendmail($to, $subject, $body) {
    global $mail_config;

    // Validate email address
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: $to");
        return false;
    }

    // Prepare email headers
    $charset = $mail_config['smtp_charset'] ?? 'utf-8';
    $from = $mail_config['smtp_from'] ?? 'Web Application';
    $from_email = $mail_config['smtp_username'];

    // Build headers
    $headers = "From: $from <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=$charset\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Try to send via SMTP
    try {
        if (sendViaSMTP($to, $subject, $body, $headers)) {
            if ($mail_config['smtp_debug'] ?? false) {
                error_log("Email sent successfully to: $to");
            }
            return true;
        }
    } catch (Exception $e) {
        error_log("SMTP Error: " . $e->getMessage());
        return false;
    }

    return false;
}

/**
 * Send email via SMTP socket connection
 *
 * @param string $to Recipient email
 * @param string $subject Subject
 * @param string $body Body
 * @param string $headers Headers
 * @return bool Success status
 */
function sendViaSMTP($to, $subject, $body, $headers) {
    global $mail_config;

    $smtp_host = $mail_config['smtp_host'];
    $smtp_port = $mail_config['smtp_port'];
    $smtp_username = $mail_config['smtp_username'];
    $smtp_password = $mail_config['smtp_password'];
    $smtp_debug = $mail_config['smtp_debug'] ?? false;

    // Remove protocol prefix (ssl://, tls://) for parsing
    $host_only = preg_replace('/^(ssl|tls):\/\//', '', $smtp_host);

    // Determine if we need SSL/TLS
    $use_ssl = strpos($smtp_host, 'ssl://') === 0;
    $use_tls = strpos($smtp_host, 'tls://') === 0 || $smtp_port == 587;

    // Connect to SMTP server
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    if ($use_ssl) {
        $socket = stream_socket_client(
            "ssl://$host_only:$smtp_port",
            $errno, $errstr, 30,
            STREAM_CLIENT_CONNECT, $context
        );
    } else {
        $socket = stream_socket_client(
            "tcp://$host_only:$smtp_port",
            $errno, $errstr, 30,
            STREAM_CLIENT_CONNECT, $context
        );
    }

    if (!$socket) {
        error_log("Failed to connect to SMTP server: $errstr ($errno)");
        return false;
    }

    // Read server greeting
    $response = fgets($socket);
    if ($smtp_debug) error_log("S: $response");

    // Send EHLO
    fputs($socket, "EHLO " . gethostname() . "\r\n");
    $response = fgets($socket);
    if ($smtp_debug) error_log("S: $response");

    // Read all EHLO responses
    while (substr($response, 3, 1) == '-') {
        $response = fgets($socket);
        if ($smtp_debug) error_log("S: $response");
    }

    // Start TLS if needed
    if ($use_tls && !$use_ssl) {
        fputs($socket, "STARTTLS\r\n");
        $response = fgets($socket);
        if ($smtp_debug) error_log("S: $response");

        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        // Send EHLO again after STARTTLS
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        $response = fgets($socket);
        if ($smtp_debug) error_log("S: $response");
        while (substr($response, 3, 1) == '-') {
            $response = fgets($socket);
            if ($smtp_debug) error_log("S: $response");
        }
    }

    // AUTH LOGIN
    fputs($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket);
    if ($smtp_debug) error_log("S: $response");

    fputs($socket, base64_encode($smtp_username) . "\r\n");
    $response = fgets($socket);
    if ($smtp_debug) error_log("S: $response");

    fputs($socket, base64_encode($smtp_password) . "\r\n");
    $response = fgets($socket);
    if ($smtp_debug) error_log("S: $response");

    if (substr($response, 0, 3) != '235') {
        error_log("SMTP authentication failed: $response");
        fclose($socket);
        return false;
    }

    // MAIL FROM
    fputs($socket, "MAIL FROM: <$smtp_username>\r\n");
    $response = fgets($socket);
    if ($smtp_debug) error_log("S: $response");

    // RCPT TO
    fputs($socket, "RCPT TO: <$to>\r\n");
    $response = fgets($socket);
    if ($smtp_debug) error_log("S: $response");

    // DATA
    fputs($socket, "DATA\r\n");
    $response = fgets($socket);
    if ($smtp_debug) error_log("S: $response");

    // Send email headers and body
    $charset = $mail_config['smtp_charset'] ?? 'utf-8';
    fputs($socket, "Subject: $subject\r\n");
    fputs($socket, $headers . "\r\n");
    fputs($socket, "\r\n");
    fputs($socket, $body . "\r\n");
    fputs($socket, ".\r\n");
    $response = fgets($socket);
    if ($smtp_debug) error_log("S: $response");

    // QUIT
    fputs($socket, "QUIT\r\n");
    $response = fgets($socket);
    if ($smtp_debug) error_log("S: $response");

    fclose($socket);
    return true;
}

?>
