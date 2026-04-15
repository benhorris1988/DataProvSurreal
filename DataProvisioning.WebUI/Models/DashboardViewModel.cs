using System;
using System.Collections.Generic;

namespace DataProvisioning.WebUI.Models;

public class DashboardViewModel
{
    public string UserName { get; set; } = string.Empty;
    public int ActiveAssets { get; set; }
    public int PendingRequests { get; set; }
    public int ActionsRequired { get; set; }
    public int TotalDatasets { get; set; }

    // Charts
    public string ActivitySvgPoints { get; set; } = string.Empty;
    public string ActivityPolygonPoints { get; set; } = string.Empty;
    public string[] ActivityLabels { get; set; } = new string[3];
    public List<ActivityDot> ActivityDots { get; set; } = new();

    public List<TopDatasetViewModel> TopDatasets { get; set; } = new();

    public DatasetTypeCount Inventory { get; set; } = new();
    public RequestOutcomeCount Outcome { get; set; } = new();

    public List<RecentDatasetViewModel> MyRecentAccess { get; set; } = new();
    public List<RecentDatasetViewModel> NewToCatalog { get; set; } = new();
}

public class ActivityDot
{
    public double Cx { get; set; }
    public double Cy { get; set; }
    public double Delay { get; set; }
}

public class TopDatasetViewModel
{
    public string Name { get; set; } = string.Empty;
    public int RequestCount { get; set; }
    public double Percentage { get; set; }
}

public class DatasetTypeCount
{
    public int Fact { get; set; }
    public int Dimension { get; set; }
    public int Staging { get; set; }
    public int Total => Fact + Dimension + Staging;
    public double FactPct => Total > 0 ? (Fact / (double)Total) * 100 : 0;
    public double DimPct => Total > 0 ? (Dimension / (double)Total) * 100 : 0;
    public double StgPct => Total > 0 ? (Staging / (double)Total) * 100 : 0;
}

public class RequestOutcomeCount
{
    public int Approved { get; set; }
    public int Pending { get; set; }
    public int Rejected { get; set; }
    public int Total => Approved + Pending + Rejected;
    public double ApprovedPct => Total > 0 ? (Approved / (double)Total) * 100 : 0;
    public double PendingPct => Total > 0 ? (Pending / (double)Total) * 100 : 0;
    public double RejectedPct => Total > 0 ? (Rejected / (double)Total) * 100 : 0;
}

public class RecentDatasetViewModel
{
    public int Id { get; set; }
    public string Name { get; set; } = string.Empty;
    public string Type { get; set; } = string.Empty;
    public string DateDisplay { get; set; } = string.Empty;
}
