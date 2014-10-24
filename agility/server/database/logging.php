<?php

define ("LEVEL_PANIC",0);
define ("LEVEL_ALERT",1);
define ("LEVEL_ERROR",2);
define ("LEVEL_WARN",3);
define ("LEVEL_NOTICE",4);
define ("LEVEL_INFO",5);
define ("LEVEL_DEBUG",6);
define ("LEVEL_TRACE",7);

define ("LEVEL_ALL",8);
define ("LEVEL_NONE",-1);

class Logger {
	private $basename;
	private $level;
	private static $levels= array("PANIC","ALERT","ERROR","WARN","NOTICE","INFO","DEBUG","TRACE");
		
	function __construct($name,$level=LEVEL_ALL) {
		$this->basename=$name;
		$this->level=$level;
	}	

	function log($level,$msg) {
		if ($level>$this->level) return;
		$trace=debug_backtrace();
		$str=Logger::$levels[$level]." ".$trace[2]['file']."::".$trace[2]['line']."::".$trace[2]['function']."() : ".$msg;
		error_log($str);
		return $str;
	}
	
	function trace($msg) { return $this->log(LEVEL_TRACE,$msg); }
	function debug($msg) { return $this->log(LEVEL_DEBUG,$msg); }
	function info($msg)  { return $this->log(LEVEL_INFO,$msg); }
	function notice($msg) { return $this->log(LEVEL_NOTICE,$msg); }
	function warn($msg) { return $this->log(LEVEL_WARN,$msg); }
	function error($msg) { return $this->log(LEVEL_ERROR,$msg); }
	function alert($msg) { return $this->log(LEVEL_ALERT,$msg); }
	function panic($msg) { die ($this->log(LEVEL_PANIC,$msg)); }

	function enter() { return ($this->log(LEVEL_TRACE,"Enter")); }
	function leave() { return ($this->log(LEVEL_TRACE,"Leave")); }

	function query($msg) {
		if ($this->level<=LEVEL_INFO) return;
		$trace=debug_backtrace();
		$str="QUERY ".$this->basename."::".$trace[2]['function']."() :\n".$msg;
		error_log($str);
		return $str;
	}
}

function do_log($str) { 
	error_log($str); 
}

date_default_timezone_set("Europe/Madrid");
// ini_set('display_errors', 1); /* dont send errors to http response */
ini_set("log_errors",1);
ini_set("error_log",__DIR__."/../../../logs/trace.log");

apache_setenv('no-gzip', 1);
ini_set('zlib.output_compression', 0);
?>