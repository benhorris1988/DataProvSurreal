using System;
using System.Collections.Generic;
namespace DataProvisioning.Domain.Entities;

public class VirtualGroup
{
    public string Id { get; set; }
    public string Name { get; set; } = string.Empty;
    public string OwnerId { get; set; }
    public string? Description { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    public User Owner { get; set; } = null!;
    public ICollection<VirtualGroupMember> Members { get; set; } = new List<VirtualGroupMember>();
    public ICollection<Dataset> Datasets { get; set; } = new List<Dataset>();
}
