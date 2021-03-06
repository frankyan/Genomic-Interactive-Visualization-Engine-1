﻿<?php
	require_once (realpath(dirname(__FILE__) . '/../includes/common_func.php'));	
	require_once (realpath(dirname(__FILE__) . "/../includes/session.php"));
	
	$res = initialize_session();
	$encodeOn = $res['encodeOn'];
	$in_debug = $res['in_debug'];
	$genemoOn = $res['genemoOn'];
	$experimentalFeatures = false;
	
	if(isset($res['experimental']) && $res['experimental']) {
		$experimentalFeatures = true;
	}
	unset($res);
	
	$sessionInfo = NULL;
	if(isset($_REQUEST['sessionID'])) {
		// this is to recover an old session
		// went to database to make sure this is a correct sessionID
		
		// if testing flag is set, go to the new server to check
		//if(!defined('USE_OLD_SERVER')) {
			$ch = curl_init((isset($_SERVER["HTTPS"])? "https": "http") +
				"://comp.genemo.org/cpbrowser/loadSession.php?sessionID=" + $_REQUEST['sessionID']);
			try {
				$sessionInfo = json_decode(curl_exec($ch));
			} catch(Exception $e) {
				$sessionError = "Invalid address or address expired.";
			}
//		} else {
//			try {
//				$sessionInfo = loadGenemoSession($_REQUEST['sessionID']);
//			} catch(Exception $e) {
//				$sessionError = $e->getMessage();
//			}
//		}
	}
?>
<!DOCTYPE html>
<html>
<head>
<link href='//fonts.googleapis.com/css?family=Roboto:400,400italic,500,700italic,700' rel='stylesheet' type='text/css'>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="keywords" content="Comparative study,Epigenomics,Epigenetics,Visualization,Epigenome browser" />
<meta name="description" content="CEpBrowser (Comparative Epigenome Browser) is a gene-centric genome browser that visualize the genomic features of multiple species with color-coded orthologous regions, aiding users in comparative genomic research. The genome browser is adapted from UCSC Genome Browser and the orthologous regions are generated from cross-species lift-over pairs." />
<title>GENEMO Search</title>
<script src="components/bower_components/webcomponentsjs/webcomponents-lite.min.js"></script>
<link href="genemo/mainstyles.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/jquery-1.7.js"></script>
<script type="text/javascript" src="js/uicomponent.js"></script>
<script type="text/javascript" src="js/generegion.js"></script>
<script type="text/javascript" src="js/regionlistui.js"></script>
<script type="text/javascript" src="js/sessionControl.js"></script>
<script type="text/javascript" src="js/navui.js"></script>
<script type="text/javascript" src="js/uploadui.js"></script>
<script type="text/javascript" src="js/libtracks.js"></script>
<script type="text/javascript" src="js/languagechange.js"></script>
<link rel="import" href="components/genemo_components/manual-icon/manual-icon.html">
<link rel="import" href="components/genemo_components/meta-entries/meta-entries.html">
<link rel="import" href="components/genemo_components/cell-line-info-card/cell-line-info-card.html">
<link rel="import" href="components/genemo_components/search-card-content/search-card-content.html">
<link rel="import" href="components/genemo_components/query-card-content/query-card-content.html">
<link rel="import" href="components/genemo_components/genemo-track-filter/genemo-track-filter.html">
<?php if(isset($experimentalFeatures)) { ?>
<link rel="import" href="components/genemo_components/genemo-tab-cards/genemo-tab-cards.html">
<?php } ?>
<link rel="import" href="components/genemo_components/genemo-card/genemo-card.html">
<link rel="import" href="components/genemo_components/genemo-styles.html">
<link rel="import" href="components/bower_components/paper-button/paper-button.html">
<link rel="import" href="components/bower_components/paper-dialog/paper-dialog.html">
<link rel="import" href="components/bower_components/google-youtube/google-youtube.html">
<link rel="import" href="components/bower_components/paper-tooltip/paper-tooltip.html">
<link rel="import" href="components/bower_components/iron-signals/iron-signals.html">
<link rel="import" href="components/bower_components/iron-icons/iron-icons.html">
<link rel="import" href="components/bower_components/iron-icons/notification-icons.html">
<style is="custom-style" include="genemo-shared-styles">
<!--
html {
	height: 100%;
}
body {
	background: #EEEEEE;
	margin: 0; /* it's good practice to zero the margin and padding of the body element to account for differing browser defaults */
	padding: 0;
	text-align: center; /* this centers the container in IE 5* browsers. The text is then set to the left aligned default in the #container selector */
	color: #000000;
	height: 100%;
	overflow: hidden;
}
paper-button {
	font-size: 14px;
	margin: 0.2em 0;
}
-->
</style>
<script type="text/javascript">

var UI = new UIObject(window);

var genemoIsOn = false;
<?php 
if($genemoOn) {
?>
genemoIsOn = true;
var cellLineMap = {};
var tissueMap = {}
var labMap = {};
var expMap = {};
<?php
}
?>

var spcArray = new Array();		// this will be the array of species (Species Object)
spcArray.map = new Object();
spcArray.activeNumber = 0;

    <?php
	$mysqli = connectCPB();
	// TODO: need to do something about the species here
	// first connect to database and find the number of species
	$species = $mysqli->query("SELECT * FROM species");
	$spcinfo = array();
	while($spcitor = $species->fetch_assoc()) {
		// get all the species ready
		//	if(isset($_REQUEST[$spcitor["dbname"]])) { should use this later
		$spcinfo[] = $spcitor;
	}
	$num_spc = sizeof($spcinfo);
	for($i = 0; $i < $num_spc; $i++) {
		?>
spcArray.push(new Species("<?php echo $spcinfo[$i]["dbname"]; ?>", 
	"<?php echo $spcinfo[$i]["name"]; ?>", "<?php echo $spcinfo[$i]["commonname"]; ?>",
	<?php echo ($spcinfo[$i]["encode"]? "true": "false"); ?>));
spcArray.map["<?php echo $spcinfo[$i]["dbname"]; ?>"] = <?php echo $i; ?>;
<?php 
		if($genemoOn) {
?>
cellLineMap["<?php echo $spcinfo[$i]["dbname"]; ?>"] = {};
tissueMap["<?php echo $spcinfo[$i]["dbname"]; ?>"] = {};
labMap["<?php echo $spcinfo[$i]["dbname"]; ?>"] = {};
expMap["<?php echo $spcinfo[$i]["dbname"]; ?>"] = {};
<?php 
		}
	}
	$species->free();
	$mysqli->close();
		?>

var cmnTracks = new TrackBundle();	// this will be holding common tracks (CmnTrack Object)

var cmnTracksEncode = new TrackBundleWithSample('', '_checkbox', '', '');	// this will be holding common Encode tracks (CmnTrack Object)

var orderedCmnTracksEncode = new Array();	// this is the sorted common track array
orderedCmnTracksEncode.sigLength = 0;		// number of tracks that have significant results

var timeoutVar;
var timerOn = 0;
var gListIsOn = 0;
//var currentGeneName = "";		// obsoleted, use currGene as commonGene class
//var numSpc = 0;				// this is the number of species IN DISPLAY
// this is already obsolete (check active from spcArray).
//var speciesCoor = {};			// obsoleted, use currGene
//var speciesStrand = {};		// obsoleted, use currGene
//var speciesGeneName = {};		// obsoleted, use currGene
//var numSpcReady = 0;			// obsoleted, check spcArray.isReady
var currGene = null;

