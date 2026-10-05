<?php

namespace App\Import;

use App\Import\Source\SourceRegistry;
use App\Security\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Importe un fichier reçu par un formulaire : contrôle l'envoi, le copie sous un nom sûr, lance l'import
 * puis supprime la copie.
 */
class UploadedFileImporter
{
    private const EXTENSIONS = ['csv', 'ods'];
    private const ACCOUNT_NAME_MAX = 100;

    public function __construct(
        private readonly TradeImporter $importer,
        private readonly SourceRegistry $sources,
    ) {
    }

    /**
     * @param string|null $accountName compte unique ; vide : le nom du fichier
     * @param bool        $perAccount  un compte par compte du fichier (formats qui en contiennent)
     *
     * @throws ImportException            si des lignes sont invalides ou un contrat est inconnu
     * @throws \InvalidArgumentException  si le fichier ou les options sont refusés (message destiné à l'utilisateur)
     */
    public function import(UploadedFile $file, User $owner, ?string $accountName = null, bool $perAccount = false): UploadResult
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Envoi du fichier impossible : '.$file->getErrorMessage());
        }

        $originalName = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Seuls les fichiers .csv et .ods sont acceptés.');
        }

        $accountName = $this->cleanName($accountName);
        if ($perAccount && null !== $accountName) {
            throw new \InvalidArgumentException('Choisissez un nom de compte OU « un compte par compte du fichier », pas les deux.');
        }
        if (null !== $accountName && mb_strlen($accountName) > self::ACCOUNT_NAME_MAX) {
            throw new \InvalidArgumentException(sprintf('Le nom du compte est trop long (%d caractères maximum).', self::ACCOUNT_NAME_MAX));
        }

        // Nom aléatoire, jamais celui du navigateur.
        $path = $file->move(sys_get_temp_dir(), sprintf('tradelens-import-%s.%s', bin2hex(random_bytes(8)), $extension))->getPathname();

        try {
            try {
                $source = $this->sources->detect($path);
            } catch (\InvalidArgumentException) {
                throw new \InvalidArgumentException(sprintf(
                    'Format non reconnu. Formats acceptés : %s.',
                    implode(' ; ', array_map(static fn ($s): string => $s->label(), $this->sources->all())),
                ));
            }

            if ($perAccount && !$source->providesAccounts()) {
                throw new \InvalidArgumentException(sprintf('Ce format (%s) ne contient pas de compte : indiquez un nom de compte ou laissez le champ vide.', $source->label()));
            }

            $target = $perAccount
                ? null
                : ($accountName ?? mb_substr($this->cleanName(pathinfo($originalName, PATHINFO_FILENAME)) ?? 'Import', 0, self::ACCOUNT_NAME_MAX));

            return new UploadResult($this->importer->import($path, $source, $owner, $target), $source, $target);
        } finally {
            @unlink($path);
        }
    }

    private function cleanName(?string $name): ?string
    {
        $clean = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $name) ?? '');

        return '' === $clean ? null : $clean;
    }
}
