<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('faculty');

$pdo = getDB();
$fid = currentUserId();
$weekId = (int)($_GET['week_id'] ?? $_POST['week_id'] ?? 0);
$sid    = (int)($_GET['subject_id'] ?? $_POST['subject_id'] ?? 0);
if (!$weekId || !$sid) { header('Location: subjects.php'); exit; }

$week = $pdo->prepare(
    "SELECT w.*, s.code, s.name AS subj_name, s.faculty_id FROM weeks w
     JOIN subjects s ON s.id=w.subject_id WHERE w.id=? AND w.subject_id=?"
);
$week->execute([$weekId, $sid]);
$week = $week->fetch();
if (!$week || $week['faculty_id'] != $fid) { header('Location: subjects.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $title     = trim($_POST['title']     ?? '');
    $category  = $_POST['category']       ?? '';
    $max_score = $_POST['max_score']      ?? '';
    $deadline  = trim($_POST['deadline']  ?? '');
    $validCats = ['Quiz','Activity','Exam','Project','Assignment'];

    if (!$title || !$category || $max_score === '') {
        $error = 'Title, category, and max score are required.';
    } elseif (!in_array($category, $validCats, true)) {
        $error = 'Invalid category.';
    } elseif (!is_numeric($max_score) || (float)$max_score <= 0 || (float)$max_score > 1000) {
        $error = 'Max score must be between 1 and 1000.';
    } else {
        $pdo->prepare(
            "INSERT INTO activities (subject_id, week_id, title, category, max_score, deadline) VALUES (?,?,?,?,?,?)"
        )->execute([$sid, $weekId, $title, $category, (float)$max_score, $deadline ?: null]);
        header("Location: subject.php?id={$sid}&tab=weeks");
        exit;
    }
}

include 'header.php';
?>

<div class="mb-xl">
    <button type="button" onclick="history.back()" class="flex items-center gap-sm text-on-surface-variant mb-xs">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        <span class="font-label-md text-label-md">Back</span>
    </button>
    <h2 class="font-headline-md text-headline-md text-on-surface">Create Activity</h2>
    <p class="text-sm text-on-surface-variant"><?= htmlspecialchars($week['code']) ?> · Week <?= $week['week_number'] ?><?= $week['title'] ? ' — ' . htmlspecialchars($week['title']) : '' ?></p>
</div>

<?php if ($error): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
    <form method="POST" class="space-y-lg">
        <?= csrfField() ?>
        <input type="hidden" name="week_id" value="<?= $weekId ?>"/>
        <input type="hidden" name="subject_id" value="<?= $sid ?>"/>
        <div>
            <label class="block font-label-lg text-label-lg text-on-surface-variant mb-1">Activity Title</label>
            <input type="text" name="title" maxlength="150"
                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                placeholder="e.g. Activity 1 — Network Diagram"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" required/>
        </div>
        <div class="grid grid-cols-2 gap-md">
            <div>
                <label class="block font-label-lg text-label-lg text-on-surface-variant mb-1">Category</label>
                <select name="category" class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary appearance-none" required>
                    <?php foreach (['Quiz','Activity','Exam','Project','Assignment'] as $cat): ?>
                    <option <?= ($_POST['category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block font-label-lg text-label-lg text-on-surface-variant mb-1">Max Score</label>
                <input type="number" name="max_score" min="1" max="1000"
                    value="<?= htmlspecialchars($_POST['max_score'] ?? '100') ?>"
                    class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" required/>
            </div>
        </div>
        <div>
            <label class="block font-label-lg text-label-lg text-on-surface-variant mb-1">
                Deadline <span class="text-outline font-label-md">(optional)</span>
            </label>
            <input type="datetime-local" name="deadline"
                value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all"/>
        </div>
        <div class="flex justify-end gap-md pt-md">
            <button type="button" onclick="history.back()"
                class="px-xl h-11 rounded-full border border-outline-variant text-on-surface-variant font-label-lg hover:bg-surface-container-low transition-all">
                Cancel
            </button>
            <button type="submit"
                class="px-xl h-11 rounded-full bg-primary text-on-primary font-label-lg hover:shadow-lg active:scale-95 transition-all flex items-center gap-sm">
                <span class="material-symbols-outlined text-sm">send</span> Publish
            </button>
        </div>
    </form>
</div>

</main>
<?php include 'footer.php'; ?>
