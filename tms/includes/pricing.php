<?php
require_once __DIR__ . '/security.php';

if (!function_exists('pricing_trip_days')) {
    function pricing_trip_days($fromDate, $toDate)
    {
        if (!valid_iso_date($fromDate) || !valid_iso_date($toDate)) {
            return 1;
        }

        $from = new DateTime($fromDate);
        $to = new DateTime($toDate);
        if ($to < $from) {
            return 1;
        }

        return ((int) $from->diff($to)->format('%a')) + 1;
    }
}

if (!function_exists('pricing_quote_config')) {
    function pricing_quote_config($basePrice)
    {
        $basePrice = max(1, (int) $basePrice);

        return array(
            'base_price' => $basePrice,
            'extra_day_rate' => max(500, (int) round($basePrice * 0.35)),
            'daily_support_fee' => max(300, (int) round($basePrice * 0.02)),
            'child_rate' => 0.55,
            'room_day_rate' => max(800, (int) round($basePrice * 0.08)),
            'seasonal_rate' => 0.06,
            'tax_rate' => 0.05,
        );
    }
}

if (!function_exists('package_price_quote')) {
    function package_price_quote($basePrice, $fromDate = null, $toDate = null, $adults = 1, $children = 0, $rooms = 1)
    {
        $config = pricing_quote_config($basePrice);
        $days = ($fromDate && $toDate) ? pricing_trip_days($fromDate, $toDate) : 1;
        $extraDays = max(0, $days - 1);
        $adults = max(1, (int) $adults);
        $children = max(0, (int) $children);
        $rooms = max(1, (int) $rooms);

        $baseAmount = $config['base_price'];
        $extraDaysAmount = $extraDays * $config['extra_day_rate'];
        $travelSubtotal = $baseAmount + $extraDaysAmount;
        $adultAmount = $travelSubtotal * $adults;
        $childAmount = (int) round($travelSubtotal * $config['child_rate'] * $children);
        $roomAmount = max(0, $rooms - 1) * $days * $config['room_day_rate'];
        $supportAmount = $days * $config['daily_support_fee'] * ($adults + $children);
        $subtotal = $adultAmount + $childAmount + $roomAmount;
        $seasonalAmount = (int) round($subtotal * $config['seasonal_rate']);
        $taxAmount = (int) round(($subtotal + $supportAmount + $seasonalAmount) * $config['tax_rate']);
        $total = $subtotal + $supportAmount + $seasonalAmount + $taxAmount;

        return array(
            'days' => $days,
            'adults' => $adults,
            'children' => $children,
            'rooms' => $rooms,
            'base_price' => $baseAmount,
            'extra_day_rate' => $config['extra_day_rate'],
            'extra_days' => $extraDays,
            'extra_days_amount' => $extraDaysAmount,
            'child_rate' => $config['child_rate'],
            'room_day_rate' => $config['room_day_rate'],
            'adult_amount' => $adultAmount,
            'child_amount' => $childAmount,
            'room_amount' => $roomAmount,
            'daily_support_fee' => $config['daily_support_fee'],
            'support_amount' => $supportAmount,
            'seasonal_amount' => $seasonalAmount,
            'tax_amount' => $taxAmount,
            'total' => $total,
        );
    }
}

if (!function_exists('pricing_format_inr')) {
    function pricing_format_inr($amount)
    {
        return 'Rs. ' . number_format((float) $amount, 0);
    }
}

if (!function_exists('pricing_int_range')) {
    function pricing_int_range($value, $min, $max)
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        if ($value === false) {
            return $min;
        }

        return max($min, min($max, (int) $value));
    }
}

if (!function_exists('ensure_booking_pricing_columns')) {
    function ensure_booking_pricing_columns(PDO $dbh)
    {
        static $done = false;
        if ($done) {
            return;
        }

        $columns = $dbh->query("SHOW COLUMNS FROM tblbooking")->fetchAll(PDO::FETCH_COLUMN);
        $columnMap = array_fill_keys($columns, true);
        $addColumns = array();

        if (empty($columnMap['Adults'])) {
            $addColumns[] = "ADD COLUMN `Adults` int(11) NOT NULL DEFAULT 1 AFTER `ToDate`";
        }
        if (empty($columnMap['Children'])) {
            $addColumns[] = "ADD COLUMN `Children` int(11) NOT NULL DEFAULT 0 AFTER `Adults`";
        }
        if (empty($columnMap['Rooms'])) {
            $addColumns[] = "ADD COLUMN `Rooms` int(11) NOT NULL DEFAULT 1 AFTER `Children`";
        }
        if (empty($columnMap['TravelDays'])) {
            $addColumns[] = "ADD COLUMN `TravelDays` int(11) NOT NULL DEFAULT 1 AFTER `Rooms`";
        }
        if (empty($columnMap['EstimatedAmount'])) {
            $addColumns[] = "ADD COLUMN `EstimatedAmount` int(11) NOT NULL DEFAULT 0 AFTER `TravelDays`";
        }
        if (empty($columnMap['PriceBreakdown'])) {
            $addColumns[] = "ADD COLUMN `PriceBreakdown` mediumtext DEFAULT NULL AFTER `EstimatedAmount`";
        }

        if ($addColumns) {
            $dbh->exec("ALTER TABLE tblbooking " . implode(", ", $addColumns));
        }

        $done = true;
    }
}

if (!function_exists('booking_quote_from_row')) {
    function booking_quote_from_row($booking)
    {
        $storedAmount = isset($booking->estimatedamount) ? (int) $booking->estimatedamount : (int) ($booking->EstimatedAmount ?? 0);
        $storedDays = isset($booking->traveldays) ? (int) $booking->traveldays : (int) ($booking->TravelDays ?? 0);
        $adults = isset($booking->adults) ? (int) $booking->adults : (int) ($booking->Adults ?? 1);
        $children = isset($booking->children) ? (int) $booking->children : (int) ($booking->Children ?? 0);
        $rooms = isset($booking->rooms) ? (int) $booking->rooms : (int) ($booking->Rooms ?? 1);
        $basePrice = isset($booking->packageprice) ? (int) $booking->packageprice : (int) ($booking->PackagePrice ?? 0);
        $fromDate = $booking->fromdate ?? ($booking->FromDate ?? null);
        $toDate = $booking->todate ?? ($booking->ToDate ?? null);
        $breakdownJson = $booking->pricebreakdown ?? ($booking->PriceBreakdown ?? null);

        $quote = package_price_quote($basePrice, $fromDate, $toDate, $adults, $children, $rooms);
        if ($breakdownJson) {
            $storedQuote = json_decode((string) $breakdownJson, true);
            if (is_array($storedQuote)) {
                $quote = array_merge($quote, $storedQuote);
            }
        }
        if ($storedAmount > 0) {
            $quote['total'] = $storedAmount;
        }
        if ($storedDays > 0) {
            $quote['days'] = $storedDays;
        }

        return $quote;
    }
}
?>
