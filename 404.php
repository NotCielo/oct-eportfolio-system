<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Page Not Found — ePortfolio</title>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:'Lexend',sans-serif;background:#f6f7f8;}.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}</style>
</head>
<body class="min-h-screen flex items-center justify-center px-4">
  <div class="text-center max-w-md">
    <span class="material-symbols-outlined text-[80px] text-gray-300 block mb-4">search_off</span>
    <h1 class="text-2xl font-bold text-gray-800 mb-2">Page Not Found</h1>
    <p class="text-gray-500 text-sm mb-6">The page you are looking for doesn't exist or has been moved.</p>
    <button onclick="history.back()"
       class="inline-flex items-center gap-2 bg-[#007608] text-white px-6 py-2 rounded-full font-medium hover:bg-green-800 transition-colors">
        <span class="material-symbols-outlined text-sm">arrow_back</span>
        Go Back
    </button>
  </div>
</body>
</html>
