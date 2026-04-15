namespace DataProvisioning.Domain.Entities;

public class DatasetColumn
{
    public string Id { get; set; }
    public string DatasetId { get; set; }
    public string Name { get; set; } = string.Empty;
    public string? DataType { get; set; }
    public string? Definition { get; set; }
    public bool IsPii { get; set; } = false;
    public string? SampleData { get; set; }

    public Dataset Dataset { get; set; } = null!;
}
