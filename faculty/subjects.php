<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('faculty');

$pdo = getDB();
$fid = currentUserId();

$subjects = $pdo->prepare(
    "SELECT s.*, sem.name AS semester_name, ay.name AS year_name,
            (SELECT COUNT(*) FROM enrollments e WHERE e.subject_id=s.id) AS student_count,
            (SELECT COUNT(*) FROM weeks w WHERE w.subject_id=s.id) AS week_count
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

<div class="mb-lg">
    <h2 class="font-headline-md text-headline-md text-on-surface">My Subjects</h2>
    <p class="text-sm text-on-surface-variant">Tap a subject to manage weeks, activities, and students.</p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-24">
    <?php if (empty($subjects)): ?>
    <div class="col-span-2 text-center py-16 text-on-surface-variant text-sm">
        No subjects assigned yet. Contact your coordinator.
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
            <p class="text-xs text-outline mb-sm">
                <?= htmlspecialchars($s['course']) ?> · <?= $s['student_count'] ?> students · <?= $s['week_count'] ?> weeks
                <?= $s['semester_name'] ? ' · ' . htmlspecialchars($s['semester_name']) : '' ?>
            </p>
            <div class="flex items-center justify-between">
                <span class="font-mono font-bold text-xs text-primary bg-primary/5 px-2 py-0.5 rounded"><?= htmlspecialchars($s['join_code']) ?></span>
                <button onclick="event.preventDefault();navigator.clipboard.writeText('<?= htmlspecialchars($s['join_code']) ?>');this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',2000)"
                    class="text-xs text-outline hover:text-primary transition-colors">Copy</button>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
