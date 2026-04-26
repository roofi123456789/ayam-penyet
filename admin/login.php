<?php
require_once '../koneksi.php';

// Sudah login? Redirect ke dashboard
if (isAdminLoggedIn()) {
    redirect('/ayam-penyet/admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_nama'] = $admin['nama'];
            $_SESSION['admin_user'] = $admin['username'];
            setFlash('success', 'Selamat datang, ' . $admin['nama'] . '!');
            redirect('/ayam-penyet/admin/dashboard.php');
        } else {
            $error = 'Username atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>

        /* ANIMASI LOGO */
.logo-box {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 38px;
    margin: 0 auto 16px;
    box-shadow: 0 12px 32px rgba(232,64,64,0.4);

    /* tambahan animasi */
    animation: float 3s ease-in-out infinite, glow 2.5s ease-in-out infinite alternate;
    transition: transform 0.3s ease;
}

/* efek hover */
.logo-box:hover {
    transform: scale(1.1) rotate(5deg);
}

/* animasi naik turun */
@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}

/* efek glow */
@keyframes glow {
    0% {
        box-shadow: 0 12px 32px rgba(232,64,64,0.4);
    }
    100% {
        box-shadow: 0 18px 40px rgba(232,64,64,0.7);
    }
}

        :root {
            --primary: #E84040;
            --primary-dark: #C42E2E;
            --dark: #1A1A2E;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #1A1A2E 0%, #16213E 50%, #0F3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo-box {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 24px;
            display: flex; align-items: center; justify-content: center;
            font-size: 38px;
            margin: 0 auto 16px;
            box-shadow: 0 12px 32px rgba(232,64,64,0.4);
        }
        .login-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            color: white;
            margin: 0 0 6px;
        }
        .login-header p { color: rgba(255,255,255,0.55); font-size: 13px; margin: 0; }

        .login-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 32px 28px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 700;
            color: rgba(255,255,255,0.7);
            margin-bottom: 8px;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 18px;
        }
        .input-icon {
            position: absolute;
            left: 16px; top: 50%; transform: translateY(-50%);
            color: rgba(255,255,255,0.4);
            font-size: 15px;
            z-index: 1;
        }
        .form-control-custom {
            width: 100%;
            background: rgba(255,255,255,0.08);
            border: 1.5px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            padding: 14px 16px 14px 44px;
            color: white;
            font-size: 14px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            outline: none;
            transition: all 0.2s;
        }
        .form-control-custom:focus {
            border-color: var(--primary);
            background: rgba(255,255,255,0.1);
            box-shadow: 0 0 0 3px rgba(232,64,64,0.2);
        }
        .form-control-custom::placeholder { color: rgba(255,255,255,0.3); }

        .toggle-pass {
            position: absolute;
            right: 14px; top: 50%; transform: translateY(-50%);
            color: rgba(255,255,255,0.4);
            cursor: pointer;
            border: none; background: none;
            font-size: 15px;
            transition: color 0.2s;
        }
        .toggle-pass:hover { color: rgba(255,255,255,0.8); }

        .alert-error {
            background: rgba(232,64,64,0.15);
            border: 1px solid rgba(232,64,64,0.3);
            border-radius: 10px;
            padding: 12px 16px;
            color: #FF8A8A;
            font-size: 13px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
            transition: all 0.2s ease;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(232,64,64,0.45);
        }

        .default-cred {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: rgba(255,255,255,0.35);
        }
        .default-cred code {
            background: rgba(255,255,255,0.08);
            padding: 2px 6px;
            border-radius: 4px;
            color: rgba(255,255,255,0.55);
        }

        .footer-link {
            text-align: center;
            margin-top: 20px;
        }
        .footer-link a {
            color: rgba(255,255,255,0.45);
            font-size: 12px;
            text-decoration: none;
            transition: color 0.2s;
        }
        .footer-link a:hover { color: rgba(255,255,255,0.75); }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-header">
        <div class="logo-box">🍗</div>
        <h1> Admin</h1>
        <p><?= APP_NAME ?></p>
    </div>

    <div class="login-card">
        <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div>
                <label class="form-label">Username</label>
                <div class="input-group-custom">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="username" class="form-control-custom"
                           placeholder="Masukkan username" 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autocomplete="username" required>
                </div>
            </div>

            <div>
                <label class="form-label">Password</label>
                <div class="input-group-custom">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="passwordInput" class="form-control-custom"
                           placeholder="Masukkan password" autocomplete="current-password" required>
                    <button type="button" class="toggle-pass" onclick="togglePassword()">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>Masuk ke Dashboard
            </button>
        </form>

        <div class="default-cred">
            Default: <code>admin</code> / <code>password</code>
        </div>
    </div>

    <div class="footer-link">
        <a href="../index.php">← Kembali ke Halaman Menu</a>
    </div>
</div>

<script>
function togglePassword() {
    const inp = document.getElementById('passwordInput');
    const icon = document.getElementById('eyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'fas fa-eye';
    }
}
</script>
</body>
</html>
