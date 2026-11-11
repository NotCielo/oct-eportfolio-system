<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('coordinator');

$pdo = getDB();
$uid = currentUserId();
$sid = (int)($_GET['id'] ?? 0);
if (!$sid) { header('Location: subjects.php'); exit; }

$subject = $pdo->prepare(
    "SELECT s.*, u.full_name AS faculty_name FROM subjects s JOIN users u ON u.id=s.faculty_id
     WHERE s.id=? AND s.coordinator_id=?"
);
$subject->execute([$sid, $uid]);
$subject = $subject->fetch();
if (!$subject) { header('Location: subjects.php'); exit; }

$students = $pdo->prepare(
    "SELECT u.id, u.full_name, u.school_id, u.year_level, e.joined_at,
            (SELECT COUNT(*) FROM submissions sub JOIN activities a ON a.id=sub.activity_id WHERE a.subject_id=? AND sub.student_id=u.id AND sub.status!='pending') AS submitted_count,
            (SELECT COUNT(*) FROM submissions sub JOIN activities a ON a.id=sub.activity_id WHERE a.subject_id=? AND sub.student_id=u.id AND sub.status='graded') AS graded_count
     FROM enrollments e JOIN users u ON u.id=e.student_id
     WHERE e.subject_id=? ORDER BY u.full_name"
);
$students->execute([$sid, $sid, $sid]);
$students = $students->fetchAll();

$weeks = $pdo->prepare(
    "SELECT w.*, COUNT(a.id) AS activity_count FROM weeks w
     LEFT JOIN activities a ON a.week_id=w.id
     WHERE w.subject_id=? GROUP BY w.id ORDER BY w.week_number DESC"
);
$weeks->execute([$sid]);
$weeks = $weeks->fetchAll();

include 'header.php';
?>

<div class="mb-xl">
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
        <p class="text-white/70 font-label-md"><?= htmlspecialchars($subject['faculty_name']) ?> · <?= count($students) ?> students</p>
    </div>
</div>

<h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mb-md">Weeks & Activities</h3>
<div class="space-y-sm mb-lg">
    <?php if (empty($weeks)): ?>
    <p class="text-center text-on-surface-variant py-4 text-sm">No weeks created yet.</p>
    <?php else: ?>
    <?php foreach ($weeks as $w): ?>
    <div class="flex items-center p-md bg-white rounded-xl card-border bento-shadow">
        <div class="w-10 h-10 rounded-lg bg-primary-container flex items-center justify-center mr-md shrink-0">
            <span class="material-symbols-outlined text-on-primary-container text-sm">calendar_view_week</span>
        </div>
        <div class="flex-1">
            <p class="font-label-lg text-label-lg text-on-surface">Week <?= $w['week_number'] ?><?= $w['title'] ? ' — ' . htmlspecialchars($w['title']) : '' ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant"><?= $w['activity_count'] ?> activit<?= $w['activity_count'] != 1 ? 'ies' : 'y' ?></p>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mb-md">Enrolled Students</h3>
<div class="space-y-sm pb-24">
    <?php if (empty($students)): ?>
    <p class="text-center text-on-surface-variant py-4 text-sm">No students enrolled.</p>
    <?php else: ?>
    <?php foreach ($students as $st): ?>
    <a href="student_submissions.php?student_id=<?= $st['id'] ?>&subject_id=<?= $sid ?>"
       class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow hover:bg-surface-container-low transition-colors block">
        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-sm">person</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($st['full_name']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant"><?= htmlspecialchars($st['school_id']) ?></p>
        </div>
        <div class="text-right shrink-0">
            <p class="font-label-lg text-label-lg text-primary"><?= $st['graded_count'] ?> graded</p>
            <p class="font-label-md text-label-md text-on-surface-variant"><?= $st['submitted_count'] ?> submitted</p>
        </div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
