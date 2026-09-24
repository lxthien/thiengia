# Local PHP 8.1 server with project-owned request/upload temporary storage.
param(
    [string]$PhpPath = 'C:\xampp81\php\php.exe',
    [int]$Port = 8000
)
$ErrorActionPreference = 'Stop'
$projectDir = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$requestTempDir = Join-Path $projectDir 'var\php-temp'
if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
    throw "PHP executable not found: $PhpPath"
}
New-Item -ItemType Directory -Path $requestTempDir -Force | Out-Null
Push-Location -LiteralPath $projectDir
try {
    & $PhpPath -d "sys_temp_dir=$requestTempDir" -d "upload_tmp_dir=$requestTempDir" -S "127.0.0.1:$Port" -t public public/router.php
} finally {
    Pop-Location
}
