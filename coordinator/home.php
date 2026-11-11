<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('coordinator');

$pdo = getDB();
$uid = currentUserId();

$totalSubjects = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE coordinator_id=? AND is_archived=0")->execute([$uid]) ? $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE coordinator_id=? AND is_archived=0") : null;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE coordinator_id=? AND is_archived=0");
$stmt->execute([$uid]); $totalSubjects = $stmt->fetchColumn();

$stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN subjects s ON s.id=e.subject_id WHERE s.coordinator_id=? AND s.is_archived=0");
$stmt2->execute([$uid]); $totalStudents = $stmt2->fetchColumn();

$stmt3 = $pdo->prepare("SELECT COUNT(DISTINCT s2.faculty_id) FROM subjects s2 WHERE s2.coordinator_id=? AND s2.is_archived=0");
$stmt3->execute([$uid]); $totalFaculty = $stmt3->fetchColumn();

$activeSemester = $pdo->query("SELECT s.name, ay.name AS year_name FROM semesters s JOIN academic_years ay ON ay.id=s.academic_year_id WHERE s.is_active=1 LIMIT 1")->fetch();

$recentSubs = $pdo->prepare(
    "SELECT u.full_name, sub.submitted_at, a.title AS act_title, s.code AS subj_code, sub.id AS sub_id
     FROM submissions sub
     JOIN users u ON u.id=sub.student_id
     JOIN activities a ON a.id=sub.activity_id
     JOIN subjects s ON s.id=a.subject_id
     WHERE s.coordinator_id=? AND sub.status='submitted'
     ORDER BY sub.submitted_at DESC LIMIT 5"
);
$recentSubs->execute([$uid]);
$recentSubs = $recentSubs->fetchAll();

include 'header.php';
?>

<?php if ($activeSemester): ?>
<div class="mb-lg px-4 py-3 rounded-xl bg-primary text-white flex items-center gap-2">
    <span class="material-symbols-outlined text-sm">event</span>
    <span class="text-sm font-medium"><?= htmlspecialchars($activeSemester['year_name']) ?> — <?= htmlspecialchars($activeSemester['name']) ?></span>
</div>
<?php endif; ?>

<h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mb-md">Overview</h3>

<section class="grid grid-cols-3 gap-sm sm:gap-md mb-8">
    <?php foreach ([
        ['icon'=>'menu_book','count'=>$totalSubjects,'label'=>'Subjects'],
        ['icon'=>'badge',    'count'=>$totalFaculty, 'label'=>'Faculty'],
        ['icon'=>'group',    'count'=>$totalStudents,'label'=>'Students'],
    ] as $c): ?>
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md flex flex-col items-center text-center shadow-sm">
        <span class="material-symbols-outlined text-primary text-2xl mb-xs"><?= $c['icon'] ?></span>
        <div class="text-[26px] font-bold text-on-surface leading-none"><?= $c['count'] ?></div>
        <div class="font-label-md text-label-md text-on-surface-variant mt-xs"><?= $c['label'] ?></div>
    </div>
    <?php endforeach; ?>
</section>

<div class="flex justify-between items-center mb-md">
    <h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider">Recent Submissions</h3>
</div>
<div class="space-y-sm pb-24">
    <?php if (empty($recentSubs)): ?>
    <p class="text-center text-on-surface-variant py-8 text-sm">No pending submissions.</p>
    <?php else: ?>
    <?php foreach ($recentSubs as $r): ?>
    <div class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow">
        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-sm">description</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($r['full_name']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant truncate"><?= htmlspecialchars($r['subj_code']) ?> · <?= htmlspecialchars($r['act_title']) ?></p>
        </div>
        <span class="font-label-md text-label-md text-outline shrink-0"><?= date('M d', strtotime($r['submitted_at'])) ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
