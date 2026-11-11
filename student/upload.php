<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');

$pdo = getDB();
$uid = currentUserId();
$aid = (int)($_GET['activity_id'] ?? $_POST['activity_id'] ?? 0);
if (!$aid) { header('Location: subjects.php'); exit; }

$activity = $pdo->prepare(
    "SELECT a.*, s.id AS subject_id, s.code, s.name AS subject_name
     FROM activities a
     JOIN subjects s ON s.id=a.subject_id
     JOIN enrollments e ON e.subject_id=s.id AND e.student_id=?
     WHERE a.id=?"
);
$activity->execute([$uid, $aid]);
$activity = $activity->fetch();
if (!$activity) { header('Location: subjects.php'); exit; }

$isPastDeadline = $activity['deadline'] && strtotime($activity['deadline']) < time();
if ($activity['is_locked'] || $isPastDeadline) {
    header("Location: subject.php?id={$activity['subject_id']}"); exit;
}

$submission = $pdo->prepare("SELECT * FROM submissions WHERE activity_id=? AND student_id=?");
$submission->execute([$aid, $uid]);
$submission = $submission->fetch();

if ($submission && $submission['status'] === 'graded') {
    header("Location: submission.php?id={$submission['id']}"); exit;
}

$MAX_ATTEMPTS   = 3;
$currentAttempt = $submission ? (int)$submission['attempt_number'] : 0;
if ($currentAttempt >= $MAX_ATTEMPTS && $submission && $submission['status'] === 'submitted') {
    header("Location: subject.php?id={$activity['subject_id']}"); exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmed'])) {
    verifyCsrf();

    $links = array_filter(array_map('trim', $_POST['links'] ?? []), fn($l) => $l !== '');
    $files = $_FILES['files'] ?? null;
    $hasFiles = $files && !empty($files['name'][0]) && $files['name'][0] !== '';
    $hasLinks = !empty($links);

    if (!$hasFiles && !$hasLinks) {
        $error = 'Please upload at least one file or add a link.';
    } else {
        $nextAttempt = $currentAttempt + 1;

        if (!$submission) {
            $pdo->prepare(
                "INSERT INTO submissions (activity_id, student_id, attempt_number, status, submitted_at) VALUES (?,?,?,'submitted',NOW())"
            )->execute([$aid, $uid, $nextAttempt]);
            $subId = (int)$pdo->lastInsertId();
        } else {
            $subId = $submission['id'];
            $pdo->prepare(
                "UPDATE submissions SET attempt_number=?, status='submitted', submitted_at=NOW(), overall_score=NULL, feedback=NULL, graded_at=NULL WHERE id=?"
            )->execute([$nextAttempt, $subId]);
            $pdo->prepare("DELETE FROM files WHERE submission_id=?")->execute([$subId]);
        }

        $pdo->prepare(
            "INSERT INTO resubmission_log (activity_id, student_id, attempt_number) VALUES (?,?,?)"
        )->execute([$aid, $uid, $nextAttempt]);

        $safeCode = preg_replace('/[^A-Z0-9]/', '_', strtoupper($activity['code']));
        $dir      = __DIR__ . '/../uploads/' . $safeCode . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        if ($hasFiles) {
            $allowedMimes = ['image/jpeg','image/png'];
            $allowedExts  = ['jpg','jpeg','png'];
            $maxKB        = ['image/jpeg'=>3072,'image/png'=>3072];

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $origName = basename($files['name'][$i]);
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $tmpPath  = $files['tmp_name'][$i];
                $sizeKB   = (int)ceil($files['size'][$i] / 1024);
                $mime     = mime_content_type($tmpPath);

                if (!in_array($ext, $allowedExts) || !in_array($mime, $allowedMimes)) continue;
                if ($sizeKB > ($maxKB[$mime] ?? 3072)) continue;
                if (!getimagesize($tmpPath)) continue;

                $stored = bin2hex(random_bytes(16)) . '.' . $ext;
                if (move_uploaded_file($tmpPath, $dir . $stored)) {
                    $pdo->prepare(
                        "INSERT INTO files (submission_id, original_name, stored_name, file_path, file_type, file_size_kb)
                         VALUES (?,?,?,?,?,?)"
                    )->execute([$subId, $origName, $stored, $safeCode . '/' . $stored, $ext, $sizeKB]);
                }
            }
        }

        foreach ($links as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
            $pdo->prepare(
                "INSERT INTO files (submission_id, original_name, stored_name, file_path, file_type, file_url, is_link, file_size_kb)
                 VALUES (?,?,?,?,'link',?,1,0)"
            )->execute([$subId, $url, '', '', $url]);
        }

        header("Location: submission.php?id={$subId}"); exit;
    }
}

