using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using DataProvisioning.Application.DTOs;
using DataProvisioning.Application.Interfaces;

namespace DataProvisioning.Application.Services;

public class AccessRequestService : IAccessRequestService
{
    private readonly ISurrealDbService _surreal;

    public AccessRequestService(ISurrealDbService surreal)
    {
        _surreal = surreal;
    }

    // ── My Requests ───────────────────────────────────────────────────────────

    public async Task<List<MyRequestDto>> GetMyRequestsAsync(int userId)
    {
        var requests = await _surreal.QueryAppDbAsync<SurrealMyRequest>($$"""
            SELECT
                record::id(id)                                                                                        AS id,
                record::id(dataset_id)                                                                                AS dataset_id,
                dataset_id.name                                                                                       AS dataset_name,
                dataset_id.owner_group_id.owner_id.name                                                              AS owner_name,
                dataset_id.owner_group_id.name                                                                        AS group_name,
                IF dataset_id.owner_group_id IS NOT NONE THEN record::id(dataset_id.owner_group_id) ELSE NONE END    AS owner_group_id,
                dataset_id.owner_group_id.owner_id.name                                                              AS group_owner_name,
                status,
                reviewed_by.name                                                                                      AS reviewer_name,
                created_at
            FROM access_requests
            WHERE user_id = users:{{userId}}
            ORDER BY created_at DESC;
            """);

        // Global approvers fetched once (only needed for pending requests)
        List<DatasetApproverDto>? globalApprovers = null;

        var result = new List<MyRequestDto>();

        foreach (var req in requests)
        {
            var dto = new MyRequestDto
            {
                Id           = req.Id,
                DatasetId    = req.DatasetId,
                DatasetName  = req.DatasetName,
                OwnerName    = req.OwnerName,
                GroupName    = req.GroupName,
                Status       = req.Status,
                ReviewerName = req.ReviewerName,
                CreatedAt    = req.CreatedAt
            };

            if (req.Status == "Pending")
            {
                globalApprovers ??= await _surreal.QueryAppDbAsync<DatasetApproverDto>(
                    "SELECT name, role AS role_type FROM users WHERE role IN ['Admin', 'IAO'];");

                dto.GlobalApprovers = globalApprovers;

                if (req.OwnerGroupId.HasValue)
                {
                    if (!string.IsNullOrEmpty(req.GroupOwnerName))
                        dto.PendingApprovers.Add(new DatasetApproverDto { Name = req.GroupOwnerName, RoleType = "Owner" });

                    var members = await _surreal.QueryAppDbAsync<DatasetApproverDto>($$"""
                        SELECT user_id.name AS name, 'Member' AS role_type
                        FROM virtual_group_members
                        WHERE group_id = virtual_groups:{{req.OwnerGroupId.Value}};
                        """);

                    dto.PendingApprovers.AddRange(members);
                    dto.PendingApprovers = dto.PendingApprovers
                        .OrderBy(a => a.RoleType == "Owner" ? 0 : 1)
                        .ThenBy(a => a.Name)
                        .ToList();
                }
            }

            result.Add(dto);
        }

        return result;
    }

    // ── Submit / Cancel ───────────────────────────────────────────────────────

    public async Task SubmitRequestAsync(int userId, SubmitRequestDto requestDto)
    {
        string policyRef = requestDto.PolicyGroupId.HasValue
            ? $"asset_policy_groups:{requestDto.PolicyGroupId.Value}"
            : "NONE";

        await _surreal.ExecuteAppDbAsync($$"""
            INSERT INTO access_requests {
                user_id:         users:{{userId}},
                dataset_id:      datasets:{{requestDto.DatasetId}},
                justification:   "{{Esc(requestDto.Justification)}}",
                policy_group_id: {{policyRef}},
                status:          'Pending',
                created_at:      time::now()
            };
            """);
    }

    public async Task CancelOrRemoveRequestAsync(int requestId, int userId)
    {
        await _surreal.ExecuteAppDbAsync($$"""
            DELETE access_requests
            WHERE id = access_requests:{{requestId}} AND user_id = users:{{userId}};
            """);
    }

    // ── Process (Approve / Reject) ────────────────────────────────────────────

