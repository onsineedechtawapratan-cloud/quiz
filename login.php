<?php
$salt = 'XyZzy12*_';
$stored_hash = hash('md5', $salt.'php123'); // รหัสผ่านคือ php123

if ( isset($_POST['cancel']) ) {
    header("Location: index.php");
    return;
}

$failure = false;
if ( isset($_POST['who']) && isset($_POST['pass']) ) {
    if ( strlen($_POST['who']) < 1 || strlen($_POST['pass']) < 1 ) {
        $failure = "User name and password are required";
    } else {
        $check = hash('md5', $salt.$_POST['pass']);
        if ( $check == $stored_hash ) {
            header("Location: game.php?name=".urlencode($_POST['who']));
            return;
        } else {
            $failure = "Incorrect password";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>f792a1e4 Login Page</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
</head>
<body>
<div class="container">
    <h1>Please Log In</h1>
    <?php if ( $failure !== false ) echo('<p style="color: red;">'.htmlentities($failure)."</p>\n"); ?>
    <form method="post">
        <label>User Name</label> <input type="text" name="who"><br/>
        <label>Password</label> <input type="text" name="pass"><br/>
        <input type="submit" class="btn btn-primary" value="Log In">
        <input type="submit" class="btn btn-default" name="cancel" value="Cancel">
    </form>
</div>
</body>
</html>