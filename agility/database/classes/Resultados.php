<?php
require_once("DBObject.php");
require_once("OrdenSalida.php");
require_once("Mangas.php");
require_once("Jornadas.php");

class Resultados extends DBObject {
	protected $manga; // datos de la manga
	protected $IDManga; // ID de la manga
	protected $jornada;  // datos de la jornada
	protected $IDJornada; // ID de la jornada
	protected $cerrada; // indica si la jornada esta cerrada

	/**
	 * Constructor
	 * @param {string} $file caller for this object
	 * @param {string} $manga Manga ID
	 * @throws Exception when
	 * - cannot contact database 
	 * - invalid manga ID
	 * - manga is closed
	 */
	function __construct($file,$manga) {
		parent::__construct($file);
		if ($manga<=0) {
			$this->errormsg="Resultados::Construct invalid Manga ID: $manga";
			throw new Exception($this->errormsg);
		}
		$this->IDManga=$manga;
		// obtenemos el id de jornada y vemos si la manga esta cerrada
		$obj=$this->__selectObject("*", "Mangas", "(ID=$manga)");
		if (!$obj) {
			$this->error("Manga $manga does not exists in database");
			throw new Exception($this->errormsg);
		}
		$this->manga=$obj;
		$this->IDJornada=$this->manga->Jornada;
		$obj=$this->__selectObject("*","Jornadas","(ID=$this->IDJornada)");
		if (!$obj) {
			$this->error("Cannot locate JornadaID: $this->IDJornada for MangaID:$manga in database");
			throw new Exception($this->errormsg);
		}
		$this->jornada=$obj;
		$this->cerrada=$this->jornada->Cerrada;
	}
		

	/**
	 * Inserta perro en la lista de resultados de la manga
	 * los datos del perro se toman de la tabla perroguiaclub
	 * @param {array} $objperro datos perroguiaclub
	 * @param {integer} $ndorsal Dorsal con el que compite
	 * @return "" on success; else error string
	 */
	function insertByData($objperro,$ndorsal) {
		$error="";
		$idmanga=$this->IDManga;
		$this->myLogger->enter();
		if ($ndorsal<=0) return $this->error("No dorsal specified");
		if ($this->cerrada!=0) 
			return $this->error("Manga $idmanga comes from closed Jornada: $this->IDJornada");	
		
		// Insert into resultados. On duplicate ($manga,$idperro) key ignore
		$sql="INSERT INTO Resultados (Manga,Dorsal,Perro,Nombre,Licencia,Categoria,Grado,NombreGuia,NombreClub) 
				VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE Manga=Manga";
		$stmt=$this->conn->prepare($sql);
		if (!$stmt) return $this->conn->error;
		$res=$stmt->bind_param('iiissssss',$manga,$dorsal,$perro,$nombre,$licencia,$categoria,$grado,$guia,$club);
		if (!$res) return $this->error($stmt->error);
		$manga=$idmanga;
		$dorsal=$ndorsal;
		$perro=$objperro['ID'];
		$nombre=$objperro['Nombre'];
		$licencia=$objperro['Licencia'];
		$categoria=$objperro['Categoria'];
		$grado=$objperro['Grado'];
		$guia=$objperro['NombreGuia'];
		$club=$objperro['NombreClub'];
		// ejecutamos el query
		$res=$stmt->execute();
		if (!$res) $error=$stmt->error;
		$stmt->close();
		$this->myLogger->leave();
		return $error;
	}
	
	/**
	 * Inserta perro en la lista de resultados de la manga
	 * @param {integer} $integer ID del perro
	 * @param {integer} $ndorsal Dorsal con el que compite
	 * @return "" on success; else error string
	 */
	function insert($idperro,$ndorsal) {
		// obtenemos los datos del perro
		$pobj=new Dogs("Resultados::insert");
		$perro=$pobj->selectByIDPerro($iderro);
		if (!$perro) throw new Exception("No hay datos para el perro a inscribir con id: $idp");
		return insertByData($perro,$ndorsal);
	}
	
