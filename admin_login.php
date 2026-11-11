<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

redirectIfLoggedIn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $school_id = trim($_POST['school_id'] ?? '');
    $password  = $_POST['password'] ?? '';

    if (!$school_id || !$password) {
        $error = 'Please enter your Admin ID and password.';
    } else {
        $lockKey = 'admin_login_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        if (!isset($_SESSION[$lockKey])) $_SESSION[$lockKey] = ['count' => 0, 'time' => time()];
        if (time() - $_SESSION[$lockKey]['time'] > 300) $_SESSION[$lockKey] = ['count' => 0, 'time' => time()];

        if ($_SESSION[$lockKey]['count'] >= 5) {
            $wait  = 300 - (time() - $_SESSION[$lockKey]['time']);
            $error = "Too many failed attempts. Please wait {$wait} seconds.";
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare(
                "SELECT id, full_name, password, role, is_active
                   FROM users WHERE school_id = ? AND role = 'admin' LIMIT 1"
            );
            $stmt->execute([$school_id]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $_SESSION[$lockKey]['count']++;
                $error = 'Incorrect Admin ID or password.';
            } elseif (!$user['is_active']) {
                $error = 'This account has been deactivated.';
            } else {
                unset($_SESSION[$lockKey]);
                session_regenerate_id(true);
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['full_name']   = $user['full_name'];
                $_SESSION['role']        = $user['role'];
                $_SESSION['_last_regen'] = time();
                header('Location: ' . appUrl('admin/home.php'));
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Admin Login — ePortfolio</title>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
tailwind.config = {
  darkMode:"class",
  theme:{extend:{
    colors:{
      primary:"#007608","on-primary":"#ffffff","on-primary-container":"#94fb82",
      surface:"#ffffff","surface-container-low":"#f8faf5","surface-container-lowest":"#ffffff",
      "surface-container":"#eaf0e2","on-surface":"#171d15","on-surface-variant":"#3f4a3b",
      outline:"#6f7a6a","outline-variant":"#e2e8f0",error:"#ba1a1a",background:"#ffffff",
    },
    fontFamily:{display:["Lexend"],body:["Lexend"]},
    spacing:{xs:"4px",sm:"8px",md:"16px",lg:"24px",xl:"32px"}
  }}
};
</script>
<style>
body{font-family:'Lexend',sans-serif;-webkit-tap-highlight-color:transparent;min-height:max(884px,100dvh);background-color:#f6f7f8;}
.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}
</style>
</head>
<body class="font-body-md text-on-surface">

<header class="bg-surface sticky top-0 z-50 flex justify-between items-center w-full px-4 h-16 border-b border-outline-variant relative">
  <div class="absolute left-1/2 -translate-x-1/2 flex items-center gap-3 max-w-[calc(100%-2rem)]">
    <div class="w-10 h-10 rounded-full overflow-hidden border border-outline-variant">
      <img src="IMG_3428.JPG" alt="oct" class="w-full h-full object-cover"/>
    </div>
    <h1 class="text-primary font-black text-xl tracking-tight hidden sm:block">Olivarez College Tagaytay E-Portfolio System 2.0</h1>
    <h1 class="text-primary font-black text-base tracking-tight sm:hidden">OCT E-Portfolio</h1>
  </div>
</header>

<div class="min-h-screen flex items-center justify-center px-4 pt-8">
  <div class="w-full max-w-[420px]">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-6 shadow-md">

      <div class="flex items-center gap-2 mb-1">
        <span class="material-symbols-outlined text-primary">admin_panel_settings</span>
        <h2 class="text-lg font-semibold">Administrator Login</h2>
      </div>
      <p class="text-sm text-on-surface-variant mb-4">Sign in with your admin credentials.</p>

      <?php if ($error): ?>
      <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">error</span>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="admin_login.php" class="space-y-4">
        <?= csrfField() ?>

        <input name="school_id" type="text" placeholder="Admin ID (e.g. ADMIN-001)" maxlength="30"
          value="<?= htmlspecialchars($_POST['school_id'] ?? '') ?>"
          class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all"
          required autocomplete="username"/>

        <div class="relative">
          <input id="password" name="password" type="password" placeholder="Password" maxlength="128"
            class="w-full border border-outline-variant rounded-lg p-2 text-sm pr-10 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all"
            required autocomplete="current-password"/>
          <button type="button" onclick="togglePw()" class="absolute right-2 top-2 text-on-surface-variant">
            <span id="eyeIcon" class="material-symbols-outlined">visibility</span>
          </button>
        </div>

        <button type="submit"
          class="w-full bg-primary text-white py-2 rounded-lg font-medium hover:bg-green-800 transition-colors">
          Sign In as Admin
        </button>

        <a href="login.php"
          class="block text-center text-sm text-on-surface-variant hover:text-primary hover:underline transition-colors mt-2">
          ← Back to main login
        </a>
      </form>
    </div>
  </div>
</div>

<script>
function togglePw() {
  const pw = document.getElementById('password');
  const ic = document.getElementById('eyeIcon');
  if (pw.type === 'password') { pw.type = 'text'; ic.textContent = 'visibility_off'; }
  else { pw.type = 'password'; ic.textContent = 'visibility'; }
}
</script>
</body>
</html>