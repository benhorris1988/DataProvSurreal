namespace DataProvisioning.Domain.Entities;

public class DatasetColumn
{
    public int Id { get; set; }
    public int DatasetId { get; set; }
    public string Name { get; set; } = string.Empty;
    public string? DataType { get; set; }
    public string? Definition { get; set; }
    public bool IsPii { get; set; } = false;
    public string? SampleData { get; set; }

    public Dataset Dataset { get; set; } = null!;
}
