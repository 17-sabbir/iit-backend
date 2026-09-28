<?php
// Separate database for temporary authentication data (OTP, verification)

class AuthTempDatabase {
    private string $host = 'localhost';
    private string $db_name = 'iit_shelf_auth_temp';
    private string $db_user = 'iit_user';
    private string $db_pass = 'iit_password';
    private ?PDO $conn = null;

    public function connect(): PDO {
        try {
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8mb4';
            $this->conn = new PDO($dsn, $this->db_user, $this->db_pass);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('Auth Temp Database Connection Error: ' . $e->getMessage());
        }

        return $this->conn;
    }
}
?>
