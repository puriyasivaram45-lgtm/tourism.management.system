-- Optional booking pricing migration for an existing database.
-- Fresh installs that import tms.sql already include these columns.
-- Run this only if tblbooking does not already have these columns.

ALTER TABLE `tblbooking`
  ADD COLUMN `Adults` int(11) NOT NULL DEFAULT 1 AFTER `ToDate`,
  ADD COLUMN `Children` int(11) NOT NULL DEFAULT 0 AFTER `Adults`,
  ADD COLUMN `Rooms` int(11) NOT NULL DEFAULT 1 AFTER `Children`,
  ADD COLUMN `TravelDays` int(11) NOT NULL DEFAULT 1 AFTER `Rooms`,
  ADD COLUMN `EstimatedAmount` int(11) NOT NULL DEFAULT 0 AFTER `TravelDays`,
  ADD COLUMN `PriceBreakdown` mediumtext DEFAULT NULL AFTER `EstimatedAmount`;
