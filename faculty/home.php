<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('faculty');

$pdo = getDB();
$fid = currentUserId();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE faculty_id=? AND is_archived=0 AND is_active=1");
$stmt->execute([$fid]); $totalSubjects = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN subjects s ON s.id=e.subject_id WHERE s.faculty_id=? AND s.is_archived=0");
$stmt->execute([$fid]); $totalStudents = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions sub JOIN activities a ON a.id=sub.activity_id JOIN subjects s ON s.id=a.subject_id WHERE s.faculty_id=? AND sub.status='submitted'");
$stmt->execute([$fid]); $pendingGrade = $stmt->fetchColumn();

$recentSubs = $pdo->prepare(
    "SELECT sub.id, sub.submitted_at, sub.attempt_number,
            u.full_name, s.code AS subj_code, a.title AS act_title, w.week_number
     FROM submissions sub
     JOIN users u ON u.id=sub.student_id
     JOIN activities a ON a.id=sub.activity_id
     LEFT JOIN weeks w ON w.id=a.week_id
     JOIN subjects s ON s.id=a.subject_id
     WHERE s.faculty_id=? AND sub.status='submitted'
     ORDER BY sub.submitted_at DESC LIMIT 6"
);
$recentSubs->execute([$fid]);
$recentSubs = $recentSubs->fetchAll();

include 'header.php';
?>

<h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mb-md">Overview</h3>

<section class="grid grid-cols-3 gap-sm sm:gap-md mb-8">
    <?php foreach ([
        ['icon'=>'menu_book','count'=>$totalSubjects,'label'=>'Subjects'],
        ['icon'=>'group',    'count'=>$totalStudents,'label'=>'Students'],
        ['icon'=>'pending',  'count'=>$pendingGrade, 'label'=>'To Grade','color'=>'text-pending'],
    ] as $c): ?>
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md flex flex-col items-center text-center shadow-sm">
        <span class="material-symbols-outlined <?= $c['color'] ?? 'text-primary' ?> text-2xl mb-xs"><?= $c['icon'] ?></span>
        <div class="text-[26px] font-bold text-on-surface leading-none"><?= $c['count'] ?></div>
        <div class="font-label-md text-label-md text-on-surface-variant mt-xs"><?= $c['label'] ?></div>
    </div>
    <?php endforeach; ?>
</section>

<div class="flex justify-between items-center mb-md">
    <h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider">Recent Submissions</h3>
    <a href="subjects.php" class="text-xs text-primary hover:underline">View All</a>
</div>
<div class="space-y-sm pb-24">
    <?php if (empty($recentSubs)): ?>
    <p class="text-center text-on-surface-variant py-8 text-sm">No pending submissions.</p>
    <?php else: ?>
    <?php foreach ($recentSubs as $r): ?>
    <a href="grade.php?id=<?= $r['id'] ?>"
       class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow hover:bg-surface-container-low transition-colors block">
        <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-pending text-sm">upload_file</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($r['full_name']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant truncate">
                <?= htmlspecialchars($r['subj_code']) ?>
                <?= $r['week_number'] ? ' · Week ' . $r['week_number'] : '' ?>
                · <?= htmlspecialchars($r['act_title']) ?>
                <?= $r['attempt_number'] > 1 ? ' · Attempt ' . $r['attempt_number'] : '' ?>
            </p>
        </div>
        <span class="font-label-md text-label-md text-outline shrink-0"><?= date('M d g:i A', strtotime($r['submitted_at'])) ?></span>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
