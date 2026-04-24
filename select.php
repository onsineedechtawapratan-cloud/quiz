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
echo "<br><br>";

$sql = "SELECT stdID, stdName, stdMajor FROM t_student"; // Your SQL query
$result = $connect->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "id: " . $row["stdID"]. " - Name: " . $row["stdName"]. " " . $row["stdMajor"]. "<br>";
    }
} else {
    echo "0 results";
}

$connect->close();
?>
