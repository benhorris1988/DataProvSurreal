namespace DataProvisioning.Application.Interfaces;

/// <summary>
/// Wraps SurrealDB HTTP API calls — primary data store for all application data.
/// </summary>
public interface ISurrealDbService
{
    /// <summary>
    /// Signs a user into SurrealDB and returns a JWT for subsequent DataWarehouse queries
    /// (which enforce row-level security). Returns null if the user is not found.
    /// </summary>
    Task<string?> SignInAsync(string email);

    /// <summary>Returns the integer IDs of all datasets the user has been granted access to.</summary>
    Task<IEnumerable<int>> GetAccessibleDatasetIdsAsync(int userId);

    /// <summary>Creates a has_access graph edge when an access request is approved.</summary>
    Task GrantDatasetAccessAsync(int userId, int datasetId, int grantedByUserId, string? rlsFilters, int? policyGroupId);

    /// <summary>Removes the has_access edge when access is revoked.</summary>
    Task RevokeDatasetAccessAsync(int userId, int datasetId);

    /// <summary>
    /// Executes SurrealQL against AppDB and deserialises the first result-set into List&lt;T&gt;.
    /// Uses snake_case-aware JSON options; empty / error results return an empty list.
    /// </summary>
    Task<List<T>> QueryAppDbAsync<T>(string surql);

    /// <summary>Executes a raw SurrealQL statement against AppDB and returns the raw JSON string.</summary>
    Task<string> QueryAppDbAsync(string surql);

    /// <summary>Executes a write statement (INSERT/UPDATE/DELETE) against AppDB — result is discarded.</summary>
    Task ExecuteAppDbAsync(string surql);

    /// <summary>Executes a write statement against the DataWarehouse as root (for PermissionsMap updates).</summary>
    Task ExecuteDwAsync(string surql);

    /// <summary>
    /// Executes SurrealQL against the DataWarehouse using the supplied user JWT so that
    /// SurrealDB row-level PERMISSIONS (backed by PermissionsMap) are applied.
    /// </summary>
    Task<string> QueryDataWarehouseAsync(string surql, string userJwt);
}
