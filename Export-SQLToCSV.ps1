#!/usr/bin/env pwsh
# Data Migration Script - SQL Server to SurrealDB (Corrected Version)
# Extracts data from SQL Server and prepares it for SurrealDB import

param(
    [string]$SqlServer = "localhost",
    [string]$SqlDatabase = "datamarketplace",
    [string]$OutputFolder = ".",
    [string]$Namespace = "DataProvisioningEngine",
    [string]$DatabaseName = "AppDB"
)

# Create output folder
if (!(Test-Path $OutputFolder)) {
    New-Item -ItemType Directory -Path $OutputFolder | Out-Null
}

# Import SQL Server command-line utilities
write-Host "Starting SQL Server to SurrealDB Migration" -ForegroundColor Yellow
Write-Host "Source Database: $SqlServer/$SqlDatabase" -ForegroundColor Cyan
Write-Host "Output Folder: $OutputFolder" -ForegroundColor Cyan
Write-Host ""

# Function to extract table data to CSV
function Export-TableToCSV {
    param(
        [string]$TableName,
        [string]$Query,
        [string]$OutputPath
    )
    
    Write-Host "Exporting $TableName..." -ForegroundColor Cyan
    
    try {
        # Use sqlcmd to export to CSV
        sqlcmd -S $SqlServer -E -d $SqlDatabase -Q "$Query" -o "$OutputPath" -s ","
        
        # Check if file was created and has content
        if (Test-Path $OutputPath) {
            $fileSize = (Get-Item $OutputPath).Length
            $lineCount = @(Get-Content $OutputPath).Count
            Write-Host "  ✓ Exported to $OutputPath ($lineCount rows)" -ForegroundColor Green
            return $true
        }
        else {
            Write-Host "  ✗ Failed to export $TableName" -ForegroundColor Red
            return $false
        }
    }
    catch {
        Write-Host "  ✗ Error exporting $TableName: $_" -ForegroundColor Red
        return $false
    }
}

# Alternative: Use SMO (SQL Server Management Objects) for better handling
function Export-TableUsingSMO {
    param(
        [string]$TableName,
        [string]$OutputPath
    )
    
    Write-Host "Exporting $TableName (using SMO)..." -ForegroundColor Cyan
    
    try {
        # Load SQL Server SMO assembly
        [Reflection.Assembly]::LoadWithPartialName("Microsoft.SqlServer.Smo") | Out-Null
        [Reflection.Assembly]::LoadWithPartialName("Microsoft.SqlServer.ConnectionInfo") | Out-Null
        
        # Connect to SQL Server
        $serverConnection  = New-Object Microsoft.SqlServer.Management.Common.ServerConnection -ArgumentList $SqlServer
        $serverConnection.LoginSecure = $true
        $server = New-Object Microsoft.SqlServer.Management.Smo.Server $serverConnection
        
        # Get table and get all rows
        $db = $server.Databases[$SqlDatabase]
        $table = $db.Tables[$TableName]
        
        if ($null -eq $table) {
            Write-Host "  ✗ Table not found: $TableName" -ForegroundColor Red
            return $false
        }
        
        # Get all columns
        $columns = @()
        foreach ($column in $table.Columns) {
            $columns += $column.Name
        }
        
        # Execute query to get all rows
        $query = "SELECT * FROM [$TableName]"
        $dataset = $server.ConnectionContext.ExecuteWithResults($query)
        
        # Export to CSV
        $csvData = @()
        $csvData += ($columns -join ",")
        
        foreach ($row in $dataset.Tables[0].Rows) {
            $values = @()
            foreach ($col in $columns) {
                $value = $row[$col]
                # Escape CSV values
                if ($null -eq $value) {
                    $values += ""
                }
                else {
                    $strValue = $value.ToString()
                    if ($strValue -match '[",\n]') {
                        $values += """$($strValue -replace '"', '""')"""
                    }
                    else {
                        $values += $strValue
                    }
                }
            }
            $csvData += ($values -join ",")
        }
        
        # Write to file
        $csvData | Out-File -FilePath $OutputPath -Encoding UTF8 -Force
        Write-Host "  ✓ Exported $TableName to $OutputPath" -ForegroundColor Green
        return $true
    }
    catch {
        Write-Host "  ✗ Error: $_" -ForegroundColor Red
        return $false
    }
}

# Tables to export from datamarketplace (AppDB)
$appDbTables = @{
    "users" = "SELECT id, name, email, role, avatar, created_at FROM users ORDER BY id"
    "virtual_groups" = "SELECT id, name, owner_id, description, created_at FROM virtual_groups ORDER BY id"
    "datasets" = "SELECT id, name, type, description, owner_group_id, created_at FROM datasets ORDER BY id"
    "columns" = "SELECT id, dataset_id, name, data_type, definition, is_pii, sample_data FROM columns ORDER BY id"
    "asset_policy_groups" = "SELECT id, dataset_id, owner_id, name, description, created_at FROM asset_policy_groups ORDER BY id"
    "asset_policy_columns" = "SELECT id, policy_group_id, column_name, is_hidden FROM asset_policy_columns ORDER BY id"
    "asset_policy_conditions" = "SELECT id, policy_group_id, column_name, operator, value FROM asset_policy_conditions ORDER BY id"
    "virtual_group_members" = "SELECT group_id, user_id, added_at FROM virtual_group_members ORDER BY group_id, user_id"
    "access_requests" = "SELECT id, user_id, dataset_id, status, requested_rls_filters, justification, reviewed_by, reviewed_at, created_at, policy_group_id FROM access_requests ORDER BY id"
    "initial_admins" = "SELECT id, username, added_at FROM initial_admins ORDER BY id"
}

# Export AppDB tables
Write-Host "=== Exporting AppDB Tables ===" -ForegroundColor Yellow
foreach ($tableName in $appDbTables.Keys) {
    $outputPath = Join-Path $OutputFolder "appdb_$tableName.csv"
    $query = $appDbTables[$tableName]
    Export-TableUsingSMO -TableName $tableName -OutputPath $outputPath
}

Write-Host ""
Write-Host "Export complete!" -ForegroundColor Green
Write-Host "CSV files saved to: $OutputFolder" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Review the exported CSV files"
Write-Host "2. Set up SurrealDB namespace and databases"
Write-Host "3. Create schema in SurrealDB"
Write-Host "4. Import the CSV data into SurrealDB"
