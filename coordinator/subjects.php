<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('coordinator');

$pdo = getDB();
$uid = currentUserId();
$error = ''; $success = '';

$activeSemester = $pdo->query("SELECT id, name FROM semesters WHERE is_active=1 LIMIT 1")->fetch();
$curriculum     = $pdo->query("SELECT c.*, d.name AS dept_name FROM curriculum c JOIN departments d ON d.id=c.department_id ORDER BY c.course, c.subject_code")->fetchAll();
$faculty        = $pdo->query("SELECT id, full_name, school_id FROM users WHERE role='faculty' AND is_active=1 ORDER BY full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $curr_id   = (int)($_POST['curriculum_id'] ?? 0);
        $faculty_id= (int)($_POST['faculty_id']    ?? 0);
        $sem_id    = $activeSemester['id'] ?? null;

        if (!$curr_id || !$faculty_id || !$sem_id) {
            $error = 'Please select a curriculum subject, faculty, and ensure an active semester exists.';
        } else {
            $curr = $pdo->prepare("SELECT * FROM curriculum WHERE id=?");
            $curr->execute([$curr_id]);
            $curr = $curr->fetch();
            if (!$curr) { $error = 'Invalid curriculum subject.'; }
            else {
                do {
                    $jc = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
                    $ck = $pdo->prepare("SELECT id FROM subjects WHERE join_code=?");
                    $ck->execute([$jc]);
                } while ($ck->fetch());

                $pdo->prepare(
                    "INSERT INTO subjects (curriculum_id, semester_id, faculty_id, coordinator_id, code, name, course, join_code)
                     VALUES (?,?,?,?,?,?,?,?)"
                )->execute([$curr_id, $sem_id, $faculty_id, $uid, $curr['subject_code'], $curr['subject_name'], $curr['course'], $jc]);
                $success = "Subject {$curr['subject_code']} created with join code {$jc}.";
            }
        }

    } elseif ($action === 'archive') {
        $sid = (int)($_POST['subject_id'] ?? 0);
        if ($sid) {
            $pdo->prepare("UPDATE subjects SET is_archived=1, is_active=0 WHERE id=? AND coordinator_id=?")->execute([$sid, $uid]);
            $success = 'Subject archived.';
        }

    } elseif ($action === 'restore') {
        $sid = (int)($_POST['subject_id'] ?? 0);
        if ($sid) {
            $pdo->prepare("UPDATE subjects SET is_archived=0, is_active=1 WHERE id=? AND coordinator_id=?")->execute([$sid, $uid]);
            $success = 'Subject restored.';
        }

    } elseif ($action === 'reassign') {
        $sid        = (int)($_POST['subject_id']  ?? 0);
        $faculty_id = (int)($_POST['faculty_id2'] ?? 0);
        if ($sid && $faculty_id) {
            $pdo->prepare("UPDATE subjects SET faculty_id=? WHERE id=? AND coordinator_id=?")->execute([$faculty_id, $sid, $uid]);
            $success = 'Faculty reassigned.';
        }
    }
}

$showArchived = isset($_GET['archived']);
$subjects = $pdo->prepare(
    "SELECT s.*, u.full_name AS faculty_name,
            (SELECT COUNT(*) FROM enrollments e WHERE e.subject_id=s.id) AS student_count,
            sem.name AS semester_name, ay.name AS year_name
     FROM subjects s
     JOIN users u ON u.id=s.faculty_id
     LEFT JOIN semesters sem ON sem.id=s.semester_id
     LEFT JOIN academic_years ay ON ay.id=sem.academic_year_id
     WHERE s.coordinator_id=? AND s.is_archived=?
     ORDER BY s.created_at DESC"
);
$subjects->execute([$uid, $showArchived ? 1 : 0]);
$subjects = $subjects->fetchAll();

include 'header.php';
?>

