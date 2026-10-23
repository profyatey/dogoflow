<?php
// DELETE THIS FILE after you're done — never leave it on production
require_once dirname(__DIR__) . '/core/bootstrap.php';

$pdo = db();
$row = $pdo->query('SELECT DATABASE() as db')->fetch();
echo 'Connected to database: <strong>' . $row['db'] . '</strong>';

// Also show products table columns
echo '<br><br>Columns in products table:<br>';
$cols = $pdo->query('DESCRIBE products')->fetchAll(PDO::FETCH_COLUMN);
foreach ($cols as $col) echo '- ' . $col . '<br>';
?>