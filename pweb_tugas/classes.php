<?php
// classes.php (Manajemen Kelas/Sesi - Dashboard Admin)

include 'config.php';
session_start();

// --- PENGAMANAN HANYA UNTUK ADMIN ---
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'user') != 'admin') {
    header("Location: dashboard.php");
    exit();
}
// ------------------------------------

$message = '';
$action_success = false;
$class_to_edit = null;

// --- LOGIKA ADD/UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_class']) || isset($_POST['update_class']))) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $trainer = mysqli_real_escape_string($conn, $_POST['trainer']);
    $schedule = mysqli_real_escape_string($conn, $_POST['schedule']);
    $capacity = mysqli_real_escape_string($conn, $_POST['capacity']);
    
    if (isset($_POST['add_class'])) {
        $query = "INSERT INTO classes (name, description, trainer, schedule, max_capacity) 
                  VALUES ('$name', '$description', '$trainer', '$schedule', '$capacity')";
        $success_msg = "Kelas baru berhasil ditambahkan.";
    } elseif (isset($_POST['update_class'])) {
        $class_id = mysqli_real_escape_string($conn, $_POST['class_id']);
        $query = "UPDATE classes SET name='$name', description='$description', trainer='$trainer', 
                  schedule='$schedule', max_capacity='$capacity' WHERE id='$class_id'";
        $success_msg = "Kelas berhasil diperbarui.";
    }

    if (mysqli_query($conn, $query)) {
        header("Location: classes.php?status=success&msg=" . urlencode($success_msg));
        exit();
    } else {
        $message = "Operasi gagal: " . mysqli_error($conn);
    }
}

// --- LOGIKA DELETE ---
if (isset($_GET['delete_id'])) {
    $id_to_delete = mysqli_real_escape_string($conn, $_GET['delete_id']);
    $delete_query = "DELETE FROM classes WHERE id = '$id_to_delete'";
    if (mysqli_query($conn, $delete_query)) {
        header("Location: classes.php?status=success&msg=" . urlencode("Kelas berhasil dihapus."));
        exit();
    } else {
        $message = "Gagal menghapus kelas: " . mysqli_error($conn);
    }
}

// --- LOGIKA EDIT FORM (Mengambil data) ---
if (isset($_GET['edit_id'])) {
    $id_to_edit = mysqli_real_escape_string($conn, $_GET['edit_id']);
    $edit_query = "SELECT * FROM classes WHERE id = '$id_to_edit'"; 
    $result_edit = mysqli_query($conn, $edit_query);
    if (mysqli_num_rows($result_edit) == 1) {
        $class_to_edit = mysqli_fetch_assoc($result_edit);
    } else {
        $message = "Kelas tidak ditemukan.";
    }
}

// Cek status pesan dari redirect
if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $message = htmlspecialchars($_GET['msg']);
    $action_success = true;
}

// --- LOGIKA TAMPILKAN SEMUA KELAS ---
$classes_query = "SELECT * FROM classes ORDER BY id ASC";
$classes_result = mysqli_query($conn, $classes_query);

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kelas/Sesi (Admin)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-container">
        <a href="dashboard.php" class="back-btn submit-btn">← Kembali ke Dashboard</a>
        <h2>Manajemen Sesi dan Kelas Gym</h2>
        <hr>

        <?php if (!empty($message)): ?>
            <p class="<?php echo $action_success ? 'message-success' : 'message-error'; ?>">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <div class="form-container class-form-container">
            <h3><?php echo $class_to_edit ? 'Edit Kelas: ' . htmlspecialchars($class_to_edit['name']) : 'Tambah Kelas Baru'; ?></h3>
            
            <form action="classes.php" method="POST">
                <?php if ($class_to_edit): ?>
                    <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($class_to_edit['id']); ?>">
                    <input type="hidden" name="update_class" value="1">
                <?php else: ?>
                    <input type="hidden" name="add_class" value="1">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Nama Kelas (Contoh: Zumba, Muay Thai):</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($class_to_edit['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="schedule">Jadwal (Contoh: Senin, 18:00 - 19:00):</label>
                    <input type="text" id="schedule" name="schedule" value="<?php echo htmlspecialchars($class_to_edit['schedule'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="trainer">Nama Trainer:</label>
                    <input type="text" id="trainer" name="trainer" value="<?php echo htmlspecialchars($class_to_edit['trainer'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="capacity">Kapasitas Maksimal (0 jika tidak dibatasi):</label>
                    <input type="number" id="capacity" name="capacity" value="<?php echo htmlspecialchars($class_to_edit['max_capacity'] ?? '0'); ?>" min="0">
                </div>

                <div class="form-group">
                    <label for="description">Deskripsi Kelas:</label>
                    <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($class_to_edit['description'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="submit-btn save-changes-btn">
                    <?php echo $class_to_edit ? 'Simpan Perubahan' : 'Tambah Kelas'; ?>
                </button>
                
                <?php if ($class_to_edit): ?>
                    <a href="classes.php" class="submit-btn cancel-edit-btn">Batal Edit</a>
                <?php endif; ?>
            </form>
        </div>
        <hr>

        <h3>Daftar Sesi Gym Aktif</h3>
        
        <?php if ($classes_result && mysqli_num_rows($classes_result) > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Kelas</th>
                            <th>Jadwal</th>
                            <th>Trainer</th>
                            <th>Kapasitas</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($classes_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($row['description']); ?></small>
                                </td>
                                <td><span class="schedule-text"><?php echo htmlspecialchars($row['schedule']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['trainer']); ?></td>
                                <td><?php echo htmlspecialchars($row['max_capacity']); ?></td>
                                <td>
                                    <a href="classes.php?edit_id=<?php echo htmlspecialchars($row['id']); ?>" class="action-btn edit-btn">Edit</a>
                                    <a href="classes.php?delete_id=<?php echo htmlspecialchars($row['id']); ?>" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus kelas <?php echo htmlspecialchars($row['name']); ?>?')" 
                                       class="action-btn delete-btn">Hapus</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>Belum ada kelas yang ditambahkan.</p>
        <?php endif; ?>
    </div>
</body>
</html>