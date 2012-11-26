<?php
namespace sitemap;

use \lib\cache\CacheInterface;

/**
 * Crawler
 * Crawls all internal links starting from set url
 * Links can be prioritized and disabled for deeper crawling
 *
 * @package Moss Sitemap
 * @author  Michal Wachowski <wachowski.michal@gmail.com>
 */
class Crawler {
	/** @var null|CacheInterface */
	protected $Cache;
	protected $cacheName;

	protected $delay = 5;

	protected $url = null;
	protected $regExpUrl = null;

	protected $disabledDirs = array();
	protected $regExpDisabled = null;

	protected $primaryDirs = array();
	protected $regExpPrimary = null;

	protected $normalDirs = array();
	protected $regExpNormal = null;

	protected $secondaryDirs = array();
	protected $regExpSecondary = null;

	protected $additional = array();

	protected $limit = 50;
	protected $queryUrl = false;

	protected $urlQueue = array();
	protected $urlList = array();

	/**
	 * Creates sitemap crawler instance
	 *
	 * @param string      $url
	 * @param int         $limit
	 * @param null|string $user
	 * @param null|string $pass
	 */
	public function __construct($url, $limit = 0, $user = null, $pass = null) {
		$this->setUrl($url);

		$this->user = (string) $user;
		$this->pass = (string) $pass;

		$this->limit = (int) $limit;
	}

	/**
	 * Sets cache mechanism to crawler
	 * If cache is present - crawler will restart itself when timeout is near
	 *
	 * @param CacheInterface $Cache
	 * @param string $cacheName
	 *
	 * @return Crawler
	 */
	public function setCache(CacheInterface $Cache, $cacheName = 'SitemapCache') {
		$this->Cache = $Cache;
		$this->cacheName = $cacheName;

		return $this;
	}

	/**
	 * Sets delay used for refreshing
	 * Script will wait set seconds before it restarts itself
	 *
	 * @param int $delay
	 */
	public function setDelay($delay) {
		$this->delay = (int) $delay;
	}

	/**
	 * Set starting address
	 *
	 * @param string $url
	 *
	 * @return Crawler
	 */
	public function setUrl($url) {
		if(substr($url, -1) !== '/') {
			$url .= '/';
		}

		$this->url = $url;
		return $this;
	}

	/**
	 * Sets array of disabled paths
	 *
	 * @param array $dirs
	 *
	 * @return Crawler
	 */
	public function setDisabled($dirs = array()) {
		$this->disabledDirs = (array) $dirs;
		return $this;
	}

	/**
	 * Retrieves array of disabled paths
	 *
	 * @return array
	 */
	public function getDisabled() {
		return $this->disabledDirs;
	}

	/**
	 * Sets array of primary paths
	 * Primary paths have priority 1
	 *
	 * @param array $dirs
	 *
	 * @return Crawler
	 */
	public function setPrimary($dirs = array()) {
		$this->primaryDirs = (array) $dirs;
		return $this;
	}

	/**
	 * Retrieves array of primary paths
	 *
	 * @return array
	 */
	public function getPrimary() {
		return $this->primaryDirs;
	}

	/**
	 * Sets array of normal paths
	 * Normal paths have priority equal or greater than 0.5
	 *
	 * @param array $dirs
	 *
	 * @return Crawler
	 */
	public function setNormal($dirs = array()) {
		$this->normalDirs = (array) $dirs;
		return $this;
	}

	/**
	 * Retrieves array of normal paths
	 *
	 * @return array
	 */
	public function getNormal() {
		return $this->normalDirs;
	}

	/**
	 * Sets array of secondary paths
	 * Secondary paths have priority 0
	 *
	 * @param array $dirs
	 *
	 * @return Crawler
	 */
	public function setSecondary($dirs = array()) {
		$this->secondaryDirs = (array) $dirs;
		return $this;
	}

	/**
	 * Retrieves array of secondary paths
	 *
	 * @return array
	 */
	public function getSecondary() {
		return $this->secondaryDirs;
	}

	/**
	 * Sets additional urls (not found on page)
	 * Additional urls are added into site
	 * Each additional url is an array containing tree fields:
	 *  url - address,
	 *  incoming - number of incoming links
	 *  outgoing - number of outgoing links
	 *
	 * @param array $urls
	 *
	 * @return Crawler
	 */
	public function setAdditional($urls = array()) {
		foreach($urls as $url => $priority) {
			if(!$this->valid($url)) {
				continue;
			}

			$this->additional[$url] = array(
				'changeFreq' => $this->urlChangeFreq($priority),
				'priority' => $priority
			);
		}

		return $this;
	}

