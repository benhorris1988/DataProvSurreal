using System.Collections.Generic;
using System.Security.Claims;
using System.Threading.Tasks;
using Microsoft.AspNetCore.Authentication;
using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.AspNetCore.Mvc;
using DataProvisioning.Application.DTOs;
using DataProvisioning.Application.Interfaces;

namespace DataProvisioning.WebUI.Controllers;

public class LoginController : Controller
{
    private readonly ISurrealDbService _surreal;
    private readonly bool _testMode;

    public LoginController(ISurrealDbService surreal, Microsoft.Extensions.Configuration.IConfiguration configuration)
    {
        _surreal   = surreal;
        _testMode  = configuration.GetValue<bool>("TestMode");
    }

    [HttpGet]
    public async Task<IActionResult> Index(string returnUrl = "/")
    {
        if (!_testMode)
            return RedirectToAction("Index", "Catalog");

        ViewData["ReturnUrl"] = returnUrl;

        var users = await _surreal.QueryAppDbAsync<UserDto>(
            "SELECT record::id(id) AS id, name, email, role FROM users WHERE type::is_int(record::id(id)) ORDER BY name;");

        return View(users);
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Authenticate(string email, string returnUrl = "/")
    {
        if (!_testMode)
            return RedirectToAction("Index", "Catalog");

        var users = await _surreal.QueryAppDbAsync<UserDto>(
            $"SELECT record::id(id) AS id, name, email, role FROM users WHERE email = \"{Esc(email)}\" AND type::is_int(record::id(id)) LIMIT 1;");

        var user = users.FirstOrDefault();
        if (user == null)
        {
            TempData["ErrorMessage"] = "User not found in the local database.";
            return RedirectToAction("Index", new { returnUrl });
        }

        var claims = new List<Claim>
        {
            new Claim(ClaimTypes.NameIdentifier, user.Id.ToString()),
            new Claim(ClaimTypes.Name,           user.Email),
            new Claim(ClaimTypes.GivenName,      user.Name),
            new Claim(ClaimTypes.Role,           user.Role)
        };

        var identity  = new ClaimsIdentity(claims, CookieAuthenticationDefaults.AuthenticationScheme);
        var principal = new ClaimsPrincipal(identity);

        await HttpContext.SignInAsync(
            CookieAuthenticationDefaults.AuthenticationScheme,
            principal,
            new AuthenticationProperties { IsPersistent = true });

        return Url.IsLocalUrl(returnUrl)
            ? LocalRedirect(returnUrl)
            : RedirectToAction("Index", "Catalog");
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Logout()
    {
        if (_testMode)
            await HttpContext.SignOutAsync(CookieAuthenticationDefaults.AuthenticationScheme);

        return RedirectToAction("Index", "Login");
    }

    private static string Esc(string? s) =>
        (s ?? "").Replace("\\", "\\\\").Replace("\"", "\\\"");
}
