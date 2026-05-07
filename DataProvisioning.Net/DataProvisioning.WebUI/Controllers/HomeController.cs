using System;
using System.Diagnostics;
using System.Linq;
using System.Threading.Tasks;
using System.Collections.Generic;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using DataProvisioning.WebUI.Models;
using DataProvisioning.Application.Interfaces;
using DataProvisioning.Domain.Enums;

namespace DataProvisioning.WebUI.Controllers;

[Authorize]
public class HomeController : Controller
{
    private readonly IApplicationDbContext _context;

    public HomeController(IApplicationDbContext context)
    {
        _context = context;
    }

    public async Task<IActionResult> Index()
    {
        int currentUserId = int.Parse(User.FindFirst(System.Security.Claims.ClaimTypes.NameIdentifier)?.Value ?? "0");
        string userName = User.Identity?.Name ?? "User";
        string userRole = User.Claims.FirstOrDefault(c => c.Type == System.Security.Claims.ClaimTypes.Role)?.Value ?? "User";

        var vm = new DashboardViewModel { UserName = userName };

        // 1. Total Datasets
        vm.TotalDatasets = await _context.Datasets.CountAsync();

        // 2. My Active Assets
        vm.ActiveAssets = await _context.AccessRequests
            .Where(r => r.UserId == currentUserId && r.Status == RequestStatus.Approved)
            .Select(r => r.DatasetId)
            .Distinct()
            .CountAsync();

        // 3. Pending Requests
        vm.PendingRequests = await _context.AccessRequests
            .CountAsync(r => r.UserId == currentUserId && r.Status == RequestStatus.Pending);

        // 4. Actions Required (IAO/Admin)
        if (userRole == "IAO" || userRole == "Admin")
        {
            vm.ActionsRequired = await _context.AccessRequests
                .Include(r => r.Dataset)
                .ThenInclude(d => d.OwnerGroup)
                .CountAsync(r => r.Dataset != null && r.Dataset.OwnerGroup != null && r.Dataset.OwnerGroup.OwnerId == currentUserId && r.Status == RequestStatus.Pending);
        }

        // --- CHART QUERIES ---

        // 1. Activity Trend (Last 30 Days)
        var thirtyDaysAgo = DateTime.UtcNow.AddDays(-30).Date;
        var trendData = await _context.AccessRequests
            .Where(r => r.CreatedAt >= thirtyDaysAgo)
            .GroupBy(r => r.CreatedAt.Date)
            .Select(g => new { Date = g.Key, Count = g.Count() })
            .ToDictionaryAsync(x => x.Date, x => x.Count);

        var chartData = new List<int>();
        var labels = new List<string>();
        int maxVal = 0;

        for (int i = 29; i >= 0; i--)
        {
            var date = DateTime.UtcNow.AddDays(-i).Date;
            int val = trendData.ContainsKey(date) ? trendData[date] : 0;
            chartData.Add(val);
            labels.Add(date.ToString("dd MMM"));
            if (val > maxVal) maxVal = val;
        }

        if (maxVal == 0) maxVal = 5;

        string svgPoints = "";
        double width = 1000;
        double height = 200;
        double stepX = width / (chartData.Count - 1);
        
        for (int i = 0; i < chartData.Count; i++)
        {
            double x = i * stepX;
            double y = height - ((chartData[i] / (double)maxVal) * height * 0.8);
            svgPoints += $"{x.ToString("0.##", System.Globalization.CultureInfo.InvariantCulture)},{y.ToString("0.##", System.Globalization.CultureInfo.InvariantCulture)} ";
            
            if (i % 5 == 0 || i == chartData.Count - 1)
            {
                vm.ActivityDots.Add(new ActivityDot { Cx = x, Cy = y, Delay = 1.5 + (i * 0.05) });
            }
        }

        vm.ActivitySvgPoints = svgPoints.Trim();
        vm.ActivityPolygonPoints = $"0,200 {vm.ActivitySvgPoints} 1000,200";
        vm.ActivityLabels[0] = labels[0];
        vm.ActivityLabels[1] = labels[15];
        vm.ActivityLabels[2] = labels[29];

        // 2. Top Datasets
        var topDatasets = await _context.AccessRequests
            .Include(r => r.Dataset)
            .GroupBy(r => new { r.DatasetId, r.Dataset.Name })
            .Select(g => new { g.Key.Name, Count = g.Count() })
            .OrderByDescending(x => x.Count)
            .Take(5)
            .ToListAsync();

        int maxReq = topDatasets.Any() ? topDatasets.Max(t => t.Count) : 1;
        if (maxReq == 0) maxReq = 1;

        foreach (var td in topDatasets)
        {
            vm.TopDatasets.Add(new TopDatasetViewModel
            {
                Name = td.Name,
                RequestCount = td.Count,
                Percentage = (td.Count / (double)maxReq) * 100
            });
        }

        // 3. Inventory Types
        var types = await _context.Datasets
            .GroupBy(d => d.Type)
            .Select(g => new { Type = g.Key, Count = g.Count() })
            .ToListAsync();

        vm.Inventory.Fact = types.FirstOrDefault(t => t.Type == DatasetType.Fact)?.Count ?? 0;
        vm.Inventory.Dimension = types.FirstOrDefault(t => t.Type == DatasetType.Dimension)?.Count ?? 0;
        vm.Inventory.Staging = types.FirstOrDefault(t => t.Type == DatasetType.Staging)?.Count ?? 0;

        // 4. Request Outcomes
        var statuses = await _context.AccessRequests
            .GroupBy(r => r.Status)
            .Select(g => new { Status = g.Key, Count = g.Count() })
            .ToListAsync();

        vm.Outcome.Approved = statuses.FirstOrDefault(s => s.Status == RequestStatus.Approved)?.Count ?? 0;
        vm.Outcome.Pending = statuses.FirstOrDefault(s => s.Status == RequestStatus.Pending)?.Count ?? 0;
        vm.Outcome.Rejected = statuses.FirstOrDefault(s => s.Status == RequestStatus.Rejected)?.Count ?? 0;

        // 5. My Recent Access
        var recentAccess = await _context.AccessRequests
            .Include(r => r.Dataset)
            .Where(r => r.UserId == currentUserId && r.Status == RequestStatus.Approved)
            .OrderByDescending(r => r.ReviewedAt)
            .Take(5)
            .ToListAsync();

        vm.MyRecentAccess = recentAccess.Select(r => new RecentDatasetViewModel
        {
            Id = r.DatasetId,
            Name = r.Dataset.Name,
            DateDisplay = "Current"
        }).ToList();

        // 6. New to Catalog
        var newCatalog = await _context.Datasets
            .OrderByDescending(d => d.CreatedAt)
            .Take(6)
            .ToListAsync();

        vm.NewToCatalog = newCatalog.Select(d => new RecentDatasetViewModel
        {
            Id = d.Id,
            Name = d.Name,
            Type = d.Type.ToString(),
            DateDisplay = d.CreatedAt.ToString("dd MMM")
        }).ToList();

        return View(vm);
    }

    public IActionResult Privacy()
    {
        return View();
    }

    [AllowAnonymous]
    [ResponseCache(Duration = 0, Location = ResponseCacheLocation.None, NoStore = true)]
    public IActionResult Error()
    {
        return View(new ErrorViewModel { RequestId = Activity.Current?.Id ?? HttpContext.TraceIdentifier });
    }
}
