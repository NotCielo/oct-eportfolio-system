<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('faculty');

$pdo = getDB();
$fid = currentUserId();
$sid = (int)($_GET['subject_id'] ?? $_POST['subject_id'] ?? 0);
if (!$sid) { header('Location: subjects.php'); exit; }

$subject = $pdo->prepare("SELECT id, code, name FROM subjects WHERE id=? AND faculty_id=? AND is_archived=0");
$subject->execute([$sid, $fid]);
$subject = $subject->fetch();
if (!$subject) { header('Location: subjects.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $week_number = (int)($_POST['week_number'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    if ($week_number < 1 || $week_number > 52) {
        $error = 'Week number must be between 1 and 52.';
    } else {
        $dup = $pdo->prepare("SELECT id FROM weeks WHERE subject_id=? AND week_number=?");
        $dup->execute([$sid, $week_number]);
        if ($dup->fetch()) {
            $error = "Week {$week_number} already exists for this subject.";
        } else {
            $pdo->prepare("INSERT INTO weeks (subject_id, week_number, title) VALUES (?,?,?)")
                ->execute([$sid, $week_number, $title]);
            header("Location: subject.php?id={$sid}&tab=weeks");
            exit;
        }
    }
}

$existingWeeks = $pdo->prepare("SELECT week_number FROM weeks WHERE subject_id=? ORDER BY week_number");
$existingWeeks->execute([$sid]);
$existingNums = array_column($existingWeeks->fetchAll(), 'week_number');
$nextWeek = 1;
while (in_array($nextWeek, $existingNums)) $nextWeek++;

include 'header.php';
?>

<div class="mb-xl">
    <button type="button" onclick="history.back()" class="flex items-center gap-sm text-on-surface-variant mb-xs">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        <span class="font-label-md text-label-md">Back</span>
    </button>
    <h2 class="font-headline-md text-headline-md text-on-surface">Add Week</h2>
    <p class="text-sm text-on-surface-variant"><?= htmlspecialchars($subject['code']) ?> — <?= htmlspecialchars($subject['name']) ?></p>
</div>

<?php if ($error): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
    <form method="POST" class="space-y-lg">
        <?= csrfField() ?>
        <input type="hidden" name="subject_id" value="<?= $sid ?>"/>
        <div>
            <label class="block font-label-lg text-label-lg text-on-surface-variant mb-1">Week Number</label>
            <input type="number" name="week_number" min="1" max="52"
                value="<?= htmlspecialchars($_POST['week_number'] ?? $nextWeek) ?>"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" required/>
        </div>
        <div>
            <label class="block font-label-lg text-label-lg text-on-surface-variant mb-1">
                Title <span class="text-outline font-label-md">(optional — e.g. Introduction to Networks)</span>
            </label>
            <input type="text" name="title" maxlength="100"
                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                placeholder="Leave blank for no subtitle"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all"/>
        </div>
        <div class="flex justify-end gap-md pt-md">
            <button type="button" onclick="history.back()"
                class="px-xl h-11 rounded-full border border-outline-variant text-on-surface-variant font-label-lg hover:bg-surface-container-low transition-all">
                Cancel
            </button>
            <button type="submit"
                class="px-xl h-11 rounded-full bg-primary text-on-primary font-label-lg hover:shadow-lg active:scale-95 transition-all">
                Create Week
            </button>
        </div>
    </form>
</div>

<?php if (!empty($existingNums)): ?>
<div class="mt-lg">
    <h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mb-sm">Existing Weeks</h3>
    <div class="flex flex-wrap gap-sm">
        <?php foreach ($existingNums as $n): ?>
        <span class="px-3 py-1 bg-surface-container-low border border-outline-variant rounded-full text-xs text-on-surface-variant">Week <?= $n ?></span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

</main>
<?php include 'footer.php'; ?>