include 'header.php';
?>

<!-- Confirmation dialog -->
<div id="confirmModal" class="hidden fixed inset-0 z-[200] flex items-center justify-center px-4">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
    <div class="relative bg-surface rounded-2xl shadow-xl border border-outline-variant w-full max-w-sm p-lg">
        <div class="flex items-center gap-md mb-md">
            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-primary">upload_file</span>
            </div>
            <div>
                <h3 class="font-title-lg text-title-lg text-on-surface">Submit Activity?</h3>
                <p class="font-label-md text-label-md text-on-surface-variant">This will count as attempt <span id="attemptNum"></span> of <?= $MAX_ATTEMPTS ?>.</p>
            </div>
        </div>
        <p class="font-body-md text-body-md text-on-surface-variant mb-lg">Make sure your files are complete before submitting.</p>
        <div class="flex gap-sm justify-end">
            <button type="button" onclick="document.getElementById('confirmModal').classList.add('hidden')"
                class="px-lg h-10 rounded-full border border-outline-variant text-on-surface-variant font-label-lg hover:bg-surface-container-low transition-all">
                Cancel
            </button>
            <button type="button" onclick="document.getElementById('mainForm').submit()"
                class="px-lg h-10 rounded-full bg-primary text-white font-label-lg hover:bg-green-800 transition-all flex items-center gap-sm">
                <span class="material-symbols-outlined text-sm">send</span> Confirm Submit
            </button>
        </div>
    </div>
</div>

<div class="mb-md">
    <button type="button" onclick="history.back()" class="flex items-center gap-sm text-on-surface-variant mb-xs">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        <span class="font-label-md text-label-md">Back</span>
    </button>
</div>

<div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm mb-lg">
    <span class="text-xs text-on-surface-variant"><?= htmlspecialchars($activity['code']) ?> — <?= htmlspecialchars($activity['subject_name']) ?></span>
    <h2 class="font-headline-md text-headline-md text-on-surface mt-1"><?= htmlspecialchars($activity['title']) ?></h2>
    <p class="font-label-md text-label-md text-on-surface-variant mt-1">
        <?= htmlspecialchars($activity['category']) ?> · Max: <?= formatScore($activity['max_score']) ?> pts
        <?= $activity['deadline'] ? ' · Due: ' . date('M d, Y g:i A', strtotime($activity['deadline'])) : '' ?>
    </p>
    <?php if ($currentAttempt > 0): ?>
    <div class="mt-sm px-3 py-1.5 rounded-lg bg-yellow-50 border border-yellow-200 inline-flex items-center gap-1 text-xs text-yellow-800">
        <span class="material-symbols-outlined text-sm">history</span>
        Attempt <?= $currentAttempt + 1 ?> of <?= $MAX_ATTEMPTS ?> (resubmission)
    </div>
    <?php endif; ?>
</div>

