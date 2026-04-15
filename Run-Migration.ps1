#!/usr/bin/env pwsh
# Complete SurrealDB Migration Orchestration Script

param(
    [string]$SqlServer = "localhost",
    [string]$SurrealUrl = "http://localhost:8000",
    [string]$SurrealUser = "root",
    [string]$SurrealPass = "root",
    [string]$MigrationFolder = "."
)

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "SurrealDB Migration - SQL Server to SurrealDB" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# Ensure migration folder exists
if (!(Test-Path $MigrationFolder)) {
    New-Item -ItemType Directory -Path $MigrationFolder | Out-Null
}

# Test SurrealDB Connection
Write-Host "Testing SurrealDB connection..." -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "$SurrealUrl/health" -Method Get -TimeoutSec 5
    Write-Host "[OK] SurrealDB is running and accessible" -ForegroundColor Green
}
catch {
    Write-Host "[ERROR] Failed to connect to SurrealDB at $SurrealUrl" -ForegroundColor Red
    Write-Host "Make sure SurrealDB is running: surreal start" -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "Setting up SurrealDB Namespace and Databases..." -ForegroundColor Yellow

$setupSqlFile = Join-Path $MigrationFolder "04_complete_schema_setup.sql"
if (!(Test-Path $setupSqlFile)) {
    Write-Host "[ERROR] Setup SQL file not found: $setupSqlFile" -ForegroundColor Red
    exit 1
}

Write-Host "[OK] Setup SQL file found" -ForegroundColor Green
Write-Host ""

# Export data from SQL Server
Write-Host "Exporting data from SQL Server..." -ForegroundColor Yellow
$tables = @(
    "users",
    "virtual_groups",
    "datasets",
    "columns",
    "asset_policy_groups",
    "asset_policy_columns",
    "asset_policy_conditions",
    "virtual_group_members",
    "access_requests",
    "initial_admins"
)

$exportCount = 0
foreach ($table in $tables) {
    Write-Host "  Exporting $table..." -NoNewline
    
    $csvFile = Join-Path $MigrationFolder "appdb_$($table).csv"
    $sqlQuery = "SELECT * FROM [$table]"
    
    try {
        sqlcmd -S $SqlServer -E -d datamarketplace -Q $sqlQuery -o $csvFile -s "," 2>$null
        
        if (Test-Path $csvFile) {
            $lineCount = @(Get-Content $csvFile -ErrorAction SilentlyContinue).Count
            Write-Host " OK ($lineCount lines)" -ForegroundColor Green
            $exportCount++
        }
        else {
            Write-Host " SKIP (table empty)" -ForegroundColor Yellow
        }
    }
    catch {
        Write-Host " ERROR ($_)" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "Exported $exportCount tables successfully" -ForegroundColor Green
Write-Host ""

# Summary
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "Migration Preparation Complete!" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Set up SurrealDB namespace and databases:" -ForegroundColor White
Write-Host "   surreal sql --conn $SurrealUrl --user $SurrealUser --pass $SurrealPass" -ForegroundColor Cyan
Write-Host "   (Then paste contents of: $setupSqlFile)" -ForegroundColor Cyan
Write-Host ""
Write-Host "2. Review exported CSV files:" -ForegroundColor White
Write-Host "   $MigrationFolder\appdb_*.csv" -ForegroundColor Cyan
Write-Host ""
Write-Host "3. Import data into SurrealDB" -ForegroundColor White
Write-Host ""
Write-Host "4. Update application configuration in appsettings.json" -ForegroundColor White
Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
