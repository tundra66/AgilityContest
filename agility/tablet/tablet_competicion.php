<!-- 
tablet_competicion.php

Copyright 2013-2015 by Juan Antonio Martinez ( juansgaviota at gmail dot com )

This program is free software; you can redistribute it and/or modify it under the terms 
of the GNU General Public License as published by the Free Software Foundation; 
either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; 
if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 -->

<?php 
include_once(__DIR__."/tablet_entradadatos.inc");
require_once(__DIR__."/../server/tools.php");
require_once(__DIR__."/../server/auth/Config.php");
$config =Config::getInstance();
?>
		
<div id="tablet-window" style="margin:0;padding:0">
	<!-- toolbar para orden de tandas -->
	<div id="tablet-toolbar" style="padding:5px">
		<a id="tablet-reloadBtn" href="#" class="easyui-linkbutton" 
			data-options="iconCls:'icon-reload'" onclick="$('#tablet-datagrid').datagrid('reload');">Actualizar</a>
	</div>
	<!-- Tabla desplegable para la entrada de datos desde el tablet -->
	<table id="tablet-datagrid" style="margin:0;padding:0;"></table>
</div> <!-- tandas / orden de salida -->
		
<script type="text/javascript">

$('#tablet-reloadBtn').linkbutton();

$('#tablet-window').window({
	title: 'Programa de la jornada - '+workingData.datosSesion.Nombre,
	fit: true,
	collapsible:	false,
	minimizable:	false,
	maximizable:	false,
	resizable:		false,
	closable:		false,
	iconCls:		'icon-order',
	maximized:		true,
	closed:			false,
	modal:			false
});

$('#tablet-datagrid').datagrid({
	// propiedades del panel asociado
    expandedRow: -1, // added by jamc
	fit: true,
	border: false,
	closable: false,
	collapsible: false,
	collapsed: false,
	// propiedades del datagrid
	method: 'get',
	url: '/agility/server/database/tandasFunctions.php',
    queryParams: {
        Operation: 'getTandas',
        Prueba: workingData.prueba,
		Jornada: workingData.jornada,
		Sesion: workingData.sesion
    },
	toolbar:'#tablet-toolbar',
    loadMsg: "Actualizando programa ...",
    pagination: false,
    rownumbers: true,
    fitColumns: true,
    singleSelect: true,
    autoRowHeight: false,
    view: detailview,
    pageSize: 100, // enought bit to make it senseless
    columns:[[ 
          	{ field:'ID',		hidden:true },
			{ field:'Sesion',	hidden:true },
        	{ field:'Prueba',	hidden:true },
          	{ field:'Jornada',	hidden:true },
          	{ field:'Manga',	hidden:true },
      		{ field:'From',		hidden:true },
      		{ field:'To',		hidden:true },
      		{ field:'Nombre',	width:200, sortable:false, align:'ledt',title:'Secuencia de salida a pista',styler:tandasStyler},
      		{ field:'Categoria',hidden:true },
      		{ field:'Grado',	hidden:true }
    ]],
    rowStyler: myRowStyler,            
    // especificamos un formateador especial para desplegar la tabla de perros por tanda
    detailFormatter:function(idx,row){
        var dg="tablet-datagrid-" + parseInt(row.ID);
        return '<div style="padding:2px"><table id="' + dg + '"></table></div>';
    },
    onClickRow: function(idx,row) { 
        doBeep(); 
        tablet_updateSession(row);
    },
    onExpandRow: function(idx,row) { 
        doBeep();
        var dg=$('#tablet-datagrid');
		// collapse previous expanded row
        var oldRow=dg.datagrid('options').expandedRow;
        if ( (oldRow!=-1) && (oldRow!=idx) )  dg.datagrid('collapseRow',oldRow);
        dg.datagrid('options').expandedRow=idx;
        // update session data
        tablet_updateSession(row);
        if (row.Tipo!=0) tablet_showPerrosByTanda(idx,row);
    },
    onCollapseRow: function(idx,row) {
        var dg="tablet-datagrid-" + parseInt(row.ID);
        $(dg).remove();
        doBeep();
    }
});

