using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using DataProvisioning.Application.Interfaces;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;

namespace DataProvisioning.Infrastructure.Data;

/// <summary>
/// Calls the SurrealDB HTTP API directly — no extra NuGet package required.
/// Configured via appsettings.json under "SurrealDb".
/// </summary>
public class SurrealDbService : ISurrealDbService
{
    private readonly HttpClient _http;
    private readonly ILogger<SurrealDbService> _logger;

    private readonly string _namespace;
    private readonly string _appDb;
    private readonly string _dwDb;
    private readonly string _rootUser;
    private readonly string _rootPass;

    // Shared options: case-insensitive property matching + snake_case name policy
    private static readonly JsonSerializerOptions _jsonOptions = new()
    {
        PropertyNameCaseInsensitive = true,
        PropertyNamingPolicy        = JsonNamingPolicy.SnakeCaseLower,
        NumberHandling              = JsonNumberHandling.AllowReadingFromString,
        DefaultIgnoreCondition      = JsonIgnoreCondition.Never
    };

    public SurrealDbService(HttpClient http, IConfiguration config, ILogger<SurrealDbService> logger)
    {
        _http    = http;
        _logger  = logger;

        var section = config.GetSection("SurrealDb");
        _namespace  = section["Namespace"] ?? "Data Provisioning Engine";
        _appDb      = section["AppDb"]     ?? "AppDB";
        _dwDb       = section["DwDb"]      ?? "DataWarehouse";
        _rootUser   = section["Username"]  ?? "root";
        _rootPass   = section["Password"]  ?? "root";
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AUTH
    // ──────────────────────────────────────────────────────────────────────────

    public async Task<string?> SignInAsync(string email)
    {
        var body = JsonSerializer.Serialize(new
        {
            ns = _namespace,
            db = _appDb,
            ac = "user_token",
            email
        });

        var request = new HttpRequestMessage(HttpMethod.Post, "/signin")
        {
            Content = new StringContent(body, Encoding.UTF8, "application/json")
        };

        var response = await _http.SendAsync(request);
        if (!response.IsSuccessStatusCode)
        {
            _logger.LogWarning("SurrealDB signin failed for {Email}: {Status}", email, response.StatusCode);
            return null;
        }

        var json = await response.Content.ReadAsStringAsync();
        if (string.IsNullOrWhiteSpace(json))
        {
            _logger.LogWarning("SurrealDB signin returned empty body for {Email}", email);
            return null;
        }

        try
        {
            using var doc = JsonDocument.Parse(json);
            if (doc.RootElement.TryGetProperty("token", out var tokenEl))
                return tokenEl.GetString();
        }
        catch (JsonException ex)
        {
            _logger.LogWarning(ex, "SurrealDB signin response was not valid JSON for {Email}", email);
            return null;
        }

        _logger.LogWarning("SurrealDB signin response had no token for {Email}", email);
        return null;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GRAPH OPERATIONS
    // ──────────────────────────────────────────────────────────────────────────

    public async Task<IEnumerable<int>> GetAccessibleDatasetIdsAsync(int userId)
    {
        var surql  = $"SELECT ->has_access->datasets.id AS ids FROM users:{userId};";
        var result = await ExecuteSqlAsync(surql, _namespace, _appDb, useRootAuth: true);

        try
        {
            using var doc = JsonDocument.Parse(result);
            var root = doc.RootElement;

            if (root.ValueKind == JsonValueKind.Array &&
                root.GetArrayLength() > 0 &&
                root[0].TryGetProperty("result", out var rows) &&
                rows.ValueKind == JsonValueKind.Array &&
                rows.GetArrayLength() > 0 &&
                rows[0].TryGetProperty("ids", out var ids) &&
                ids.ValueKind == JsonValueKind.Array)
            {
                var datasetIds = new List<int>();
                foreach (var item in ids.EnumerateArray())
                {
                    var raw   = item.GetString() ?? "";
                    var colon = raw.IndexOf(':');
                    if (colon >= 0 && int.TryParse(raw[(colon + 1)..], out var id))
                        datasetIds.Add(id);
                }
                return datasetIds;
            }
        }
        catch (JsonException ex)
        {
            _logger.LogError(ex, "Failed to parse GetAccessibleDatasetIds response");
        }

        return [];
    }

    public async Task GrantDatasetAccessAsync(int userId, int datasetId, int grantedByUserId, string? rlsFilters, int? policyGroupId)
    {
        var parts = new List<string>
        {
            $"granted_by = users:{grantedByUserId}",
            "granted_at = time::now()"
        };

        if (!string.IsNullOrWhiteSpace(rlsFilters))
        {
            var escaped = rlsFilters.Replace("\\", "\\\\").Replace("\"", "\\\"");
            parts.Add($"rls_filters = \"{escaped}\"");
        }

        if (policyGroupId.HasValue)
            parts.Add($"policy_group_id = asset_policy_groups:{policyGroupId.Value}");

        var setClause = string.Join(", ", parts);
        var surql     = $"RELATE users:{userId}->has_access->datasets:{datasetId} SET {setClause};";

        await ExecuteSqlAsync(surql, _namespace, _appDb, useRootAuth: true);
        _logger.LogInformation("Granted SurrealDB has_access: users:{UserId} -> datasets:{DatasetId}", userId, datasetId);
    }

    public async Task RevokeDatasetAccessAsync(int userId, int datasetId)
    {
        var surql = $"DELETE has_access WHERE in = users:{userId} AND out = datasets:{datasetId};";
        await ExecuteSqlAsync(surql, _namespace, _appDb, useRootAuth: true);
        _logger.LogInformation("Revoked SurrealDB has_access: users:{UserId} -> datasets:{DatasetId}", userId, datasetId);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TYPED QUERIES
    // ──────────────────────────────────────────────────────────────────────────

    public async Task<List<T>> QueryAppDbAsync<T>(string surql)
    {
        var raw = await ExecuteSqlAsync(surql, _namespace, _appDb, useRootAuth: true);
        return ParseFirstResult<T>(raw);
    }

    public Task<string> QueryAppDbAsync(string surql)
        => ExecuteSqlAsync(surql, _namespace, _appDb, useRootAuth: true);

    public Task ExecuteAppDbAsync(string surql)
        => ExecuteSqlAsync(surql, _namespace, _appDb, useRootAuth: true);

    public Task ExecuteDwAsync(string surql)
        => ExecuteSqlAsync(surql, _namespace, _dwDb, useRootAuth: true);

    public Task<string> QueryDataWarehouseAsync(string surql, string userJwt)
        => ExecuteSqlAsync(surql, _namespace, _dwDb, jwt: userJwt);

    // ──────────────────────────────────────────────────────────────────────────
    // JSON HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    /// <summary>
    /// Parses a SurrealDB /sql response and deserialises the first result-set.
    /// Response shape: [{ "status": "OK", "result": [...] }]
    /// </summary>
    private static List<T> ParseFirstResult<T>(string raw)
    {
        try
        {
            using var doc  = JsonDocument.Parse(raw);
            var root       = doc.RootElement;

            if (root.ValueKind != JsonValueKind.Array || root.GetArrayLength() == 0)
                return [];

            var first = root[0];
            if (!first.TryGetProperty("result", out var result))
                return [];

            // "result" is an array for SELECT, a single object for FROM ONLY
            if (result.ValueKind == JsonValueKind.Array)
                return JsonSerializer.Deserialize<List<T>>(result.GetRawText(), _jsonOptions) ?? [];

            if (result.ValueKind == JsonValueKind.Object)
            {
                var single = JsonSerializer.Deserialize<T>(result.GetRawText(), _jsonOptions);
                return single is null ? [] : [single];
            }
        }
        catch (Exception)
        {
            // Swallow parse errors — return empty
        }

        return [];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // INTERNAL HTTP HELPER
    // ──────────────────────────────────────────────────────────────────────────

    private async Task<string> ExecuteSqlAsync(
        string surql,
        string ns,
        string db,
        bool useRootAuth = false,
        string? jwt = null)
    {
        var request = new HttpRequestMessage(HttpMethod.Post, "/sql")
        {
            Content = new StringContent(surql, Encoding.UTF8, "text/plain")
        };

        request.Headers.Add("surreal-ns", ns);
        request.Headers.Add("surreal-db", db);
        request.Headers.Add("Accept", "application/json");

        if (jwt != null)
        {
            request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", jwt);
        }
        else if (useRootAuth)
        {
            var credentials = Convert.ToBase64String(Encoding.UTF8.GetBytes($"{_rootUser}:{_rootPass}"));
            request.Headers.Authorization = new AuthenticationHeaderValue("Basic", credentials);
        }

        try
        {
            var response = await _http.SendAsync(request);
            return await response.Content.ReadAsStringAsync();
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "SurrealDB query failed: {Surql}", surql[..Math.Min(surql.Length, 200)]);
            return "[]";
        }
    }
}
