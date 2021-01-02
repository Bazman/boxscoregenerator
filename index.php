<?php

include 'simple_html_dom.php';
date_default_timezone_set('America/Los_Angeles');

$statIndex = [
	0, // Name
	2, // Mins
	14, // Pts
	3,
	5, // 3PT
	4, // FT
	6,
	7,
	8,
	9,
	10,
	11,
	12,
	13,
];

$games = json_decode(file_get_contents("api/v1/games.json"), true);

$ready = false;
if (isset($_GET['gameID']) && is_numeric($_GET['gameID'])) {
	$gameID = $_GET['gameID'];
	$ready = true;
}

if ($ready) {
	$gameChosen = $games['games'][$gameID];

	$visitorShort = getShortName($gameChosen['visitor']);
	$visitorName = $gameChosen['visitor'];
	$visitorScore = $gameChosen['visitor_score'];
	$visitorBox = $gameChosen['visitor_boxscore'];

	$homeShort = getShortName($gameChosen['home']);
	$homeName = $gameChosen['home'];
	$homeScore = $gameChosen['home_score'];
	$homeBox = $gameChosen['home_boxscore'];

	$textToReddit = getRedditText($visitorShort, $visitorName, $visitorScore, $visitorBox, $homeShort, $homeName, $homeScore, $homeBox);
}



/*
 * Function getBoxScore
 *
 * This function forms a 2D array containing box score data.
 * 1 Row for each player (> 7 and <= 15) plus one for team totals.
 * Columns are: Name, POS, MIN, FGM-A, 3PM-A, FTM-A, +/-, OFF, DEF, TOT
 * AST, PF, ST, TO, BS, BA, PTS except for when a player doesn't play,
 * in that case columns while be: Name and Comment.
 *
 * @param (DOM object)
 * @return (array)
*/
function getBoxScore($teamData)
{
	$teamArray = array();
	$i = 0;
	$rowCount = count($teamData->find('tr'));
	foreach ($teamData->find('tr') as $row) {
		// Ignore first 3 rows
		if ($i >= 3) {
			$teamArray[$i - 3] = array();
			$j = 0;
			for ($j = 0; $j < 17; $j++) {
				$col = $row->find('td', $j);
				if ($col != "") {
					if ($j == 0 && $i != $rowCount - 2) {
						$teamArray[$i - 3][$j] = $col->find('a', 0)->innertext;
					} else {
						$teamArray[$i - 3][$j] = $col->innertext;
					}
				} else {
					$teamArray[$i - 3][$j] = "-";
				}
			}
		}
		$i++;
	}
	return $teamArray;
}

/*
 * Function printBoxScore
 *
 * This function prints a 2D array containing a team's box score
 * data.
 *
 * @param (array)
*/
function printBoxScore($teamArray)
{
	$lenI = count($teamArray);
	for ($i = 0; $i < $lenI; $i++) {
		$lenJ = count($teamArray[$i]);
		for ($j = 0; $j < $lenJ; $j++) {
			echo $teamArray[$i][$j] . " | ";
		}
		echo $i . "<br>";
	}
}

function short($player)
{
	return substr($player, 0, 1) . ". " . strstr($player, " ");
}

function noDash($date)
{
	return str_replace("-", "", $date);
}

function dash($date)
{
	return substr($date, 0, 4) . "-" . substr($date, 4, 2) . "-" . substr($date, 6, 2);
}

function printHTMLTable($name, $short, $boxscore)
{
	global $statIndex;
?>
	<div class="row">
		<div class="col-md-10 col-md-offset-1">
			<table class="table table-hover" style='text-align: center'>
				<thead>
					<tr style='font-weight: bold'>
						<th style='text-align: left'><?php echo $name; ?></th>
						<?php
						$numCols = count($statIndex);
						for ($i = 0; $i < $numCols; $i++) {
							// Don't show Player
							if ($i >= 1) {
								$bi = $statIndex[$i];
								echo "<th>" . $boxscore[0][$bi] . "</th>";
							}
						}
						?>
					</tr>
				</thead>
				<tbody>
					<?php
					$lenI = count($boxscore);
					for ($i = 1; $i < $lenI; $i++) {
						$lenJ = count($statIndex); ?>
						<tr>
							<?php
							if (count($boxscore[$i]) == 2) {
								echo "<td style='text-align: left'>" . $boxscore[$i][0] . "</td>";
								// show if its a DNP - reason column
								echo "<td colspan=13>" . $boxscore[$i][1] . "</td>";
							} else {
								for ($j = 0; $j < $lenJ; $j++) {
									$bi = $statIndex[$j];
									if ($bi == 0) {
										// Name
										echo "<td style='text-align: left'>" . $boxscore[$i][$bi] . "</td>";
									} else {
										echo "<td>" . $boxscore[$i][$bi] . "</td>";
									}
								}
							}
							?>
						</tr>
					<?php
					}
					?>
				</tbody>
			</table>
		</div>
	</div>
<?php
}

