<?php
  $site="port";
  ##Bug, jämförelse simulering går ej välja

include 'config.php';
include 'functions/port.php';
include 'functions/stock.php';
include 'functions/index.php';
include 'functions/rss.php';
include 'functions/sys.php';
include 'pageTop.php';

$stockID = $_GET['stockID'];

$date = portGetStockSpan($stockID, "1");

$startdate = $date['0'];

?>
 
	 
 
 <?php
$output = portGetStock($FROM, $TO, "1");
$output2 = portGetStockSummary('2000-01-01', $TODAY, $stockID);
    echo '<table width="28%" style="float: left;" >';
    echo '<caption style="text-align: left; font-size:15px;">Data</caption>';
    echo "<tr>";
	echo '<th style="text-align:left;">Namn</th>';
	echo "<td>".$output2['shortName']."</td>"; 
	echo "</tr>";
	echo "<tr>";
	echo '<th style="text-align:left;">Antal</th>';
	echo "<td>".number_format($output2['q'], 2, ',', ' ')."</td>";
	echo "</tr>";
	echo "<tr>";
	echo '<th style="text-align:left;">Senaste kursen</th>';
	echo "<td>".number_format($output2['ltrade'], 2, ',', ' ')."</td>";	
	echo "</tr>";
	echo "<tr>";
	echo '<th style="text-align:left;">Kursdatum</th>';
	echo "<td>".$output2['date']."</td>";
	echo "</tr>";
	echo "<tr>";
	echo '<th style="text-align:left;">Anskaffningspris</th>';
	echo "<td>".number_format($output2['aprice'], 2, ',', ' ')."</td>";
	echo "</tr>";
	echo "<tr>";
	echo '<th style="text-align:left;">Marknadsvärde</th>';
	echo "<td>".number_format($output2['mvalue'], 0, ',', ' ')."</td>";
	echo "</tr>";
	echo "<tr>";
	echo '<th style="text-align:left;">Utveckling %</th>';
	echo "<td>".number_format(@($output2['utvkr'] / @$output2['mvalue'] * 100) , 2, ',', ' ')."%</td>";
	echo "</tr>";
	echo "<tr>";
	echo '<th style="text-align:left;">Utveckling kr</th>';
	echo "<td>".number_format($output2['utvkr'], 0, ',', ' ')."</td>";
	echo "</tr>";
	echo "<tr>";
	echo '<th style="text-align:left;">Direktavkastning</th>';
	echo "<td>".number_format($output2['diravkkr'], 0, ',', ' ')."</td>";
	echo "</tr>";
	echo "<tr>";
	echo '<th style="text-align:left;">Realiserad s:a</th>';
	echo "<td>".number_format($output2['rea'], 0, ',', ' ')."</td>";
	echo "</tr>";
	echo "<tr>";
	echo '<th style="text-align:left;">Uppdatering Avanza</th>';
	echo "<td>Ja</td>";
	echo "</tr>";
	echo "<tr>";
	echo '<th style="text-align:left;">Uppdatering Morningstar</th>';
	echo "<td>Ja</td>";
	echo "</tr>";
	echo "<tr>";
	echo '<th style="text-align:left;">Uppdatering Nordnet</th>';
	echo "<td>Ja</td>";
	echo "</tr>";
    echo "</table>";

 ?>
 	


<table width="68%" style="float: right" class="sortable"> 
<caption style="text-align: left; font-size:15px;">Historik</caption>
    <tr>
	<th width="10px">ID</th>
	<th>Datum</th>
	<th>Beskrivning</th>
	<th>Antal</th>
	<th>Kurs</th>
	<th>Summa</th>
	</tr>
<?php

  $activity = portGetStockActivityOld('2000-01-01', $TO, $stockID);
    $tID  = "1";
    $amount = "0";
    foreach($activity as $key ){

      echo "<tr>";
	  echo "<td>".$tID."</td>";
      echo "<td>".$key['date']."</td>";
	  if($key['bought'] == 'bought') {
	    echo "<td>köpt</td>"; 
        $amount += $key['quantity']; 
	  } else if($key['bought'] == 'sold') {
	    echo "<td>sålt</td>";
        $amount -= $key['quantity'];
      } else if($key['bought'] == 'dividend') {
	    echo "<td>utdelning</td>"; 	
        $key['quantity'] =   $amount;
	  } 
	  echo "<td>".$key['quantity']."</td>";
	  echo "<td>".$key['price']."</td>";
	  echo "<td>".round($key['price'] * $key['quantity'])."</td>";
	  echo "</tr>";
	  $tID = $tID + 1;
      
    }

  echo "</table>";

  $chartColumns['3'] = "data.addColumn('number', 'Kurs');\n";
  $chartColumns['2'] = "data.addColumn({type:'string', role:'annotation'});\n";
  $chartColumns['1'] = "data.addColumn({type:'string', role:'annotationText'});\n";

  if($compareToIndex) {
    $chartColumns['0'] = "data.addColumn('number', '".indexResName($indexISIN)."');\n";
    $indexToScale = indexGetValue($startdate, $indexISIN);
    $scaleAgainst = stockGetValue($stockID, $startdate);
    @$scale = $scaleAgainst['value'] / $indexToScale['price'];
  }

  $current = '0';
  $annotation = 1;
  $chart_data = '';
  while(strtotime($startdate) <= strtotime($TODAY)) {
    $output = stockGetValue($stockID, $startdate);
    $ann = portGetStockAction($stockID, $startdate );
    $data = '';
    if($compareToIndex) {
      $index = indexGetValue($startdate, $indexISIN);
      $data  = "," . round($index['price'] * $scale, 2);
    }

    if(is_array($activity) && $activity[$current]['date'] == $startdate) {
      ### Display entry ID.
      $chart_data .= "
      ['".date('y-m-d', strtotime($startdate))."',  $output[value], '$annotation', '$ann[price]'".$data."],";
      $annotation++;
 
      ### Check if we have duplicate entries on same day and skip if.
      if(array_key_exists($current + 1, $activity)) {
        while(array_key_exists($current + 1, $activity) && $activity[$current]['date'] == $activity[$current + 1]['date']) {
          $current++;
          $annotation++;
        }
        ### Next entry
        $current++;
      }

    } else {
      $chart_data .= "
      ['".date('y-m-d', strtotime($startdate))."', $output[value],  undefined, undefined".$data."],";
    }
      $startdate = strtotime ( '+1 day' , strtotime ($startdate) ) ;
      $startdate = date ( 'Y-m-d' , $startdate );
    }
    $chart_data = substr_replace($chart_data ,"",-1	);