	/**
	 * Returns additional urls
	 *
	 * @return array
	 */
	public function getAdditional() {
		return $this->additional;
	}

	/**
	 * Sets query urls flag
	 * If true, addresses with ? are skipped
	 *
	 * @param $normal
	 *
	 * @return Crawler
	 */
	public function setQuery($normal) {
		$this->queryUrl = (bool) $normal;

		return $this;
	}

	/**
	 * Retrieves query urls flag
	 *
	 * @return bool
	 */
	public function getQuery() {
		return $this->queryUrl;
	}

	/**
	 * Starts crawling
	 *
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	public function build() {
		if(empty($this->url)) {
			throw new \InvalidArgumentException('Url not set');
		}

		$this->urlList = array();
		$this->regExpUrl = preg_quote(preg_replace('#^www\.(.*)$#i', '$1', str_replace(array('http://', 'https://'), null, $this->url)));

		if(!empty($this->disabledDirs) && is_array($this->disabledDirs)) {
			$this->regExpDisabled = $this->buildRegexpUrl($this->disabledDirs);
		}

		if(!empty($this->primaryDirs) && is_array($this->primaryDirs)) {
			$this->regExpPrimary = $this->buildRegexpUrl($this->primaryDirs);
		}

		if(!empty($this->normalDirs) && is_array($this->normalDirs)) {
			$this->regExpNormal = $this->buildRegexpUrl($this->normalDirs);
		}

		if(!empty($this->secondaryDirs) && is_array($this->secondaryDirs)) {
			$this->regExpSecondary = $this->buildRegexpUrl($this->secondaryDirs);
		}

		$this->urlQueue[] = $this->url;
		$this->urlList[$this->url] = array('incoming' => 0, 'outgoing' => 0);
		$counter = 0;

		if($this->Cache && ($cached = $this->Cache->fetch($this->cacheName)) && isset($cached['queue'], $cached['list'], $cached['counter'])) {
			$this->urlQueue = $cached['queue'];
			$this->urlList = $cached['list'];
			$counter = $cached['counter'];
		}

		$timeout = strtotime('+' . (ini_get('max_execution_time') - 5) . 'seconds');
		while(!empty($this->urlQueue)) {
			$counter++;

			$url = $this->readUrl();

			$this->message($counter, $url);

			if($this->limit && $counter > $this->limit) {
				break;
			}

			if($timeout < time()) {
				$this->Cache->store($this->cacheName, array('queue' => $this->urlQueue, 'list' => $this->urlList, 'counter' => $counter));
				$this->message('--- Timeout - will restart at node ' . ($counter + 1) . ' in ' . $this->delay . " seconds --- \n\n");
				die('<script type="text/javascript" language="javascript">setTimeout("window.location.href = \'' . $_SERVER['REQUEST_URI'] . '\'", ' . ($this->delay * 1000). ');</script>');
			}
		}

		$this->Cache->delete($this->cacheName);

		$this->buildCFP();

		foreach($this->additional as $url => $data) {
			if(isset($this->urlList[$url])) {
				continue;
			}

			$this->urlList[$url] = $data;
		}

		$this->message(sprintf('Crawl completed - %d nodes visited, %d nodes found', $counter, count($this->urlList)), null);

		return $this->urlList;
	}

	/**
	 * Builds regular expression for directories
	 *
	 * @param array $dirs
	 *
	 * @return null|string
	 */
	protected function buildRegexpUrl($dirs) {
		if(empty($dirs)) {
			return null;
		}

		$regExp = '(';
		foreach($dirs as $dir) {
			if(empty($dir)) {
				continue;
			}

			if(strpos($dir, './') === 0) {
				$dir = substr($dir, 2);
			}

			if(strpos($dir, '/') === 0) {
				$dir = substr($dir, 1);
			}

			$regExp .= '' . preg_quote($dir) . '|';
		}

		$regExp[strlen($regExp) - 1] = ')';

		return $regExp;
	}


	/**
	 * Builds change frequency and priority for address
	 */
	protected function buildCFP() {
		foreach($this->urlList as $url => &$node) {
			if(!$node['outgoing']) {
				$node['outgoing'] = 1;
			}

			$priority = $this->getPriority($url, $node['incoming'], $node['outgoing']);

			$node = array(
				'changeFreq' => $this->urlChangeFreq($priority),
				'priority' => $priority
			);

			unset($node);
		}

		uasort($this->urlList, function($a, $b) {
			return $b['priority'] * 10 - $a['priority'] * 10;
		});
	}

