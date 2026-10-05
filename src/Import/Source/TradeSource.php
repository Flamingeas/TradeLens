<?php

namespace App\Import\Source;

use App\Import\AccountResolver;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Un format de fichier d'un courtier ou d'une plateforme.
 * Pour en ajouter un : implémenter cette interface (enregistrée par l'étiquette "app.import.source").
 * Une source ne fait que lire et convertir : elle produit des Trade et/ou des Fill.
 */
#[AutoconfigureTag('app.import.source')]
interface TradeSource
{
    /** Identifiant court saisi avec --source, ex. "mffu". */
    public function key(): string;

    /** Description affichée dans les messages et la liste des formats. */
    public function label(): string;

    /** Le fichier contient-il une colonne de compte ? Sinon, un compte unique est requis. */
    public function providesAccounts(): bool;

    /** Ce fichier est-il dans ce format ? Rapide : extension ou en-tête seulement. */
    public function supports(string $path): bool;

    /**
     * Lit le fichier et renvoie ses lignes converties ; une ligne invalide est renvoyée avec ImportRow::error().
     *
     * @return \Generator<int, ImportRow>
     *
     * @throws \InvalidArgumentException si le fichier est corrompu
     */
    public function read(string $path, AccountResolver $accounts): \Generator;
}