	/**
	 * Borra el idperro de la lista de resultados de la manga
	 * @param {integer} $idperro
	 * @return "" on success; null on error
	 */
	function delete($idperro) {
		$this->myLogger->enter();
		$idmanga=$this->IDManga;
		if ($idperro<=0) return $this->error("No Perro ID specified");
		if ($this->cerrada!=0) 
			return $this->error("Manga $idmanga comes from closed Jornada:$this->IDJornada");
		$str="DELETE * FROM Resultados WHERE ( Perro=$idperro ) AND ( Manga=$idmanga)";
		$rs=$this->query($str);
		if (!$rs) return $this->error($this->conn->error);
		$this->myLogger->leave();
		return "";
	}
	
	/**
	 * selecciona los datos del idperro indicado desde la lista de resultados de la manga
	 * @param {integer} $idperro
	 * @return {array} [key=>value,...] on success; null on error
	 */
	function select($idperro) {
		$this->myLogger->enter();
		$idmanga=$this->IDManga;
		if ($idperro<=0) return $this->error("No Perro ID specified");
		$row=$this->__singleSelect("*", "Resultados", "(Perro=$idperro) AND (Manga=$idmanga)");
		if(!$row) return $this->error("No Results for Perro:$idperro on Manga:$idmanga");
		$this->myLogger->leave();
		return $row;
	}
	
	/**
	 * Actualiza los resultados de la manga para el idperro indicado
	 * @param {integer} $idperro
	 * @return datos actualizados desde la DB; null on error
	 */
	function update($idperro) {
		$this->myLogger->enter();
		$idmanga=$this->IDManga;
		if ($idperro<=0) return $this->error("No Perro ID specified");
		if ($this->cerrada!=0) 
			return $this->error("Manga $idmanga comes from closed Jornada: $this->IDJornada");
		// buscamos la lista de parametros a actualizar
		$entrada=http_request("Entrada","s",date("Y-m-d H:i:s"));
		$comienzo=http_request("Comienzo","s",date("Y-m-d H:i:s"));
		$faltas=http_request("Faltas","i",0);
		$rehuses=http_request("Rehuses","i",0);
		$tocados=http_request("Tocados","i",0);
		$nopresentado=http_request("NoPresentado","i",0);
		$eliminado=http_request("Eliminado","i",0);
		$tiempo=http_request("Tiempo","d",0.0);
		$observaciones=http_request("Observaciones","s","");
		// comprobamos la coherencia de los datos recibidos y ajustamos
		// NOTA: el orden de estas comprobaciones es MUY importante
		if ($rehuses>=3) { $tiempo=0; $eliminado=1; $nopresentado=0;}
		if ($tiempo>0) {$nopresentado=0;}
		if ($eliminado==1) { $tiempo=0; $nopresentado=0; }
		if ($nopresentado==1) { $tiempo=0; $eliminado=0; $faltas=0; $rehuses=0; $tocados=0; }
		if ( ($tiempo==0) && ($eliminado==0)) { $nopresentado=1; $faltas=0; $rehuses=0; $tocados=0; }
		if ( ($tiempo==0) && ($eliminado==1)) { $nopresentado=0; }
		// efectuamos el update, marcando "pendiente" como false
		$sql="UPDATE Resultados 
			SET Entrada='$entrada' , Comienzo='$comienzo' , 
				Faltas=$faltas , Rehuses=$rehuses , Tocados=$tocados ,
				NoPresentado=$nopresentado , Eliminado=$eliminado , 
				Tiempo=$tiempo , Observaciones='$observaciones' , Pendiente=0
			WHERE (Perro=$idperro) AND (Manga=$this->IDManga)";
		$rs=$this->query($sql);
		if (!$rs) return $this->error($this->conn->error);
		$this->myLogger->leave();
		return $this->select($idperro);
	}
	
	/**
	 * Presenta una tabla ordenada segun los resultados de la manga
	 * @return null on error else array en formato easyui datagrid
	 */
	function getResultados() {
		// TODO: write
	}
}
?>