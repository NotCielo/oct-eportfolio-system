<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('faculty');

$pdo  = getDB();
$user = $pdo->prepare("SELECT * FROM users WHERE id=?");
$user->execute([currentUserId()]);
$user = $user->fetch();

$dept = null;
if ($user['department_id']) {
    $d = $pdo->prepare("SELECT name FROM departments WHERE id=?");
    $d->execute([$user['department_id']]);
    $dept = $d->fetchColumn();
}

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $old_pw    = $_POST['old_password']   ?? '';
    $new_pw    = $_POST['new_password']   ?? '';
    $new_pw2   = $_POST['confirm_password'] ?? '';

    if (!$full_name || !$email) { $error = 'Name and email required.'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'Invalid email.'; }
    else {
        $dup = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $dup->execute([$email, currentUserId()]);
        if ($dup->fetch()) { $error = 'Email already in use.'; }
        elseif ($new_pw !== '') {
            if (!password_verify($old_pw, $user['password']))  { $error = 'Current password incorrect.'; }
            elseif (strlen($new_pw) < 8)                       { $error = 'Min 8 characters.'; }
            elseif ($new_pw !== $new_pw2)                      { $error = 'Passwords do not match.'; }
            else {
                $hash = password_hash($new_pw, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET full_name=?, email=?, password=? WHERE id=?")
                    ->execute([$full_name, $email, $hash, currentUserId()]);
                $_SESSION['full_name'] = $full_name;
                $success = 'Profile and password updated.';
            }
        } else {
            $pdo->prepare("UPDATE users SET full_name=?, email=? WHERE id=?")
                ->execute([$full_name, $email, currentUserId()]);
            $_SESSION['full_name'] = $full_name;
            $success = 'Profile updated.';
        }
        if (!$error) {
            $user = $pdo->prepare("SELECT * FROM users WHERE id=?");
            $user->execute([currentUserId()]);
            $user = $user->fetch();
        }
    }
}

include 'header.php';
?>

<section class="relative overflow-hidden rounded-xl p-lg text-white mb-lg shadow-sm"
    style="background-color:#007608;background-image:radial-gradient(at 0% 0%,#005b04 0,transparent 50%),radial-gradient(at 100% 0%,#2f7534 0,transparent 50%)">
    <div class="relative z-10 flex items-center gap-lg">
        <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-white text-4xl" style="font-variation-settings:'FILL' 1;">school</span>
        </div>
        <div>
            <h2 class="font-headline-md text-headline-md font-bold"><?= htmlspecialchars($user['full_name']) ?></h2>
            <p class="text-white/80 text-sm">Faculty<?= $dept ? ' · ' . htmlspecialchars($dept) : '' ?></p>
            <p class="text-white/60 text-xs"><?= htmlspecialchars($user['school_id']) ?></p>
        </div>
    </div>
</section>

<?php if ($success): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-center gap-2">
    <span class="material-symbols-outlined text-sm">check_circle</span><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden mb-lg">
    <div class="px-lg py-md border-b border-outline-variant bg-surface-container-low">
        <h3 class="font-title-lg text-on-surface font-semibold">Edit Profile</h3>
    </div>
    <form method="POST" class="p-lg space-y-md">
        <?= csrfField() ?>
        <div>
            <label class="block font-label-lg text-label-lg text-on-surface-variant mb-1">Full Name</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" required/>
        </div>
        <div>
            <label class="block font-label-lg text-label-lg text-on-surface-variant mb-1">Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" required/>
        </div>
        <hr class="border-outline-variant"/>
        <p class="font-label-md text-on-surface-variant">Change Password <span class="text-outline">(leave blank to keep current)</span></p>
        <input type="password" name="old_password" placeholder="Current Password"
            class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all"/>
        <input type="password" name="new_password" placeholder="New Password (min 8)"
            class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all"/>
        <input type="password" name="confirm_password" placeholder="Confirm New Password"
            class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all"/>
        <div class="flex justify-end pt-md">
            <button type="submit"
                class="px-xl h-11 rounded-full bg-primary text-on-primary font-label-lg hover:shadow-lg active:scale-95 transition-all">
                Save Changes
            </button>
        </div>
    </form>
</section>

<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm mb-24">
    <a href="../logout.php" class="flex items-center gap-md px-lg py-md hover:bg-red-50 transition-colors">
        <span class="material-symbols-outlined text-error">logout</span>
        <span class="text-error font-body-md font-medium">Logout</span>
    </a>
</section>

</main>
<?php include 'footer.php'; ?>
