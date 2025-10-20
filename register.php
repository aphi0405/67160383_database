<?php
require __DIR__ . '/config_mysqli.php';

// เปิด session เพื่อใช้ CSRF token และ flash message
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// สร้าง CSRF token ครั้งแรก
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = "";

// ฟังก์ชันเล็ก ๆ กัน XSS เวลา echo ค่าเดิมกลับฟอร์ม
function e($str){ return htmlspecialchars($str ?? "", ENT_QUOTES, "UTF-8"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ตรวจ CSRF token
  if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $errors[] = "CSRF token ไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองอีกครั้ง";
  }

  // รับค่าจากฟอร์ม
  $password  = $_POST['password'] ?? "";
  $email     = trim($_POST['email'] ?? "");
  $display_name = trim($_POST['name'] ?? "");

  // ตรวจความถูกต้องเบื้องต้น
  if (strlen($password) < 8) {
    $errors[] = "รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร";
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "อีเมลไม่ถูกต้อง";
  }
  if ($display_name === "" || mb_strlen($display_name) > 100) {
    $errors[] = "กรุณากรอกชื่อ–นามสกุล (ไม่เกิน 100 ตัวอักษร)";
  }

  // ตรวจอีเมลซ้ำ
  if (!$errors) {
    $sql = "SELECT 1 FROM users WHERE email = ? LIMIT 1";
    if ($stmt = $mysqli->prepare($sql)) {
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $stmt->store_result();
      if ($stmt->num_rows > 0) {
        $errors[] = "อีเมลนี้ถูกใช้แล้ว";
      }
      $stmt->close();
    } else {
      $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบข้อมูล (prepare)";
    }
  }

  // บันทึกลงฐานข้อมูล
  if (!$errors) {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)";
    if ($stmt = $mysqli->prepare($sql)) {
      $stmt->bind_param("sss", $email, $display_name, $password_hash);
      if ($stmt->execute()) {
        $success = "สมัครสมาชิกสำเร็จ! คุณสามารถล็อกอินได้แล้วค่ะ";
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $email = $display_name = "";
      } else {
        if ($mysqli->errno == 1062) {
          $errors[] = "อีเมลซ้ำ กรุณาใช้อีเมลอื่น";
        } else {
          $errors[] = "บันทึกข้อมูลไม่สำเร็จ: " . e($mysqli->error);
        }
      }
      $stmt->close();
    } else {
      $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล (prepare)";
    }
  }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register</title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Prompt', sans-serif;
      background: linear-gradient(135deg, #fce4ec, #f3e5f5, #e3f2fd);
      margin:0; padding:0;
      height: 100vh;
      overflow: hidden;
      position: relative;
    }

    .float-emoji {
      position: absolute;
      top: -50px;
      font-size: 30px;
      opacity: 0;
      animation: fall 10s linear infinite;
      pointer-events: none;
      z-index: 0;
    }
    @keyframes fall {
      0% { transform: translateY(-100px) rotate(0deg); opacity: 0; }
      10% { opacity: 0.9; }
      90% { opacity: 0.9; }
      100% { transform: translateY(110vh) rotate(360deg); opacity: 0; }
    }

    .container {
      position: relative;
      z-index: 5;
      max-width:480px;
      margin:40px auto;
      background:#fff;
      border-radius:16px;
      padding:24px;
      box-shadow:0 10px 30px rgba(0,0,0,.06);
    }

    h1{margin:0 0 16px; text-align:center; color:#f48fb1;}
    .alert{padding:12px 14px; border-radius:12px; margin-bottom:12px; font-size:14px;}
    .alert.error{background:#ffecec; color:#a40000; border:1px solid #ffc9c9;}
    .alert.success{background:#efffed; color:#0a7a28; border:1px solid #c9f5cf;}
    label{display:block; font-size:14px; margin:10px 0 6px;}
    input{width:100%; padding:12px; border-radius:12px; border:1px solid #ddd;}
    button{
      width:100%; padding:12px; border:none; border-radius:12px;
      margin-top:14px; background:linear-gradient(90deg,#f8bbd0,#f48fb1);
      color:#fff; font-weight:600; cursor:pointer; transition:transform .2s;
    }
    button:hover{transform:scale(1.03);}
    .hint{font-size:12px; color:#666;}
  </style>
</head>
<body>

  <div class="float-emoji" style="left:10%; animation-delay:0s;">🧁</div>
  <div class="float-emoji" style="left:25%; animation-delay:2s;">🌷</div>
  <div class="float-emoji" style="left:45%; animation-delay:4s;">💫</div>
  <div class="float-emoji" style="left:65%; animation-delay:1s;">🩷</div>
  <div class="float-emoji" style="left:80%; animation-delay:3s;">🦄</div>

  <div class="container">
    <h1>สมัครสมาชิก</h1>

    <?php if ($errors): ?>
      <div class="alert error">
        <?php foreach ($errors as $m) echo "<div>".e($m)."</div>"; ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <label>Email</label>
      <input type="email" name="email" value="<?= e($email ?? "") ?>" required>

      <label>ชื่อ–นามสกุล</label>
      <input type="text" name="name" value="<?= e($display_name ?? "") ?>" required>

      <label>Password</label>
      <input type="password" name="password" required>
      <div class="hint">อย่างน้อย 8 ตัวอักษร</div>

      <button type="submit">สมัครสมาชิก</button>
    </form>
  </div>
</body>
</html>
