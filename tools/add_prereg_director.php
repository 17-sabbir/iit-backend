<?php
// CLI: Seed a director into PreReg_Directors
// Usage: php backend/tools/add_prereg_director.php <email> [full_name] [contact]

require_once __DIR__ . '/../config/db_pre_registration.php';

function logLine($m){ echo '['.date('Y-m-d H:i:s').'] '.$m.PHP_EOL; }

if ($argc < 2) {
    logLine('Usage: php backend/tools/add_prereg_director.php <email> [full_name] [contact]');
    exit(1);
}
$email = strtolower(trim($argv[1]));
$fullName = isset($argv[2]) ? trim($argv[2]) : 'Director User';
$contact = isset($argv[3]) ? trim($argv[3]) : '';

$preDb = (new PreRegistrationDatabase())->connect();
if (!$preDb) { logLine('ERROR: Could not connect to prereg DB'); exit(1);} 

$preDb->exec('CREATE TABLE IF NOT EXISTS PreReg_Directors (email VARCHAR(150) PRIMARY KEY, full_name VARCHAR(150) NOT NULL, contact VARCHAR(20) NULL)');

$stmt = $preDb->prepare('SELECT email FROM PreReg_Directors WHERE email = :email');
$stmt->execute([':email' => $email]);
if ($stmt->fetch()) {
    logLine('Already exists: '.$email);
    exit(0);
}

$ins = $preDb->prepare('INSERT INTO PreReg_Directors (email, full_name, contact) VALUES (:email, :full_name, :contact)');
$ins->execute([':email'=>$email, ':full_name'=>$fullName, ':contact'=>$contact]);
logLine('Inserted prereg director: '.$email.' ('.$fullName.')');
