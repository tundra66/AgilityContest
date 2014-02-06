<?php

require_once("DBObject.php");
require_once("Mangas.php");

class Jornadas extends DBObject {
	
	protected $prueba; // id de prueba

	/**
	 * Constructor
	 * @param {string} $file caller for this object
	 * @param {integer} $prueba Prueba ID for these jornadas
	 * @throws Exception if cannot contact database
	 */
	function __construct($file,$prueba) {
		parent::__construct($file);
		if ($prueba<=0){
			$this->errormsg="$file::construct() invalid prueba ID";
			throw new Exception($this->errormsg);
		}
		$this->prueba=$prueba;
	}
	
	/**
	 * creacion / borrado de mangas asociadas a una jornada 
	 * @param {integer} $id ID de jornada
	 * @param {integer} $grado1 la jornada tiene(1) o no (0) mangas de grado 1
	 * @param {integer} $grado2 la jornada tiene (1) o no (0) mangas de grado 2
	 * @param {integer} $grado3 la jornada tiene (1) o no (0) mangas de grado 3
	 * @param {integer} $equipos la jornada tiene (1) o no (0) una manga por equipos
	 * @param {integer} $preagility la jornada tiene (1) o no (0) manga de preagility
	 * @param {integer} $ko la jornada contiene (1) o no (0) una prueba k0
	 * @param {integer} $exhibicion la jornada tiene (1) o no (0) mangas de exhibicion
	 * @param {integer} $otras la jornada contiene (1) o no (0) mangas no definidas
	 * // TODO: handle ko, exhibicion and otras
	 */
	function declare_mangas($id,$grado1,$grado2,$grado3,$equipos,$preagility,$ko,$exhibicion,$otras) {
		$this->myLogger->enter();
		$mangas =new Mangas("jornadaFunctions",$id);
		if ($grado1) { 	$mangas->insert('Agility-1 GI','GI'); $mangas->insert('Agility-2 GI','GI');		}
		else { $mangas->delete('Agility-1 GI');	$mangas->delete('Agility-2 GI'); }
		if ($grado2) { $mangas->insert('Agility GII','GII'); $mangas->insert('Jumping GII','GII'); }
		else { $mangas->delete('Agility GII'); $mangas->delete('Jumping GII'); }
		if ($grado3) { $mangas->insert('Agility GIII','GIII'); $mangas->insert('Jumping GIII','GIII'); }
		else { $mangas->delete('Agility GIII');	$mangas->delete('Jumping GIII'); }
		if ($equipos) {	$mangas->insert('Agility Equipos','-');	$mangas->insert('Jumping Equipos','-');	}
		else { $mangas->delete('Agility Equipos');	$mangas->delete('Jumping Equipos');	}
		if ($preagility) { $mangas->insert('Pre-Agility','P.A.'); }
		else { $mangas->delete('Pre-Agility'); }
		if ($exhibicion) { $mangas->insert('Exhibicion','-');}
		else { $mangas->delete('Exhibicion'); }
		// TODO: Decidir que se hace con las mangas 'otras'
		// TODO: las mangas KO hay que crearlas dinamicamente en funcion del numero de participantes
		$this->myLogger->leave();
	}
	
	/***** insert, update, delete, select (by) functions*/
	
	/**
	 * Insert a new jornada into database
	 * @return {string} "" if ok; null on error
	 */
	function insert() {
		$this->myLogger->enter();
		
		// componemos un prepared statement
		$sql ="INSERT INTO Jornadas (Prueba,Nombre,Fecha,Hora,Grado1,Grado2,Grado3,Equipos,PreAgility,KO,Exhibicion,Otras,Cerrada)
			   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?);";
		$stmt=$this->conn->prepare($sql);
		if (!$stmt) return $this->error($this->conn->error); 
		$res=$stmt->bind_param('isssiiiiiiiii',
				$prueba,$nombre,$fecha,$hora,$grado1,$grado2,$grado3,$equipos,$preagility,$ko,$exhibicion,$otras,$cerrada);
		if (!$res) return $this->error($this->conn->error); 
		
