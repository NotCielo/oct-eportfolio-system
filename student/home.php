<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');

$pdo = getDB();
$uid = currentUserId();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id=?");
$stmt->execute([$uid]); $totalSubjects = $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM activities a
     JOIN subjects s ON s.id=a.subject_id
     JOIN enrollments e ON e.subject_id=s.id AND e.student_id=?
     LEFT JOIN submissions sub ON sub.activity_id=a.id AND sub.student_id=?
     WHERE (sub.id IS NULL OR sub.status='pending') AND a.is_locked=0
     AND (a.deadline IS NULL OR a.deadline > NOW())"
);
$stmt->execute([$uid, $uid]); $pendingActivities = $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM submissions WHERE student_id=? AND status='graded'"
);
$stmt->execute([$uid]); $totalGraded = $stmt->fetchColumn();

$upcoming = $pdo->prepare(
    "SELECT a.id, a.title, a.deadline, a.max_score, s.code, w.week_number,
            sub.status
     FROM activities a
     JOIN subjects s ON s.id=a.subject_id
     JOIN enrollments e ON e.subject_id=s.id AND e.student_id=?
     LEFT JOIN weeks w ON w.id=a.week_id
     LEFT JOIN submissions sub ON sub.activity_id=a.id AND sub.student_id=?
     WHERE a.deadline IS NOT NULL AND a.deadline > NOW()
     AND a.is_locked=0 AND (sub.id IS NULL OR sub.status='pending')
     ORDER BY a.deadline ASC LIMIT 5"
);
$upcoming->execute([$uid, $uid]);
$upcoming = $upcoming->fetchAll();

include 'header.php';
?>

<section class="relative overflow-hidden rounded-xl p-lg text-white mb-lg shadow-sm"
    style="background-color:#007608;background-image:radial-gradient(at 0% 0%,#005b04 0,transparent 50%),radial-gradient(at 100% 0%,#2f7534 0,transparent 50%)">
    <div class="relative z-10">
        <p class="text-white/70 text-sm mb-1">Welcome back,</p>
        <h2 class="font-headline-md text-headline-md font-bold"><?= htmlspecialchars(currentName()) ?></h2>
    </div>
</section>

<section class="grid grid-cols-3 gap-sm sm:gap-md mb-8">
    <?php foreach ([
        ['icon'=>'menu_book', 'count'=>$totalSubjects,     'label'=>'Subjects'],
        ['icon'=>'pending',   'count'=>$pendingActivities, 'label'=>'Pending', 'color'=>'text-pending'],
        ['icon'=>'quiz',      'count'=>$totalGraded,       'label'=>'Graded'],
    ] as $c): ?>
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md flex flex-col items-center text-center shadow-sm">
        <span class="material-symbols-outlined <?= $c['color'] ?? 'text-primary' ?> text-2xl mb-xs"><?= $c['icon'] ?></span>
        <div class="text-[26px] font-bold text-on-surface leading-none"><?= $c['count'] ?></div>
        <div class="font-label-md text-label-md text-on-surface-variant mt-xs"><?= $c['label'] ?></div>
    </div>
    <?php endforeach; ?>
</section>

<div class="flex justify-between items-center mb-md">
    <h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider">Upcoming Deadlines</h3>
    <a href="subjects.php" class="text-xs text-primary hover:underline">All Subjects</a>
</div>
<div class="space-y-sm pb-24">
    <?php if (empty($upcoming)): ?>
    <p class="text-center text-on-surface-variant py-8 text-sm">No upcoming deadlines.</p>
    <?php else: ?>
    <?php foreach ($upcoming as $act): ?>
    <div class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow">
        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-pending text-sm">assignment_late</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($act['title']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant text-xs">
                <?= htmlspecialchars($act['code']) ?>
                <?= $act['week_number'] ? ' · Week ' . $act['week_number'] : '' ?>
            </p>
        </div>
        <div class="text-right shrink-0">
            <p class="font-label-md text-label-md text-pending"><?= date('M d', strtotime($act['deadline'])) ?></p>
            <p class="text-[10px] text-outline"><?= date('g:i A', strtotime($act['deadline'])) ?></p>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
