<?php
if ( ! isset($_GET['name']) || strlen($_GET['name']) < 1  ) {
    die("Name parameter missing");
}

if ( isset($_POST['logout']) ) {
    header('Location: index.php');
    return;
}

$names = array('Rock', 'Paper', 'Scissors');

function check($computer, $human) {
    if ( $human == $computer ) return "Tie";
    if ( ($human == 0 && $computer == 2) || ($human == 1 && $computer == 0) || ($human == 2 && $computer == 1) ) return "You Win";
    return "You Lose";
}

$human = isset($_POST["human"]) ? $_POST['human']+0 : -1;
$computer = rand(0,2); // สุ่มเพื่อให้ได้ค่าอื่นนอกจาก Rock
$result = check($computer, $human);
?>
<!DOCTYPE html>
<html>
<head>
<title>f792a1e4 Rock Paper Scissors</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
</head>
<body>
<div class="container">
    <h1>Rock Paper Scissors</h1>
    <p>Welcome: <?php echo htmlentities($_GET['name']); ?></p>

    <form method="post">
        <select name="human">
            <option value="-1">Select</option>
            <option value="0">Rock</option>
            <option value="1">Paper</option>
            <option value="2">Scissors</option>
            <option value="3">Test</option>
        </select>
        <input type="submit" class="btn btn-primary" value="Play">
        <input type="submit" class="btn btn-danger" name="logout" value="Logout">
    </form>

    <pre style="margin-top:10px;"> <?php
if ( $human == -1 ) {
    echo "Please select a strategy and press Play.\n";
} else if ( $human == 3 ) {
    for($c=0;$c<3;$c++) {
        for($h=0;$h<3;$h++) {
            $r = check($c, $h);
            echo "Human=$names[$h] Computer=$names[$c] Result=$r\n";
        }
    }
} else {
    echo "Your Play=$names[$human] Computer Play=$names[$computer] Result=$result\n";
}
?>
    </pre>
</div>
</body>
</html>