<?php

define("PATH", dirname( __FILE__ ));

require(PATH . '/lib/twitter-async/EpiCurl.php');
require(PATH . '/lib/twitter-async/EpiOAuth.php'); /* Modified this library to address a bug posting images */
require(PATH . '/lib/twitter-async/EpiTwitter.php');
require(PATH . '/lib/teaser/class.phpteaser.php');
require(PATH . '/lib/simple_html_dom.php');

class NewsBot {

	private $maxAge;
	private $debug;
	private $sources;
	private $selectors;
	private $historyFilename;
	private $lastPostedItem;
	private $bitlyToken;

	public function __construct() {

	/*	Twitter @simplenewsbot */

		$this->twitter_handle     = "simplenewsbot"; /* You may not want to use MY account */
		$this->consumer_key       = 'THE VARIABLE NAME EXPLAINS WHAT GOES HERE';
		$this->consumer_secret    = 'THE VARIABLE NAME EXPLAINS WHAT GOES HERE';

		$this->oauth_token        = 'THE VARIABLE NAME EXPLAINS WHAT GOES HERE';
		$this->oauth_token_secret = 'THE VARIABLE NAME EXPLAINS WHAT GOES HERE';

	/*	Bitly */

		$this->bitlyToken         = 'THE VARIABLE NAME EXPLAINS WHAT GOES HERE'; /* See: https://bitly.com/a/oauth_apps */

	/*	Source material */

		$this->sources = array(
			array('NYTimes',    'http://www.nytimes.com/services/xml/rss/nyt/HomePage.xml'),
			array('Guardian',   'http://www.theguardian.com/world/rss'),
			array('New Yorker', 'http://www.newyorker.com/online/blogs/newsdesk/rss.xml'),
			array('BBC',        'http://feeds.bbci.co.uk/news/world/rss.xml'),
			array('Aljazeera',  'http://america.aljazeera.com/content/ajam/articles.rss'),
			array('Reuters',    'http://feeds.reuters.com/reuters/topNews?format=xml')
		);

		$this->selectors = array(
			'NYTimes'    => array('meta[property=og:title]', 'article#story, article div.entry-content', 'meta[property=og:image]'),
			'Guardian'   => array('meta[property=og:title]', 'div[itemprop=articleBody], div#article-body-blocks', 'meta[property=og:image]'),
			'New Yorker' => array('meta[property=og:title]', 'div.entry-content', 'meta[property=og:image]'),
			'BBC'        => array('meta[property=og:title]', 'div.story-body',    'meta[property=og:image]'),
			'Aljazeera'  => array('meta[property=og:title]', 'div.mainpar',       'meta[property=og:image]'),
			'Reuters'    => array('meta[property=og:title]', '#articleText',      'meta[property=og:image]')
		);

		$this->sourceUrlBlacklist = array(
			"movies", "sports"
		);

		$this->maxAge           = strtotime("-3 hours");
		$this->MaxResultPerFeed = 5;

	/*	Misc */

		$this->historyFilename  = 'tmp/history.js';
		$this->lastPostedItem   = false;

	/*	Numbers & Stats */

		$this->debug  = ($_GET["debug"]==="true") ? true : false;
	}

	public function __destruct() {
		/*clean Up */
	}

	public function gather() {
		$list    = $this->getList();
		$article = $this->getArticle($list);
		$result  = $this->sendTweet($article);
		return $this->dropMic($result);
	}

	private function getArticle($list) {

		$candidiate = $list[0];

		print "<p>Candidate:</p><pre class='short-preview'>";
		print_r($candidiate);
		print "</pre><hr>";

		$article = $this->getArticleData($candidiate);

		if ($article['image'] != "") {
			$hasImage = $this->getImage($article);
			if ($hasImage == true ) {
				$article['card'] = "y";
			}
		}

		print "<p>Article data:</p><pre class='short-preview'>";
		print_r($article);
		print "</pre><hr>";

		$this->updateRecentHistory($article);

		return $article;
	}

/*	Twitter */

