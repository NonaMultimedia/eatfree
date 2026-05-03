<?php
require_once __DIR__ . '/config/config.php';

try {

    $db = getDB();

    $vendorId = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;

    if ($vendorId <= 0) {
        throw new Exception("Invalid QR code");
    }

    $stmt = $db->prepare("
        SELECT id, business_name, status
        FROM vendors
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch();

    if (!$vendor) {
        throw new Exception("Vendor not found");
    }

    if ($vendor['status'] !== 'approved') {
        throw new Exception("Vendor not approved");
    }

} catch (Exception $e) {
    die("
        <div style='text-align:center;margin-top:80px;font-family:sans-serif;'>
            <h3>⚠️ " . htmlspecialchars($e->getMessage()) . "</h3>
        </div>
    ");
}
?>

<!DOCTYPE html>
<html lang="en-ZA">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EatFree | Claim Meal</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="assets/css/Montserrat.css">
<link rel="stylesheet" href="assets/css/Kaushan Script.css">
<link rel="stylesheet" href="assets/css/Cooper Black Regular.css">
<link rel="stylesheet" href="assets/css/istyle3.css">

<style>
body {
    background: var(--ef-gray-100);
}

.claim-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.claim-card {
    background: #fff;
    border-radius: var(--ef-radius-lg);
    padding: 2rem;
    width: 100%;
    max-width: 540px;
    box-shadow: var(--ef-shadow-lg);
}

.vendor-banner {
    background: linear-gradient(135deg, var(--ef-primary), #4e8a32);
    color: #fff;
    padding: 1.25rem;
    border-radius: var(--ef-radius);
    margin-bottom: 1.5rem;
    text-align: center;
}

.vendor-banner h3 {
    margin: 0;
    font-family: 'Cooper Black Regular', serif;
    font-size: 1.25rem;
}

.claim-title {
    font-family: 'Cooper Black Regular', serif;
    color: var(--ef-primary);
    text-align: center;
}

.claim-subtitle {
    text-align: center;
    color: var(--ef-gray-500);
    margin-bottom: 1.5rem;
}

/* =========================
   SUCCESS MODAL (NEW)
========================= */
.success-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(5px);
    justify-content: center;
    align-items: center;
    z-index: 99999;
}

.success-modal.active {
    display: flex;
}

.success-box {
    background: #fff;
    padding: 2rem;
    border-radius: var(--ef-radius-lg);
    text-align: center;
    width: 90%;
    max-width: 420px;
    box-shadow: var(--ef-shadow-xl);
    animation: popIn 0.25s ease;
}

.success-title {
    font-family: 'Cooper Black Regular', serif;
    color: var(--ef-primary);
    margin-bottom: 0.5rem;
}

.voucher-code {
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: 2px;
    color: var(--ef-dark);
    margin: 1rem 0;
}

.success-note {
    color: var(--ef-gray-500);
    font-size: 0.9rem;
}

@keyframes popIn {
    from { transform: scale(0.8); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
</style>
</head>

<body>

<div class="claim-wrapper">

    <div class="claim-card">

        <div class="vendor-banner">
            <h3>Welcome to EatFree, <?= htmlspecialchars($vendor['business_name']) ?></h3>
            <p>Enter your details below to receive your voucher meal</p>
        </div>

        <h2 class="claim-title">Claim Your Meal</h2>
        <p class="claim-subtitle">Quick secure verification for your free meal</p>

        <form id="claimForm">

            <div class="ef-input-group">
                <label class="ef-label">Full Name</label>
                <input type="text" name="name" class="ef-input" required>
            </div>

            <div class="ef-input-group">
                <label class="ef-label">ID Number</label>
                <input type="text" name="id_number" class="ef-input" maxlength="13" required>
            </div>

            <div class="ef-input-group">
                <label class="ef-label">Residential Address</label>
                <input type="text" name="address" class="ef-input" required>
            </div>

            <div class="ef-input-group">
                <label class="ef-label">Phone</label>
                <input type="text" name="phone" class="ef-input" required>
            </div>

            <input type="hidden" name="vendor_id" value="<?= (int)$vendor['id'] ?>">

            <button type="submit" class="ef-btn ef-btn-primary w-100 mt-3">
                Claim Meal
            </button>

        </form>

    </div>

</div>

<!-- SUCCESS MODAL -->
<div id="successModal" class="success-modal">
    <div class="success-box">
        <div class="success-title">Congratulations</div>
        <div>Here is your meal code</div>

        <div class="voucher-code" id="voucherCode">---</div>

        <div class="success-note">
            Show this code to the cashier before leaving
        </div>

        <button class="ef-btn ef-btn-primary mt-3 w-100" onclick="continueFlow()">
            Continue
        </button>
    </div>
</div>

<script>
const API = "api/claim-meal.php";

function showModal(code) {
    document.getElementById("voucherCode").innerText = code;
    document.getElementById("successModal").classList.add("active");
}

function continueFlow() {
    window.location.href = "index.html#success";
}

document.getElementById("claimForm").addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);

    try {
        const res = await fetch(API, {
            method: "POST",
            body: new URLSearchParams(formData)
        });

        const data = await res.json();

        if (data.success) {
            showModal(data.data.voucher_code);
        } else {
            alert(data.message || "Claim failed");
        }

    } catch (err) {
        console.error(err);
        alert("Network error");
    }
});
</script>

</body>
</html>