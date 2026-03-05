<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

ob_start();
try {
    include 'dashboard_admin.php';
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage();
}
$out = ob_get_clean();
echo "== OUTPUT LENGTH ==\n" . strlen($out) . "\n";
echo "== START 500 CHARACTERS ==\n" . substr($out, 0, 500) . "\n";
?>