function getRedditText($awayShort, $awayName, $awayScore, $awayBox, $homeShort, $homeName, $homeScore, $homeBox)
{
	global $statIndex;
	$text = "
**[](/" . $awayShort . ") " . $awayShort . "**|";

	$numCols = count($statIndex);
	for ($i = 0; $i < $numCols; $i++) {
		if ($i >= 1) {
			$bi = $statIndex[$i];
			$text .= "**" . $awayBox[0][$bi] . "**|";
		}
	}

	$text .= "
|:---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
";

	$text .= getTableText($awayBox);

	$text .= "
**[](/" . $homeShort . ") " . $homeShort . "**|";
	$numCols = count($statIndex);
	for ($i = 0; $i < $numCols; $i++) {
		if ($i >= 1) {
			$bi = $statIndex[$i];
			$text .= "**" . $homeBox[0][$bi] . "**|";
		}
	}

	$text .= "
|:---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
";

	$text .= getTableText($homeBox);

	$text .= "
||
|:-:|
|^[boxscoregenerator.sportsdeck.com](https://boxscoregenerator.sportsdeck.com/) ^by ^/u/bazman1976 ^(original work by /u/Obi-Wan_Ginobili)|";

	return $text;
}

function getTableText($box)
{
	global $statIndex;
	$text = "";
	$lenI = count($box);
	$numCols = count($statIndex);
	for ($i = 1; $i < $lenI; $i++) {
		$lenJ = count($box[$i]);
		if ($lenJ == 2) {
			// DNP
			$text .= $box[$i][0] . "|";
			for ($j = 0; $j < $numCols - 1; $j++) {
				$text .= "|";
			}
		} else {
			for ($j = 0; $j < $numCols; $j++) {
				$bi = $statIndex[$j];
				$text .= $box[$i][$bi] . "|";
			}
		}
		$text .= "\n";
	}
	return $text;
}

function getShortName($teamName)
{
	switch ($teamName) {
		case 'Boston':
			return "BOS";
		case 'Brooklyn':
			return "BKN";
		case 'New York':
			return "NYK";
		case 'Philadelphia':
			return "PHI";
		case 'Toronto':
			return "TOR";
		case 'Chicago':
			return "CHI";
		case 'Cleveland':
			return "CLE";
		case 'Detroit':
			return "DET";
		case 'Indiana':
			return "IND";
		case 'Milwaukee':
			return "MIL";
		case 'Atlanta':
			return "ATL";
		case 'Charlotte':
			return "CHA";
		case 'Miami':
			return "MIA";
		case 'Orlando':
			return "ORL";
		case 'Washington':
			return "WAS";
		case 'Golden State':
			return "GSW";
		case 'LA Clippers':
			return "LAC";
		case 'LA Lakers':
			return "LAL";
		case 'Phoenix':
			return "PHX";
		case 'Sacramento':
			return "SAC";
		case 'Dallas':
			return "DAL";
		case 'Houston':
			return "HOU";
		case 'Memphis':
			return "MEM";
		case 'New Orleans':
			return "NOP";
		case 'San Antonio':
			return "SAS";
		case 'Denver':
			return "DEN";
		case 'Minnesota':
			return "MIN";
		case 'Oklahoma City':
			return "OKC";
		case 'Portland':
			return "POR";
		case 'Utah':
			return "UTA";
		default:
			return "";
	}
}

?>

<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>NBA Box Score Generator</title>

	<link rel="shortcut icon" href="chalmers.ico">

	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css" crossorigin="anonymous">

	<link rel="stylesheet" href="https://cdn.materialdesignicons.com/1.5.54/css/materialdesignicons.min.css">

	<!-- Latest compiled and minified JavaScript -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js" crossorigin="anonymous"></script>


	<style type="text/css">
		.mdi::before {
			font-size: 24px;
			line-height: 14px;
		}

		.btn .mdi::before {
			position: relative;
			top: 4px;
		}

		.btn-xs .mdi::before {
			font-size: 18px;
			top: 3px;
		}

		.btn-sm .mdi::before {
			font-size: 18px;
			top: 3px;
		}

		.dropdown-menu .mdi {
			width: 18px;
		}

		.dropdown-menu .mdi::before {
			position: relative;
			top: 4px;
			left: -8px;
		}

		.nav .mdi::before {
			position: relative;
			top: 4px;
		}

		.navbar .navbar-toggle .mdi::before {
			position: relative;
			top: 4px;
			color: #FFF;
		}

		.breadcrumb .mdi::before {
			position: relative;
			top: 4px;
		}

		.breadcrumb a:hover {
			text-decoration: none;
		}

		.breadcrumb a:hover span {
			text-decoration: underline;
		}

		.alert .mdi::before {
			position: relative;
			top: 4px;
			margin-right: 2px;
		}

		.input-group-addon .mdi::before {
			position: relative;
			top: 3px;
		}

		.navbar-brand .mdi::before {
			position: relative;
			top: 2px;
			margin-right: 2px;
		}

		.list-group-item .mdi::before {
			position: relative;
			top: 3px;
			left: -3px
		}
	</style>
</head>

<body>
	<div class="container">
		<div class="row">
			<h1 style="text-align: center; padding-bottom: 10px;">NBA Box Score Generator for Reddit</h1>
			<div class="col-md-3 col-md-offset-2">

				<!-- FORM TO CHOOSE DATE -->
				<!--
				<form action="" method="GET">
					<div class="row">
						<div class="col-md-10" style="padding-right: 5px">
							<div class="input-group">
								<span class="input-group-addon" id="basic-addon1">Date</span>
								<input type="date" class="form-control" style="max-width: 170px;" name="date" value="<?php echo $date; ?>">
							</div>
						</div>
						<div class="col-md-1" style="padding-left: 5px">
							<input type="submit" value="Enter" class="btn btn-primary">
						</div>
					</div>
				</form>
				-->

				<form action="" method="GET">
					<div class="row" style="margin-top: 15px">
						<div class="col-md-8">
							<select name="gameID">
								<?php
								foreach ($games['games'] as $game) {
									$id = $game['id'];
									$visitor = $game['visitor'];
									$home = $game['home'];
									$matchup = $visitor . " @ " . $home;

									echo "<option value='" . $id . "'>" . $matchup . "</option>";
								}
								?>
							</select>
							<!--<input type="hidden" name="date" value="<?php echo $date; ?>"></input>-->
							<input type="submit" value="Go!" class="btn btn-primary">
						</div>
					</div>
				</form>
				<p><B>Now with LIVE results!</B></p>

			</div>
			<div class="col-md-6 col-md-offset-1">
				<p>Made for <a href="http://reddit.com/r/nba">/r/NBA</a> by <a href="https://www.reddit.com/user/bazman1976/">/u/bazman1976</a> (originally by <a href="http://reddit.com/user/Obi-Wan_Ginobili">/u/Obi-Wan_Ginobili</a>).
					<br>
					Report any issues on <a href="https://github.com/Bazman/boxscoregenerator">Github <i class="mdi mdi-github-circle"></i></a> or send me a <a href="https://www.reddit.com/user/bazman1976/">PM</a>.
			</div>
		</div> <!-- End row -->

		<hr>

		<?php
		if ($ready) {

			// Print HTML box score tables
			printHTMLTable($games['games'][$gameID]['visitor'], $games['games'][$gameID]['visitor'], $games['games'][$gameID]['visitor_boxscore']);
			printHTMLTable($games['games'][$gameID]['home'], $games['games'][$gameID]['home'], $games['games'][$gameID]['home_boxscore']);
		?>

			<div class="row">
				<div class="col-md-10 col-md-offset-1">
					<h2>Copy text below to Reddit</h2>
					<p>Use Ctrl-A</p>
					<textarea cols="130" rows="50"><?php echo $textToReddit; ?></textarea>
				</div>
			</div>
		<?php
		} else {
		?>
			<div class="row">
				<div class="col-md-10 col-md-offset-1">
					<!--<h3 style="text-align: center">Game hasn't started or values are invalid.</h3>-->
					<h3 style="text-align: center">Select a game from the list and click Go!</h3>
					<p style="text-align: center">Empty list means no games have started.</p>
					<br><br>

					<a href="https://play.google.com/store/apps/details?id=com.gmail.jorgegilcavazos.ballislife">
						<div style="text-align: center">
							<h4>Download Swish! An android app /u/Obi-Wan_Ginobili made for /r/NBA</h4>
							<img style="width: 200px;" alt='Get it on Google Play' src='https://play.google.com/intl/en_us/badges/static/images/badges/en_badge_web_generic.png' />
						</div>
					</a>
				</div>
			</div>
		<?php
		}
		?>
	</div>
	<script>
		(function(i, s, o, g, r, a, m) {
			i['GoogleAnalyticsObject'] = r;
			i[r] = i[r] || function() {
				(i[r].q = i[r].q || []).push(arguments)
			}, i[r].l = 1 * new Date();
			a = s.createElement(o),
				m = s.getElementsByTagName(o)[0];
			a.async = 1;
			a.src = g;
			m.parentNode.insertBefore(a, m)
		})(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');

		ga('create', 'UA-75603173-1', 'auto');
		ga('send', 'pageview');
	</script>
</body>

</html>