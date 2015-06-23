<?php
/*
print_equiposByJornada.php

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


header('Set-Cookie: fileDownload=true; path=/');
// mandatory 'header' to be the first element to be echoed to stdout

/**
 * genera un pdf ordenado con los participantes en jornada de prueba por equipos
*/

require_once(__DIR__."/fpdf.php");
require_once(__DIR__."/../tools.php");
require_once(__DIR__."/../logging.php");
require_once(__DIR__.'/../database/classes/DBObject.php');
require_once(__DIR__.'/../database/classes/Pruebas.php');
require_once(__DIR__.'/../database/classes/Jornadas.php');
require_once(__DIR__.'/../database/classes/Equipos.php');
require_once(__DIR__."/print_common.php");

class PrintClasificacionEq4 extends PrintCommon {
	protected $equipos; // lista de equipos de esta jornada
    protected $perros; // lista de participantes en esta jornada
    protected $manga; // datos de la manga
    protected $categoria;
	
	// geometria de las celdas
	protected $cellHeader;
    //                      Dorsal  nombre raza licencia Categoria guia club  celo  observaciones
	protected $pos	=array( 10,     25,     27,    10,    18,      40,   25,  10,    25);
	protected $align=array( 'R',    'C',    'R',    'C',  'C',     'R',  'R', 'C',   'R');
	protected $cat=
        array("-" => "","L"=>"Large","M"=>"Medium","S"=>"Small","T"=>"Tiny","LM"=>"Large/Medium","ST"=>"Small/Tiny","MS"=>"Medium/Small");
	
	/**
	 * Constructor
     * @param {integer} $prueba Prueba ID
     * @param {integer} $jornada Jormada ID
     * @param {integer} $manga Manga ID
	 * @throws Exception
	 */
	function __construct($prueba,$jornada,$manga) {
		parent::__construct('Portrait',"print_entradaDeDatosEquipos4",$prueba,$jornada);
		if ( ($prueba<=0) || ($jornada<=0) ) {
			$this->errormsg="print_datosEquipos4: either prueba or jornada data are invalid";
			throw new Exception($this->errormsg);
		}
        // comprobamos que estamos en una jornada por equipos
        $flag=intval($this->jornada->Equipos3)+intval($this->jornada->Equipos4);
        if ($flag==0) {
            $this->errormsg="print_datosEquipos4: Jornada $jornada has no Team competition declared";
            throw new Exception($this->errormsg);
        }
        // guardamos info de la manga
        $this->manga=$this->myDBObject->__getObject("Mangas",$manga);
        // Datos del orden de salida de equipos
        $m = new OrdenSalida("entradaDeDatosEquipos4",$manga);
        $teams= $m->getTeams();
        $this->equipos=$teams['rows'];
        // anyadimos el array de perros del equipo
        foreach($this->equipos as &$equipo) {
            $equipo['Perros']=array();
            $equipo['faltas']=0;
            $equipo['tocados']=0;
            $equipo['rehuses']=0;
            $equipo['eliminados']=0;
            $equipo['nopresentados']=0;
            $equipo['tiempo']=0;
            $equipo['Penalizacion']=0;
        }
        $r= $this->myDBObject->__select("*","Resultados","(Manga=$manga)","","");
        foreach($r['rows'] as $perro) {
            foreach($this->equipos as &$equipo) {
                if ($perro['Equipo']==$equipo['ID']) {
                    array_push($equipo['Perros'],$perro);
                    $equipo['faltas'] +=$perro['Faltas'];
                    $equipo['tocados'] +=$perro['Tocados'];
                    $equipo['rehuses'] +=$perro['Rehuses'];
                    $equipo['eliminados'] +=$perro['Eliminado'];
                    $equipo['nopresentados'] +=$perro['NoPresentado'];
                    $equipo['tiempo'] +=$perro['Tiempo'];
                    $penalizacion=5*$equipo['faltas']+5*$equipo['tocados']+5*$equipo['rehuses']+100*$equipo['eliminados']+200*$equipo['nopresentados'];
                    $equipo['penalizacion'] += $penalizacion;
                    break;
                }
            }
        }
        // finalmente ordenamos los equipos en funcion de penalizacion/tiempo
        usort($this->equipos,function($a,$b){
            return ($a['penalizacion']==$b['penalizacion'])? ($a['tiempo']-$b['tiempo']): ($a['tiempo']-$b['tiempo']);
        });
	}
	
