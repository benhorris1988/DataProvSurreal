-- Add some reports linked to datasets
INSERT INTO reports (name, url, description) VALUES 
('Global Sales Dashboard', 'https://powerbi.microsoft.com/demo/sales', 'Executive view of global sales performance'),
('Sector Performance Q3', 'https://tableau.server/sectors/q3', 'Deep dive into sector analysis');

-- Link them to FactSales (id: 1)
INSERT INTO report_datasets (report_id, dataset_id) VALUES 
(1, 1),
(2, 1);

-- Add another dataset 'FactProduction' for Nuclear sector demo
INSERT INTO datasets (name, type, description, owner_group_id) VALUES
('FactProduction', 'Fact', 'Nuclear and Marine production output metrics.', 1);

-- Columns for FactProduction
INSERT INTO columns (dataset_id, name, data_type, definition, is_pii, sample_data) VALUES
(4, 'PlantID', 'INT', 'Manufacturing plant identifier', 0, 'PL-01, PL-02'),
(4, 'Output_MW', 'DECIMAL', 'Megawatt output', 0, '450.5, 300.2'),
(4, 'Safety_Rating', 'VARCHAR(10)', 'Safety inspection score', 0, 'A+, A-'),
(4, 'Sector', 'VARCHAR(50)', 'Target sector', 0, 'Nuclear, Marine');

-- Add a pending request for demonstration
INSERT INTO access_requests (user_id, dataset_id, status, justification, requested_rls_filters, created_at) VALUES 
(1, 4, 'Pending', 'Need production data for quarterly safety audit.', '{"Sector": "Nuclear"}', NOW());
