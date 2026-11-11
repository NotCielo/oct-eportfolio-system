<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('faculty');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: subjects.php'); exit; }
verifyCsrf();

$pdo = getDB();
$fid = currentUserId();
$aid = (int)($_POST['activity_id'] ?? 0);
$sid = (int)($_POST['subject_id']  ?? 0);
if (!$aid || !$sid) { header('Location: subjects.php'); exit; }

$chk = $pdo->prepare("SELECT a.id FROM activities a JOIN subjects s ON s.id=a.subject_id WHERE a.id=? AND s.faculty_id=?");
$chk->execute([$aid, $fid]);
if (!$chk->fetch()) { header('Location: subjects.php'); exit; }

$pdo->prepare("UPDATE activities SET is_locked = NOT is_locked WHERE id=?")->execute([$aid]);
header("Location: subject.php?id={$sid}&tab=weeks");
exit;
