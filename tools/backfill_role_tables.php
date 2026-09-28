<?php
// CLI script to backfill Students and Teachers tables from Users using pre-registration data
// Usage: php backend/tools/backfill_role_tables.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/db_pre_registration.php';

function logLine($msg) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

$database = new Database();
$pdo = $database->getConnection();
if (!$pdo) {
    logLine('ERROR: Could not connect to main database.');
    exit(1);
}

$preDb = new PreRegistrationDatabase();
$pre = $preDb->connect();
if (!$pre) {
    logLine('ERROR: Could not connect to pre-registration database.');
    exit(1);
}

// Check initial counts
$studentsCount = (int)$pdo->query('SELECT COUNT(*) FROM Students')->fetchColumn();
$teachersCount = (int)$pdo->query('SELECT COUNT(*) FROM Teachers')->fetchColumn();
logLine("Initial counts → Students: {$studentsCount}, Teachers: {$teachersCount}");

$insertedStudents = 0;
$insertedTeachers = 0;

// Backfill Students
logLine('Scanning Users with role=Student missing in Students...');
$missingStudentsStmt = $pdo->query("SELECT email FROM Users WHERE role = 'Student' AND email NOT IN (SELECT email FROM Students)");
$missingStudents = $missingStudentsStmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($missingStudents as $email) {
    // Fetch prereg student info
    $preStmt = $pre->prepare('SELECT roll, session FROM PreReg_Students WHERE email = :email');
    $preStmt->execute([':email' => $email]);
    $info = $preStmt->fetch();

    if (!$info || empty($info['roll'])) {
        logLine("WARN: No prereg student info found or missing roll for email={$email}; skipping.");
        continue;
    }

    // Insert into Students
    try {
        $ins = $pdo->prepare('INSERT INTO Students (email, roll, session) VALUES (:email, :roll, :session)');
        $ins->execute([
            ':email' => $email,
            ':roll' => $info['roll'],
            ':session' => $info['session'] ?? null,
        ]);
        $insertedStudents++;
        logLine("OK: Inserted student record for {$email} (roll={$info['roll']}, session=" . ($info['session'] ?? 'NULL') . ")");
    } catch (Exception $e) {
        logLine("ERROR: Failed inserting student {$email}: " . $e->getMessage());
    }
}

// Backfill Teachers
logLine('Scanning Users with role=Teacher missing in Teachers...');
$missingTeachersStmt = $pdo->query("SELECT email FROM Users WHERE role = 'Teacher' AND email NOT IN (SELECT email FROM Teachers)");
$missingTeachers = $missingTeachersStmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($missingTeachers as $email) {
    // Fetch prereg teacher info
    $preStmt = $pre->prepare('SELECT designation FROM PreReg_Teachers WHERE email = :email');
    $preStmt->execute([':email' => $email]);
    $info = $preStmt->fetch();

    if (!$info) {
        logLine("WARN: No prereg teacher info found for email={$email}; skipping.");
        continue;
    }

    // Insert into Teachers
    try {
        $ins = $pdo->prepare('INSERT INTO Teachers (email, designation) VALUES (:email, :designation)');
        $ins->execute([
            ':email' => $email,
            ':designation' => $info['designation'] ?? null,
        ]);
        $insertedTeachers++;
        logLine("OK: Inserted teacher record for {$email} (designation=" . ($info['designation'] ?? 'NULL') . ")");
    } catch (Exception $e) {
        logLine("ERROR: Failed inserting teacher {$email}: " . $e->getMessage());
    }
}

// Final counts
$studentsCountFinal = (int)$pdo->query('SELECT COUNT(*) FROM Students')->fetchColumn();
$teachersCountFinal = (int)$pdo->query('SELECT COUNT(*) FROM Teachers')->fetchColumn();
logLine("Final counts → Students: {$studentsCountFinal} (inserted {$insertedStudents}), Teachers: {$teachersCountFinal} (inserted {$insertedTeachers})");

// Exit code indicates success if any changes or already populated
exit(0);
