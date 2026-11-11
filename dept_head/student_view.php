<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('dept_head');

$pdo       = getDB();
$deptId    = currentDeptId();
$studentId = (int)($_GET['student_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);
if (!$studentId || !$subjectId) { header('Location: faculty.php'); exit; }

$subject = $pdo->prepare(
    "SELECT s.*, u.department_id FROM subjects s JOIN users u ON u.id=s.faculty_id WHERE s.id=?"
);
$subject->execute([$subjectId]);
$subject = $subject->fetch();
if (!$subject || $subject['department_id'] != $deptId) { header('Location: faculty.php'); exit; }

$student = $pdo->prepare("SELECT id, full_name, school_id, year_level FROM users WHERE id=?");
$student->execute([$studentId]);
$student = $student->fetch();
if (!$student) { header('Location: faculty.php'); exit; }

$weeks = $pdo->prepare("SELECT id, week_number, title FROM weeks WHERE subject_id=? ORDER BY week_number DESC");
$weeks->execute([$subjectId]);
$weeks = $weeks->fetchAll();

include 'header.php';
?>

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
        "SELECT a.id, a.title, a.category, a.max_score,
                sub.id AS sub_id, sub.status, sub.overall_score, sub.submitted_at, sub.attempt_number,
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
        <div class="space-y-sm">
            <?php foreach ($activities as $act): ?>
            <div class="flex items-center p-md bg-white rounded-xl card-border bento-shadow">
                <div class="w-10 h-10 rounded-lg <?= $act['status']==='graded' ? 'bg-primary-container' : ($act['status']==='submitted' ? 'bg-secondary-fixed' : 'bg-surface-container-high') ?> flex items-center justify-center mr-md shrink-0">
                    <span class="material-symbols-outlined text-sm <?= $act['status']==='graded' ? 'text-on-primary-container' : ($act['status']==='submitted' ? 'text-on-secondary-fixed' : 'text-on-surface-variant') ?>">
                        <?= $act['status']==='graded' ? 'quiz' : ($act['status']==='submitted' ? 'upload_file' : 'assignment') ?>
                    </span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($act['title']) ?></p>
                    <p class="font-label-md text-label-md text-on-surface-variant text-xs">
                        <?= htmlspecialchars($act['category']) ?>
                        <?php if ($act['submitted_at']): ?>
                         · <?= date('M d, Y g:i A', strtotime($act['submitted_at'])) ?>
                        <?php endif; ?>
                        <?php if ($act['attempt_number'] > 1): ?>
                         · Attempt <?= $act['attempt_number'] ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="text-right shrink-0 ml-sm">
                    <?php if ($act['status'] === 'graded'): ?>
                    <span class="block font-label-lg text-label-lg text-primary"><?= formatScore($act['overall_score']) ?>/<?= formatScore($act['max_score']) ?></span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-primary-fixed text-on-primary-fixed font-bold uppercase">Graded</span>
                    <?php elseif ($act['status'] === 'submitted'): ?>
                    <span class="block font-label-md text-label-md text-on-surface-variant"><?= $act['file_count'] ?> file<?= $act['file_count']!=1?'s':'' ?></span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-yellow-100 text-yellow-800 font-bold uppercase">Submitted</span>
                    <?php else: ?>
                    <span class="text-outline font-label-md text-label-md">No submission</span>
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