	// Cabecera de página
	function Header() {
		$this->print_commonHeader(_("Result. Parciales (Equipos-4)"));

        // pintamos datos de la jornada
        $this->SetFont('Arial','B',12); // Arial bold 15
        $str  = $this->jornada->Nombre . " - " . $this->jornada->Fecha;
        $this->Cell(90,9,$str,0,0,'L',false);

        // pintamos tipo y categoria de la manga
        $tmanga= Mangas::$tipo_manga[$this->manga->Tipo][1];
        $categoria=$this->cat[$this->categoria];
        $str2 = "$tmanga - $categoria";
        $this->Cell(100,9,$str2,0,0,'R',false); // al otro lado tipo y categoria de la manga
        $this->Ln(10);
        // indicamos nombre del operador que rellena la hoja
        $this->ac_header(2,12);
        $this->Cell(90,7,'Apunta:','LTBR',0,'L',true);
        $this->Cell(10,7,'',0,'L',false);
        $this->Cell(90,7,'Revisa:','LTBR',0,'L',true);
	}
	
	// Pie de página
	function Footer() {
		$this->print_commonFooter();
	}
	
	function printTeamInfo($rowcount,$index,$team,$members) {
        // evaluate logos
        $logos=array('null.png','null.png','null.png','null.png');
        if ($team['Nombre']==="-- Sin asignar --") {
            $logos[0]='agilitycontest.png';
        } else {
            $count=0;
            foreach($members as $miembro) {
                $logo=$this->getLogoName($miembro['Perro']);
                if ( ( ! in_array($logo,$logos) ) && ($count<4) ) $logos[$count++]=$logo;
            }
        }
        // posicion de la celda
        $y=55+16*($rowcount);
        $this->SetXY(10,$y);
        // caja de datos de perros
        $this->ac_header(2,16);
        $this->Cell(12,14,1+$index,'LTB',0,'C',true);
        $this->Cell(48,14,"","TBR",0,'C',true);
        $this->SetY($y+1);
        $this->ac_header(2,16);
        foreach($members as $id => $perro) {
            $this->SetX(22);
            $this->ac_row($id,8);
            $this->Cell(6,3,$perro['Dorsal'],'LTBR',0,'L',true);
            $this->SetFont('Arial','B',8);
            $this->Cell(13,3,$perro['Nombre'],'LTBR',0,'C',true);
            $this->SetFont('Arial','',7);
            $this->Cell(28,3,$perro['NombreGuia'],'LTBR',0,'R',true);
            $this->Ln(3);
        }
        // caja de datos del equipo
        $this->SetXY(70,$y);
        $this->ac_header(1,14);
        $this->Cell(130,14,"","LTBR",0,'C',true);
        $this->SetXY(71,$y+1);
        $this->Cell(10,5,$this->Image(__DIR__.'/../../images/logos/'.$logos[0],$this->getX(),$this->getY(),5),"",0,'C',($logos[0]==='null.png')?true:false);
        $this->Cell(10,5,$this->Image(__DIR__.'/../../images/logos/'.$logos[1],$this->getX(),$this->getY(),5),"",0,'C',($logos[1]==='null.png')?true:false);
        $this->Cell(10,5,$this->Image(__DIR__.'/../../images/logos/'.$logos[2],$this->getX(),$this->getY(),5),"",0,'C',($logos[2]==='null.png')?true:false);
        $this->Cell(10,5,$this->Image(__DIR__.'/../../images/logos/'.$logos[3],$this->getX(),$this->getY(),5),"",0,'C',($logos[3]==='null.png')?true:false);
        $this->Cell(80,5,$team['Nombre'],'',0,'R',true);
        $this->Cell(8,5,'','',0,'',true); // empty space at right of page
        $this->Ln();
        // caja de faltas/rehuses/tiempos
        $this->ac_SetFillColor("#ffffff"); // white background
        $this->SetXY(71,7+$y);
        $this->Cell(15,6,"",'R',0,'L',true);
        $this->Cell(15,6,"",'R',0,'L',true);
        $this->Cell(15,6,"",'R',0,'L',true);
        $this->Cell(15,6,"",'R',0,'L',true);
        $this->Cell(15,6,"",'R',0,'L',true);
        $this->Cell(25,6,"",'R',0,'L',true);
        $this->Cell(28,6,"",'R',0,'L',true);

        $this->ac_SetFillColor("#c0c0c0"); // light gray
        $this->SetXY(71,7+$y+1);
        $this->SetFont('Arial','I',8); // italic 8px
        $this->Cell(15,2.5,"Flt",0,0,'L',false);
        $this->Cell(15,2.5,"Reh",0,0,'L',false);
        $this->Cell(15,2.5,"Toc",0,'L',false);
        $this->Cell(15,2.5,"Elim",0,'L',false);
        $this->Cell(15,2.5,"N.P.",0,'L',false);
        $this->Cell(25,2.5,"Tiempo",0,0,'L',false);
        $this->Cell(28,2.5,"Penaliz.",0,0,'L',false);

        $this->SetXY(71,6+$y+1);
        $this->SetFont('Arial','B',12); // italic 8px
        $this->Cell(15,7,$team['faltas'],0,0,'R',false);
        $this->Cell(15,7,$team['rehuses'],0,0,'R',false);
        $this->Cell(15,7,$team['tocados'],0,0,'R',false);
        $this->Cell(15,7,$team['eliminados'],0,0,'R',false);
        $this->Cell(15,7,$team['nopresentados'],0,0,'R',false);
        $this->Cell(25,7,$team['tiempo'],0,0,'R',false);
        $this->Cell(28,7,$team['penalizacion'],0,0,'R',false);
	}
	
