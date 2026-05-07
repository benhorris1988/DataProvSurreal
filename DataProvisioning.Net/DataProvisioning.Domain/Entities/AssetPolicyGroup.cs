using System;
using System.Collections.Generic;

namespace DataProvisioning.Domain.Entities;

public class AssetPolicyGroup
{
    public int Id { get; set; }
    public int DatasetId { get; set; }
    public int? OwnerId { get; set; }
    public string Name { get; set; } = string.Empty;
    public string? Description { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    public Dataset Dataset { get; set; } = null!;
    public User? Owner { get; set; }
    public ICollection<AccessRequest> AccessRequests { get; set; } = new List<AccessRequest>();
    public ICollection<AssetPolicyCondition> Conditions { get; set; } = new List<AssetPolicyCondition>();
    public ICollection<AssetPolicyColumn> HiddenColumns { get; set; } = new List<AssetPolicyColumn>();
}