var mouseInGList = false;
var inFocus = false;
var maxGeneListHeight;

//var spcNum = 0;				// this is the total number of species (obsoleted, use spcArray.length)
//var spcNumVisible = spcNum;		// number of species that have their panel expanded (obsoleted, check !spcArray.isCollapsed
var spcNumEnabled = 0;
//var spcDbName = new Array();	// obsoleted
//var spcCmnName = new Object();	// obsoleted
//var spcName = new Object();		// obsoleted
//var spcEncode = new Object();	// obsoleted

//var spcReady = new Object();		// obsoleted

var left_width = 290;
var left_value = 0;
var querySent = "";


//var cmnTracksSampleType = new Object();		// link sample name to array of tracks
//var uniTracksSampleType = new Array();
//var cmnTracksStatusBackup = new Object();	// this is to backup the selection states of tracks for sample selection
//var uniTracksStatusBackup = new Array();

var listPanels = new Array('trackSettings', 'tableBrowser');

var isInDownload = false;

var isInBrowser = false;
var tracksInitialized = false;

var isEncodeOn = <?php echo ($encodeOn? 'true': 'false'); ?>;			// Switch this to on to make ENCODE data as default, 
var cpbrowserURL = 'cpbrowser/cpbrowser.php<?php echo ($encodeOn? '?Encode=XCEncode': ''); ?>';

var encodeMeta = null;
var bedFileLink = null;

function setTrackReady(index) {
	spcArray[index].setTrackReady(spcArray, cmnTracks, cmnTracksEncode, !tracksInitialized, isInBrowser);
}

function validate_form_searchregion() {
	// Now is the real Ajax part.
	if($("#allDataSingleRegion").val() != "on") {
		var postdata = {};
	//	speciesDbName = new Array();
		$.each($('#searchform').serializeArray(), function(i, field) {
	//		if($('#' + field.name).is("checkbox")) {
	//			speciesDbName.push(field.name);
	//		}
			postdata[field.name] = field.value;
			});
		$.post("regionsearch.php<?php echo $in_debug? "?Debug=XCDebug": ""; ?>", postdata, function (data) {
			$("#contentHolder").html(data);
		});
		return false;
	} else {
		return true;
	}
}

function createJSONReturnFunction(isCommon, iTrack, iSpecies) {
	// TODO: add sorting mechanisms
	if(isCommon) {
		return function(data) {
			var items = [];
			items.push($(document.getElementById(cmnTracksEncode[iTrack].getCleanID() + 'Preview')).html());
			items.push('[' + spcArray[iSpecies].db + ']');
			$.each(data, function(key, val) {
				if(val) {
					var length = val.length;
					var validCount = val.validCount;
					var sum = val.sum;
					var sumSquare = val.sumSquare;
					
					cmnTracksEncode[iTrack].addSpeciesValues(spcArray[iSpecies].db, key, validCount, sum, sumSquare);
				}
			});
			// calculate and display the species-wide values
			items.push('Max mean: ');
			items.push(cmnTracksEncode[iTrack].getCompareValue(null, "mean"));
			items.push(' / Max CV: ');
			items.push(cmnTracksEncode[iTrack].getCompareValue(null, "cv"));
			$(document.getElementById(cmnTracksEncode[iTrack].getCleanID() + 'Preview')).html(items.join(' '));
			
			if(cmnTracksEncode[iTrack].isSpcArrayUpdated()) {
				// all species value updated, move the track to its corresponding location
				insert(cmnTracksEncode[iTrack], orderedCmnTracksEncode, 'cmnTrackEncodeSortedTbodyHolder',
					'cmnTrackEncodeInsigTbodyHolder', null, "mean");
			}
		};
	} else {
		return function(data) {
			var items = [];
			items.push($(document.getElementById(spcArray[iSpecies].uniTracksEncode[iTrack].getCleanID() + 'Preview')).html());
			//items.push(spcDbName[iSpecies]); 
			$.each(data, function(key, val) {
				var length = val.length;
				var validCount = val.validCount;
				var sum = val.sum;
				var sumSquare = val.sumSquare;
				
				spcArray[iSpecies].uniTracksEncode[iTrack].addSpeciesValues(key, validCount, sum, sumSquare);
			});
			items.push('Max mean: ');
			items.push(spcArray[iSpecies].uniTracksEncode[iTrack].getCompareValue(null, "mean"));
			items.push(' / Max CV: ');
			items.push(spcArray[iSpecies].uniTracksEncode[iTrack].getCompareValue(null, "cv"));
			if(spcArray[iSpecies].uniTracksEncode[iTrack].isSpcArrayUpdated()) {
				// all species value updated, move the track to its corresponding location
				insert(spcArray[iSpecies].uniTracksEncode[iTrack], 
					spcArray[iSpecies].orderedUniTracksEncode, spcArray[iSpecies].sortedTbodyID,
					spcArray[iSpecies].insigTbodyID, null, "mean");
			}
			$(document.getElementById(spcArray[iSpecies].uniTracksEncode[iTrack].getCleanID()
				+ 'Preview')).html(items.join(' '));
		}
	}
}

function searchTracks() {
	orderedCmnTracksEncode.length = 0;		// clear ordered tracks
	orderedCmnTracksEncode.sigLength = 0;	// number of tracks that have significant results
	$('#cmnTrackEncodeTbodyHolder').html($('#cmnTrackEncodeTbodyHolder').html() 
		+ $('#cmnTrackEncodeSortedTbodyHolder').html() 
		+ $('#cmnTrackEncodeInsigTbodyHolder').html());
	$('#cmnTrackEncodeSortedTbodyHolder').html('');
	$('#cmnTrackEncodeInsigTbodyHolder').html('');
	$('#cmnTrackEncodeSortedTbodyHolder').show();
	$('#cmnTrackEncodeInsigTbodyHolderHeader').hide();
	toggleTbody('cmnTrackEncodeInsigTbodyHolder', false);
	
	for(var i = 0; i < spcArray.length; i++) {
		if(!isEncodeOn || !spcArray[i].isEncode) {
			continue;
		}
		spcArray[i].regionToShow = new ChrRegion($('#regionToShow').val());
		spcArray[i].orderedUniTracksEncode.length = 0;		// clear ordered tracks
		spcArray[i].orderedUniTracksEncode.sigLength = 0;	// number of tracks that have significant results
		$(document.getElementById(spcArray[i].unsortedTbodyID)).html($(document.getElementById(spcArray[i].unsortedTbodyID)).html()
			+ $(document.getElementById(spcArray[i].sortedTbodyID)).html() 
			+ $(document.getElementById(spcArray[i].insigTbodyID)).html());
		$(document.getElementById(spcArray[i].sortedTbodyID)).html('');
		$(document.getElementById(spcArray[i].insigTbodyID)).html('');
		$(document.getElementById(spcArray[i].sortedTbodyID)).show();
		$(document.getElementById(spcArray[i].insigTbodyID + 'Header')).hide();
		toggleTbody(spcArray[i].insigTbodyID, false);
	}
	for(var j = 0; j < cmnTracksEncode.length; j++) {
		// send across all species
		$('#' + cmnTracksEncode[j].getCleanID() + 'Preview').html('');
		cmnTracksEncode[j].clearAllSpeciesValues();
		for(var i = 0; i < spcArray.length; i++) {
			if(!isEncodeOn || !spcArray[i].isEncode) {
				continue;
			}
			var sendData = new Object();
			sendData['region'] = spcArray[i].regionToShow.toString();
			//console.log(spcArray[i].regionToShow.toString());
			sendData['tableName'] = cmnTracksEncode[j].getSpeciesTblName(spcArray[i].db);
			sendData['db'] = spcArray[i].db;
			//console.log(sendData);
			
			$.getJSON('getpreview.php', sendData, createJSONReturnFunction(true, j, i));
		}
	}
	for(var i = 0; i < spcArray.length; i++) {
		if(!isEncodeOn || !spcArray[i].isEncode) {
			continue;
		}
		for(var j = 0; j < spcArray[i].uniTracksEncode.length; j++) {
			spcArray[i].uniTracksEncode[j].clearAllSpeciesValues();
			var sendData = new Object();
			sendData['region'] = spcArray[i].regionToShow.toString();
			//console.log(spcArray[i].regionToShow.toString());
			sendData['tableName'] = spcArray[i].uniTracksEncode[j].getSpeciesTblName();
			sendData['db'] = spcArray[i].db;
			$.getJSON('getpreview.php', sendData, createJSONReturnFunction(false, j, i));
		}
	}
}

