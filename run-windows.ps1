param(
    [string]$XamppPath = "C:\xampp",
    [string]$DbName = "tms",
    [string]$DbUser = "root",
    [string]$DbPassword = "",
    [int]$DbPort = 3306,
    [switch]$CopyToHtdocs,
    [switch]$NoBrowser
)

$ErrorActionPreference = "Stop"

function Write-Step($Message) {
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Write-Ok($Message) {
    Write-Host "[OK] $Message" -ForegroundColor Green
}

function Write-Warn($Message) {
    Write-Host "[WARN] $Message" -ForegroundColor Yellow
}

function Fail($Message) {
    Write-Host "[ERROR] $Message" -ForegroundColor Red
    Write-Host ""
    Write-Host "Common fixes:"
    Write-Host "- Install XAMPP in C:\xampp, or run: .\run-windows.ps1 -XamppPath D:\xampp"
    Write-Host "- Start this script from the project root, the folder that contains tms and SQL FIle."
    Write-Host "- If port 80 or the MySQL port is busy, stop IIS, Skype, another Apache, or another MySQL service."
    Write-Host "- If XAMPP MySQL uses another port, run: .\run-windows.ps1 -DbPort 3308"
    Write-Host "- In XAMPP Control Panel, make sure Apache and MySQL can start without red errors."
    exit 1
}

function Test-Port($Port) {
    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $task = $client.ConnectAsync("127.0.0.1", $Port)
        $connected = $task.Wait(800)
        $client.Close()
        return $connected
    } catch {
        return $false
    }
}

function Get-PortOwner($Port) {
    try {
        $connection = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction Stop | Select-Object -First 1
        if ($connection) {
            $process = Get-Process -Id $connection.OwningProcess -ErrorAction SilentlyContinue
            if ($process) {
                return "$($process.ProcessName) pid=$($process.Id)"
            }
            return "pid=$($connection.OwningProcess)"
        }
    } catch {
        return ""
    }
    return ""
}

function Wait-Port($Port, $Name, $Seconds = 25) {
    for ($i = 0; $i -lt $Seconds; $i++) {
        if (Test-Port $Port) {
            Write-Ok "$Name is listening on port $Port"
            return $true
        }
        Start-Sleep -Seconds 1
    }
    return $false
}

