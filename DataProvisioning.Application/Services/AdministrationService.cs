using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using DataProvisioning.Application.DTOs;
using DataProvisioning.Application.Interfaces;

namespace DataProvisioning.Application.Services;

public class AdministrationService : IAdministrationService
{
    private readonly ISurrealDbService _surreal;

    public AdministrationService(ISurrealDbService surreal)
    {
        _surreal = surreal;
    }

    // ── Users ─────────────────────────────────────────────────────────────────

    public Task<List<UserDto>> GetUsersAsync() =>
        _surreal.QueryAppDbAsync<UserDto>(
            "SELECT record::id(id) AS id, name, email, role FROM users WHERE type::is_int(record::id(id)) ORDER BY name;");

    public async Task AddUserAsync(string name, string email, string roleStr)
    {
        // Determine next integer ID (max existing int ID + 1)
        var maxRows = await _surreal.QueryAppDbAsync<SurrealMaxId>(
            "SELECT math::max(record::id(id)) AS max_id FROM users WHERE type::is_int(record::id(id)) GROUP ALL;");
        var nextId = (maxRows.FirstOrDefault()?.MaxId ?? 0) + 1;

        await _surreal.ExecuteAppDbAsync($$"""
            INSERT INTO users:{{nextId}} {
                name:       "{{Esc(name)}}",
                email:      "{{Esc(email)}}",
                role:       "{{Esc(roleStr)}}",
                created_at: time::now()
            };
            """);
    }

    public async Task UpdateUserRoleAsync(int userId, string roleStr)
    {
        await _surreal.ExecuteAppDbAsync($$"""
            UPDATE users:{{userId}} SET role = "{{Esc(roleStr)}}";
            """);
    }

    public async Task UpdateUserNameAsync(int userId, string name)
    {
        await _surreal.ExecuteAppDbAsync($$"""
            UPDATE users:{{userId}} SET name = "{{Esc(name)}}";
            """);
    }

    // ── Groups ────────────────────────────────────────────────────────────────

    public async Task<List<VirtualGroupDto>> GetGroupsAsync(int currentUserId, string role)
    {
        string whereClause = role == "Admin"
            ? ""
            : $$"""WHERE owner_id = users:{{currentUserId}} OR id IN (SELECT VALUE group_id FROM virtual_group_members WHERE user_id = users:{{currentUserId}})""";

        var groups = await _surreal.QueryAppDbAsync<SurrealGroupFull>($$"""
            SELECT
                record::id(id)   AS id,
                name,
                description,
                record::id(owner_id) AS owner_id,
                owner_id.name        AS owner_name,
                (SELECT
                    record::id(user_id) AS user_id,
                    user_id.name        AS name,
                    user_id.email       AS email
                 FROM virtual_group_members
                 WHERE group_id = $parent.id
                 ORDER BY user_id.name)             AS members,
                (SELECT VALUE name FROM datasets WHERE owner_group_id = $parent.id ORDER BY name) AS controlled_datasets
            FROM virtual_groups
            {{whereClause}}
            ORDER BY name;
            """);

        return groups.Select(g => new VirtualGroupDto
        {
            Id                 = g.Id,
            Name               = g.Name,
            Description        = g.Description ?? "",
            OwnerId            = g.OwnerId,
            OwnerName          = g.OwnerName ?? "Unknown",
            IsOwner            = g.OwnerId == currentUserId,
            CanEdit            = g.OwnerId == currentUserId || role == "Admin",
            ControlledDatasets = g.ControlledDatasets,
            Members            = g.Members.Select(m => new GroupMemberDto
            {
                UserId = m.UserId,
                Name   = m.Name,
                Email  = m.Email
            }).ToList()
        }).ToList();
    }

    public async Task CreateGroupAsync(CreateGroupDto dto)
    {
        await _surreal.ExecuteAppDbAsync($$"""
            INSERT INTO virtual_groups {
                name:        "{{Esc(dto.Name)}}",
                description: "{{Esc(dto.Description)}}",
                owner_id:    users:{{dto.OwnerId}},
                created_at:  time::now()
            };
            """);
    }

    public async Task UpdateGroupAsync(int currentUserId, string roleStr, UpdateGroupDto dto)
    {
        if (roleStr == "Admin")
        {
            await _surreal.ExecuteAppDbAsync($$"""
                UPDATE virtual_groups:{{dto.Id}} SET
                    name        = "{{Esc(dto.Name)}}",
                    description = "{{Esc(dto.Description)}}",
                    owner_id    = users:{{dto.OwnerId}};
                """);
        }
        else
        {
            // Non-admin: only update if they own the group, and cannot change owner
            var check = await _surreal.QueryAppDbAsync<SurrealOwnerId>($$"""
                SELECT record::id(owner_id) AS owner_id FROM virtual_groups WHERE id = virtual_groups:{{dto.Id}} LIMIT 1;
                """);

            if (check.FirstOrDefault()?.OwnerId == currentUserId)
            {
                await _surreal.ExecuteAppDbAsync($$"""
                    UPDATE virtual_groups:{{dto.Id}} SET
                        name        = "{{Esc(dto.Name)}}",
                        description = "{{Esc(dto.Description)}}";
                    """);
            }
        }
    }

    public async Task<string?> AddGroupMemberAsync(int groupId, int userId)
    {
        // Check for duplicate
        var existing = await _surreal.QueryAppDbAsync<SurrealCount>($$"""
            SELECT count() AS count
            FROM virtual_group_members
            WHERE group_id = virtual_groups:{{groupId}} AND user_id = users:{{userId}}
            GROUP ALL;
            """);

        if ((existing.FirstOrDefault()?.Count ?? 0) > 0)
            return "User is already a member.";

        await _surreal.ExecuteAppDbAsync($$"""
            INSERT INTO virtual_group_members {
                group_id: virtual_groups:{{groupId}},
                user_id:  users:{{userId}},
                added_at: time::now()
            };
            """);

        return null;
    }

    public Task<List<UserDto>> GetPossibleOwnersAsync() =>
        _surreal.QueryAppDbAsync<UserDto>(
            "SELECT record::id(id) AS id, name FROM users WHERE role IN ['IAO', 'Admin'] AND type::is_int(record::id(id)) ORDER BY name;");

    public Task<List<UserDto>> GetAllUsersForDropdownAsync() =>
        _surreal.QueryAppDbAsync<UserDto>(
            "SELECT record::id(id) AS id, name, email FROM users WHERE type::is_int(record::id(id)) ORDER BY name;");

    // ── SurrealDB response models ─────────────────────────────────────────────

    private class SurrealGroupFull
    {
        public int                  Id                 { get; set; }
        public string               Name               { get; set; } = "";
        public string?              Description        { get; set; }
        public int                  OwnerId            { get; set; }
        public string?              OwnerName          { get; set; }
        public List<SurrealMember>  Members            { get; set; } = new();
        public List<string>         ControlledDatasets { get; set; } = new();
    }

    private class SurrealMember
    {
        public int    UserId { get; set; }
        public string Name   { get; set; } = "";
        public string Email  { get; set; } = "";
    }

    private class SurrealOwnerId { public int OwnerId { get; set; } }
    private class SurrealCount   { public int Count   { get; set; } }
    private class SurrealMaxId   { public int MaxId   { get; set; } }

    private static string Esc(string? s) =>
        (s ?? "").Replace("\\", "\\\\").Replace("\"", "\\\"");
}
