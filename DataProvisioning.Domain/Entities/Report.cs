using System.Collections.Generic;
namespace DataProvisioning.Domain.Entities;

public class Report
{
    public string Id { get; set; }
    public string Name { get; set; } = string.Empty;
    public string? Url { get; set; }
    public string? Description { get; set; }

    public ICollection<Dataset> Datasets { get; set; } = new List<Dataset>();
}
