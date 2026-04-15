using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.AspNetCore.Authentication.Negotiate;
using SurrealDb.Net;
using DataProvisioning.Infrastructure.Data;

var builder = WebApplication.CreateBuilder(args);

// Add services to the container.
var surrealEndpoint = builder.Configuration.GetSection("SurrealDb").GetValue<string>("Endpoint");
if (!string.IsNullOrEmpty(surrealEndpoint))
{
    builder.Services.AddSurreal(surrealEndpoint);
}

builder.Services.AddScoped<DataProvisioning.Application.Interfaces.ICatalogService, DataProvisioning.Application.Services.CatalogService>();
builder.Services.AddScoped<DataProvisioning.Application.Interfaces.IDatasetDetailsService, DataProvisioning.Application.Services.DatasetDetailsService>();
builder.Services.AddScoped<DataProvisioning.Application.Interfaces.IAccessRequestService, DataProvisioning.Application.Services.AccessRequestService>();
builder.Services.AddScoped<DataProvisioning.Application.Interfaces.IAdministrationService, DataProvisioning.Application.Services.AdministrationService>();

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


