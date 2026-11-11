<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo = getDB();

$totalFaculty     = $pdo->query("SELECT COUNT(*) FROM users WHERE role='faculty' AND is_active=1")->fetchColumn();
$totalStudents    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND is_active=1")->fetchColumn();
$totalCoord       = $pdo->query("SELECT COUNT(*) FROM users WHERE role='coordinator' AND is_active=1")->fetchColumn();
$totalSubjects    = $pdo->query("SELECT COUNT(*) FROM subjects WHERE is_active=1 AND is_archived=0")->fetchColumn();

$activeSemester   = $pdo->query(
    "SELECT s.name, ay.name AS year_name FROM semesters s
     JOIN academic_years ay ON ay.id = s.academic_year_id
     WHERE s.is_active=1 LIMIT 1"
)->fetch();

$recentUsers = $pdo->query(
    "SELECT u.full_name, u.role, d.code AS dept_code, u.created_at
     FROM users u LEFT JOIN departments d ON d.id = u.department_id
     WHERE u.role != 'admin' ORDER BY u.created_at DESC LIMIT 5"
)->fetchAll();

include 'header.php';
?>

<?php if ($activeSemester): ?>
<div class="mb-lg px-4 py-3 rounded-xl bg-primary text-white flex items-center justify-between">
    <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">event</span>
        <span class="text-sm font-medium">Active: <?= htmlspecialchars($activeSemester['year_name']) ?> — <?= htmlspecialchars($activeSemester['name']) ?></span>
    </div>
    <a href="academic.php" class="text-xs text-white/80 hover:text-white underline">Manage</a>
</div>
<?php else: ?>
<div class="mb-lg px-4 py-3 rounded-xl bg-orange-100 border border-orange-200 text-orange-800 flex items-center justify-between">
    <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">warning</span>
        <span class="text-sm font-medium">No active semester set</span>
    </div>
    <a href="academic.php" class="text-xs text-orange-700 hover:underline font-medium">Set Now</a>
</div>
<?php endif; ?>

<h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mb-md">Overview</h3>

<section class="grid grid-cols-2 sm:grid-cols-4 gap-sm sm:gap-md mb-8">
    <?php foreach ([
        ['icon'=>'badge',           'color'=>'text-primary',  'count'=>$totalCoord,    'label'=>'Coordinators'],
        ['icon'=>'school',          'color'=>'text-primary',  'count'=>$totalFaculty,  'label'=>'Faculty'],
        ['icon'=>'person',          'color'=>'text-primary',  'count'=>$totalStudents, 'label'=>'Students'],
        ['icon'=>'menu_book',       'color'=>'text-primary',  'count'=>$totalSubjects, 'label'=>'Subjects'],
    ] as $card): ?>
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md flex flex-col items-center justify-center text-center shadow-sm">
        <span class="material-symbols-outlined <?= $card['color'] ?> text-3xl mb-xs"><?= $card['icon'] ?></span>
        <div class="text-[28px] leading-none font-bold text-on-surface"><?= $card['count'] ?></div>
        <div class="font-label-md text-label-md text-on-surface-variant mt-xs"><?= $card['label'] ?></div>
    </div>
    <?php endforeach; ?>
</section>

<div class="flex justify-between items-center mb-md">
    <h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider">Recently Registered</h3>
    <a href="users.php" class="text-xs text-primary hover:underline">View All</a>
</div>
<div class="space-y-sm pb-24">
    <?php if (empty($recentUsers)): ?>
    <p class="text-center text-on-surface-variant py-8 text-sm">No users yet.</p>
    <?php else: ?>
    <?php foreach ($recentUsers as $u): ?>
    <div class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow">
        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-sm">person</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($u['full_name']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant">
                <?= ucfirst($u['role']) ?>
                <?= $u['dept_code'] ? ' · ' . htmlspecialchars($u['dept_code']) : '' ?>
            </p>
        </div>
        <span class="font-label-md text-label-md text-outline shrink-0"><?= date('M d', strtotime($u['created_at'])) ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
