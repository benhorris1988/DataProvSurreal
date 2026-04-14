namespace DataProvisioning.Domain.Entities;

public class AssetPolicyColumn
{
    public string Id { get; set; }
    public string PolicyGroupId { get; set; }
    public string ColumnName { get; set; } = string.Empty;
    public bool IsHidden { get; set; }

    public AssetPolicyGroup PolicyGroup { get; set; } = null!;
}
