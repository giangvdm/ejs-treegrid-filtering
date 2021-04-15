<script src="Grid/GridE.js"> </script>

<div class="ExampleBorder">
   <div class="ExampleMain" style="width:100%;height:100%;">
      <bdo Debug='info'
           Layout_Url="transfers_layout.php" 
            Data_Url="transfers_data.php" 
            Page_Url="transfers_page.php" 
            Page_Format="Internal" Page_Data="TGData" 
            Upload_Url="transfers_upload.php" Upload_Format="Internal" Upload_Data="TGData"
         >
      </bdo>
   </div>
</div>
<script >
// Loads all pages on background

//Grids.OnDataReceive = function(G,IO){ if(IO.Row) G.LoadPage(IO.Row.nextSibling); }



Grids.OnGetFilterValue = function(G,row,col,val){
if(col=="GROUP" && row.Def.Name=="R") val = Get(row,"REG")+","+Get(row,"CN");
return val;
}
Grids.OnRowSearch = function(G,row,col,found,F,type){
if(type || G.SearchDefs!="") return found; // Only for "in orders with items" and default call
if(row.Def.Name=="Data") return -1;        // Only for orders
for(var r=row.firstChild;r;r=r.nextSibling) { 
	var found = F(r,col,1);                 // calls F(r,col,type=1)
	if(!(found<=0)) return found; 
	}
return 0;
}
// Events to show debugging information
// -----------------------------------------------------------------------------------------
Grids.OnDownloadPage = function(G,Row){
G.RecalculateRows(G.Rows.Fix1,1);
}

// -----------------------------------------------------------------------------------------
// -----------------------------------------------------------------------------------------
Grids.OnRenderPageFinish = function(G){
G.RecalculateRows(G.Rows.Fix1,1);
}

Grids.OnPageReady = function(G,Row){
G.RecalculateRows(G.Rows.Fix1,1);
}


// Called after changed language to reset currency and recalculate grid
Grids.OnLanguageFinish = function(G,code){ 
var row = G.Rows.Fix3;
G.SetValue(row,"C",Get(row,Get(row,"D")+"Rate"),1);
}
</script>