	private function sendTweet($article) {

		if ($article['card'] != "y") {
			print "<p><b>Fail</b>: No card image to post with tweet.</p>";
			return;
		}

		if (strlen($article['headline'])>100) {
			print "<p><b>Fail</b>: Headline is too long (>100) for a tweet</p>";
			return;
		}

		$message  = $article['headline'] . ' ' . $article['shortUrl'];

		if ($this->debug===true) {
			print "<p><b>Debug enabled</b> <span style='color:red;'><b>No tweet sent.</b></span></p>";
			return;
		}

		$this->twitter = new EpiTwitter($this->consumer_key, $this->consumer_secret, $this->oauth_token, $this->oauth_token_secret);

		$response = $this->twitter->post('/statuses/update_with_media.json',
			array(
				'status'   => $message,
				'@media[]' => '@tmp/twitter_image.jpg'
			),
			true,
			true
		);

		if ($response->code != 200) {
			print "<p style='color:red;'>Tweet failed :(</p>";
		} else {
			print "<p style='color:green;'>Success. Tweet tweeted to Twitter</p>";
			print "<blockquote class='twitter-tweet' lang='en'><a href='https://twitter.com/simplenewsbot/statuses/" . $response->id ."'></a></blockquote>\n";
			print "<script async src='//platform.twitter.com/widgets.js' charset='utf-8'></script>\n";
		}

		print "<hr><p>Full Twitter response:</p><pre class='short-preview'>";
		print_r(json_decode($response->responseText));
		print "</pre>";
	}

/* 	Image */

	public function getImage($article) {

		if ($article == false ) {
			return false;
		}

		if (count($article['lines'])>4) {
			print "<p style='color:red;'>Text too long. Too many lines</p>";
			return false;
		}

		$desired_image_width  = 640;
		$desired_image_height = 358;
		$min_width            = 460; // BBC :(

		$source_path      = "./tmp/remote_image.jpg";
		$destination_path = "./tmp/twitter_image.jpg";
		$fontFamily       = "./fonts/Impact.ttf";

		$text_stroke_thicknoess = 1;

	//	Get remote Image and Resize as nesesary...

		$remote_image = file_get_contents($article['image']);
		file_put_contents($source_path, $remote_image);
		list($source_width, $source_height, $source_type) = getimagesize($source_path);

		if ($source_width < $min_width) {
			print "<p style='color:red;'>Image too small (" . $source_width . "x" . $source_height . ")</p>";
			return false;
		}

		switch ($source_type) {
			case IMAGETYPE_GIF:
				$source_gdim = imagecreatefromgif($source_path);
				break;
			case IMAGETYPE_JPEG:
				$source_gdim = imagecreatefromjpeg($source_path);
				break;
			case IMAGETYPE_PNG:
				$source_gdim = imagecreatefrompng($source_path);
				break;
		}

		$source_aspect_ratio  = $source_width / $source_height;
		$desired_aspect_ratio = $desired_image_width / $desired_image_height;

		if ($source_aspect_ratio > $desired_aspect_ratio) {
			$temp_height = $desired_image_height;
			$temp_width  = ( int ) ($desired_image_height * $source_aspect_ratio);
		} else {
			$temp_width  = $desired_image_width;
			$temp_height = ( int ) ($desired_image_width / $source_aspect_ratio);
		}

		$temp_gdim = imagecreatetruecolor($temp_width, $temp_height);
		imagecopyresampled($temp_gdim, $source_gdim, 0, 0, 0, 0, $temp_width, $temp_height, $source_width, $source_height);

		$x0 = ($temp_width  - $desired_image_width)  / 2;
		$y0 = ($temp_height - $desired_image_height) / 2;

		$im = imagecreatetruecolor($desired_image_width, $desired_image_height);
		imagecopy($im, $temp_gdim, 0, 0, $x0, $y0, $desired_image_width, $desired_image_height);

		$margin_vert  = 20;

		$font_color   = imagecolorallocate($im, 255, 255, 255);
		$stroke_color = imagecolorallocate($im, 0, 0, 0);

		$fontHeight   = 20;
		$lineHeight   = $fontHeight;
		$x            = 18;
		$y            = $desired_image_height - ($lineHeight * (count($article['lines'])+1)) - $margin_vert;

		$line_box_padding_w    = 16;
		$line_box_padding_h    = 10;
		$line_box_transparency = 60;

	/* Image bkgnd */

		for ($i=0; $i<count($article['lines']); $i++) {
			$line = $article['lines'][$i];

		// Retrieve bounding box:
			$type_space   = imagettfbbox($fontHeight, 0, $fontFamily, $line);

		// Determine image width and height, 10 pixels are added for 5 pixels padding
			$image_width  = abs($type_space[4] - $type_space[0]) + $line_box_padding_w;
			$image_height = abs($type_space[5] - $type_space[1]) + $line_box_padding_h;

		// Create image:
			$image      = imagecreatetruecolor($source_width, $source_height);
			$text_color = imagecolorallocate($image, 255, 255, 255);
			$bg_color   = imagecolorallocate($image, 0, 0, 0);

		// Fill image, then Merge
			imagefill($image, 0, 0, $bg_color);
			imagecopymerge($im, $image, ($x - $line_box_padding_w/2), ($y - ($line_box_padding_h/2) - ($lineHeight * 1.2)), 0, 0, $image_width, $image_height, $line_box_transparency);

			$this->imagettfstroketext($im, $fontHeight, 0, $x, $y, $font_color, $stroke_color, $fontFamily, $line, $text_stroke_thicknoess);

			$y = $y + $image_height;
		}

		$imgType = "";
		switch ($source_type) {
			case IMAGETYPE_GIF:
				ImageGif($im,  $destination_path);
				$imgType = "gif";
				break;
			case IMAGETYPE_JPEG:
				ImageJpeg($im, $destination_path, 100);
				$imgType = "jpeg";
				break;
			case IMAGETYPE_PNG:
				ImagePng($im,  $destination_path);
				$imgType = "png";
				break;
		}

		ImageDestroy ($im);

		return true;
	}

