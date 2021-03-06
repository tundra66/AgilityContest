<?php
/*
print_listaPerros.php

Copyright  2013-2016 by Juan Antonio Martinez ( juansgaviota at gmail dot com )

This program is free software; you can redistribute it and/or modify it under the terms 
of the GNU General Public License as published by the Free Software Foundation; 
either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; 
if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

/**
 * genera fichero excel de perros seleccionada desde el menu de la base de datos en el orden especificado en la pantalla
*/

require_once(__DIR__."/../tools.php");
require_once(__DIR__."/../logging.php");
require_once(__DIR__.'/../database/classes/Pruebas.php');
require_once(__DIR__.'/../database/classes/Jornadas.php');
require_once(__DIR__.'/../database/classes/Inscripciones.php');
require_once(__DIR__."/common_writer.php");

class Excel_Inscripciones extends XLSX_Writer {

	protected $jornadas=array(); // lista de jornadas de la prueba
    protected $club=-1; // -1:inscriptiones 0:header x:template for club x

    protected $cols = array(
		'Dorsal',
		'Name','Pedigree Name','Gender','Breed','License','KC id','Category','Grade','Handler','Club','Country', // datos del perro
		'Heat','Comments' // Jornada1, Jornada2, // datos de la inscripcion en la jornada
	);
    protected $fields = array(
		'Dorsal',
		'Nombre','NombreLargo','Genero','Raza','Licencia','LOE_RRC','Categoria','Grado','NombreGuia','NombreClub','Pais', // datos del perro
		'Celo','Observaciones' //, Equipo1, Equipo2 .... // datos de la inscripcion en la jornada
	);

	/**
	 * Constructor
	 * @param {int} $prueba Prueba ID
	 * @throws Exception
	 */
	function __construct($prueba,$club) {
		parent::__construct("inscriptionlist.xlsx");
        $this->club=intval($club);
		setcookie('fileDownload','true',time()+30,"/"); // tell browser to hide "downloading" message box
        $p=new Pruebas("excel_Inscripciones");
        $res=$p->selectByID($prueba);
        if (!is_array($res)){
			$this->errormsg="excel_InscriptionList: getPruebaByID($prueba) failed";
			throw new Exception($this->errormsg);
		}
        $this->prueba=$res;
		$j=new Jornadas("excel_Inscripciones",$prueba);
		$res=$j->selectByPrueba();
		if (!is_array($res)){
			$this->errormsg="excel_InscriptionList: getJornadasByPrueba($prueba) failed";
			throw new Exception($this->errormsg);
		}
		$this->jornadas=$res['rows'];
	}

	private function writeTableHeader() {
		// internationalize header texts
		for($n=0;$n<count($this->cols);$n++) {
			$this->cols[$n]=_utf($this->cols[$n]);
		}
		// send to excel
		$this->myWriter->addRowWithStyle($this->cols,$this->rowHeaderStyle);
	}

	function composeTable() {
		$this->myLogger->enter();
		$this->createInfoPage(_utf('Inscription List'),$this->prueba['RSCE']);
		$this->createPruebaInfoPage($this->prueba,$this->jornadas);
		// evaluate journeys to be added as excel column
		foreach ($this->jornadas as $jornada) {
			if ($jornada['Nombre']==='-- Sin asignar --') continue; // skip empty journeys
			array_push($this->cols,$jornada['Nombre']);
			array_push($this->fields,"J".$jornada['Numero']);
		}
		// add "pagado" at the end
		array_push($this->cols,_utf('Paid'));
		array_push($this->fields,'Pagado');

		// Create page
		$journeypage=$this->myWriter->addNewSheetAndMakeItCurrent();
		$name=$this->normalizeSheetName(_utf('Inscriptions'));
		$journeypage->setName($name);

		// write header
		$this->writeTableHeader();

        if ($this->club==-1) return; // just print header
        $insc=new Inscripciones("excel_printInscripciones",$this->prueba['ID']);
        if ($this->club==0) { // retrieve inscription list
            $lista=$insc->enumerate()['rows'];
        } else { // retrieve dog list from provided club
            $res=$insc->__select(
                "*",
                "PerroGuiaClub",
                "(Club={$this->club}) && (Federation={$this->prueba['RSCE']})",
                "Categoria ASC, Grado ASC, Nombre ASC",
                "");
            $lista=$res['rows'];
        }
		foreach ($lista as $perro) {
		    if ( ($this->club>0) && ($this->club!=$perro['Club']) ) continue;
            $strip=($this->club>0)?true:false;
			$row=array();
			$row[]=($this->club>0)? 0 : $perro['Dorsal'];
			$row[]=$perro['Nombre'];
			$row[]=$perro['NombreLargo'];
			$row[]=$perro['Genero'];
			$row[]=$perro['Raza'];
			$row[]=$perro['Licencia'];
			$row[]=$perro['LOE_RRC'];
			$row[]=$perro['Categoria'];
			$row[]=$perro['Grado'];
			$row[]=$perro['NombreGuia'];
			$row[]=$perro['NombreClub'];
			$row[]=$perro['Pais'];
			$row[]=($this->club>0)? "" : ($perro['Celo']==1)?"X":"";
			$row[]=($this->club>0)? "" : $perro['Observaciones'];
			// aniadimos info de jornadas (inscrito/equipo
			foreach ($this->jornadas as $jornada) {
				if ($jornada['Nombre']==='-- Sin asignar --') continue; // skip empty journeys
                if ($this->club>0) { $row[]=""; continue; } // in club template mode just print empty field
				if ($perro['J'.$jornada['Numero']]==0) { // el perro no esta inscrito en la jornada
					$row[]="";
				}
				// si Estamos en una jornada por equipos, ponemos el nombre del equipo
				// else ponemos "X"
				else {	// perro inscrito en la jornada. buscamos equipo. Si no default se pone nombre, else "X"
					$eqobj=new Equipos("excel_printInscripciones",$this->prueba['ID'],$jornada['ID']);
					$equipo=$eqobj->getTeamByPerro($perro['Perro']);
					if( ($jornada['Equipos3']!=0) || ($jornada['Equipos4']!=0) ) $row[]=$equipo['Nombre'];
					else $row[]="X";
				}
			}
			// finalmente informacion de pago
			$row[]=($this->club>0)? 0 : $perro['Pagado'];
			// !!finaly!! add perro to excel table
			$this->myWriter->addRow($row);
		}
		$this->myLogger->leave();
	}
}

// Consultamos la base de datos
try {
	// 	Creamos generador de documento
    $prueba=http_request("Prueba","i",-1);
    $club=http_request("Club","i",0); // -1:empty template 0:inscriptions x:club template
	$excel = new Excel_Inscripciones($prueba,$club);
	$excel->open();
	$excel->composeTable();
	$excel->close();
    return 0;
} catch (Exception $e) {
	die ("Error accessing database: ".$e->getMessage());
}
?>