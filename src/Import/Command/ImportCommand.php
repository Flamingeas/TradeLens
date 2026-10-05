<?php

namespace App\Import\Command;

use App\Import\ImportException;
use App\Import\Source\SourceRegistry;
use App\Import\TradeImporter;
use App\Security\Repository\UserRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe un fichier de courtier dont le format est reconnu automatiquement (ou choisi avec --source).
 * L'option --user est obligatoire.
 */
#[AsCommand(
    name: 'app:import',
    description: 'Importe un fichier de trades ou d\'exécutions dans un seul compte (ou un par compte avec --par-compte). Rejouable sans doublons.',
    aliases: ['app:import:csv', 'app:import:ods'],
)]
class ImportCommand
{
    public function __construct(
        private readonly TradeImporter $importer,
        private readonly SourceRegistry $sources,
        private readonly UserRepository $users,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Chemin du fichier, ex. data/MFFU.csv ou data/TopStep.ods')] string $file,
        #[Argument(description: 'Compte unique qui reçoit tout (créé s\'il n\'existe pas). Par défaut : le nom du fichier, ex. "MFFU"')] ?string $account = null,
        #[Option(description: 'Format du fichier (mffu, topstep...) ; reconnu automatiquement si omis')] ?string $source = null,
        #[Option(description: 'Conserve un compte par ligne, quand le format en contient, au lieu d\'un compte unique')] bool $parCompte = false,
        #[Option(description: 'Analyse le fichier sans rien écrire en base')] bool $dryRun = false,
        #[Option(description: "E-mail de l'utilisateur à qui appartiennent les données (obligatoire)")] ?string $user = null,
    ): int {
        if (null === $user || '' === trim($user)) {
            $io->error("Précisez le propriétaire des données : --user=adresse@mail.com (l'utilisateur doit avoir créé son compte).");

            return Command::INVALID;
        }
        $owner = $this->users->findOneByEmail($user);
        if (null === $owner) {
            $io->error(sprintf("Aucun utilisateur avec l'adresse \"%s\". Il doit d'abord créer son compte sur le site.", $user));

            return Command::INVALID;
        }

        if (!is_file($file) || !is_readable($file)) {
            $io->error(sprintf('Fichier illisible : "%s".', $file));

            return Command::INVALID;
        }

        try {
            $format = null !== $source ? $this->sources->get($source) : $this->sources->detect($file);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        if ($parCompte && null !== $account) {
            $io->error('--par-compte et un nom de compte sont incompatibles : choisissez l\'un ou l\'autre.');

            return Command::INVALID;
        }
        if ($parCompte && !$format->providesAccounts()) {
            $io->error(sprintf('Le format "%s" ne contient pas de compte : --par-compte est impossible.', $format->key()));
            return Command::INVALID;
        }

        $target = $parCompte ? null : ($account ?? pathinfo($file, PATHINFO_FILENAME));

        $io->writeln(sprintf('Utilisateur : <info>%s</info>', $owner->getEmail()));
        $io->writeln(sprintf('Format : <info>%s</info> (%s)', $format->key(), $format->label()));
        $io->writeln(null === $target
            ? 'Comptes : <info>un par compte du fichier</info>'
            : sprintf('Compte : <info>%s</info>', $target));

        try {
            ImportReportPrinter::report($io, $this->importer->import($file, $format, $owner, $target, $dryRun));
        } catch (ImportException $e) {
            ImportReportPrinter::failure($io, $e);

            return Command::FAILURE;
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        return Command::SUCCESS;
    }
}
