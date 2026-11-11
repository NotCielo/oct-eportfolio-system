<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('faculty');

$pdo   = getDB();
$fid   = currentUserId();
$subId = (int)($_GET['id'] ?? 0);
if (!$subId) { header('Location: subjects.php'); exit; }

$submission = $pdo->prepare(
    "SELECT sub.*, u.full_name, u.school_id,
            a.title AS act_title, a.max_score, a.category, a.id AS act_id,
            s.code, s.name AS subj_name, s.id AS subj_id, s.faculty_id,
            w.week_number
     FROM submissions sub
     JOIN users u ON u.id=sub.student_id
     JOIN activities a ON a.id=sub.activity_id
     JOIN subjects s ON s.id=a.subject_id
     LEFT JOIN weeks w ON w.id=a.week_id
     WHERE sub.id=?"
);
$submission->execute([$subId]);
$submission = $submission->fetch();
if (!$submission || $submission['faculty_id'] != $fid) { header('Location: subjects.php'); exit; }

$files = $pdo->prepare("SELECT * FROM files WHERE submission_id=? ORDER BY uploaded_at");
$files->execute([$subId]);
$files = $files->fetchAll();

$history = $pdo->prepare(
    "SELECT rl.*, sub.overall_score AS sub_score FROM resubmission_log rl
     LEFT JOIN submissions sub ON sub.id=?
     WHERE rl.activity_id=? AND rl.student_id=?
     ORDER BY rl.attempt_number DESC"
);
$history->execute([$subId, $submission['act_id'], $submission['student_id']]);
$history = $history->fetchAll();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $score         = trim($_POST['overall_score']   ?? '');
    $feedback      = trim($_POST['feedback']        ?? '');
    $file_feedbacks= $_POST['file_feedback']        ?? [];

    if ($score === '' || !is_numeric($score)) {
        $error = 'Please enter a valid score.';
    } elseif ((float)$score < 0 || (float)$score > (float)$submission['max_score']) {
        $error = 'Score must be between 0 and ' . formatScore($submission['max_score']) . '.';
    } else {
        $pdo->prepare(
            "UPDATE submissions SET overall_score=?, feedback=?, status='graded', graded_at=NOW() WHERE id=?"
        )->execute([(float)$score, $feedback, $subId]);

        foreach ($file_feedbacks as $fid2 => $fb) {
            $pdo->prepare("UPDATE files SET file_feedback=? WHERE id=? AND submission_id=?")
                ->execute([trim($fb), (int)$fid2, $subId]);
        }

        $pdo->prepare(
            "UPDATE resubmission_log SET overall_score=?, graded_at=NOW() WHERE activity_id=? AND student_id=? AND attempt_number=?"
        )->execute([(float)$score, $submission['act_id'], $submission['student_id'], $submission['attempt_number']]);

        $success = 'Grade saved successfully.';
        $submission['overall_score'] = (float)$score;
        $submission['feedback']      = $feedback;
        $submission['status']        = 'graded';

        $files = $pdo->prepare("SELECT * FROM files WHERE submission_id=? ORDER BY uploaded_at");
        $files->execute([$subId]);
        $files = $files->fetchAll();
    }
}

include 'header.php';
?>

<!-- Lightbox -->
<div id="lightbox" class="hidden fixed inset-0 z-[200] flex items-center justify-center bg-black/80 px-4" onclick="closeLightbox()">
    <div class="relative max-w-3xl w-full" onclick="event.stopPropagation()">
        <button onclick="closeLightbox()"
            class="absolute -top-10 right-0 text-white/70 hover:text-white flex items-center gap-1 text-sm">
            <span class="material-symbols-outlined">close</span> Close
        </button>
        <img id="lightboxImg" src="" alt="" class="w-full max-h-[80vh] object-contain rounded-xl"/>
    </div>
</div>

<div class="mb-md">
    <button type="button" onclick="history.back()" class="flex items-center gap-sm text-on-surface-variant mb-xs">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        <span class="font-label-md text-label-md">Back</span>
    </button>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-lg">
    <div class="sm:col-span-2 bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
        <p class="text-xs text-on-surface-variant"><?= htmlspecialchars($submission['code']) ?> · Week <?= $submission['week_number'] ?></p>
        <h2 class="font-headline-md text-headline-md text-on-surface mt-1"><?= htmlspecialchars($submission['act_title']) ?></h2>
        <p class="font-label-md text-label-md text-on-surface-variant">
            <?= htmlspecialchars($submission['category']) ?> · Max: <?= formatScore($submission['max_score']) ?> pts
            · Attempt <?= $submission['attempt_number'] ?>
        </p>
    </div>
    <div class="bg-primary-container rounded-xl p-lg flex flex-col justify-center">
        <p class="font-label-lg text-label-lg text-on-primary-container mb-1"><?= htmlspecialchars($submission['full_name']) ?></p>
        <p class="font-label-md text-label-md text-on-primary-container/70 mb-2"><?= htmlspecialchars($submission['school_id']) ?></p>
        <?php if ($submission['status'] === 'graded'): ?>
        <span class="text-3xl font-bold text-on-primary-container"><?= formatScore($submission['overall_score']) ?></span>
        <span class="text-xs text-on-primary-container/70">/ <?= formatScore($submission['max_score']) ?> pts</span>
        <?php else: ?>
        <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-800 text-xs font-medium inline-block">Awaiting Grade</span>
        <?php endif; ?>
        <?php if ($submission['submitted_at']): ?>
        <p class="text-[10px] text-on-primary-container/60 mt-2">Submitted: <?= date('M d, Y g:i A', strtotime($submission['submitted_at'])) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if ($success): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-center gap-2">
    <span class="material-symbols-outlined text-sm">check_circle</span><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mb-md">Submitted Files</h3>

