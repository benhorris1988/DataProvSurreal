using System;
using System.Collections.Generic;
using DataProvisioning.Domain.Enums;
namespace DataProvisioning.Domain.Entities;

public class Dataset
{
    public int Id { get; set; }
    public string Name { get; set; } = string.Empty;
    public DatasetType Type { get; set; } = DatasetType.Fact;
    public string? Description { get; set; }
    public int? OwnerGroupId { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    public VirtualGroup? OwnerGroup { get; set; }
    public ICollection<DatasetColumn> Columns { get; set; } = new List<DatasetColumn>();
    public ICollection<AccessRequest> AccessRequests { get; set; } = new List<AccessRequest>();
    public ICollection<Report> Reports { get; set; } = new List<Report>();
    public ICollection<AssetPolicyGroup> PolicyGroups { get; set; } = new List<AssetPolicyGroup>();
}
