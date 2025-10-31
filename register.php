<?php
require __DIR__ . '/config_mysqli.php'; 
  session_start();
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = "";

function e($str){ return htmlspecialchars($str ?? "", ENT_QUOTES, "UTF-8"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $errors[] = "CSRF token ไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองอีกครั้ง";
  }

  $password  = $_POST['password'] ?? "";
  $email     = trim($_POST['email'] ?? "");
  $display_name = trim($_POST['name'] ?? "");

  if (strlen($password) < 8) {
    $errors[] = "รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร";
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "อีเมลไม่ถูกต้อง";
  }
  if ($display_name === "" || mb_strlen($display_name) > 100) {
    $errors[] = "กรุณากรอกชื่อ–นามสกุล (ไม่เกิน 100 ตัวอักษร)";
  }

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

  if (!$errors) {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)";
    if ($stmt = $mysqli->prepare($sql)) {
      $stmt->bind_param("sss", $email, $display_name, $password_hash);
      if ($stmt->execute()) {
        $success = "สมัครสมาชิกสำเร็จ! คุณสามารถล็อกอินได้แล้วค่ะ 💕";
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

  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&family=Prompt:wght@400;600&display=swap" rel="stylesheet">

  <style>
    body {
      font-family: 'Prompt', sans-serif;
      background: linear-gradient(135deg, #ffe6f0 0%, #e3f2fd 100%);
      margin: 0; padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      overflow: hidden;
      position: relative;
    }
    .container {
      max-width: 450px;
      width: 90%;
      background: #fff;
      border-radius: 25px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.1);
      padding: 35px 40px;
      text-align: center;
      animation: fadeIn 0.5s ease-in-out;
      z-index: 10;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    h1 {
      margin-bottom: 18px;
      color: #f48fb1;
      font-weight: 700;
      font-size: 26px;
    }
    .alert {
      padding: 12px 14px;
      border-radius: 12px;
      margin-bottom: 12px;
      font-size: 14px;
      text-align: left;
    }
    .alert.error {
      background: #ffecec;
      color: #b71c1c;
      border: 1px solid #ffc9c9;
    }
    .alert.success {
      background: #e8f5e9;
      color: #1b5e20;
      border: 1px solid #c8e6c9;
    }
    label {
      display: block;
      font-size: 14px;
      text-align: left;
      margin: 12px 0 5px;
      color: #444;
      font-weight: 600;
    }
    input {
      width: 100%;
      padding: 12px;
      border-radius: 12px;
      border: 1px solid #ddd;
      font-size: 15px;
      background: #fafafa;
      transition: border 0.2s;
    }
    input:focus {
      border-color: #f48fb1;
      outline: none;
      box-shadow: 0 0 0 2px #f8bbd0;
    }
    button {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 12px;
      margin-top: 16px;
      background: linear-gradient(90deg, #f48fb1 0%, #f06292 100%);
      color: white;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      font-size: 16px;
    }
    button:hover {
      transform: translateY(-2px);
      filter: brightness(1.05);
    }
    .hint {
      font-size: 12px;
      color: #666;
      text-align: left;
    }

    .login-link {
      margin-top: 15px;
      font-size: 15px;
      text-align: center;
    }
    .login-link a {
      color: #f06292;
      text-decoration: none;
      font-weight: 600;
    }
    .login-link a:hover {
      text-decoration: underline;
    }

    .emoji {
      position: absolute;
      top: -2rem;
      font-size: 2rem;
      opacity: 0.8;
      animation: fall linear forwards;
    }
    @keyframes fall {
      from { transform: translateY(0) rotate(0deg); opacity: 1; }
      to { transform: translateY(110vh) rotate(360deg); opacity: 0; }
    }
  </style>
</head>
<body>
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

      <label>อีเมล</label>
      <input type="email" name="email" value="<?= e($email ?? "") ?>" required>

      <label>ชื่อ–นามสกุล</label>
      <input type="text" name="name" value="<?= e($display_name ?? "") ?>" required>

      <label>รหัสผ่าน</label>
      <input type="password" name="password" required>
      <div class="hint">รหัสผ่านอย่างน้อย 8 ตัวอักษร</div>

      <button type="submit">สมัครสมาชิก!</button>
    </form>

    <p class="login-link">มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
  </div>

  <script>
    const emojis = ['🌸','🌷','🌼','💖','✨','🌹','🩷','💫','🌺'];
    function createEmoji() {
      const el = document.createElement('div');
      el.classList.add('emoji');
      el.textContent = emojis[Math.floor(Math.random() * emojis.length)];
      el.style.left = Math.random() * 100 + 'vw';
      el.style.animationDuration = (3 + Math.random() * 6) + 's';
      el.style.fontSize = (1.5 + Math.random() * 1.5) + 'rem';
      document.body.appendChild(el);
      setTimeout(() => el.remove(), 7000);
    }
    setInterval(createEmoji, 600);
  </script>
</body>
</html>
