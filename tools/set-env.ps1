# ---------------------------------------------------------------------------
# Portable development toolchain for the Secure Hotel Management System.
#
# The tools below were extracted into a per-user folder so that NO administrator
# rights are required. They are NOT installed system-wide.
#
#   PHP 8.4.26      C:\Users\CHARMIES\tools\php84
#   Composer 2.10   C:\Users\CHARMIES\tools\composer.phar
#   Git 2.56        C:\Users\CHARMIES\tools\git
#   Flutter 3.47.7  C:\Users\CHARMIES\tools\flutter
#
# Usage (run from the repository root):
#     . .\tools\set-env.ps1
# then in the same PowerShell window:
#     php -v
#     composer --version
# ---------------------------------------------------------------------------

$script:ToolsRoot = "C:\Users\CHARMIES\tools"

function global:php        { & "$script:ToolsRoot\php84\php.exe" @args }
function global:composer   { & "$script:ToolsRoot\php84\php.exe" "$script:ToolsRoot\composer.phar" @args }
function global:git        { & "$script:ToolsRoot\git\cmd\git.exe" @args }
function global:flutter    { & "$script:ToolsRoot\flutter\bin\flutter.bat" @args }
function global:dart       { & "$script:ToolsRoot\flutter\bin\dart.bat" @args }

# Make the tools visible to child processes too (php artisan serve, etc.)
$env:PATH = "$script:ToolsRoot\php84;$script:ToolsRoot\git\cmd;$script:ToolsRoot\flutter\bin;$env:PATH"

Write-Host "Portable toolchain ready." -ForegroundColor Green
Write-Host "  php      -> $script:ToolsRoot\php84\php.exe"
Write-Host "  composer -> $script:ToolsRoot\composer.phar"
Write-Host "  git      -> $script:ToolsRoot\git\cmd\git.exe"
Write-Host "  flutter  -> $script:ToolsRoot\flutter\bin\flutter.bat"
