<?php
// ============================================================
// Application Constants
// ============================================================

// Roles
define('ROLE_ADMIN', 1);
define('ROLE_ACCOUNTANT', 2);
define('ROLE_COMMITTEE', 3);
define('ROLE_STAFF_MANAGER', 4);
define('ROLE_AUDITOR', 5);

// Payment modes
define('PAYMENT_MODES', [
    'cash'   => 'Cash',
    'bank'   => 'Bank Transfer',
    'cheque' => 'Cheque',
    'online' => 'Online Payment',
    'upi'    => 'UPI',
]);

// Staff designations
define('STAFF_DESIGNATIONS', [
    'imam'         => 'Imam',
    'khatib'       => 'Khatib',
    'muazzin'      => 'Muazzin',
    'teacher'      => 'Madrasa Teacher',
    'office_staff' => 'Office Staff',
    'cleaner'      => 'Cleaner',
    'security'     => 'Security',
    'other'        => 'Other',
]);

// Property types
define('PROPERTY_TYPES', [
    'land'      => 'Land',
    'shop'      => 'Shop',
    'building'  => 'Building',
    'hall'      => 'Hall',
    'classroom' => 'Classroom',
    'room'      => 'Room',
    'other'     => 'Other',
]);

// Account types
define('ACCOUNT_TYPES', [
    'Assets'      => 'Assets',
    'Liabilities' => 'Liabilities',
    'Income'      => 'Income',
    'Expense'     => 'Expense',
    'Capital'     => 'Capital / Fund',
]);

// Months
define('MONTHS', [
    '01' => 'January', '02' => 'February', '03' => 'March',
    '04' => 'April',   '05' => 'May',       '06' => 'June',
    '07' => 'July',    '08' => 'August',    '09' => 'September',
    '10' => 'October', '11' => 'November',  '12' => 'December',
]);
