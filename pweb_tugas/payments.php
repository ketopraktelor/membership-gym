<?php
// payments.php (Manajemen Pembayaran / Halaman Pembayaran Anggota)

include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php?action=login");
    exit();
}

$user_role = $_SESSION['user_role'] ?? 'user';
$user_id = $_SESSION['user_id'];
$message = '';
$action_success = false;

// --- LOGIKA FORM ANGGOTA: SUBMIT PEMBAYARAN BARU ---
if ($user_role == 'user' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_payment'])) {
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $method = mysqli_real_escape_string($conn, $_POST['method']);
    // Logika ini mengasumsikan anggota membayar untuk perpanjangan standar (misal: 30 hari)
    $payment_date = date('Y-m-d'); 

    // Insert pembayaran baru dengan status Pending
    $insert_payment_query = "INSERT INTO payments (user_id, amount, payment_date, method, status) 
                             VALUES ('$user_id', '$amount', '$payment_date', '$method', 'Pending')";
    
    if (mysqli_query($conn, $insert_payment_query)) {
        $payment_id = mysqli_insert_id($conn);

        // Update kolom renewal_request dan renewal_proof di tabel users
        $update_user_query = "UPDATE users SET renewal_request = TRUE, renewal_proof = '$payment_id' WHERE id = '$user_id'";
        mysqli_query($conn, $update_user_query); // Eksekusi update

        $message = "Bukti Pembayaran berhasil diserahkan! Admin akan memproses perpanjangan Anda setelah pembayaran diverifikasi.";
        $action_success = true;
    } else {
        $message = "Gagal memproses pembayaran: " . mysqli_error($conn);
    }
}


