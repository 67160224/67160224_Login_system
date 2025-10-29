<?php require __DIR__ . '/config_mysqli.php'; require __DIR__ . '/csrf.php'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            /* สีพื้นหลัง: #FFF5EE */
            background-color: #FFF5EE !important; 
            /* ใช้ฟอนต์ Nunito */
            font-family: 'Nunito', sans-serif; 
            color: #696969; /* สีตัวหนังสือทั่วไป */
        }
        .login-card { 
            max-width: 420px; 
            width: 100%; 
        }
        .card {
            /* สีพื้นหลังกรอบด้านใน: #F5F5F5 */
            background-color: #F5F5F5 !important;
            border: none; /* ทำให้เรียบง่ายขึ้น */
        }
        .h4 {
            /* สีตัวหนังสือคำว่า Welcome: #2F4F4F */
            color: #2F4F4F !important;
            font-weight: 700; /* ทำให้เด่นขึ้น */
        }
        .form-label, .small, .text-muted {
            /* สีตัวหนังสือทั่วไป */
            color: #696969 !important;
        }
        .btn-primary {
            /* สีปุ่ม Sign in: #E0FFFF */
            background-color: #E0FFFF !important;
            border-color: #E0FFFF !important;
            /* สีตัวหนังสือปุ่มเป็นสีเข้มเพื่อให้มองเห็นได้ */
            color: #2F4F4F !important; 
            font-weight: 700;
        }
        .form-control {
            border-radius: .5rem; /* ทำให้ดูโค้งมนและเป็นมิตรมากขึ้น */
        }
    </style>
</head>
<body class="bg-light">
    <main class="container d-flex justify-content-center">
        <div class="card shadow-sm login-card p-3 p-md-4">
            <div class="card-body">
                <h1 class="h4 mb-3 text-center">Welcome</h1>

                <?php if (!empty($_SESSION['flash'])): ?>
                    <div class="alert alert-danger py-2"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
                <?php endif; ?>

                <form method="post" action="login_process.php" novalidate>
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" type="email" id="email" name="email" placeholder="you@example.com" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label d-flex justify-content-between" for="password">
                            <span>Password</span>
                            <a href="#" class="small text-decoration-none" onclick="alert('Ask admin to reset');return false;">Forgot?</a>
                        </label>
                        <input class="form-control" type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                    <div class="d-grid mt-3">
                        <button class="btn btn-primary" type="submit">Sign in</button>
                    </div>
                </form>

                <p class="text-center text-muted mt-3 mb-0 small">Demo only — do not use weak passwords.</p>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>