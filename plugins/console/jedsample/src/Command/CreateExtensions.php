<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

namespace Joomla\Plugin\Console\JEDSample\Command;

use DateInterval;
use DateTime;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Plugin\Console\JEDSample\Command\Traits\ConfigureIOTrait;
use Joomla\Plugin\Console\JEDSample\Command\Traits\ErrorReportingTrait;
use Joomla\Plugin\Console\JEDSample\Command\Traits\MemoryInfoTrait;
use Joomla\Plugin\Console\JEDSample\Command\Traits\TimeInfoTrait;
use Joomla\Plugin\Console\JEDSample\Util\Bias;
use Joomla\Plugin\Console\JEDSample\Util\RandomImage;
use Joomla\Plugin\Console\JEDSample\Util\RandomMedia;
use Joomla\Plugin\Console\JEDSample\Util\RandomWords;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateExtensions extends \Joomla\Console\Command\AbstractCommand
{
	use ConfigureIOTrait;
	use MemoryInfoTrait;
	use TimeInfoTrait;
	use ErrorReportingTrait;
	use DatabaseAwareTrait;

	/**
	 * The default command name
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected static $defaultName = 'jed4:create:extensions';

	private array $categories;

	private array $createdTitles = [];

	private Generator $faker;

	private RandomImage $randomImage;

	private RandomMedia $randomMedia;

	private RandomWords $randomWords;

	public function __construct(?string $name = null)
	{
		parent::__construct($name);

		$this->randomWords = new RandomWords();
		$this->randomImage = new RandomImage();
		$this->randomMedia = new RandomMedia($this->randomWords);
		$this->faker       = FakerFactory::create();
	}

	protected function configure(): void
	{
		$this->setDescription(Text::_('PLG_CONSOLE_JEDSAMPLE_CREATE_EXTENSIONS_DESC'));
		$this->setHelp(Text::_('PLG_CONSOLE_JEDSAMPLE_CREATE_EXTENSIONS_HELP'));

		$this->addOption('per-developer', null, InputOption::VALUE_REQUIRED, Text::_('PLG_CONSOLE_JEDSAMPLE_CREATE_extensions_per_developer'), 5);
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

		$maxExtensionsPerDeveloper = (int) $input->getOption('per-developer');

		try
		{
			// Load categories
			$this->loadCategories();

			// Delete existing extensions
			$this->deleteExtensions();

			// Create extensions
			$this->createExtensions($maxExtensionsPerDeveloper);

			// Create varied data for each extension
			$this->createVariedDataForExtensions();

			// Create extension images
			$this->createExtensionImages();

			// Create extension reviews and review comments
			$this->createReviews();

			// Create extension scores (averages)
			$this->createScores();
		}
		catch (\Exception $e)
		{
			$this->reportError($e);

			return 255;
		}

		$this->ioStyle->success(Text::_('PLG_CONSOLE_JEDSAMPLE_COMMON_DONE'));

		return 0;
	}

	private function createExtension(object $developer): int
	{
		// Joomla versions supported
		$jVersions = $this->getChanceWeighedArrayElements(
			[
				'30' => 30,
				'40' => 75,
				'51' => 5,
			],
			'40'
		);

		$created = $this->randomWords->date($developer->registerDate, '-1 week');
		[$title, $alias] = $this->getRandomExtensionTitle();
		$approved = $this->randomWords->bool(98);

		if ($approved)
		{
			$approvedDate = $this->randomWords->date($created, '1 week');
		}

		$fileName = md5(microtime() . serialize($developer)) . '.png';
		$this->randomImage->generate();
		$this->randomImage->draw();
		$this->randomImage->saveImage(JPATH_ROOT . '/images/jed_logos', $fileName);

		$includesExtTypes = $this->getChanceWeighedArrayElements(
			['com' => 70, 'mod' => 50, 'plugin' => 20],
			'com'
		);
		$record           = (object) [
			'title'                 => $title,
			'alias'                 => $alias,
			'joomla_versions'       => implode(',', $jVersions),
			'popular'               => $this->randomWords->bool(10) ? 1 : 0,
			'requires_registration' => $this->randomWords->bool(15) ? 1 : 0,
			'gpl_license_type'      => $this->getRandomLicense(),
			'jed_internal_note'     => '',
			'can_update'            => $this->randomWords->bool(95) ? 1 : 0,
			'video'                 => $this->randomMedia->video(),
			'version'               => $this->randomWords->version(),
			'uses_updater'          => $this->randomWords->bool(95) ? 1 : 0,
			'includes'              => implode(',', $includesExtTypes),
			'approved'              => $approved ? 1 : 0,
			'approved_time'         => $approved ? $approvedDate->toSql() : null,
			'second_contact_email'  => $this->faker->email,
			'jed_checked'           => $this->randomWords->bool(95) ? 1 : 0,
			'uses_third_party'      => $this->randomWords->bool(5) ? 1 : 0,
			'primary_category_id'   => $this->randomWords->randomElement($this->categories),
			'logo'                  => '/images/jed_logos/' . $fileName,

			'approved_notes'   => 'All good',
			'approved_reason'  => 'All good',
			'published_notes'  => '',
			'published_reason' => '',
			'published'        => 1,
			'checked_out'      => null,
			'created_by'       => $developer->id,
			'modified_by'      => $developer->id,
			'checked_out_time' => null,
			'created_on'       => $created->toSql(),
			'modified_on'      => $created->toSql(),
			'state'            => 1,
		];

		$db = $this->getDatabase();
		$db->insertObject('#__jed_extensions', $record);

		return $db->insertid();
	}

	private function createExtensionImages(): void
	{
		$this->ioStyle->title('Create extension images');

		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
		            ->select([
			            $db->quoteName('id'),
			            $db->quoteName('extension_id'),
			            $db->quoteName('supply_option_id'),
			            $db->quoteName('created_by'),
		            ])
		            ->from($db->quoteName('#__jed_extension_varied_data'));

		$variedData = $db->setQuery($query)->loadObjectList();

		$this->ioStyle->progressStart(count($variedData));

		$db->transactionStart();

		foreach ($variedData as $variedDatum)
		{
			$numImages = random_int(1, 7);
			$photos    = $this->randomMedia->photos($numImages);

			for ($order = 0; $order < $numImages; $order++)
			{
				$data = (object) [
					'extension_id'     => $variedDatum->extension_id,
					'supply_option_id' => $variedDatum->supply_option_id,
					'filename'         => $photos[$order],
					'state'            => 1,
					'ordering'         => $order,
					'checked_out'      => null,
					'checked_out_time' => null,
					'created_by'       => $variedDatum->created_by,
					'modified_by'      => $variedDatum->created_by,
				];

				$db->insertObject('#__jed_extension_images', $data);
			}

			$this->ioStyle->progressAdvance();
		}

		$db->transactionCommit();

		$this->ioStyle->progressFinish();
	}

	private function createExtensions(int $maxExtensionsPerDeveloper): void
	{
		$this->ioStyle->title('Creating extensions');

		$developers = $this->getDevelopers();

		$this->ioStyle->progressStart(count($developers));

		$this->getDatabase()->transactionStart();

		/** @var object $developer */
		foreach ($developers as $developer)
		{
			$numExtensions = $this->randomWords->int(1, $maxExtensionsPerDeveloper, Bias::LINEAR_LOW);

			for ($i = 0; $i < $numExtensions; $i++)
			{
				$this->createExtension($developer);
			}

			$this->ioStyle->progressAdvance();
		}

		$this->ioStyle->progressFinish();

		$this->getDatabase()->transactionCommit();
	}

	private function createReviews(): void
	{
		$this->ioStyle->title('Creating extension reviews');

		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
		            ->select([
			            $db->quoteName('d.extension_id'),
			            $db->quoteName('d.supply_option_id'),
			            $db->quoteName('e.version'),
			            $db->quoteName('e.created_on'),
			            $db->quoteName('e.created_by'),
		            ])
		            ->from($db->quoteName('#__jed_extension_varied_data', 'd'))
		            ->innerJoin(
			            $db->quoteName('#__jed_extensions', 'e'),
			            $db->quoteName('e.id') . ' = ' . $db->quoteName('d.extension_id')
		            );

		$variedData = $db->setQuery($query)->loadObjectList();

		$regularUsers = $this->getNonDevelopers();

		$this->ioStyle->progressStart(count($variedData));

		$db->setQuery('SET FOREIGN_KEY_CHECKS = 0')->execute();

		$db->transactionStart();

		/** @var object $variedDatum */
		foreach ($variedData as $variedDatum)
		{
			/**
			 * Number of reviews
			 *
			 * 80% of the extensions get 0–10 reviews, flat chance
			 * 20% of the extensions get 5–100 reviews, chances are towards the lower end
			 */
			$numReviews     = $this->randomWords->bool(80)
				? random_int(0, 10)
				: $this->randomWords->int(5, 100, Bias::EXP_LOW);
			$commentedUsers = [];

			for ($i = 0; $i < $numReviews; $i++)
			{
				do
				{
					$user = $this->randomWords->randomElement($regularUsers);
				} while (in_array($user, $commentedUsers));

				$commentTitle = $this->faker->sentence();

				$functionality   = $this->randomWords->int(0, 100, Bias::EXP_HIGH);
				$ease_of_use     = $this->randomWords->int(0, 100, Bias::EXP_HIGH);
				$support         = $this->randomWords->int(0, 100, Bias::EXP_HIGH);
				$documentation   = $this->randomWords->int(0, 100, Bias::EXP_HIGH);
				$value_for_money = $this->randomWords->int(0, 100, Bias::EXP_HIGH);
				$overall         = ($functionality + $ease_of_use + $support + $documentation + $value_for_money) / 5;

				// id, registerDate
				$extCreated  = new Date($variedDatum->created_on);
				$userCreated = new Date($user->registerDate);
				$minDate     = $extCreated < $userCreated ? $userCreated : $extCreated;

				$created_on = $this->randomWords->date($minDate->toISO8601());

				$review = (object) [
					'extension_id'            => $variedDatum->extension_id,
					'supply_option_id'        => $variedDatum->supply_option_id,
					'title'                   => $commentTitle,
					'alias'                   => ApplicationHelper::stringURLSafe($commentTitle),
					'body'                    => $this->faker->paragraph(),
					'functionality'           => $functionality,
					'functionality_comment'   => $this->faker->paragraph(2),
					'ease_of_use'             => $ease_of_use,
					'ease_of_use_comment'     => $this->faker->paragraph(2),
					'support'                 => $support,
					'support_comment'         => $this->faker->paragraph(2),
					'documentation'           => $documentation,
					'documentation_comment'   => $this->faker->paragraph(2),
					'value_for_money'         => $value_for_money,
					'value_for_money_comment' => $this->faker->paragraph(2),
					'overall_score'           => $overall,
					'used_for'                => $this->faker->text(350),
					'version'                 => $this->randomWords->version($variedDatum->version),
					'flagged'                 => $this->randomWords->bool(0.1),
					'ip_address'              => $this->faker->ipv4,
					'published'               => 1,
					'created_on'              => $created_on->toSql(),
					'created_by'              => $user->id,
					'ordering'                => $i,
					'checked_out'             => null,
					'checked_out_time'        => null,
				];

				$db->insertObject('#__jed_reviews', $review);
				$reviewId = $db->insertid();

				// Create review comment (3% chance)
				if (!$this->randomWords->bool(3))
				{
					continue;
				}

				$earliestCommentDate = $this->randomWords->date($created_on->toISO8601(), (clone $created_on)->add(new DateInterval('PT2H')));
				$latestCommentDate   = (clone $earliestCommentDate)->add(new DateInterval('P14D'));
				$now                 = new Date();
				$latestCommentDate   = $latestCommentDate > $now ? $now : $latestCommentDate;

				$commentCreated = $this->randomWords->date($earliestCommentDate->toISO8601(), $latestCommentDate->toISO8601());

				$reviewComment = (object) [
					'review_id'  => $reviewId,
					'comments'   => $this->faker->paragraphs(2),
					'ip_address' => $this->faker->ipv4,
					'created_on' => $commentCreated->toSql(),
					'created_by' => $variedDatum->created_by,
					'ordering'   => 0,
					'state'      => 1,
				];

				$db->insertObject('#__jed_reviews_comments', $reviewComment);
			}

			$this->ioStyle->progressAdvance();
		}

		$db->transactionCommit();

		$db->setQuery('SET FOREIGN_KEY_CHECKS = 1')->execute();

		$this->ioStyle->progressFinish();
	}

	private function createScores()
	{
		$this->ioStyle->title('Creating extension scores');

		$db = $this->getDatabase();
		$sql = <<< SQL
REPLACE INTO `#__jed_extension_scores`
SELECT
    NULL as `id`,
    `extension_id`,
    `supply_option_id`,
    AVG(`functionality`) as `functionality_score`,
    AVG(`ease_of_use`) as `ease_of_use_score`,
    AVG(`support`) as `support_score`,
    AVG(`value_for_money`) as `value_for_money_score`,
    AVG(`documentation`) as `documentation_score`,
    COUNT(*) as `number_of_reviews`,
    1 as `state`,
    0 as `ordering`,
    NULL as `checked_out`,
    NULL as `checked_out_time`,
    NULL as `created_by`,
    NULL as `modified_by`
FROM
    `#__jed_reviews`
WHERE
    `published` = 1
GROUP BY `extension_id`, `supply_option_id`;

SQL;
		$db->setQuery($sql)->execute();

		$sql = <<< SQL
INSERT INTO `#__jed_extension_scores`
SELECT
    NULL as `id`,
    `extension_id`,
    `supply_option_id`,
    NULL as `functionality_score`,
    NULL as `ease_of_use_score`,
    NULL as `support_score`,
    NULL as `value_for_money_score`,
    NULL as `documentation_score`,
    0 as `number_of_reviews`,
    1 as `state`,
    0 as `ordering`,
    NULL as `checked_out`,
    NULL as `checked_out_time`,
    NULL as `created_by`,
    NULL as `modified_by`
FROM
    `#__jed_extension_varied_data` `d`
WHERE
    NOT EXISTS (
        SELECT 1 FROM `#__jed_extension_scores` s WHERE `s`.`extension_id` = `d`.`extension_id` AND `s`.`supply_option_id` = `d`.`supply_option_id`
        )
SQL;

		$db->setQuery($sql)->execute();
	}

	private function createVariedDataForExtensions(): void
	{
		$this->ioStyle->title('Creating varied data for extensions');

		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
		            ->select([
			            $db->quoteName('id'),
			            $db->quoteName('requires_registration'),
			            $db->quoteName('version'),
			            $db->quoteName('uses_updater'),
			            $db->quoteName('logo'),
			            $db->quoteName('published'),
			            $db->quoteName('created_by'),
			            $db->quoteName('created_on'),
		            ])
		            ->from($db->quoteName('#__jed_extensions'));

		$extensions = $db->setQuery($query)->loadObjectList() ?: [];

		$this->ioStyle->progressStart(count($extensions));

		$db->transactionStart();

		/** @var object $extension */
		foreach ($extensions as $extension)
		{
			if ($extension->requires_registration == 0)
			{
				// Only Free extensions can possibly require no registration
				$supplyOptions = [1];
			}
			else
			{
				// Requires registration: can be cloud, free, paid, or a combination of free & paid.
				$supplyOptions = $this->randomWords->bool(2)
					? [3] // Cloud
					: $this->getChanceWeighedArrayElements(
						[
							1 => 60, // Free
							2 => 50, // Paid
						],
						random_int(1, 2)
					);
			}

			foreach ($supplyOptions as $ordering => $supplyOption)
			{

				$baseUrl = 'https://' . $this->faker->domainName;

				$tags    = [];
				$numTags = random_int(0, 4);

				for ($i = 0; $i < $numTags; $i++)
				{
					$tags[] = strtolower($this->randomWords->word());
				}

				$hasDownloadIntegration = $this->randomWords->bool(90);
				$dlIntegration          = $hasDownloadIntegration
					? match ($supplyOption)
					{
						'1', 1 => $extension->requires_registration
							? 'Free but Registration required at link:'
							: 'Free Direct Download link:',
						'2', '3', 2, 3 => 'Paid purchase required at link:',
					}
					: 'None';

				$variedDatum = (object) [
					'extension_id'              => $extension->id,
					'supply_option_id'          => $supplyOption,
					'intro_text'                => $this->faker->realText(120, 3),
					'description'               => $this->faker->realTextBetween(),
					'homepage_link'             => $baseUrl,
					'download_link'             => $baseUrl . '/download',
					'demo_link'                 => $this->randomWords->bool(40) ? $baseUrl . '/demo' : null,
					'support_link'              => $this->randomWords->bool(75) ? $baseUrl . '/support' : null,
					'documentation_link'        => $this->randomWords->bool(50) ? $baseUrl . '/documentation' : null,
					'license_link'              => $baseUrl . '/license',
					'translation_link'          => $this->randomWords->bool(30) ? $baseUrl . '/languages' : null,
					'tags'                      => implode(',', $tags),
					'update_url'                => $extension->uses_updater ? $baseUrl . '/update' : null,
					'update_url_ok'             => $extension->uses_updater,
					'download_integration_type' => $dlIntegration,
					'download_integration_url'  => $hasDownloadIntegration ? $baseUrl . '/dlintegration' : null,
					'logo'                      => $extension->logo,
					'is_default_data'           => $ordering == 0 ? 1 : 0,
					'ordering'                  => $ordering,
					'state'                     => $ordering == 0
						? 1
						: ($this->randomWords->bool(98) ? 1 : 0),
					'checked_out'               => null,
					'checked_out_time'          => null,
					'created_by'                => $extension->created_by,
				];

				$db->insertObject('#__jed_extension_varied_data', $variedDatum);
			}

			$this->ioStyle->progressAdvance();
		}

		$db->transactionCommit();

		$this->ioStyle->progressFinish();
	}

	private function deleteExtensions(): void
	{
		$this->ioStyle->title('Deleting extensions, reviews, and scores.');

		$db = $this->getDatabase();
		$db->setQuery('SET FOREIGN_KEY_CHECKS = 0')->execute();
		$this->ioStyle->text('Extension images (files)');
		Folder::delete(JPATH_ROOT . '/images/jed_logos');
		Folder::create(JPATH_ROOT . '/images/jed_logos');
		$this->ioStyle->text('Extension images (database)');
		$db->truncateTable('#__jed_extension_images');
		$this->ioStyle->text('Extension scores');
		$db->truncateTable('#__jed_extension_scores');
		$this->ioStyle->text('Extension review comments');
		$db->truncateTable('#__jed_reviews_comments');
		$this->ioStyle->text('Extension reviews');
		$db->truncateTable('#__jed_reviews');
		$this->ioStyle->text('Extension varied data');
		$db->truncateTable('#__jed_extension_varied_data');
		$this->ioStyle->text('Extensions');
		$db->truncateTable('#__jed_extensions');
		$db->setQuery('SET FOREIGN_KEY_CHECKS = 1')->execute();
	}

	private function getChanceWeighedArrayElements(array $chances, string $default): array
	{
		$ret = [];

		foreach ($chances as $item => $chance)
		{
			if ($this->randomWords->bool($chance))
			{
				$ret[] = $item;
			}
		}

		if (empty($ret))
		{
			$ret = [$default];
		}

		return $ret;
	}

	private function getDevelopers(): array
	{
		$db       = $this->getDatabase();
		$subQuery = $db->getQuery(true)
		               ->select('1')
		               ->from($db->quoteName('#__jed_developers', 'd'))
		               ->where($db->quoteName('d.user_id') . ' = ' . $db->quoteName('u.id'));

		$query = $db->getQuery(true)
		            ->select([
			            $db->quoteName('id'),
			            $db->quoteName('name'),
			            $db->quoteName('registerDate'),
		            ])
		            ->from($db->quoteName('#__users', 'u'))
		            ->where('EXISTS(' . $subQuery . ')');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	private function getNonDevelopers(): array
	{
		$db       = $this->getDatabase();
		$subQuery = $db->getQuery(true)
		               ->select('1')
		               ->from($db->quoteName('#__jed_developers', 'd'))
		               ->where($db->quoteName('d.user_id') . ' = ' . $db->quoteName('u.id'));

		$query = $db->getQuery(true)
		            ->select([
			            $db->quoteName('id'),
			            $db->quoteName('registerDate'),
		            ])
		            ->from($db->quoteName('#__users', 'u'))
		            ->where('NOT EXISTS(' . $subQuery . ')');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	private function getRandomExtensionTitle(): array
	{
		while (empty($title ?? ''))
		{
			$title = $this->randomWords->combo();

			if (in_array($title, $this->createdTitles))
			{
				$title = null;

				continue;
			}

			$this->createdTitles[] = $title;
			$alias                 = ApplicationHelper::stringURLSafe($title);
		}

		return [$title, $alias];
	}

	private function getRandomLicense(): string
	{
		$licenses = [
			'GPLv2 or later', 'AGPL', 'LGPL',
		];

		$index = $this->randomWords->int(0, 2, Bias::EXP_LOW);

		return $licenses[$index];
	}

	private function loadCategories()
	{
		$db               = $this->getDatabase();
		$query            = $db->getQuery(true)
		                       ->select($db->quoteName('id'))
		                       ->from($db->quoteName('#__categories'))
		                       ->where($db->quoteName('extension') . ' = ' . $db->quote('com_jed'))
		                       ->where($db->quoteName('published') . ' = 1');
		$this->categories = $db->setQuery($query)->loadColumn();
	}
}