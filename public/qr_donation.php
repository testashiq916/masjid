<?php
/**
 * Public QR Donation Page — No auth required
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';

// Check if QR donation is enabled
$qrEnabled = getSetting('qr_donation_enabled');
$masjid    = getMasjidProfile();
$upiId     = getSetting('upi_id') ?: ($masjid['upi_id'] ?? '');
$upiName   = getSetting('upi_name') ?: ($masjid['upi_name'] ?? $masjid['masjid_name'] ?? 'Masjid');
$donationMsg = getSetting('qr_donation_message') ?: 'Scan to donate. Jazakallah Khair!';

$lang = $_SESSION['lang'] ?? 'en';
$isMal = ($lang === 'ml');

$thankYou = false;
$receiptNo = '';
$errors = [];

// Handle donation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_donation'])) {
    $donorName  = htmlspecialchars(trim($_POST['donor_name'] ?? ''), ENT_QUOTES);
    $donorPhone = trim($_POST['donor_phone'] ?? '');
    $amount     = (float)($_POST['amount'] ?? 0);
    $purpose    = htmlspecialchars(trim($_POST['purpose'] ?? 'General'), ENT_QUOTES);

    if (empty($donorName)) $errors[] = $isMal ? 'പേര് ആവശ്യമാണ്' : 'Name is required.';
    if ($amount <= 0)       $errors[] = $isMal ? 'സാധുവായ തുക നൽകുക' : 'Please enter a valid amount.';

    if (empty($errors)) {
        try {
            $db = db();
            // Get or create a default income category for UPI donations
            $cat = $db->query("SELECT id FROM income_categories WHERE deleted_at IS NULL ORDER BY id LIMIT 1")->fetch();
            $catId = $cat ? (int)$cat['id'] : null;

            // Get default bank/UPI account
            $acct = $db->query("SELECT id FROM chart_of_accounts WHERE account_code='1002' AND deleted_at IS NULL LIMIT 1")->fetch();
            $acctId = $acct ? (int)$acct['id'] : null;

            // Generate receipt number
            $receiptNo = generateNumber('QR', 'receipts', 'receipt_no');
            $db->prepare("
                INSERT INTO receipts
                    (receipt_no, date, donor_name, donor_phone, category_id, account_id,
                     payment_mode, amount, remarks, created_at)
                VALUES (?,CURDATE(),?,?,?,?,'upi',?,?,NOW())
            ")->execute([
                $receiptNo, $donorName, $donorPhone ?: null,
                $catId, $acctId, $amount,
                'QR Donation - ' . $purpose
            ]);
            $thankYou = true;
        } catch (Exception $e) {
            $errors[] = 'Could not save donation. Please try again.';
        }
    }
}

// Build UPI string and QR URL
function buildQrUrl(string $upiId, string $upiName, float $amount = 0): string {
    $upiStr = 'upi://pay?pa=' . urlencode($upiId)
            . '&pn=' . urlencode($upiName)
            . '&cu=INR';
    if ($amount > 0) $upiStr .= '&am=' . $amount;
    return 'https://chart.googleapis.com/chart?cht=qr&chs=280x280&chld=M|0&chl=' . urlencode($upiStr);
}
?>
<!DOCTYPE html>
<html lang="<?= $isMal ? 'ml' : 'en' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $isMal ? 'ദാനം ചെയ്യുക' : 'Donate' ?> — <?= htmlspecialchars($masjid['masjid_name'] ?? 'Masjid') ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
.qr-card { max-width: 480px; margin: 0 auto; }
.qr-img { border: 3px solid #198754; border-radius: 12px; padding: 8px; background: #fff; }
.masjid-logo { height: 70px; object-fit: contain; }
.amount-btn.active { background: #198754 !important; color: #fff !important; border-color: #198754 !important; }
.bilingual { font-size: 0.9rem; color: #555; }
@media print { .no-print { display:none!important; } }
</style>
</head>
<body>

<div class="container py-4">
  <div class="qr-card">

    <!-- Masjid Header -->
    <div class="text-center mb-3">
      <?php if (!empty($masjid['logo'])): ?>
      <img src="<?= BASE_PATH ?>/assets/images/<?= htmlspecialchars($masjid['logo']) ?>"
           alt="Logo" class="masjid-logo mb-2">
      <?php else: ?>
      <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2"
           style="width:70px;height:70px;font-size:2rem;">
          <i class="bi bi-building"></i>
      </div>
      <?php endif; ?>
      <h4 class="fw-bold text-success mb-0">
          <?= htmlspecialchars($masjid['masjid_name'] ?? 'Masjid') ?>
      </h4>
      <?php if (!empty($masjid['address'])): ?>
      <p class="text-muted small mb-0"><?= htmlspecialchars($masjid['address']) ?></p>
      <?php endif; ?>
      <?php if (!empty($masjid['phone'])): ?>
      <p class="text-muted small mb-0"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($masjid['phone']) ?></p>
      <?php endif; ?>
    </div>

    <?php if ($qrEnabled === '0'): ?>
    <div class="alert alert-warning text-center">
        <i class="bi bi-info-circle me-2"></i>
        QR donation is currently disabled. Please contact the Masjid admin.
    </div>

    <?php elseif (empty($upiId)): ?>
    <div class="alert alert-warning text-center">
        <i class="bi bi-exclamation-triangle me-2"></i>
        QR donation is not configured yet. Please contact the Masjid admin.
    </div>

    <?php elseif ($thankYou): ?>
    <!-- Thank You -->
    <div class="card border-success shadow-sm">
        <div class="card-body text-center py-5">
            <div class="text-success mb-3" style="font-size:4rem;"><i class="bi bi-check-circle-fill"></i></div>
            <h4 class="fw-bold text-success">
                <?= $isMal ? 'ജസാക്കല്ലാഹ് ഖൈർ!' : 'Jazakallah Khair!' ?>
            </h4>
            <p class="text-muted">
                <?= $isMal
                    ? 'നിങ്ങളുടെ ദാനത്തിന് ഹൃദയപൂർവ്വം നന്ദി.'
                    : 'Thank you for your generous donation.' ?>
            </p>
            <div class="alert alert-success">
                <?= $isMal ? 'രസീദ് നമ്പർ:' : 'Receipt No:' ?>
                <strong><?= htmlspecialchars($receiptNo) ?></strong>
            </div>
            <a href="<?= BASE_PATH ?>/public/qr_donation.php" class="btn btn-outline-success">
                <i class="bi bi-arrow-repeat me-1"></i>
                <?= $isMal ? 'മറ്റൊരു ദാനം' : 'Donate Again' ?>
            </a>
        </div>
    </div>

    <?php else: ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger small">
        <?php foreach ($errors as $e): ?><div><?= $e ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- QR Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white text-center fw-bold">
            <i class="bi bi-qr-code me-2"></i>
            <?= $isMal ? 'QR വഴി ദാനം ചെയ്യുക' : 'Donate via QR Code' ?>
        </div>
        <div class="card-body text-center">
            <p class="text-muted small mb-2 bilingual">
                <?= $isMal
                    ? 'ദാനം ചെയ്യാൻ ഏതെങ്കിലും UPI ആപ്പ് ഉപയോഗിച്ച് സ്കാൻ ചെയ്യുക'
                    : 'Scan with any UPI app (PhonePe, GPay, Paytm) to donate' ?>
            </p>

            <!-- Amount Presets -->
            <div class="d-flex justify-content-center gap-2 flex-wrap mb-3 no-print">
                <button class="btn btn-sm btn-outline-success amount-btn" data-amount="100">₹100</button>
                <button class="btn btn-sm btn-outline-success amount-btn" data-amount="500">₹500</button>
                <button class="btn btn-sm btn-outline-success amount-btn" data-amount="1000">₹1000</button>
                <button class="btn btn-sm btn-outline-success amount-btn" data-amount="2000">₹2000</button>
                <button class="btn btn-sm btn-outline-success amount-btn" data-amount="0">
                    <?= $isMal ? 'ഏതെങ്കിലും' : 'Any' ?>
                </button>
            </div>

            <!-- QR Code Image -->
            <img id="qrImage"
                 src="<?= htmlspecialchars(buildQrUrl($upiId, $upiName)) ?>"
                 alt="UPI QR Code"
                 class="qr-img mb-3"
                 style="width:280px;height:280px;">

            <div class="small text-muted mb-1">
                <strong><?= $isMal ? 'UPI ID:' : 'UPI ID:' ?></strong>
                <span class="font-monospace"><?= htmlspecialchars($upiId) ?></span>
                <button class="btn btn-sm btn-link p-0 ms-1 no-print" onclick="copyUpi()">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
            <p class="text-muted small"><?= htmlspecialchars($donationMsg) ?></p>

            <button class="btn btn-outline-success btn-sm no-print" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>
                <?= $isMal ? 'QR പ്രിന്റ് ചെയ്യുക' : 'Print QR' ?>
            </button>
        </div>
    </div>

    <!-- Donation Form -->
    <div class="card shadow-sm no-print">
        <div class="card-header bg-light fw-semibold">
            <i class="bi bi-person-check me-2 text-success"></i>
            <?= $isMal ? 'ദാന വിവരങ്ങൾ (ഐച്ഛികം)' : 'Donation Details (Optional)' ?>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                <?= $isMal
                    ? 'UPI വഴി ദാനം ചെയ്ത ശേഷം, ആഗ്രഹിക്കുന്നെങ്കിൽ നിങ്ങളുടെ വിവരങ്ങൾ ചേർക്കുക.'
                    : 'After completing your UPI payment, optionally fill your details for a receipt.' ?>
            </p>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <?= $isMal ? 'നിങ്ങളുടെ പേര്' : 'Your Name' ?> <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" name="donor_name"
                           value="<?= htmlspecialchars($_POST['donor_name'] ?? '') ?>"
                           placeholder="<?= $isMal ? 'പേര് നൽകുക' : 'Enter your name' ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <?= $isMal ? 'മൊബൈൽ നമ്പർ' : 'Mobile Number' ?>
                    </label>
                    <input type="tel" class="form-control" name="donor_phone"
                           value="<?= htmlspecialchars($_POST['donor_phone'] ?? '') ?>"
                           placeholder="10-digit mobile">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <?= $isMal ? 'ദാന തുക (₹)' : 'Amount Donated (₹)' ?> <span class="text-danger">*</span>
                    </label>
                    <input type="number" class="form-control" name="amount" id="formAmount"
                           value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
                           placeholder="e.g. 500" min="1" step="1">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <?= $isMal ? 'ദാനത്തിന്റെ ഉദ്ദേശ്യം' : 'Purpose of Donation' ?>
                    </label>
                    <select class="form-select" name="purpose">
                        <option value="General"><?= $isMal ? 'പൊതു' : 'General' ?></option>
                        <option value="Zakat"><?= $isMal ? 'സകാത്ത്' : 'Zakat' ?></option>
                        <option value="Sadaqah"><?= $isMal ? 'സദഖ' : 'Sadaqah' ?></option>
                        <option value="Masjid Maintenance"><?= $isMal ? 'മസ്ജിദ് അറ്റകുറ്റ' : 'Masjid Maintenance' ?></option>
                        <option value="Madrasa"><?= $isMal ? 'മദ്രസ' : 'Madrasa' ?></option>
                        <option value="Iftar"><?= $isMal ? 'ഇഫ്ത്താർ' : 'Iftar' ?></option>
                        <option value="Poor & Needy"><?= $isMal ? 'ദരിദ്രർ' : 'Poor & Needy' ?></option>
                    </select>
                </div>
                <button type="submit" name="submit_donation" class="btn btn-success w-100">
                    <i class="bi bi-check-circle me-2"></i>
                    <?= $isMal ? 'ദാന വിവരങ്ങൾ സമർപ്പിക്കുക' : 'Submit Donation Details' ?>
                </button>
            </form>
        </div>
    </div>

    <?php endif; ?>

    <!-- Language Toggle -->
    <div class="text-center mt-3 no-print">
        <a href="?lang=en" class="btn btn-sm btn-outline-secondary <?= !$isMal ? 'active' : '' ?>">
            🇬🇧 English
        </a>
        <a href="?lang=ml" class="btn btn-sm btn-outline-secondary <?= $isMal ? 'active' : '' ?>">
            🇮🇳 മലയാളം
        </a>
    </div>

    <div class="text-center mt-2 no-print">
        <a href="<?= BASE_PATH ?>/login.php" class="text-muted small">
            <i class="bi bi-lock me-1"></i>Staff Login
        </a>
    </div>

  </div><!-- qr-card -->
</div><!-- container -->

<script>
// Handle lang param (session-free for public page)
(function() {
    var urlParams = new URLSearchParams(window.location.search);
    var lang = urlParams.get('lang');
    if (lang) {
        document.cookie = 'pub_lang=' + lang + ';path=/';
    }
})();

var upiId   = <?= json_encode($upiId) ?>;
var upiName = <?= json_encode($upiName) ?>;

function buildQrUrl(amount) {
    var str = 'upi://pay?pa=' + encodeURIComponent(upiId)
            + '&pn=' + encodeURIComponent(upiName)
            + '&cu=INR';
    if (amount > 0) str += '&am=' + amount;
    return 'https://chart.googleapis.com/chart?cht=qr&chs=280x280&chld=M|0&chl=' + encodeURIComponent(str);
}

document.querySelectorAll('.amount-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.amount-btn').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
        var amount = parseFloat(this.dataset.amount) || 0;
        document.getElementById('qrImage').src = buildQrUrl(amount);
        var formAmt = document.getElementById('formAmount');
        if (formAmt && amount > 0) formAmt.value = amount;
    });
});

function copyUpi() {
    navigator.clipboard.writeText(upiId).then(function() {
        alert('UPI ID copied: ' + upiId);
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
