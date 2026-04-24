<?php
// ===== Database Config =====
$host = "localhost";
$user = "root";
$pass = "";
$db   = "student_db";

// ===== Connect Database =====
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ===== Fetch Data =====
$sql = "SELECT id, name, email, phone FROM students ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student List</title>
    <style>
        body { font-family: Arial; margin: 40px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background-color: #f4f4f4; }
        a { text-decoration: none; color: blue; }
        .btn { padding: 5px 10px; border: 1px solid #333; background: #eee; }
    </style>
</head>
<body>

<h2>📋 Student List</h2>

<a href="add.php" class="btn">+ Add Student</a>
<br><br>

<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Action</th>
    </tr>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($
                row['phone']) ?></td>
                <td>
                    <a href="edit.php?id=<?= $row['id'] ?>">Edit</a> |
                    <a href="delete.php?id=<?= $row['id'] ?>" 
                       onclick="return confirm('Are you sure?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="5">No data found</td>
        </tr>
    <?php endif; ?>
</table>

</body>
</html>

<?php
$conn->close();
?>