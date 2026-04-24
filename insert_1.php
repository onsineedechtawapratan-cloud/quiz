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

$sql = "INSERT INTO internships (studentID, firstName, lastName, email, term, company, address, province, startDate, endDate, contact)
VALUES 
('6510101025', 'Konsuay', 'Ka', 'konsuayka@email.com', '1/2565', 'Srijun', 'Ubon', 'Ubon', '2022-02-03', '2024-04-01', 'P’Smorn')";


if ($connect->query($sql) === TRUE) {
  echo "New record created successfully";
} else {
  echo "Error: " . $sql . "<br>" . $connect->error;
}

$connect->close();
?>