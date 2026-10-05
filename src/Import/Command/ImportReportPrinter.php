<?php

namespace App\Import\Command;

use App\Import\ImportException;
use App\Import\ImportReport;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Affichage commun aux commandes d'import.
 *
 * @internal
 */
final class ImportReportPrinter
{
    private const MAX_ERRORS_SHOWN = 20;

    public static function report(SymfonyStyle $io, ImportReport $report): void
    {
        $rows = [['Lignes lues', $report->rowsRead]];

        if ($report->fillsInserted + $report->fillsSkipped > 0) {
            $rows[] = [$report->dryRun ? 'Exécutions à ajouter' : 'Exécutions ajoutées', $report->fillsInserted];
            $rows[] = ['Exécutions déjà en base (ignorées)', $report->fillsSkipped];
        }
        $rows[] = [$report->dryRun ? 'Trades à ajouter' : 'Trades ajoutés', $report->tradesInserted];
        $rows[] = ['Trades déjà en base (ignorés)', $report->tradesSkipped];
        if ([] !== $report->accountsCreated) {
            $rows[] = [($report->dryRun ? 'Comptes qui seraient créés' : 'Comptes créés'), implode(', ', $report->accountsCreated)];
        }

        $io->table(['Bilan', ''], $rows);

        foreach ($report->openPositions as $where => $net) {
            $io->warning(sprintf('Position encore ouverte : %s, %+d contrat(s). Aucun trade n\'en est tiré tant qu\'elle n\'est pas fermée.', $where, $net));
        }

        if ($report->dryRun) {
            $io->note('Simulation : rien n\'a été écrit en base. Relancez sans --dry-run pour importer.');
        } else {
            $io->success('Import terminé.');
        }
    }

    public static function failure(SymfonyStyle $io, ImportException $e): void
    {
        $io->error($e->getMessage());
        $shown = array_slice($e->errors, 0, self::MAX_ERRORS_SHOWN);
        $io->listing($shown);
        if (count($e->errors) > count($shown)) {
            $io->writeln(sprintf('… et %d autre(s) erreur(s).', count($e->errors) - count($shown)));
        }
    }
}
