using Microsoft.EntityFrameworkCore;
using DataProvisioning.Domain.Entities;
using System.Threading;
using System.Threading.Tasks;

namespace DataProvisioning.Application.Interfaces;

public interface IApplicationDbContext
{
    DbSet<User> Users { get; }
    DbSet<VirtualGroup> VirtualGroups { get; }
    DbSet<VirtualGroupMember> VirtualGroupMembers { get; }
    DbSet<Dataset> Datasets { get; }
    DbSet<DatasetColumn> Columns { get; }
    DbSet<AccessRequest> AccessRequests { get; }
    DbSet<Report> Reports { get; }
    DbSet<AssetPolicyGroup> AssetPolicyGroups { get; }
    DbSet<AssetPolicyCondition> AssetPolicyConditions { get; }
    DbSet<AssetPolicyColumn> AssetPolicyColumns { get; }

    Task<int> SaveChangesAsync(CancellationToken cancellationToken = default);
}
