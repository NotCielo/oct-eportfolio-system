<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('faculty');

$pdo       = getDB();
$fid       = currentUserId();
$studentId = (int)($_GET['student_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);
if (!$studentId || !$subjectId) { header('Location: subjects.php'); exit; }

$subject = $pdo->prepare("SELECT id, code, name, faculty_id FROM subjects WHERE id=? AND faculty_id=?");
$subject->execute([$subjectId, $fid]);
$subject = $subject->fetch();
if (!$subject) { header('Location: subjects.php'); exit; }

$student = $pdo->prepare("SELECT id, full_name, school_id, year_level FROM users WHERE id=?");
$student->execute([$studentId]);
$student = $student->fetch();
if (!$student) { header('Location: subjects.php'); exit; }

$weeks = $pdo->prepare("SELECT id, week_number, title FROM weeks WHERE subject_id=? ORDER BY week_number DESC");
$weeks->execute([$subjectId]);
$weeks = $weeks->fetchAll();

include 'header.php';
?>

<div class="mb-md">
    <button type="button" onclick="history.back()" class="flex items-center gap-sm text-on-surface-variant mb-xs">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        <span class="font-label-md text-label-md">Back</span>
    </button>
</div>

<div class="bg-primary rounded-xl p-lg mb-lg text-white">
    <h2 class="font-headline-md text-headline-md font-bold"><?= htmlspecialchars($student['full_name']) ?></h2>
    <p class="text-white/70 text-sm"><?= htmlspecialchars($student['school_id']) ?><?= $student['year_level'] ? ' · ' . strtoupper($student['year_level']) : '' ?></p>
    <p class="text-white/60 text-xs mt-1"><?= htmlspecialchars($subject['code']) ?> — <?= htmlspecialchars($subject['name']) ?></p>
</div>

<div class="space-y-lg pb-24">
    <?php if (empty($weeks)): ?>
    <p class="text-center text-on-surface-variant py-8 text-sm">No weeks yet.</p>
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
            <div class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0
                    <?= $act['status']==='graded' ? 'bg-primary-container' : ($act['status']==='submitted' ? 'bg-yellow-100' : 'bg-surface-container-high') ?>">
                    <span class="material-symbols-outlined text-sm
                        <?= $act['status']==='graded' ? 'text-on-primary-container' : ($act['status']==='submitted' ? 'text-pending' : 'text-on-surface-variant') ?>">
                        <?= $act['status']==='graded' ? 'quiz' : ($act['status']==='submitted' ? 'upload_file' : 'assignment') ?>
                    </span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($act['title']) ?></p>
                    <p class="font-label-md text-label-md text-on-surface-variant text-xs">
                        <?= htmlspecialchars($act['category']) ?>
                        <?= $act['submitted_at'] ? ' · ' . date('M d, Y g:i A', strtotime($act['submitted_at'])) : '' ?>
                        <?= $act['attempt_number'] > 1 ? ' · Attempt ' . $act['attempt_number'] : '' ?>
                    </p>
                </div>
                <div class="text-right shrink-0 ml-sm">
                    <?php if ($act['status'] === 'graded'): ?>
                    <span class="block font-label-lg text-label-lg text-primary"><?= formatScore($act['overall_score']) ?>/<?= formatScore($act['max_score']) ?></span>
                    <a href="grade.php?id=<?= $act['sub_id'] ?>" class="text-[10px] text-on-surface-variant hover:underline">Edit</a>
                    <?php elseif ($act['status'] === 'submitted'): ?>
                    <span class="block font-label-md text-label-md text-pending"><?= $act['file_count'] ?> file<?= $act['file_count']!=1?'s':'' ?></span>
                    <a href="grade.php?id=<?= $act['sub_id'] ?>"
                       class="text-[10px] px-2 py-1 bg-primary text-white rounded-lg hover:bg-green-800 transition-colors">Grade</a>
                    <?php else: ?>
                    <span class="text-outline text-xs">--/<?= formatScore($act['max_score']) ?></span>
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
