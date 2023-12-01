<?php

class database
{

  private $host = "localhost";
  private $db_name = "just_api";
  private $username = "root";
  private $password = "";
  public $conn;


  // get the database connection
  // public function getConnection()
  // {

  //   $this->conn = null;

  //   try {
  //     $this->conn = mysqli_connect($this->host, $this->username, $this->password, $this->db_name);

  //     	echo 'Success';
  //   } catch (Exception $exception) {
  //     echo "Connection error: " . $exception->getMessage();
  //   }

  //   return $this->conn;
  // }

  public function getConnection() {
    try {
      $this->conn = mysqli_connect($this->host, $this->username, $this->password, $this->db_name);
      // echo 'Connection successful';
    } catch (Exception $exception) {
      echo "Connection error: ". $exception->getMessage();
    }
    return $this->conn;
  }
  
  
}

//$connect = new database();
//$connect->getConnection();
