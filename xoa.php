<?php
require_once 'config.php';

$id = intval($_POST['id'] ?? 0);
if ($id > 0) {
    $conn = ketNoi();
    $stmt = $conn->prepare("DELETE FROM dia_diem WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

header('Location: admin.php');
exit;
?>