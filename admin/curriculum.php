<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo     = getDB();
$error   = '';
$success = '';

$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$courseOptions = ['BEED','BSIT','BSBA','BSA','BSPsych','BSCrim','BSN','THM','ABM','STEM','HUMSS','TVL'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_subject') {
        $dept_id  = (int)($_POST['dept_id']      ?? 0);
        $code     = strtoupper(trim($_POST['subject_code'] ?? ''));
        $name     = trim($_POST['subject_name']  ?? '');
        $course   = $_POST['course']             ?? '';
        $units    = (int)($_POST['units']        ?? 3);

        if (!$dept_id || !$code || !$name || !$course) {
            $error = 'All fields are required.';
        } elseif (!in_array($course, $courseOptions, true)) {
            $error = 'Invalid course selected.';
        } else {
            $pdo->prepare(
                "INSERT INTO curriculum (department_id, subject_code, subject_name, course, units) VALUES (?,?,?,?,?)"
            )->execute([$dept_id, $code, $name, $course, $units]);
            $success = "Subject {$code} added to curriculum.";
        }

    } elseif ($action === 'delete_subject') {
        $cid = (int)($_POST['curriculum_id'] ?? 0);
        if ($cid) {
            $pdo->prepare("DELETE FROM curriculum WHERE id=?")->execute([$cid]);
            $success = 'Subject removed from curriculum.';
        }

    } elseif ($action === 'add_dept') {
        $dname  = trim($_POST['dept_name'] ?? '');
        $dcourse= $_POST['dept_course']    ?? '';
        $dcode  = strtoupper(trim($_POST['dept_code'] ?? ''));

        if (!$dname || !$dcode || !$dcourse) {
            $error = 'Department name, code, and course are required.';
        } elseif (!in_array($dcourse, $courseOptions, true)) {
            $error = 'Invalid course selected.';
        } else {
            $check = $pdo->prepare("SELECT id FROM departments WHERE code=?");
            $check->execute([$dcode]);
            if ($check->fetch()) {
                $error = 'Department code already exists.';
            } else {
                $pdo->prepare("INSERT INTO departments (name, code) VALUES (?,?)")->execute([$dname, $dcode]);
                $success = "Department {$dcode} added.";
            }
        }
    }

    $departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();
}

$filterDept     = (int)($_GET['dept_id'] ?? 0);
$curriculumQuery= "SELECT c.*, d.name AS dept_name FROM curriculum c JOIN departments d ON d.id=c.department_id WHERE 1=1";
$curriculumParams = [];
if ($filterDept) {
    $curriculumQuery .= " AND c.department_id=?";
    $curriculumParams[] = $filterDept;
}
$curriculumQuery .= " ORDER BY c.course, c.subject_code";
$stmt = $pdo->prepare($curriculumQuery);
$stmt->execute($curriculumParams);
$curriculum = $stmt->fetchAll();

include 'header.php';
?>

<div class="mb-lg">
    <h2 class="font-headline-md text-headline-md text-on-surface">Curriculum Management</h2>
    <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Add subjects to the curriculum and manage departments.</p>
</div>

<?php if ($success): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-center gap-2">
    <span class="material-symbols-outlined text-sm">check_circle</span><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
    <span class="material-symbols-outlined text-sm">error</span><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-lg mb-lg">

    <!-- Add Subject -->
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
        <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Add Subject to Curriculum</h3>
        <form method="POST" class="space-y-md">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_subject"/>

            <select name="dept_id"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary appearance-none" required>
                <option value="">Select Department</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['code']) ?> — <?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="subject_code" placeholder="Subject Code (e.g. IT211)" maxlength="20"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary transition-all" required/>

            <input type="text" name="subject_name" placeholder="Subject Name" maxlength="120"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary transition-all" required/>

            <select name="course"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary appearance-none" required>
                <option value="">Select Course / Program</option>
                <?php foreach ($courseOptions as $c): ?>
                <option value="<?= $c ?>"><?= $c ?></option>
                <?php endforeach; ?>
            </select>

            <div class="flex items-center gap-md">
                <label class="font-label-md text-label-md text-on-surface-variant shrink-0">Units:</label>
                <input type="number" name="units" value="3" min="1" max="6"
                    class="w-24 border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary transition-all"/>
            </div>

            <button type="submit"
                class="w-full h-10 rounded-full bg-primary text-white font-label-lg hover:bg-green-800 transition-colors">
                Add to Curriculum
            </button>
        </form>
    </div>

    <!-- Add Department -->
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
        <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Add Department</h3>
        <form method="POST" class="space-y-md">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_dept"/>

            <input type="text" name="dept_name" placeholder="Department Name (e.g. College of IT)" maxlength="100"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary transition-all" required/>

            <input type="text" name="dept_code" placeholder="Code (e.g. CIT)" maxlength="20"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary transition-all" required/>

            <select name="dept_course"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary appearance-none" required>
                <option value="">Select Primary Course</option>
                <?php foreach ($courseOptions as $c): ?>
                <option value="<?= $c ?>"><?= $c ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit"
                class="w-full h-10 rounded-full bg-primary text-white font-label-lg hover:bg-green-800 transition-colors">
                Add Department
            </button>
        </form>

        <h4 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mt-lg mb-sm">Departments</h4>
        <div class="space-y-xs max-h-48 overflow-y-auto">
            <?php foreach ($departments as $d): ?>
            <div class="flex items-center p-sm rounded-lg bg-surface-container-low border border-outline-variant">
                <span class="text-sm text-on-surface"><?= htmlspecialchars($d['code']) ?> — <?= htmlspecialchars($d['name']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Curriculum list -->
<div class="flex items-center justify-between mb-md flex-wrap gap-sm">
    <h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider">Curriculum Subjects</h3>
    <form method="GET" class="flex gap-sm">
        <select name="dept_id" onchange="this.form.submit()"
            class="h-9 px-md rounded-lg border border-outline-variant bg-white text-sm focus:outline-none appearance-none">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $filterDept == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['code']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="space-y-sm pb-24">
    <?php if (empty($curriculum)): ?>
    <p class="text-center text-on-surface-variant py-8 text-sm">No curriculum subjects yet.</p>
    <?php else: ?>
    <?php foreach ($curriculum as $c): ?>
    <div class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow">
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface"><?= htmlspecialchars($c['subject_code']) ?> — <?= htmlspecialchars($c['subject_name']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant"><?= htmlspecialchars($c['course']) ?> · <?= $c['units'] ?> units · <?= htmlspecialchars($c['dept_name']) ?></p>
        </div>
        <form method="POST" onsubmit="return confirm('Remove this subject from the curriculum?')">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_subject"/>
            <input type="hidden" name="curriculum_id" value="<?= $c['id'] ?>"/>
            <button type="submit" class="text-error hover:text-red-700 transition-colors">
                <span class="material-symbols-outlined text-sm">delete</span>
            </button>
        </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
