<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\SiteTranslation;
use App\Enum\SiteLanguage;
use App\Repository\SiteTranslationRepository;
use App\SiteUi\SiteUiDefaults;
use App\SiteUi\SiteUiStrings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:site-ui:seed',
    description: 'Insert or refresh site UI translations (EN + localized DE/ES/UA from locale files).',
)]
final class SeedSiteUiTranslationsCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption(
            'update',
            'u',
            InputOption::VALUE_NONE,
            'Overwrite existing database texts with the current catalog (use after locale file changes).',
        );
    }

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteTranslationRepository $translations,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $inserted = 0;
        $updated = 0;
        $doUpdate = (bool) $input->getOption('update');

        foreach (array_keys(SiteUiDefaults::ALIASES) as $alias) {
            foreach (SiteLanguage::cases() as $language) {
                $text = SiteUiStrings::text($language, $alias);

                $existing = $this->translations->findOneBy([
                    'alias' => $alias,
                    'languageType' => $language,
                ]);

                if (null !== $existing) {
                    if ($doUpdate && $existing->getTranslate() !== $text) {
                        $existing->setTranslate($text);
                        ++$updated;
                    }

                    continue;
                }

                $row = new SiteTranslation();
                $row->setAlias($alias);
                $row->setLanguageType($language);
                $row->setTranslate($text);
                $this->em->persist($row);
                ++$inserted;
            }
        }

        $this->em->flush();

        $io->success(\sprintf(
            'Inserted %d new row(s).%s',
            $inserted,
            $doUpdate ? \sprintf(' Updated %d existing row(s).', $updated) : ' (run with --update to refresh existing texts.)',
        ));

        return Command::SUCCESS;
    }
}