// --- LOGIKA ADMIN: UPDATE STATUS PEMBAYARAN ---
if ($user_role == 'admin' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_payment_status'])) {
    $payment_id = mysqli_real_escape_string($conn, $_POST['payment_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);

    $update_query = "UPDATE payments SET status = '$new_status' WHERE id = '$payment_id'";
    
    if (mysqli_query($conn, $update_query)) {
        // Jika status menjadi Completed, tambahkan logika perpanjangan (diasumsikan 30 hari)
        if ($new_status == 'Completed') {
            $user_id_query = mysqli_query($conn, "SELECT user_id FROM payments WHERE id = '$payment_id'");
            $user_id_row = mysqli_fetch_assoc($user_id_query);
            $target_user_id = $user_id_row['user_id'];

            // Cek tanggal kadaluarsa saat ini
            $user_details_query = mysqli_query($conn, "SELECT expiry_date FROM users WHERE id = '$target_user_id'");
            $user_expiry_row = mysqli_fetch_assoc($user_details_query);
            $current_expiry = $user_expiry_row['expiry_date'];

            // Tentukan tanggal baru: 30 hari dari hari ini atau dari tanggal kadaluarsa terakhir
            $base_date = (strtotime($current_expiry) > time()) ? $current_expiry : date('Y-m-d');
            $new_expiry = date('Y-m-d', strtotime($base_date . ' + 30 days'));

            // Update user
            $update_user_exp_query = "
                UPDATE users 
                SET expiry_date = '$new_expiry', 
                    renewal_request = FALSE, 
                    renewal_proof = NULL
                WHERE id = '$target_user_id'";

            mysqli_query($conn, $update_user_exp_query);

            // Update riwayat pembayaran dengan tanggal kadaluarsa
            mysqli_query($conn, "UPDATE payments SET expiry_date_before = '$current_expiry', expiry_date_after = '$new_expiry' WHERE id = '$payment_id'");
            
            $message = "Pembayaran diverifikasi. Membership anggota berhasil diperpanjang hingga " . date('d M Y', strtotime($new_expiry)) . ".";
        } else {
            $message = "Status pembayaran berhasil diperbarui.";
        }
        $action_success = true;
    } else {
        $message = "Gagal memperbarui status: " . mysqli_error($conn);
    }
}


// --- LOGIKA TAMPILKAN SEMUA PEMBAYARAN (HANYA ADMIN) ---
if ($user_role == 'admin') {
    $payments_query = "
        SELECT p.*, u.name AS user_name, u.email 
        FROM payments p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.payment_date DESC
    ";
    $payments_result = mysqli_query($conn, $payments_query);
}

// Tutup koneksi di akhir
if (isset($conn)) {
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($user_role == 'admin') ? 'Manajemen Pembayaran (Admin)' : 'Pembayaran Membership'; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-container">
        <a href="dashboard.php" class="back-btn submit-btn">← Kembali ke Dashboard</a>
        <h2><?php echo ($user_role == 'admin') ? 'Manajemen Pembayaran' : 'Form Pembayaran Membership'; ?></h2>
        <hr>

        <?php if (!empty($message)): ?>
            <p class="<?php echo $action_success ? 'message-success' : 'message-error'; ?>">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <?php if ($user_role == 'admin'): ?>
            <h3>Riwayat Transaksi Anggota</h3>
            
            <?php if ($payments_result && mysqli_num_rows($payments_result) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Anggota</th>
                                <th>Jumlah</th>
                                <th>Tanggal Bayar</th>
                                <th>Metode</th>
                                <th>Status</th>
                                <th>Periode Exp.</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($payments_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['user_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($row['email']); ?></small>
                                    </td>
                                    <td>Rp <?php echo number_format($row['amount'], 0, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($row['payment_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['method']); ?></td>
                                    <td>
                                        <span class="payment-status payment-<?php echo strtolower($row['status']); ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($row['expiry_date_before'] ?? '-'); ?> 
                                        → 
                                        <?php echo htmlspecialchars($row['expiry_date_after'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <form action="payments.php" method="POST" class="inline-form">
                                            <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                            <input type="hidden" name="update_payment_status" value="1">
                                            <select name="new_status" class="form-control status-select">
                                                <option value="Pending" <?php echo ($row['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Completed" <?php echo ($row['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                                <option value="Failed" <?php echo ($row['status'] == 'Failed') ? 'selected' : ''; ?>>Failed</option>
                                            </select>
                                            <button type="submit" class="action-btn update-status-btn">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Belum ada riwayat pembayaran.</p>
            <?php endif; ?>

        <?php else: ?>
            <div class="card payment-form-card">
                <h3>Informasi Perpanjangan Membership</h3>
                <p>Silakan lakukan transfer ke rekening berikut:</p>
                <div class="bank-info">
                    <strong>BANK XYZ</strong><br>
                    No. Rekening: 1234567890<br>
                    Atas Nama: PT. Gym Sehat Selalu<br>
                    Jumlah Tagihan (30 Hari): **Rp 150.000**
                </div>
                <hr>

                <h3>Konfirmasi Pembayaran</h3>
                <p>Unggah bukti pembayaran Anda dan isi detail di bawah ini untuk memulai proses perpanjangan.</p>
                <form action="payments.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="submit_payment" value="1">
                    
                    <div class="form-group">
                        <label for="amount">Jumlah Transfer (Rp):</label>
                        <input type="number" id="amount" name="amount" required min="10000">
                    </div>
                    
                    <div class="form-group">
                        <label for="method">Metode Pembayaran:</label>
                        <select id="method" name="method" required>
                            <option value="">-- Pilih Metode --</option>
                            <option value="Transfer Bank XYZ">Transfer Bank XYZ</option>
                            <option value="E-Wallet">E-Wallet</option>
                            <option value="Tunai di Lokasi">Tunai di Lokasi</option>
                        </select>
                    </div>

                    <button type="submit" class="submit-btn renewal-request-btn">
                        Serahkan Bukti Pembayaran
                    </button>
                    <p class="form-hint-text">Setelah diserahkan, status membership Anda akan diperbarui setelah verifikasi Admin.</p>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>