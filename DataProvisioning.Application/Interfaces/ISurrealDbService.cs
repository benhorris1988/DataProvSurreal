namespace DataProvisioning.Application.Interfaces;

/// <summary>
/// Wraps SurrealDB HTTP API calls for graph operations and DataWarehouse access.
/// SQL Server / EF Core remains the primary store; SurrealDB handles graph edges,
/// row-level security enforcement in the DataWarehouse, and JWT auth tokens.
/// </summary>
public interface ISurrealDbService
{
    /// <summary>
    /// Signs a user into SurrealDB using their email address (Windows Auth has already
    /// validated their identity). Returns a JWT token that can be passed to the browser
    /// or used server-side when querying the DataWarehouse with RLS applied.
    /// Returns null if the user is not found in SurrealDB.
    /// </summary>
    Task<string?> SignInAsync(string email);

    /// <summary>
    /// Returns the SurrealDB record IDs of all datasets the user has been granted access to,
    /// derived from the has_access graph edge (users -> datasets).
    /// </summary>
    Task<IEnumerable<int>> GetAccessibleDatasetIdsAsync(int userId);

    /// <summary>
    /// Creates a has_access graph edge: RELATE users:{userId} -> has_access -> datasets:{datasetId}
    /// Called when an access request is approved.
    /// </summary>
    Task GrantDatasetAccessAsync(int userId, int datasetId, int grantedByUserId, string? rlsFilters, int? policyGroupId);

    /// <summary>
    /// Removes the has_access edge between a user and a dataset.
    /// Called when access is revoked.
    /// </summary>
    Task RevokeDatasetAccessAsync(int userId, int datasetId);

    /// <summary>
    /// Executes a raw SurrealQL query against AppDB and returns the raw JSON result.
    /// Useful for graph traversal queries that have no EF Core equivalent.
    /// </summary>
    Task<string> QueryAppDbAsync(string surql);

    /// <summary>
    /// Executes a raw SurrealQL query against DataWarehouse using the provided JWT token,
    /// so the SurrealDB row-level permissions (PermissionsMap) are applied to the results.
    /// </summary>
    Task<string> QueryDataWarehouseAsync(string surql, string userJwt);
}
