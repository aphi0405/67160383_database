<?php 
require __DIR__ . '/config_mysqli.php'; 
require __DIR__ . '/csrf.php'; 
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Prompt', sans-serif;
      background: linear-gradient(135deg, #ffe6f0 0%, #e3f2fd 100%);
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
      overflow: hidden; 
      position: relative;
    }
    .login-container {
      width: 90%;
      max-width: 430px;
      background: #fff;
      border-radius: 25px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.08);
      padding: 35px 40px;
      text-align: center;
      animation: fadeIn 0.6s ease;
      z-index: 10;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    h1 { color: #f06292; font-weight: 700; margin-bottom: 20px; }
    .alert {
      background: #ffe6e9; color: #c62828;
      border: 1px solid #ffcdd2; border-radius: 12px;
      padding: 10px; margin-bottom: 15px; font-size: 14px; text-align: left;
    }
    label { display: block; text-align: left; font-weight: 600; margin-bottom: 5px; color: #444; }
    input {
      width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #ddd;
      background: #fafafa; font-size: 15px; transition: border 0.2s;
    }
    input:focus { border-color: #f48fb1; outline: none; box-shadow: 0 0 0 2px #f8bbd0; }
    button {
      width: 100%; padding: 12px; border: none; border-radius: 12px;
      background: linear-gradient(90deg, #f48fb1 0%, #f06292 100%);
      color: white; font-weight: 600; font-size: 16px;
      margin-top: 18px; cursor: pointer; transition: all 0.25s ease;
    }
    button:hover { transform: translateY(-2px); filter: brightness(1.07); }
    .forgot { text-align: right; margin-top: 5px; }
    .forgot a { font-size: 13px; color: #f06292; text-decoration: none; }
    .forgot a:hover { text-decoration: underline; }
    .note { margin-top: 12px; font-size: 13px; color: #666; }

    .emoji {
      position: absolute;
      top: -2rem;
      font-size: 2rem;
      opacity: 0.9;
      animation: fall linear forwards;
    }
    @keyframes fall {
      from { transform: translateY(0) rotate(0deg); opacity: 1; }
      to { transform: translateY(110vh) rotate(360deg); opacity: 0; }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h1>เข้าสู่ระบบ</h1>

    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="alert"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>

    <form method="post" action="login_process.php" novalidate>
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">

      <label for="email">อีเมล</label>
      <input type="email" id="email" name="email" placeholder="you@example.com" required>

      <label for="password">รหัสผ่าน</label>
      <input type="password" id="password" name="password" placeholder="••••••••" required>

      <div class="forgot">
        <a href="#" onclick="alert('กรุณาติดต่อผู้ดูแลระบบเพื่อรีเซ็ตรหัสผ่านนะคะ 💬'); return false;">ลืมรหัสผ่าน?</a>
      </div>

      <button type="submit">เข้าสู่ระบบ</button>
    </form>

    <p class="note">ยังไม่มีบัญชี? <a href="register.php" style="color:#f06292;text-decoration:none;font-weight:600;">สมัครสมาชิก</a></p>
  </div>

  <script>
    const emojis = ['🌸','🌼','💖','🌷','🌻','💫','✨','🌹','🩷'];
    function createEmoji() {
      const el = document.createElement('div');
      el.classList.add('emoji');
      el.textContent = emojis[Math.floor(Math.random() * emojis.length)];
      el.style.left = Math.random() * 100 + 'vw';
      el.style.animationDuration = (3 + Math.random() * 5) + 's';
      el.style.fontSize = (1.5 + Math.random() * 1.5) + 'rem';
      document.body.appendChild(el);
      setTimeout(() => el.remove(), 7000);
    }
    setInterval(createEmoji, 600);
  </script>
</body>
</html>
