using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace DataProvisioning.Domain.Entities;

[Table("PermissionsMap", Schema = "AppAdmin")]
public class PermissionMap
{
    [Key]
    [DatabaseGenerated(DatabaseGeneratedOption.Identity)]
    public int RecordID { get; set; }

    [Required]
    [MaxLength(255)]
    public string UserID { get; set; } = string.Empty;

    [Required]
    [MaxLength(255)]
    public string TableName { get; set; } = string.Empty;

    [Required]
    [MaxLength(255)]
    public string ColumnID { get; set; } = string.Empty;

    [Required]
    [MaxLength(255)]
    public string AuthorizedValue { get; set; } = string.Empty;
}