function toggleHeaderText(header) {
	if($('#' + header).html() == '[-]') {
		$('#' + header).html('[+]');
	} else {
		$('#' + header).html('[-]');
	}
}

function toggleTbody(panel, toggleTo) {
	// don't toggle if it's already matching toggleTo
	if(arguments.length > 1 && (toggleTo === ($('#' + panel).css('display') != 'none'))) {
		return;
	}
	$('#' + panel + 'Holder').slideToggle('fast', toggleHeaderText(panel + 'Indicator'));
}

//function reset_selection() {
//	if(document.getElementById("geneName").value != "") {
//		document.getElementById("genelist").selectedIndex = 0;
//	}
//}

function toggleGList(toggle) {
	if(toggle == 1) {
		// turn on GList
		gListIsOn = 1;
		$('#GListResponse').removeClass("BoxHide");
		$('#GListResponse').addClass("BoxShow");
	} else {
		gListIsOn = 0;
		$('#GListResponse').removeClass("BoxShow");
		$('#GListResponse').addClass("BoxHide");
	}
}

function toggleHeaderText(header) {
	if($('#' + header).html() == '[-]') {
		$('#' + header).html('[+]');
	} else {
		$('#' + header).html('[-]');
	}
}

function togglePanel(panel, hideothers) {
	if(hideothers && $('#' + panel + 'Holder').css('display') == 'none') {
		if($('#navigationHolder').css('display') != 'none') {
			$('#navigationHolder').slideToggle('fast', toggleHeaderText('navigationIndicator'));
		}
		if($('#genelistHolder').css('display') != 'none') {
			$('#genelistHolder').slideToggle('fast', toggleHeaderText('genelistIndicator'));
		}
//		if($('#selectionHolder').css('display') != 'none') {
//			$('#selectionHolder').slideToggle('fast', toggleHeaderText('selectionIndicator'));
//		}
		if($('#trackManipHolder').css('display') != 'none') {
			$('#trackManipHolder').slideToggle('fast', toggleHeaderText('trackManipIndicator'));
		}
	}
	$('#' + panel + 'Holder').slideToggle('fast', toggleHeaderText(panel + 'Indicator'));
}

function textFocused() {
	inFocus = true;
}

//function setText(text) {
//	querySent = text;
//	document.getElementById("geneName").value = text;
//}

function validate_form_genequery(postdata) {
	$("#genelistContentHolder").html('');
	$('#genelistLoading').removeClass('BoxHide');
	trackUpdatedCallback.data = event;
	trackUpdatedCallback.func = function(eventData) {
		$.post("cpbrowser/genelist.php<?php echo $in_debug? "?Debug=XCDebug": ""; ?>", postdata, regionUiHandler);
	};
	updateTracks(false);
	return false;
}

function updateSampleCheckbox() {
	if(!genemoIsOn) {
		cmnTracksEncode.updateAllStates();
		for(var s = 0; s < spcArray.length; s++) {
			if(!spcArray[s].isEncode) {
				continue;
			}
			spcArray[s].uniTracksEncode.updateAllStates();
		}
	}
}

function changeSettings(db, settings, val) {
	var conDoc = (document.getElementById(db).contentWindow || document.getElementById(db).contentDocument);
	if(conDoc.document) {
		conDoc = conDoc.document;
	}
	conDoc.getElementById(settings).value = val;
}

var isInDownload = false;

function callDownloadMenu(cmnName, isCommon, btnID, isEncode) {
	var btnPos = $('#' + btnID).offset();
	var btnWidth = $('#' + btnID).width();
	var btnHeight = $('#' + btnID).height();
	var sendData = "";
	if(!isEncode) {
		isEncode = false;
	}
	$('#downloadBox').css({left: btnPos.left - $('#downloadBox').width() + btnWidth,
		top: btnPos.top + btnHeight});
	$('#downloadContent').html('<em>Loading...</em>');
	$('#downloadBox').show();
	if(isCommon) {
		// This comes from common, send the whole associative array to download page
		if(!isEncode) {
			sendData = cmnTracks.get(cmnName).getWholeSpcTblName();
		} else {
			sendData = cmnTracksEncode.get(cmnName).getWholeSpcTblName();
		}
		$.getJSON('cpbrowser/getdownload.php', sendData, function(data) {
			// The return will have basically one key (spcDbName+'__'+tableName), 
			// and one value (shortLabel + '||' + type + '||' + longLabel) to display
			// no super track will be returned (will be filtered by jsondownload.php)
			// also returns will be ordered by species for grouping
			var currentDb = "";
			var items = [];
			$.each(data, function(key, val) {
				var db = key.split("__")[0];
				if(currentDb != db) {
					// db has changed
					items.push("<div class='speciesTrackHeader'>" + spcArray[spcArray.map[db]].commonName + "</div>");
					currentDb = db;
				}
				// split the value into shortlabel, type, and long label
				values = val.split("||");
				// put the short label into display and key in the link
				items.push("<div style='padding: 0px 8px;'><a class='downloadFile' href='cpbrowser/download.php?file="
					+ key + "' title='"
					+ values[2] + "'>" 
					+ values[0] + "</a> <div class='downloadType'>"
					+ values[1] + "</div></div>");
			});
			$('#downloadContent').html(items.join(''));
		});
	} else {
		var uniIDNames = cmnName.split("--");
		var jsondata = new Object();
		jsondata[uniIDNames[0]] = uniIDNames[1];
		$.getJSON('cpbrowser/getdownload.php', jsondata, function(data) {
			// The return will have basically one key (spcDbName+'__'+tableName), 
			// and one value (shortLabel + '||' + type + '||' + longLabel) to display
			// no super track will be returned (will be filtered by jsondownload.php)
			// also returns will be ordered by species for grouping
			var items = [];
			$.each(data, function(key, val) {
				// split the value into shortlabel, type, and long label
				values = val.split("||");
				// put the short label into display and key in the link
				items.push("<div style='padding: 0px 4px;'><a class='downloadFile' href='cpbrowser/download.php?file="
					+ key + "' title='"
					+ values[2] + "'>" 
					+ values[0] + "</a> <div class='downloadType'>"
					+ values[1] + "</div></div>");
			});
			$('#downloadContent').html(items.join(''));
		});
	}
	return false;
}

