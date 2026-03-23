using System;
using System.Collections.Generic;
using DataProvisioning.Domain.Enums;
namespace DataProvisioning.Domain.Entities;

public class User
{
    public int Id { get; set; }
    public string Name { get; set; } = string.Empty;
    public string Email { get; set; } = string.Empty;
    public UserRole Role { get; set; } = UserRole.User;
    public string? Avatar { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    public ICollection<VirtualGroup> OwnedGroups { get; set; } = new List<VirtualGroup>();
    public ICollection<VirtualGroupMember> GroupMemberships { get; set; } = new List<VirtualGroupMember>();
    public ICollection<AccessRequest> AccessRequests { get; set; } = new List<AccessRequest>();
    public ICollection<AccessRequest> ReviewedRequests { get; set; } = new List<AccessRequest>();
}
