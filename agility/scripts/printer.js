/*
printer.js

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

/**
 * Funciones relacionadas con gestion de impresos del desarrollo de la competicion
 */

/************************** programa de actividades **********/

/**
 * Imprime la secuencia de tandas de la jornada
 */
function printOrdenTandas() {
    $.fileDownload(
        '/agility/server/pdf/print_ordenTandas.php',
        {
            httpMethod: 'GET',
            data: {
                Prueba: workingData.prueba,
                Jornada: workingData.jornada
            },
            preparingMessageHtml: "We are preparing your report, please wait...",
            failMessageHtml: "There was a problem generating your report, please try again."
        }
    );
    return false; //this is critical to stop the click event which will trigger a normal file download!
}

/************************** impresion del orden de salida **********/

/**
 * manda a la impresora el orden de salida
 * @returns {Boolean}
 */
function printOrdenSalida() {
    $.fileDownload(
        '/agility/server/pdf/print_ordenDeSalida.php',
        {
            httpMethod: 'GET',
            data: {
                Prueba: workingData.prueba,
                Jornada: workingData.jornada,
                Manga: workingData.manga
            },
            preparingMessageHtml: "Imprimiendo orden de salida; por favor espere...",
            failMessageHtml: "There was a problem generating your report, please try again."
        }
    );
    return false; //this is critical to stop the click event which will trigger a normal file download!
}

/*************************** impresion de la hoja de calculo de TRS ********/

/**
 * Prints TRS templates
 * @param mode // 0: Distance/Velocity=TRS dataSheet 1: TRS List template
 * @returns {boolean} False to avoid key binding event chaining
 */
function print_trsTemplates(mode) {

    $.messager.radio(
        'Selecciona modelo',
        'Indica el tipo de plantilla que quieres generar:',
        {0:'Hoja de calculo para evaluar el TRS y TRM',1:'Hoja para apuntar datos de las mangas'},
        function(r){
            if (!r) return;
            $.fileDownload(
                '/agility/server/pdf/print_trsTemplates.php',
                {
                    httpMethod: 'GET',
                    data: {
                        Prueba: workingData.prueba,
                        Jornada: workingData.jornada,
                        Manga: workingData.manga,
                        Mode: r
                    },
                    preparingMessageHtml: "Imprimiendo orden de salida; por favor espere...",
                    failMessageHtml: "There was a problem generating your report, please try again."
                }
            );
        }).window('resize',{width:400});
    return false; //this is critical to stop the click event which will trigger a normal file download!
}

/************************** Hojas del asistente del juez ****************/
function print_asistente() {
    $.messager.radio(
        'Selecciona formulario',
        'Indica el tipo de formulario que quieres generar:',
        {1:'Extendido (1 perro/pagina)',5:'Normal (5 perros/pagina)',10:'Compacto (10 perros/pagina)'},
        function(r){
            if (!r) return;
            $.fileDownload(
                '/agility/server/pdf/print_entradaDeDatos.php',
                {
                    httpMethod: 'GET',
                    data: {
                        Prueba: workingData.prueba,
                        Jornada: workingData.jornada,
                        Manga: workingData.manga,
                        Mode: r
                    },
                    preparingMessageHtml: "We are preparing your report, please wait...",
                    failMessageHtml: "There was a problem generating your report, please try again."
                }
            );
        }).window('resize',{width:300});
    return false; //this is critical to stop the click event which will trigger a normal file download!
}

/********************** impresion de datos parciales ***************/

function printParcial(mode) {
    var url='/agility/server/pdf/print_resultadosByManga.php';
    if ( parseInt(workingData.datosJornada.Equipos3)!=0)
        url='/agility/server/pdf/print_resultadosByEquipos.php';
	$.fileDownload(
		url,
		{
			httpMethod: 'GET',
			data: { 
				Prueba: workingData.prueba,
				Jornada: workingData.jornada,
				Manga: workingData.manga,
				Mode: mode,
				Operation: 'print'
			},
	        preparingMessageHtml: "We are preparing your report, please wait...",
	        failMessageHtml: "There was a problem generating your report, please try again."
		}
	);
}

/**
 * imprime los resultados de la manga/categoria solicitadas
 * @param val 0:L 1:M 2:S 3:T
 */