		// iniciamos los valores, chequeando su existencia
		$prueba = $this->prueba;
		$nombre = http_request("Nombre","s",null); // Name or comment for jornada
		$fecha = str_replace("/","-",http_request("Fecha","s","")); // mysql requires format YYYY-MM-DD
		$hora = http_request("Hora","s","");
		$grado1 = http_request("Grado1","i",0);
		$grado2 = http_request("Grado2","i",0);
		$grado3 = http_request("Grado3","i",0);
		$equipos = http_request("Equipos","i",0);
		$preagility = http_request("PreAgility","i",0);
		$ko = http_request("KO","i",0);
		$exhibicion = http_request("Exhibicion","i",0);
		$otras = http_request("Otras","i",0);
		$cerrada = http_request("Cerrada","i",0);
		
		$this->myLogger->debug("Prueba: $prueba Nombre: $nombre Fecha: $fecha Hora: $hora");
		
		// invocamos la orden SQL y devolvemos el resultado
		$res=$stmt->execute();
		if (!$res) return $this->error($this->conn->error);
		$stmt->close();
		// retrieve ID on last created jornada
		$jornadaid=$this->conn->insert_id;
		// if not closed ( an stupid thing create a closed jornada, but.... ) create mangas and default team
		if (!$cerrada) {
			// creamos las mangas asociadas a esta jornada
			declare_mangas($jornadaid,$grado1,$grado2,$grado3,$equipos,$preagility,$ko,$exhibicion,$otras);
			$this->myLogger->trace("Declare mangas for jornadaid $jornadaid");
			// create a default team for this jornada
			$res=$this->query("INSERT INTO Equipos (Jornada,Nombre,Observaciones)
					VALUES ($jornadaid,'-- Sin asignar --','NO BORRAR: USADO COMO GRUPO POR DEFECTO PARA LA JORNADA $jornadaid')");
			if (!$res) return $this->error($this->conn->error);
		};
		$this->myLogger->leave();
		return ""; 
	}
	
	function update($jornadaid) {
		$this->myLogger->enter();
		if ($jornadaid<=0) return $this->error("Invalid jornada ID"); 
		// componemos un prepared statement
		$sql ="UPDATE Jornadas
				SET Prueba=?, Nombre=?, Fecha=?, Hora=?, Grado1=?, Grado2=?, Grado3=?,
					Equipos=?, PreAgility=?, KO=?, Exhibicion=?, Otras=?, Cerrada=?
				WHERE ( ID=? );";
		$stmt=$this->conn->prepare($sql);
		if (!$stmt) return $this->error($this->conn->error); 
		$res=$stmt->bind_param('isssiiiiiiiiii',
				$prueba,$nombre,$fecha,$hora,$grado1,$grado2,$grado3,$equipos,$preagility,$ko,$exhibicion,$otras,$cerrada,$id);
		if (!$res) return $this->error($this->conn->error); 
		
		// iniciamos los valores, chequeando su existencia
		$prueba = $this->prueba;
		$nombre = http_request("Nombre","s",null); // Name or comment for jornada
		$fecha = str_replace("/","-",http_request("Fecha","s","")); // mysql requires format YYYY-MM-DD
		$hora = http_request("Hora","s","");
		$grado1 = http_request("Grado1","i",0);
		$grado2 = http_request("Grado2","i",0);
		$grado3 = http_request("Grado3","i",0);
		$equipos = http_request("Equipos","i",0);
		$preagility = http_request("PreAgility","i",0);
		$ko = http_request("KO","i",0);
		$exhibicion = http_request("Exhibicion","i",0);
		$otras = http_request("Otras","i",0);
		$cerrada = http_request("Cerrada","i",0);
		$id= $jornadaid;
		
		$this->myLogger->info("ID: $id Prueba: $prueba Nombre: $nombre Fecha: $fecha Hora: $hora");
		
		// invocamos la orden SQL y devolvemos el resultado
		$res=$stmt->execute();
		if (!$res) return $this->error($this->conn->error); 
		$stmt->close();
		if (!$cerrada) {
			$this->declare_mangas($id,$grado1,$grado2,$grado3,$equipos,$preagility,$ko,$exhibicion,$otras);
		}
		$this->myLogger->leave();
		return "";
	}
	
