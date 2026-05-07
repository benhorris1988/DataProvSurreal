using System;
using DataProvisioning.Domain.Enums;
namespace DataProvisioning.Domain.Entities;

public class AccessRequest
{
    public int Id { get; set; }
    public int UserId { get; set; }
    public int DatasetId { get; set; }
    public RequestStatus Status { get; set; } = RequestStatus.Pending;
    public string? RequestedRlsFilters { get; set; }
    public string? Justification { get; set; }
    
    public int? ReviewedById { get; set; }
    public DateTime? ReviewedAt { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    public int? PolicyGroupId { get; set; }

    public User User { get; set; } = null!;
    public Dataset Dataset { get; set; } = null!;
    public User? ReviewedBy { get; set; }
    public AssetPolicyGroup? PolicyGroup { get; set; }
}
