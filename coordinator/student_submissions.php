<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('coordinator');

$pdo       = getDB();
$uid       = currentUserId();
$studentId = (int)($_GET['student_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);
if (!$studentId || !$subjectId) { header('Location: subjects.php'); exit; }

$subject = $pdo->prepare("SELECT * FROM subjects WHERE id=? AND coordinator_id=?");
$subject->execute([$subjectId, $uid]);
$subject = $subject->fetch();
if (!$subject) { header('Location: subjects.php'); exit; }

$student = $pdo->prepare("SELECT id, full_name, school_id, year_level FROM users WHERE id=?");
$student->execute([$studentId]);
$student = $student->fetch();
if (!$student) { header('Location: subjects.php'); exit; }

$weeks = $pdo->prepare(
    "SELECT w.id, w.week_number, w.title FROM weeks w WHERE w.subject_id=? ORDER BY w.week_number DESC"
);
$weeks->execute([$subjectId]);
$weeks = $weeks->fetchAll();

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

<div class="mb-xl">
    <button type="button" onclick="history.back()" class="flex items-center gap-sm text-on-surface-variant mb-xs">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        <span class="font-label-md text-label-md">Back</span>
    </button>
</div>

<div class="bg-primary rounded-xl p-lg mb-lg text-white">
    <h2 class="font-headline-md text-headline-md font-bold"><?= htmlspecialchars($student['full_name']) ?></h2>
    <p class="text-white/70 text-sm"><?= htmlspecialchars($student['school_id']) ?></p>
    <p class="text-white/60 text-xs mt-1"><?= htmlspecialchars($subject['code']) ?> — <?= htmlspecialchars($subject['name']) ?></p>
</div>

<div class="space-y-lg pb-24">
    <?php if (empty($weeks)): ?>
    <p class="text-center text-on-surface-variant py-8 text-sm">No weeks in this subject yet.</p>
    <?php else: ?>
    <?php foreach ($weeks as $week): ?>
    <?php
    $activities = $pdo->prepare(
        "SELECT a.*, sub.id AS sub_id, sub.status, sub.overall_score,
                sub.submitted_at, sub.attempt_number, sub.feedback,
                (SELECT COUNT(*) FROM files f WHERE f.submission_id=sub.id) AS file_count
         FROM activities a
         LEFT JOIN submissions sub ON sub.activity_id=a.id AND sub.student_id=?
         WHERE a.week_id=?
         ORDER BY a.created_at DESC"
    );
    $activities->execute([$studentId, $week['id']]);
    $activities = $activities->fetchAll();
    if (empty($activities)) continue;
    ?>
    <div>
        <h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mb-sm">
            Week <?= $week['week_number'] ?><?= $week['title'] ? ' — ' . htmlspecialchars($week['title']) : '' ?>
        </h3>
        <div class="space-y-md">
            <?php foreach ($activities as $act): ?>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">

                <!-- Activity header row -->
                <div class="flex items-center gap-md p-md border-b border-outline-variant
                    <?= $act['status']==='graded' ? 'bg-primary-container/10' : ($act['status']==='submitted' ? 'bg-yellow-50' : '') ?>">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0
                        <?= $act['status']==='graded' ? 'bg-primary-container' : ($act['status']==='submitted' ? 'bg-yellow-100' : 'bg-surface-container-high') ?>">
                        <span class="material-symbols-outlined text-sm
                            <?= $act['status']==='graded' ? 'text-on-primary-container' : ($act['status']==='submitted' ? 'text-pending' : 'text-on-surface-variant') ?>">
                            <?= $act['status']==='graded' ? 'quiz' : ($act['status']==='submitted' ? 'upload_file' : 'assignment') ?>
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-label-lg text-label-lg text-on-surface"><?= htmlspecialchars($act['title']) ?></p>
                        <p class="font-label-md text-label-md text-on-surface-variant text-xs">
                            <?= htmlspecialchars($act['category']) ?> · Max: <?= formatScore($act['max_score']) ?> pts
                            <?= $act['submitted_at'] ? ' · Submitted: ' . date('M d, Y g:i A', strtotime($act['submitted_at'])) : '' ?>
                            <?= $act['attempt_number'] > 1 ? ' · Attempt ' . $act['attempt_number'] . '/3' : '' ?>
                        </p>
                    </div>
                    <div class="text-right shrink-0">
                        <?php if ($act['status'] === 'graded'): ?>
                            <span class="block font-label-lg text-label-lg text-primary"><?= formatScore($act['overall_score']) ?>/<?= formatScore($act['max_score']) ?></span>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-primary-fixed text-on-primary-fixed font-bold uppercase">Graded</span>
                        <?php elseif ($act['status'] === 'submitted'): ?>
                            <span class="block font-label-md text-label-md text-pending"><?= $act['file_count'] ?> file<?= $act['file_count']!=1?'s':'' ?></span>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-yellow-100 text-yellow-800 font-bold uppercase">Submitted</span>
                        <?php else: ?>
                            <span class="text-outline text-xs">No submission</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($act['sub_id'] && in_array($act['status'], ['submitted','graded'])): ?>
                <?php
                $files = $pdo->prepare("SELECT * FROM files WHERE submission_id=? ORDER BY uploaded_at");
                $files->execute([$act['sub_id']]);
                $files = $files->fetchAll();
                ?>

                <?php if (!empty($files)): ?>
                <div class="p-md">
                    <p class="font-label-md text-label-md text-outline uppercase tracking-wider mb-sm text-xs">Submitted Files</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-sm">
                        <?php foreach ($files as $file): ?>
                        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                            <?php if ($file['is_link']): ?>
                            <a href="<?= htmlspecialchars($file['file_url']) ?>" target="_blank" rel="noopener"
                               class="aspect-square bg-surface-container flex items-center justify-center hover:bg-surface-container-high transition-colors">
                                <span class="material-symbols-outlined text-[40px] text-secondary-container">link</span>
                            </a>
                            <div class="p-xs">
                                <p class="text-[10px] text-on-surface-variant truncate"><?= htmlspecialchars(parse_url($file['file_url'], PHP_URL_HOST) ?: $file['file_url']) ?></p>
                            </div>
                            <?php else: ?>
                            <button type="button"
                                onclick="openLightbox('../uploads/<?= htmlspecialchars($file['file_path']) ?>')"
                                class="aspect-square bg-surface-container overflow-hidden hover:opacity-90 transition-opacity">
                                <img src="../uploads/<?= htmlspecialchars($file['file_path']) ?>"
                                     alt="<?= htmlspecialchars($file['original_name']) ?>"
                                     class="w-full h-full object-cover"/>
                            </button>
                            <div class="p-xs">
                                <p class="text-[10px] text-on-surface truncate" title="<?= htmlspecialchars($file['original_name']) ?>">
                                    <?= htmlspecialchars($file['original_name']) ?>
                                </p>
                                <p class="text-[10px] text-outline"><?= $file['file_size_kb'] ?> KB</p>
                                <?php if ($file['file_feedback']): ?>
                                <p class="text-[10px] text-on-surface-variant italic border-t border-outline-variant pt-xs mt-xs">
                                    "<?= htmlspecialchars($file['file_feedback']) ?>"
                                </p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($act['status'] === 'graded' && $act['feedback']): ?>
                <div class="px-md pb-md">
                    <p class="text-xs text-on-surface-variant italic bg-surface-container-low border border-outline-variant rounded-lg px-3 py-2">
                        Faculty feedback: "<?= htmlspecialchars($act['feedback']) ?>"
                    </p>
                </div>
                <?php endif; ?>

                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

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
