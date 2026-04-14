using System;
using System.Collections.Generic;

namespace DataProvisioning.Application.DTOs;

public class ManageAccessViewModel
{
    public List<ManagedDatasetDto> MyDatasets { get; set; } = new();
    public List<PendingRequestDto> PendingRequests { get; set; } = new();
    public List<RequestHistoryDto> RecentDecisions { get; set; } = new();
}

public class ManagedDatasetDto
{
    public int Id { get; set; }
    public string Name { get; set; } = string.Empty;
    public string Type { get; set; } = string.Empty;
    public string Description { get; set; } = string.Empty;
    public int PolicyCount { get; set; }
    public int UserAccessCount { get; set; }
    public bool IsMissingFromDataWarehouse { get; set; }
}

public class PendingRequestDto
{
    public int Id { get; set; }
    public int DatasetId { get; set; }
    public string RequestorName { get; set; } = string.Empty;
    public string DatasetName { get; set; } = string.Empty;
    public DateTime CreatedAt { get; set; }
    public string Justification { get; set; } = string.Empty;
    public string? RequestedFilters { get; set; }
    public int? SelectedPolicyGroupId { get; set; }
    
    public List<DatasetApproverDto> GroupApprovers { get; set; } = new();
    public List<PolicyGroupOptionDto> AvailablePolicies { get; set; } = new();
}

public class RequestHistoryDto
{
    public DateTime ReviewedAt { get; set; }
    public string RequestorName { get; set; } = string.Empty;
    public string DatasetName { get; set; } = string.Empty;
    public string Status { get; set; } = string.Empty;
    public string? AppliedPolicyName { get; set; }
}

public class PolicyGroupOptionDto
{
    public int Id { get; set; }
    public string Name { get; set; } = string.Empty;
}

// --- Policy Management DTOs (manage_policy.php) ---

public class ManagePolicyViewModel
{
    public int DatasetId { get; set; }
    public string DatasetName { get; set; } = string.Empty;
    public List<DatasetColumnDto> DatasetColumns { get; set; } = new();
    public List<PolicyGroupDetailDto> PolicyGroups { get; set; } = new();
    public List<UserDto> AvailableOwners { get; set; } = new();
}

public class PolicyGroupDetailDto
{
    public int Id { get; set; }
    public string Name { get; set; } = string.Empty;
    public string? Description { get; set; }
    public string OwnerName { get; set; } = string.Empty;
    
    public List<PolicyConditionDto> Conditions { get; set; } = new();
    public List<PolicyColumnDto> Columns { get; set; } = new();
    public List<PolicyUserDto> AuthorizedUsers { get; set; } = new();
}

public class PolicyConditionDto
{
    public int Id { get; set; }
    public string ColumnName { get; set; } = string.Empty;
    public string Operator { get; set; } = string.Empty;
    public string Value { get; set; } = string.Empty;
}

public class PolicyColumnDto
{
    public string ColumnName { get; set; } = string.Empty;
    public bool IsHidden { get; set; }
}

public class PolicyUserDto
{
    public string Name { get; set; } = string.Empty;
    public string Email { get; set; } = string.Empty;
    public DateTime? ReviewedAt { get; set; }
}
