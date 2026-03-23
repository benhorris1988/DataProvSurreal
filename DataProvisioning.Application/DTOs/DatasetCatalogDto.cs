namespace DataProvisioning.Application.DTOs;

public class DatasetCatalogDto
{
    public int Id { get; set; }
    public string Name { get; set; } = string.Empty;
    public string Type { get; set; } = string.Empty;
    public string? Description { get; set; }
    public string GroupName { get; set; } = "Unassigned";
    public int? GroupOwnerId { get; set; }
    public bool IsMember { get; set; }
    public string? AccessStatus { get; set; }
}
