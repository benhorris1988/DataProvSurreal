using System.Threading;
using System.Threading.Tasks;
using DataProvisioning.Domain.Entities;
using Microsoft.EntityFrameworkCore;

namespace DataProvisioning.Application.Interfaces;

public interface IDataWarehouseDbContext
{
    DbSet<PermissionMap> PermissionsMap { get; }
    
    Task<int> SaveChangesAsync(CancellationToken cancellationToken = default);
}
