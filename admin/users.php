<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo     = getDB();
$error   = '';
$success = '';

$departments = $pdo->query("SELECT id, name, code FROM departments ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid) {
            $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id=? AND role!='admin'")->execute([$uid]);
            $success = 'Account status updated.';
        }

    } elseif ($action === 'reset_password') {
        $uid      = (int)($_POST['uid'] ?? 0);
        $new_pass = trim($_POST['new_password'] ?? '');
        if (!$uid || strlen($new_pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
            $success = 'Password reset successfully.';
        }

    } elseif ($action === 'create_user') {
        $school_id = trim($_POST['school_id'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $role      = $_POST['role'] ?? '';
        $dept_id   = (int)($_POST['dept_id'] ?? 0) ?: null;
        $password  = trim($_POST['password'] ?? '');

        $validRoles = ['coordinator','dept_head','faculty'];
        if (!$school_id || !$full_name || !$email || !in_array($role, $validRoles, true) || strlen($password) < 6) {
            $error = 'All fields required. Password min 6 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email.';
        } else {
            $dup = $pdo->prepare("SELECT id FROM users WHERE school_id=? OR email=?");
            $dup->execute([$school_id, $email]);
            if ($dup->fetch()) {
                $error = 'School ID or email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
                $pdo->prepare(
                    "INSERT INTO users (school_id, full_name, email, password, role, department_id) VALUES (?,?,?,?,?,?)"
                )->execute([$school_id, $full_name, $email, $hash, $role, $dept_id]);
                $success = "Account for {$full_name} created.";
            }
        }
    }
}

$filterRole = $_GET['role'] ?? 'all';
$search     = trim($_GET['q'] ?? '');

$sql    = "SELECT u.*, d.code AS dept_code FROM users u LEFT JOIN departments d ON d.id=u.department_id WHERE u.role!='admin'";
$params = [];
if ($filterRole !== 'all' && in_array($filterRole, ['coordinator','dept_head','faculty','student'], true)) {
    $sql .= " AND u.role=?"; $params[] = $filterRole;
}
if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.school_id LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$users = $stmt->fetchAll();

$resetUser = null;
if (isset($_GET['reset'])) {
    $ru = $pdo->prepare("SELECT id, full_name, school_id FROM users WHERE id=? AND role!='admin'");
    $ru->execute([(int)$_GET['reset']]);
    $resetUser = $ru->fetch();
}

include 'header.php';
?>

<div class="flex items-center justify-between mb-lg flex-wrap gap-sm">
    <div>
        <h2 class="font-headline-md text-headline-md text-on-surface">User Management</h2>
        <p class="text-sm text-on-surface-variant">Manage all accounts and reset passwords.</p>
    </div>
    <button onclick="document.getElementById('createPanel').classList.toggle('hidden')"
        class="flex items-center gap-1 px-4 py-2 rounded-full bg-primary text-white text-sm font-medium hover:bg-green-800 transition-colors">
        <span class="material-symbols-outlined text-sm">person_add</span> Add User
    </button>
</div>

<div id="createPanel" class="hidden mb-lg bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
    <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Create Staff Account</h3>
    <?php if ($error): ?>
    <div class="mb-md px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-md">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create_user"/>
        <input type="text" name="school_id" placeholder="School ID" maxlength="30" class="border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary" required/>
        <input type="text" name="full_name" placeholder="Full Name" maxlength="100" class="border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary" required/>
        <input type="email" name="email" placeholder="Email" maxlength="120" class="border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary" required/>
        <input type="password" name="password" placeholder="Password (min 6 chars)" class="border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary" required/>
        <select name="role" class="border border-outline-variant rounded-lg p-2 text-sm focus:outline-none appearance-none" required>
            <option value="">Select Role</option>
            <option value="coordinator">Program Coordinator</option>
            <option value="dept_head">Department Head</option>
            <option value="faculty">Faculty</option>
        </select>
        <select name="dept_id" class="border border-outline-variant rounded-lg p-2 text-sm focus:outline-none appearance-none">
            <option value="">No Department</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['code']) ?> — <?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="sm:col-span-2 flex justify-end">
            <button type="submit" class="px-xl h-10 rounded-full bg-primary text-white font-label-lg hover:bg-green-800 transition-colors">
                Create Account
            </button>
        </div>
    </form>
</div>

<?php if ($success): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-center gap-2">
    <span class="material-symbols-outlined text-sm">check_circle</span><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<?php if ($resetUser): ?>
<div class="mb-lg bg-surface-container-lowest border border-primary/30 rounded-xl p-lg shadow-sm">
    <h3 class="font-title-lg text-title-lg text-on-surface mb-1">Reset Password — <?= htmlspecialchars($resetUser['full_name']) ?></h3>
    <p class="text-sm text-on-surface-variant mb-md"><?= htmlspecialchars($resetUser['school_id']) ?></p>
    <form method="POST" class="flex gap-sm flex-wrap">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reset_password"/>
        <input type="hidden" name="uid" value="<?= $resetUser['id'] ?>"/>
        <input type="text" name="new_password" placeholder="New temporary password" minlength="6"
            class="flex-1 border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary" required/>
        <button type="submit" class="px-lg h-10 rounded-full bg-primary text-white font-label-lg hover:bg-green-800 transition-colors">
            Set Password
        </button>
        <a href="users.php" class="px-lg h-10 rounded-full border border-outline-variant text-on-surface-variant font-label-lg flex items-center hover:bg-surface-container-low transition-colors">Cancel</a>
    </form>
</div>
<?php endif; ?>

<form method="GET" class="flex flex-col sm:flex-row gap-sm mb-lg">
    <div class="flex gap-sm flex-wrap">
        <?php foreach (['all'=>'All','coordinator'=>'Coordinators','dept_head'=>'Dept Heads','faculty'=>'Faculty','student'=>'Students'] as $val => $label): ?>
        <a href="?role=<?= $val ?>&q=<?= urlencode($search) ?>"
           class="px-4 py-2 rounded-full text-xs font-medium border transition-colors <?= $filterRole===$val ? 'bg-primary text-white border-primary' : 'border-outline-variant text-on-surface-variant hover:bg-surface-container-low' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>
    <div class="relative flex-1">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-sm">search</span>
        <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, ID, or email..."
            class="w-full pl-9 pr-4 py-2 rounded-xl border border-outline-variant text-sm focus:outline-none focus:border-primary transition-all"/>
        <input type="hidden" name="role" value="<?= htmlspecialchars($filterRole) ?>"/>
    </div>
</form>

<div class="space-y-sm pb-24">
    <?php if (empty($users)): ?>
    <p class="text-center text-on-surface-variant py-16 text-sm">No users found.</p>
    <?php else: ?>
    <?php foreach ($users as $u): ?>
    <div class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow <?= !$u['is_active'] ? 'opacity-60' : '' ?>">
        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-sm">person</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($u['full_name']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant">
                <?= htmlspecialchars($u['school_id']) ?> · <?= ucfirst(str_replace('_',' ',$u['role'])) ?>
                <?= $u['dept_code'] ? ' · ' . htmlspecialchars($u['dept_code']) : '' ?>
            </p>
        </div>
        <div class="flex items-center gap-sm shrink-0">
            <?php if ($u['is_active']): ?>
            <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[10px] font-bold">Active</span>
            <?php else: ?>
            <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-600 text-[10px] font-bold">Inactive</span>
            <?php endif; ?>
            <a href="?role=<?= $filterRole ?>&q=<?= urlencode($search) ?>&reset=<?= $u['id'] ?>#reset"
               class="text-[10px] px-2 py-1 rounded-lg border border-outline-variant hover:bg-surface-container-low transition-colors text-on-surface-variant">
                Reset PW
            </a>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle"/>
                <input type="hidden" name="uid" value="<?= $u['id'] ?>"/>
                <button type="submit" class="text-[10px] px-2 py-1 rounded-lg border border-outline-variant hover:bg-surface-container-low transition-colors text-on-surface-variant">
                    <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
