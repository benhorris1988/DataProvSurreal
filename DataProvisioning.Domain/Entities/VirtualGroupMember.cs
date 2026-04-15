using System;
namespace DataProvisioning.Domain.Entities;

public class VirtualGroupMember
{
    public string GroupId { get; set; }
    public string UserId { get; set; }
    public DateTime AddedAt { get; set; } = DateTime.UtcNow;

    public VirtualGroup Group { get; set; } = null!;
    public User User { get; set; } = null!;
}