function inDownload(flag) {
	isInDownload = flag;
}

function hideDownload() {
	$('#downloadBox').hide();
}

function toggleWindowButtonText(textstem, action) {
	toggleWindowButton(textstem, action);
}

function toggleWindowButton(buttonid, action) {
	if(action == 'hide') {
		fireCoreSignal('toggle', {group: buttonid, flag: false});
	} else if(action == 'show') {
		fireCoreSignal('toggle', {group: buttonid, flag: true});
	} else {
		fireCoreSignal('toggle', {group: buttonid});
	}
}

function toggleWindowHeaderText(header, action) {
	action = action || 'toggle';
	if($('#' + header).text() == '≪' || action == 'hide') {
		$('#' + header).text('≫');
	} else {
		$('#' + header).text('≪');
	}
}

function hideWindow(panel) {
	$('#' + panel).fadeOut('fast', toggleWindowButtonText(panel, 'hide'));
	hideSample();
	hideDownload();
}

function showWindow(panel) {
	indexToNav();
	$('#' + panel).fadeIn('fast', toggleWindowButtonText(panel, 'show'));
}

function toggleWindow(panel, action) {
	/*for(var i = 0; i < listPanels.length; i++) {
		if(listPanels[i] == panel) {
			continue;
		}
		hidePanel(listPanels[i]);
	}*/
	if(action === 'hide') {
		hideWindow(panel);
	} else {
		if(genemoIsOn) {
			$('#CommonENCODEBox').hide();
			for(var i = 0; i < spcArray.length; i++) {
				if(spcArray[i].isEncode) {
					$('#' + spcArray[i].db + 'EncodeTableHolder').hide();
				}
			}
			$('#' + document.querySelector('#searchCard').currentRef + 'EncodeTableHolder').show();
		}
		if(action === 'show') {
			showWindow(panel);
		} else {
			indexToNav();
			$('#' + panel).fadeToggle('fast', toggleWindowButtonText(panel, action));
			hideDownload();
			hideSample();
		}
	}
}

function trackSettingsOnLoad() {
	if(document.getElementById('trackSettingFrame').contentWindow.location.href != "about:blank" && !$('#trackSettings').is(":visible")) {
		togglePanel('trackSettings');
	}
}

function markTrackInitialized(flag) {
	tracksInitialized = tracksInitialized || flag;
	if(flag) {
		$('#trackSelectLoading').addClass('trackSelectHide');
	} else {
		$('#trackSelectLoading').removeClass('trackSelectHide');
	}
}



function toggleSubHeaderText(header) {
	if($('#' + header).html() == '[-]') {
		$('#' + header).html('[+]');
	} else {
		$('#' + header).html('[-]');
	}
}

function toggleSubPanel(panel, hideothers) {
	if(hideothers && $('#' + panel).css('display') == 'none') {
		if($('#commonHolder').css('display') != 'none') {
			$('#commonHolder').slideToggle('fast', toggleSubHeaderText('commonIndicator'));
		}
		if($('#uniqueHolder').css('display') != 'none') {
			$('#uniqueHolder').slideToggle('fast', toggleSubHeaderText('uniqueIndicator'));
		}
	}
	$('#' + panel + 'Holder').slideToggle('fast', toggleSubHeaderText(panel + 'Indicator'));
}

function toggleEncode() {
	isEncodeOn = !isEncodeOn;
	if(isEncodeOn) {
		$('#NonEncodeData').addClass('BoxHide');
		$('#EncodeData').removeClass('BoxHide');
		$('#trackSelect').width(600);
		//$('#EncodeDataButton').html('View Other Data');
		$('#encodeSampleSettings').show();
	} else {
		$('#EncodeData').addClass('BoxHide');
		$('#NonEncodeData').removeClass('BoxHide');
		$('#trackSelect').width(380);
		//$('#EncodeDataButton').html('View ENCODE Data');
		$('#encodeSampleSettings').hide();
	}
	fireCoreSignal('encodecheck', {flag: isEncodeOn});
}

function toggleSample() {
	if(!genemoIsOn) {
		if($('#sampleTypeBox').css('display') == 'none') {
			$('#sampleTypeBox').show();
			updateSampleCheckbox();
		} else {
			$('#sampleTypeBox').hide();
		}
	} else if(document.querySelector('genemo-track-filter')) {
		if(document.querySelector('genemo-track-filter').opened) {
			document.querySelector('genemo-track-filter').hide();
		} else {
			document.querySelector('genemo-track-filter').show();
		}
	}
}

function hideSample() {
	$('#sampleTypeBox').hide();
}

function resize_tbody() {
	$('#trackSelect').css('max-height', ($(window).height() - 4) + 'px'); 
	$('#EncodeData').css('max-height', ($(window).height() - 144) + 'px'); 
}

spcArray.updateAllSpcActiveNum = function () {
	this.activeNumber = 0;
	for(var i = 0; i < this.length; i++) {
		if(this[i].isActive) {
			this.activeNumber++;
		}
	}
}

function filterTracksFromList(map, flags) {
	var totalCheckboxList = document.querySelector('#' + document.querySelector('#searchCard').currentRef + 'EncodeTableHolder')
									.querySelectorAll('.trackCheckbox');
	for(var i = 0; i < totalCheckboxList.length; i++) {
		if(flags.hasOwnProperty('matched') && map.hasOwnProperty(totalCheckboxList[i].id)) {
			totalCheckboxList[i].checked = flags.matched;
		}
		if(flags.hasOwnProperty('unmatched') && !map.hasOwnProperty(totalCheckboxList[i].id)) {
			totalCheckboxList[i].checked = flags.unmatched;
		}
	}
}

function showMetaInfo(term) {
	document.querySelector('cell-line-info-card').generateDialog(encodeMeta, 
		term, spcArray[spcArray.map[document.querySelector('#searchCard').currentRef]].commonName);
}

$(document).ready( function () {

	<?php echo $genemoOn? "": "UI.initNavSidebar();"; ?>
	resize_tbody();
	
	jQuery(function() {
		jQuery(".geneNameInsert").hide();
		jQuery(".geneNameExpander").click(function(event) {
			jQuery(this.nextElementSibling).toggle();
			jQuery(this.nextElementSibling).position().left 
				= jQuery(this).position().left + jQuery(this).width() 
				- jQuery(this.nextElementSibling).width();
			jQuery(this.nextElementSibling).position().top = jQuery(this).position().top;
			event.stopPropagation();
		});
		jQuery(".geneNameInsert").click(function(event) {
			jQuery(this).hide();
			event.stopPropagation();
		});
	});
	
});

