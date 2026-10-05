<?php

namespace App\Import;

use App\Import\Source\TradeSource;
use App\Portfolio\Entity\Account;
use App\Portfolio\Entity\Fill;
use App\Portfolio\Entity\Trade;
use App\Portfolio\Repository\AccountRepository;
use App\Portfolio\Repository\FillRepository;
use App\Portfolio\Repository\TradeRepository;
use App\Security\Entity\User;
use App\Trading\FifoTradeMatcher;
use App\Trading\UnknownContractException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Importeur unique pour tous les formats : consomme les lignes d'une TradeSource.
 * Les Trade sont importés tels quels ; les Fill sont appariées en FIFO avec toutes les exécutions déjà en base
 * du compte. Rejouer un fichier n'ajoute rien, et une erreur n'écrit rien (tout ou rien).
 */
class TradeImporter
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly FillRepository $fills,
        private readonly TradeRepository $trades,
        private readonly FifoTradeMatcher $matcher,
        private readonly TradeDeduplicator $deduplicator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param User        $owner       propriétaire des données importées
     * @param string|null $accountName compte unique (créé s'il n'existe pas), null : un compte par ligne
     *
     * @throws ImportException            si des lignes sont invalides ou un contrat est inconnu
     * @throws \InvalidArgumentException  si le fichier est illisible ou le nom de compte vide
     */
    public function import(string $path, TradeSource $source, User $owner, ?string $accountName = null, bool $dryRun = false): ImportReport
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException(sprintf('Fichier illisible : "%s".', $path));
        }

        $accountName = null === $accountName ? null : trim($accountName);
        if ('' === $accountName) {
            throw new \InvalidArgumentException('Le nom du compte est vide.');
        }

        $resolver = new AccountResolver($this->accounts, $owner, $accountName);

        /** @var list<Trade> $trades */
        $trades = [];
        /** @var list<Fill> $newFills */
        $newFills = [];
        /** @var array<int, Account> $fillAccounts comptes qui ont reçu des exécutions, par identité d'objet */
        $fillAccounts = [];
        /** @var array<int, array<string, true>> $knownIds identifiants d'exécution déjà vus, par compte */
        $knownIds = [];
        $errors = [];
        $read = 0;
        $skippedFills = 0;

        try {
            foreach ($source->read($path, $resolver) as $row) {
                ++$read;

                if (null !== $row->error) {
                    $errors[] = sprintf('%s : %s', $row->position, $row->error);
                    continue;
                }

                $record = $row->record;
                if ($record instanceof Trade) {
                    $trades[] = $record;
                    continue;
                }

                $account = $record->getAccount();
                $accountKey = spl_object_id($account);
                $fillAccounts[$accountKey] = $account;
                $knownIds[$accountKey] ??= null === $account->getId()
                    ? []
                    : array_fill_keys($this->fills->findExecutionIds($account), true);

                if (isset($knownIds[$accountKey][$record->getExecutionId()])) {
                    ++$skippedFills;
                    continue;
                }
                $knownIds[$accountKey][$record->getExecutionId()] = true;
                $newFills[] = $record;
            }
        } catch (\InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        if ([] !== $errors) {
            throw new ImportException($errors);
        }

        $openPositions = [];
        if ([] !== $fillAccounts) {
            $storedFills = [];
            foreach ($fillAccounts as $account) {
                if (null !== $account->getId()) {
                    array_push($storedFills, ...$this->fills->findForMatching($account));
                }
            }

            try {
                $matched = $this->matcher->match([...$storedFills, ...$newFills]);
            } catch (UnknownContractException $e) {
                throw new ImportException([$e->getMessage()]);
            }

            foreach ($matched->trades as $m) {
                $trades[] = $m->trade;
            }
            $openPositions = $matched->openPositions;
        }

        $storedTrades = [];
        foreach ($resolver->all() as $account) {
            if (null !== $account->getId()) {
                array_push($storedTrades, ...$this->trades->findBy(['account' => $account]));
            }
        }
        ['new' => $newTrades, 'duplicates' => $duplicateTrades] = $this->deduplicator->unseen($trades, $storedTrades);

        if (!$dryRun) {
            $this->em->wrapInTransaction(function () use ($resolver, $newFills, $newTrades): void {
                foreach ($resolver->all() as $account) {
                    $this->em->persist($account);
                }
                foreach ([...$newFills, ...$newTrades] as $entity) {
                    $this->em->persist($entity);
                }
            });
        }

        return new ImportReport(
            rowsRead: $read,
            fillsInserted: count($newFills),
            fillsSkipped: $skippedFills,
            tradesInserted: count($newTrades),
            tradesSkipped: $duplicateTrades,
            accountsCreated: $resolver->createdNames(),
            openPositions: $openPositions,
            dryRun: $dryRun,
        );
    }
}
