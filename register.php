<?php
// ----------------------------------------------------
// ส่วนที่ 1: การตั้งค่าและการรวมไฟล์
// ----------------------------------------------------

// 1. นำเข้าไฟล์ config_mysqli.php (ไฟล์นี้จะสร้าง $mysqli และเรียก session_start() ให้แล้ว)
require __DIR__ . '/config_mysqli.php';

// 2. เปิดการแสดงผลข้อผิดพลาดทั้งหมดเพื่อให้เห็นปัญหาได้ทันที (สำหรับ Development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ไม่ต้องเรียก session_start() ซ้ำ เพราะถูกเรียกใน config_mysqli.php แล้ว

$errors = [];
$success = "";

// สร้าง CSRF token ครั้งแรก (ถ้า config_mysqli.php ยังไม่ได้เรียก session_start() ให้ย้ายบรรทัดนี้ไปหลัง session_start())
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ตอนนี้ตัวแปร $mysqli พร้อมใช้งานแล้ว ไม่ต้องเชื่อมต่อซ้ำ

// ฟังก์ชันเล็ก ๆ กัน XSS เวลา echo ค่าเดิมกลับฟอร์ม
function e($str){ return htmlspecialchars($str ?? "", ENT_QUOTES, "UTF-8"); }


// ----------------------------------------------------
// ส่วนที่ 2: การประมวลผลฟอร์ม (POST)
// ----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ตรวจ CSRF token
  if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $errors[] = "CSRF token ไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองอีกครั้ง";
  }

  // รับค่าจากฟอร์ม
  $username  = trim($_POST['username'] ?? "");
  $password  = $_POST['password'] ?? "";
  $email     = trim($_POST['email'] ?? "");
  $full_name = trim($_POST['name'] ?? "");

  // ตรวจความถูกต้องเบื้องต้น
  if ($username === "" || !preg_match('/^[A-Za-z0-9_\.]{3,30}$/', $username)) {
    $errors[] = "กรุณากรอก username 3–30 ตัวอักษร (a-z, A-Z, 0-9, _, .)";
  }
  if (strlen($password) < 8) {
    $errors[] = "รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร";
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "อีเมลไม่ถูกต้อง";
  }
  if ($full_name === "" || mb_strlen($full_name) > 100) {
    $errors[] = "กรุณากรอกชื่อ–นามสกุล (ไม่เกิน 100 ตัวอักษร)";
  }

  // ตรวจซ้ำ username/email
  if (!$errors) {
    // ใช้ $mysqli ที่มาจาก config_mysqli.php
    $sql = "SELECT 1 FROM users WHERE username = ? OR email = ? LIMIT 1";
    if ($stmt = $mysqli->prepare($sql)) {
      $stmt->bind_param("ss", $username, $email);
      $stmt->execute();
      $stmt->store_result();
      if ($stmt->num_rows > 0) {
        $errors[] = "Username หรือ Email นี้ถูกใช้แล้ว";
      }
      $stmt->close();
    } else {
      // **ข้อผิดพลาดนี้จะไม่เกิดแล้ว เพราะใช้ mysqli_report() ใน config_mysqli.php
      $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบข้อมูล (prepare)";
    }
  }

  // บันทึกลงฐานข้อมูล
  if (!$errors) {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)";
    // ใช้ $mysqli ที่มาจาก config_mysqli.php
    if ($stmt = $mysqli->prepare($sql)) {
      $stmt->bind_param("ssss", $username, $email, $password_hash, $full_name);
      
      try {
          if ($stmt->execute()) {
            $success = "สมัครสมาชิกสำเร็จ! คุณสามารถล็อกอินได้แล้วค่ะ";
            // regenerate CSRF token หลังสำเร็จ
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            // เคลียร์ฟอร์ม
            $username = $email = $full_name = "";
          }
      } catch (\mysqli_sql_exception $e) {
          // ตรวจจับ duplicate (Error Code 1062)
          if ($e->getCode() == 1062) {
            $errors[] = "Username/Email ซ้ำ กรุณาใช้ค่าอื่น";
          } else {
            // Error อื่นๆ (เช่น Field name ผิด) จะถูกจับที่นี่
            $errors[] = "บันทึกข้อมูลไม่สำเร็จ: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8");
          }
      }

      $stmt->close();
    } else {
        // **ข้อผิดพลาดนี้จะไม่เกิดแล้ว เพราะใช้ mysqli_report() ใน config_mysqli.php
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
  <style>
    /* 🎨 ปรับแต่งตามคำขอ */
    body{
        font-family:system-ui, sans-serif; 
        background:#FFF5EE; 
        margin:0; 
        padding:0;
        color: #696969; 
        
        /* 🔥 ปรับตรงกลางจอด้วย Flexbox */
        display: flex;
        justify-content: center; /* กึ่งกลางแนวนอน */
        align-items: center; /* กึ่งกลางแนวตั้ง */
        min-height: 100vh; /* กำหนดความสูงเต็มหน้าจอ */
    }
    .container{
        max-width:480px; 
        /* ลบ margin:40px auto; เพราะใช้ flexbox แทน */
        background:#F5F5F5; 
        border-radius:16px; 
        padding:24px; 
        box-shadow:0 10px 30px rgba(0,0,0,.06);
        width: 90%; /* ช่วยให้กรอบไม่เล็กเกินไปบนจอมือถือ */
    }
    h1{
        margin:0 0 16px;
        color: #2F4F4F; 
    }
    .alert{padding:12px 14px; border-radius:12px; margin-bottom:12px; font-size:14px;}
    .alert.error{background:#ffecec; color:#a40000; border:1px solid #ffc9c9;}
    .alert.success{background:#efffed; color:#0a7a28; border:1px solid #c9f5cf;}
    label{display:block; font-size:14px; margin:10px 0 6px; color: #696969;} 
    input{width:100%; padding:12px; border-radius:12px; border:1px solid #ddd;}
    button{
        width:100%; 
        padding:12px; 
        border:none; 
        border-radius:12px; 
        margin-top:14px; 
        background:#E0FFFF; 
        color:#2F4F4F; 
        font-weight:600; 
        cursor:pointer;
    }
    button:hover{filter:brightness(.95);}
    .hint{font-size:12px; color:#696969;} 
    /* 🎨 สิ้นสุดการปรับแต่ง */
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
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
      <label>Username</label>
      <input type="text" name="username" value="<?= e($username ?? "") ?>" required>
      <div class="hint">อนุญาต a-z, A-Z, 0-9, _ และ . (3–30 ตัว)</div>

      <label>Password</label>
      <input type="password" name="password" required>
      <div class="hint">อย่างน้อย 8 ตัวอักษร</div>

      <label>Email</label>
      <input type="email" name="email" value="<?= e($email ?? "") ?>" required>

      <label>ชื่อ–นามสกุล</label>
      <input type="text" name="name" value="<?= e($full_name ?? "") ?>" required>

      <button type="submit">สมัครสมาชิก</button>
    </form>
  </div>
</body>
</html>