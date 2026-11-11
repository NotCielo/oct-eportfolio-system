<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('dept_head');

$pdo    = getDB();
$deptId = currentDeptId();
$search = trim($_GET['q'] ?? '');

$sql = "SELECT u.id, u.full_name, u.school_id, u.is_active,
               COUNT(DISTINCT s.id) AS subject_count,
               COUNT(DISTINCT e.student_id) AS student_count
        FROM users u
        LEFT JOIN subjects s ON s.faculty_id=u.id AND s.is_archived=0
        LEFT JOIN enrollments e ON e.subject_id=s.id
        WHERE u.role='faculty' AND u.department_id=?";
$params = [$deptId];
if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.school_id LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$sql .= " GROUP BY u.id ORDER BY u.full_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$faculty = $stmt->fetchAll();

include 'header.php';
?>

<div class="flex items-center justify-between mb-lg flex-wrap gap-sm">
    <h2 class="font-headline-md text-headline-md text-on-surface">Faculty</h2>
</div>

<div class="relative mb-md">
    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-sm">search</span>
    <form method="GET">
        <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search faculty..."
            class="w-full pl-9 pr-4 py-2 rounded-xl border border-outline-variant text-sm focus:outline-none focus:border-primary transition-all"/>
    </form>
</div>

<div class="space-y-sm pb-24">
    <?php if (empty($faculty)): ?>
    <p class="text-center text-on-surface-variant py-16 text-sm">No faculty found.</p>
    <?php else: ?>
    <?php foreach ($faculty as $f): ?>
    <a href="faculty_detail.php?id=<?= $f['id'] ?>"
       class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow hover:bg-surface-container-low transition-colors block <?= !$f['is_active'] ? 'opacity-60' : '' ?>">
        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-sm">badge</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($f['full_name']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant"><?= htmlspecialchars($f['school_id']) ?></p>
        </div>
        <div class="text-right shrink-0">
            <p class="font-label-lg text-label-lg text-on-surface"><?= $f['subject_count'] ?> subj</p>
            <p class="font-label-md text-label-md text-on-surface-variant"><?= $f['student_count'] ?> students</p>
        </div>
        <span class="material-symbols-outlined text-outline">chevron_right</span>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
