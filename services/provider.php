<?php

/**
 * @package     Redeyed.Plugin
 * @subpackage  Captcha.redeyed
 *
 * @copyright   Copyright (C) 2026 Redeyed Corporation. All rights reserved.
 * @license     MIT
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Redeyed\Plugin\Captcha\Redeyed\Extension\Redeyed;

return new class () implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 */
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$config  = (array) PluginHelper::getPlugin('captcha', 'redeyed');
				$subject = $container->get(DispatcherInterface::class);

				$plugin = new Redeyed($subject, $config);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
