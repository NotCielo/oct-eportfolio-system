<?php
function appBasePath(): string {
    static $basePath;

    if ($basePath !== null) {
        return $basePath;
    }

    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptPath)), '/');

    foreach (['admin', 'coordinator', 'dept_head', 'faculty', 'student'] as $roleDirectory) {
        $suffix = '/' . $roleDirectory;
        if (substr($basePath, -strlen($suffix)) === $suffix) {
            $basePath = substr($basePath, 0, -strlen($suffix));
            break;
        }
    }

    return $basePath === '/' ? '' : $basePath;
}

function appUrl(string $path = ''): string {
    return appBasePath() . '/' . ltrim($path, '/');
}
