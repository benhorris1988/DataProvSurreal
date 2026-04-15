using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using DataProvisioning.Application.DTOs;
using DataProvisioning.Application.Interfaces;

namespace DataProvisioning.Application.Services;

public class DatasetDetailsService : IDatasetDetailsService
{
    private readonly ISurrealDbService _surreal;

    public DatasetDetailsService(ISurrealDbService surreal)
    {
        _surreal = surreal;
    }

    public async Task<DatasetDetailsDto?> GetDatasetDetailsAsync(int datasetId, int currentUserId, string currentUserRole)
    {
        // 1. Dataset header + owner group
        var datasets = await _surreal.QueryAppDbAsync<SurrealDatasetHeader>($$"""
            SELECT
                record::id(id)                                                                               AS id,
                name,
                type,
                description,
                IF owner_group_id IS NOT NONE THEN record::id(owner_group_id) ELSE NONE END                 AS owner_group_id,
                IF owner_group_id.owner_id IS NOT NONE THEN record::id(owner_group_id.owner_id) ELSE NONE END AS group_owner_id,
                owner_group_id.name                                                                          AS group_name
            FROM datasets WHERE id = datasets:{{datasetId}} LIMIT 1;
            """);

        var dataset = datasets.FirstOrDefault();
        if (dataset == null) return null;

        var dto = new DatasetDetailsDto
        {
            Id             = dataset.Id,
            Name           = dataset.Name,
            Type           = dataset.Type,
            Description    = dataset.Description,
            GroupName      = dataset.GroupName,
            OwnerGroupId   = dataset.OwnerGroupId,
            IsOwnerOrAdmin = currentUserRole == "Admin" || dataset.GroupOwnerId == currentUserId
        };

        // 2. Columns
        dto.Columns = await _surreal.QueryAppDbAsync<DatasetColumnDto>($$"""
            SELECT record::id(id) AS id, name, data_type, definition, is_pii, sample_data
            FROM columns WHERE dataset_id = datasets:{{datasetId}};
            """);

        // 3. Linked reports (via junction table)
        var reportLinks = await _surreal.QueryAppDbAsync<SurrealReportLink>($$"""
            SELECT record::id(report_id) AS id, report_id.name AS name, report_id.url AS url
            FROM report_datasets WHERE dataset_id = datasets:{{datasetId}};
            """);

        dto.LinkedReports = reportLinks
            .Select(r => new DatasetReportDto { Id = r.Id, Name = r.Name ?? "", Url = r.Url })
            .ToList();

        // 4. User's access requests for this dataset
        var requests = await _surreal.QueryAppDbAsync<SurrealRequestDetail>($$"""
            SELECT
                record::id(id)                                                                         AS id,
                status,
                IF policy_group_id IS NOT NONE THEN record::id(policy_group_id) ELSE NONE END         AS policy_group_id,
                policy_group_id.name                                                                   AS policy_name,
                reviewed_by.name                                                                       AS reviewer_name,
                created_at
            FROM access_requests
            WHERE user_id = users:{{currentUserId}} AND dataset_id = datasets:{{datasetId}}
            ORDER BY created_at DESC;
            """);

        dto.HasFullAccess = requests.Any(r => r.Status == "Approved" && r.PolicyGroupId == null);

        // 5. Global approvers (for pending request display)
        var globalApprovers = await _surreal.QueryAppDbAsync<DatasetApproverDto>(
            "SELECT name, role AS role_type FROM users WHERE role IN ['Admin', 'IAO'];");

        // 6. Group approvers (owner + members)
        var groupApprovers = new List<DatasetApproverDto>();
        if (dataset.OwnerGroupId.HasValue)
        {
            // Owner
            var owners = await _surreal.QueryAppDbAsync<SurrealGroupOwner>($$"""
                SELECT owner_id.name AS name FROM virtual_groups WHERE id = virtual_groups:{{dataset.OwnerGroupId.Value}} LIMIT 1;
                """);
            var ownerName = owners.FirstOrDefault()?.Name;
            if (!string.IsNullOrEmpty(ownerName))
                groupApprovers.Add(new DatasetApproverDto { Name = ownerName, RoleType = "Owner" });

            // Members
            var members = await _surreal.QueryAppDbAsync<DatasetApproverDto>($$"""
                SELECT user_id.name AS name, 'Member' AS role_type
                FROM virtual_group_members WHERE group_id = virtual_groups:{{dataset.OwnerGroupId.Value}};
                """);
            groupApprovers.AddRange(members);
        }

        // 7. Build per-request DTOs
        var requestedOrActiveIds = requests
            .Where(r => r.Status == "Approved" || r.Status == "Pending")
            .Select(r => r.PolicyGroupId)
            .ToList();

        foreach (var req in requests)
        {
            var reqDto = new DatasetRequestDto
            {
                Id           = req.Id,
                Status       = req.Status,
                PolicyName   = req.PolicyName ?? "Full Dataset",
                PolicyGroupId = req.PolicyGroupId,
                ReviewerName = req.ReviewerName,
                CreatedAt    = req.CreatedAt
            };

            if (req.Status == "Pending")
            {
                reqDto.GlobalApprovers  = globalApprovers;
                reqDto.PendingApprovers = groupApprovers
                    .OrderBy(a => a.RoleType == "Owner" ? 0 : 1)
                    .ThenBy(a => a.Name)
                    .ToList();
            }

            dto.UserRequests.Add(reqDto);
        }

        // 8. Available policy groups (not already requested/active)
        var allPolicies = await _surreal.QueryAppDbAsync<AssetPolicyGroupDto>($$"""
            SELECT record::id(id) AS id, name, description
            FROM asset_policy_groups WHERE dataset_id = datasets:{{datasetId}};
            """);

        dto.AvailablePolicies = allPolicies
            .Where(p => !requestedOrActiveIds.Contains(p.Id))
            .ToList();

        if (!requestedOrActiveIds.Contains(null))
        {
            dto.AvailablePolicies.Add(new AssetPolicyGroupDto
            {
                Id          = 0,
                Name        = "Full Dataset (Requires Admin Approval)",
                Description = ""
            });
        }

        return dto;
    }

