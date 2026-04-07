<?php
// Password to hash
$password = 'sandee3p1';

// Generate bcrypt hash with cost factor 12
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Output the result
echo "Generated Hash: " . $hash . PHP_EOL;
?>