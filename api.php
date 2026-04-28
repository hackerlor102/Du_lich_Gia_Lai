<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$conn   = ketNoi();
$loai   = $_GET['loai'] ?? ''; // lọc theo loại nếu cần

if (!empty($loai)) {
    $stmt = $conn->prepare("SELECT * FROM dia_diem WHERE loai = ?");
    $stmt->bind_param('s', $loai);
    $stmt->execute();
    $ketQua = $stmt->get_result();
} else {
    $ketQua = $conn->query("SELECT * FROM dia_diem ORDER BY ten");
}

$danhSach = $ketQua->fetch_all(MYSQLI_ASSOC);
$conn->close();

echo json_encode($danhSach, JSON_UNESCAPED_UNICODE);
?>