function checkAndPrintParcial(val) {
	var value=parseInt(val); // stupid javascript!!
	// obtenemos informacion sobre los datos a imprimir
	var mode=getMangaMode(workingData.datosPrueba.RSCE,workingData.datosManga.Recorrido,value);
	$.ajax({
		type:'GET',
		url:"/agility/server/database/resultadosFunctions.php",
		dataType:'json',
		data: {	Operation:'getPendientes', Prueba:workingData.prueba, Jornada:workingData.jornada, Manga:workingData.manga, Mode: mode },
		success: function(data) {
			if (parseInt(data['total'])==0) {
				// No hay perros pendientes de salir: imprimimos los datos de la manga y categoria solicitada
				printParcial(mode);
			} else {
				var str="<h3>Perros pendientes de introducci&oacute;n de datos:</h3>";
				str +="<table><tr><th>Dorsal</th><th>Perro</th><th>Gu&iacute;a</th><th>Club</th></tr>";
				// componemos mensaje de error
				$.each(
					data['rows'],
					function(index,val) {
						str+="<tr><td>"+val['Dorsal']+"</td><td>"+val['Nombre']+"</td><td>"+val['NombreGuia']+"</td><td>"+val['NombreClub']+"</td></tr>";
					}
				);
				str+="</table><br />Imprimir de todos modos?";
				var w=$.messager.confirm('Datos no v&aacute;lidos',str,function(r){if (r) printParcial(mode);});
				w.window('resize',{width:550}).window('center');
			}
		}
	});
    return false; //this is critical to stop the click event which will trigger a normal file download!
}

/******************************** Datos de clasificacion general **********************/

/**
 * Imprime una hoja con los podio de esta ronda
 */
function clasificaciones_printPodium() {
	var ronda=$('#resultados-info-ronda').combogrid('grid').datagrid('getSelected');
	var url='/agility/server/pdf/print_podium.php';
    if (isJornadaEq3()) url='/agility/server/pdf/print_podium_eq3.php';
    if (isJornadaEq4()) url='/agility/server/pdf/print_podium_eq4.php';
	if (ronda==null) {
    	$.messager.alert("Error:","!No ha seleccionado ninguna ronda de esta jornada!","warning");
    	return false; // no way to know which ronda is selected
	}
	$.fileDownload(
		url,
		{
			httpMethod: 'GET',
			data: { 
				Prueba:workingData.prueba,
				Jornada:workingData.jornada,
				Manga1:ronda.Manga1,
				Manga2:ronda.Manga2,
				Rondas: ronda.Rondas
			},
	        preparingMessageHtml: "Generando PDF con los podios. Por favor, espere...",
	        failMessageHtml: "Ha habido problemas en la generacion del formulario\n. Por favor, intentelo de nuevo."
		}
	);
    return false; //this is critical to stop the click event which will trigger a normal file download!
}

/**
 * Imprime los resultados finales separados por categoria y grado, tal y como pide la RSCE
 */
function clasificaciones_printCanina() {
	// Client-side excel conversion
	// $('#resultados-datagrid').datagrid('toExcel',"clasificaciones.xls");
	
	// Server-side excel generation
	var ronda=$('#resultados-info-ronda').combogrid('grid').datagrid('getSelected');
	var url='/agility/server/pdf/print_clasificacion_excel.php';
	var mode=$('#resultados-selectCategoria').combobox('getValue');
	if (ronda==null) {
    	$.messager.alert("Error:","!No ha seleccionado ninguna ronda de esta jornada!","warning");
    	return false; // no way to know which ronda is selected
	}
	$.fileDownload(
		url,
		{
			httpMethod: 'GET',
			data: { 
				Prueba:workingData.prueba,
				Jornada:workingData.jornada,
				Manga1:ronda.Manga1,
				Manga2:ronda.Manga2,
				Rondas: ronda.Rondas,
				Mode: mode
			},
	        preparingMessageHtml: "Generando fichero Excel con las clasificaciones. Por favor, espere...",
	        failMessageHtml: "Ha habido problemas en la generacion del fichero\n. Por favor, intentelo de nuevo."
		}
	);
    return false; //this is critical to stop the click event which will trigger a normal file download!
}