window.addEventListener("WebComponentsReady", function(e) {
	isEncodeOn = !isEncodeOn;		// because doing toggleEncode() will reverse isEncodeOn as well
	fireCoreSignal('content-dom-ready', null);
	var searchCard = document.querySelector('#searchCard');
	if(searchCard) {
		searchCard.addEventListener('submit-form', validateUploadFileOrURL);
	}
	
	var queryCard = document.querySelector('#queryCard');
	if(queryCard) {
		queryCard.addEventListener('partial-genename', function(e) { 
			$.getJSON('cpbrowser/jsongenename.php', {name: e.detail.query}, e.detail.func); 
		});
		queryCard.addEventListener('submit-genequery', function(e) {
			validate_form_genequery(e.detail.postdata); 
		});
	}
	
	var tabPages = document.querySelector('#tabPages');
	if(tabPages) {
		tabPages.addEventListener('submit-form', validateUploadFileOrURL);
		tabPages.addEventListener('partial-genename', function(e) { 
			$.getJSON('cpbrowser/jsongenename.php', {name: e.detail.query}, e.detail.func); 
		});
		tabPages.addEventListener('submit-genequery', function(e) {
			validate_form_genequery(e.detail.postdata); 
		});
	}
	
	var manualBtn = document.querySelector('#manualBtn');
	if(manualBtn) {
		manualBtn.addEventListener('click', window.open.bind(window, 'cpbrowser/manual_genemo.php', '_blank'));
	}
	var videoBtn = document.querySelector('#videoBtn');
	if(videoBtn) {
		var videoDialog = document.querySelector('#videoDialog');
		var videoPlayer = document.querySelector('#videoPlayer');
		if(videoDialog) {
			videoDialog.addEventListener('iron-overlay-opened', function(e) {
				if(videoPlayer && videoPlayer.playsupported) {
					videoPlayer.play();
				}
			});
			videoDialog.addEventListener('iron-overlay-closed', function(e) {
				if(videoPlayer && videoPlayer.playsupported) {
					videoPlayer.pause();
				}
			});
			videoBtn.addEventListener('click', videoDialog.open.bind(videoDialog));
		}
	}
	var engBtn = document.querySelector('#engBtn');
	if(engBtn) {
		engBtn.addEventListener('click', setTexts.bind(window, "en"));
	}
	
	var zhBtn = document.querySelector('#zhBtn');
	if(zhBtn) {
		zhBtn.addEventListener('click', setTexts.bind(window, "zh"));
	}
	
	encodeMeta = new MetaEntries();
	Polymer.dom(document.documentElement).appendChild(encodeMeta);

	document.addEventListener('alert', function(e) { UI.alert(e.detail.msg); } );
	document.addEventListener('species-changed', function(e) { 
		toggleWindow('trackSelect', 'hide');
		if(document.querySelector('genemo-track-filter')) {
			document.querySelector('genemo-track-filter').initialize(e.detail.newRef, expMap, cellLineMap, tissueMap, labMap);
		}
	} );
	document.addEventListener('toggle-window', function(e) { toggleWindow('trackSelect', e.detail.action); } );
	document.addEventListener('species-ready', function(e) { allSpeciesDoneCheck(spcArray, cmnTracks, cmnTracksEncode);} );
	document.addEventListener('filter-tracks', function(e) { filterTracksFromList(e.detail.map, e.detail.flags);} );
	
	toggleEncode();
<?php
	// this is loading part
	if($sessionInfo) {
?>
	var sessionObj = new Object();
	sessionObj.id = '<?php echo $sessionInfo['id']; ?>';
	sessionObj.db = '<?php echo $sessionInfo['db']; ?>';
	sessionObj.list = '<?php echo $sessionInfo['selected_tracks']; ?>';
	sessionObj.urlToShow = '<?php echo $sessionInfo['display_file_url']; ?>';
	sessionObj.originalFile = '<?php echo ($sessionInfo['original_file_name']? $sessionInfo['original_file_name']: basename($sessionInfo['display_file_url'])); ?>';
	sessionObj.hasDisplay = <?php echo ((strpos($sessionInfo['display_file_url'], $sessionInfo['id']) !== false) || (strpos($sessionInfo['display_file_url'], $sessionInfo['original_file_name']) !== false))? 'false': 'true'; ?>;
	sessionObj.searchRange = '<?php echo trim($sessionInfo['search_range']); ?>';
	
	fireCoreSignal('updatecontent', {sessionObj: sessionObj});
	
	for(var i = 0; i < spcArray.length; i++) {
		spcArray[i].isActive = (spcArray[i].db == sessionObj.db);
	}
	spcArray.updateAllSpcActiveNum();
	
	trackUpdatedCallback.func = loadResults;
	trackUpdatedCallback.data = sessionObj;
<?php		
	} elseif(isset($sessionError)) {
?>
	trackUpdatedCallback.func = function(data) { UI.alert.call(UI, data); };
	trackUpdatedCallback.data = '<?php echo $sessionError; ?>';
<?php		
	}
?>
});

