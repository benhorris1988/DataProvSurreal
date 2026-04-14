namespace DataProvisioning.Domain.Entities;

public class AssetPolicyColumn
{
    public int Id { get; set; }
    public int PolicyGroupId { get; set; }
    public string ColumnName { get; set; } = string.Empty;
    public bool IsHidden { get; set; }

    public AssetPolicyGroup PolicyGroup { get; set; } = null!;
}