<?php if (empty($files)): ?>
<p class="text-center text-on-surface-variant py-6 text-sm mb-lg">No files uploaded.</p>
<?php else: ?>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-md mb-lg">
    <?php foreach ($files as $file): ?>
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col hover:shadow-md transition-shadow">
        <?php if ($file['is_link']): ?>
        <a href="<?= htmlspecialchars($file['file_url']) ?>" target="_blank" rel="noopener"
           class="aspect-square bg-surface-container flex items-center justify-center hover:bg-surface-container-high transition-colors">
            <span class="material-symbols-outlined text-[48px] text-secondary-container">link</span>
        </a>
        <?php elseif (in_array($file['file_type'], ['jpg','jpeg','png'])): ?>
        <button type="button"
            onclick="openLightbox('../uploads/<?= htmlspecialchars($file['file_path']) ?>')"
            class="aspect-square bg-surface-container overflow-hidden hover:opacity-90 transition-opacity">
            <img src="../uploads/<?= htmlspecialchars($file['file_path']) ?>"
                 alt="<?= htmlspecialchars($file['original_name']) ?>"
                 class="w-full h-full object-cover"/>
        </button>
        <?php else: ?>
        <a href="../uploads/<?= htmlspecialchars($file['file_path']) ?>" target="_blank"
           class="aspect-square bg-surface-container flex items-center justify-center hover:bg-surface-container-high transition-colors">
            <span class="material-symbols-outlined text-[48px] text-error">picture_as_pdf</span>
        </a>
        <?php endif; ?>
        <div class="p-sm flex flex-col gap-xs">
            <p class="font-label-md text-on-surface truncate text-xs" title="<?= htmlspecialchars($file['original_name']) ?>">
                <?= htmlspecialchars($file['original_name']) ?>
            </p>
            <?php if (!$file['is_link']): ?>
            <p class="text-[10px] text-outline"><?= $file['file_size_kb'] ?> KB</p>
            <?php endif; ?>
            <input type="text" form="gradeForm" name="file_feedback[<?= $file['id'] ?>]"
                value="<?= htmlspecialchars($file['file_feedback'] ?? '') ?>"
                placeholder="Comment..." maxlength="500"
                class="w-full border border-outline-variant rounded px-2 py-1 text-xs focus:outline-none focus:border-primary"/>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($history)): ?>
<div class="mb-lg bg-surface-container-low border border-outline-variant rounded-xl p-md">
    <h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mb-sm">Submission History</h3>
    <div class="space-y-xs">
        <?php foreach ($history as $h): ?>
        <div class="flex items-center justify-between text-xs py-1 border-b border-outline-variant last:border-0">
            <span class="text-on-surface-variant">Attempt <?= $h['attempt_number'] ?> · <?= date('M d, Y g:i A', strtotime($h['submitted_at'])) ?></span>
            <?php if ($h['overall_score'] !== null): ?>
            <span class="text-primary font-medium"><?= formatScore($h['overall_score']) ?> pts</span>
            <?php else: ?>
            <span class="text-outline">Not graded</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<form id="gradeForm" method="POST" class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm mb-24">
    <?= csrfField() ?>
    <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Grade Submission</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-lg mb-lg">
        <div>
            <label class="block font-label-lg text-label-lg text-on-surface-variant mb-1">
                Overall Score <span class="text-outline font-label-md">(max <?= formatScore($submission['max_score']) ?>)</span>
            </label>
            <input type="number" name="overall_score" min="0" max="<?= $submission['max_score'] ?>" step="0.01"
                value="<?= htmlspecialchars($submission['overall_score'] ?? '') ?>"
                placeholder="e.g. 85"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" required/>
        </div>
        <div>
            <label class="block font-label-lg text-label-lg text-on-surface-variant mb-1">Overall Feedback</label>
            <textarea name="feedback" rows="3" maxlength="1000"
                placeholder="Comment for this student..."
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all resize-none"><?= htmlspecialchars($submission['feedback'] ?? '') ?></textarea>
        </div>
    </div>
    <div class="flex justify-end">
        <button type="submit"
            class="px-xl h-11 rounded-full bg-primary text-on-primary font-label-lg hover:shadow-lg active:scale-95 transition-all flex items-center gap-sm">
            <span class="material-symbols-outlined text-sm">grading</span> Save Grade
        </button>
    </div>
</form>

</main>
<?php include 'footer.php'; ?>

<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.add('hidden');
    document.getElementById('lightboxImg').src = '';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>
