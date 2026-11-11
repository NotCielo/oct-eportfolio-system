<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('dept_head');

$pdo    = getDB();
$deptId = currentDeptId();
$search = trim($_GET['q'] ?? '');

$sql = "SELECT DISTINCT u.id, u.full_name, u.school_id, u.year_level,
               COUNT(DISTINCT e.subject_id) AS subject_count,
               COUNT(DISTINCT CASE WHEN sub.status='graded' THEN sub.id END) AS graded_count,
               COUNT(DISTINCT CASE WHEN sub.status='submitted' THEN sub.id END) AS pending_count
        FROM enrollments e
        JOIN users u ON u.id=e.student_id
        JOIN subjects s ON s.id=e.subject_id
        JOIN users fac ON fac.id=s.faculty_id
        LEFT JOIN activities a ON a.subject_id=s.id
        LEFT JOIN submissions sub ON sub.activity_id=a.id AND sub.student_id=u.id
        WHERE fac.department_id=? AND s.is_archived=0";
$params = [$deptId];
if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.school_id LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$sql .= " GROUP BY u.id ORDER BY u.full_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$students = $stmt->fetchAll();

include 'header.php';
?>

<div class="mb-lg">
    <h2 class="font-headline-md text-headline-md text-on-surface">All Students</h2>
    <p class="text-sm text-on-surface-variant">All students enrolled in subjects under your department.</p>
</div>

<div class="relative mb-md">
    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-sm">search</span>
    <form method="GET">
        <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search student..."
            class="w-full pl-9 pr-4 py-2 rounded-xl border border-outline-variant text-sm focus:outline-none focus:border-primary transition-all"/>
    </form>
</div>

<div class="space-y-sm pb-24">
    <?php if (empty($students)): ?>
    <p class="text-center text-on-surface-variant py-16 text-sm">No students found.</p>
    <?php else: ?>
    <?php foreach ($students as $st): ?>
    <div class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow">
        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-sm">person</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($st['full_name']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant">
                <?= htmlspecialchars($st['school_id']) ?>
                <?= $st['year_level'] ? ' · ' . strtoupper($st['year_level']) : '' ?>
                · <?= $st['subject_count'] ?> subj
            </p>
        </div>
        <div class="text-right shrink-0">
            <p class="font-label-lg text-label-lg text-primary"><?= $st['graded_count'] ?> graded</p>
            <?php if ($st['pending_count'] > 0): ?>
            <p class="font-label-md text-label-md text-pending"><?= $st['pending_count'] ?> pending</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
