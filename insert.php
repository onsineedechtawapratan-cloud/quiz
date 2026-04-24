<?php
$host = "127.0.0.1";
$dbuser = "root";
$dbpass = "";
$dbname = "is222";

$connect = mysqli_connect($host,$dbuser,$dbpass,$dbname,'3306');
if($connect){
	echo "Connect Success";
}else{
	echo "Connect Failed";
}

$sql = "INSERT INTO t_student (stdID, stdName, stdMajor)
VALUES ('6510101021', 'Jannie Doe', 'สารสนเทศศึกษา')";

if ($connect->query($sql) === TRUE) {
  echo "New record created successfully";
} else {
  echo "Error: " . $sql . "<br>" . $connect->error;
}

$connect->close();
?>