// mostrar los perros de una tanda
function tablet_showPerrosByTanda(index,row){ 
	// - sub tabla orden de salida de una tanda
    var tbt_dg=$('#tablet-datagrid');
    var mySelfstr='#tablet-datagrid-'+row.ID;
    var mySelf=$(mySelfstr);
	mySelf.datagrid({
		method: 'get',
		url: '/agility/server/database/tandasFunctions.php',
	    queryParams: {
	        Operation: 'getDataByTanda',
	        Prueba: row.Prueba,
	        Jornada: row.Jornada,
			Sesion: row.Sesion,
			ID:row.ID
	    },
	    loadMsg: "Actualizando orden de salida ...",
	    pagination: false,
	    rownumbers: true,
	    fitColumns: true,
	    singleSelect: true,
	    autoRowHeight: false,
	    width: '100%',
	    height: 'auto',
		columns:[[
		        { field:'Parent',		width:0, hidden:true }, // self reference to row index
	            { field:'Prueba',		width:0, hidden:true }, // extra field to be used on form load/save
	            { field:'Jornada',		width:0, hidden:true }, // extra field to be used on form load/save
	            { field:'Manga',		width:0, hidden:true },
	            { field:'Tanda',		width:0, hidden:true }, // string with tanda's name
	            { field:'ID',			width:0, hidden:true }, // tanda ID
	            { field:'Perro',		width:0, hidden:true },
	            { field:'Licencia',		width:0, hidden:true },
	            { field:'Pendiente',	width:0, hidden:true },
	            { field:'Tanda',		width:0, hidden:true },
                { field:'Equipo',		width:0, hidden:true },
                { field:'NombreEquipo',	width:20, align:'center',	title: 'Equipo' },
	            { field:'Dorsal',		width:10, align:'center',	title: 'Dorsal', styler:checkPending },
	            { field:'Nombre',		width:20, align:'left',		title: 'Nombre'},
                { field:'Celo',			width:8, align:'center',	title: 'Celo', formatter:formatCelo},
	            { field:'NombreGuia',	width:35, align:'right',	title: 'Guia' },
	            { field:'NombreClub',	width:25, align:'right',	title: 'Club' },
	            { field:'Categoria',	width:10, align:'center',	title: 'Categ.' },
	            { field:'Grado',		width:10, align:'center',	title: 'Grado' },
	            { field:'Faltas',		width:5, align:'center',	title: 'F'},
	            { field:'Rehuses',		width:5, align:'center',	title: 'R'},
	            { field:'Tocados',		width:5, align:'center',	title: 'T'},
	            { field:'Tiempo',		width:15, align:'right',	title: 'Tiempo'	}, 
	            { field:'Eliminado',	width:5, align:'center',	formatter:formatEliminado,	title: 'EL.'},
	            { field:'NoPresentado',	width:5, align:'center',	formatter:formatNoPresentado,	title: 'NP'},		
	            { field:'Observaciones',width:0, hidden:true }
	          ]],
          	// colorize rows. notice that overrides default css, so need to specify proper values on datagrid.css
        rowStyler:myRowStyler,
        onClickRow: function(idx,data) {
            doBeep();
	    	data.Session=workingData.sesion;
            data.Parent=mySelfstr; // store datagrid reference
            $('#tdialog-form').form('load',data);
            $('#tablet-window').window('close');
            $('#tdialog-window').window('open');
        },
        onResize:function(){
            tbt_dg.datagrid('fixDetailRowHeight',index);
        },
        onLoadSuccess:function(){
            // show/hide team name
            if (isTeamByJornada(workingData.datosJornada) ) mySelf.datagrid('showColumn','NombreEquipo');
            else  mySelf.datagrid('hideColumn','NombreEquipo');
            // auto resize columns
            setTimeout(function(){ tbt_dg.datagrid('fixDetailRowHeight',index); },0);
<?php if (toBoolean($config->getEnv('tablet_dnd'))) { ?>
			mySelf.datagrid('enableDnd');
			mySelf.datagrid('getPanel').panel('panel').attr('tabindex',0).focus();
<?php } else { /* if dnd is off enable only on PC */?>
			if (! isMobileDevice() ) {
				mySelf.datagrid('enableDnd');
				mySelf.datagrid('getPanel').panel('panel').attr('tabindex',0).focus();
			}
<?php } ?>
    	},
        onDragEnter: function(dst,src) {
            if (dst.Manga!=src.Manga) return false;
            if (dst.Categoria!=src.Categoria) return false;
            if (dst.Grado!=src.Grado) return false;
            if (dst.Celo!=src.Celo) return false;
            return true;
        }, 
        onDrop: function(dst,src,updown) {
            // reload el orden de salida en la manga asociada
            workingData.prueba=src.Prueba;
            workingData.jornada=src.Jornada;
            workingData.manga=src.Manga;
            dragAndDropOrdenSalida(
                    src.Perro,
                    dst.Perro,
                    (updown==='top')?0:1,
                    function()  { mySelf.datagrid('reload'); }
             	);
        	return false;
        }
	});
	tbt_dg.datagrid('fixDetailRowHeight',index);
}
</script>
