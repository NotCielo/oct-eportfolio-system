<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
redirectIfLoggedIn();

$error   = '';
$success = '';
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old = [
        'full_name'  => trim($_POST['full_name']  ?? ''),
        'school_id'  => trim($_POST['school_id']  ?? ''),
        'role'       => $_POST['role']             ?? 'student',
        'year_level' => $_POST['year_level']       ?? '',
        'email'      => trim(strtolower($_POST['email'] ?? '')),
    ];
    $password  = $_POST['password']         ?? '';
    $password2 = $_POST['confirm_password'] ?? '';

    $validRoles = ['student','faculty'];
    $validYears = ['g11','g12','y1','y2','y3','y4'];

    if (!$old['full_name'] || !$old['school_id'] || !$old['email'] || !$password) {
        $error = 'All fields are required.';
    } elseif (!in_array($old['role'], $validRoles, true)) {
        $error = 'Invalid role.';
    } elseif ($old['role'] === 'student' && !in_array($old['year_level'], $validYears, true)) {
        $error = 'Please select a valid year level.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8 || strlen($password) > 128) {
        $error = 'Password must be 8–128 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $pdo   = getDB();
        $check = $pdo->prepare('SELECT id FROM users WHERE school_id = ? OR email = ? LIMIT 1');
        $check->execute([$old['school_id'], $old['email']]);
        if ($check->fetch()) {
            $error = 'An account with that School ID or email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare(
                'INSERT INTO users (school_id, full_name, email, password, role, year_level) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $old['school_id'], $old['full_name'], $old['email'], $hash, $old['role'],
                $old['role'] === 'student' ? $old['year_level'] : null,
            ]);
            $success = 'Account created! You can now log in.';
            $old = [];
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Sign Up — OCT E-Portfolio</title>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>tailwind.config={theme:{extend:{colors:{primary:"#007608","on-primary":"#ffffff",surface:"#ffffff","surface-container-low":"#f8faf5","surface-container-lowest":"#ffffff","surface-container":"#eaf0e2","on-surface":"#171d15","on-surface-variant":"#3f4a3b",outline:"#6f7a6a","outline-variant":"#e2e8f0",error:"#ba1a1a"},fontFamily:{body:["Lexend"]},spacing:{xs:"4px",sm:"8px",md:"16px",lg:"24px",xl:"32px"}}}};</script>
<style>body{font-family:'Lexend',sans-serif;background:#f6f7f8;min-height:100dvh;}.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}</style>
</head>
<body class="text-on-surface">

<header class="bg-white sticky top-0 z-50 flex items-center justify-center w-full px-4 h-16 border-b border-outline-variant gap-3">
  <div class="w-10 h-10 rounded-full overflow-hidden border border-outline-variant shrink-0">
    <img src="IMG_3428.JPG" alt="oct" class="w-full h-full object-cover"/>
  </div>
  <h1 class="text-primary font-black text-base sm:text-xl tracking-tight">Olivarez College Tagaytay E-Portfolio System 2.0</h1>
</header>

<div class="flex items-center justify-center px-4 py-8">
  <div class="w-full max-w-[420px]">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-6 shadow-md">

      <h2 class="text-lg font-semibold mb-1">Create Account</h2>
      <p class="text-sm text-on-surface-variant mb-4">Students and faculty can self-register.</p>

      <?php if ($success): ?>
      <div class="mb-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">check_circle</span>
        <?= htmlspecialchars($success) ?>
      </div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">error</span>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <?= csrfField() ?>
        <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($old['role'] ?? 'student') ?>"/>

        <input name="full_name" type="text" placeholder="Full Name" maxlength="100"
          value="<?= htmlspecialchars($old['full_name'] ?? '') ?>"
          class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" required/>

        <input name="school_id" type="text" placeholder="School ID" maxlength="30"
          value="<?= htmlspecialchars($old['school_id'] ?? '') ?>"
          class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" required/>

        <div class="grid grid-cols-2 gap-2 p-1 bg-surface-container-low border border-outline-variant rounded-lg">
          <button type="button" id="roleStudentBtn" onclick="setRole('student')"
            class="py-2 rounded-md text-sm transition-colors">Student</button>
          <button type="button" id="roleFacultyBtn" onclick="setRole('faculty')"
            class="py-2 rounded-md text-sm transition-colors">Faculty</button>
        </div>

        <div id="studentFields">
          <select name="year_level" id="yearLevel"
            class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
            <option value="">Select Year Level</option>
            <?php foreach (['g11'=>'Grade 11','g12'=>'Grade 12','y1'=>'1st Year','y2'=>'2nd Year','y3'=>'3rd Year','y4'=>'4th Year'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= ($old['year_level'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <input name="email" type="email" placeholder="Email Address" maxlength="120"
          value="<?= htmlspecialchars($old['email'] ?? '') ?>"
          class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" required autocomplete="email"/>

        <input name="password" type="password" placeholder="Password (min. 8 characters)" maxlength="128"
          class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" required autocomplete="new-password"/>

        <input name="confirm_password" type="password" placeholder="Confirm Password" maxlength="128"
          class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" required autocomplete="new-password"/>

        <button type="submit"
          class="w-full bg-primary text-white py-2 rounded-lg font-medium hover:bg-green-800 transition-colors">
          Create Account
        </button>

        <button type="button" onclick="window.location.href='login.php'"
          class="w-full border border-outline-variant text-on-surface-variant py-2 rounded-lg font-medium hover:bg-surface-container-low transition-colors">
          Back to Login
        </button>
      </form>
    </div>
  </div>
</div>

<script>
const savedRole = "<?= htmlspecialchars($old['role'] ?? 'student') ?>";
function setRole(role) {
  document.getElementById('roleInput').value = role;
  const sBtn = document.getElementById('roleStudentBtn');
  const fBtn = document.getElementById('roleFacultyBtn');
  const sf   = document.getElementById('studentFields');
  if (role === 'student') {
    sBtn.classList.add('bg-primary','text-white'); sBtn.classList.remove('text-on-surface-variant');
    fBtn.classList.remove('bg-primary','text-white'); fBtn.classList.add('text-on-surface-variant');
    sf.classList.remove('hidden');
  } else {
    fBtn.classList.add('bg-primary','text-white'); fBtn.classList.remove('text-on-surface-variant');
    sBtn.classList.remove('bg-primary','text-white'); sBtn.classList.add('text-on-surface-variant');
    sf.classList.add('hidden');
  }
}
setRole(savedRole);
</script>
</body>
</html>
