#!/usr/bin/env pwsh
# Data Migration Script - SQL Server to SurrealDB
# Extracts data from SQL Server and migrates it to SurrealDB

param(
    [string]$SqlServer = "localhost",
    [string]$SqlDatabase = "datamarketplace",
    [string]$SurrealUrl = "http://localhost:8000",
    [string]$SurrealUser = "root",
    [string]$SurrealPass = "root"
)

# SQL Server connection
$connectionString = "Server=$SqlServer;Database=$SqlDatabase;Integrated Security=true;"

function Invoke-SqlQuery {
    param(
        [string]$Query,
        [string]$Database = $SqlDatabase
    )
    
    try {
        $connection = New-Object System.Data.SqlClient.SqlConnection
        $connection.ConnectionString = "Server=$SqlServer;Database=$Database;Integrated Security=true;"
        $connection.Open()
        
        $command = $connection.CreateCommand()
        $command.CommandText = $Query
        
        $adapter = New-Object System.Data.SqlClient.SqlDataAdapter $command
        $dataTable = New-Object System.Data.DataTable
        $adapter.Fill($dataTable)
        
        $connection.Close()
        
        return $dataTable
    }
    catch {
        Write-Error "Error executing query: $_"
        return $null
    }
}

function ConvertTo-SurrealJson {
    param(
        [System.Data.DataTable]$DataTable,
        [string]$TableName,
        [hashtable]$RelationshipMap
    )
    
    $records = @()
    
    foreach ($row in $DataTable.Rows) {
        $record = @{}
        
        foreach ($column in $DataTable.Columns) {
            $value = $row[$column.ColumnName]
            
            # Skip null values
            if ($value -eq [DBNull]::Value) {
                continue
            }
            
            # Check if this is a foreign key that needs to be converted to a record link
            if ($RelationshipMap.ContainsKey($column.ColumnName)) {
                $refTable = $RelationshipMap[$column.ColumnName]
                $record[$column.ColumnName] = @{
                    type = "record"
                    table = $refTable
                    id = $value
                    link = "$refTable/$value"
                }
            }
            else {
                $record[$column.ColumnName] = $value
            }
        }
        
        $records += $record
    }
    
    return $records
}

function Migrate-Table {
    param(
        [string]$TableName,
        [string]$Query,
        [hashtable]$RelationshipMap = @{}
    )
    
    Write-Host "Migrating table: $TableName..." -ForegroundColor Cyan
    
    $dataTable = Invoke-SqlQuery -Query $Query
    
    if ($null -eq $dataTable) {
        Write-Host "No data found for table: $TableName" -ForegroundColor Yellow
        return
    }
    
    $records = ConvertTo-SurrealJson -DataTable $dataTable -TableName $TableName -RelationshipMap $RelationshipMap
    
    Write-Host "  Found $($records.Count) records" -ForegroundColor Green
    
    # Save to JSON for inspection or batch import
    $jsonPath = "$PSScriptRoot\data_$TableName.json"
    $records | ConvertTo-Json | Out-File -FilePath $jsonPath -Encoding UTF8 -Force
    Write-Host "  Saved to: $jsonPath"
    
    return $records
}

# Define table migration queries
Write-Host "Starting SQL Server to SurrealDB Migration" -ForegroundColor Yellow
Write-Host "SQL Server: $SqlServer / $SqlDatabase" -ForegroundColor Cyan
Write-Host "SurrealDB: $SurrealUrl" -ForegroundColor Cyan
Write-Host ""

# Migrate Users
$userRecords = Migrate-Table -TableName "users" -Query "SELECT id, name, email, role, avatar, created_at FROM users"

# Migrate Reports
$reportRecords = Migrate-Table -TableName "reports" -Query "SELECT id, name, url, description FROM reports"

# Migrate Virtual Groups
$vgRecords = Migrate-Table -TableName "virtual_groups" `
    -Query "SELECT id, name, owner_id, description, created_at FROM virtual_groups" `
    -RelationshipMap @{"owner_id" = "users"}

# Migrate Datasets
$datasetRecords = Migrate-Table -TableName "datasets" `
    -Query "SELECT id, name, type, description, owner_group_id, created_at FROM datasets" `
    -RelationshipMap @{"owner_group_id" = "virtual_groups"}

# Migrate Columns
$columnRecords = Migrate-Table -TableName "columns" `
    -Query "SELECT id, dataset_id, name, data_type, definition, is_pii, sample_data FROM columns" `
    -RelationshipMap @{"dataset_id" = "datasets"}

# Migrate Asset Policy Groups
$apgRecords = Migrate-Table -TableName "asset_policy_groups" `
    -Query "SELECT id, dataset_id, owner_id, name, description, created_at FROM asset_policy_groups" `
    -RelationshipMap @{"dataset_id" = "datasets"; "owner_id" = "users"}

# Migrate Asset Policy Columns
$apcRecords = Migrate-Table -TableName "asset_policy_columns" `
    -Query "SELECT id, policy_group_id, column_name, is_hidden FROM asset_policy_columns" `
    -RelationshipMap @{"policy_group_id" = "asset_policy_groups"}

# Migrate Asset Policy Conditions
$apcoRecords = Migrate-Table -TableName "asset_policy_conditions" `
    -Query "SELECT id, policy_group_id, column_name, operator, value FROM asset_policy_conditions" `
    -RelationshipMap @{"policy_group_id" = "asset_policy_groups"}

# Migrate Virtual Group Members
$vgmRecords = Migrate-Table -TableName "virtual_group_members" `
    -Query "SELECT group_id, user_id, added_at FROM virtual_group_members" `
    -RelationshipMap @{"group_id" = "virtual_groups"; "user_id" = "users"}

# Migrate Report Datasets
$rdRecords = Migrate-Table -TableName "report_datasets" `
    -Query "SELECT dataset_id, report_id FROM report_datasets" `
    -RelationshipMap @{"dataset_id" = "datasets"; "report_id" = "reports"}

# Migrate Access Requests
$arRecords = Migrate-Table -TableName "access_requests" `
    -Query "SELECT id, user_id, dataset_id, status, requested_rls_filters, justification, reviewed_by, reviewed_at, created_at, policy_group_id FROM access_requests" `
    -RelationshipMap @{"user_id" = "users"; "dataset_id" = "datasets"; "reviewed_by" = "users"; "policy_group_id" = "asset_policy_groups"}

Write-Host ""
Write-Host "Data extraction complete! All data saved to JSON files." -ForegroundColor Green
Write-Host "Next step: Import these JSON files into SurrealDB" -ForegroundColor Cyan
