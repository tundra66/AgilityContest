<?php

require_once("logging.php");
require_once("tools.php");
require_once("classes/Dogs.php");

try {
	$result=null;
	$perros= new Dogs("dogFunctions");
	$operation=http_request("Operation","s",null);
	$idperro=http_request("ID","i",0);
	$idguia=http_request("Guia","i",0);
	if ($operation===null) throw new Exception("Call to dogFunctions without 'Operation' requested");
	switch ($operation) {
		case "insert": $result=$perros->insert(); break;
		case "update": $result=$perros->update($idperro); break;
		case "delete": $result=$perros->delete($idperro); break;
		case "orphan": $result=$perros->orphan($idperro); break; // unassign from handler
		case "select": $result=$perros->select(); break; // list with order, index, count and where
		case "enumerate":	$result=$perros->enumerate(); break; // list with where
		case "getbyguia":	$result=$perros->selectByGuia($idguia); break;
		case "getbyidperro":	$result=$perros->selectByID($idperro); break;
		case "categorias":	$result=$perros->categoriasPerro(); break;
		case "grados":		$result=$perros->gradosPerro(); break;
		default: throw new Exception("dogFunctions:: invalid operation: $operation provided");
	}
	if ($result===null) throw new Exception($perros->errormsg);
	if ($result==="") echo json_encode(array('success'=>true));
	else echo json_encode($result);
} catch (Exception $e) {
	do_log($e->getMessage());
	echo json_encode(array('errorMsg'=>$e->getMessage()));
}

?>