    public async Task ProcessRequestAsync(int requestId, int adminId, string action, int? policyGroupId)
    {
        // Load the request with user email and dataset name
        var requests = await _surreal.QueryAppDbAsync<SurrealRequestFull>($$"""
            SELECT
                record::id(id)         AS id,
                record::id(user_id)    AS user_id,
                user_id.email          AS user_email,
                record::id(dataset_id) AS dataset_id,
                dataset_id.name        AS dataset_name
            FROM access_requests WHERE id = access_requests:{{requestId}} LIMIT 1;
            """);

        var request = requests.FirstOrDefault();
        if (request == null) return;

        if (action == "approve")
        {
            string policyRef = policyGroupId.HasValue
                ? $"asset_policy_groups:{policyGroupId.Value}"
                : "NONE";

            // Update status in AppDB
            await _surreal.ExecuteAppDbAsync($$"""
                UPDATE access_requests:{{requestId}} SET
                    status          = 'Approved',
                    reviewed_by     = users:{{adminId}},
                    reviewed_at     = time::now(),
                    policy_group_id = {{policyRef}};
                """);

            // Build RLS filter JSON from policy conditions
            string? rlsFilters = null;
            if (policyGroupId.HasValue)
            {
                var conditions = await _surreal.QueryAppDbAsync<SurrealCondition>($$"""
                    SELECT column_name, value
                    FROM asset_policy_conditions
                    WHERE policy_group_id = asset_policy_groups:{{policyGroupId.Value}};
                    """);

                if (conditions.Any())
                {
                    var pairs = conditions
                        .GroupBy(c => c.ColumnName)
                        .ToDictionary(g => g.Key, g => g.First().Value);
                    rlsFilters = System.Text.Json.JsonSerializer.Serialize(pairs);
                }
            }

            // Create the has_access graph edge in AppDB
            await _surreal.GrantDatasetAccessAsync(
                userId:          request.UserId,
                datasetId:       request.DatasetId,
                grantedByUserId: adminId,
                rlsFilters:      rlsFilters,
                policyGroupId:   policyGroupId);

            // Sync PermissionsMap in DataWarehouse (root access, drives DW row-level security)
            var deleteExisting = $$"""
                DELETE PermissionsMap
                WHERE UserID    = "{{Esc(request.UserEmail)}}"
                  AND TableName = "{{Esc(request.DatasetName)}}";
                """;
            await _surreal.ExecuteDwAsync(deleteExisting);

            if (string.IsNullOrEmpty(rlsFilters))
            {
                // No filter — unrestricted access to all rows
                await _surreal.ExecuteDwAsync($$"""
                    INSERT INTO PermissionsMap {
                        UserID:          "{{Esc(request.UserEmail)}}",
                        TableName:       "{{Esc(request.DatasetName)}}",
                        ColumnID:        NONE,
                        AuthorizedValue: NONE,
                        CreatedDate:     time::now()
                    };
                    """);
            }
            else
            {
                // One PermissionsMap row per RLS condition
                var conditions = await _surreal.QueryAppDbAsync<SurrealCondition>($$"""
                    SELECT column_name, value
                    FROM asset_policy_conditions
                    WHERE policy_group_id = asset_policy_groups:{{policyGroupId!.Value}};
                    """);

                foreach (var condition in conditions)
                {
                    await _surreal.ExecuteDwAsync($$"""
                        INSERT INTO PermissionsMap {
                            UserID:          "{{Esc(request.UserEmail)}}",
                            TableName:       "{{Esc(request.DatasetName)}}",
                            ColumnID:        "{{Esc(condition.ColumnName)}}",
                            AuthorizedValue: "{{Esc(condition.Value)}}",
                            CreatedDate:     time::now()
                        };
                        """);
                }
            }
        }
        else if (action == "reject")
        {
            await _surreal.ExecuteAppDbAsync($$"""
                UPDATE access_requests:{{requestId}} SET
                    status      = 'Rejected',
                    reviewed_by = users:{{adminId}},
                    reviewed_at = time::now();
                """);
        }
    }

    // ── Manage Access Dashboard ───────────────────────────────────────────────

