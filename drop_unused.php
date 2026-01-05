<?php
require_once 'db.php';

$tablesToDrop = [
    'active_storage_attachments',
    'active_storage_blobs',
    'active_storage_variant_records',
    'cache',
    'cache_locks',
    'failed_jobs',
    'job_batches',
    'jobs',
    'migrations',
    'password_reset_tokens',
    'password_resets',
    'personal_access_tokens',
    'sessions'
];

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Disable foreign key checks to avoid constraint errors during drop
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($tablesToDrop as $table) {
        // Check if table exists first to avoid errors
        $check = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($check->rowCount() > 0) {
            echo "Dropping table: $table... ";
            $pdo->exec("DROP TABLE `$table`");
            echo "Done.\n";
        } else {
            echo "Table $table does not exist.\n";
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Unused tables removal complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