	/**
	 * Delete jornada with provided name
	 * @param {integer} jornada name primary key
	 * @return "" on success ; otherwise null
	 */
	function delete($jornadaid) {
		$this->myLogger->enter();
		if ($jornadaid<=0) return $this->error("Invalid Jornada ID"); 
		
		// si la jornada esta cerrada en lugar de borrarla la movemos a "-- Sin asignar --"
		// con esto evitamos borrar mangas y resultados ya fijos
		$res= $this->query("UPDATE Jornadas SET Prueba='-- Sin asignar --' WHERE ( (ID=$jornadaid) AND (Cerrada=1) );");
		if (!$res) return $this->error($this->conn->error); 
		
		// si la jornada no está cerrada, directamente la borramos
		// recuerda que las mangas y resultados asociados se borran por la "foreign key"
		$res= $this->query("DELETE FROM Jornadas WHERE ( (ID=$jornadaid) AND (Cerrada=0) );");
		if (!$res)  return $this->error($this->conn->error); 

		$this->myLogger->leave();
	} 
	
	/**
	 * select all jornadas related to provided prueba 
	 * @return unknown
	 */
	function selectByPrueba() {
		$this->myLogger->enter();
		$result = array();
		$items = array();
		
		$str="SELECT count(*) FROM Jornadas WHERE ( Prueba = ".$this->prueba." )";
		$rs=$this->query($str);
		if (!$rs) return $this->error($this->conn->error); 
		$row=$rs->fetch_row();
		$result["total"] = $row[0];
		$rs->free();
		if ($result["total"]>0) {
			$str="SELECT * FROM Jornadas WHERE ( Prueba = ".$this->prueba." ) ORDER BY Numero ASC";
			$rs=$this->query($str);
			if (!$rs) return $this->error($this->conn->error); 
			// retrieve result into an array
			while($row = $rs->fetch_array()){
				array_push($items, $row);
			}
			$rs->free();
		}
		$result["rows"] = $items;
		$this->myLogger->leave();
		return $result;
	}	
	
	/**
	 * search all jornadas related to provided prueba that matches provided criteria 
	 * @return unknown
	 */
	function searchByPrueba() {
		$this->myLogger->enter();
		
		$result = array();
		$items = array();
		// evaluate search terms
		$q=http_request("q","s","");
		$like=")";
		if ($q!=="") $like = " AND ( (Nombre LIKE '%$q%') OR (Numero LIKE '%$q%') ) )";
		
		$str="SELECT count(*) FROM Jornadas WHERE ( ( Prueba = ".$this->prueba." ) AND ( Cerrada=0) $like";
		$rs=$this->query($str);
		if (!$rs) return $this->error($this->conn->error); 
		$row=$rs->fetch_row();
		$result["total"] = $row[0];
		$rs->free();
		if ($result["total"]>0) {
			$str="SELECT * FROM Jornadas WHERE ( ( Prueba = ".$this->prueba." ) AND ( Cerrada=0 ) $like ORDER BY Numero ASC";
			$rs=$this->query($str);
			if (!$rs)  return $this->error($this->conn->error); 
			// retrieve result into an array
			while($row = $rs->fetch_array()){
				array_push($items, $row);
			}
			$rs->free();
		}
		$result["rows"] = $items;
		$this->myLogger->leave();
		return $result;
	}
}
?>