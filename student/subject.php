<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');

$pdo = getDB();
$uid = currentUserId();
$sid = (int)($_GET['id'] ?? 0);
if (!$sid) { header('Location: subjects.php'); exit; }

$enroll = $pdo->prepare("SELECT id FROM enrollments WHERE subject_id=? AND student_id=?");
$enroll->execute([$sid, $uid]);
if (!$enroll->fetch()) { header('Location: subjects.php'); exit; }

$subject = $pdo->prepare(
    "SELECT s.*, u.full_name AS faculty_name FROM subjects s JOIN users u ON u.id=s.faculty_id WHERE s.id=?"
);
$subject->execute([$sid]);
$subject = $subject->fetch();
if (!$subject) { header('Location: subjects.php'); exit; }

$weeks = $pdo->prepare("SELECT id, week_number, title FROM weeks WHERE subject_id=? ORDER BY week_number DESC");
$weeks->execute([$sid]);
$weeks = $weeks->fetchAll();

include 'header.php';
?>

<div class="mb-md">
    <button type="button" onclick="history.back()" class="flex items-center gap-sm text-on-surface-variant mb-xs">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        <span class="font-label-md text-label-md">Back</span>
    </button>
</div>

<div class="relative overflow-hidden rounded-xl bg-primary mb-lg p-lg shadow-sm">
    <div class="absolute top-0 right-0 w-32 h-32 -mr-8 -mt-8 bg-white/10 rounded-full"></div>
    <div class="relative z-10">
        <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/20 text-white font-label-md text-label-md mb-2"><?= htmlspecialchars($subject['course']) ?></span>
        <h2 class="font-headline-md text-headline-md text-white mb-1"><?= htmlspecialchars($subject['code']) ?> <?= htmlspecialchars($subject['name']) ?></h2>
        <p class="text-white/70 font-label-md"><?= htmlspecialchars($subject['faculty_name']) ?></p>
    </div>
</div>

<div class="space-y-lg pb-24">
    <?php if (empty($weeks)): ?>
    <p class="text-center text-on-surface-variant py-12 text-sm">No weeks yet. Check back later.</p>
    <?php else: ?>
    <?php foreach ($weeks as $week): ?>
    <?php
    $activities = $pdo->prepare(
        "SELECT a.id, a.title, a.category, a.max_score, a.deadline, a.is_locked,
                sub.id AS sub_id, sub.status, sub.overall_score, sub.submitted_at, sub.attempt_number
         FROM activities a
         LEFT JOIN submissions sub ON sub.activity_id=a.id AND sub.student_id=?
         WHERE a.week_id=?
         ORDER BY a.created_at DESC"
    );
    $activities->execute([$uid, $week['id']]);
    $activities = $activities->fetchAll();
    if (empty($activities)) continue;
    ?>
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
        <div class="px-lg py-md border-b border-outline-variant bg-surface-container-low">
            <h3 class="font-title-lg text-title-lg text-on-surface">
                Week <?= $week['week_number'] ?><?= $week['title'] ? ' — ' . htmlspecialchars($week['title']) : '' ?>
            </h3>
        </div>
        <div class="divide-y divide-outline-variant">
            <?php foreach ($activities as $act):
                $isPastDeadline = $act['deadline'] && strtotime($act['deadline']) < time();
                $isLocked       = $act['is_locked'] || $isPastDeadline;
                $canSubmit      = !$isLocked && ($act['status'] === null || $act['status'] === 'pending');
                $canResubmit    = !$isLocked && $act['status'] === 'submitted' && $act['attempt_number'] < 3;
            ?>
            <div class="flex items-center gap-md p-md">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0
                    <?= $act['status']==='graded' ? 'bg-primary-container' : ($act['status']==='submitted' ? 'bg-secondary-fixed' : ($isLocked ? 'bg-error/10' : 'bg-surface-container-high')) ?>">
                    <span class="material-symbols-outlined text-sm
                        <?= $act['status']==='graded' ? 'text-on-primary-container' : ($act['status']==='submitted' ? 'text-on-secondary-fixed' : ($isLocked ? 'text-error' : 'text-on-surface-variant')) ?>">
                        <?= $act['status']==='graded' ? 'quiz' : ($act['status']==='submitted' ? 'upload_file' : ($isLocked ? 'lock' : 'assignment')) ?>
                    </span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-label-lg text-label-lg text-on-surface"><?= htmlspecialchars($act['title']) ?></p>
                    <p class="font-label-md text-label-md text-on-surface-variant text-xs">
                        <?= htmlspecialchars($act['category']) ?> · <?= formatScore($act['max_score']) ?> pts
                        <?php if ($act['deadline']): ?>
                         · <span class="<?= $isPastDeadline ? 'text-error' : 'text-pending' ?>">
                            <?= $isPastDeadline ? 'Due was' : 'Due' ?> <?= date('M d g:i A', strtotime($act['deadline'])) ?>
                          </span>
                        <?php endif; ?>
                    </p>
                    <?php if ($act['submitted_at']): ?>
                    <p class="text-[10px] text-outline mt-0.5">
                        Submitted: <?= date('M d, Y g:i A', strtotime($act['submitted_at'])) ?>
                        <?= $act['attempt_number'] > 1 ? ' · Attempt ' . $act['attempt_number'] . '/3' : '' ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="text-right shrink-0 ml-sm">
                    <?php if ($act['status'] === 'graded'): ?>
                        <span class="block font-label-lg text-label-lg text-primary"><?= formatScore($act['overall_score']) ?>/<?= formatScore($act['max_score']) ?></span>
                        <a href="submission.php?id=<?= $act['sub_id'] ?>"
                           class="text-[10px] text-on-surface-variant hover:underline">View</a>
                    <?php elseif ($act['status'] === 'submitted'): ?>
                        <span class="block text-[10px] px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800 font-bold mb-1">Submitted</span>
                        <?php if ($canResubmit): ?>
                        <a href="upload.php?activity_id=<?= $act['id'] ?>"
                           class="text-[10px] text-primary hover:underline">Resubmit (<?= 3 - $act['attempt_number'] ?> left)</a>
                        <?php else: ?>
                        <span class="text-[10px] text-outline">Max attempts</span>
                        <?php endif; ?>
                    <?php elseif ($canSubmit): ?>
                        <a href="upload.php?activity_id=<?= $act['id'] ?>"
                           class="inline-flex items-center gap-1 text-xs text-primary font-medium border border-primary/30 px-2 py-1 rounded-lg hover:bg-primary/5 transition-colors">
                            <span class="material-symbols-outlined text-sm">upload</span> Submit
                        </a>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-xs text-outline border border-outline-variant px-2 py-1 rounded-lg">
                            <span class="material-symbols-outlined text-sm">lock</span>
                            <?= $act['is_locked'] ? 'Locked' : 'Past Due' ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
