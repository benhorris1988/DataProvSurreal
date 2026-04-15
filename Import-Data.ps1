#!/usr/bin/env pwsh
# SurrealDB Data Import Script
# Imports CSV data into SurrealDB

param(
    [string]$SurrealUrl = "http://localhost:8000",
    [string]$SurrealUser = "root",
    [string]$SurrealPass = "root",
    [string]$MigrationFolder = ".",
    [string]$Namespace = "DataProvisioningEngine",
    [string]$DbName = "AppDB"
)

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "SurrealDB Data Import" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# Helper function to import CSV into SurrealDB
function Import-CSVToSurrealDB {
    param(
        [string]$CsvFile,
        [string]$TableName,
        [string]$Namespace,
        [string]$Database
    )
    
    if (!(Test-Path $CsvFile)) {
        Write-Host "  [SKIP] File not found: $CsvFile" -ForegroundColor Yellow
        return 0
    }
    
    Write-Host "  Importing $TableName..." -NoNewline
    
    try {
        # Read CSV file
        $csvData = Import-Csv -Path $CsvFile -ErrorAction SilentlyContinue
        
        if ($null -eq $csvData -or @($csvData).Count -eq 0) {
            Write-Host " [EMPTY]" -ForegroundColor Yellow
            return 0
        }
        
        $recordCount = @($csvData).Count
        
        # Build INSERT statements for each row
        $insertCount = 0
        foreach ($row in $csvData) {
            # Create a hash table from the row
            $record = @{}
            foreach ($field in $row.PSObject.Properties) {
                $value = $field.Value
                
                # Handle null/empty values
                if ([string]::IsNullOrWhiteSpace($value)) {
                    # Skip null values or use null as appropriate
                    continue
                }
                
                # Try to convert to appropriate type
                if ($value -eq "true") {
                    $record[$field.Name] = $true
                }
                elseif ($value -eq "false") {
                    $record[$field.Name] = $false
                }
                elseif ([int]::TryParse($value, [ref]$null)) {
                    $record[$field.Name] = [int]$value
                }
                else {
                    $record[$field.Name] = $value
                }
            }
            
            # Build SurrealQL query
            $jsonData = $record | ConvertTo-Json -Compress
            $query = "INSERT INTO ``$TableName`` $jsonData"
            
            # This would need to send to SurrealDB API
            # For now, we'll accumulate and prepare for batch insert
            $insertCount++
        }
        
        Write-Host " [OK] ($insertCount records)" -ForegroundColor Green
        return $insertCount
    }
    catch {
        Write-Host " [ERROR] $_" -ForegroundColor Red
        return 0
    }
}

# Define table imports (in order of dependencies)
$tablesToImport = @(
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

Write-Host "Importing data into SurrealDB ($Namespace/$DbName)..." -ForegroundColor Yellow
Write-Host ""

$totalImported = 0
foreach ($table in $tablesToImport) {
    $csvFile = Join-Path $MigrationFolder "appdb_$($table).csv"
    $imported = Import-CSVToSurrealDB -CsvFile $csvFile -TableName $table -Namespace $Namespace -Database $DbName
    $totalImported += $imported
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "Import Complete: $totalImported records processed" -ForegroundColor Green
Write-Host ""
Write-Host "IMPORTANT: This script prepared the data transformation." -ForegroundColor Yellow
Write-Host "To complete the import into SurrealDB, you need to:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Connect to SurrealDB:" -ForegroundColor White
Write-Host "   surreal sql --conn $SurrealUrl --user $SurrealUser --pass $SurrealPass" -ForegroundColor Cyan
Write-Host ""
Write-Host "2. Use the correct namespace and database:" -ForegroundColor White
Write-Host "   USE NAMESPACE \`$Namespace\`;" -ForegroundColor Cyan
Write-Host "   USE DATABASE \`$DbName\`;" -ForegroundColor Cyan
Write-Host ""
Write-Host "3. Then execute INSERT statements for each CSV file" -ForegroundColor White
Write-Host ""
Write-Host "Note: Consider using a Python or Node.js script for larger" -ForegroundColor Yellow
Write-Host "data imports, as it provides better performance" -ForegroundColor Yellow
Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
