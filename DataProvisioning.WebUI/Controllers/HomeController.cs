using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Linq;
using System.Threading.Tasks;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using DataProvisioning.Application.Interfaces;
using DataProvisioning.WebUI.Models;

namespace DataProvisioning.WebUI.Controllers;

[Authorize]
public class HomeController : Controller
{
    private readonly ISurrealDbService _surreal;

    public HomeController(ISurrealDbService surreal)
    {
        _surreal = surreal;
    }

    public async Task<IActionResult> Index()
    {
        int    currentUserId = int.Parse(User.FindFirst(System.Security.Claims.ClaimTypes.NameIdentifier)?.Value ?? "0");
        string userName      = User.Identity?.Name ?? "User";
        string userRole      = User.Claims.FirstOrDefault(c => c.Type == System.Security.Claims.ClaimTypes.Role)?.Value ?? "User";

        var vm = new DashboardViewModel { UserName = userName };

        // ── Summary counts ────────────────────────────────────────────────────

        // Total datasets
        var totalDs = await _surreal.QueryAppDbAsync<SurrealCount>(
            "SELECT count() AS count FROM datasets GROUP ALL;");
        vm.TotalDatasets = totalDs.FirstOrDefault()?.Count ?? 0;

        // My active assets (distinct approved datasets)
        var activeDs = await _surreal.QueryAppDbAsync<SurrealId>(
            $"SELECT VALUE dataset_id FROM access_requests WHERE user_id = users:{currentUserId} AND status = 'Approved';");
        vm.ActiveAssets = activeDs.Select(x => x.Id).Distinct().Count();

        // Pending requests (mine)
        var pendingCount = await _surreal.QueryAppDbAsync<SurrealCount>(
            $"SELECT count() AS count FROM access_requests WHERE user_id = users:{currentUserId} AND status = 'Pending' GROUP ALL;");
        vm.PendingRequests = pendingCount.FirstOrDefault()?.Count ?? 0;

        // Actions required (for IAO/Admin: pending requests on datasets I own the group for)
        if (userRole == "IAO" || userRole == "Admin")
        {
            var actionCount = await _surreal.QueryAppDbAsync<SurrealCount>(
                $"SELECT count() AS count FROM access_requests WHERE status = 'Pending' AND dataset_id.owner_group_id.owner_id = users:{currentUserId} GROUP ALL;");
            vm.ActionsRequired = actionCount.FirstOrDefault()?.Count ?? 0;
        }

        // ── Activity trend (last 30 days) ─────────────────────────────────────

        var thirtyDaysAgo    = DateTime.UtcNow.AddDays(-30).Date;
        var thirtyDaysAgoIso = thirtyDaysAgo.ToString("yyyy-MM-dd") + "T00:00:00Z";

        var trendRows = await _surreal.QueryAppDbAsync<SurrealCreatedAt>(
            $"SELECT created_at FROM access_requests WHERE created_at >= d\"{thirtyDaysAgoIso}\";");

        var trendData = trendRows
            .Where(r => r.CreatedAt != default)
            .GroupBy(r => r.CreatedAt.Date)
            .ToDictionary(g => g.Key, g => g.Count());

        var chartData = new List<int>();
        var labels    = new List<string>();
        int maxVal    = 0;

        for (int i = 29; i >= 0; i--)
        {
            var date = DateTime.UtcNow.AddDays(-i).Date;
            int val  = trendData.GetValueOrDefault(date, 0);
            chartData.Add(val);
            labels.Add(date.ToString("dd MMM"));
            if (val > maxVal) maxVal = val;
        }

        if (maxVal == 0) maxVal = 5;

        string svgPoints = "";
        double width  = 1000;
        double height = 200;
        double stepX  = width / (chartData.Count - 1);

        for (int i = 0; i < chartData.Count; i++)
        {
            double x = i * stepX;
            double y = height - ((chartData[i] / (double)maxVal) * height * 0.8);
            svgPoints += $"{x.ToString("0.##", System.Globalization.CultureInfo.InvariantCulture)},{y.ToString("0.##", System.Globalization.CultureInfo.InvariantCulture)} ";

            if (i % 5 == 0 || i == chartData.Count - 1)
                vm.ActivityDots.Add(new ActivityDot { Cx = x, Cy = y, Delay = 1.5 + (i * 0.05) });
        }

        vm.ActivitySvgPoints     = svgPoints.Trim();
        vm.ActivityPolygonPoints = $"0,200 {vm.ActivitySvgPoints} 1000,200";
        vm.ActivityLabels[0]     = labels[0];
        vm.ActivityLabels[1]     = labels[15];
        vm.ActivityLabels[2]     = labels[29];

        // ── Top datasets by request count ─────────────────────────────────────

        var topDs = await _surreal.QueryAppDbAsync<SurrealTopDataset>(
            "SELECT dataset_id.name AS name, count() AS count FROM access_requests GROUP BY dataset_id ORDER BY count DESC LIMIT 5;");

        int maxReq = topDs.Any() ? topDs.Max(t => t.Count) : 1;
        if (maxReq == 0) maxReq = 1;

        foreach (var td in topDs)
        {
            vm.TopDatasets.Add(new TopDatasetViewModel
            {
                Name         = td.Name ?? "",
                RequestCount = td.Count,
                Percentage   = (td.Count / (double)maxReq) * 100
            });
        }

        // ── Dataset type inventory ────────────────────────────────────────────

        var types = await _surreal.QueryAppDbAsync<SurrealTypeCount>(
            "SELECT type, count() AS count FROM datasets GROUP BY type;");

        vm.Inventory.Fact      = types.FirstOrDefault(t => t.Type == "Fact")?.Count      ?? 0;
        vm.Inventory.Dimension = types.FirstOrDefault(t => t.Type == "Dimension")?.Count ?? 0;
        vm.Inventory.Staging   = types.FirstOrDefault(t => t.Type == "Staging")?.Count   ?? 0;

        // ── Request outcomes ──────────────────────────────────────────────────

        var statuses = await _surreal.QueryAppDbAsync<SurrealStatusCount>(
            "SELECT status, count() AS count FROM access_requests GROUP BY status;");

        vm.Outcome.Approved = statuses.FirstOrDefault(s => s.Status == "Approved")?.Count ?? 0;
        vm.Outcome.Pending  = statuses.FirstOrDefault(s => s.Status == "Pending")?.Count  ?? 0;
        vm.Outcome.Rejected = statuses.FirstOrDefault(s => s.Status == "Rejected")?.Count ?? 0;

        // ── My recent approved access ─────────────────────────────────────────

        var recentAccess = await _surreal.QueryAppDbAsync<SurrealRecentAccess>(
            $"SELECT record::id(dataset_id) AS dataset_id, dataset_id.name AS dataset_name FROM access_requests WHERE user_id = users:{currentUserId} AND status = 'Approved' ORDER BY reviewed_at DESC LIMIT 5;");

        vm.MyRecentAccess = recentAccess
            .Select(r => new RecentDatasetViewModel { Id = r.DatasetId, Name = r.DatasetName ?? "", DateDisplay = "Current" })
            .ToList();

        // ── New to catalog ────────────────────────────────────────────────────

        var newCatalog = await _surreal.QueryAppDbAsync<SurrealNewDataset>(
            "SELECT record::id(id) AS id, name, type, created_at FROM datasets ORDER BY created_at DESC LIMIT 6;");

        vm.NewToCatalog = newCatalog
            .Select(d => new RecentDatasetViewModel
            {
                Id          = d.Id,
                Name        = d.Name,
                Type        = d.Type,
                DateDisplay = d.CreatedAt != default ? d.CreatedAt.ToString("dd MMM") : ""
            })
            .ToList();

        return View(vm);
    }

