<?php

/**
 * @package     Redeyed.Plugin
 * @subpackage  Captcha.redeyed
 *
 * @copyright   Copyright (C) 2026 Redeyed Corporation. All rights reserved.
 * @license     MIT
 */

namespace Redeyed\Plugin\Captcha\Redeyed\Extension;

use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/**
 * Redeyed Sentinel CAPTCHA plugin.
 *
 * Sentinel is a self-hosted CAPTCHA + IP-reputation service. The plugin is free
 * to install but stays INERT until both a Site Key and a Secret Key are provided.
 * The Secret Key authenticates the server-side verify call, reCAPTCHA/Turnstile
 * style — no developer API key is required. When the Secret Key is missing,
 * verification fails open so it never blocks a site that has not finished
 * configuration.
 */
final class Redeyed extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var  boolean
	 */
	protected $autoloadLanguage = true;

	/**
	 * Whether the block-log logger has been registered this request.
	 *
	 * @var  boolean
	 */
	private static $loggerReady = false;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array<string, string>
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onInit'        => 'onInit',
			'onDisplay'     => 'onDisplay',
			'onCheckAnswer' => 'onCheckAnswer',
		];
	}

	/**
	 * Initialise the CAPTCHA: load the Sentinel widget script.
	 *
	 * @param   \Joomla\Event\Event  $event  The event.
	 *
	 * @return  void
	 */
	public function onInit($event): void
	{
		$siteKey = trim((string) $this->params->get('site_key', ''));

		// Stay inert until a Site Key is configured.
		if ($siteKey === '') {
			return;
		}

		$baseUrl = $this->getBaseUrl();

		$app = $this->getApplication();

		/** @var WebAssetManager $wa */
		$wa = $app->getDocument()->getWebAssetManager();
		$wa->registerAndUseScript(
			'plg_captcha_redeyed.sentinel',
			$baseUrl . '/sentinel.js',
			[],
			['async' => true]
		);
	}

	/**
	 * Output the Sentinel CAPTCHA widget markup.
	 *
	 * @param   \Joomla\Event\Event  $event  The event. Arguments: [name, id, class].
	 *
	 * @return  void  The HTML is set as the event result.
	 */
	public function onDisplay($event): void
	{
		$arguments = method_exists($event, 'getArguments') ? $event->getArguments() : [];

		$class = isset($arguments[2]) ? (string) $arguments[2] : '';

		$siteKey = trim((string) $this->params->get('site_key', ''));

		$classAttr = trim('sentinel-captcha ' . $class);

		$html = '<div class="' . htmlspecialchars($classAttr, ENT_QUOTES, 'UTF-8') . '"'
			. ' data-sitekey="' . htmlspecialchars($siteKey, ENT_QUOTES, 'UTF-8') . '"';

		// Optional widget customisation. Each param maps to a data-* attribute the
		// Sentinel widget script reads, and is rendered only when non-empty so the
		// defaults stay fully adaptive and backward-compatible.
		$dataAttributes = [
			'data-widget'     => trim((string) $this->params->get('widget', '')),
			'data-theme'      => trim((string) $this->params->get('theme', '')),
			'data-scheme'     => trim((string) $this->params->get('scheme', '')),
			'data-difficulty' => trim((string) $this->params->get('difficulty', '')),
			'data-width'      => trim((string) $this->params->get('width', '')),
		];

		foreach ($dataAttributes as $attribute => $value) {
			if ($value === '') {
				continue;
			}

			$html .= ' ' . $attribute . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
		}

		$html .= '></div>';

		$this->setEventResult($event, $html);
	}

	/**
	 * Verify the posted Sentinel token against the Sentinel verify endpoint.
	 *
	 * @param   \Joomla\Event\Event  $event  The event. Argument [0] is the posted code.
	 *
	 * @return  void  A boolean result is set on the event.
	 */
	public function onCheckAnswer($event): void
	{
		$this->setEventResult($event, $this->verify());
	}

	/**
	 * Perform the actual verification.
	 *
	 * @return  boolean  True when the challenge passed, or when the plugin is inert.
	 */
	private function verify(): bool
	{
		$secretKey = trim((string) $this->params->get('secret_key', ''));

		// Fail open while inert: an empty Secret Key means the plugin is not configured.
		if ($secretKey === '') {
			return true;
		}

		$remoteIp = $this->resolveRemoteIp();

		$token = (string) $this->getApplication()->getInput()->post->get(
			'sentinel-token',
			'',
			'string'
		);

		if ($token === '') {
			$this->logBlock($remoteIp, 'missing_token', null);

			return false;
		}

		$baseUrl = $this->getBaseUrl();

		$payload = [
			'secret'   => $secretKey,
			'response' => $token,
		];

		if ($remoteIp !== '') {
			$payload['remoteip'] = $remoteIp;
		}

		try {
			$http = (new HttpFactory())->getHttp();

			$response = $http->post(
				$baseUrl . '/sentinel/siteverify',
				json_encode($payload),
				[
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				]
			);
		} catch (\Throwable $e) {
			$this->logBlock($remoteIp, 'error', null);

			return false;
		}

		$body = json_decode((string) $response->body, true);

		if (!\is_array($body)) {
			$this->logBlock($remoteIp, 'error', null);

			return false;
		}

		$passed  = isset($body['success']) && $body['success'] === true;
		$outcome = isset($body['outcome']) ? (string) $body['outcome'] : '';
		$score   = isset($body['score']) ? (float) $body['score'] : null;

		if (!$passed) {
			$this->logBlock($remoteIp, $outcome, $score);
		}

		return $passed;
	}

	/**
	 * Record a blocked attempt to a Joomla log file when logging is enabled.
	 *
	 * Writes to administrator/logs/plg_captcha_redeyed.log.php via Joomla's
	 * native Log — no custom table. The Secret Key is never logged.
	 *
	 * @param   string      $ip       The visitor's IP.
	 * @param   string      $outcome  The verify outcome.
	 * @param   float|null  $score    The verify score.
	 *
	 * @return  void
	 */
	private function logBlock(string $ip, string $outcome, ?float $score): void
	{
		if (!(bool) $this->params->get('log_blocks', 1)) {
			return;
		}

		try {
			if (!self::$loggerReady) {
				Log::addLogger(
					['text_file' => 'plg_captcha_redeyed.log.php'],
					Log::ALL,
					['plg_captcha_redeyed']
				);
				self::$loggerReady = true;
			}

			Log::add(
				sprintf(
					'Blocked submission from %s (outcome: %s, score: %s)',
					$ip !== '' ? $ip : 'unknown',
					$outcome !== '' ? $outcome : 'n/a',
					$score === null ? 'n/a' : (string) $score
				),
				Log::WARNING,
				'plg_captcha_redeyed'
			);
		} catch (\Throwable $e) {
			// Logging must never break verification.
		}
	}

	/**
	 * Resolve and normalise the configured base URL.
	 *
	 * @return  string
	 */
	private function getBaseUrl(): string
	{
		$baseUrl = trim((string) $this->params->get('base_url', 'https://redeyed.com'));

		if ($baseUrl === '') {
			$baseUrl = 'https://redeyed.com';
		}

		return rtrim($baseUrl, '/');
	}

	/**
	 * Resolve the visitor's IP address in a proxy-aware way.
	 *
	 * Reads, in order of trust, the Cloudflare connecting-IP header, the first
	 * entry of X-Forwarded-For, X-Real-IP, then the raw REMOTE_ADDR, returning
	 * the first value that is a valid IP. This makes the remoteip sent on
	 * verification match the address that actually solved the challenge when the
	 * site sits behind Cloudflare or a reverse proxy, instead of the proxy's own
	 * REMOTE_ADDR.
	 *
	 * @return  string  A valid IP address, or '' when none can be determined.
	 */
	private function resolveRemoteIp(): string
	{
		$server = $this->getApplication()->getInput()->server;

		$candidates = [
			(string) $server->get('HTTP_CF_CONNECTING_IP', '', 'string'),
			(string) $server->get('HTTP_X_FORWARDED_FOR', '', 'string'),
			(string) $server->get('HTTP_X_REAL_IP', '', 'string'),
			(string) $server->get('REMOTE_ADDR', '', 'string'),
		];

		foreach ($candidates as $candidate) {
			if ($candidate === '') {
				continue;
			}

			// X-Forwarded-For may be a comma-separated chain; the first entry is
			// the original client.
			$candidate = trim(explode(',', $candidate)[0]);

			if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Set a result on the event in a version-tolerant way.
	 *
	 * @param   \Joomla\Event\Event  $event  The event.
	 * @param   mixed                $value  The result value.
	 *
	 * @return  void
	 */
	private function setEventResult($event, $value): void
	{
		if (method_exists($event, 'addResult')) {
			$event->addResult($value);

			return;
		}

		if (method_exists($event, 'setArgument')) {
			$result   = $event->getArgument('result', []);
			$result   = \is_array($result) ? $result : [$result];
			$result[] = $value;
			$event->setArgument('result', $result);
		}
	}
}