/**
 * Imprime los resultados finales de la ronda seleccionada en formato CSV para su conversion en etiquetas
 * @param {int} flag 0:CSV 1:PDF
 * @param {int} start if mode==PDF first line in output
 * @param {string} list CSV dorsal list
 * @returns {Boolean} false 
 */
function clasificaciones_printEtiquetas(flag,start,list) {
	var ronda=$('#resultados-info-ronda').combogrid('grid').datagrid('getSelected');
	var url='/agility/server/pdf/print_etiquetas_csv.php';
	if (flag!=0) url='/agility/server/pdf/print_etiquetas_pdf.php';
	var mode=$('#resultados-selectCategoria').combobox('getValue');
	var strt=parseInt(start)-1;
	if (ronda==null) {
    	$.messager.alert("Error:","!No ha seleccionado ninguna ronda de esta jornada!","warning");
    	return false; // no way to know which ronda is selected
	}
	$.fileDownload(
		url,
		{
			httpMethod: 'GET',
			data: { 
				Prueba:workingData.prueba,
				Jornada:workingData.jornada,
				Manga1:ronda.Manga1,
				Manga2:ronda.Manga2,
				Rondas: ronda.Rondas,
				Mode: mode,
				Start: strt,
				List: list
			},
	        preparingMessageHtml: "Generando formulario con las etiquetas. Por favor, espere...",
	        failMessageHtml: "Ha habido problemas en la generacion del formulario\n. Por favor, intentelo de nuevo."
		}
	);
    return false; //this is critical to stop the click event which will trigger a normal file download!
}

/**
 * Imprime los resultados finales de la ronda seleccionada en formato pdf
 * @return false
 */
function clasificaciones_printClasificacion() {
	var ronda=$('#resultados-info-ronda').combogrid('grid').datagrid('getSelected');
	var url='/agility/server/pdf/print_clasificacion.php';
    if (isJornadaEq3()) url='/agility/server/pdf/print_clasificacion_eq3.php';
    if (isJornadaEq4()) url='/agility/server/pdf/print_clasificacion_eq4.php';
	var mode=$('#resultados-selectCategoria').combobox('getValue');
	if (ronda==null) {
    	$.messager.alert("Error:","!No ha seleccionado ninguna ronda de esta jornada!","warning");
    	return false; // no way to know which ronda is selected
	}
	$.fileDownload(
		url,
		{
			httpMethod: 'GET',
			data: { 
				Prueba:workingData.prueba,
				Jornada:workingData.jornada,
				Manga1:ronda.Manga1,
				Manga2:ronda.Manga2,
				Rondas: ronda.Rondas,
				Mode: mode
			},
	        preparingMessageHtml: "Generando PDF con las clasificaciones. Por favor, espere...",
	        failMessageHtml: "Ha habido problemas en la generacion del formulario\n. Por favor, intentelo de nuevo."
		}
	);
    return false; //this is critical to stop the click event which will trigger a normal file download!
}

/**
 * Ajusta el menu de seleccion de metodo de impresion en funcion de la opcion seleccionada
 */
function r_selectOption(val) {
    var prfirst= $('#r_prfirst');
    var prlist=$('#r_prlist');
	switch (parseInt(val)) {
	case 0:
	case 1:
	case 3:
	case 4: prfirst.numberspinner('disable'); prlist.numberspinner('disable'); break;
	case 2: prfirst.numberspinner('enable'); prlist.numberspinner('disable'); break;
	case 5: prfirst.numberspinner('enable'); prlist.numberspinner('enable'); break;
	}
}

/**
 * Presenta un menu al usuario indicando que es lo que se quiere imprimir
 */
function clasificaciones_doPrint() {
	var r=$('input:radio[name="r_prformat"]:checked').val();
	var line=$('#r_prfirst').numberspinner('getValue');
	var list=$('#r_prlist').textbox('getValue');
	$('#resultados-printDialog').dialog('close');
	switch(parseInt(r)) {
		case 0: clasificaciones_printPodium(); break;
		case 1: clasificaciones_printEtiquetas(0); break; // csv
		case 3: clasificacioness_printCanina(); break;
		case 4: clasificaciones_printClasificacion(); break;
		case 5: clasificaciones_printEtiquetas(1,line,list); break;
		case 2: clasificaciones_printEtiquetas(1,line,''); break;
	}
	return false; //this is critical to stop the click event which will trigger a normal file download!
}