function Start-Bat($Path, $WorkingDirectory) {
    Start-Process -FilePath "cmd.exe" -ArgumentList @("/c", "`"$Path`"") -WorkingDirectory $WorkingDirectory -WindowStyle Minimized
}

function Get-MySqlArgs([string]$Database = "") {
    $args = @("-h127.0.0.1", "-P$DbPort", "-u$DbUser")
    if ($script:DbPassword -ne "") {
        $args += "-p$script:DbPassword"
    }
    if ($Database -ne "") {
        $args += $Database
    }
    return $args
}

function Invoke-MySql([string]$Sql, [string]$Database = "") {
    $args = Get-MySqlArgs $Database
    $args += @("-N", "-B", "-e", $Sql)
    $output = & $script:MysqlExe @args 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "MySQL command failed: $output"
    }
    return (($output | Out-String).Trim())
}

function Invoke-MySqlScript([string]$Path, [string]$Database) {
    $args = Get-MySqlArgs $Database
    Get-Content -Path $Path | & $script:MysqlExe @args
    if ($LASTEXITCODE -ne 0) {
        throw "Failed to import SQL file: $Path"
    }
}

function Test-MySqlLogin {
    try {
        Invoke-MySql "SELECT 1;" | Out-Null
        return $true
    } catch {
        return $false
    }
}

function Set-EnvValue([string]$Path, [string]$Key, [string]$Value) {
    if (Test-Path $Path) {
        $lines = Get-Content -Path $Path
    } else {
        $lines = @()
    }

    $pattern = "^" + [Regex]::Escape($Key) + "="
    $found = $false
    $updated = foreach ($line in $lines) {
        if ($line -match $pattern) {
            $found = $true
            "$Key=$Value"
        } else {
            $line
        }
    }

    if (-not $found) {
        $updated += "$Key=$Value"
    }

    Set-Content -Path $Path -Value $updated -Encoding UTF8
}

function Get-EnvValue([string]$Path, [string]$Key) {
    if (-not (Test-Path $Path)) {
        return ""
    }
    $line = Get-Content -Path $Path | Where-Object { $_ -match ("^" + [Regex]::Escape($Key) + "=") } | Select-Object -First 1
    if (-not $line) {
        return ""
    }
    return ($line -replace ("^" + [Regex]::Escape($Key) + "="), "")
}

function Ensure-Column([string]$ColumnName, [string]$Definition) {
    $exists = Invoke-MySql "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$DbName' AND table_name='tblbooking' AND column_name='$ColumnName';"
    if ([int]$exists -eq 0) {
        Invoke-MySql "ALTER TABLE tblbooking ADD COLUMN $Definition;" $DbName | Out-Null
        Write-Ok "Added tblbooking.$ColumnName"
    } else {
        Write-Ok "tblbooking.$ColumnName already exists"
    }
}

Write-Step "Checking Windows and project folder"
if (-not $IsWindows -and $env:OS -notmatch "Windows") {
    Write-Warn "This script is meant for Windows. Continue only if you are running inside Windows PowerShell."
}

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectRoot = (Resolve-Path $ScriptDir).Path
if (-not (Test-Path (Join-Path $ProjectRoot "tms\index.php"))) {
    Fail "Project root is wrong. I could not find tms\index.php in $ProjectRoot"
}
if (-not (Test-Path (Join-Path $ProjectRoot "SQL FIle\tms.sql"))) {
    Fail "SQL FIle\tms.sql is missing."
}
Write-Ok "Project found: $ProjectRoot"

Write-Step "Checking XAMPP"
if (-not (Test-Path $XamppPath)) {
    Fail "XAMPP path was not found: $XamppPath"
}

$script:PhpExe = Join-Path $XamppPath "php\php.exe"
$script:MysqlExe = Join-Path $XamppPath "mysql\bin\mysql.exe"
$ApacheStart = Join-Path $XamppPath "apache_start.bat"
$MysqlStart = Join-Path $XamppPath "mysql_start.bat"
$Htdocs = Join-Path $XamppPath "htdocs"

if (-not (Test-Path $PhpExe)) { Fail "PHP was not found at $PhpExe" }
if (-not (Test-Path $MysqlExe)) { Fail "MySQL client was not found at $MysqlExe" }
if (-not (Test-Path $ApacheStart)) { Fail "Apache start script was not found at $ApacheStart" }
if (-not (Test-Path $MysqlStart)) { Fail "MySQL start script was not found at $MysqlStart" }
if (-not (Test-Path $Htdocs)) { Fail "XAMPP htdocs folder was not found at $Htdocs" }

Write-Ok "XAMPP found: $XamppPath"
Write-Ok "PHP found: $PhpExe"
Write-Ok "MySQL client found: $MysqlExe"

Write-Step "Checking project location under htdocs"
$HtdocsFull = (Resolve-Path $Htdocs).Path.TrimEnd("\")
$ProjectFull = (Resolve-Path $ProjectRoot).Path.TrimEnd("\")
$isUnderHtdocs = $ProjectFull.StartsWith($HtdocsFull, [System.StringComparison]::OrdinalIgnoreCase)

if (-not $isUnderHtdocs) {
    $target = Join-Path $HtdocsFull (Split-Path -Leaf $ProjectFull)
    if (-not $CopyToHtdocs) {
        Write-Warn "Project is not inside XAMPP htdocs. Apache may not serve it from localhost."
        $answer = Read-Host "Copy project to $target now? Type Y to copy"
        if ($answer -notmatch "^[Yy]$") {
            Fail "Move/copy this folder into $HtdocsFull, then run the script again."
        }
    }

    Write-Step "Copying project to htdocs"
    if (-not (Test-Path $target)) {
        New-Item -ItemType Directory -Path $target | Out-Null
    }
    Copy-Item -Path (Join-Path $ProjectFull "*") -Destination $target -Recurse -Force
    $ProjectRoot = (Resolve-Path $target).Path
    $ProjectFull = $ProjectRoot.TrimEnd("\")
    Write-Ok "Project copied to: $ProjectRoot"
}

$relativePath = $ProjectFull.Substring($HtdocsFull.Length).TrimStart("\") -replace "\\", "/"
$urlPath = (($relativePath -split "/") | ForEach-Object { [Uri]::EscapeDataString($_) }) -join "/"
$PublicUrl = "http://localhost/$urlPath/tms"
$AdminUrl = "$PublicUrl/admin"

Write-Step "Checking PHP extensions"
$modules = & $PhpExe -m
if ($modules -notcontains "PDO") { Fail "PHP PDO extension is missing in XAMPP." }
if ($modules -notcontains "pdo_mysql") { Fail "PHP pdo_mysql extension is missing. Enable it in php.ini or reinstall XAMPP with MySQL support." }
if ($modules -notcontains "curl") { Write-Warn "PHP curl extension is missing. The site can run, but PhonePe payment calls will fail." } else { Write-Ok "PHP curl extension is enabled" }
Write-Ok "PHP PDO/MySQL extensions are enabled"

Write-Step "Starting Apache and MySQL if needed"
if (Test-Port 80) {
    $owner = Get-PortOwner 80
    Write-Ok "Port 80 is already listening" 
    if ($owner -and $owner -notmatch "httpd|apache") {
        Write-Warn "Port 80 owner looks like: $owner. If the app does not open, stop that program and start Apache."
    }
} else {
    Start-Bat $ApacheStart $XamppPath
    if (-not (Wait-Port 80 "Apache" 30)) {
        $owner = Get-PortOwner 80
        Fail "Apache did not start on port 80. Port owner: $owner"
    }
}

if (Test-Port $DbPort) {
    $owner = Get-PortOwner $DbPort
    Write-Ok "Port $DbPort is already listening"
    if ($owner -and $owner -notmatch "mysqld|mariadbd") {
        Write-Warn "Port $DbPort owner looks like: $owner. If database login fails, stop that program and start XAMPP MySQL."
    }
} else {
    Start-Bat $MysqlStart $XamppPath
    if (-not (Wait-Port $DbPort "MySQL" 30)) {
        $owner = Get-PortOwner $DbPort
        Fail "MySQL did not start on port $DbPort. Port owner: $owner"
    }
}

Write-Step "Checking MySQL login"
if (-not (Test-MySqlLogin)) {
    Write-Warn "MySQL login failed for user '$DbUser' with the current password."
    $DbPassword = Read-Host "Enter MySQL password for $DbUser, or press Enter if blank"
    if (-not (Test-MySqlLogin)) {
        Fail "Could not login to MySQL as $DbUser on 127.0.0.1:$DbPort."
    }
}
Write-Ok "MySQL login works"

Write-Step "Creating and importing database if needed"
Invoke-MySql "CREATE DATABASE IF NOT EXISTS $DbName DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;" | Out-Null
Write-Ok "Database exists: $DbName"

$packageTableCount = Invoke-MySql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DbName' AND table_name='tbltourpackages';"
if ([int]$packageTableCount -eq 0) {
    $sqlFile = Join-Path $ProjectRoot "SQL FIle\tms.sql"
    Write-Warn "Main tables are missing. Importing $sqlFile"
    Invoke-MySqlScript $sqlFile $DbName
    Write-Ok "Imported main database schema and demo data"
} else {
    Write-Ok "Main database tables already exist"
}

$paymentTableCount = Invoke-MySql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DbName' AND table_name='tblpayments';"
if ([int]$paymentTableCount -eq 0) {
    $paymentSql = Join-Path $ProjectRoot "SQL FIle\payment_updates.sql"
    if (Test-Path $paymentSql) {
        Invoke-MySqlScript $paymentSql $DbName
        Write-Ok "Created payment table"
    }
} else {
    Write-Ok "Payment table already exists"
}

Write-Step "Checking booking pricing columns"
Ensure-Column "Adults" "Adults int(11) NOT NULL DEFAULT 1 AFTER ToDate"
Ensure-Column "Children" "Children int(11) NOT NULL DEFAULT 0 AFTER Adults"
Ensure-Column "Rooms" "Rooms int(11) NOT NULL DEFAULT 1 AFTER Children"
Ensure-Column "TravelDays" "TravelDays int(11) NOT NULL DEFAULT 1 AFTER Rooms"
Ensure-Column "EstimatedAmount" "EstimatedAmount int(11) NOT NULL DEFAULT 0 AFTER TravelDays"
Ensure-Column "PriceBreakdown" "PriceBreakdown mediumtext DEFAULT NULL AFTER EstimatedAmount"

Write-Step "Writing .env for local Windows run"
$envPath = Join-Path $ProjectRoot ".env"
if (-not (Test-Path $envPath)) {
    $examplePath = Join-Path $ProjectRoot ".env.example"
    if (Test-Path $examplePath) {
        Copy-Item $examplePath $envPath
    } else {
        New-Item -ItemType File -Path $envPath | Out-Null
    }
}

Set-EnvValue $envPath "APP_DEBUG" "false"
Set-EnvValue $envPath "APP_BASE_URL" $PublicUrl
Set-EnvValue $envPath "DB_HOST" "127.0.0.1"
Set-EnvValue $envPath "DB_PORT" $DbPort
Set-EnvValue $envPath "DB_USER" $DbUser
Set-EnvValue $envPath "DB_PASS" $DbPassword
Set-EnvValue $envPath "DB_NAME" $DbName

$phonepeEnabled = (Get-EnvValue $envPath "PHONEPE_ENABLED").ToLower()
$phonepeClientId = Get-EnvValue $envPath "PHONEPE_CLIENT_ID"
$phonepeSecret = Get-EnvValue $envPath "PHONEPE_CLIENT_SECRET"
if ($phonepeEnabled -eq "true" -and ($phonepeClientId -eq "" -or $phonepeSecret -eq "" -or $phonepeClientId -match "your-" -or $phonepeSecret -match "your-")) {
    Write-Warn "PhonePe is enabled but credentials look missing. The app will keep PHONEPE_ENABLED=true, but payment may fail until credentials are added."
} elseif ($phonepeEnabled -eq "") {
    Set-EnvValue $envPath "PHONEPE_ENABLED" "false"
}
Write-Ok ".env updated: $envPath"

Write-Step "Running PHP application database smoke test"
$projectForPhp = $ProjectRoot.Replace("\", "/").Replace('"', '\"')
$phpCode = "require `"$projectForPhp/tms/includes/config.php`"; ensure_booking_pricing_columns(`$dbh); echo `"packages=`" . `$dbh->query(`"SELECT COUNT(*) FROM tbltourpackages`")->fetchColumn() . PHP_EOL;"
$phpOutput = & $PhpExe -r $phpCode 2>&1
if ($LASTEXITCODE -ne 0) {
    Fail "PHP database check failed: $phpOutput"
}
Write-Ok "PHP app DB check: $phpOutput"

Write-Step "Checking site response"
try {
    $response = Invoke-WebRequest -Uri "$PublicUrl/index.php" -UseBasicParsing -TimeoutSec 20
    if ($response.StatusCode -ne 200) {
        throw "HTTP status $($response.StatusCode)"
    }
    Write-Ok "Public site responded: $PublicUrl"
} catch {
    Write-Warn "The site did not respond from $PublicUrl"
    Write-Warn "Possible issues: project not under htdocs, Apache is not serving this folder, .htaccess/port conflict, or Apache failed silently."
    Fail "Site check failed: $_"
}

Write-Host ""
Write-Host "READY" -ForegroundColor Green
Write-Host "Public site: $PublicUrl"
Write-Host "Admin panel: $AdminUrl"
Write-Host ""
Write-Host "Default logins:"
Write-Host "Admin: admin / Test@123"
Write-Host "User : test@gmail.com / Test@123"
Write-Host ""

if (-not $NoBrowser) {
    Start-Process $PublicUrl
    Start-Process $AdminUrl
}
