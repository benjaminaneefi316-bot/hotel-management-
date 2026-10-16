# ---------------------------------------------------------------------------
# Start the portable MariaDB server used by the Secure Hotel Management System.
#
#   Server   : C:\Users\CHARMIES\tools\mariadb\bin\mariadbd.exe  (MariaDB 11.4)
#   Config   : C:\Users\CHARMIES\tools\my.ini                    (port 3307)
#   Data dir : C:\Users\CHARMIES\tools\mariadb-data
#
# Usage (from the repository root):
#     .\tools\start-db.ps1
#
# The server is started hidden in the background and the script waits until the
# port is actually accepting connections before it returns.
# ---------------------------------------------------------------------------

[CmdletBinding()]
param(
    [string]$ToolsRoot = 'C:\Users\CHARMIES\tools'
)

$ErrorActionPreference = 'Stop'

$mysqld = Join-Path $ToolsRoot 'mariadb\bin\mariadbd.exe'
$myIni  = Join-Path $ToolsRoot 'my.ini'
$dataDir = Join-Path $ToolsRoot 'mariadb-data'
$port   = 3307

if (-not (Test-Path $mysqld)) {
    throw "MariaDB server not found at '$mysqld'. See docs/INSTALLATION.md for the one-time setup."
}
if (-not (Test-Path $myIni)) {
    throw "MariaDB config not found at '$myIni'."
}
if (-not (Test-Path $dataDir)) {
    throw "MariaDB data directory '$dataDir' does not exist. Initialise it once with: & '$ToolsRoot\mariadb\bin\mariadb-install-db.exe' --defaults-file='$myIni'"
}

function Test-DbPort {
    [bool](Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue)
}

if (Test-DbPort) {
    Write-Host "MariaDB is already listening on 127.0.0.1:$port." -ForegroundColor Yellow
    exit 0
}

Write-Host "Starting MariaDB on 127.0.0.1:$port ..." -ForegroundColor Cyan
Start-Process -FilePath $mysqld -ArgumentList "--defaults-file=`"$myIni`"" -WindowStyle Hidden

$deadline = (Get-Date).AddSeconds(30)
while ((Get-Date) -lt $deadline) {
    if (Test-DbPort) {
        Write-Host "MariaDB is up (data: $dataDir)." -ForegroundColor Green
        exit 0
    }
    Start-Sleep -Milliseconds 500
}

throw "MariaDB did not start within 30 seconds. Check '$dataDir\error.log'."
