USE autoserve_db;

INSERT IGNORE INTO `ServiceType` (name, est_minutes, base_price) VALUES
('Change Oil', 45, 850.00),
('Brake Check', 60, 500.00),
('Tune-up', 90, 1500.00),
('Tire Rotation', 30, 400.00),
('General Inspection', 60, 600.00);

-- Demo staff accounts are created by setup/seed.php so PHP can generate
-- password_hash() values compatible with the current PHP installation.
