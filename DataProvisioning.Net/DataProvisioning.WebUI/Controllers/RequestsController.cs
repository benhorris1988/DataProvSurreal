using System.Threading.Tasks;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using DataProvisioning.Application.Interfaces;
using DataProvisioning.Infrastructure.Data;
using Microsoft.EntityFrameworkCore;

namespace DataProvisioning.WebUI.Controllers;

[Authorize]
public class RequestsController : Controller
{
    private readonly IAccessRequestService _requestService;
    private readonly IApplicationDbContext _context;

    public RequestsController(IAccessRequestService requestService, IApplicationDbContext context)
    {
        _requestService = requestService;
        _context = context;
    }

    [HttpGet]
    public async Task<IActionResult> Index()
    {
        int currentUserId = int.Parse(User.FindFirst(System.Security.Claims.ClaimTypes.NameIdentifier)?.Value ?? "0");

        var requests = await _requestService.GetMyRequestsAsync(currentUserId);
        return View(requests);
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> CancelRequest(int requestId)
    {
        int currentUserId = int.Parse(User.FindFirst(System.Security.Claims.ClaimTypes.NameIdentifier)?.Value ?? "0");

        await _requestService.CancelOrRemoveRequestAsync(requestId, currentUserId);
        
        TempData["SuccessMessage"] = "Request successfully cancelled.";
        return RedirectToAction(nameof(Index));
    }
}
