<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('coordinator');
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Coordinator — OCT E-Portfolio</title>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
tailwind.config={darkMode:"class",theme:{extend:{colors:{primary:"#007608",pending:"#e6811c","primary-container":"#007608","on-primary":"#ffffff","on-primary-container":"#94fb82","primary-fixed":"#00d30e","primary-fixed-dim":"#78dd68",secondary:"#007608","secondary-container":"#3691ff","secondary-fixed":"#d5e3ff","on-secondary":"#ffffff","on-secondary-container":"#002955","on-secondary-fixed":"#001b3c","on-secondary-fixed-variant":"#004689",surface:"#ffffff","surface-variant":"#f1f5f0","surface-container-low":"#f8faf5","surface-container-lowest":"#ffffff","surface-container":"#eaf0e2","surface-container-high":"#e4eadd","surface-bright":"#ffffff","on-surface":"#171d15","on-surface-variant":"#3f4a3b",outline:"#6f7a6a","outline-variant":"#e2e8f0",error:"#ba1a1a","error-container":"#ffdad6",background:"#ffffff",tertiary:"#823600","tertiary-fixed":"#ffdbca","on-tertiary-fixed":"#341100","on-tertiary-fixed-variant":"#783200"},fontFamily:{display:["Lexend"],body:["Lexend"]},fontSize:{"headline-md":["24px",{lineHeight:"32px",fontWeight:"600"}],"title-lg":["20px",{lineHeight:"28px",fontWeight:"500"}],"body-md":["16px",{lineHeight:"24px",fontWeight:"400"}],"label-lg":["14px",{lineHeight:"20px",fontWeight:"600"}],"label-md":["12px",{lineHeight:"16px",fontWeight:"500"}]},borderRadius:{DEFAULT:"0.25rem",lg:"0.5rem",xl:"0.75rem",full:"9999px"},spacing:{xs:"4px",sm:"8px",md:"16px",lg:"24px",xl:"32px"}}}};
</script>
<style>
body{font-family:'Lexend',sans-serif;-webkit-tap-highlight-color:transparent;min-height:100dvh;background-color:#f6f7f8;}
.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}
.bento-shadow{box-shadow:0 4px 12px -2px rgba(0,0,0,0.05);}
.card-border{border:1px solid #f1f5f0;}
</style>
</head>
<body class="font-body-md text-on-surface bg-background min-h-screen">

<header class="bg-surface sticky top-0 z-50 w-full px-4 h-16 border-b border-outline-variant flex items-center justify-between relative">
  <div class="absolute left-1/2 -translate-x-1/2 flex items-center gap-3 max-w-[calc(100%-2rem)]">
    <div class="w-10 h-10 rounded-full overflow-hidden border border-outline shrink-0">
      <img src="../IMG_3428.JPG" alt="oct" class="w-full h-full object-cover">
    </div>
    <h1 class="text-primary font-black text-xl tracking-tight hidden sm:block">Olivarez College Tagaytay E-Portfolio System 2.0</h1>
    <h1 class="text-primary font-black text-base tracking-tight sm:hidden">OCT E-Portfolio</h1>
  </div>
  <div class="absolute right-4 flex items-center gap-3">
    <span class="text-sm text-on-surface-variant hidden sm:block"><?= htmlspecialchars(currentName()) ?></span>
    <a href="../logout.php" class="flex items-center gap-1 text-sm text-error hover:underline">
      <span class="material-symbols-outlined text-sm">logout</span>
      <span class="hidden sm:block">Logout</span>
    </a>
  </div>
</header>

<main class="max-w-4xl mx-auto px-3 sm:px-4 py-4 sm:py-6 pb-28">

<nav class="fixed bottom-0 left-0 right-0 bg-surface/90 backdrop-blur-md border-t border-outline-variant z-50">
  <div class="max-w-4xl mx-auto flex justify-around items-center h-16">
    <?php
    $nav = [
      'home.php'     => ['icon'=>'home',      'label'=>'Home'],
      'subjects.php' => ['icon'=>'menu_book', 'label'=>'Subjects'],
      'users.php'    => ['icon'=>'group',     'label'=>'Users'],
      'profile.php'  => ['icon'=>'person',    'label'=>'Profile'],
    ];
    foreach ($nav as $page => $item):
      $active = $currentPage === $page;
    ?>
    <a href="<?= htmlspecialchars(appUrl('coordinator/' . $page)) ?>"
       class="flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition-colors <?= $active ? 'text-primary' : 'text-on-surface-variant hover:text-primary' ?>">
      <span class="material-symbols-outlined" style="<?= $active ? "font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24;" : '' ?>"><?= $item['icon'] ?></span>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>
  </div>
</nav>