    public async Task<EditDatasetDto?> GetEditDatasetAsync(int datasetId, int currentUserId, string currentUserRole)
    {
        var datasets = await _surreal.QueryAppDbAsync<SurrealDatasetHeader>($$"""
            SELECT
                record::id(id)                                                                               AS id,
                name,
                description,
                IF owner_group_id IS NOT NONE THEN record::id(owner_group_id) ELSE NONE END                 AS owner_group_id,
                IF owner_group_id.owner_id IS NOT NONE THEN record::id(owner_group_id.owner_id) ELSE NONE END AS group_owner_id
            FROM datasets WHERE id = datasets:{{datasetId}} LIMIT 1;
            """);

        var dataset = datasets.FirstOrDefault();
        if (dataset == null) return null;

        // Security check
        bool canEdit = currentUserRole == "Admin";
        if (!canEdit && dataset.OwnerGroupId.HasValue)
        {
            bool isOwner = dataset.GroupOwnerId == currentUserId;
            if (isOwner)
            {
                canEdit = true;
            }
            else
            {
                var membership = await _surreal.QueryAppDbAsync<SurrealCount>($$"""
                    SELECT count() AS count
                    FROM virtual_group_members
                    WHERE group_id = virtual_groups:{{dataset.OwnerGroupId.Value}} AND user_id = users:{{currentUserId}}
                    GROUP ALL;
                    """);
                canEdit = (membership.FirstOrDefault()?.Count ?? 0) > 0;
            }
        }

        if (!canEdit) return null;

        var groups = await _surreal.QueryAppDbAsync<VirtualGroupDto>(
            "SELECT record::id(id) AS id, name FROM virtual_groups ORDER BY name;");

        return new EditDatasetDto
        {
            Id             = dataset.Id,
            Name           = dataset.Name,
            Description    = dataset.Description,
            OwnerGroupId   = dataset.OwnerGroupId,
            AvailableGroups = groups
        };
    }

    public async Task UpdateDatasetAsync(EditDatasetDto dto)
    {
        string ownerRef = dto.OwnerGroupId.HasValue && dto.OwnerGroupId.Value > 0
            ? $"virtual_groups:{dto.OwnerGroupId.Value}"
            : "NONE";

        await _surreal.ExecuteAppDbAsync($$"""
            UPDATE datasets:{{dto.Id}} SET
                name           = "{{Esc(dto.Name)}}",
                description    = "{{Esc(dto.Description)}}",
                owner_group_id = {{ownerRef}};
            """);
    }

    public async Task AddReportAsync(int datasetId, string name, string url)
    {
        // Insert the report record and link it to the dataset
        var raw = await _surreal.QueryAppDbAsync($$"""
            INSERT INTO reports { name: "{{Esc(name)}}", url: "{{Esc(url)}}" } RETURN id;
            """);

        // Extract the new report ID from the response
        int? reportId = ExtractInsertedId(raw, "reports");
        if (reportId.HasValue)
        {
            await _surreal.ExecuteAppDbAsync($$"""
                INSERT INTO report_datasets {
                    dataset_id: datasets:{{datasetId}},
                    report_id:  reports:{{reportId.Value}}
                };
                """);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /// <summary>Extracts the integer ID from a SurrealDB INSERT RETURN id response.</summary>
    private static int? ExtractInsertedId(string raw, string tableName)
    {
        try
        {
            using var doc = System.Text.Json.JsonDocument.Parse(raw);
            var root = doc.RootElement;
            if (root.ValueKind != System.Text.Json.JsonValueKind.Array || root.GetArrayLength() == 0) return null;
            var result = root[0].GetProperty("result");
            var idEl   = result.ValueKind == System.Text.Json.JsonValueKind.Array
                ? result[0].GetProperty("id")
                : result.GetProperty("id");
            var idStr  = idEl.GetString() ?? "";
            var colon  = idStr.IndexOf(':');
            if (colon >= 0 && int.TryParse(idStr[(colon + 1)..], out var id)) return id;
        }
        catch { /* swallow */ }
        return null;
    }

    private static string Esc(string? s) =>
        (s ?? "").Replace("\\", "\\\\").Replace("\"", "\\\"");

    // ── SurrealDB response models ─────────────────────────────────────────────

    private class SurrealDatasetHeader
    {
        public int     Id           { get; set; }
        public string  Name         { get; set; } = "";
        public string  Type         { get; set; } = "";
        public string? Description  { get; set; }
        public int?    OwnerGroupId { get; set; }
        public int?    GroupOwnerId { get; set; }
        public string? GroupName    { get; set; }
    }

    private class SurrealReportLink
    {
        public int     Id   { get; set; }
        public string? Name { get; set; }
        public string? Url  { get; set; }
    }

    private class SurrealRequestDetail
    {
        public int       Id            { get; set; }
        public string    Status        { get; set; } = "";
        public int?      PolicyGroupId { get; set; }
        public string?   PolicyName    { get; set; }
        public string?   ReviewerName  { get; set; }
        public DateTime  CreatedAt     { get; set; }
    }

    private class SurrealGroupOwner { public string? Name { get; set; } }
    private class SurrealCount      { public int Count    { get; set; } }
}
