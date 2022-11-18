<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

namespace Joomla\Plugin\Console\JEDSample\Command;

use Exception;
use Faker\Factory as FakerFactory;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Plugin\Console\JEDSample\Command\Traits\ConfigureIOTrait;
use Joomla\Plugin\Console\JEDSample\Command\Traits\ErrorReportingTrait;
use Joomla\Plugin\Console\JEDSample\Command\Traits\MemoryInfoTrait;
use Joomla\Plugin\Console\JEDSample\Command\Traits\TimeInfoTrait;
use Joomla\Plugin\Console\JEDSample\Util\Constants;
use Joomla\Plugin\Console\JEDSample\Util\RandomWords;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class CreateUsers extends \Joomla\Console\Command\AbstractCommand
{
	use ConfigureIOTrait;
	use MemoryInfoTrait;
	use TimeInfoTrait;
	use ErrorReportingTrait;
	use DatabaseAwareTrait;

	/**
	 * How many users should I create in each transaction?
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	private const USERS_PER_BATCH = 100;

	/**
	 * Chance (in percentage points) that a developer is a company.
	 *
	 * @since 1.0.0
	 * @var   float
	 */
	private const COMPANY_CHANCE = 40.0;

	/**
	 * Chance (in percentage points) that a developer is deemed suspicious.
	 *
	 * @since 1.0.0
	 * @var   float
	 */
	private const SUSPICIOUS_CHANCE = 0.5;

	/**
	 * The default command name
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected static $defaultName = 'jed4:create:users';

	private RandomWords $randomWords;

	public function __construct(?string $name = null)
	{
		parent::__construct($name);

		$this->randomWords = new RandomWords();
	}


	protected function configure(): void
	{
		$this->setDescription(Text::_('PLG_CONSOLE_JEDSAMPLE_CREATE_USERS_DESC'));
		$this->setHelp(Text::_('PLG_CONSOLE_JEDSAMPLE_CREATE_USERS_HELP'));

		$this->addOption('min-uid', null, InputOption::VALUE_REQUIRED, Text::_('PLG_CONSOLE_JEDSAMPLE_CREATE_USERS_MIN_UID'), 2000);
		$this->addOption('developers', null, InputOption::VALUE_REQUIRED, Text::_('PLG_CONSOLE_JEDSAMPLE_CREATE_USERS_DEVELOPERS'), 3000);
		$this->addOption('regular', null, InputOption::VALUE_REQUIRED, Text::_('PLG_CONSOLE_JEDSAMPLE_CREATE_USERS_REGULAR'), 10000);
		$this->addOption('users-per-batch', null, InputOption::VALUE_REQUIRED, Text::_('PLG_CONSOLE_JEDSAMPLE_CREATE_USERS_USERS_PER_BATCH'), 100);
		$this->addOption('company-chance', null, InputOption::VALUE_REQUIRED, Text::_('PLG_CONSOLE_JEDSAMPLE_CREATE_USERS_COMPANY_CHANCE'), 40);
		$this->addOption('suspicious-chance', null, InputOption::VALUE_REQUIRED, Text::_('PLG_CONSOLE_JEDSAMPLE_CREATE_USERS_SUSPICIOUS_CHANCE'), 0.5);
	}

	/**
	 * @inheritDoc
	 */
	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{
		// region Boilerplate
		$this->configureSymfonyIO($input, $output);
		$this->getDatabase()->setMonitor(null);
		$this->getApplication()->set('mailonline', 0);
		// endregion

		ini_set('display_errors', false);

		try
		{
			$this->ioStyle->title('Deleting old users');

			$minUID           = $input->getOption('min-uid');
			$developers       = (int) $input->getOption('developers');
			$regular          = (int) $input->getOption('regular');
			$batchSize        = (int) $input->getOption('users-per-batch');
			$companyChance    = (float) $input->getOption('company-chance');
			$suspiciousChance = (float) $input->getOption('suspicious-chance');

			$this->deleteUsers(
				minUID: $minUID
			);

			$this->ioStyle->title('Creating new users');

			$this->createUsers(
				minUid   : $minUID,
				count    : $developers + $regular,
				batchSize: $batchSize
			);

			$this->ioStyle->title('Assigning developer names');

			$this->assignDevelopers(
				minUid          : $minUID,
				count           : $developers,
				companyChance   : $companyChance,
				suspiciousChance: $suspiciousChance
			);
		}
		catch (\Exception $e)
		{
			$this->reportError($e);

			return 255;
		}

		$this->ioStyle->success(Text::_('PLG_CONSOLE_JEDSAMPLE_COMMON_DONE'));

		return 0;
	}

	private function assignDevelopers(int $minUid, int $count, float $companyChance, float $suspiciousChance)
	{
		$faker    = FakerFactory::create();
		$devNames = [];

		$db = $this->getDatabase();
		$db->transactionStart();

		$this->ioStyle->progressStart($count);

		for ($id = $minUid; $id <= $minUid + $count; $id++)
		{
			$isCompany    = random_int(0, 10000) <= 100 * $companyChance;
			$isSuspicious = random_int(0, 10000) <= 100 * $suspiciousChance;

			$developerName = null;

			while ($developerName === null)
			{
				if ($isCompany)
				{
					$developerName = $this->randomWords->company();
				}
				else
				{
					$user = Factory::getContainer()
					               ->get(UserFactoryInterface::class)
					               ->loadUserById($id);
					$developerName = $user->name;
				}

				if (!in_array($developerName, $devNames))
				{
					break;
				}
			}

			$devNames[] = $developerName;

			$entry = (object) [
				'user_id' => $id,
				'developer_name' => $developerName,
				'suspicious' => $isSuspicious ? 1 : 0
			];

			$db->insertObject('#__jed_developers', $entry);
			$this->ioStyle->progressAdvance();
		}

		$db->transactionCommit();

		$this->ioStyle->progressFinish();
	}

	private function createUsers(int $minUid, int $count, int $batchSize): void
	{
		$currentId = $minUid;
		$maxId     = $minUid + $count;

		$faker = FakerFactory::create();

		$password     = UserHelper::genRandomPassword(64);
		$passwordHash = UserHelper::hashPassword($password);

		$this->ioStyle->progressStart($count);

		$db = $this->getDatabase();
		$db->transactionStart();

		$retryCount = 0;

		while ($currentId <= $maxId)
		{
			if ($retryCount >= 5)
			{
				throw new RuntimeException(
					'The last 5 attempts to create a user failed.'
				);
			}

			if ($currentId % self::USERS_PER_BATCH === 0)
			{
				$db->transactionCommit();
				$db->transactionStart();
				$this->ioStyle->progressAdvance(self::USERS_PER_BATCH);
			}

			$randomLocale = $faker->randomElement(Constants::LOCALES);
			[, $country] = explode('_', $randomLocale);
			$registerDate  = $faker->dateTimeBetween('-10 years', '-1 week');
			$lastVisitDate = $faker->dateTimeBetween($registerDate, 'now');
			$user          = $this->randomWords->user();

			$userData = [
				'id'            => $currentId,
				'name'          => $user->name,
				'username'      => $user->username,
				'email'         => $user->email,
				'password'      => $passwordHash,
				'groups'        => [2],
				'block'         => 0,
				'sendEmail'     => 0,
				'registerDate'  => (new Date('@' . $registerDate->getTimestamp()))->toSql(),
				'lastvisitDate' => (new Date('@' . $lastVisitDate->getTimestamp()))->toSql(),
				'activation'    => null,
				'params'        => json_encode([
					'language' => $randomLocale,
					'timezone' => $faker->randomElement(Constants::TIMEZONES_PER_COUNTRY[$country]),
				]),
				'lastResetTime' => null,
				'resetCount'    => 0,
				'otpKey'        => null,
				'otep'          => null,
				'requireReset'  => 0,
			];

			try
			{
				// Create user
				$object = (object) $userData;

				$db->insertObject('#__users', $object, 'id');// Create user group mapping
			}
			catch (Exception $e)
			{
				// The insert might have failed if we have a duplicate email or username. No worries! Skip over.

				$retryCount++;

				continue;
			}

			try
			{
				$object = (object) [
					'user_id'  => $currentId,
					'group_id' => 2,
				];
				$db->insertObject('#__user_usergroup_map', $object, 'user_id');
			}
			catch (Exception $e)
			{
				// Maybe we already had a user to user group map. Whatever.
			}

			$retryCount = 0;
			$currentId++;
		}

		try
		{
			$db->transactionCommit();
		}
		catch (Throwable $e)
		{
			// No worries. We might have just done so already.
		}

		$this->ioStyle->progressFinish();
	}

	private function deleteUsers(int $minUID): void
	{
		$db = $this->getDatabase();
		$db->transactionStart();

		$query = $db->getQuery(true)
		            ->delete($db->quoteName('#__users'))
		            ->where($db->quoteName('id') . ' >= :minuid')
		            ->bind(':minuid', $minUID, ParameterType::INTEGER);
		$db->setQuery($query)->execute();

		$query = $db->getQuery(true)
		            ->delete($db->quoteName('#__user_usergroup_map'))
		            ->where($db->quoteName('user_id') . ' >= :minuid')
		            ->bind(':minuid', $minUID, ParameterType::INTEGER);
		$db->setQuery($query)->execute();

		$query = $db->getQuery(true)
		            ->delete($db->quoteName('#__user_profiles'))
		            ->where($db->quoteName('user_id') . ' >= :minuid')
		            ->bind(':minuid', $minUID, ParameterType::INTEGER);
		$db->setQuery($query)->execute();

		$db->transactionCommit();

		$db->truncateTable('#__jed_developers');
	}

}