    public async Task<ManageAccessViewModel> GetManageAccessDashboardAsync(int userId, string role)
    {
        var vm = new ManageAccessViewModel();

        // 1. My Datasets (datasets whose owner group I own)
        var myDatasets = await _surreal.QueryAppDbAsync<SurrealManagedDataset>($$"""
            SELECT
                record::id(id) AS id,
                name,
                type,
                description,
                array::len((SELECT id FROM asset_policy_groups WHERE dataset_id = $parent.id))                                                                  AS policy_count,
                array::len(array::distinct((SELECT VALUE user_id FROM access_requests WHERE dataset_id = $parent.id AND status = 'Approved'))) AS user_access_count
            FROM datasets
            WHERE owner_group_id.owner_id = users:{{userId}}
            ORDER BY name;
            """);

        vm.MyDatasets = myDatasets.Select(d => new ManagedDatasetDto
        {
            Id                        = d.Id,
            Name                      = d.Name,
            Type                      = d.Type,
            Description               = d.Description ?? "",
            PolicyCount               = d.PolicyCount,
            UserAccessCount           = d.UserAccessCount,
            IsMissingFromDataWarehouse = false
        }).ToList();

        // 2. Build IAO group filter (if applicable)
        string roleFilter = "";
        if (role == "IAO")
        {
            var myGroups = await _surreal.QueryAppDbAsync<SurrealId>($$"""
                SELECT record::id(id) AS id FROM virtual_groups
                WHERE owner_id = users:{{userId}}
                   OR id IN (SELECT VALUE group_id FROM virtual_group_members WHERE user_id = users:{{userId}});
                """);

            if (!myGroups.Any()) return vm;   // No managed groups — nothing to show

            var refs = string.Join(", ", myGroups.Select(g => $"virtual_groups:{g.Id}"));
            roleFilter = $"AND dataset_id.owner_group_id IN [{refs}]";
        }

        // 3. Pending Requests
        var pendingRows = await _surreal.QueryAppDbAsync<SurrealPendingRequest>($$"""
            SELECT
                record::id(id)                                                                                       AS id,
                record::id(dataset_id)                                                                               AS dataset_id,
                user_id.name                                                                                         AS requestor_name,
                dataset_id.name                                                                                      AS dataset_name,
                created_at,
                justification,
                requested_rls_filters,
                IF policy_group_id IS NOT NONE THEN record::id(policy_group_id) ELSE NONE END                       AS selected_policy_group_id,
                IF dataset_id.owner_group_id IS NOT NONE THEN record::id(dataset_id.owner_group_id) ELSE NONE END   AS owner_group_id,
                dataset_id.owner_group_id.owner_id.name                                                             AS group_owner_name
            FROM access_requests
            WHERE status = 'Pending' {{roleFilter}}
            ORDER BY created_at DESC;
            """);

        // Batch-fetch available policies for all unique datasets in pending list
        var datasetIds = pendingRows.Select(p => p.DatasetId).Distinct().ToList();
        var policiesByDataset = new Dictionary<int, List<PolicyGroupOptionDto>>();
        if (datasetIds.Any())
        {
            var datasetRefs   = string.Join(", ", datasetIds.Select(id => $"datasets:{id}"));
            var policyOptions = await _surreal.QueryAppDbAsync<SurrealPolicyOption>(
                $"SELECT record::id(id) AS id, name, record::id(dataset_id) AS dataset_id FROM asset_policy_groups WHERE dataset_id IN [{datasetRefs}];");

            policiesByDataset = policyOptions
                .GroupBy(p => p.DatasetId)
                .ToDictionary(
                    g => g.Key,
                    g => g.Select(p => new PolicyGroupOptionDto { Id = p.Id, Name = p.Name }).ToList());
        }

        foreach (var req in pendingRows)
        {
            var dto = new PendingRequestDto
            {
                Id                    = req.Id,
                DatasetId             = req.DatasetId,
                RequestorName         = req.RequestorName,
                DatasetName           = req.DatasetName,
                CreatedAt             = req.CreatedAt,
                Justification         = req.Justification ?? "",
                RequestedFilters      = req.RequestedRlsFilters,
                SelectedPolicyGroupId = req.SelectedPolicyGroupId,
                AvailablePolicies     = policiesByDataset.GetValueOrDefault(req.DatasetId) ?? new()
            };

            if (req.OwnerGroupId.HasValue)
            {
                if (!string.IsNullOrEmpty(req.GroupOwnerName))
                    dto.GroupApprovers.Add(new DatasetApproverDto { Name = req.GroupOwnerName, RoleType = "Owner" });

                var members = await _surreal.QueryAppDbAsync<DatasetApproverDto>($$"""
                    SELECT user_id.name AS name, 'Member' AS role_type
                    FROM virtual_group_members
                    WHERE group_id = virtual_groups:{{req.OwnerGroupId.Value}};
                    """);

                dto.GroupApprovers.AddRange(members);
                dto.GroupApprovers = dto.GroupApprovers
                    .OrderBy(a => a.RoleType == "Owner" ? 0 : 1)
                    .ThenBy(a => a.Name)
                    .ToList();
            }

            vm.PendingRequests.Add(dto);
        }

        // 4. Recent Decisions (history)
        vm.RecentDecisions = await _surreal.QueryAppDbAsync<RequestHistoryDto>($$"""
            SELECT
                reviewed_at                  AS reviewed_at,
                user_id.name                 AS requestor_name,
                dataset_id.name              AS dataset_name,
                status,
                policy_group_id.name         AS applied_policy_name
            FROM access_requests
            WHERE status != 'Pending' {{roleFilter}}
            ORDER BY reviewed_at DESC
            LIMIT 10;
            """);

        // Fallback: if reviewed_at is default (NONE), substitute UtcNow
        foreach (var d in vm.RecentDecisions.Where(d => d.ReviewedAt == default))
            d.ReviewedAt = DateTime.UtcNow;

        return vm;
    }