	/**
	 * Calculates address priority based on incoming and outgoing links
	 *
	 * @param string $url
	 * @param int $incoming
	 * @param int $outgoing
	 *
	 * @return float
	 */
	protected function getPriority($url, $incoming, $outgoing) {
		if($this->url == $url || $this->regExpPrimary && preg_match('#' . $this->regExpUrl . $this->regExpPrimary . '#i', $url)) {
			return 1;
		}

		if($this->regExpSecondary && preg_match('#' . $this->regExpUrl . $this->regExpSecondary . '#i', $url)) {
			return 0;
		}

		$priority = log10($incoming / $outgoing);

		if($this->regExpNormal && $priority < 0.5 && preg_match('#' . $this->regExpUrl . $this->regExpNormal . '#i', $url)) {
			return 0.5;
		}

		if($priority > 1) {
			$priority = 1;
		}

		if($priority < 0) {
			$priority = 0;
		}


		return round($priority, 1);
	}

	/**
	 * Translates change frequency into word
	 *
	 * @param float $priority
	 *
	 * @return string
	 */
	protected function urlChangeFreq($priority) {
		if($priority == 1) {
			return 'always';
		}

		if($priority >= 0.8) {
			return 'hourly';
		}

		if($priority >= 0.6) {
			return 'daily';
		}

		if($priority >= 0.5) {
			return 'weekly';
		}

		if($priority >= 0.3) {
			return 'monthly';
		}

		if($priority > 0) {
			return 'yearly';
		}

		return 'never';
	}

	/**
	 * Sends message to stdOutput
	 *
	 * @param $string
	 */
	protected function message($string) {
		$args = func_get_args();
		echo implode("\t", $args) . "\n";
		flush();
	}

	/**
	 * Reads next address from stack
	 *
	 * @return mixed|null|string
	 */
	protected function readUrl() {
		$url = array_shift($this->urlQueue);

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_NOBODY, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');

		if($this->user && $this->pass) {
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($curl, CURLOPT_USERPWD, $this->user . ':' . $this->pass);
		}

		$data = curl_exec($curl);
		curl_close($curl);

		if(!preg_match('/Content-Type: .*\/.*html/', $data)) {
			return null;
		}

		preg_match_all('#<\s*(?:a|frame|iframe|form)[^>]*\s+(?:href|src|URL|action)\s*=\s*["\']?(?!mailto:|news:|javascript:|ftp:|telnet:|callto:|ed2k:)([^"\'\#\s>]+)#is', $data, $urlList);

		foreach($urlList[1] as $foundUrl) {
			$url = html_entity_decode(urldecode($url));

			if(strpos($foundUrl, './') !== 0 && strpos($foundUrl, '/') !== 0 && strpos($foundUrl, 'http://') !== 0) {
				$foundUrl = $this->url . $foundUrl;
			}

			if(empty($foundUrl) || !$this->valid($foundUrl)) {
				continue;
			}

			if(isset($this->urlList[$url])) {
				$this->urlList[$url]['outgoing'] += 1;
			}

			if(isset($this->urlList[$foundUrl])) {
				$this->urlList[$foundUrl]['incoming'] += 1;
			}
			else {
				$this->urlList[$foundUrl] = array('incoming' => 1, 'outgoing' => 0);
				$this->urlQueue[] = $foundUrl;
			}
		}

		return $url;
	}

	/**
	 * Checks if address is valid
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	protected function valid($url) {
		if(empty($url)) {
			return false;
		}

		if(preg_match('#\.(ico|png|jpg|gif|css|js)(\?.*)?$#i', $url)) {
			return false;
		}

		if($this->regExpDisabled && preg_match('#' . $this->regExpUrl . $this->regExpDisabled . '#i', $url)) {
			return false;
		}

		if(!$this->queryUrl && preg_match('#' . $this->regExpUrl . '.*\?.*#i', $url)) {
			return false;
		}

		if(preg_match('#.*https?://.*https?://.*#', $url)) {
			return false;
		}

		if(preg_match('#^https?://(www\.)?([^/]+\.)*' . $this->regExpUrl . '[^\#]*$#i', $url)) {
			return true;
		}

		return false;
	}
}