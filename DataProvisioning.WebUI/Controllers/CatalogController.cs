using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using System.Threading.Tasks;
using DataProvisioning.Application.Interfaces;

namespace DataProvisioning.WebUI.Controllers;

[Authorize]
public class CatalogController : Controller
{
    private readonly ICatalogService _catalogService;

    public CatalogController(ICatalogService catalogService)
    {
        _catalogService = catalogService;
    }

    public async Task<IActionResult> Index(string? q)
    {
        // Extract authenticated user ID from Claims
        int currentUserId = int.Parse(User.FindFirst(System.Security.Claims.ClaimTypes.NameIdentifier)?.Value ?? "0");
        var userRole = User.FindFirst(System.Security.Claims.ClaimTypes.Role)?.Value ?? "User";
        
        ViewBag.SearchQuery = q;
        ViewBag.CurrentUserId = currentUserId;
        ViewBag.UserRole = userRole;

        var catalog = await _catalogService.GetCatalogAsync(currentUserId, q);
        return View(catalog);
    }
}
