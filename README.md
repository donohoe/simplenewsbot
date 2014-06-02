simplenewsbot
=============

Twitter Bot. News in headlines, and inline with images. Formatted to fit your app. Won't flood your stream.

Getting Started
===============

Twitter
-------

* Create a Twitter account at http://twitter.com
* Go to http://dev.twitter.com and Sign In
* Choose "My Applications" from the menu
* Click "Create New Application" and follow various steps
  * Make sure app has "Read and Write" permissions
* Note the following:
  * API key
  * API Secret
  * Access token
  * Access token secret

Bitly
-----
* Go to https://bitly.com/a/oauth_apps and "Generate an Access Token"

This is optional but you'll need to modify the code to not use Bitly if you want to use original URL in the Bot's messages.

Code
----
Modify using the above with the below:

	$this->twitter_handle     = "simplenewsbot"; /* You may not want to use MY account */
	$this->consumer_key       = 'THE VARIABLE NAME EXPLAINS WHAT GOES HERE';
	$this->consumer_secret    = 'THE VARIABLE NAME EXPLAINS WHAT GOES HERE';
	$this->oauth_token        = 'THE VARIABLE NAME EXPLAINS WHAT GOES HERE';
	$this->oauth_token_secret = 'THE VARIABLE NAME EXPLAINS WHAT GOES HERE';
	$this->bitlyToken         = 'THE VARIABLE NAME EXPLAINS WHAT GOES HERE';

Feeds, Selectors, Scraping
----------
This is important and might seem confusing if you look at the code. This is also the bit I forgot to provide documentation for. I'll do it later. Soon. Won't forget. Promise.

Destroy, Crush, Kill
---------
Use your bot responsibly