</script>
</head>
<body unresolved class="<?php echo $genemoOn? "firstIndex": "twoColLiqLt"; ?>" onresize="resize_tbody();">
<?php include_once(realpath(dirname(__FILE__) . '/../includes/analyticstracking.php')); ?>
<div id="genemo-container">
  <div id="sidebar1">
    <div id="logoholder"> <a href="genemo/index.php" target="_self"><img src="images/genemologo.png" alt="GENEMO Logo" border="0" /></a> </div>
      <?php if(!isset($experimentalFeatures)) { ?>
    <genemo-card collapse-group='query-search'>
      <?php } else { ?>
    <genemo-tab-cards collapse-group='query-search' id='tabPages' selected-tab='<?php echo $genemoOn? 0: 1; ?>'>
      <?php }
		    if($genemoOn) { ?>
      <search-card-content class='tabContent GenemoBody' id='searchCard' is-encode-on='<?php echo $encodeOn? "true": "false"; ?>'> </search-card-content>
      <?php }
		    if($genemoOn === isset($experimentalFeatures)) { ?>
      <query-card-content class='tabContent GenemoBody' id='queryCard' is-encode-on='<?php echo $encodeOn? "true": "false"; ?>'> </query-card-content>
      <?php }
		    if(isset($experimentalFeatures)) { ?>
    </genemo-tab-cards>
      <?php } else { ?>
    </genemo-card>
      <?php } ?>
    <div style="display: none;">
      <iframe style="display: none;" name="uploadFileHolder" id="uploadFileHolder"></iframe>
    </div>
    <div class="header" id="genelistHeader" onclick="togglePanel('genelist', false);"> <span class="tableHeader"><span class="headerIndicator" id="genelistIndicator">[-]</span><span class="text" id="Results Panel Name"> Results</span></span><div style="float: right; display: none" id="bedDownloadHolder">[<a id="bedDownloadLink" target="_blank">Export BED file</a>]</div></div>
    <div id="genelistHolder">
      <div id="genelistLoading" class="loadingCover BoxHide" style="min-height: 36px; left: 0px; width: auto;">
        <div class="loadingCoverBG"></div>
        <div class="loadingCoverImage"></div>
      </div>
      <div id="genelistContentHolder"> </div>
      <!-- end #genelist --> 
      <!--<div id="genelistfooter"> <span class="smallformstyle">(*): there is no orthologous region in that section.</span> </div>--> 
    </div>
    <div class="header" id="navigationHeader" onclick="togglePanel('navigation', false);"> <span class="tableHeader"><span class="headerIndicator" id="navigationIndicator">[-]</span><span class="text" id="Navigation Panel Name"> Navigation</span></span></div>
    <div class="smallformstyle" id="navigationHolder">
      <div style="border: 1px #000000 solid;" id="masterHolder" class="BoxHide">
        <div id="masterLoading" class="loadingCover" style="height: 36px; width: 218px;">
          <div class="loadingCoverBG"></div>
          <div class="loadingCoverImage"></div>
        </div>
        <table width="100%" border="0" align="center" cellpadding="0" cellspacing="0" style="table-layout: fixed;">
          <tr>
            <td rowspan="2" scope="col" class="speciesHeader"><span><span class="text" id="Master Control Box">MASTER CONTROL</span></span></td>
            <td align="center" valign="middle" scope="col" style="padding-top: 2px;"><div onmouseover="showHover('L3');" onmouseout="hideHover('L3');" class="toolButtonImage" style="float: left;" title="Move 95% to upstream" onclick="callMasterViewChange('left3', false);"><img src="images/arrowsl3.gif" width="17" height="8" class="toolImage" /></div>
              <div onmouseover="showHover('L2');" onmouseout="hideHover('L2');" class="toolButtonImage" style="float: left;" title="Move 47.5% to upstream" onclick="callMasterViewChange('left2', false);"><img src="images/arrowsl2.gif" width="12" height="8" class="toolImage" /></div>
              <div onmouseover="showHover('L1');" onmouseout="hideHover('L1');" class="toolButtonImage" style="float: left;" title="Move 10% to upstream" onclick="callMasterViewChange('left1', false);"><img src="images/arrowsl1.gif" width="7" height="8" class="toolImage" /></div>
              <div onmouseover="showHover('R3');" onmouseout="hideHover('R3');" class="toolButtonImage" style="float: right;" title="Move 95% to downstream" onclick="callMasterViewChange('right3', false);"><img src="images/arrowsr3.gif" width="17" height="8" class="toolImage" /></div>
              <div onmouseover="showHover('R2');" onmouseout="hideHover('R2');" class="toolButtonImage" style="float: right;" title="Move 47.5% to downstream" onclick="callMasterViewChange('right2', false);"><img src="images/arrowsr2.gif" width="12" height="8" class="toolImage" /></div>
              <div onmouseover="showHover('R1');" onmouseout="hideHover('R1');" class="toolButtonImage" style="float: right;" title="Move 10% to downstream" onclick="callMasterViewChange('right1', false);"><img src="images/arrowsr1.gif" width="7" height="8" class="toolImage" /></div>
              <span id="overallGeneName"><span class="text" id="Gene Name">Gene name</span></span>
              <!--<div style="clear: both"></div>--></td>
          </tr>
          <tr>
            <td align="center" valign="middle" style="padding-bottom: 2px;"><img src="images/zoomin.gif" alt="ZoomIn" width="12" height="12" class="iconImage" style="float: left;" />
              <div onmouseover="showZoomHover('I1');" onmouseout="hideZoomHover('I1');" class="toolButton" style="float: left;" onclick="callMasterViewChange('in1', true);">1.5x</div>
              <div onmouseover="showZoomHover('I2');" onmouseout="hideZoomHover('I2');" class="toolButton" style="float: left;" onclick="callMasterViewChange('in2', true);">3x</div>
              <div onmouseover="showZoomHover('I3');" onmouseout="hideZoomHover('I3');" class="toolButton" style="float: left;" onclick="callMasterViewChange('in3', true);">10x</div>
              <div onmouseover="showZoomHover('I4');" onmouseout="hideZoomHover('I4');" class="toolButton" style="float: left;" onclick="callMasterViewChange('inBase', true);"><span class="text" id="Base(Zooming)">Base</span></div>
              <div onmouseover="showZoomHover('O1');" onmouseout="hideZoomHover('O1');" class="toolButton" style="float: right;" onclick="callMasterViewChange('out3', true);">10x</div>
              <div onmouseover="showZoomHover('O2');" onmouseout="hideZoomHover('O2');" class="toolButton" style="float: right;" onclick="callMasterViewChange('out2', true);">3x</div>
              <div onmouseover="showZoomHover('O3');" onmouseout="hideZoomHover('O3');" class="toolButton" style="float: right;" onclick="callMasterViewChange('out1', true);">1.5x</div>
              <img src="images/zoomout.gif" alt="ZoomOut" width="12" height="12" class="iconImage" style="float: right;" /> 
              <!--<div style="clear: both;"></div>--></td>
          </tr>
        </table>
      </div>
      <div id="navigationContent"> </div>
    </div>
    <paper-button class="fullWidth" noink raised id="manualBtn">
      <iron-icon class="smallInline" icon="genemo-iconset:manual-icon" alt="manual"></iron-icon>
      <span class="text" id="Genemo Manual">Genemo Manual </span></paper-button>
    <paper-button class="fullWidth" noink raised id="videoBtn">
      <iron-icon class="smallInline" icon="notification:ondemand-video" alt="manual"></iron-icon>
      <span class="text" id="Video Intro">Video introduction </span></paper-button>
	  <paper-button class="halfWidth" noink raised id="engBtn">
		English<img src="https://www.pegasusautoracing.com/Images/S/3610-101.GIF" style="width:50px;height:30px;">
		</paper-button>
		<paper-button class="halfWidth" noink raised id="zhBtn">  中文  <br>
		<img src="http://www.flags.net/images/smallflags/CHIN0001.GIF"  style="width:50px;height:30px;">
		</paper-button>
    <!-- end #sidebar1 --> 
    <paper-dialog with-backdrop id="videoDialog">
      <google-youtube id="videoPlayer" video-id="r0SQHCOth2A" height="540px" width="960px" rel="0">
      </google-youtube>
    </paper-dialog>
  </div>
  <div id="spcNaviTemplate" class="BoxHide">
    <div id="spcDbNameLoading" class="loadingCover" style="height: 50px; width: 218px;">
      <div class="loadingCoverBG"></div>
      <div class="loadingCoverImage"></div>
    </div>
    <table width="100%" border="0" align="center" cellpadding="0" cellspacing="0" style="table-layout: fixed; height: 50px;">
      <tr>
        <td rowspan="3" scope="col" class="speciesLead"><span><span class="text" id="Species Common Name">spcCmnName</span></span></td>
        <td align="center" valign="middle" scope="col" style="padding: 2px;"><span id="spcDbNameCoor">spcCoor</span></td>
      </tr>
      <tr>
        <td align="center" valign="middle"><div id="spcDbNameleft3" class="toolButtonImage" style="float: left;" title="Move 95% to the left" onclick="callViewChange('spcDbName', 'left3');"> <img src="images/arrowsl3.gif" width="17" height="8" class="toolImage" /> </div>
          <div id="spcDbNameleft2" class="toolButtonImage" style="float: left;" title="Move 47.5% to the left" onclick="callViewChange('spcDbName', 'left2');"><img src="images/arrowsl2.gif" width="12" height="8" class="toolImage" /></div>
          <div id="spcDbNameleft1" class="toolButtonImage" style="float: left;" title="Move 10% to the left" onclick="callViewChange('spcDbName', 'left1');"><img src="images/arrowsl1.gif" width="7" height="8" class="toolImage" /></div>
          <div id="spcDbNameright3" class="toolButtonImage" style="float: right;" title="Move 95% to the right" onclick="callViewChange('spcDbName', 'right3');"><img src="images/arrowsr3.gif" width="17" height="8" class="toolImage" /></div>
          <div id="spcDbNameright2" class="toolButtonImage" style="float: right;" title="Move 47.5% to the right" onclick="callViewChange('spcDbName', 'right2');"><img src="images/arrowsr2.gif" width="12" height="8" class="toolImage" /></div>
          <div id="spcDbNameright1" class="toolButtonImage" style="float: right;" title="Move 10% to the right" onclick="callViewChange('spcDbName', 'right1');"><img src="images/arrowsr1.gif" width="7" height="8" class="toolImage" /></div>
          <span class="text" id="Species Gene Name">spcGeneName </span>
          <!--<div style="clear: both"></div>--></td>
      </tr>
      <tr>
        <td align="center" valign="middle" scope="col"><img src="images/zoomin.gif" alt="ZoomIn" width="12" height="12" class="iconImage" style="float: left;" />
          <div id="spcDbNameI1" class="toolButton" style="float: left;" onclick="callViewChange('spcDbName', 'in1');">1.5x</div>
          <div id="spcDbNameI2" class="toolButton" style="float: left;" onclick="callViewChange('spcDbName', 'in2');">3x</div>
          <div id="spcDbNameI3" class="toolButton" style="float: left;" onclick="callViewChange('spcDbName', 'in3');">10x</div>
          <div id="spcDbNameI4" class="toolButton" style="float: left;" onclick="callViewChange('spcDbName', 'inBase');"><span class="text" id="Base(Zooming)">Base</span></div>
          <div id="spcDbNameO1" class="toolButton" style="float: right;" onclick="callViewChange('spcDbName', 'out3');">10x</div>
          <div id="spcDbNameO2" class="toolButton" style="float: right;" onclick="callViewChange('spcDbName', 'out2');">3x</div>
          <div id="spcDbNameO3" class="toolButton" style="float: right;" onclick="callViewChange('spcDbName', 'out1');">1.5x</div>
          <img src="images/zoomout.gif" alt="ZoomOut" width="12" height="12" class="iconImage" style="float: right;" /> 
          <!--<div style="clear: both;"></div>--></td>
      </tr>
    </table>
  </div>
  <genemo-track-filter id='mainFilter'></genemo-track-filter>
  <div id="trackSelect" class="trackSelectClass" style="width: 380px; min-height: 275px;" onclick="updateSampleCheckbox();">
    <div class="loadingTrackCover" id="trackSelectLoading">
      <div class="loadingTrackCoverBG"></div>
      <div class="loadingTrackCoverImage"></div>
    </div>
    <div class="headerNoHover"><span class="text" id="Data Selection">Data Selection </span>
      <!--<div class="header buttons" id="EncodeDataButton" style="float: right; padding: 2px 3px; margin: -3px 5px -3px -2px;"
        onclick="toggleEncode();">View ENCODE Data</div>
      <div style="clear: both;"></div>--> 
    </div>
    <div class="settingsNormal"><span class="text" id="Tracks On/Off">Tracks can be turned on/off via the checkboxes below.</span>
      <div id="encodeSampleSettings" style="display: inline;"><span class="text" id="You can also">You can also</span>
        <div class="header buttons" style="display: inline; padding: 2px 3px; margin: -3px 0px -3px -2px;"
    onclick="toggleSample();"><span class="text" id="Choose sample type">Use filters</span></div>
      </div>
      <div style="display: none;">
        <label>
        <input type="checkbox" id="useAllTracks" name="useAllTracks" />
        <span class="text" id="Use all encode data">Use all ENCODE data.</span>
        <div class="inlineDiv">
          <iron-icon class="smallInline transparent" icon="help" alt="help">
          </iron-icon>
          <paper-tooltip position="bottom" offset="0">
            <span class="text" id="Use entire encode dataset">Use the entire ENCODE dataset to query similar tracks instead of the selected ones. </span><br>
            <strong><em><span class="text" id="Caution on using entire encode">Caution: the result may take significant amount of time to compute, so providing your email is highly recommended.</span></em></strong>
          </paper-tooltip>
        </div>
        </label>
      </div>
    </div>
    <div id="NonEncodeData">
      <div class="subBox">
        <div class="subHeader" onclick="toggleSubPanel('cmnTrack', false);"><span class="headerIndicator" id="cmnTrackIndicator">[-]</span><span class="text" id="Common tracks"> Common tracks</span></div>
        <div class="trackHolder" id="cmnTrackHolder"></div>
      </div>
      <div class="subBox">
        <div class="subHeader" onclick="toggleSubPanel('unique', false);"><span class="headerIndicator" id="uniqueIndicator">[-]</span><span class="text" id="Unique tracks"> Unique tracks</span></div>
        <div id="uniqueHolder"></div>
      </div>
    </div>
    <div id="EncodeData" style="overflow-y: auto;" class="BoxHide">
      <div class="subBox ENCODETracks" id='CommonENCODEBox'>
        <div class="subHeader" onclick="toggleSubPanel('cmnTrackEncode', false);"> <span class="headerIndicator" id="cmnTrackEncodeIndicator">[-]</span><span class="text" id="Common tracks encode"> Common tracks from ENCODE</span></div>
        <div class="trackHolder" id="cmnTrackEncodeHolder">
          <table width="100%" style="border-collapse: collapse; border-spacing: 0;">
            <thead>
              <tr class="trackHeaderEncode">
                <th style="width: 35%;"><span class="text" id="Track Name">Track Name</span></th>
                <th><span class="text" id="Sample Type">Sample Type</span></th>
                <th style="width: 20%;"><span class="text" id="Preview">Preview</span></th>
                <th style="width: 5%;"><span class="text" id="Data">Data</span></th>
              </tr>
            </thead>
            <tbody id="cmnTrackEncodeSortedTbodyHolder" style="display: none;">
            </tbody>
            <tbody class="insigTbody" id="cmnTrackEncodeInsigTbodyHolderHeader" style="display: none;">
              <tr>
                <td class="insigHeader" onclick="toggleTbody('cmnTrackEncodeInsigTbody', false);" colspan="4"><span class="headerIndicator" id="spcDbNameEncodeInsigTbodyIndicator">[+]</span><span class="text" id="Insignificant signals"> Tracks with insignificant signals</span></td>
              </tr>
            </tbody>
            <tbody class="insigTbody" id="cmnTrackEncodeInsigTbodyHolder" style="display: none;">
            </tbody>
            <tbody id="cmnTrackEncodeTbodyHolder">
            </tbody>
          </table>
        </div>
      </div>
      <div class="subBox ENCODETracks">
        <div class="subHeader" onclick="toggleSubPanel('uniqueEncode', false);"> <span class="headerIndicator" id="uniqueEncodeIndicator">[-]</span><span class="text" id="Unique encode"> <?php echo $genemoOn? "ENCODE tracks": "Unique tracks from ENCODE" ?></span></div>
        <div id="uniqueEncodeHolder"></div>
      </div>
      <div style="display: none;">
        <?php
	for($i = 0; $i < $num_spc; $i++) {
		
?>
        <iframe onload="setTrackReady(<?php echo $i; ?>);" id="<?php echo $spcinfo[$i]["dbname"] . "_controls"; ?>" 
         name="<?php echo $spcinfo[$i]["dbname"] . "_controls"; ?>" src="<?php 
	  echo "../../cgi-bin/hgTracks" . $spcinfo[$i]["commonname"] . "&db=" . $spcinfo[$i]["dbname"] . "&Submit=submit&hgsid=" . requestSpeciesHgsID($spcinfo[$i]["dbname"]) . '&showEncode=' . ($encodeOn? 'on': 'off') . "&hgControlOnly=on" . ((isset($_SESSION['resetView']) && $_SESSION['resetView'])? "&hgt.reset=TRUE&hgt.defaultImgOrder=TRUE": ""); 
	  ?>"><span class="text" id="Browser support">Your browser doesn't support &lt;iframe&gt; tag. You need a browser supporting &lt;iframe&gt; tag to use Comparison Browser. (Latest versions of mainstream browsers should all support this tag.)</span></iframe>
        <?php
	}
	$_SESSION['resetView'] = false;
		?>
      </div>
    </div>
    <div class="header buttons" style="float: right;" onclick="updateTracks(); hideWindow('trackSelect');"><span class="text" id="Update">Update</span></div>
    <div class="header buttons" style="float: right;" onclick="resetTracks();"><span class="text" id="Reset View">Reset view</span></div>
    <div class="header buttons" style="float: right;" onclick="hideWindow('trackSelect');"><span class="text" id="Close">Close</span></div>
    <div style="clear: both"></div>
  </div>
  <div id="sampleTypeBox" class="downloadBox" style="left: 637px; top: 58px; width: 300px;">
    <div class="subHeaderNoHover"><span class="text" id="Sample List">Sample List</span>
      <div class="header buttons" style="float: right; padding: 2px 3px; margin: -2px;" onclick="hideSample();"><span class="text" id="Close">Close</span></div>
      <div style="clear: both;"></div>
    </div>
    <div class="speciesTrackHeader"><span class="text" id="Common sample types">Common sample types:</span></div>
    <div id="cmnSampleEncodeHolder" style="padding: 4px;"></div>
    <div class="speciesTrackHeader"><span class="text" id="Species only sample types">Species-only sample types</span></div>
    <div id="uniSampleEncodeHolder" style="padding: 4px; max-height: 300px; overflow-y: auto; overflow-x: hide;"></div>
  </div>
  <div id="downloadBox" class="downloadBox" onmouseover="inDownload(true);" onmouseout="inDownload(false);">
    <div class="subHeaderNoHover"><span class="text" id="Download Data">Download Data</span>
      <div class="header buttons" style="float: right; padding: 2px 3px; margin: -2px;" onclick="hideDownload();"><span class="text" id="Close">Close</span></div>
      <div style="clear: both;"></div>
    </div>
    <div id="downloadContent" style="padding: 4px;"></div>
  </div>
  <?php
		if((!isset($_COOKIE['NoTipTrackSettings']) || $_COOKIE['NoTipTrackSettings'] != 'true') && !$genemoOn) {
?>
  <script type="text/javascript">
function hideTrackHint() {
	//$.post('cpbrowser/postcookie.php', { varName: 'NoTipTrackSettings', value: 'false' } );
	$('#trackSelectHint').fadeOut('fast');
}
function hideTrackHintForever() {
	$.post('cpbrowser/postcookie.php', { varName: 'NoTipTrackSettings', value: 'true' } );
	$('#trackSelectHint').fadeOut('fast');
}
//setTimeout("$('#trackSelectHint').fadeOut('fast')", 7500);
</script>
  <div id="trackSelectHint" style="z-index: 20; width: 250px; display: block; padding: 5px; font-family: Verdana, Arial, Helvetica, sans-serif;
font-size: 12px; line-height: 17px; background: #FFFFCC;" class="trackSelectClass"><span class="text" id="Hint"> Hint: tracks can be turned on / off via the <span class="panel">track selection</span> panel, click button on the left to show.</span>
    <div class="header buttons" style="float: right; margin-top: 5px;" onclick="hideTrackHintForever();"><span class="text" id="Do not show">Do not show in the future</span></div>
    <div class="header buttons" style="float: left; margin-top: 5px;" onclick="hideTrackHint();"><span class="text" id="Close">Close</span></div>
    <div style="clear: both"></div>
  </div>
  <?php
		}