	private function imagettfstroketext(&$image, $size, $angle, $x, $y, &$textcolor, &$strokecolor, $fontfile, $text, $px) {
		for($c1 = ($x-abs($px)); $c1 <= ($x+abs($px)); $c1++)
			for($c2 = ($y-abs($px)); $c2 <= ($y+abs($px)); $c2++)
				$bg = imagettftext($image, $size, $angle, $c1, $c2, $strokecolor, $fontfile, $text);
		return imagettftext($image, $size, $angle, $x, $y, $textcolor, $fontfile, $text);
	}
	
	private function getLinesOfText($text) {

		$limit  = 56; /* This will vary depedning on what Font and Font-size you choose. 56 chars works okay with Impact, 20 */
		$lines  = array();
		$text   = ucfirst($text);

		if (strlen($text) > $limit) {

			$arrayWords = explode(' ', $text);
			$currentLength = 0;
			$index = 0;

			foreach($arrayWords as $word) {

				$wordLength = strlen($word) + 1;

				if( ( $currentLength + $wordLength ) <= $limit ) {
					$arrayOutput[$index] .= $word . ' ';
					$currentLength += $wordLength;
			    } else {
					$index += 1;
					$currentLength = $wordLength;
					$arrayOutput[$index] = $word . ' ';
				}
			}

			$lines = $arrayOutput;
		}

		return $lines;
	}

/* Article Related */

	public function getArticleData($item) {

		$url       = $item['link'];
		$source    = $item['source'];
		$headline  = $item['title'];
		$selectors = $this->selectors[$source];

		$result           = array();
		$result['url']    = $url;
		$result['title']  = $headline;
		$result['source'] = $source;
		$result['card']   = "n";
		$result['image']  = "";
		$result['caption'] = "";
		$result['shortUrl'] = $this->getBitlyUrl($result['url']);

		$html      = $this->getContent($url);
		$pageHTML  = str_get_html($html);

		$contentHTML = $pageHTML->find($selectors[1], 0);
		$image       = $pageHTML->find($selectors[2], 0);

		if (isset($contentHTML) && isset($image)) {

			$captionLimit = 110;
			$plaintext = $this->getParagraphs($contentHTML); //$contentHTML->plaintext;
			$image     = $image->content;

			if (strlen($plaintext)>500 && $image!="") {

				$html   = '';

			/*  Summary */

				$summary = new Teaser();
				$content = $summary->createSummary($plaintext, 'text', $headline);
				$caption = $content['0'];

				print "<p>Caption:</p><pre class='line-preview'>" . $caption . "</pre><hr>";

				if (strlen($caption) > $captionLimit) {
					$caption = substr($caption, 0, strpos($caption, '.', $captionLimit)+1);
				
				//	If still too long then try the 2nd text snippet...
					if (strlen($caption) > $captionLimit && isset($content['1'])  ) {
						$caption = $content['1'];
						if (strlen($caption) > $captionLimit) {
							$caption = substr($caption, 0, strpos($caption, '.', $captionLimit)+1);
						}

					//	And - if still too long then try the 2nd text snippet...
						if (strlen($caption) > $captionLimit && isset($content['2']) ) {
							$caption = $content['2'];
							if (strlen($caption) > $captionLimit) {
								$caption = substr($caption, 0, strpos($caption, '.', $captionLimit)+1);
							}
						}
					}
				}

				$result['headline'] = $this->convertSmartQuotes($headline);
				$result['caption']  = $this->convertSmartQuotes($caption);
				$result['lines']    = $this->getLinesOfText($result['caption']);
				$result['image']    = $image;

			} else {
				print "<p><b>END:</b> Content too short, or no image.</p>";
				print "<textarea>" . $plaintext . "</textarea>";
			}
		} else {
			print "<p><b>END:</b> Unable to get any content for Article.</p>";
		}
	
		return $result;
	}

