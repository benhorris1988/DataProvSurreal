using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using DataProvisioning.Application.DTOs;
using DataProvisioning.Application.Interfaces;

namespace DataProvisioning.Application.Services;

public class CatalogService : ICatalogService
{
    private readonly ISurrealDbService _surreal;

    public CatalogService(ISurrealDbService surreal)
    {
        _surreal = surreal;
    }

    public async Task<List<DatasetCatalogDto>> GetCatalogAsync(int currentUserId, string? searchQuery = null)
    {
        // Build optional search filter
        string whereClause = string.IsNullOrWhiteSpace(searchQuery)
            ? ""
            : $$"""WHERE name CONTAINS "{{Esc(searchQuery)}}" OR description CONTAINS "{{Esc(searchQuery)}}" """;

        // 1. All datasets with owner group info
        var datasets = await _surreal.QueryAppDbAsync<SurrealCatalogDataset>($$"""
            SELECT
                record::id(id)                                                                          AS id,
                name,
                type,
                description,
                IF owner_group_id IS NOT NONE THEN record::id(owner_group_id) ELSE NONE END            AS owner_group_id,
                owner_group_id.name                                                                     AS group_name,
                IF owner_group_id.owner_id IS NOT NONE THEN record::id(owner_group_id.owner_id) ELSE NONE END AS group_owner_id
            FROM datasets
            {{whereClause}}
            ORDER BY name;
            """);

        // 2. Groups the current user belongs to (owned or member-of)
        var memberGroups = await _surreal.QueryAppDbAsync<SurrealGroupRef>($$"""
            SELECT record::id(group_id) AS group_id
            FROM virtual_group_members
            WHERE user_id = users:{{currentUserId}};
            """);

        var memberGroupIds = memberGroups.Select(m => m.GroupId).ToHashSet();

        // 3. Current user's access requests (all datasets, best-status wins)
        var accesses = await _surreal.QueryAppDbAsync<SurrealAccessSummary>($$"""
            SELECT record::id(dataset_id) AS dataset_id, status
            FROM access_requests
            WHERE user_id = users:{{currentUserId}};
            """);

        // Pick best status per dataset: Approved > Pending > Rejected
        var bestAccess = accesses
            .GroupBy(a => a.DatasetId)
            .ToDictionary(
                g => g.Key,
                g => g.OrderBy(a => a.Status switch
                {
                    "Approved" => 1,
                    "Pending"  => 2,
                    _          => 3
                }).First().Status);

        return datasets.Select(d => new DatasetCatalogDto
        {
            Id           = d.Id,
            Name         = d.Name,
            Type         = d.Type,
            Description  = d.Description,
            GroupName    = d.GroupName ?? "Unassigned",
            GroupOwnerId = d.GroupOwnerId,
            IsMember     = d.OwnerGroupId.HasValue && memberGroupIds.Contains(d.OwnerGroupId.Value),
            AccessStatus = bestAccess.GetValueOrDefault(d.Id)
        }).ToList();
    }

    // ── SurrealDB response models ─────────────────────────────────────────────

    private class SurrealCatalogDataset
    {
        public int     Id           { get; set; }
        public string  Name         { get; set; } = "";
        public string  Type         { get; set; } = "";
        public string? Description  { get; set; }
        public int?    OwnerGroupId { get; set; }   // record::id(owner_group_id)
        public string? GroupName    { get; set; }   // owner_group_id.name
        public int?    GroupOwnerId { get; set; }   // record::id(owner_group_id.owner_id)
    }

    private class SurrealGroupRef      { public int GroupId   { get; set; } }
    private class SurrealAccessSummary { public int DatasetId { get; set; } public string Status { get; set; } = ""; }

    private static string Esc(string? s) =>
        (s ?? "").Replace("\\", "\\\\").Replace("\"", "\\\"");
}
