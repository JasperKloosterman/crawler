<?php
// header('Content-Type: application/json; charset=utf-8');
setlocale(LC_TIME, "NL_nl");

require __DIR__ . "/vendor/autoload.php";

use Symfony\Component\DomCrawler\Crawler;

echo '
<form action="" name="GatheringDate" method="get">
	<label for="date">Kies een datum</label>
	<input type="date" id="date" name="date">
	<button type="submit" name="sendDate">Zoek</button>
</form>
';

class GatheringBot {
	protected $url = 'https://www.kerktijden.nl/zoeken/plaats/?query=urk';
	protected $logFile = 'log.json';
	protected $log = null;
	protected $date = null;
	protected $newDate= null;
	protected $formatted_date = null; 

	public function __construct() {
		$this->openLog();
		$date = new DateTime();
		$date = DateTime::createFromFormat('Y-m-d', $_GET['date']);
		$this->newDate = $date->format("d-m-Y");
		$this->formatted_date = strftime("%A %d %B", $date->getTimestamp());	
	}

	protected function getContent($url) {
		$con = curl_init(); 

		curl_setopt($con, CURLOPT_URL, $url); 
		curl_setopt($con, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($con, CURLOPT_FOLLOWLOCATION, true);
		$output = curl_exec($con); 

		curl_close($con);  

		return $output;
	}

	public function checkForGatherings()
	{
		$newDate = $this->newDate;
		$url = $this->url . '&dateFrom=' . $newDate;
		$content = $this->getContent($url);

		$crawler = new Crawler();
		$crawler->addContent($content);

		$log = $this->log;

		$links = $crawler->filter('.results > ul a.row')
			->reduce(function (Crawler $node, $i) {
				return $i;
			})
			->each(function (Crawler $node, $i) {
				return $node->attr('href');
			});
	
		if ( sizeof($links) ) {
			$this->parseLinks($links);
		}
	} 	

	public function parseLinks($links)
	{
		$newDate = $this->newDate;
		$formatted_date = $this->formatted_date;

		$diensten = [];
		foreach ($links as $link) {
			$content = $this->getContent($link);

			$crawler = new Crawler();
			$crawler->addContent($content);

			$kerk = [];

			$kerk['locatie'] = $crawler->filter('h1')->text();
			$kerk['diensten'] = $crawler->filter('#gatherings li.day')
				->reduce(function (Crawler $node, $i) use (&$formatted_date) {
					return $node->children()->first()->text() == $formatted_date;
				})
				->each(function (Crawler $node, $i) {
					$diensten = $node->filter('ul > li')->each(function (Crawler $node, $i) {
						return [
							'aanvang' => $node->filter('.time')->text(),
							'dominee' => $node->filter('.preacher > .preacher')->text(),
							'omschrijving' => (
								$node->children()->last()->attr('class') == 'gatheringTypes'
								? $node->filter('.gatheringTypes > .type')->text()
								: ""
							)
						];
					});

					return $diensten;

				});

		}
	}

	protected function openLog() {
		$log = file_get_contents($this->logFile);
		$this->log = ($log ? json_decode($log) : json_decode('{}'));
	}



}

if(isset($_GET['sendDate'])) {
	$gatheringBot = new GatheringBot();
	$gatheringBot->checkForGatherings();
}