    public IActionResult Privacy() => View();

    [AllowAnonymous]
    [ResponseCache(Duration = 0, Location = ResponseCacheLocation.None, NoStore = true)]
    public IActionResult Error() =>
        View(new ErrorViewModel { RequestId = Activity.Current?.Id ?? HttpContext.TraceIdentifier });

    // ── SurrealDB response models ─────────────────────────────────────────────

    private class SurrealCount      { public int     Count { get; set; } }
    private class SurrealId         { public int     Id    { get; set; } }
    private class SurrealCreatedAt  { public DateTime CreatedAt { get; set; } }

    private class SurrealTopDataset
    {
        public string? Name  { get; set; }
        public int     Count { get; set; }
    }

    private class SurrealTypeCount
    {
        public string? Type  { get; set; }
        public int     Count { get; set; }
    }

    private class SurrealStatusCount
    {
        public string? Status { get; set; }
        public int     Count  { get; set; }
    }

    private class SurrealRecentAccess
    {
        public int     DatasetId   { get; set; }
        public string? DatasetName { get; set; }
    }

    private class SurrealNewDataset
    {
        public int      Id        { get; set; }
        public string   Name      { get; set; } = "";
        public string   Type      { get; set; } = "";
        public DateTime CreatedAt { get; set; }
    }
}
