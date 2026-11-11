<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');

$pdo   = getDB();
$uid   = currentUserId();
$subId = (int)($_GET['id'] ?? 0);
if (!$subId) { header('Location: subjects.php'); exit; }

$submission = $pdo->prepare(
    "SELECT sub.*, a.title AS act_title, a.max_score, a.category, a.id AS act_id,
            s.code, s.name AS subj_name, s.id AS subj_id, w.week_number
     FROM submissions sub
     JOIN activities a ON a.id=sub.activity_id
     JOIN subjects s ON s.id=a.subject_id
     LEFT JOIN weeks w ON w.id=a.week_id
     WHERE sub.id=? AND sub.student_id=?"
);
$submission->execute([$subId, $uid]);
$submission = $submission->fetch();
if (!$submission) { header('Location: subjects.php'); exit; }

$files = $pdo->prepare("SELECT * FROM files WHERE submission_id=? ORDER BY uploaded_at");
$files->execute([$subId]);
$files = $files->fetchAll();

$history = $pdo->prepare(
    "SELECT * FROM resubmission_log WHERE activity_id=? AND student_id=? ORDER BY attempt_number DESC"
);
$history->execute([$submission['act_id'], $uid]);
$history = $history->fetchAll();

include 'header.php';
?>

<!-- Lightbox -->
<div id="lightbox" class="hidden fixed inset-0 z-[200] flex items-center justify-center bg-black/80 px-4" onclick="closeLightbox()">
    <div class="relative max-w-3xl w-full" onclick="event.stopPropagation()">
        <button onclick="closeLightbox()" class="absolute -top-10 right-0 text-white/70 hover:text-white text-sm flex items-center gap-1">
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
        </p>
        <?php if ($submission['submitted_at']): ?>
        <p class="text-xs text-outline mt-1">
            Submitted: <?= date('M d, Y g:i A', strtotime($submission['submitted_at'])) ?>
            · Attempt <?= $submission['attempt_number'] ?>/3
        </p>
        <?php endif; ?>
    </div>
    <div class="<?= $submission['status']==='graded' ? 'bg-primary-container' : 'bg-surface-container-low border border-outline-variant' ?> rounded-xl p-lg flex flex-col justify-center">
        <?php if ($submission['status'] === 'graded'): ?>
        <p class="font-label-md text-label-md text-on-primary-container/70 mb-1">Your Score</p>
        <span class="text-4xl font-bold text-on-primary-container"><?= formatScore($submission['overall_score']) ?></span>
        <span class="text-sm text-on-primary-container/70">/ <?= formatScore($submission['max_score']) ?> pts</span>
        <?php if ($submission['graded_at']): ?>
        <p class="text-[10px] text-on-primary-container/60 mt-2">Graded: <?= date('M d, Y g:i A', strtotime($submission['graded_at'])) ?></p>
        <?php endif; ?>
        <?php else: ?>
        <span class="px-3 py-1.5 rounded-full bg-yellow-100 text-yellow-800 text-xs font-medium text-center">Awaiting Grade</span>
        <?php endif; ?>
    </div>
</div>

<?php if ($submission['feedback']): ?>
<div class="mb-lg p-md bg-surface-container-low border border-outline-variant rounded-xl">
    <p class="font-label-lg text-label-lg text-on-surface-variant mb-xs">Faculty Feedback</p>
    <p class="text-sm text-on-surface italic">"<?= htmlspecialchars($submission['feedback']) ?>"</p>
</div>
<?php endif; ?>

<h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mb-md">Your Files</h3>

<?php if (empty($files)): ?>
<p class="text-center text-on-surface-variant py-6 text-sm mb-lg">No files submitted.</p>
<?php else: ?>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-md mb-lg">
    <?php foreach ($files as $file): ?>
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col hover:shadow-md transition-shadow">
        <?php if ($file['is_link']): ?>
        <a href="<?= htmlspecialchars($file['file_url']) ?>" target="_blank" rel="noopener"
           class="aspect-square bg-surface-container flex items-center justify-center hover:bg-surface-container-high transition-colors">
            <span class="material-symbols-outlined text-[48px] text-secondary-container">link</span>
        </a>
        <?php else: ?>
        <button type="button" onclick="openLightbox('../uploads/<?= htmlspecialchars($file['file_path']) ?>')"
            class="aspect-square bg-surface-container overflow-hidden hover:opacity-90 transition-opacity">
            <img src="../uploads/<?= htmlspecialchars($file['file_path']) ?>"
                 alt="<?= htmlspecialchars($file['original_name']) ?>"
                 class="w-full h-full object-cover"/>
        </button>
        <?php endif; ?>
        <div class="p-sm">
            <p class="font-label-md text-on-surface truncate text-xs" title="<?= htmlspecialchars($file['original_name']) ?>">
                <?= htmlspecialchars($file['is_link'] ? parse_url($file['file_url'], PHP_URL_HOST) : $file['original_name']) ?>
            </p>
            <?php if (!$file['is_link']): ?>
            <p class="text-[10px] text-outline"><?= $file['file_size_kb'] ?> KB</p>
            <?php endif; ?>
            <?php if ($file['file_feedback']): ?>
            <p class="text-xs text-on-surface-variant italic mt-xs border-t border-outline-variant pt-xs">
                "<?= htmlspecialchars($file['file_feedback']) ?>"
            </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (count($history) > 1): ?>
<div class="mb-24 bg-surface-container-low border border-outline-variant rounded-xl p-md">
    <h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mb-sm">Submission History</h3>
    <div class="space-y-xs">
        <?php foreach ($history as $h): ?>
        <div class="flex items-center justify-between text-xs py-1 border-b border-outline-variant last:border-0">
            <span class="text-on-surface-variant">Attempt <?= $h['attempt_number'] ?> · <?= date('M d, Y g:i A', strtotime($h['submitted_at'])) ?></span>
            <?php if ($h['overall_score'] !== null): ?>
            <span class="text-primary font-medium"><?= formatScore($h['overall_score']) ?> pts</span>
            <?php else: ?>
            <span class="text-outline">Pending</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="pb-24"></div>
<?php endif; ?>

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