    // ── Policy Management ─────────────────────────────────────────────────────

    public async Task<ManagePolicyViewModel?> GetManagePolicyViewModelAsync(int datasetId, int currentUserId, string role)
    {
        // Dataset header + security check
        var datasets = await _surreal.QueryAppDbAsync<SurrealDatasetHeader>($$"""
            SELECT
                record::id(id)                                                                               AS id,
                name,
                IF owner_group_id IS NOT NONE THEN record::id(owner_group_id) ELSE NONE END                 AS owner_group_id,
                IF owner_group_id.owner_id IS NOT NONE THEN record::id(owner_group_id.owner_id) ELSE NONE END AS group_owner_id
            FROM datasets WHERE id = datasets:{{datasetId}} LIMIT 1;
            """);

        var dataset = datasets.FirstOrDefault();
        if (dataset == null) return null;

        bool canManage = role == "Admin";
        if (!canManage && dataset.OwnerGroupId.HasValue)
        {
            if (dataset.GroupOwnerId == currentUserId)
            {
                canManage = true;
            }
            else
            {
                var membership = await _surreal.QueryAppDbAsync<SurrealCount>($$"""
                    SELECT count() AS count
                    FROM virtual_group_members
                    WHERE group_id = virtual_groups:{{dataset.OwnerGroupId.Value}} AND user_id = users:{{currentUserId}}
                    GROUP ALL;
                    """);
                canManage = (membership.FirstOrDefault()?.Count ?? 0) > 0;
            }
        }

        if (!canManage) return null;

        var vm = new ManagePolicyViewModel
        {
            DatasetId   = dataset.Id,
            DatasetName = dataset.Name
        };

        // Columns
        vm.DatasetColumns = await _surreal.QueryAppDbAsync<DatasetColumnDto>($$"""
            SELECT record::id(id) AS id, name, data_type, definition, is_pii, sample_data
            FROM columns WHERE dataset_id = datasets:{{datasetId}};
            """);

        // Available owners (IAO / Admin)
        vm.AvailableOwners = await _surreal.QueryAppDbAsync<UserDto>(
            "SELECT record::id(id) AS id, name FROM users WHERE role IN ['Admin', 'IAO'] AND type::is_int(record::id(id));");

        // Policy groups with conditions, hidden columns, authorized users (single query)
        vm.PolicyGroups = await _surreal.QueryAppDbAsync<PolicyGroupDetailDto>($$"""
            SELECT
                record::id(id)  AS id,
                name,
                description,
                owner_id.name   AS owner_name,
                (SELECT record::id(id) AS id, column_name, operator, value
                 FROM asset_policy_conditions
                 WHERE policy_group_id = $parent.id)           AS conditions,
                (SELECT column_name, is_hidden
                 FROM asset_policy_columns
                 WHERE policy_group_id = $parent.id)           AS columns,
                (SELECT user_id.name AS name, user_id.email AS email, reviewed_at
                 FROM access_requests
                 WHERE policy_group_id = $parent.id AND status = 'Approved') AS authorized_users
            FROM asset_policy_groups WHERE dataset_id = datasets:{{datasetId}};
            """);

        // Fill in "Unassigned" for any null owner names
        foreach (var pg in vm.PolicyGroups)
            pg.OwnerName ??= "Unassigned";

        return vm;
    }

