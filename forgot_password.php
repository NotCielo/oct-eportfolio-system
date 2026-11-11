<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
redirectIfLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Forgot Password — OCT E-Portfolio</title>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:'Lexend',sans-serif;background:#f6f7f8;}.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}</style>
</head>
<body class="min-h-screen flex flex-col">

<header class="bg-white sticky top-0 z-50 flex items-center justify-center w-full px-4 h-16 border-b border-gray-200 gap-3">
  <div class="w-10 h-10 rounded-full overflow-hidden border border-gray-200 shrink-0">
    <img src="IMG_3428.JPG" alt="oct" class="w-full h-full object-cover"/>
  </div>
  <h1 class="text-[#007608] font-black text-base sm:text-xl tracking-tight">Olivarez College Tagaytay E-Portfolio System 2.0</h1>
</header>

<div class="flex-1 flex items-center justify-center px-4 py-8">
  <div class="w-full max-w-[420px]">
    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-md">

      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center shrink-0">
          <span class="material-symbols-outlined text-orange-500">lock_reset</span>
        </div>
        <div>
          <h2 class="text-lg font-semibold text-gray-800">Forgot Password</h2>
          <p class="text-xs text-gray-500">Password reset is handled by your administrator.</p>
        </div>
      </div>

      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
        <p class="text-sm text-blue-800 font-medium mb-2">What to do:</p>
        <ol class="text-sm text-blue-700 space-y-1 list-decimal list-inside">
          <li>Contact your <strong>Program Coordinator</strong> or <strong>Administrator</strong></li>
          <li>Provide your <strong>School ID</strong> and <strong>full name</strong></li>
          <li>They will reset your password and give you a temporary one</li>
          <li>Log in with the temporary password, then change it in your profile</li>
        </ol>
      </div>

      <div class="bg-surface-container-low border border-gray-200 rounded-lg p-3 mb-4">
        <p class="text-xs text-gray-500 mb-1">Contact Information</p>
        <p class="text-sm text-gray-700">📧 For assistance, visit your department office or contact your coordinator directly.</p>
      </div>

      <a href="login.php"
        class="flex items-center justify-center gap-2 w-full border border-gray-300 text-gray-600 py-2 rounded-lg font-medium hover:bg-gray-50 transition-colors text-sm">
        <span class="material-symbols-outlined text-sm">arrow_back</span>
        Back to Login
      </a>
    </div>
  </div>
</div>
</body>
</html>
