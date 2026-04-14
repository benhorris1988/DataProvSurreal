using System.Threading.Tasks;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using DataProvisioning.Application.Interfaces;

namespace DataProvisioning.WebUI.Controllers;

[Authorize(Roles = "Admin,IAO,IAA")]
public class ManageController : Controller
{
    private readonly IAccessRequestService _requestService;

    public ManageController(IAccessRequestService requestService)
    {
        _requestService = requestService;
    }

    [HttpGet]
    public async Task<IActionResult> Index()
    {
        // Extract authenticated user ID from Claims
        int currentUserId = int.Parse(User.FindFirst(System.Security.Claims.ClaimTypes.NameIdentifier)?.Value ?? "0");
        var userRole = User.FindFirst(System.Security.Claims.ClaimTypes.Role)?.Value ?? "User";

        var dashboard = await _requestService.GetManageAccessDashboardAsync(currentUserId, userRole);
        return View(dashboard);
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> ProcessRequest(int requestId, string action, int? policyGroupId)
    {
        int currentAdminId = int.Parse(User.FindFirst(System.Security.Claims.ClaimTypes.NameIdentifier)?.Value ?? "0");

        await _requestService.ProcessRequestAsync(requestId, currentAdminId, action, policyGroupId);

        TempData["SuccessMessage"] = action == "approve" ? "Request approved successfully." : "Request rejected.";
        return RedirectToAction(nameof(Index));
    }

    // --- Policy Management ---
    
    [HttpGet("Manage/Policy/{id}")]
    public async Task<IActionResult> Policy(int id)
    {
        int currentUserId = int.Parse(User.FindFirst(System.Security.Claims.ClaimTypes.NameIdentifier)?.Value ?? "0");
        var userRole = User.FindFirst(System.Security.Claims.ClaimTypes.Role)?.Value ?? "User";

        var vm = await _requestService.GetManagePolicyViewModelAsync(id, currentUserId, userRole);
        if (vm == null) return Forbid();

        return View(vm);
    }

    [HttpPost("Manage/Policy/CreateGroup")]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> CreatePolicyGroup(int datasetId, string name, string description, int? ownerId)
    {
        await _requestService.CreatePolicyGroupAsync(datasetId, name, description, ownerId);
        TempData["SuccessMessage"] = "Policy group created successfully.";
        return RedirectToAction("Policy", new { id = datasetId });
    }

    [HttpPost("Manage/Policy/AddCondition")]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> AddCondition(int datasetId, int policyGroupId, string column, string op, string value)
    {
        await _requestService.AddPolicyConditionAsync(policyGroupId, column, op, value);
        TempData["SuccessMessage"] = "Condition added.";
        return RedirectToAction("Policy", new { id = datasetId });
    }

    [HttpPost("Manage/Policy/DeleteCondition")]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> DeleteCondition(int datasetId, int conditionId)
    {
        await _requestService.DeletePolicyConditionAsync(conditionId);
        TempData["SuccessMessage"] = "Condition deleted.";
        return RedirectToAction("Policy", new { id = datasetId });
    }

    [HttpPost("Manage/Policy/ToggleCls")]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> ToggleCls(int datasetId, int policyGroupId, string column, bool isVisible)
    {
        await _requestService.TogglePolicyColumnVisibilityAsync(policyGroupId, column, isVisible);
        TempData["SuccessMessage"] = $"Column visibility updated.";
        return RedirectToAction("Policy", new { id = datasetId });
    }
}
