namespace DataProvisioning.Domain.Entities;

public class AssetPolicyCondition
{
    public string Id { get; set; }
    public string PolicyGroupId { get; set; }
    public string ColumnName { get; set; } = string.Empty;
    public string Operator { get; set; } = string.Empty;
    public string Value { get; set; } = string.Empty;

    public AssetPolicyGroup PolicyGroup { get; set; } = null!;
}
