<?php
require_once 'config.php';

$thongBao = '';
$loiNhap  = [];

// Xử lý khi submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ten      = trim($_POST['ten'] ?? '');
    $mo_ta    = trim($_POST['mo_ta'] ?? '');
    $dia_chi  = trim($_POST['dia_chi'] ?? '');
    $lat      = floatval($_POST['lat'] ?? 0);
    $lng      = floatval($_POST['lng'] ?? 0);
    $loai     = $_POST['loai'] ?? 'du_lich';

    // Validate dữ liệu
    if (empty($ten))    $loiNhap[] = 'Tên địa điểm không được để trống';
    if ($lat == 0)      $loiNhap[] = 'Chưa chọn vị trí trên bản đồ';

    if (empty($loiNhap)) {
        $conn = ketNoi();
        $stmt = $conn->prepare(
            "INSERT INTO dia_diem (ten, mo_ta, dia_chi, lat, lng, loai)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sssdds', $ten, $mo_ta, $dia_chi, $lat, $lng, $loai);

        if ($stmt->execute()) {
            $thongBao = 'success';
        } else {
            $thongBao = 'error';
        }
        $stmt->close();
        $conn->close();
    }
}

// Lấy danh sách đã nhập để hiển thị
$conn     = ketNoi();
$ketQua   = $conn->query("SELECT * FROM dia_diem ORDER BY ngay_tao DESC");
$danhSach = $ketQua->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quản lý địa điểm — Gia Lai</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: sans-serif; background: #f5f5f5; }

    .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
    h1 { font-size: 22px; margin-bottom: 20px; color: #333; }

    /* Layout 2 cột */
    .layout { display: flex; gap: 20px; }
    .cot-trai { width: 380px; flex-shrink: 0; }
    .cot-phai { flex: 1; }

    /* Form */
    .form-card {
      background: #fff;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
      margin-bottom: 16px;
    }
    .form-card h2 { font-size: 16px; margin-bottom: 16px; color: #444; }

    label { display: block; font-size: 13px; color: #555; margin-bottom: 4px; margin-top: 12px; }
    label:first-of-type { margin-top: 0; }

    input[type=text], textarea, select {
      width: 100%; padding: 9px 12px;
      border: 1px solid #ddd; border-radius: 8px;
      font-size: 14px; font-family: sans-serif;
    }
    input[type=text]:focus, textarea:focus, select:focus {
      outline: none; border-color: #4a90e2;
    }
    textarea { height: 80px; resize: vertical; }

    /* Toạ độ — hiển thị đọc-only, tự điền khi click map */
    .toa-do-row { display: flex; gap: 8px; }
    .toa-do-row input { background: #f9f9f9; color: #333; cursor: default; }

    .hint {
      font-size: 12px; color: #888; margin-top: 6px;
      padding: 8px 10px; background: #f0f7ff;
      border-radius: 6px; border-left: 3px solid #4a90e2;
    }

    button[type=submit] {
      width: 100%; margin-top: 16px; padding: 11px;
      background: #4a90e2; color: #fff;
      border: none; border-radius: 8px;
      font-size: 15px; cursor: pointer;
    }
    button[type=submit]:hover { background: #357abd; }

    /* Thông báo */
    .tb-success {
      padding: 10px 14px; border-radius: 8px; margin-bottom: 12px;
      background: #e6f9f0; color: #1a7a4a; border: 1px solid #9fe1cb;
    }
    .tb-error {
      padding: 10px 14px; border-radius: 8px; margin-bottom: 12px;
      background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5;
    }
    .tb-error ul { padding-left: 16px; margin-top: 4px; }

    /* Bản đồ chọn toạ độ */
    #map-chon { width: 100%; height: 420px; border-radius: 12px; }

    /* Danh sách */
    table { width: 100%; border-collapse: collapse; background: #fff;
            border-radius: 12px; overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
    th { background: #f0f0f0; padding: 10px 12px; font-size: 13px;
         text-align: left; color: #555; }
    td { padding: 10px 12px; font-size: 13px; border-top: 1px solid #f0f0f0; }
    tr:hover td { background: #fafafa; }

    .badge {
      display: inline-block; padding: 2px 8px;
      border-radius: 20px; font-size: 11px;
    }
    .badge-du_lich   { background:#dbeafe; color:#1e40af; }
    .badge-an_uong   { background:#dcfce7; color:#166534; }
    .badge-vui_choi  { background:#fef9c3; color:#854d0e; }
    .badge-nghi_duong{ background:#f3e8ff; color:#6b21a8; }
    .badge-khac      { background:#f1f5f9; color:#475569; }

    .btn-xoa {
      padding: 4px 10px; background: #fee2e2; color: #b91c1c;
      border: none; border-radius: 6px; cursor: pointer; font-size: 12px;
    }
    .btn-xoa:hover { background: #fca5a5; }

    @media(max-width: 768px) {
      .layout { flex-direction: column; }
      .cot-trai { width: 100%; }
    }
  </style>
</head>
<body>
<div class="container">
  <h1>Quản lý địa điểm du lịch — Gia Lai</h1>

  <div class="layout">
    <!-- CỘT TRÁI: Form nhập -->
    <div class="cot-trai">

      <?php if ($thongBao === 'success'): ?>
        <div class="tb-success">Thêm địa điểm thành công!</div>
      <?php elseif ($thongBao === 'error'): ?>
        <div class="tb-error">Có lỗi xảy ra, thử lại.</div>
      <?php endif; ?>

      <?php if (!empty($loiNhap)): ?>
        <div class="tb-error">
          <ul>
            <?php foreach ($loiNhap as $l): ?>
              <li><?= htmlspecialchars($l) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="form-card">
        <h2>Thêm địa điểm mới</h2>
        <form method="POST">

          <label>Tên địa điểm *</label>
          <input type="text" name="ten"
                 value="<?= htmlspecialchars($_POST['ten'] ?? '') ?>"
                 placeholder="VD: Biển Hồ Pleiku">

          <label>Loại hình</label>
          <select name="loai">
            <option value="du_lich">Du lịch / Thắng cảnh</option>
            <option value="an_uong">Ăn uống</option>
            <option value="vui_choi">Vui chơi giải trí</option>
            <option value="nghi_duong">Nghỉ dưỡng / Khách sạn</option>
            <option value="khac">Khác</option>
          </select>

          <label>Địa chỉ</label>
          <input type="text" name="dia_chi"
                 value="<?= htmlspecialchars($_POST['dia_chi'] ?? '') ?>"
                 placeholder="VD: TP. Pleiku, Gia Lai">

          <label>Mô tả ngắn</label>
          <textarea name="mo_ta"
                    placeholder="Mô tả ngắn về địa điểm..."><?= htmlspecialchars($_POST['mo_ta'] ?? '') ?></textarea>

          <label>Toạ độ (click vào bản đồ bên phải để chọn)</label>
          <div class="toa-do-row">
            <input type="text" name="lat" id="inp-lat"
                   value="<?= $_POST['lat'] ?? '' ?>"
                   placeholder="Latitude" readonly>
            <input type="text" name="lng" id="inp-lng"
                   value="<?= $_POST['lng'] ?? '' ?>"
                   placeholder="Longitude" readonly>
          </div>
          <p class="hint">Click thẳng vào bản đồ bên phải — toạ độ sẽ tự điền vào đây</p>

          <button type="submit">Thêm địa điểm</button>
        </form>
      </div>
    </div>

    <!-- CỘT PHẢI: Bản đồ chọn toạ độ -->
    <div class="cot-phai">
      <div id="map-chon"></div>
    </div>
  </div>

  <!-- BẢNG DANH SÁCH -->
  <h2 style="margin: 24px 0 12px; font-size:17px; color:#333;">
    Danh sách địa điểm (<?= count($danhSach) ?>)
  </h2>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Tên địa điểm</th>
        <th>Loại</th>
        <th>Địa chỉ</th>
        <th>Toạ độ</th>
        <th>Thao tác</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($danhSach as $i => $dd): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><strong><?= htmlspecialchars($dd['ten']) ?></strong></td>
        <td>
          <span class="badge badge-<?= $dd['loai'] ?>">
            <?= match($dd['loai']) {
              'du_lich'    => 'Du lịch',
              'an_uong'    => 'Ăn uống',
              'vui_choi'   => 'Vui chơi',
              'nghi_duong' => 'Nghỉ dưỡng',
              default      => 'Khác'
            } ?>
          </span>
        </td>
        <td><?= htmlspecialchars($dd['dia_chi'] ?? '—') ?></td>
        <td style="font-size:11px; color:#888;">
          <?= number_format($dd['lat'], 5) ?>,
          <?= number_format($dd['lng'], 5) ?>
        </td>
        <td>
          <form method="POST" action="xoa.php"
                onsubmit="return confirm('Xoá địa điểm này?')">
            <input type="hidden" name="id" value="<?= $dd['id'] ?>">
            <button class="btn-xoa" type="submit">Xoá</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  // Bản đồ chọn toạ độ — căn giữa Gia Lai
  var mapChon = L.map('map-chon').setView([13.8079, 108.1094], 9);

  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap'
  }).addTo(mapChon);

  // Hiển thị marker các địa điểm đã có
  var danhSachCu = <?= json_encode($danhSach) ?>;
  danhSachCu.forEach(function(dd) {
    L.marker([dd.lat, dd.lng])
     .addTo(mapChon)
     .bindPopup('<b>' + dd.ten + '</b>');
  });

  // Click vào map → tự điền toạ độ vào form
  var markerChon = null;
  mapChon.on('click', function(e) {
    var lat = e.latlng.lat.toFixed(7);
    var lng = e.latlng.lng.toFixed(7);

    document.getElementById('inp-lat').value = lat;
    document.getElementById('inp-lng').value = lng;

    // Xoá marker cũ, vẽ marker mới
    if (markerChon) mapChon.removeLayer(markerChon);
    markerChon = L.marker([lat, lng], {
      icon: L.divIcon({
        className: '',
        html: '<div style="width:14px;height:14px;background:#e74c3c;border:2px solid #fff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,0.3)"></div>',
        iconAnchor: [7, 7]
      })
    }).addTo(mapChon).bindPopup('Vị trí đã chọn').openPopup();
  });
</script>
</body>
</html>