<?php
require_once __DIR__ . '/includes/path.php';

session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: ' . appUrl('login.php')); exit;
}
match ($_SESSION['role'] ?? '') {
    'admin'       => header('Location: ' . appUrl('admin/home.php')),
    'coordinator' => header('Location: ' . appUrl('coordinator/home.php')),
    'dept_head'   => header('Location: ' . appUrl('dept_head/home.php')),
    'faculty'     => header('Location: ' . appUrl('faculty/home.php')),
    'student'     => header('Location: ' . appUrl('student/home.php')),
    default       => header('Location: ' . appUrl('login.php')),
};
exit;
