<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('faculty');

$pdo = getDB();
$fid = currentUserId();
$sid = (int)($_GET['id'] ?? 0);
if (!$sid) { header('Location: subjects.php'); exit; }

$subject = $pdo->prepare("SELECT * FROM subjects WHERE id=? AND faculty_id=? AND is_archived=0");
$subject->execute([$sid, $fid]);
$subject = $subject->fetch();
if (!$subject) { header('Location: subjects.php'); exit; }

$tab = $_GET['tab'] ?? 'weeks';

$weeks = $pdo->prepare(
    "SELECT w.*, COUNT(a.id) AS activity_count,
            SUM(CASE WHEN a.is_locked=1 THEN 1 ELSE 0 END) AS locked_count
     FROM weeks w LEFT JOIN activities a ON a.week_id=w.id
     WHERE w.subject_id=? GROUP BY w.id ORDER BY w.week_number DESC"
);
$weeks->execute([$sid]);
$weeks = $weeks->fetchAll();

$students = $pdo->prepare(
    "SELECT u.id, u.full_name, u.school_id, u.year_level, e.joined_at,
            (SELECT COUNT(*) FROM submissions sub JOIN activities a ON a.id=sub.activity_id WHERE a.subject_id=? AND sub.student_id=u.id AND sub.status='submitted') AS pending_count,
            (SELECT COUNT(*) FROM submissions sub JOIN activities a ON a.id=sub.activity_id WHERE a.subject_id=? AND sub.student_id=u.id AND sub.status='graded') AS graded_count
     FROM enrollments e JOIN users u ON u.id=e.student_id
     WHERE e.subject_id=? ORDER BY u.full_name"
);
$students->execute([$sid, $sid, $sid]);
$students = $students->fetchAll();

include 'header.php';
?>

<div class="mb-md">
    <button type="button" onclick="history.back()" class="flex items-center gap-sm text-on-surface-variant mb-xs">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        <span class="font-label-md text-label-md">Back</span>
    </button>
</div>

<div class="relative overflow-hidden rounded-xl bg-primary mb-lg p-lg shadow-sm">
    <div class="absolute top-0 right-0 w-32 h-32 -mr-8 -mt-8 bg-white/10 rounded-full"></div>
    <div class="relative z-10">
        <h2 class="font-headline-md text-headline-md text-white mb-1"><?= htmlspecialchars($subject['code']) ?> <?= htmlspecialchars($subject['name']) ?></h2>
        <p class="text-white/70 font-label-md mb-3"><?= count($students) ?> students · <?= count($weeks) ?> weeks</p>
        <div class="flex items-center gap-3 flex-wrap">
            <span class="font-mono font-bold text-white bg-white/20 px-3 py-1.5 rounded-lg text-sm tracking-widest"><?= htmlspecialchars($subject['join_code']) ?></span>
            <button id="copyBtn" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($subject['join_code']) ?>');this.textContent='Copied!';setTimeout(()=>this.textContent='Copy Code',2000)"
                class="text-sm text-white bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg transition-colors">
                Copy Code
            </button>
        </div>
    </div>
</div>

<div class="flex gap-sm mb-lg">
    <a href="?id=<?= $sid ?>&tab=weeks"
       class="px-4 py-2 rounded-full text-xs font-medium border transition-colors <?= $tab==='weeks' ? 'bg-primary text-white border-primary' : 'border-outline-variant text-on-surface-variant' ?>">
        Weeks & Activities
    </a>
    <a href="?id=<?= $sid ?>&tab=students"
       class="px-4 py-2 rounded-full text-xs font-medium border transition-colors <?= $tab==='students' ? 'bg-primary text-white border-primary' : 'border-outline-variant text-on-surface-variant' ?>">
        Students (<?= count($students) ?>)
    </a>
</div>

<?php if ($tab === 'weeks'): ?>

<div class="flex justify-end mb-md">
    <a href="week_create.php?subject_id=<?= $sid ?>"
       class="flex items-center gap-1 text-sm text-primary font-medium hover:underline">
        <span class="material-symbols-outlined text-sm">add</span> Add Week
    </a>
</div>

