using System;
using System.Collections.Generic;
using DataProvisioning.Domain.Enums;

namespace DataProvisioning.Application.DTOs;

public class MyRequestDto
{
    public int Id { get; set; }
    public int DatasetId { get; set; }
    public string DatasetName { get; set; } = string.Empty;
    public string? OwnerName { get; set; }
    public string? GroupName { get; set; }
    public string Status { get; set; } = string.Empty;
    public string? ReviewerName { get; set; }
    public DateTime CreatedAt { get; set; }
    
    public List<DatasetApproverDto> PendingApprovers { get; set; } = new();
    public List<DatasetApproverDto> GlobalApprovers { get; set; } = new();
}

public class SubmitRequestDto
{
    public int DatasetId { get; set; }
    public string Justification { get; set; } = string.Empty;
    public int? PolicyGroupId { get; set; }
}
