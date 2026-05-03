<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="description" content="EatFree - Digital feeding scheme connecting donors with local food vendors to provide free meals to communities across South Africa.">
    <meta name="theme-color" content="#6db049">
    
     <!-- Custom Fonts -->
    <link rel="stylesheet" href="assets/css/Cooper%20Black%20Regular.css?v=2">
    <link rel="stylesheet" href="assets/css/Montserrat.css?v=2">
    <link rel="stylesheet" href="assets/css/Kaushan%20Script.css?v=2">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/istyle3.css?v=2">   
    
    <style>
        body { font-family: 'Cooper Black Regular', serif; }
        .header { text-align:center; margin-bottom:20px; }
        .title { font-size:24px; color:#6db049; }
        .section { margin-bottom:20px; }
        table { width:100%; border-collapse: collapse; }
        th, td { padding:8px; border-bottom:1px solid #ddd; }
        th { background:#6db049; color:white; }
    </style>
</head>
<body>

<div class="header">
    <div class="title">EatFree Statement</div>
    <div><?= date('Y-m-d') ?></div>
</div>

<div class="section">
    <strong>Business:</strong> <?= htmlspecialchars($vendor['business_name']) ?><br>
    <strong>Email:</strong> <?= htmlspecialchars($vendor['email']) ?><br>
    <strong>Status:</strong> <?= $vendor['subscription_status'] ?>
</div>

<div class="section">
    <h3>Transactions</h3>

    <table>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Amount</th>
        </tr>

        <?php foreach($transactions as $t): ?>
        <tr>
            <td><?= $t['created_at'] ?></td>
            <td><?= $t['transaction_type'] ?></td>
            <td>R <?= number_format($t['amount'],2) ?></td>
        </tr>
        <?php endforeach; ?>

    </table>
</div>

</body>
</html>