	private function getParagraphs($content) {

		$text = "";
		foreach($content->find('p') as $p) {
			$text .=  $p->plaintext . "\n\n";
		}

		print "<p>Article text:</p><pre class='short-preview'>" . $text . "</pre><hr>";

		return $text;
	}

/*	Feed Related */

	private function getList() {
		$list = array();

		foreach($this->sources as $source) {
			$items = $this->getFeed($source);
			$list  = array_merge($list, $items);
		}

	//	Sort by Age

		function cmp_publish_date($a, $b) {
		    if ($a['pubd'] == $b['pubd']) { return 0; }
		    return ($a['pubd'] > $b['pubd']) ? -1 : 1;
		}
		usort($list, "cmp_publish_date");

	//	Remove Posts already in History

		$history       = $this->getRecentHistory();
		$historicLinks = $this->getLinksFrom($history);

		$len           = count($list);
		$filtered      = array();

		for ($i=0; $i < count($list); $i++) {
			$link = $list[$i]['link'];
			if (!in_array($link, $historicLinks)) {
				array_push($filtered, $list[$i]);
			}
		}

		print "<hr>";

	//	Done
		return $filtered;
	}

	private function getLinksFrom($list) {
		$links = array();
		foreach($list as $item) {
			$link = $item['url'];
			array_push($links, $link);
		}
		return $links;
	}

	private function getFeed($source) {

		$org = $source[0];
		$url = $source[1];

		$content = file_get_contents($url);
		$x = new SimpleXmlElement($content);

		$json_rss = json_encode($x);
		$rss      = json_decode($json_rss);
		$items    = array();
		$count    = 0;

		print "<p><b>" . $org . "</b>\t&nbsp;";

		foreach($rss->channel->item as $entry) {
			$item = array();
			$item['title']  = (string) $entry->title;
			$item['link']   = $this->getLink($entry);//$entry->link;
			$item['date']   = $this->getTheDate($entry);
			$item['pubd']   = (string) $entry->pubDate;
			$item['tags']   = $this->getCategory($entry);
			$item['source'] = $org;

			if ($item['date'] >= $this->maxAge && $this->isNotOnBlacklist($item['link'])) {
				if ($count < $this->MaxResultPerFeed) {

			        echo "<a href='" . $item['link'] . "' title='" . $item['title'] . "'>&#9651;</a>&nbsp;";
					array_push($items, $item);
				}
				$count++;
			}
	    }
	    echo "</p>";

		return $items;
	}

	private function isNotOnBlacklist($link) {
		return ($this->containsFromArray($link, $this->sourceUrlBlacklist)===false);
	}

	private function getLink($entry) {
		if (isset($entry->guid)) {
			return (string) $entry->guid;
		}
		return (string) $entry->link;
	}

	private function getTheDate($entry) {
		if (isset($entry->pubDate)) {
			return strtotime($entry->pubDate);
		}
		return "";
	}

	private function getCategory($entry) {
		if (isset($entry->category)) {
			return $entry->category;
		}
		return array();
	}

/*	History */

	private function getRecentHistory() {
		$fn = $this->historyFilename;
		$this->lastPostedItem = false;

		// print "<p>Reading history file... <i>" . $this->historyFilename . "</i></p>";
		if (file_exists($fn)) {
			touch($fn);
			$data = file_get_contents($fn);
			$list = json_decode($data, true);
			if (count($list)>0) {
				$this->lastPostedItem = $list[0];
			}
			return $list;
		} else {
			return array();
		}
	}

