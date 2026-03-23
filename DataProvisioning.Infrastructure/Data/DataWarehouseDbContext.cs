using DataProvisioning.Application.Interfaces;
using DataProvisioning.Domain.Entities;
using Microsoft.EntityFrameworkCore;

namespace DataProvisioning.Infrastructure.Data;

public class DataWarehouseDbContext : DbContext, IDataWarehouseDbContext
{
    public DataWarehouseDbContext(DbContextOptions<DataWarehouseDbContext> options)
        : base(options)
    {
    }

    public DbSet<PermissionMap> PermissionsMap => Set<PermissionMap>();
}
