using System.Collections.Generic;
using System.Threading.Tasks;
using DataProvisioning.Application.DTOs;

namespace DataProvisioning.Application.Interfaces;

public interface IAccessRequestService
{
    Task<List<MyRequestDto>> GetMyRequestsAsync(int userId);
    Task SubmitRequestAsync(int userId, SubmitRequestDto requestDto);
    Task CancelOrRemoveRequestAsync(int requestId, int userId);
    
    Task ProcessRequestAsync(int requestId, int adminId, string action, int? policyGroupId);
    
    Task<ManageAccessViewModel> GetManageAccessDashboardAsync(int userId, string role);

    // --- Policy Management ---
    Task<ManagePolicyViewModel?> GetManagePolicyViewModelAsync(int datasetId, int currentUserId, string role);
    Task CreatePolicyGroupAsync(int datasetId, string name, string description, int? ownerId);
    Task AddPolicyConditionAsync(int policyGroupId, string columnName, string op, string value);
    Task DeletePolicyConditionAsync(int conditionId);
    Task TogglePolicyColumnVisibilityAsync(int policyGroupId, string columnName, bool isVisible);
}