	private function updateRecentHistory($article) {
		
		if ($this->debug===true) {
			print "<p style='color:orange;'><b>Skipping history update</b></p>";
			return true;
		}

		// print "<p>Updating history file... <i>" . $this->historyFilename . "</i></p>";
		$fn = $this->historyFilename;
		if (file_exists($fn)) {
			touch($fn);
			$data = file_get_contents($fn);
			$list = json_decode($data, true);
			array_unshift($list, $article);
			file_put_contents($fn, json_encode($list));
	    } else {
			$list = array();
			array_push($list, $article);
			file_put_contents($fn, json_encode($list));
	    }
		return true;
	}

/*
	Utility and Helper functions
*/

	private function convertSmartQuotes($string) {

		$chr_map = array(
		   // Windows codepage 1252
		   "\xC2\x82" => "'", // U+0082⇒U+201A single low-9 quotation mark
		   "\xC2\x84" => '"', // U+0084⇒U+201E double low-9 quotation mark
		   "\xC2\x8B" => "'", // U+008B⇒U+2039 single left-pointing angle quotation mark
		   "\xC2\x91" => "'", // U+0091⇒U+2018 left single quotation mark
		   "\xC2\x92" => "'", // U+0092⇒U+2019 right single quotation mark
		   "\xC2\x93" => '"', // U+0093⇒U+201C left double quotation mark
		   "\xC2\x94" => '"', // U+0094⇒U+201D right double quotation mark
		   "\xC2\x9B" => "'", // U+009B⇒U+203A single right-pointing angle quotation mark

		   // Regular Unicode     // U+0022 quotation mark (")
		                          // U+0027 apostrophe     (')
		   "\xC2\xAB"     => '"', // U+00AB left-pointing double angle quotation mark
		   "\xC2\xBB"     => '"', // U+00BB right-pointing double angle quotation mark
		   "\xE2\x80\x98" => "'", // U+2018 left single quotation mark
		   "\xE2\x80\x99" => "'", // U+2019 right single quotation mark
		   "\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
		   "\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
		   "\xE2\x80\x9C" => '"', // U+201C left double quotation mark
		   "\xE2\x80\x9D" => '"', // U+201D right double quotation mark
		   "\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
		   "\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
		   "\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
		   "\xE2\x80\xBA" => "'", // U+203A single right-pointing angle quotation mark
		);
		$chr = array_keys  ($chr_map); // but: for efficiency you should
		$rpl = array_values($chr_map); // pre-calculate these two arrays

		$result = str_replace($chr, $rpl, html_entity_decode($string, ENT_QUOTES, "UTF-8"));

		// print "<p>Converting <i>" . $string . "</i> to <i>" . $result . "</i></p>";

		return $result;
	}

	private function getBitlyUrl($url) {
		$longUrl    = $url . urlencode("?source=simplenewsbot");
		$apiUrl     = 'https://api-ssl.bitly.com/v3/shorten?access_token=' . $this->bitlyToken . '&longUrl=' . $longUrl;

		$result = $this->getContent($apiUrl);
		$json   = json_decode($result);

		return $json->data->url;
	}

	private function dropMic($x) {
		/* this doesn't actually do anything, I'm just immature */
	}

	private function getContent($url) {
		if(!$curld = curl_init($url)) {
			echo "Could not connect to the specified resource";
			exit;
		}

		$ch = curl_init();
		$useragent = "Mozilla/5.0 (Macintosh; Intel Mac OS X x.y; rv:10.0) Gecko/20100101 Firefox/10.0";
		
		curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt ($ch, CURLOPT_USERAGENT, $useragent);
		curl_setopt ($ch, CURLOPT_COOKIEJAR, "tmp/curl_cookie.txt");
		curl_setopt ($ch, CURLOPT_HEADER, 0);
		curl_setopt ($ch, CURLOPT_URL, $url);

		ob_start();
		curl_exec ($ch);
		curl_close ($ch);

		$string = ob_get_contents();
		ob_end_clean();

		return $string;
	}

	private function randomItemFrom($array) {
		if (is_array($array)) {
	    	return $array[ rand(0, count($array)-1) ];
		}
		return array();
	}

	private function startsWith($haystack, $needle){
		return $needle === "" || strpos($haystack, $needle) === 0;
	}

	private function doesContain($haystack, $needle) {
		$pos = strpos($haystack, $needle);
		if ($pos === false) {
			return false;
		}
		return true;
	}

	private function containsFromArray($str, array $arr) {
		foreach($arr as $a) {
			if (stripos($str,$a) !== false) return true;
		}
		return false;
	}
}