?>
  <div style="display: none;" id="uniqueTemplate">
    <div class="speciesTrackHeader"><span class="text" id="Species Common Name">spcCmnName</span></div>
    <div class="trackHolder" id="spcDbNameTableHolder"></div>
  </div>
  <div style="display: none;" id="uniqueEncodeTemplate">
    <div class="trackHolder" id="spcDbNameEncodeTableHolder">
    <div class="speciesTrackHeader"><span class="text" id="Species Common Name">spcCmnName</span></div>
      <table width="100%" style="border-collapse: collapse; border-spacing: 0;">
        <thead>
          <tr class="trackHeaderEncode">
            <th style="width: 30%;"><span class="text" id="Track Name">Track Name</span></th>
            <th style="width: 20%;"><span class="text" id="Sample Type">Sample Type</span></th>
            <th><span class="text" id="Lab">Lab</span></th>
            <th style="width: 15%;"><span class="text" id="Preview">Preview</span></th>
            <th style="width: 7%;">Data</th>
          </tr>
        </thead>
        <tbody id="spcDbNameEncodeSortedTbodyHolder" style="display: none;">
        </tbody>
        <tbody class="insigTbody" id="spcDbNameEncodeInsigTbodyHolderHeader" style="display: none;">
          <tr>
            <td class="insigHeader" onclick="toggleTbody('spcDbNameEncodeInsigTbody', false);" colspan="5"><span class="headerIndicator" id="spcDbNameEncodeInsigTbodyIndicator">[+]</span><span class="text" id="Insignificant spcCmnName Tracks"> Tracks with insignificant signals for spcCmnName</span></td>
          </tr>
        </tbody>
        <tbody class="insigTbody" id="spcDbNameEncodeInsigTbodyHolder" style="display: none;">
        </tbody>
        <tbody id="spcDbNameEncodeTbodyHolder">
        </tbody>
      </table>
    </div>
  </div>
  <div style="display: none;" id="uniqueSampleEncodeTemplate">
    <div class="speciesTrackHeader"><span class="text" id="Species Common Name">spcCmnName</span></div>
    <div class="trackHolder" id="spcDbNameSampleEncodeHolder"></div>
  </div>
  <!--
  <div id="trackSettings" class="trackSettingsClass" style="display: none;">
    <div id="trackSettingsHeader" class="headerNoHover2">Track information &amp; settings</div>
    <div style="position: absolute; top: 45px; left: 0px;">
      <iframe onload="trackSettingsOnLoad();" id="trackSettingFrame" name="trackSettingFrame" class="trackSettingFrame" src="about:blank">Your browser doesn't support &lt;iframe&gt; tag. You need a browser supporting &lt;iframe&gt; tag to use Comparison Browser. (Latest versions of mainstream browsers should all support this tag.)</iframe>
      <div class="header buttons" style="float: right; width: 150px;" onclick="hidePanel('trackSettings');">Close</div>
      <div style="clear: both"></div>
    </div>
  </div>
  -->
  <div id="leftborder">
    <div id="leftbutton" onclick="UI.switchLeft();"></div>
  </div>
  <div id="mainContent">
    <iframe id="cpbrowser" name="cpbrowser" src="cpbrowser.php" width="100%" marginwidth="0" height="100%" marginheight="0" scrolling="auto" frameborder="0">Your browser doesn't support &lt;iframe&gt; tag. You need a browser supporting &lt;iframe&gt; tag to use Comparison Browser. (Latest versions of mainstream browsers should all support this tag.)</iframe>
    <!-- end #mainContent --> 
  </div>
  <!-- This clearing element should immediately follow the #mainContent div in order to force the #container div to contain all child floats --> 
  <br class="clearfloat" />
  <!-- end #container --> 
</div>
<cell-line-info-card></cell-line-info-card>
</body>
</html>
