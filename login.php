<?php
require_once 'koneksi.php';

// Sudah login? Redirect sesuai role
if (isLoggedIn()) {
    $role = getUserRole();
    if ($role === 'admin') redirect('/ayam-penyet/admin/dashboard.php');
    elseif ($role === 'kasir') redirect('/ayam-penyet/kasir/dashboard.php');
    elseif ($role === 'kitchen') redirect('/ayam-penyet/kitchen/index.php');
    else redirect('/ayam-penyet/login.php');
}

$error = '';
if (isset($_GET['error']) && $_GET['error'] === 'akses') {
    $error = 'Anda tidak memiliki akses ke halaman tersebut.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_nama'] = $user['nama'];
            $_SESSION['user_user'] = $user['username'];
            $_SESSION['user_role'] = $user['role'] ?? 'kasir';
            // Legacy support
            $_SESSION['admin_id']   = $user['id'];
            $_SESSION['admin_nama'] = $user['nama'];
            $_SESSION['admin_user'] = $user['username'];

            $role = $user['role'] ?? 'kasir';
            if ($role === 'admin') redirect('/ayam-penyet/admin/dashboard.php');
            elseif ($role === 'kasir') redirect('/ayam-penyet/kasir/dashboard.php');
            elseif ($role === 'kitchen') redirect('/ayam-penyet/kitchen/index.php');
            else redirect('/ayam-penyet/kasir/dashboard.php');
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
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #E84040; --primary-dark: #C42E2E; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #1A1A2E 0%, #16213E 50%, #0F3460 100%);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 20px;
        }
        .login-wrapper { width: 100%; max-width: 440px; }
        .login-header { text-align: center; margin-bottom: 28px; }
        .logo-box {
            width: 86px; height: 86px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 24px;
            display: flex; align-items: center; justify-content: center;
            font-size: 40px; margin: 0 auto 14px;
            box-shadow: 0 12px 32px rgba(232,64,64,0.4);
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%,100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        .login-header h1 { font-family: 'Playfair Display', serif; font-size: 22px; color: white; margin: 0 0 4px; }
        .login-header p { color: rgba(255,255,255,0.5); font-size: 13px; margin: 0; }
        .login-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px; padding: 32px 28px;
        }
        /* Role badges */
        .role-info {
            display: flex; gap: 8px; margin-bottom: 24px; justify-content: center; flex-wrap: wrap;
        }
        .role-badge {
            padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
            display: flex; align-items: center; gap: 6px;
        }
        .role-admin { background: rgba(168,85,247,0.2); color: #C084FC; border: 1px solid rgba(168,85,247,0.3); }
        .role-kasir { background: rgba(59,130,246,0.2); color: #93C5FD; border: 1px solid rgba(59,130,246,0.3); }
        .role-kitchen { background: rgba(34,197,94,0.2); color: #86EFAC; border: 1px solid rgba(34,197,94,0.3); }

        .form-label { font-size: 13px; font-weight: 700; color: rgba(255,255,255,0.7); margin-bottom: 8px; }
        .input-group-custom { position: relative; margin-bottom: 18px; }
        .input-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.4); font-size: 15px; z-index: 1; }
        .form-control-custom {
            width: 100%; background: rgba(255,255,255,0.08);
            border: 1.5px solid rgba(255,255,255,0.12); border-radius: 12px;
            padding: 14px 16px 14px 44px; color: white; font-size: 14px;
            font-family: 'Plus Jakarta Sans', sans-serif; outline: none; transition: all 0.2s;
        }
        .form-control-custom:focus { border-color: var(--primary); background: rgba(255,255,255,0.1); box-shadow: 0 0 0 3px rgba(232,64,64,0.2); }
        .form-control-custom::placeholder { color: rgba(255,255,255,0.3); }
        .toggle-pass { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.4); cursor: pointer; border: none; background: none; font-size: 15px; }
        .alert-error { background: rgba(232,64,64,0.15); border: 1px solid rgba(232,64,64,0.3); border-radius: 10px; padding: 12px 16px; color: #FF8A8A; font-size: 13px; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
        .btn-login {
            width: 100%; background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; border: none; border-radius: 12px; padding: 15px; font-size: 15px;
            font-weight: 700; cursor: pointer; margin-top: 8px; transition: all 0.2s;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(232,64,64,0.45); }
        .divider { border: none; border-top: 1px solid rgba(255,255,255,0.1); margin: 20px 0; }
        .cred-table { width: 100%; font-size: 12px; color: rgba(255,255,255,0.5); }
        .cred-table td { padding: 3px 6px; }
        .cred-table td:first-child { color: rgba(255,255,255,0.35); font-weight: 600; text-align: right; }
        code { background: rgba(255,255,255,0.08); padding: 1px 5px; border-radius: 4px; color: rgba(255,255,255,0.6); }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-header">
        <div class="logo-box">🍗</div>
        <h1><?= APP_NAME ?></h1>
        <p>Sistem Manajemen Restoran</p>
    </div>

    <div class="login-card">
        <div class="role-info">
            <span class="role-badge role-admin"><i class="fas fa-crown"></i> Admin</span>
            <span class="role-badge role-kasir"><i class="fas fa-cash-register"></i> Kasir</span>
            <span class="role-badge role-kitchen"><i class="fas fa-utensils"></i> Kitchen</span>
        </div>

        <?php if ($error): ?>
        <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
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
                <i class="fas fa-sign-in-alt me-2"></i>Masuk
            </button>
        </form>

    </div>
</div>
<script>
function togglePassword() {
    const inp = document.getElementById('passwordInput');
    const icon = document.getElementById('eyeIcon');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.className = inp.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
</body>
</html>
