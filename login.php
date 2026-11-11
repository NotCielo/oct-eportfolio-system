<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
redirectIfLoggedIn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $school_id   = trim($_POST['school_id']   ?? '');
    $password    = $_POST['password']          ?? '';
    $role_chosen = $_POST['role_chosen']       ?? '';

    $studentRoles  = ['student'];
    $facultyRoles  = ['faculty','coordinator','dept_head'];
    $allRoles      = ['student','faculty','coordinator','dept_head'];

    if (!$school_id || !$password || !$role_chosen) {
        $error = 'Please fill in all fields.';
    } elseif (!in_array($role_chosen, ['student','faculty'], true)) {
        $error = 'Invalid role selected.';
    } else {
        $lockKey = 'login_' . md5(($_SERVER['REMOTE_ADDR'] ?? '') . $school_id);
        if (!isset($_SESSION[$lockKey])) $_SESSION[$lockKey] = ['count' => 0, 'time' => time()];
        if (time() - $_SESSION[$lockKey]['time'] > 300) $_SESSION[$lockKey] = ['count' => 0, 'time' => time()];

        if ($_SESSION[$lockKey]['count'] >= 5) {
            $wait  = 300 - (time() - $_SESSION[$lockKey]['time']);
            $error = "Too many failed attempts. Please wait {$wait} seconds.";
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare('SELECT id, full_name, password, role, is_active, department_id FROM users WHERE school_id = ? LIMIT 1');
            $stmt->execute([$school_id]);
            $user = $stmt->fetch();

            $expectedRoles = $role_chosen === 'student' ? $studentRoles : $facultyRoles;

            if (!$user || !password_verify($password, $user['password'])) {
                $_SESSION[$lockKey]['count']++;
                $error = 'Incorrect ID or password.';
            } elseif (!in_array($user['role'], $expectedRoles, true)) {
                $_SESSION[$lockKey]['count']++;
                $error = 'Credentials do not match the selected role.';
            } elseif (!$user['is_active']) {
                $error = 'Your account has been deactivated. Contact the administrator.';
            } else {
                unset($_SESSION[$lockKey]);
                session_regenerate_id(true);
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['full_name']     = $user['full_name'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['department_id'] = $user['department_id'];
                $_SESSION['_last_regen']   = time();
                redirectToDashboard();
            }
        }
    }
}
$chosenRole = $_POST['role_chosen'] ?? 'student';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>OCT E-Portfolio System</title>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
tailwind.config={darkMode:"class",theme:{extend:{colors:{primary:"#007608","on-primary":"#ffffff","primary-container":"#007608","on-primary-container":"#94fb82","primary-fixed":"#00d30e",secondary:"#007608","secondary-container":"#3691ff","on-secondary":"#ffffff","on-secondary-container":"#002955",surface:"#ffffff","surface-variant":"#f1f5f0","surface-container-low":"#f8faf5","surface-container-lowest":"#ffffff","surface-container":"#eaf0e2","surface-container-high":"#e4eadd","on-surface":"#171d15","on-surface-variant":"#3f4a3b",outline:"#6f7a6a","outline-variant":"#e2e8f0",error:"#ba1a1a",background:"#ffffff",pending:"#e6811c"},fontFamily:{display:["Lexend"],body:["Lexend"]},spacing:{xs:"4px",sm:"8px",md:"16px",lg:"24px",xl:"32px"}}}};
</script>
<style>body{font-family:'Lexend',sans-serif;-webkit-tap-highlight-color:transparent;min-height:100dvh;background-color:#f6f7f8;}.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}</style>
</head>
<body class="text-on-surface">

<header class="bg-surface sticky top-0 z-50 flex items-center justify-center w-full px-4 h-16 border-b border-outline-variant gap-3">
  <div class="w-10 h-10 rounded-full overflow-hidden border border-outline-variant shrink-0">
    <img src="IMG_3428.JPG" alt="oct" class="w-full h-full object-cover"/>
  </div>
  <h1 class="text-primary font-black text-base sm:text-xl tracking-tight">Olivarez College Tagaytay E-Portfolio System 2.0</h1>
</header>

<div class="min-h-[calc(100dvh-64px)] flex items-center justify-center px-4 py-8">
  <div class="w-full max-w-[420px]">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-6 shadow-md">
      <h2 class="text-lg font-semibold mb-1">Welcome back</h2>
      <p class="text-sm text-on-surface-variant mb-4">Select your role and enter your credentials.</p>

      <?php if ($error): ?>
      <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">error</span>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <?= csrfField() ?>
        <input type="hidden" name="role_chosen" id="roleInput" value="<?= htmlspecialchars($chosenRole) ?>"/>

        <div class="grid grid-cols-2 gap-2 p-1 bg-surface-container-low border border-outline-variant rounded-lg">
          <button type="button" id="roleStudent" onclick="setRole('student')"
            class="py-2 rounded-md text-sm transition-colors">Student</button>
          <button type="button" id="roleFaculty" onclick="setRole('faculty')"
            class="py-2 rounded-md text-sm transition-colors">Faculty / Staff</button>
        </div>

        <input name="school_id" type="text" placeholder="School ID" maxlength="50"
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

        <div class="text-right">
          <a href="forgot_password.php" class="text-xs text-primary hover:underline">Forgot password?</a>
        </div>

        <button type="submit"
          class="w-full bg-primary text-white py-2 rounded-lg font-medium hover:bg-green-800 transition-colors">
          Log In
        </button>

        <button type="button" onclick="window.location.href='signin.php'"
          class="w-full border border-outline-variant text-on-surface-variant py-2 rounded-lg font-medium hover:bg-surface-container-low transition-colors">
          Sign Up
        </button>

        <div id="adminLink" class="hidden text-center">
          <a href="admin_login.php" class="text-xs text-primary underline opacity-80 hover:opacity-100">Admin Login</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const savedRole = "<?= htmlspecialchars($chosenRole) ?>";
function setRole(role) {
  document.getElementById('roleInput').value = role;
  const sBtn = document.getElementById('roleStudent');
  const fBtn = document.getElementById('roleFaculty');
  const aLink= document.getElementById('adminLink');
  if (role === 'student') {
    sBtn.classList.add('bg-primary','text-white'); sBtn.classList.remove('text-on-surface-variant');
    fBtn.classList.remove('bg-primary','text-white'); fBtn.classList.add('text-on-surface-variant');
    aLink.classList.add('hidden');
  } else {
    fBtn.classList.add('bg-primary','text-white'); fBtn.classList.remove('text-on-surface-variant');
    sBtn.classList.remove('bg-primary','text-white'); sBtn.classList.add('text-on-surface-variant');
    aLink.classList.remove('hidden');
  }
}
function togglePw() {
  const pw = document.getElementById('password');
  const ic = document.getElementById('eyeIcon');
  if (pw.type==='password'){pw.type='text';ic.textContent='visibility_off';}
  else{pw.type='password';ic.textContent='visibility';}
}
setRole(savedRole);
</script>
</body>
</html>
