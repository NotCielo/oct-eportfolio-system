<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');

$pdo = getDB();
$uid = currentUserId();

$subjects = $pdo->prepare(
    "SELECT s.id, s.code, s.name, s.course, u.full_name AS faculty_name,
            sem.name AS semester_name,
            (SELECT COUNT(*) FROM activities a WHERE a.subject_id=s.id) AS total_act,
            (SELECT COUNT(*) FROM submissions sub JOIN activities a ON a.id=sub.activity_id WHERE a.subject_id=s.id AND sub.student_id=? AND sub.status='graded') AS graded_count
     FROM enrollments e
     JOIN subjects s ON s.id=e.subject_id
     JOIN users u ON u.id=s.faculty_id
     LEFT JOIN semesters sem ON sem.id=s.semester_id
     WHERE e.student_id=? AND s.is_archived=0
     ORDER BY s.created_at DESC"
);
$subjects->execute([$uid, $uid]);
$subjects = $subjects->fetchAll();

include 'header.php';
?>

<div class="flex items-center justify-between mb-lg">
    <div>
        <h2 class="font-headline-md text-headline-md text-on-surface">My Subjects</h2>
        <p class="text-sm text-on-surface-variant">Tap a subject to view activities.</p>
    </div>
    <a href="add.php"
       class="flex items-center gap-1 px-4 py-2 rounded-full bg-primary text-white text-sm font-medium hover:bg-green-800 transition-colors">
        <span class="material-symbols-outlined text-sm">add</span> Join
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-24">
    <?php if (empty($subjects)): ?>
    <div class="col-span-2 text-center py-16 text-on-surface-variant">
        <p class="text-sm mb-3">No subjects yet.</p>
        <a href="add.php" class="text-sm text-primary hover:underline">Join a subject with a code</a>
    </div>
    <?php else: ?>
    <?php foreach ($subjects as $s): ?>
    <a href="subject.php?id=<?= $s['id'] ?>"
       class="bg-surface rounded-xl border border-outline-variant overflow-hidden hover:shadow-md transition-shadow block">
        <div class="p-md">
            <div class="flex items-start gap-md mb-md">
                <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' 1;">menu_book</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-label-lg text-label-lg text-on-surface"><?= htmlspecialchars($s['code']) ?></h3>
                    <p class="font-label-md text-label-md text-on-surface-variant truncate"><?= htmlspecialchars($s['name']) ?></p>
                </div>
            </div>
            <p class="text-xs text-outline mb-sm"><?= htmlspecialchars($s['faculty_name']) ?><?= $s['semester_name'] ? ' · ' . htmlspecialchars($s['semester_name']) : '' ?></p>
            <div class="flex items-center justify-between">
                <span class="text-xs text-on-surface-variant"><?= $s['graded_count'] ?>/<?= $s['total_act'] ?> graded</span>
                <div class="flex-1 mx-3 bg-outline-variant rounded-full h-1.5 overflow-hidden">
                    <?php $pct = $s['total_act'] > 0 ? round(($s['graded_count'] / $s['total_act']) * 100) : 0; ?>
                    <div class="bg-primary h-full rounded-full" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="text-xs text-primary font-medium"><?= $pct ?>%</span>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
