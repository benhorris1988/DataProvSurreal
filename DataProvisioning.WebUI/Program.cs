using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.AspNetCore.Authentication.Negotiate;
using DataProvisioning.Application.Interfaces;
using DataProvisioning.Infrastructure.Data;

var builder = WebApplication.CreateBuilder(args);

// ── Application Services ───────────────────────────────────────────────────
builder.Services.AddScoped<ICatalogService,        DataProvisioning.Application.Services.CatalogService>();
builder.Services.AddScoped<IDatasetDetailsService, DataProvisioning.Application.Services.DatasetDetailsService>();
builder.Services.AddScoped<IAccessRequestService,  DataProvisioning.Application.Services.AccessRequestService>();
builder.Services.AddScoped<IAdministrationService, DataProvisioning.Application.Services.AdministrationService>();

// ── SurrealDB — typed HttpClient backed service ────────────────────────────
builder.Services.AddHttpClient<ISurrealDbService, SurrealDbService>(client =>
{
    var endpoint = builder.Configuration["SurrealDb:Endpoint"] ?? "http://localhost:8000";
    client.BaseAddress = new Uri(endpoint);
});

var testMode = builder.Configuration.GetValue<bool>("TestMode");

if (testMode)
{
    builder.Services.AddAuthentication(CookieAuthenticationDefaults.AuthenticationScheme)
        .AddCookie(options =>
        {
            options.LoginPath = "/Login";
            options.AccessDeniedPath = "/Login/AccessDenied";
        });
}
else
{
    builder.Services.AddAuthentication(NegotiateDefaults.AuthenticationScheme)
        .AddNegotiate();
}

builder.Services.AddControllersWithViews();

var app = builder.Build();

// Configure the HTTP request pipeline.
if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Home/Error");
    // The default HSTS value is 30 days. You may want to change this for production scenarios, see https://aka.ms/aspnetcore-hsts.
    app.UseHsts();
}

app.UseHttpsRedirection();
app.UseRouting();

app.UseAuthentication();

app.UseMiddleware<DataProvisioning.WebUI.Middlewares.UserProvisioningMiddleware>();

app.UseAuthorization();

app.UseStaticFiles();

app.MapControllerRoute(
    name: "default",
    pattern: "{controller=Catalog}/{action=Index}/{id?}");

app.Run();