<div class="space-y-lg pb-24">
    <?php if (empty($weeks)): ?>
    <div class="text-center py-12 text-on-surface-variant">
        <p class="text-sm mb-3">No weeks yet.</p>
        <a href="week_create.php?subject_id=<?= $sid ?>" class="text-sm text-primary hover:underline">Create first week</a>
    </div>
    <?php else: ?>
    <?php foreach ($weeks as $week): ?>
    <?php
    $activities = $pdo->prepare(
        "SELECT a.*,
                (SELECT COUNT(*) FROM submissions sub WHERE sub.activity_id=a.id AND sub.status='submitted') AS pending_count,
                (SELECT COUNT(*) FROM submissions sub WHERE sub.activity_id=a.id AND sub.status='graded') AS graded_count
         FROM activities a WHERE a.week_id=? ORDER BY a.created_at DESC"
    );
    $activities->execute([$week['id']]);
    $activities = $activities->fetchAll();
    $isPastDeadlineAll = true;
    ?>
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
        <div class="flex items-center justify-between px-lg py-md border-b border-outline-variant bg-surface-container-low">
            <div>
                <h3 class="font-title-lg text-title-lg text-on-surface">
                    Week <?= $week['week_number'] ?><?= $week['title'] ? ' — ' . htmlspecialchars($week['title']) : '' ?>
                </h3>
                <p class="font-label-md text-label-md text-on-surface-variant"><?= $week['activity_count'] ?> activit<?= $week['activity_count']!=1?'ies':'y' ?></p>
            </div>
            <a href="activity_create.php?week_id=<?= $week['id'] ?>&subject_id=<?= $sid ?>"
               class="flex items-center gap-1 text-xs text-primary font-medium hover:underline">
                <span class="material-symbols-outlined text-sm">add</span> Activity
            </a>
        </div>
        <div class="divide-y divide-outline-variant">
            <?php if (empty($activities)): ?>
            <p class="text-sm text-on-surface-variant text-center py-4">No activities. Add one above.</p>
            <?php else: ?>
            <?php foreach ($activities as $act):
                $isPastDeadline = $act['deadline'] && strtotime($act['deadline']) < time();
                $effectiveLock  = $act['is_locked'] || $isPastDeadline;
            ?>
            <div class="flex items-center gap-md p-md <?= $effectiveLock ? 'bg-red-50/40' : '' ?>">
                <div class="w-9 h-9 rounded-lg <?= $effectiveLock ? 'bg-error/10' : 'bg-primary/10' ?> flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-sm <?= $effectiveLock ? 'text-error' : 'text-primary' ?>">
                        <?= $effectiveLock ? 'lock' : 'assignment' ?>
                    </span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="font-label-lg text-label-lg text-on-surface"><?= htmlspecialchars($act['title']) ?></p>
                        <?php if ($act['is_locked']): ?>
                        <span class="px-2 py-0.5 rounded-full bg-error/10 text-error text-[10px] font-bold">Locked</span>
                        <?php elseif ($isPastDeadline): ?>
                        <span class="px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 text-[10px] font-bold">Past Due</span>
                        <?php endif; ?>
                    </div>
                    <p class="font-label-md text-label-md text-on-surface-variant text-xs">
                        <?= htmlspecialchars($act['category']) ?> · <?= formatScore($act['max_score']) ?> pts
                        <?= $act['deadline'] ? ' · Due ' . date('M d, Y g:i A', strtotime($act['deadline'])) : '' ?>
                    </p>
                    <p class="font-label-md text-label-md text-xs mt-0.5">
                        <?php if ($act['pending_count'] > 0): ?>
                        <span class="text-pending"><?= $act['pending_count'] ?> to grade</span>
                        <?php endif; ?>
                        <?php if ($act['graded_count'] > 0): ?>
                        <span class="text-primary ml-2"><?= $act['graded_count'] ?> graded</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex items-center gap-sm shrink-0">
                    <a href="submissions.php?activity_id=<?= $act['id'] ?>"
                       class="text-xs text-primary hover:underline">View</a>
                    <a href="activity_edit.php?id=<?= $act['id'] ?>"
                       class="text-on-surface-variant hover:text-primary transition-colors">
                        <span class="material-symbols-outlined text-sm">edit</span>
                    </a>
                    <form method="POST" action="toggle_lock.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="activity_id" value="<?= $act['id'] ?>"/>
                        <input type="hidden" name="subject_id" value="<?= $sid ?>"/>
                        <button type="submit" title="<?= $act['is_locked'] ? 'Unlock' : 'Lock' ?>"
                            class="<?= $act['is_locked'] ? 'text-error' : 'text-on-surface-variant hover:text-error' ?> transition-colors">
                            <span class="material-symbols-outlined text-sm"><?= $act['is_locked'] ? 'lock' : 'lock_open' ?></span>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php else: ?>

<div class="space-y-sm pb-24">
    <?php if (empty($students)): ?>
    <p class="text-center text-on-surface-variant py-8 text-sm">No students enrolled yet.</p>
    <?php else: ?>
    <?php foreach ($students as $st): ?>
    <a href="student_submissions.php?student_id=<?= $st['id'] ?>&subject_id=<?= $sid ?>"
       class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow hover:bg-surface-container-low transition-colors block">
        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-sm">person</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($st['full_name']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant"><?= htmlspecialchars($st['school_id']) ?><?= $st['year_level'] ? ' · ' . strtoupper($st['year_level']) : '' ?></p>
        </div>
        <div class="text-right shrink-0">
            <?php if ($st['pending_count'] > 0): ?>
            <p class="font-label-lg text-label-lg text-pending"><?= $st['pending_count'] ?> to grade</p>
            <?php endif; ?>
            <p class="font-label-md text-label-md text-on-surface-variant"><?= $st['graded_count'] ?> graded</p>
        </div>
        <span class="material-symbols-outlined text-outline shrink-0">chevron_right</span>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php endif; ?>

<button onclick="window.location.href='week_create.php?subject_id=<?= $sid ?>'"
    class="fixed bottom-20 right-4 sm:right-6 w-14 h-14 rounded-full bg-primary text-white shadow-lg flex items-center justify-center hover:scale-110 transition-transform z-50">
    <span class="material-symbols-outlined text-3xl">add</span>
</button>

</main>
<?php include 'footer.php'; ?>
