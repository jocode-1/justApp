<?php

class database
{

//  private $host = "localhost";
//  private $db_name = "enerjust_api";
//  private $username = "enerjust_api";
//  private $password = "Cougar@123..??";

    private $host = "localhost";
    private $db_name = "just_api";
    private $username = "root";
    private $password = "";
  public $conn;


  // get the database connection
 
    // get the database connection
    public function getConnection(){
  
        $this->conn = null;
  
        try{
            $this->conn = new mysqli($this->host,$this->username, $this->password, $this->db_name);
    
	//	echo 'Success';
        }catch(Exception $exception){
            echo "Connection error: " . $exception->getMessage();
        }
  
        return $this->conn;
  
        
    }


  
  
}

//$connect = new database();
//$connect->getConnection();
