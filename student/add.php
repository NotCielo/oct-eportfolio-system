<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');

$pdo = getDB();
$uid = currentUserId();
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $code = strtoupper(trim($_POST['join_code'] ?? ''));
    if (!preg_match('/^[A-Z0-9]{4,10}$/', $code)) {
        $error = 'Invalid join code format.';
    } else {
        $subject = $pdo->prepare("SELECT id, code, name, is_archived, is_active FROM subjects WHERE join_code=?");
        $subject->execute([$code]);
        $subject = $subject->fetch();
        if (!$subject) {
            $error = 'Join code not found. Double-check with your faculty.';
        } elseif ($subject['is_archived'] || !$subject['is_active']) {
            $error = 'This subject is no longer active.';
        } else {
            $dup = $pdo->prepare("SELECT id FROM enrollments WHERE subject_id=? AND student_id=?");
            $dup->execute([$subject['id'], $uid]);
            if ($dup->fetch()) {
                $error = 'You are already enrolled in this subject.';
            } else {
                $pdo->prepare("INSERT INTO enrollments (subject_id, student_id) VALUES (?,?)")
                    ->execute([$subject['id'], $uid]);
                $success = "Successfully enrolled in {$subject['code']} — {$subject['name']}!";
            }
        }
    }
}

include 'header.php';
?>

<div class="mb-xl">
    <button type="button" onclick="history.back()" class="flex items-center gap-sm text-on-surface-variant mb-xs">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        <span class="font-label-md text-label-md">Back</span>
    </button>
    <h2 class="font-headline-md text-headline-md text-on-surface">Join a Subject</h2>
    <p class="text-sm text-on-surface-variant">Enter the 6-character code from your faculty.</p>
</div>

<?php if ($success): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-center gap-2">
    <span class="material-symbols-outlined text-sm">check_circle</span><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
    <span class="material-symbols-outlined text-sm">error</span><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm mb-24">
    <form method="POST" class="space-y-lg">
        <?= csrfField() ?>
        <div>
            <label class="block font-label-lg text-label-lg text-on-surface-variant mb-1">Join Code</label>
            <input type="text" name="join_code" maxlength="10" autocomplete="off"
                value="<?= htmlspecialchars($_POST['join_code'] ?? '') ?>"
                placeholder="e.g. AB1234"
                class="w-full border border-outline-variant rounded-lg p-3 text-center text-lg font-mono tracking-widest uppercase focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" required/>
        </div>
        <button type="submit"
            class="w-full h-12 rounded-full bg-primary text-on-primary font-label-lg hover:shadow-lg active:scale-95 transition-all flex items-center justify-center gap-sm">
            <span class="material-symbols-outlined">login</span> Enroll
        </button>
    </form>
</div>

</main>
<?php include 'footer.php'; ?>