	// Tabla coloreada
	function composeTable() {
		$this->myLogger->enter();
        $bg1=$this->config->getEnv('pdf_rowcolor1');
        $bg2=$this->config->getEnv('pdf_rowcolor2');
        $this->ac_SetFillColor($bg1);
        $this->ac_SetDrawColor($this->config->getEnv('pdf_linecolor'));
		$this->SetLineWidth(.3);

        // take care on RFEC contests
        if ($this->federation->getFederation()==1) {
            $this->pos[1] -= 2;
            $this->pos[2] -= 3;
            $this->pos[3] += 20;
            $this->pos[8] -= 15;
        }
        $index=0;
        $rowcount=0;
        $this->categoria="-";
		foreach($this->equipos as $equipo) {
            $miembros=$equipo['Perros'];
            $num=count($miembros);
            if ($num==0) continue; // skip empty teams
            // 14 teams/page
            if ( ($rowcount%14==0) || ($equipo['Categorias']!=$this->categoria)) {
                $rowcount=0;
                $this->categoria=$equipo['Categorias'];
                $this->AddPage();
            }
            // pintamos el aspecto general de la celda
            $this->printTeamInfo($rowcount,$index,$equipo,$miembros);
            $rowcount++;
            $index++;
		}
		// Línea de cierre
		$this->myLogger->leave();
	}
}

try {
    $result=null;
    $mangas=array();
    $prueba=http_request("Prueba","i",0);
    $jornada=http_request("Jornada","i",0);
    $rondas=http_request("Rondas","i","0"); // bitfield of 512:Esp 256:KO 128:Eq4 64:Eq3 32:Opn 16:G3 8:G2 4:G1 2:Pre2 1:Pre1
    $mangas[0]=http_request("Manga1","i",0); // single manga
    $mangas[1]=http_request("Manga2","i",0); // mangas a dos vueltas
    $mangas[2]=http_request("Manga3","i",0);
    $mangas[3]=http_request("Manga4","i",0); // 1,2:GII 3,4:GIII
    $mangas[4]=http_request("Manga5","i",0);
    $mangas[5]=http_request("Manga6","i",0);
    $mangas[6]=http_request("Manga7","i",0);
    $mangas[7]=http_request("Manga8","i",0);
    $mangas[8]=http_request("Manga9","i",0); // mangas 3..9 are used in KO rondas
    $mode=http_request("Mode","i","0"); // 0:Large 1:Medium 2:Small 3:Medium+Small 4:Large+Medium+Small
    $c= new Clasificaciones("print_clasificacion_pdf",$prueba,$jornada);
    $resultados=$c->clasificacionFinal($rondas,$mangas,$mode);

    // Creamos generador de documento
    $pdf = new PrintClasificacionEq4($prueba,$jornada,$mangas,$resultados,$mode);
    $pdf->AliasNbPages();
    $pdf->composeTable();
    $pdf->Output("print_clasificacion_eq3.pdf","D"); // "D" means open download dialog
} catch (Exception $e) {
    do_log($e->getMessage());
    die ($e->getMessage());
}
?>