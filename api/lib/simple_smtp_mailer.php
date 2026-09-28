<?php
/**
 * Simple SMTP Mailer - No external dependencies required
 * Works with Mailtrap, Gmail, SendGrid, AWS SES, and local mail servers
 */

class SimpleSMTPMailer
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $fromEmail;
    private $fromName;

    public function __construct($host, $port, $username, $password, $encryption, $fromEmail, $fromName)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = $encryption;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    /**
     * Send email via SMTP
     */
    public function send($toEmail, $toName, $subject, $htmlBody, $textBody = null): bool
    {
        try {
            // Connect to SMTP server
            $socket = $this->connect();
            if (!$socket) {
                error_log("SMTP: Failed to connect to {$this->host}:{$this->port}");
                return false;
            }

            // Read greeting
            $this->readResponse($socket);

            // EHLO command
            $this->sendCommand($socket, "EHLO localhost");
            $this->readResponse($socket);

            // STARTTLS if needed
            if ($this->encryption === 'tls') {
                $this->sendCommand($socket, "STARTTLS");
                $this->readResponse($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }

            // Authentication
            $this->authenticate($socket);

            // Build email
            $headers = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $headers .= "To: {$toName} <{$toEmail}>\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            $message = "{$headers}\r\n{$htmlBody}";

            // Send email
            $this->sendCommand($socket, "MAIL FROM:<{$this->fromEmail}>");
            $this->readResponse($socket);

            $this->sendCommand($socket, "RCPT TO:<{$toEmail}>");
            $this->readResponse($socket);

            $this->sendCommand($socket, "DATA");
            $this->readResponse($socket);

            fwrite($socket, $message . "\r\n.\r\n");
            $this->readResponse($socket);

            // Quit
            $this->sendCommand($socket, "QUIT");
            fclose($socket);

            return true;
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }

    private function connect()
    {
        $context = stream_context_create();
        
        if ($this->encryption === 'ssl') {
            return @fsockopen("ssl://{$this->host}", $this->port, $errno, $errstr, 10);
        } else {
            return @fsockopen($this->host, $this->port, $errno, $errstr, 10);
        }
    }

    private function authenticate(&$socket)
    {
        $this->sendCommand($socket, "AUTH LOGIN");
        $this->readResponse($socket);

        $this->sendCommand($socket, base64_encode($this->username));
        $this->readResponse($socket);

        $this->sendCommand($socket, base64_encode($this->password));
        $this->readResponse($socket);
    }

    private function sendCommand(&$socket, $command)
    {
        fwrite($socket, "{$command}\r\n");
    }

    private function readResponse(&$socket)
    {
        $response = '';
        while ($line = fgets($socket, 1024)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    }
}
