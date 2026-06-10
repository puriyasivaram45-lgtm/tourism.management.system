-- Optional standalone payment table migration.
-- The application also creates this table automatically when the payment flow is used.

CREATE TABLE IF NOT EXISTS `tblpayments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `BookingId` int(11) NOT NULL,
  `UserEmail` varchar(100) NOT NULL,
  `MerchantOrderId` varchar(100) NOT NULL,
  `Provider` varchar(30) NOT NULL DEFAULT 'phonepe',
  `AmountPaise` int(11) NOT NULL,
  `Currency` char(3) NOT NULL DEFAULT 'INR',
  `Status` varchar(40) NOT NULL DEFAULT 'created',
  `GatewayOrderId` varchar(100) DEFAULT NULL,
  `RedirectUrl` text DEFAULT NULL,
  `RequestPayload` mediumtext DEFAULT NULL,
  `ResponsePayload` mediumtext DEFAULT NULL,
  `StatusPayload` mediumtext DEFAULT NULL,
  `CreatedAt` timestamp NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `MerchantOrderId` (`MerchantOrderId`),
  KEY `BookingId` (`BookingId`),
  KEY `UserEmail` (`UserEmail`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
