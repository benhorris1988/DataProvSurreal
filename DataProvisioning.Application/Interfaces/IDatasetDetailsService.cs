using System.Threading.Tasks;
using DataProvisioning.Application.DTOs;

namespace DataProvisioning.Application.Interfaces;

public interface IDatasetDetailsService
{
    Task<DatasetDetailsDto?> GetDatasetDetailsAsync(int datasetId, int currentUserId, string currentUserRole);
    
    // Editing
    Task<EditDatasetDto?> GetEditDatasetAsync(int datasetId, int currentUserId, string currentUserRole);
    Task UpdateDatasetAsync(EditDatasetDto dto);
    
    // Reports
    Task AddReportAsync(int datasetId, string name, string url);
}
