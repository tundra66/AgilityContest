<?php

/*
userFunctions.php

Copyright 2013-2015 by Juan Antonio Martinez ( juansgaviota at gmail dot com )

This program is free software; you can redistribute it and/or modify it under the terms
of the GNU General Public License as published by the Free Software Foundation;
either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program;
if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/


require_once(__DIR__."/logging.php");
require_once(__DIR__."/tools.php");
require_once(__DIR__."/auth/Config.php");
require_once(__DIR__."/auth/AuthManager.php");

class Admin {
	protected $myLogger;
	protected $myConfig;
	protected $file;
	public $errormsg;
	
	function __construct($file) {
		// connect database
		$this->file=$file;
		$this->myLogger= new Logger($file);
		$this->myConfig=new Config();
	}
	
	// FROM: https://gist.github.com/lavoiesl/9a08e399fc9832d12794
	private function process_line($line) {
		$length = strlen($line);
		$pos = strpos($line, ' VALUES ') + 8;
		echo substr($line, 0, $pos);
		$parenthesis = false;
		$quote = false;
		$escape = false;
		for ($i = $pos; $i < $length; $i++) {
			switch($line[$i]) {
				case '(':
					if (!$quote) {
						if ($parenthesis) {
							throw new Exception('double open parenthesis');
						} else {
							echo PHP_EOL;
							$parenthesis = true;
						}
					}
					$escape = false;
					break;
				case ')':
					if (!$quote) {
						if ($parenthesis) {
							$parenthesis = false;
						} else {
							throw new Exception('closing parenthesis without open');
						}
					}
					$escape = false;
					break;
				case '\\':
					$escape = !$escape;
					break;
				case "'":
					if ($escape) {
						$escape = false;
					} else {
						$quote = !$quote;
					}
					break;
				default:
					$escape = false;
					break;
			}
			echo $line[$i];
		}
	}
	
	public function backup() {
		
		$n=$this->myConfig->getEnv('database_name');
		$h=$this->myConfig->getEnv('database_host');
		$u=$this->myConfig->getEnv('database_user');
		$p=$this->myConfig->getEnv('database_pass');
		
		
		$cmd="mysqldump";
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') $cmd="\\xampp\\mysql\\bin\\mysqldump.exe";
		$cmd = "$cmd --single-transaction -u $u@$h -p$p $n";
		$this->myLogger->info("Ejecutando comando: '$cmd'");
		$input = popen($cmd, 'r');
		if ($input===FALSE) { $this->errorMsg="adminFunctions::popen() failed"; return null;}
		
		$fname="$n-".date("Ymd_Hi").".sql";
		header('Content-Type: text/plain; charset=utf-8');
		header('Content-Disposition: attachment; filename="'.$fname.'"');
		
		while(!feof($input)) {
			$line = fgets($input);
			if (substr($line, 0, 6) === 'INSERT') {
				$this->process_line($line);
			} else {
				echo $line;
			}
		}
		pclose($input);
		return "ok";
	}	
	
	public function restore() {
		// TODO: write
		return "";
	}
	
	public function factoryReset() {
		// TODO: write
		
		// reset configuration
		// drop database
		// re-create database
		return "";
	}
}

$response="";
try {
	$result=null;
	$adm= new Admin("adminFunctions");
	$am= new AuthManager("adminFunctions");
	$sk=http_request("SessionKey","s","");
	$operation=http_request("Operation","s","");
	if ($operation===null) throw new Exception("Call to adminFunctions without 'Operation' requested");
	switch ($operation) {
		case "backup": /* $am->access(PERMS_ADMIN); */ $result=$adm->backup(); break;
		case "restore": $am->access(PERMS_ADMIN); $result=$adm->restore(); break;
		case "reset": $am->access(PERMS_ADMIN); $result=$adm->factoryReset(); break;
		default: throw new Exception("adminFunctions:: invalid operation: '$operation' provided");
	}
	if ($result===null) // error
		throw new Exception($adm->errormsg);
	if ($result==="") // success
		$response= array('success'=>true); 
	if ($result==="ok") return; // don't generate any aditional response 
	else $response=$result;
} catch (Exception $e) {
	do_log($e->getMessage());
	$response = array('errorMsg'=>$e->getMessage());
	echo json_encode($response);
}
?>