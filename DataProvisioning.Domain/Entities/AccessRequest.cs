using System;
using DataProvisioning.Domain.Enums;
namespace DataProvisioning.Domain.Entities;

public class AccessRequest
{
    public string Id { get; set; }
    public string UserId { get; set; }
    public string DatasetId { get; set; }
    public RequestStatus Status { get; set; } = RequestStatus.Pending;
    public string? RequestedRlsFilters { get; set; }
    public string? Justification { get; set; }
    
    public string? ReviewedById { get; set; }
    public DateTime? ReviewedAt { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    public string? PolicyGroupId { get; set; }

    public User User { get; set; } = null!;
    public Dataset Dataset { get; set; } = null!;
    public User? ReviewedBy { get; set; }
    public AssetPolicyGroup? PolicyGroup { get; set; }
}
