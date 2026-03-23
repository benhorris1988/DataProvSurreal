using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using Microsoft.EntityFrameworkCore;
using DataProvisioning.Application.DTOs;
using DataProvisioning.Application.Interfaces;
using DataProvisioning.Domain.Enums;

namespace DataProvisioning.Application.Services;

public class CatalogService : ICatalogService
{
    private readonly IApplicationDbContext _context;

    public CatalogService(IApplicationDbContext context)
    {
        _context = context;
    }

    public async Task<List<DatasetCatalogDto>> GetCatalogAsync(int currentUserId, string? searchQuery = null)
    {
        var query = _context.Datasets
            .Include(d => d.OwnerGroup)
            .ThenInclude(g => g != null ? g.Members : null)
            .AsQueryable();

        if (!string.IsNullOrWhiteSpace(searchQuery))
        {
            query = query.Where(d => 
                d.Name.Contains(searchQuery) || 
                (d.Description != null && d.Description.Contains(searchQuery)));
        }

        var results = await query.Select(d => new DatasetCatalogDto
        {
            Id = d.Id,
            Name = d.Name,
            Type = d.Type.ToString(),
            Description = d.Description,
            GroupName = d.OwnerGroup != null ? d.OwnerGroup.Name : "Unassigned",
            GroupOwnerId = d.OwnerGroup != null ? d.OwnerGroup.OwnerId : null,
            IsMember = d.OwnerGroup != null && d.OwnerGroup.Members.Any(m => m.UserId == currentUserId),
            AccessStatus = _context.AccessRequests
                .Where(ar => ar.DatasetId == d.Id && ar.UserId == currentUserId)
                .OrderBy(ar => ar.Status == RequestStatus.Approved ? 1 : ar.Status == RequestStatus.Pending ? 2 : 3)
                .Select(ar => ar.Status.ToString())
                .FirstOrDefault()
        }).ToListAsync();

        return results.OrderBy(d => d.Name).ToList();
    }
}
