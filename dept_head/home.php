<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('dept_head');

$pdo    = getDB();
$uid    = currentUserId();
$deptId = currentDeptId();

$totalFaculty  = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='faculty' AND department_id=? AND is_active=1");
$totalFaculty->execute([$deptId]); $totalFaculty = $totalFaculty->fetchColumn();

$totalStudents = $pdo->prepare(
    "SELECT COUNT(DISTINCT e.student_id) FROM enrollments e
     JOIN subjects s ON s.id=e.subject_id
     JOIN users u ON u.id=s.faculty_id
     WHERE u.department_id=? AND s.is_archived=0"
);
$totalStudents->execute([$deptId]); $totalStudents = $totalStudents->fetchColumn();

$totalSubjects = $pdo->prepare(
    "SELECT COUNT(DISTINCT s.id) FROM subjects s
     JOIN users u ON u.id=s.faculty_id
     WHERE u.department_id=? AND s.is_archived=0"
);
$totalSubjects->execute([$deptId]); $totalSubjects = $totalSubjects->fetchColumn();

$pendingGrade = $pdo->prepare(
    "SELECT COUNT(*) FROM submissions sub
     JOIN activities a ON a.id=sub.activity_id
     JOIN subjects s ON s.id=a.subject_id
     JOIN users u ON u.id=s.faculty_id
     WHERE u.department_id=? AND sub.status='submitted'"
);
$pendingGrade->execute([$deptId]); $pendingGrade = $pendingGrade->fetchColumn();

$activeSemester = $pdo->query("SELECT s.name, ay.name AS year_name FROM semesters s JOIN academic_years ay ON ay.id=s.academic_year_id WHERE s.is_active=1 LIMIT 1")->fetch();

$dept = $pdo->prepare("SELECT name FROM departments WHERE id=?");
$dept->execute([$deptId]); $dept = $dept->fetch();

include 'header.php';
?>

<?php if ($activeSemester): ?>
<div class="mb-lg px-4 py-3 rounded-xl bg-primary text-white flex items-center gap-2">
    <span class="material-symbols-outlined text-sm">event</span>
    <span class="text-sm font-medium"><?= htmlspecialchars($activeSemester['year_name']) ?> — <?= htmlspecialchars($activeSemester['name']) ?></span>
</div>
<?php endif; ?>

<div class="mb-lg">
    <h2 class="font-headline-md text-headline-md text-on-surface"><?= htmlspecialchars($dept['name'] ?? 'Department') ?></h2>
    <p class="text-sm text-on-surface-variant">Department Head — read-only view</p>
</div>

<section class="grid grid-cols-2 sm:grid-cols-4 gap-sm sm:gap-md mb-8">
    <?php foreach ([
        ['icon'=>'badge',       'count'=>$totalFaculty,  'label'=>'Faculty'],
        ['icon'=>'group',       'count'=>$totalStudents, 'label'=>'Students'],
        ['icon'=>'menu_book',   'count'=>$totalSubjects, 'label'=>'Subjects'],
        ['icon'=>'pending',     'count'=>$pendingGrade,  'label'=>'Pending Grade', 'color'=>'text-pending'],
    ] as $c): ?>
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md flex flex-col items-center text-center shadow-sm">
        <span class="material-symbols-outlined <?= $c['color'] ?? 'text-primary' ?> text-2xl mb-xs"><?= $c['icon'] ?></span>
        <div class="text-[26px] font-bold text-on-surface leading-none"><?= $c['count'] ?></div>
        <div class="font-label-md text-label-md text-on-surface-variant mt-xs"><?= $c['label'] ?></div>
    </div>
    <?php endforeach; ?>
</section>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-md pb-24">
    <a href="faculty.php"
       class="flex items-center gap-md p-lg bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary">badge</span>
        </div>
        <div>
            <p class="font-label-lg text-label-lg text-on-surface">View Faculty</p>
            <p class="font-label-md text-label-md text-on-surface-variant">See all faculty and their subjects</p>
        </div>
        <span class="material-symbols-outlined text-outline ml-auto">chevron_right</span>
    </a>
    <a href="students.php"
       class="flex items-center gap-md p-lg bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary">group</span>
        </div>
        <div>
            <p class="font-label-lg text-label-lg text-on-surface">View Students</p>
            <p class="font-label-md text-label-md text-on-surface-variant">See all student submissions</p>
        </div>
        <span class="material-symbols-outlined text-outline ml-auto">chevron_right</span>
    </a>
</div>

</main>
<?php include 'footer.php'; ?>
