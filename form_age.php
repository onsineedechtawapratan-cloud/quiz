<?php
// ================================
// ค่าระบบ
// ================================
$currentYear = 2569;

// ================================
// เตรียมตัวแปรเก็บข้อมูลผู้ใช้
// ================================
$users = [];

// ================================
// Function: คำนวณอายุ
// ================================
function calculateAge($birthYear, $currentYear) {
    return $currentYear - $birthYear;
}

// ================================
// Function: ตรวจสอบสิทธิ์
// ================================
function checkAccess($age, $birthYear, $currentYear) {

    if ($birthYear > $currentYear) {
        return "ข้อมูลปีเกิดไม่ถูกต้อง";
    }

    if ($age > 120) {
        return "กรุณาตรวจสอบข้อมูลอีกครั้ง";
    }

    if ($age < 18) {
        return "ไม่อนุญาตให้เข้าใช้งาน";
    } else {
        return "อนุญาตให้เข้าใช้งาน";
    }
}

// ================================
// ประมวลผล Form
// ================================
if (isset($_POST['username']) && isset($_POST['birthYear'])) {

    $username = $_POST['username'];
    $birthYear = intval($_POST['birthYear']);

    $age = calculateAge($birthYear, $currentYear);
    $result = checkAccess($age, $birthYear, $currentYear);

    $users[] = [
        "username" => $username,
        "birthYear" => $birthYear,
        "age" => $age,
        "result" => $result
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Age Gate Web App</title>
</head>
<body>

<h2>ระบบตรวจสอบสิทธิ์เข้าใช้งานเว็บไซต์</h2>

<form method="post">
    ชื่อผู้ใช้:<br>
    <input type="text" name="username" required><br><br>

    ปีเกิด (พ.ศ.):<br>
    <input type="number" name="birthYear" required><br><br>

    <button type="submit">เพิ่มผู้ใช้</button>
</form>

<hr>

<?php
// ================================
// แสดงผลผู้ใช้ทั้งหมด
// ================================
foreach ($users as $user) {
    echo "ชื่อผู้ใช้: " . $user['username'] . "<br>";
    echo "ปีเกิด: " . $user['birthYear'] . "<br>";
    echo "อายุ: " . $user['age'] . " ปี<br>";
    echo "ผลการตรวจสอบ: " . $user['result'] . "<br>";
    echo "<hr>";
}
?>

</body>
</html>