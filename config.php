<?php
// config.php — dùng chung cho tất cả các file
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // username MySQL của bạn
define('DB_PASS', '');           // password MySQL của bạn
define('DB_NAME', 'du_lich_gia_lai');

function ketNoi() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');

    if ($conn->connect_error) {
        die(json_encode(['loi' => 'Không kết nối được database']));
    }
    return $conn;
}
?>