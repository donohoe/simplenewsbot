<html>
<head>
	<title>NewsBot</title>
	<meta charset="UTF-8">
	<style>
		body {
			margin:  20px;
			padding:  0;
			font-family: Arial;
		}
		h1, h2, h3, p, li {
			color: black;
			font-size: 14px;
			font-family: Arial;
		}
		p {
			padding: 0;
			margin:  5px 0;
		}
		ul {
			margin:  0;
			padding: 0;
		}
		textarea {
			height: 90px;
			width:  800px;
		}
		.line-preview,
		.short-preview {
			border: 1px solid #ccc;
			height: 80px;
			width:  80%;
			max-width: 800px;
			padding-left: 5px;
			padding-top:  2px;
			overflow: hidden;
			overflow-y: scroll;
		}
		.line-preview {
			height: 18px;
		}

	</style>
</head>
<body>
	<h1>Hello Bot</h1>
	<p>Lets play a game</p>
	<!--ul>
		<li>A bot may not spam the social network, through inaction, allow a social network to come to harm.</li>
		<li>A bot must obey the orders given to it by social users, except where such orders would conflict with the First Law.</li>
		<li>A bot must protect its own existence as long as such protection does not conflict with the First or Second Law.</li>
	</ul-->
	<hr/>
<?php

require('newsbot.php');

$list = new NewsBot();
$list->gather();

?>
</body>
</html>