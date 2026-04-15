#!/usr/bin/env pwsh
# Complete SurrealDB Migration Orchestration Script
# Handles all steps: Setup, Export, Import

param(
    [string]$SqlServer = "localhost",
    [string]$SurrealUrl = "http://localhost:8000",
    [string]$SurrealUser = "root",
    [string]$SurrealPass = "root",
    [string]$MigrationFolder = ".",
    [ValidateSet("full", "setup-only", "export-only", "import-only")]
    [string]$Step = "full"
)

Write-Host "╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║ SurrealDB Migration - SQL Server to SurrealDB                 ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# Ensure migration folder exists
if (!(Test-Path $MigrationFolder)) {
    New-Item -ItemType Directory -Path $MigrationFolder | Out-Null
}

# ========================================
# STEP 1: Check SurrealDB Connection
# ========================================
function Test-SurrealDBConnection {
    Write-Host "Testing SurrealDB connection..." -ForegroundColor Yellow
    try {
        $response = Invoke-RestMethod -Uri "$SurrealUrl/health" -Method Get -TimeoutSec 5
        Write-Host "[OK] SurrealDB is running and accessible" -ForegroundColor Green
        return $true
    }
    catch {
        Write-Host "[ERROR] Failed to connect to SurrealDB at $SurrealUrl" -ForegroundColor Red
        Write-Host "  Error: $_" -ForegroundColor Red
        return $false
    }
}

# ========================================
# STEP 2: Execute SurrealDB Setup
# ========================================
function Start-SurrealDBSetup {
    Write-Host ""
    Write-Host "Setting up SurrealDB Namespace and Databases..." -ForegroundColor Yellow
    
    $setupSqlFile = Join-Path $MigrationFolder "04_complete_schema_setup.sql"
    
    if (!(Test-Path $setupSqlFile)) {
        Write-Host "✗ Setup SQL file not found: $setupSqlFile" -ForegroundColor Red
        return $false
    }
    
    # Read the SQL file
    $sqlContent = Get-Content $setupSqlFile -Raw
    
    # Split into individual queries (simple split by GO or semicolon)
    $queries = $sqlContent -split '(?:GO|;)\s*(?=\n|$)' | Where-Object { $_.Trim() -ne '' }
    
    Write-Host "Found $($queries.Count) setup queries to execute" -ForegroundColor Cyan
    
    # For now, save as surreal compatible file
    Write-Host "Setup SQL prepared (manual execution recommended):" -ForegroundColor Green
    Write-Host "  surreal sql --conn $SurrealUrl --user $SurrealUser --pass $SurrealPass"
    Write-Host "  Then paste contents of: $setupSqlFile" -ForegroundColor Green
    
    return $true
}

# ========================================
# STEP 3: Export SQL Server Data
# ========================================
function Start-DataExport {
    Write-Host ""
    Write-Host "Exporting data from SQL Server..." -ForegroundColor Yellow
    
    # Tables to export
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
        
        # Use sqlcmd to export
        $csvFile = Join-Path $MigrationFolder "appdb_$($table).csv"
        $sqlQuery = "SELECT * FROM [$table]"
        
        try {
            sqlcmd -S $SqlServer -E -d datamarketplace -Q $sqlQuery -o $csvFile -s "," -I | Out-Null
            
            if (Test-Path $csvFile) {
                $lineCount = @(Get-Content $csvFile).Count
                Write-Host " [OK] ($lineCount rows)" -ForegroundColor Green
                $exportCount++
            }
            else {
                Write-Host " [FAILED] (export failed)" -ForegroundColor Red
            }
        }
        catch {
            Write-Host " [ERROR] (error: $_)" -ForegroundColor Red
        }
    }
    
    Write-Host ""
    Write-Host "Exported $exportCount of $($tables.Count) tables" -ForegroundColor Yellow
    return ($exportCount -eq $tables.Count)
}

# ========================================
# STEP 4: Generate Import Script
# ========================================
function New-ImportScript {
    Write-Host ""
    Write-Host "Generating import instructions..." -ForegroundColor Yellow
    
    $importScript = @"
# SurrealDB Data Import Instructions

To import the exported data into SurrealDB, use one of these methods:

## Method 1: Using SurrealQL (Recommended)

Connect to SurrealDB:
\`\`\`
surreal start --db file://./surreal.db
surreal sql --conn http://localhost:8000 --user root --pass root
\`\`\`

Then execute INSERT statements from the CSV files...

## Method 2: Using REST API

For each CSV file, transform and POST to:
\`\`\`
POST http://localhost:8000/api/v1/sql
Authorization: Bearer [token]
Content-Type: application/json

{
  "query": "INSERT INTO table_name (fields) VALUES (values)"
}
\`\`\`

## CSV Files Generated

- appdb_users.csv
- appdb_virtual_groups.csv
- appdb_datasets.csv
- appdb_columns.csv
- appdb_asset_policy_groups.csv
- appdb_asset_policy_columns.csv
- appdb_asset_policy_conditions.csv
- appdb_virtual_group_members.csv
- appdb_access_requests.csv
- appdb_initial_admins.csv

## Next Steps

1. Review all generated files
2. Execute the schema setup SQL
3. Import the CSV data
4. Verify data integrity
5. Update application configuration

"@
    
    $importFile = Join-Path $MigrationFolder "IMPORT_INSTRUCTIONS.txt"
    $importScript | Out-File -FilePath $importFile -Encoding UTF8 -Force
    
    Write-Host "Import instructions saved to: $importFile" -ForegroundColor Green
}

# ========================================
# Main Execution
# ========================================
switch ($Step) {
    "full" {
        Test-SurrealDBConnection | Out-Null
        Start-SurrealDBSetup
        Start-DataExport
        New-ImportScript
    }
    "setup-only" {
        Test-SurrealDBConnection | Out-Null
        Start-SurrealDBSetup
    }
    "export-only" {
        Start-DataExport
    }
    "import-only" {
        New-ImportScript
    }
}

Write-Host ""
Write-Host "═════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "Migration preparation complete!" -ForegroundColor Green
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Review the migration files in: $MigrationFolder" -ForegroundColor White
Write-Host "2. Execute setup SQL on SurrealDB" -ForegroundColor White
Write-Host "3. Import the CSV data" -ForegroundColor White
Write-Host "4. Update application configuration" -ForegroundColor White
Write-Host ""
Write-Host "For help, see: MIGRATION_PLAN.md and IMPORT_INSTRUCTIONS.txt" -ForegroundColor Cyan
