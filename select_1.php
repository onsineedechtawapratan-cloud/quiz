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

$sql = "SELECT studentID, firstName, lastName, email, term, company, address, province, startDate, endDate, contact FROM internships "; // Your SQL query
$result = $connect->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "id: " . $row["studentID"]. 
             " Name: " . $row["firstName"] .
             " ". $row["lastName"]."<br>".
             " - email: ". $row["email"]."<br>".
             " - term: ". $row["term"] . "<br>".
             " - company: ". $row["company"]."<br>".
             " - address: ". $row["address"] ."<br>".
             " - province: ". $row["province"]."<br>".
             " - startDate: ". $row["startDate"] ."<br>".
             " - endDate: ". $row["endDate"]."<br>".
             " - contact: ". $row["contact"]."<br>";
    }
} else {
    echo "0 results";
}

$connect->close();
?>
