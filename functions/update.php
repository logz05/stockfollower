<?php


#####NORDNET#####
function updateFilter($var){ 
	return(preg_match("/^20/", $var));
}
function updateNordnet($fetch) {
	$output = '';
	foreach($fetch as $address) {
		$file = file($address['link']);
		foreach(array_filter($file, "updateFilter") as $line){
		###BUG
			$reg = "/(201[0-9]-[0-9]{2}-[0-9]{2})[\s]([0-9]+,[0-9]+)/";
			preg_match ($reg, $line, $matches);
			if(!empty($matches)) {
				$matches['2'] = preg_replace("/,/", ".", $matches['2']);
				$query = "REPLACE INTO stockprice (date, price, stockID)
							VALUES ('$matches[1]', '$matches[2]', '$address[stockID]')";
				$output .= $query . "\n";
				$result=mysql_query($query) or die(mysql_error());; 
			}  
		}
	}
	return $output;
}  

#####MORNINGSTAR#####
function updateMorningstar($fetch) {
	$output = '';
	foreach($fetch as $address) {
		$file = file($address['link']);
		foreach($file as $line){
			$reg = "/<td>Senaste NAV<\/td><td>  ([0-9]+,[0-9]+) SEK<\/td><td>([0-9]{4}-[0-9]{2}-[0-9]{2})<\/td>/";
			preg_match ($reg, $line, $matches);
			if(!empty($matches)) {
				$matches['1'] = preg_replace("/,/", ".", $matches['1']);
				$query = "INSERT IGNORE stockprice (date, price, stockID)
					VALUES ('$matches[2]', '$matches[1]', '$address[stockID]')";
				$output .= $query . "\n";
				$result=mysql_query($query) or die(mysql_error());; 
			}  
		}
	}
	return $output;
}

#####AVANZA#####
function updateAvanza($fetch) {
	$output = '';
	### Tidigaste klockslag vi kan ta hem r�tt uppgifter
	$minTid = '18';
	### Senaste klockslag vi kan ta hem r�tt uppgifter
	$maxTid = '09';
	$hourNow = date('H');
	if($maxTid > $hourNow) {
		$offset = ($hourNow + 1) * 60 * 60;
		$date = date('Y-m-d', time() - $offset);
	} else if($minTid <= $hourNow) {
		$date = date('Y-m-d');
	} else {
		return("tiden �r utanf�r till�ten tid (AVANZA)");
	}
	foreach($fetch as $stockID => $link) {
		$file = file_get_contents($link);
		$reg = '/>[A-Za-z .]*<\/td><td nowrap class="(winner|looser|neutral)">[\-\+]*[0-9]+,[0-9]+<\/td><td nowrap class="(winner|looser|neutral)">[\-\+]*[0-9]+,[0-9]+<\/td><td nowrap class="(winner|looser|neutral)">[0-9]+,[0-9]+<\/td><td nowrap class="(winner|looser|neutral)">[0-9]+,[0-9]+<\/td><td nowrap class="(winner|looser|neutral)">([0-9]+,[0-9]+)<\/td>/';
		preg_match ($reg, $file, $matches);

		if(!empty($matches)) {
			# Replace dot with comma
			$matches['6'] = preg_replace("/,/", ".", $matches['6']);
			$query = "REPLACE INTO stockprice (date, price, stockID)
						VALUES ('$date', '$matches[6]', '$stockID')";
			$output .= $query . "\n";
			$result=mysql_query($query) or die(mysql_error());;
		}  
	}
	return $output;
}

###NASDAQ INDEX PARSER### 
function updateNasdaqParse($rawData) {
	require_once('classHtmldom.php');
	$html = str_get_html($rawData);

	$i = 0;
	$output = array();
	foreach($html->find('tr') as $e) {
		$temp_row = array();
		foreach($e->find('td') as $f) {
			$temp_row[] = $f->innertext;
		}

		if(!empty($temp_row['0'])) {
			$i++;
			$output[$i]['date'] = $temp_row['0'];
			$temp_row['3'] = preg_replace("/,/", ".", $temp_row['3']);
			$output[$i]['price'] = preg_replace("/ /", "", $temp_row['3']);
		} 

	}
	return $output;
}

###NASDAQ RETRIEVER###
function updateNasdaqGet($instrument, $toDate, $fromDate = '2012-05-01') {

	$requestData =
	'<post>
	<param name="SubSystem" value="History"/>
	<param name="Action" value="GetDataSeries"/>
	<param name="AppendIntraDay" value="no"/>
	<param name="Instrument" value="'.$instrument.'"/>
	<param name="FromDate" value="'.$fromDate.'"/>
	<param name="ToDate" value="'.$toDate.'"/>
	<param name="hi__a" value="0,1,2,4,21,8,10,11,12,9"/>
	<param name="ext_xslt" value="/nordicV3/hi_table.xsl"/>
	<param name="ext_xslt_lang" value="sv"/>
	<param name="ext_contenttype" value="application/vnd.ms-excel"/>
	<param name="ext_contenttypefilename" value="_SE0004384915.xls"/>
	<param name="ext_xslt_hiddenattrs" value=",ip,iv,"/>
	<param name="ext_xslt_tableId" value="historicalTable"/>
	<param name="app" value="/index/historiska_kurser/"/>
	</post>';

	$postdata = http_build_query(
	array(
	'xmlquery' => $requestData,
	) 
	);

	$opts = array('http' =>
	array(
	'method'  => 'POST',
	'header'  => 'Content-type: application/x-www-form-urlencoded',
	'content' => $postdata
	) 
	);

	$context  = stream_context_create($opts);
	$result = file_get_contents('http://www.nasdaqomxnordic.com/webproxy/DataFeedProxy.aspx', false, $context);
	return $result;
}

###INDEX UPDATER###
function updateIndex($values, $isin){
	$output = '';
	foreach($values as $key) {
		$query = "REPLACE INTO indexprice (ISIN, date, price)
				VALUES ('$isin', '$key[date]', '$key[price]')";
		$result=mysql_query($query) or die(mysql_error());;
	}
}

function updateNasdaq($toDate) {
	$indexList = indexGetList();
	### Makes php seg fault if the document is to big, use with caution..
	foreach($indexList as $index) {
		$ng = updateNasdaqGet($index['ISIN'], $toDate);
		$output = updateNasdaqParse($ng);
		updateIndex($output, $index['ISIN']);
	} 
	return true;
}


?>