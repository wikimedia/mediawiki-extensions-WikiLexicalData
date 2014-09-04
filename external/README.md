# Installing External APIs

This directory will be used to add external APIs.

## APIs that are supported by WikiLexicalData

### Wordnik

Currently tested with Wordnik.com v4 API.

#### Basic Setup

Place the `wordnik` folder that you downloaded in this directory
where it can be accessed by WikiLexicaldata.

Obtain an API key from Wordnik.

Then create a PHP file under this directory named wordnikConfig.php and 
with the following code:

	<?php
	global $myWordnikAPIKey;
	$myWordnikAPIKey = '<PLACE_YOUR_WORDNIK_API_KEY_HERE>';

Replace <PLACE_YOUR_WORDNIK_API_KEY_HERE> with the API key you have received
from Wordnik.
