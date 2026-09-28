<?php
/**
 * Migrate pre-registration tables from iit_shelf to iit_shelf_prereg database
 */

$mainDb = new PDO(
    'mysql:host=localhost;dbname=iit_shelf',
    'iit_user',
    'iit_password'
);
$mainDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$preRegDb = new PDO(
    'mysql:host=localhost;dbname=iit_shelf_prereg',
    'iit_user',
    'iit_password'
);
$preRegDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Create tables in new database
    $preRegDb->exec("
        CREATE TABLE IF NOT EXISTS PreReg_Students (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            roll VARCHAR(50) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            contact VARCHAR(20),
            session VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $preRegDb->exec("
        CREATE TABLE IF NOT EXISTS PreReg_Teachers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            designation VARCHAR(100) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            contact VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $preRegDb->exec("
        CREATE TABLE IF NOT EXISTS PreReg_Librarians (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            contact VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $preRegDb->exec("
        CREATE TABLE IF NOT EXISTS PreReg_Directors (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            contact VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    echo "✓ Pre-registration tables created in iit_shelf_prereg database\n";
    
    // Migrate data from old location
    $tables = ['PreReg_Students', 'PreReg_Teachers', 'PreReg_Librarians', 'PreReg_Directors'];
    
    foreach ($tables as $table) {
        // Check if table has data in main database
        $result = $mainDb->query("SELECT COUNT(*) as cnt FROM $table");
        $count = $result->fetch()['cnt'];
        
        if ($count > 0) {
            // Truncate new table first (clear any existing data)
            $preRegDb->exec("TRUNCATE TABLE $table");
            
            // Migrate data
            $stmt = $mainDb->query("SELECT * FROM $table");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($table === 'PreReg_Students') {
                foreach ($rows as $row) {
                    $preRegDb->prepare("
                        INSERT INTO PreReg_Students 
                        (email, roll, full_name, contact, session, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $row['email'],
                        $row['roll'],
                        $row['full_name'],
                        $row['contact'],
                        $row['session'],
                        $row['created_at'] ?? date('Y-m-d H:i:s'),
                        $row['updated_at'] ?? date('Y-m-d H:i:s')
                    ]);
                }
            } elseif ($table === 'PreReg_Teachers') {
                foreach ($rows as $row) {
                    $preRegDb->prepare("
                        INSERT INTO PreReg_Teachers 
                        (email, designation, full_name, contact, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $row['email'],
                        $row['designation'],
                        $row['full_name'],
                        $row['contact'],
                        $row['created_at'] ?? date('Y-m-d H:i:s'),
                        $row['updated_at'] ?? date('Y-m-d H:i:s')
                    ]);
                }
            } else {
                foreach ($rows as $row) {
                    $preRegDb->prepare("
                        INSERT INTO $table 
                        (email, full_name, contact, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([
                        $row['email'],
                        $row['full_name'],
                        $row['contact'],
                        $row['created_at'] ?? date('Y-m-d H:i:s'),
                        $row['updated_at'] ?? date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            echo "✓ Migrated $count records from $table\n";
        } else {
            echo "ℹ No data in $table (this is okay if starting fresh)\n";
        }
    }
    
    echo "\n✓ Pre-registration database migration completed successfully!\n";
    echo "✓ The pre-registration tables are now in: iit_shelf_prereg database\n";
    echo "✓ Teachers table now has designation column\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
