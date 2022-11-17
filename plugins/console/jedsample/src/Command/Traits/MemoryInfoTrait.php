<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

namespace Joomla\Plugin\Console\JEDSample\Command\Traits;

defined('_JEXEC') || die;

/**
 * Utility methods to get memory information
 *
 * @since  1.0.0
 */
trait MemoryInfoTrait
{
	/**
	 * Returns the current memory usage
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function memUsage(): string
	{
		if (function_exists('memory_get_usage'))
		{
			$size = memory_get_usage();
			$unit = ['b', 'KB', 'MB', 'GB', 'TB', 'PB'];

			return @round($size / 1024 ** ($i = floor(log($size, 1024))), 2) . ' ' . $unit[$i];
		}
		else
		{
			return "(unknown)";
		}
	}

	/**
	 * Returns the peak memory usage
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function peakMemUsage(): string
	{
		if (function_exists('memory_get_peak_usage'))
		{
			$size = memory_get_peak_usage();
			$unit = ['b', 'KB', 'MB', 'GB', 'TB', 'PB'];

			return @round($size / 1024 ** ($i = floor(log($size, 1024))), 2) . ' ' . $unit[$i];
		}
		else
		{
			return "(unknown)";
		}
	}
}