?>
   <!--Load the AJAX API-->
 <script type="text/javascript">
      google.load("visualization", "1", {packages:["corechart"]});
      google.setOnLoadCallback(drawChart);
      function drawChart() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Year');
       <?php foreach($chartColumns as $arr) echo $arr; ?>

        data.addRows([<?php echo $chart_data; ?>]);
        var options = {
          title: 'Kurs',
		  annotation: {'2': {style: 'letter'}}
        };

        var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
        chart.draw(data, options);
      }
    </script>

    <div id="chart_div" class="chart" style="width: 100%; height: 250px; float: right; border-width:thin; border-style:solid; margin: 10px 0 0 0;"></div>
<?php
sysFlush_page();
 
$startdate = $date['0'];
while($startdate <= $TO) {
  $var[] = portCacheGetHoldingSum($startdate, $stockID);
  $startdate = strtotime ( '+1 day' , strtotime ($startdate) ) ;
  $startdate = date ( 'Y-m-d' , $startdate ); 
}



if($compareToIndex) {
  $transactions = portGetStockTransactionsOld(array('1' => $stockID));

  $omx = portSimIndex($transactions,$indexISIN);

}

?>

 <script type="text/javascript">
      google.load("visualization", "1", {packages:["corechart"]});
      google.setOnLoadCallback(drawChart);
      function drawChart() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Year');
        data.addColumn('number', 'Utveckling');
        <?php if($compareToIndex) { ?>
        data.addColumn('number', 'Utveckling s:a i <?php echo indexResName($indexISIN);?>');
        <?php } ?>
		data.addColumn({type:'string', role:'annotation'});
		data.addColumn({type:'string', role:'annotationText'});
        data.addRows([<?php
        $chart_data = '';
        $first_run = 1;
          foreach($var as $key) {
			if($compareToIndex) {
              if($first_run == 1 ) {
                $first_run = 0;
                @$norm = $omx[$key['date']]['utv'];
              } 
              @$utv = $omx[$key['date']]['utv'] - $norm . ",";
            } else $utv = '';
            $primary = round($key['utv'] + $key['diravk'] + $key['rea']);
            $chart_data .= "
            ['".date('y-m-d', strtotime($key['date']))."',".$primary.",".$utv." undefined, undefined],";
          }
        $chart_data = substr_replace($chart_data ,"",-1	);
        echo $chart_data;
        ?>]);

        var options = {
          title: 'Utveckling',
		 annotation: {'1': {style: 'letter'}}
        };

        var chart = new google.visualization.LineChart(document.getElementById('char_div'));
        chart.draw(data, options);
      }
    </script>

<div id="char_div" class="chart" style="width: 100%; height: 250px; float: right; margin: 10px 0 0 0"></div>
<div style="width: 100%; height: 250px; float: right; margin: 10px 0 0 0">
<?php

  $out = rssReadStockID($stockID);
  if(!empty($out)) {
  ?>
<table width="100%" class="sortable">
<tr class="row">
<th style="text-align: left;">Datum</th>
<th style="text-align: left;">Titel</th>
<th style="text-align: left;">Länk</th>

</tr>	<?php

    foreach($out as $each) {
      $stockName = stockResName($each['stockID']);
      echo "<tr>";
      echo "<td style=\"text-align:left;\">" . $each['pubDate'] . '</td>';
      echo '<td style="text-align:left;">';
      if($each['new'] == '1')
        echo '<img src="unread.png" width="12px"><b>';
      echo '<a href="rss.php?load=' . $each['ID'] . '">' . $each['title'] . '</a>';
      if($each['new'] == '1')
        echo '</b>';
      echo '</td>';
      echo '<td style="text-align:left;"> <a href="' . $each['link'] . '">LÄNK</a>';
      echo '</td>';

      echo '</tr>';
    }

    echo "</table></div>";
  }
  
 include 'pageBottom.php';
 
  ?>