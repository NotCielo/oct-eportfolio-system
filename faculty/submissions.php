<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('faculty');

$pdo = getDB();
$fid = currentUserId();
$aid = (int)($_GET['activity_id'] ?? 0);
if (!$aid) { header('Location: subjects.php'); exit; }

$activity = $pdo->prepare(
    "SELECT a.*, s.faculty_id, s.id AS subject_id, s.code, s.name AS subj_name, w.week_number
     FROM activities a
     JOIN subjects s ON s.id=a.subject_id
     LEFT JOIN weeks w ON w.id=a.week_id
     WHERE a.id=?"
);
$activity->execute([$aid]);
$activity = $activity->fetch();
if (!$activity || $activity['faculty_id'] != $fid) { header('Location: subjects.php'); exit; }

$students = $pdo->prepare(
    "SELECT u.id, u.full_name, u.school_id,
            sub.id AS sub_id, sub.status, sub.overall_score, sub.submitted_at, sub.attempt_number,
            (SELECT COUNT(*) FROM files f WHERE f.submission_id=sub.id) AS file_count
     FROM enrollments e
     JOIN users u ON u.id=e.student_id
     LEFT JOIN submissions sub ON sub.activity_id=? AND sub.student_id=u.id
     WHERE e.subject_id=?
     ORDER BY
        CASE sub.status WHEN 'submitted' THEN 0 WHEN 'pending' THEN 1 WHEN 'graded' THEN 2 ELSE 3 END,
        u.full_name"
);
$students->execute([$aid, $activity['subject_id']]);
$students = $students->fetchAll();

include 'header.php';
?>

<div class="mb-md">
    <button type="button" onclick="history.back()" class="flex items-center gap-sm text-on-surface-variant mb-xs">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        <span class="font-label-md text-label-md">Back</span>
    </button>
</div>

<div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm mb-lg">
    <p class="text-sm text-on-surface-variant"><?= htmlspecialchars($activity['code']) ?> · Week <?= $activity['week_number'] ?></p>
    <h2 class="font-headline-md text-headline-md text-on-surface mt-1"><?= htmlspecialchars($activity['title']) ?></h2>
    <p class="font-label-md text-label-md text-on-surface-variant mt-1">
        <?= htmlspecialchars($activity['category']) ?> · Max: <?= formatScore($activity['max_score']) ?> pts
        <?= $activity['deadline'] ? ' · Due ' . date('M d, Y g:i A', strtotime($activity['deadline'])) : '' ?>
    </p>
</div>

<div class="space-y-sm pb-24">
    <?php foreach ($students as $st): ?>
    <div class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow">
        <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0
            <?= $st['status']==='submitted' ? 'bg-yellow-100' : ($st['status']==='graded' ? 'bg-primary/10' : 'bg-surface-container-high') ?>">
            <span class="material-symbols-outlined text-sm
                <?= $st['status']==='submitted' ? 'text-pending' : ($st['status']==='graded' ? 'text-primary' : 'text-on-surface-variant') ?>">
                <?= $st['status']==='submitted' ? 'upload_file' : ($st['status']==='graded' ? 'quiz' : 'person') ?>
            </span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($st['full_name']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant text-xs">
                <?= htmlspecialchars($st['school_id']) ?>
                <?php if ($st['submitted_at']): ?>
                 · <?= date('M d, Y g:i A', strtotime($st['submitted_at'])) ?>
                <?php endif; ?>
                <?php if ($st['attempt_number'] > 1): ?>
                 · Attempt <?= $st['attempt_number'] ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="text-right shrink-0">
            <?php if ($st['status'] === 'submitted'): ?>
                <span class="block text-xs font-medium text-pending mb-1"><?= $st['file_count'] ?> file<?= $st['file_count']!=1?'s':'' ?></span>
                <a href="grade.php?id=<?= $st['sub_id'] ?>"
                   class="text-xs px-3 py-1.5 rounded-lg bg-primary text-white font-medium hover:bg-green-800 transition-colors">
                    Grade
                </a>
            <?php elseif ($st['status'] === 'graded'): ?>
                <span class="block font-label-lg text-label-lg text-primary"><?= formatScore($st['overall_score']) ?>/<?= formatScore($activity['max_score']) ?></span>
                <a href="grade.php?id=<?= $st['sub_id'] ?>" class="text-xs text-on-surface-variant hover:underline">Edit</a>
            <?php else: ?>
                <span class="text-xs text-outline">No submission</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

</main>
<?php include 'footer.php'; ?>
