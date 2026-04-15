namespace DataProvisioning.WebUI.Middlewares;

using System.Security.Claims;
using System.Threading.Tasks;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using DataProvisioning.Application.Interfaces;

public class UserProvisioningMiddleware
{
    private readonly RequestDelegate _next;
    private readonly bool   _testMode;
    private readonly string _initialAdminConfig;

    public UserProvisioningMiddleware(RequestDelegate next, IConfiguration configuration)
    {
        _next               = next;
        _testMode           = configuration.GetValue<bool>("TestMode");
        _initialAdminConfig = configuration.GetValue<string>("InitialAdmin") ?? "";
    }

    public async Task InvokeAsync(HttpContext context)
    {
        if (context.User.Identity?.IsAuthenticated == true)
        {
            var surreal          = context.RequestServices.GetRequiredService<ISurrealDbService>();
            var usernameOrEmail  = context.User.Identity.Name;

            if (!string.IsNullOrEmpty(usernameOrEmail))
            {
                // Look up user in SurrealDB
                var users = await surreal.QueryAppDbAsync<SurrealUser>(
                    $"SELECT record::id(id) AS id, name, email, role FROM users WHERE email = \"{Esc(usernameOrEmail)}\" OR name = \"{Esc(usernameOrEmail)}\" LIMIT 1;");

                var user = users.FirstOrDefault();

                if (user == null)
                {
                    // Determine role for new user
                    bool isInitialAdminByConfig = !string.IsNullOrEmpty(_initialAdminConfig) &&
                        _initialAdminConfig.Equals(usernameOrEmail, StringComparison.OrdinalIgnoreCase);

                    bool isInitialAdminByTable = false;
                    if (!isInitialAdminByConfig)
                    {
                        var admins = await surreal.QueryAppDbAsync<SurrealUser>(
                            $"SELECT id FROM initial_admins WHERE string::lowercase(username) = string::lowercase(\"{Esc(usernameOrEmail)}\") LIMIT 1;");
                        isInitialAdminByTable = admins.Any();
                    }

                    string role = (isInitialAdminByConfig || isInitialAdminByTable) ? "Admin" : "User";

                    // Create the new user
                    await surreal.ExecuteAppDbAsync($$"""
                        INSERT INTO users {
                            name:       "{{Esc(usernameOrEmail)}}",
                            email:      "{{Esc(usernameOrEmail)}}",
                            role:       "{{role}}",
                            created_at: time::now()
                        };
                        """);

                    // Re-fetch to get the assigned ID
                    var newUsers = await surreal.QueryAppDbAsync<SurrealUser>(
                        $"SELECT record::id(id) AS id, name, email, role FROM users WHERE email = \"{Esc(usernameOrEmail)}\" LIMIT 1;");
                    user = newUsers.FirstOrDefault();
                }

                if (user != null && context.User.Identity is ClaimsIdentity claimsIdentity)
                {
                    if (!claimsIdentity.HasClaim(c => c.Type == ClaimTypes.Role))
                        claimsIdentity.AddClaim(new Claim(ClaimTypes.Role, user.Role));

                    if (!claimsIdentity.HasClaim(c => c.Type == ClaimTypes.NameIdentifier))
                        claimsIdentity.AddClaim(new Claim(ClaimTypes.NameIdentifier, user.Id.ToString()));

                    // Sign into SurrealDB and store the JWT for DataWarehouse queries
                    var jwt = await surreal.SignInAsync(user.Email);
                    if (jwt != null)
                        context.Items["SurrealJwt"] = jwt;
                }
            }
        }

        await _next(context);
    }

    private static string Esc(string? s) =>
        (s ?? "").Replace("\\", "\\\\").Replace("\"", "\\\"");

    private class SurrealUser
    {
        public int    Id    { get; set; }
        public string Name  { get; set; } = "";
        public string Email { get; set; } = "";
        public string Role  { get; set; } = "";
    }
}