<div class="flex items-center justify-between mb-lg flex-wrap gap-sm">
    <div>
        <h2 class="font-headline-md text-headline-md text-on-surface">Subjects</h2>
        <p class="text-sm text-on-surface-variant">
            <?= $activeSemester ? 'Active: ' . htmlspecialchars($activeSemester['name']) : 'No active semester — contact admin.' ?>
        </p>
    </div>
    <div class="flex gap-sm">
        <a href="?<?= $showArchived ? '' : 'archived' ?>"
           class="text-xs px-3 py-1.5 rounded-full border border-outline-variant text-on-surface-variant hover:bg-surface-container-low transition-colors">
            <?= $showArchived ? 'Active Subjects' : 'Archived' ?>
        </a>
    </div>
</div>

<?php if ($success): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-center gap-2">
    <span class="material-symbols-outlined text-sm">check_circle</span><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$showArchived && $activeSemester): ?>
<div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm mb-lg">
    <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Create Subject from Curriculum</h3>
    <form method="POST" class="space-y-md">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create"/>
        <select name="curriculum_id" class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary appearance-none" required>
            <option value="">Select Subject from Curriculum</option>
            <?php foreach ($curriculum as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['subject_code']) ?> — <?= htmlspecialchars($c['subject_name']) ?> (<?= htmlspecialchars($c['course']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <select name="faculty_id" class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary appearance-none" required>
            <option value="">Assign Faculty</option>
            <?php foreach ($faculty as $f): ?>
            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['full_name']) ?> (<?= htmlspecialchars($f['school_id']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="w-full h-10 rounded-full bg-primary text-white font-label-lg hover:bg-green-800 transition-colors">
            Create Subject
        </button>
    </form>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-24">
    <?php if (empty($subjects)): ?>
    <div class="col-span-2 text-center py-16 text-on-surface-variant text-sm">
        <?= $showArchived ? 'No archived subjects.' : 'No subjects yet. Create one from the curriculum above.' ?>
    </div>
    <?php else: ?>
    <?php foreach ($subjects as $s): ?>
    <div class="bg-surface rounded-xl border border-outline-variant overflow-hidden hover:shadow-md transition-shadow">
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
            <p class="font-label-md text-label-md text-on-surface-variant mb-xs"><?= htmlspecialchars($s['faculty_name']) ?></p>
            <p class="text-xs text-outline mb-md">
                <?= htmlspecialchars($s['course']) ?> · <?= $s['student_count'] ?> students
                <?= $s['semester_name'] ? ' · ' . htmlspecialchars($s['semester_name']) : '' ?>
            </p>

            <?php if (!$showArchived): ?>
            <div class="flex items-center gap-2 mb-sm">
                <span class="font-mono font-bold text-xs text-primary bg-primary/5 px-2 py-0.5 rounded"><?= htmlspecialchars($s['join_code']) ?></span>
                <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($s['join_code']) ?>');this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',2000)"
                    class="text-xs text-outline hover:text-primary transition-colors">Copy</button>
            </div>
            <?php endif; ?>

            <div class="flex gap-sm flex-wrap">
                <?php if (!$showArchived): ?>
                <a href="subject_detail.php?id=<?= $s['id'] ?>"
                   class="flex-1 text-center text-xs px-3 py-2 border border-outline rounded-lg hover:bg-surface-container-low transition-colors text-on-surface-variant">
                    Open
                </a>
                <form method="POST" onsubmit="return confirm('Archive this subject?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="archive"/>
                    <input type="hidden" name="subject_id" value="<?= $s['id'] ?>"/>
                    <button type="submit" class="text-xs px-3 py-2 border border-error/30 rounded-lg hover:bg-red-50 transition-colors text-error">
                        Archive
                    </button>
                </form>
                <?php else: ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="restore"/>
                    <input type="hidden" name="subject_id" value="<?= $s['id'] ?>"/>
                    <button type="submit" class="text-xs px-3 py-2 bg-primary text-white rounded-lg hover:bg-green-800 transition-colors">
                        Restore
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
