<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('dept_head');

$pdo    = getDB();
$deptId = currentDeptId();
$fid    = (int)($_GET['id'] ?? 0);
if (!$fid) { header('Location: faculty.php'); exit; }

$faculty = $pdo->prepare("SELECT id, full_name, school_id FROM users WHERE id=? AND role='faculty' AND department_id=?");
$faculty->execute([$fid, $deptId]);
$faculty = $faculty->fetch();
if (!$faculty) { header('Location: faculty.php'); exit; }

$subjects = $pdo->prepare(
    "SELECT s.id, s.code, s.name, s.course, s.join_code,
            (SELECT COUNT(*) FROM enrollments e WHERE e.subject_id=s.id) AS student_count,
            sem.name AS semester_name, ay.name AS year_name
     FROM subjects s
     LEFT JOIN semesters sem ON sem.id=s.semester_id
     LEFT JOIN academic_years ay ON ay.id=sem.academic_year_id
     WHERE s.faculty_id=? AND s.is_archived=0
     ORDER BY s.created_at DESC"
);
$subjects->execute([$fid]);
$subjects = $subjects->fetchAll();

include 'header.php';
?>

<div class="mb-xl">
    <button type="button" onclick="history.back()" class="flex items-center gap-sm text-on-surface-variant mb-xs">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        <span class="font-label-md text-label-md">Back</span>
    </button>
</div>

<div class="relative overflow-hidden rounded-xl bg-primary mb-lg p-lg shadow-sm">
    <div class="absolute top-0 right-0 w-24 h-24 -mr-6 -mt-6 bg-white/10 rounded-full"></div>
    <div class="relative z-10 flex items-center gap-md">
        <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-white text-3xl" style="font-variation-settings:'FILL' 1;">badge</span>
        </div>
        <div>
            <h2 class="font-headline-md text-headline-md text-white"><?= htmlspecialchars($faculty['full_name']) ?></h2>
            <p class="text-white/70 text-sm"><?= htmlspecialchars($faculty['school_id']) ?></p>
        </div>
    </div>
</div>

<h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mb-md">Subjects (<?= count($subjects) ?>)</h3>
<div class="space-y-sm pb-24">
    <?php if (empty($subjects)): ?>
    <p class="text-center text-on-surface-variant py-8 text-sm">No subjects assigned.</p>
    <?php else: ?>
    <?php foreach ($subjects as $s): ?>
    <a href="subject_view.php?id=<?= $s['id'] ?>"
       class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow hover:bg-surface-container-low transition-colors block">
        <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-sm" style="font-variation-settings:'FILL' 1;">menu_book</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface"><?= htmlspecialchars($s['code']) ?> — <?= htmlspecialchars($s['name']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant">
                <?= htmlspecialchars($s['course']) ?> · <?= $s['student_count'] ?> students
                <?= $s['semester_name'] ? ' · ' . htmlspecialchars($s['semester_name']) : '' ?>
            </p>
        </div>
        <span class="material-symbols-outlined text-outline shrink-0">chevron_right</span>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