    public async Task CreatePolicyGroupAsync(int datasetId, string name, string description, int? ownerId)
    {
        string ownerRef = ownerId.HasValue ? $"users:{ownerId.Value}" : "NONE";

        await _surreal.ExecuteAppDbAsync($$"""
            INSERT INTO asset_policy_groups {
                dataset_id:  datasets:{{datasetId}},
                name:        "{{Esc(name)}}",
                description: "{{Esc(description)}}",
                owner_id:    {{ownerRef}},
                created_at:  time::now()
            };
            """);
    }

    public async Task AddPolicyConditionAsync(int policyGroupId, string columnName, string op, string value)
    {
        await _surreal.ExecuteAppDbAsync($$"""
            INSERT INTO asset_policy_conditions {
                policy_group_id: asset_policy_groups:{{policyGroupId}},
                column_name:     "{{Esc(columnName)}}",
                operator:        "{{Esc(op)}}",
                value:           "{{Esc(value)}}"
            };
            """);
    }

    public async Task DeletePolicyConditionAsync(int conditionId)
    {
        await _surreal.ExecuteAppDbAsync(
            $"DELETE asset_policy_conditions WHERE id = asset_policy_conditions:{conditionId};");
    }

    public async Task TogglePolicyColumnVisibilityAsync(int policyGroupId, string columnName, bool isVisible)
    {
        string hiddenVal = isVisible ? "false" : "true";

        var existing = await _surreal.QueryAppDbAsync<SurrealId>($$"""
            SELECT record::id(id) AS id
            FROM asset_policy_columns
            WHERE policy_group_id = asset_policy_groups:{{policyGroupId}}
              AND column_name = "{{Esc(columnName)}}"
            LIMIT 1;
            """);

        if (existing.Any())
        {
            await _surreal.ExecuteAppDbAsync($$"""
                UPDATE asset_policy_columns:{{existing[0].Id}} SET is_hidden = {{hiddenVal}};
                """);
        }
        else
        {
            await _surreal.ExecuteAppDbAsync($$"""
                INSERT INTO asset_policy_columns {
                    policy_group_id: asset_policy_groups:{{policyGroupId}},
                    column_name:     "{{Esc(columnName)}}",
                    is_hidden:       {{hiddenVal}}
                };
                """);
        }
    }

    // ── SurrealDB response models ─────────────────────────────────────────────

    private class SurrealMyRequest
    {
        public int      Id             { get; set; }
        public int      DatasetId      { get; set; }
        public string   DatasetName    { get; set; } = "";
        public string?  OwnerName      { get; set; }
        public string?  GroupName      { get; set; }
        public int?     OwnerGroupId   { get; set; }
        public string?  GroupOwnerName { get; set; }
        public string   Status         { get; set; } = "";
        public string?  ReviewerName   { get; set; }
        public DateTime CreatedAt      { get; set; }
    }

    private class SurrealRequestFull
    {
        public int    Id          { get; set; }
        public int    UserId      { get; set; }
        public string UserEmail   { get; set; } = "";
        public int    DatasetId   { get; set; }
        public string DatasetName { get; set; } = "";
    }

    private class SurrealCondition
    {
        public string ColumnName { get; set; } = "";
        public string Value      { get; set; } = "";
    }

    private class SurrealManagedDataset
    {
        public int    Id              { get; set; }
        public string Name            { get; set; } = "";
        public string Type            { get; set; } = "";
        public string? Description    { get; set; }
        public int    PolicyCount     { get; set; }
        public int    UserAccessCount { get; set; }
    }

    private class SurrealPendingRequest
    {
        public int      Id                    { get; set; }
        public int      DatasetId             { get; set; }
        public string   RequestorName         { get; set; } = "";
        public string   DatasetName           { get; set; } = "";
        public DateTime CreatedAt             { get; set; }
        public string?  Justification         { get; set; }
        public string?  RequestedRlsFilters   { get; set; }
        public int?     SelectedPolicyGroupId { get; set; }
        public int?     OwnerGroupId          { get; set; }
        public string?  GroupOwnerName        { get; set; }
    }

    private class SurrealPolicyOption
    {
        public int    Id        { get; set; }
        public string Name      { get; set; } = "";
        public int    DatasetId { get; set; }
    }

    private class SurrealDatasetHeader
    {
        public int    Id           { get; set; }
        public string Name         { get; set; } = "";
        public int?   OwnerGroupId { get; set; }
        public int?   GroupOwnerId { get; set; }
    }

    private class SurrealId    { public int Id    { get; set; } }
    private class SurrealCount { public int Count { get; set; } }

    private static string Esc(string? s) =>
        (s ?? "").Replace("\\", "\\\\").Replace("\"", "\\\"");
}
