<?php
/**
 * SMS Helper - Fast2SMS Integration
 * Masjid ERP System
 */

/**
 * Send SMS via Fast2SMS API
 *
 * @param string $mobile   Recipient mobile number
 * @param string $message  SMS message text
 * @param string $refType  Reference type (e.g. 'receipt', 'salary')
 * @param int    $refId    Reference record ID
 * @return bool            True on successful send
 */
function sendSMS(string $mobile, string $message, string $refType = '', int $refId = 0): bool
{
    // Check if SMS is enabled
    if (getSetting('sms_enabled') !== '1') {
        return false;
    }

    $apiKey = getSetting('sms_api_key');
    if (empty($apiKey)) {
        return false;
    }

    // Normalise mobile number: strip non-digits
    $mobile = preg_replace('/\D/', '', $mobile);

    // Strip country prefix if present
    if (strlen($mobile) === 12 && substr($mobile, 0, 2) === '91') {
        $mobile = substr($mobile, 2);
    } elseif (strlen($mobile) === 11 && substr($mobile, 0, 1) === '0') {
        $mobile = substr($mobile, 1);
    }

    if (strlen($mobile) !== 10) {
        _logSMS($mobile, $message, 'failed', 'fast2sms', 'Invalid mobile number format', $refType, $refId);
        return false;
    }

    $status   = 'failed';
    $response = '';

    try {
        $postFields = http_build_query([
            'route'    => 'q',
            'message'  => $message,
            'language' => 'english',
            'flash'    => 0,
            'numbers'  => $mobile,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://www.fast2sms.com/dev/bulkV2',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_HTTPHEADER     => [
                'authorization: ' . $apiKey,
                'Content-Type: application/x-www-form-urlencoded',
                'Cache-Control: no-cache',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $responseRaw = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $response = 'cURL error: ' . $curlError;
        } else {
            $response = $responseRaw;
            $decoded  = json_decode($responseRaw, true);
            if ($httpCode === 200 && isset($decoded['return']) && $decoded['return'] === true) {
                $status = 'sent';
            }
        }
    } catch (\Exception $e) {
        $response = 'Exception: ' . $e->getMessage();
    }

    _logSMS($mobile, $message, $status, 'fast2sms', $response, $refType, $refId);

    return $status === 'sent';
}

/**
 * Internal helper — insert a row into sms_log
 */
function _logSMS(
    string $mobile,
    string $message,
    string $status,
    string $provider,
    string $response,
    string $refType,
    int    $refId
): void {
    try {
        $userId = (function_exists('currentUserId') ? currentUserId() : 0);
        $stmt = db()->prepare(
            "INSERT INTO sms_log
                (mobile, message, status, provider, response, reference_type, reference_id, created_by, created_at)
             VALUES
                (:mobile, :message, :status, :provider, :response, :ref_type, :ref_id, :created_by, NOW())"
        );
        $stmt->execute([
            ':mobile'     => $mobile,
            ':message'    => $message,
            ':status'     => $status,
            ':provider'   => $provider,
            ':response'   => substr($response, 0, 1000),
            ':ref_type'   => $refType,
            ':ref_id'     => $refId,
            ':created_by' => $userId ?: null,
        ]);
    } catch (\Exception $e) {
        // Silently fail — do not break application flow
        error_log('SMS log error: ' . $e->getMessage());
    }
}

/**
 * Send receipt confirmation SMS to donor
 *
 * @param array $receipt  Receipt row (id, receipt_no, amount)
 * @param array $donor    Donor data (name / donor_name, phone / donor_phone)
 * @return bool
 */
function sendReceiptSMS(array $receipt, array $donor): bool
{
    if (getSetting('sms_on_receipt') !== '1') {
        return false;
    }

    $profile    = getMasjidProfile();
    $masjidName = $profile['masjid_name'] ?? ($profile['name'] ?? 'Masjid');
    $donorName  = $donor['name'] ?? ($donor['donor_name'] ?? 'Donor');
    $phone      = $donor['phone'] ?? ($donor['donor_phone'] ?? '');
    $amount     = number_format((float)($receipt['amount'] ?? 0), 2);
    $receiptNo  = $receipt['receipt_no'] ?? '';

    $message = "Dear {$donorName}, your donation of Rs.{$amount} to {$masjidName} has been received. "
             . "Receipt No: {$receiptNo}. Jazakallah Khair!";

    return sendSMS($phone, $message, 'receipt', (int)($receipt['id'] ?? 0));
}

/**
 * Send salary payment SMS to staff member
 *
 * @param array  $staff   Staff row (name / staff_name, phone / mobile)
 * @param float  $amount  Net salary amount
 * @param string $month   Month label e.g. "March 2026"
 * @return bool
 */
function sendSalaryPaymentSMS(array $staff, float $amount, string $month): bool
{
    if (getSetting('sms_on_salary') !== '1') {
        return false;
    }

    $profile    = getMasjidProfile();
    $masjidName = $profile['masjid_name'] ?? ($profile['name'] ?? 'Masjid');
    $staffName  = $staff['name'] ?? ($staff['staff_name'] ?? 'Staff');
    $phone      = $staff['phone'] ?? ($staff['mobile'] ?? '');
    $amountFmt  = number_format($amount, 2);

    $message = "Dear {$staffName}, your salary of Rs.{$amountFmt} for {$month} has been credited. - {$masjidName}";

    return sendSMS($phone, $message, 'salary', (int)($staff['id'] ?? 0));
}

/**
 * Send rent reminder SMS to tenant
 *
 * @param array  $tenant  Tenant row (name / tenant_name, phone / mobile)
 * @param float  $amount  Rent amount due
 * @param string $month   Month label e.g. "March 2026"
 * @return bool
 */
function sendRentReminderSMS(array $tenant, float $amount, string $month): bool
{
    if (getSetting('sms_on_rent_due') !== '1') {
        return false;
    }

    $profile    = getMasjidProfile();
    $masjidName = $profile['masjid_name'] ?? ($profile['name'] ?? 'Masjid');
    $tenantName = $tenant['name'] ?? ($tenant['tenant_name'] ?? 'Tenant');
    $phone      = $tenant['phone'] ?? ($tenant['mobile'] ?? '');
    $amountFmt  = number_format($amount, 2);

    $message = "Dear {$tenantName}, your rent of Rs.{$amountFmt} for {$month} is due. "
             . "Please pay at the earliest. - {$masjidName}";

    return sendSMS($phone, $message, 'rent', (int)($tenant['id'] ?? 0));
}

/**
 * Send payment voucher approval / rejection SMS
 *
 * @param string $mobile     Recipient mobile number
 * @param string $voucherNo  Voucher number
 * @param float  $amount     Voucher amount
 * @param string $type       'approval_request' | 'approved' | 'rejected'
 * @return bool
 */
function sendApprovalSMS(string $mobile, string $voucherNo, float $amount, string $type = 'approval_request'): bool
{
    if (getSetting('sms_on_approval') !== '1') {
        return false;
    }

    $amountFmt = number_format($amount, 2);

    switch ($type) {
        case 'approved':
            $message = "Payment voucher {$voucherNo} of Rs.{$amountFmt} has been approved.";
            break;
        case 'rejected':
            $message = "Payment voucher {$voucherNo} of Rs.{$amountFmt} has been rejected.";
            break;
        default: // approval_request
            $message = "Payment voucher {$voucherNo} of Rs.{$amountFmt} is pending your approval on Masjid ERP.";
            break;
    }

    return sendSMS($mobile, $message, 'voucher', 0);
}
