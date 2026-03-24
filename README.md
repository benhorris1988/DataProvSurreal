# Data Provisioning Engine

The **Data Provisioning Engine** is a comprehensive .NET 8 ASP.NET Core MVC web application designed to manage and orchestrate user access requests to organizational databases and datasets. 

Originally ported from a legacy PHP architecture, this modernized application enforces Clean Architecture principles, strict separation of concerns, and enterprise-grade identity integration.

## Key Features

- **Dataset Catalog:** Browse available data assets, view metadata (Fact/Dimension/Staging), and request granular access.
- **Request Workflows:** Automated request tracking. Users can request access, while Information Asset Owners (IAO) and Approvers (IAA) can review, approve, or reject dataset access natively.
- **Role-Based Access Control (RBAC):** Built-in support for multiple organizational roles (`Admin`, `IAO`, `IAA`, `User`), seamlessly integratable with Microsoft Entra ID (Azure AD).
- **Virtual Groups & Policies:** Enforce row-level security or specific slice access by mapping datasets to Virtual Groups and Access Policies.
- **KPI Dashboard:** A dynamic, beautiful dashboard tracking real-time metrics including "My Active Assets", "Pending Requests", 30-day activity trends, and dataset composition analytics.
- **Admin Control Centre:** Administrators can dynamically update underlying database connection strings and identity provider settings without touching the codebase.

## Application Architecture

The solution implements a strict Clean Architecture pattern divided into four primary layers:

1. **`DataProvisioning.Domain` (Core)**
   - Contains all enterprise logic, base Entities (e.g., `Dataset`, `AccessRequest`, `VirtualGroup`), Enums, and custom Exceptions.
   - **No dependencies.** The safest, most isolated layer of the application.

2. **`DataProvisioning.Application` (Use Cases)**
   - Houses the business rules, Services (`CatalogService`, `AccessRequestService`), Interfaces, Data Transfer Objects (DTOs), and ViewModels.
   - Only depends on the `Domain` layer.

3. **`DataProvisioning.Infrastructure` (Data & External)**
   - Connects to external systems. Contains the Entity Framework Core `ApplicationDbContext` mapped directly to SQL Server 2022.
   - Handles interactions with identity services or external APIs.
   - Depends on the `Application` layer to fullfil its defined interfaces.

4. **`DataProvisioning.WebUI` (Presentation)**
   - The ASP.NET Core MVC application providing the user interface.
   - Utilizes custom "Babcock" CSS styling built over Bootstrap grids, ensuring a responsive, glassmorphism-inspired dark theme.
   - Configures Dependency Injection (DI) and coordinates HTTP requests.

5. **`DataProvisioning.UnitTests` & `DataProvisioning.IntegrationTests`**
   - xUnit based test projects to secure the application logic and ensure data adapters function correctly against the database context.

## Technology Stack

- **Framework:** .NET 8.0 SDK
- **Web App:** ASP.NET Core MVC
- **Data Access:** Entity Framework Core 8.0 (EF Core)
- **Database Target:** Microsoft SQL Server 2022
- **Frontend Stack:** HTML5, standard CSS3 (Flexbox/Grid), JavaScript, jQuery 3.x, Bootstrap 5 (Grids/Utilities).

## Getting Started for Developers

1. **Open the Project:**
   Open `DataProvisioning.slnx` (or `.sln`) in Visual Studio 2022.

2. **Configure Database Settings:**
   - Run the application, authenticate as an Administrator, and navigate to **Administration -> Admin Centre** to configure the target SQL Server.
   - Alternatively, supply standard ADO.NET connection strings to `appsettings.json` under `ConnectionStrings:DefaultConnection`.

3. **Run Locally:**
   Set `DataProvisioning.WebUI` as the Startup Project and press `F5` to launch via IIS Express or Kestrel. Entity Framework will automatically migrate the core tables into your local database if they do not exist.
