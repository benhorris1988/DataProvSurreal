param(
    [string]$ConnectionString = "Server=localhost;Database=datawarehouse_DEV;User Id=dmp;Password=dmp1234;TrustServerCertificate=True",
    [string]$OutputFile = "c:\convert\data_provisioning\DataProvisioning.Net\Database\datawarehouse_dump.surql",
    [int]$MaxRowsPerTable = 1000
)

Write-Host "Connecting to SQL Server..."
$conn = New-Object System.Data.SqlClient.SqlConnection($ConnectionString)
$conn.Open()

# We will clear the file if it exists, or create a new one
$utf8NoBom = New-Object System.Text.UTF8Encoding($False)
[System.IO.File]::WriteAllText($OutputFile, "-- Data Warehouse to SurrealDB ETL Dump`n`n", $utf8NoBom)

$tablesCmd = $conn.CreateCommand()
$tablesCmd.CommandText = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"
$reader = $tablesCmd.ExecuteReader()
$tables = @()
while ($reader.Read()) {
    $tables += $reader["TABLE_NAME"]
}
$reader.Close()

Write-Host "Found $($tables.Count) tables. Starting ETL..."

# Loop through tables and extract
foreach ($tbl in $tables) {
    Write-Host "Extracting $tbl..."

    $dataCmd = $conn.CreateCommand()
    # TOP limit for safe dev migration
    $dataCmd.CommandText = "SELECT TOP $MaxRowsPerTable * FROM [$tbl]"
    
    $dReader = $dataCmd.ExecuteReader()
    $schema = $dReader.GetSchemaTable()
    
    $cols = @()
    foreach ($col in $schema) {
        $cols += @{
            Name = $col.ColumnName
            Type = $col.DataType.Name
        }
    }

    $rowCount = 0
    while ($dReader.Read()) {
        $rowCount++
        $surqlLine = "CREATE $tbl SET "
        $values = @()
        
        for ($i = 0; $i -lt $cols.Count; $i++) {
            $val = $dReader.GetValue($i)
            $colName = $cols[$i].Name
            
            if ([DBNull]::Value.Equals($val) -or $val -eq $null) {
                # Skip nulls or set as NONE
                continue
            }
            
            $type = $cols[$i].Type
            
            # Format value
            $strVal = ""
            if ($type -eq "String" -or $type -eq "Guid") {
                # Escape single quotes
                $escaped = $val.ToString().Replace("'", "\'")
                $strVal = "'$escaped'"
            } elseif ($type -like "*Date*") {
                # Format to ISO 8601
                $dateVal = [datetime]$val
                $strVal = "'$($dateVal.ToString("yyyy-MM-ddTHH:mm:ssZ"))'"
            } elseif ($type -eq "Boolean") {
                $strVal = if ($val) { "true" } else { "false" }
            } else {
                # Numeric
                $strVal = $val.ToString().Replace(",", ".") # Ensure culture invariance
            }
            
            $values += "$colName = $strVal"
        }
        
        if ($values.Count -gt 0) {
            $surqlLine += [string]::Join(", ", $values) + ";"
            [System.IO.File]::AppendAllText($OutputFile, $surqlLine + "`n", $utf8NoBom)
        }
    }
    $dReader.Close()
    Write-Host "Exported $rowCount rows from $tbl"
}

$conn.Close()
Write-Host "ETL Complete. Master graph file written to $OutputFile"