<?php if ($error): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-start gap-2">
    <span class="material-symbols-outlined text-sm mt-0.5 shrink-0">error</span>
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<form id="mainForm" method="POST" enctype="multipart/form-data" class="space-y-lg mb-24">
    <?= csrfField() ?>
    <input type="hidden" name="activity_id" value="<?= $aid ?>"/>
    <input type="hidden" name="confirmed" value="1"/>

    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
        <h3 class="font-title-lg text-title-lg text-on-surface mb-xs">Upload Images</h3>
        <p class="text-xs text-on-surface-variant mb-md">JPEG or PNG only · max 3 MB each</p>

        <div id="dropZone"
            class="border-2 border-dashed border-outline-variant rounded-xl p-6 text-center cursor-pointer hover:border-primary hover:bg-surface-container-low transition-all"
            onclick="document.getElementById('fileInput').click()">
            <span class="material-symbols-outlined text-outline text-4xl block mb-2">image</span>
            <p class="font-label-lg text-label-lg text-on-surface-variant">Tap to select images</p>
            <p class="text-xs text-outline mt-1 hidden sm:block">or drag and drop here</p>
        </div>
        <input type="file" id="fileInput" name="files[]" multiple accept=".jpg,.jpeg,.png"
            class="hidden" onchange="showSelected(this)"/>
        <div id="selectedFiles" class="mt-md space-y-sm hidden"></div>
    </div>

    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
        <h3 class="font-title-lg text-title-lg text-on-surface mb-xs">Add Links</h3>
        <p class="text-xs text-on-surface-variant mb-md">Google Drive, Canva, GitHub, etc.</p>
        <div id="linkContainer" class="space-y-sm">
            <div class="flex items-center gap-sm">
                <input type="url" name="links[]" placeholder="https://drive.google.com/..."
                    class="flex-1 border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all"/>
                <button type="button" onclick="addLink()"
                    class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center hover:bg-primary/20 transition-colors shrink-0">
                    <span class="material-symbols-outlined text-sm">add</span>
                </button>
            </div>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-sm justify-end">
        <button type="button" onclick="history.back()"
            class="h-12 px-xl rounded-full border border-outline-variant text-on-surface-variant font-label-lg hover:bg-surface-container-low transition-all text-center">
            Cancel
        </button>
        <button type="button" onclick="showConfirm()"
            class="h-12 px-xl rounded-full bg-primary text-on-primary font-label-lg hover:shadow-lg active:scale-95 transition-all flex items-center justify-center gap-sm">
            <span class="material-symbols-outlined text-[20px]">upload</span> Submit
        </button>
    </div>
</form>

</main>
<?php include 'footer.php'; ?>

<script>
const nextAttempt = <?= $currentAttempt + 1 ?>;

function showConfirm() {
    document.getElementById('attemptNum').textContent = nextAttempt;
    document.getElementById('confirmModal').classList.remove('hidden');
}

function addLink() {
    const c = document.getElementById('linkContainer');
    const d = document.createElement('div');
    d.className = 'flex items-center gap-sm';
    d.innerHTML = `<input type="url" name="links[]" placeholder="https://..." class="flex-1 border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary"/>
        <button type="button" onclick="this.parentElement.remove()" class="w-9 h-9 rounded-full bg-red-100 text-error flex items-center justify-center hover:bg-red-200 transition-colors shrink-0">
            <span class="material-symbols-outlined text-sm">remove</span>
        </button>`;
    c.appendChild(d);
}

function showSelected(input) {
    const c = document.getElementById('selectedFiles');
    c.innerHTML = '';
    if (!input.files.length) { c.classList.add('hidden'); return; }
    c.classList.remove('hidden');
    Array.from(input.files).forEach(file => {
        const kb  = Math.ceil(file.size / 1024);
        const ok  = kb <= 3072 && ['image/jpeg','image/png'].includes(file.type);
        const d   = document.createElement('div');
        d.className = 'flex items-center gap-sm p-sm rounded-lg border ' + (ok ? 'border-outline-variant bg-surface-container-low' : 'border-red-200 bg-red-50');
        d.innerHTML = `<span class="material-symbols-outlined text-sm text-primary">image</span>
            <span class="flex-1 text-xs text-on-surface truncate">${file.name}</span>
            <span class="text-xs text-outline shrink-0">${kb} KB</span>
            ${!ok ? '<span class="text-xs text-error font-medium shrink-0">Invalid or too large</span>' : ''}`;
        c.appendChild(d);
    });
}

const dz = document.getElementById('dropZone');
if (dz) {
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('border-primary','bg-surface-container-low'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('border-primary','bg-surface-container-low'));
    dz.addEventListener('drop', e => {
        e.preventDefault(); dz.classList.remove('border-primary','bg-surface-container-low');
        const inp = document.getElementById('fileInput');
        inp.files = e.dataTransfer.files; showSelected(inp);
    });
}
</script>
