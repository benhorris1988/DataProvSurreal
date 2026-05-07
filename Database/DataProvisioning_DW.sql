IF OBJECT_ID(N'[__EFMigrationsHistory]') IS NULL
BEGIN
    CREATE TABLE [__EFMigrationsHistory] (
        [MigrationId] nvarchar(150) NOT NULL,
        [ProductVersion] nvarchar(32) NOT NULL,
        CONSTRAINT [PK___EFMigrationsHistory] PRIMARY KEY ([MigrationId])
    );
END;
GO

-- ========================================
-- FACT TABLES
-- ========================================
IF OBJECT_ID(N'[dbo].[fact_CustomerOrders]') IS NULL
BEGIN
    CREATE TABLE [dbo].[fact_CustomerOrders] (
        [id] INT PRIMARY KEY IDENTITY(1,1),
        [customer_id] INT NULL,
        [order_id] INT NULL,
        [order_date] DATETIME NULL,
        [amount] DECIMAL(18,2) NULL
    );
END;
GO

IF OBJECT_ID(N'[dbo].[FactInventory]') IS NULL
BEGIN
    CREATE TABLE [dbo].[FactInventory] (
        [id] INT PRIMARY KEY IDENTITY(1,1),
        [part_id] INT NULL,
        [quantity_on_hand] INT NULL,
        [quantity_reserved] INT NULL,
        [warehouse_location] NVARCHAR(MAX) NULL
    );
END;
GO

IF OBJECT_ID(N'[dbo].[FactSales]') IS NULL
BEGIN
    CREATE TABLE [dbo].[FactSales] (
        [id] INT PRIMARY KEY IDENTITY(1,1),
        [sales_date] DATETIME NULL,
        [part_id] INT NULL,
        [quantity_sold] INT NULL,
        [revenue] DECIMAL(18,2) NULL
    );
END;
GO

-- ========================================
-- DIMENSION TABLES
-- ========================================
IF OBJECT_ID(N'[dbo].[dim_Customer]') IS NULL
BEGIN
    CREATE TABLE [dbo].[dim_Customer] (
        [id] INT PRIMARY KEY IDENTITY(1,1),
        [customer_name] NVARCHAR(MAX) NULL,
        [customer_email] NVARCHAR(MAX) NULL,
        [customer_phone] NVARCHAR(MAX) NULL,
        [created_at] DATETIME NULL
    );
END;
GO

IF OBJECT_ID(N'[dbo].[dim_Order]') IS NULL
BEGIN
    CREATE TABLE [dbo].[dim_Order] (
        [id] INT PRIMARY KEY IDENTITY(1,1),
        [order_number] NVARCHAR(MAX) NULL,
        [order_status] NVARCHAR(MAX) NULL,
        [customer_id] INT NULL,
        [order_date] DATETIME NULL
    );
END;
GO

IF OBJECT_ID(N'[dbo].[dim_Part]') IS NULL
BEGIN
    CREATE TABLE [dbo].[dim_Part] (
        [id] INT PRIMARY KEY IDENTITY(1,1),
        [part_number] NVARCHAR(MAX) NULL,
        [part_description] NVARCHAR(MAX) NULL,
        [unit_cost] DECIMAL(18,2) NULL,
        [supplier_id] INT NULL
    );
END;
GO

IF OBJECT_ID(N'[dbo].[dim_SalesPart]') IS NULL
BEGIN
    CREATE TABLE [dbo].[dim_SalesPart] (
        [id] INT PRIMARY KEY IDENTITY(1,1),
        [part_id] INT NULL,
        [sales_region] NVARCHAR(MAX) NULL,
        [sales_channel] NVARCHAR(MAX) NULL,
        [unit_price] DECIMAL(18,2) NULL
    );
END;
GO

-- ========================================
-- PERMISSIONS AND UTILITY TABLES
-- ========================================
IF OBJECT_ID(N'[dbo].[PermissionsMap]') IS NULL
BEGIN
    CREATE TABLE [dbo].[PermissionsMap] (
        [id] NVARCHAR(MAX) PRIMARY KEY,
        [user_id] NVARCHAR(MAX) NULL,
        [dataset_id] NVARCHAR(MAX) NULL,
        [row_filter] NVARCHAR(MAX) NULL,
        [created_at] DATETIME NULL
    );
END;
GO

