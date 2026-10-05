<?php

namespace App\Import\Controller;

use App\Import\ImportException;
use App\Import\UploadedFileImporter;
use App\Import\UploadResult;
use App\Portfolio\AccountLocator;
use App\Portfolio\Repository\AccountRepository;
use App\Security\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ImportController extends AbstractController
{
    private const MAX_ERRORS_SHOWN = 10;

    /**
     * Importe le fichier du formulaire pour l'utilisateur connecté, puis redirige vers le tableau de bord ;
     * le bilan passe par des messages flash.
     */
    #[Route('/import', name: 'app_import', methods: ['POST'])]
    public function upload(
        Request $request,
        UploadedFileImporter $importer,
        AccountRepository $accounts,
        AccountLocator $locator,
        #[CurrentUser] User $user,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('import', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expiré. Rechargez la page puis réessayez.');

            return $this->redirectToRoute('app_dashboard');
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('danger', 'Choisissez un fichier .csv ou .ods à importer.');

            return $this->redirectToRoute('app_dashboard');
        }

        try {
            $result = $importer->import(
                $file,
                $user,
                (string) $request->request->get('account', ''),
                $request->request->getBoolean('per_account'),
            );
        } catch (ImportException $e) {
            $this->addFlash('danger', $e->getMessage());
            foreach (array_slice($e->errors, 0, self::MAX_ERRORS_SHOWN) as $error) {
                $this->addFlash('danger', $error);
            }
            if (count($e->errors) > self::MAX_ERRORS_SHOWN) {
                $this->addFlash('danger', sprintf('… et %d autre(s) erreur(s).', count($e->errors) - self::MAX_ERRORS_SHOWN));
            }

            return $this->redirectToRoute('app_dashboard');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_dashboard');
        }

        $this->flashReport($result);

        // Compte unique : on affiche directement son analyse. Sinon, tous les comptes.
        $account = null === $result->accountName ? null : $accounts->findOneByOwnerAndName($user, $result->accountName);

        return $this->redirectToRoute('app_dashboard', null === $account ? [] : ['account' => $locator->slugOf($user, $account)]);
    }

    private function flashReport(UploadResult $result): void
    {
        $report = $result->report;

        if (0 === $report->tradesInserted && $report->tradesSkipped > 0) {
            $this->addFlash('info', sprintf('Ce fichier (%s) est déjà importé : aucun nouveau trade.', $result->source->label()));
        } else {
            $details = [sprintf('%d trade(s) ajouté(s)', $report->tradesInserted)];
            if ($report->fillsInserted > 0) {
                $details[] = sprintf('%d exécution(s) enregistrée(s)', $report->fillsInserted);
            }
            if ($report->tradesSkipped > 0) {
                $details[] = sprintf('%d déjà présent(s)', $report->tradesSkipped);
            }
            $this->addFlash('success', sprintf(
                'Import terminé (%s) : %s. Voici l\'analyse%s.',
                $result->source->label(),
                implode(', ', $details),
                null === $result->accountName ? ' de tous les comptes' : sprintf(' du compte « %s »', $result->accountName),
            ));
        }

        foreach ($report->openPositions as $where => $net) {
            $this->addFlash('warning', sprintf('Position encore ouverte : %s, %+d contrat(s). Elle ne donne un trade qu\'une fois fermée.', $where, $net));
        }
    }
}
