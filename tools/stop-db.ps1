# ---------------------------------------------------------------------------
# Stop the portable MariaDB server started by tools\start-db.ps1.
#
# It first tries a *clean* shutdown through mariadb-admin using the credentials
# in backend\.env. If that is not permitted it falls back to stopping the
# mariadbd process.
#
# Usage (from the repository root):
#     .\tools\stop-db.ps1
# ---------------------------------------------------------------------------

[CmdletBinding()]
param(
    [string]$ToolsRoot = 'C:\Users\CHARMIES\tools'
)

$ErrorActionPreference = 'Stop'

$process = Get-Process mariadbd -ErrorAction SilentlyContinue
if (-not $process) {
    Write-Host 'MariaDB is not running.' -ForegroundColor Yellow
    exit 0
}

$admin = Join-Path $ToolsRoot 'mariadb\bin\mariadb-admin.exe'
$envFile = Join-Path $PSScriptRoot '..\backend\.env'

$user = $null
$pass = $null
if (Test-Path $envFile) {
    foreach ($line in Get-Content $envFile) {
        if ($line -match '^\s*DB_USERNAME\s*=\s*"?(.*?)"?\s*$') { $user = $Matches[1] }
        if ($line -match '^\s*DB_PASSWORD\s*=\s*"?(.*?)"?\s*$') { $pass = $Matches[1] }
    }
}

if ($user -and (Test-Path $admin)) {
    Write-Host 'Requesting a clean shutdown ...' -ForegroundColor Cyan
    $args = @('-h', '127.0.0.1', '-P', '3307', '-u', $user)
    if ($pass) { $args += "-p$pass" }
    $args += 'shutdown'
    & $admin @args 2>$null
}

Start-Sleep -Seconds 1
$still = Get-Process mariadbd -ErrorAction SilentlyContinue
if (-not $still) {
    Write-Host 'MariaDB stopped.' -ForegroundColor Green
    exit 0
}

Write-Host 'Clean shutdown was not permitted; stopping the process.' -ForegroundColor Yellow
$still | Stop-Process -Force
Write-Host 'MariaDB process stopped.' -ForegroundColor Green
