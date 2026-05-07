namespace DataProvisioning.Domain.Entities;

public class AssetPolicyCondition
{
    public int Id { get; set; }
    public int PolicyGroupId { get; set; }
    public string ColumnName { get; set; } = string.Empty;
    public string Operator { get; set; } = string.Empty;
    public string Value { get; set; } = string.Empty;

    public AssetPolicyGroup PolicyGroup { get; set; } = null!;
}
