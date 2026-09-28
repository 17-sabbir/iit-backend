<?php
class PreRegistrationDatabase {
    private $host = 'localhost';
    private $db_name = 'iit_shelf_prereg';  // Separate pre-registration database
    private $username = 'iit_user';
    private $password = 'iit_password';
    private $conn;

    public function connect() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log('PreReg DB Connection Error: ' . $e->getMessage());
            return null;
        }
        
        return $this->conn;
    }
}
?>
