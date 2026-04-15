namespace DataProvisioning.Domain.Entities;

public class InitialAdmin
{
    public string Id { get; set; }
    
    /// <summary>
    /// The Windows domain username, e.g., "DOMAIN\username"
    /// </summary>
    public string Username { get; set; } = string.Empty;
}
