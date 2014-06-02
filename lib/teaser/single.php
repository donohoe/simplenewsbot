<?php

$api   = "http://www.nytimes.com/chrome/backend/services/full.html?uri=http://platforms.nytimes.com/mobile/v1/json/skimmer/homepage.json";
$cache = 'single.json';
$html  = "";

require("class.phpteaser.php");

//if(!file_exists($cache)){

$html = genHTML($cache);

// if(file_exists($cache) && filemtime($cache) > time() - 60*60){
// 	$html = genHTML($cache);
// } else {
// 	file_put_contents($cache, file_get_contents($api));
// }

function genHTML($cache) {
	$news = json_decode(file_get_contents($cache),true);
	$html = '';
	foreach($news['assets'] as $article) {
		$summary = new Teaser();
		$content = $summary->createSummary($article['body'],'text',$article['title']);
		$html .= '<div class="article">';
		$html .= '<div class="article-section">'.$article['sectionDisplayName'].'</div>';
		$html .= '<h1 class="article-title"><a href="'.$article['url'].'" class="article-link">'.$article['title'].'</a></h1>';
		// $html .= '<div class="article-summary">'.$article['summary'].'</div>';
		$html .= '<ul class="artile-list"><li class="article-item">'.implode('</li><li class="article-item">',$content).'</li></ul>';
		$html .= '</div>';
	}
	return $html;
}

?>

<!DOCTYPE HTML>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>News</title>
	<link rel="stylesheet" type="text/css" media="screen" href="style.css" />
</head>
<body>
	<div class="wrap">
		<?php echo $html; ?>
	</div>
</body>
</html>
